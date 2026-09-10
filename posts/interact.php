<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Session Authentication Safeguard
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

require_once __DIR__ . '/../includes/database.php';

$userId = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit();
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Dynamic Display Name SQL Helper
function getDisplayNameColumnSelect($tableAlias = 'u') {
    return "COALESCE(NULLIF({$tableAlias}.username, ''), {$tableAlias}.email, 'Viking')";
}

// Dynamic Avatar Column Detector & Path Formatter
function getAvatarColumnSelect($pdo, $tableAlias = 'u') {
    static $avatarColumn = null;
    if ($avatarColumn === null) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'");
            if ($stmt->fetch()) {
                $avatarColumn = 'avatar';
            } else {
                $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_url'");
                $avatarColumn = $stmt->fetch() ? 'avatar_url' : null;
            }
        } catch (Throwable $e) {
            $avatarColumn = null;
        }
    }

    if ($avatarColumn) {
        return "{$tableAlias}.{$avatarColumn}";
    }
    return "'/assets/images/default_avatar.png'";
}

// Helper to normalize relative upload paths for JSON output
function formatAvatarUrl($avatarPath) {
    if (empty($avatarPath) || $avatarPath === 'default_avatar.png') {
        return '/assets/images/default_avatar.png';
    }
    if (strpos($avatarPath, '/') === 0 || strpos($avatarPath, 'http') === 0) {
        return $avatarPath;
    }
    return '/uploads/profiles/' . $avatarPath;
}

// -------------------------------------------------------------------
// 1. FETCH COMMENTS
// -------------------------------------------------------------------
if ($action === 'fetch_comments') {
    $postId = (int)($_GET['post_id'] ?? 0);
    if (!$postId) {
        echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
        exit();
    }

    try {
        $displayNameSql = getDisplayNameColumnSelect('u');
        $avatarSql      = getAvatarColumnSelect($pdo, 'u');

        $stmt = $pdo->prepare("
            SELECT 
                c.id, 
                c.content, 
                c.created_at, 
                u.id AS user_id, 
                {$displayNameSql} AS display_name, 
                {$avatarSql} AS raw_avatar 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = ? 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$postId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($comments as &$comment) {
            $comment['avatar_url'] = formatAvatarUrl($comment['raw_avatar'] ?? '');
            unset($comment['raw_avatar']);
            $comment['formatted_time'] = date('M j, Y \a\t g:i a', strtotime($comment['created_at']));
            $comment['content'] = nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8'));
        }

        echo json_encode([
            'success'  => true, 
            'comments' => $comments,
            'count'    => count($comments)
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false, 
            'error'   => 'FETCH ERROR: ' . $e->getMessage()
        ]);
    }
    exit();
}

// -------------------------------------------------------------------
// 2. ADD COMMENT
// -------------------------------------------------------------------
if ($action === 'add_comment') {
    $postId  = (int)($_POST['post_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (!$postId || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Comment content cannot be empty']);
        exit();
    }

    try {
        // A. Insert Comment Record
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$postId, $userId, $content]);
        $newCommentId = $pdo->lastInsertId();

        // B. Fetch Post Owner Details
        $displayNameSql = getDisplayNameColumnSelect('u');
        $postStmt = $pdo->prepare("
            SELECT p.user_id AS owner_id, u.email AS owner_email, {$displayNameSql} AS owner_name 
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.id = ?
        ");
        $postStmt->execute([$postId]);
        $postOwner = $postStmt->fetch(PDO::FETCH_ASSOC);

        // C. Fetch Commenter Details
        $commenterDisplayNameSql = getDisplayNameColumnSelect('users');
        $commenterAvatarSql      = getAvatarColumnSelect($pdo, 'users');

        $commenterStmt = $pdo->prepare("
            SELECT {$commenterDisplayNameSql} AS display_name, {$commenterAvatarSql} AS raw_avatar 
            FROM users 
            WHERE id = ?
        ");
        $commenterStmt->execute([$userId]);
        $commenter = $commenterStmt->fetch(PDO::FETCH_ASSOC);

        $commenterAvatar = formatAvatarUrl($commenter['raw_avatar'] ?? '');
        $commenterName   = $commenter['display_name'] ?? 'Viking';

        // D. Notifications
        if ($postOwner && (int)$postOwner['owner_id'] !== $userId) {
            $ownerId    = (int)$postOwner['owner_id'];
            $ownerEmail = $postOwner['owner_email'];
            $notifMsg   = "{$commenterName} commented on your signal.";

            // In-App Notification Engine with Unread Status Flag
            try {
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, actor_id, post_id, type, message, is_read, created_at) 
                    VALUES (?, ?, ?, 'comment', ?, 0, NOW())
                ");
                $notifStmt->execute([$ownerId, $userId, $postId, $notifMsg]);
            } catch (Throwable $notifEx) {
                // Fallback for schemas without is_read column
                try {
                    $notifStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, actor_id, post_id, type, message, created_at) 
                        VALUES (?, ?, ?, 'comment', ?, NOW())
                    ");
                    $notifStmt->execute([$ownerId, $userId, $postId, $notifMsg]);
                } catch (Throwable $e) {}
            }

            // Email Notification Dispatch
            if (!empty($ownerEmail) && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $subject = "New Signal Comment from " . $commenterName;
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                $headers .= 'From: VALKYRIN <no-reply@beardedviking.org>' . "\r\n";

                $emailBody = "
                    <div style='font-family: Arial, sans-serif; background: #0f1115; color: #e0e0e0; padding: 20px; border-radius: 8px;'>
                        <h2 style='color: #4facfe; margin-top: 0;'>New Signal Log Received</h2>
                        <p>Hail, <strong>" . htmlspecialchars($postOwner['owner_name'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                        <p><strong>" . htmlspecialchars($commenterName, ENT_QUOTES, 'UTF-8') . "</strong> left a comment on your signal:</p>
                        <blockquote style='background: #1a1d24; border-left: 4px solid #4facfe; margin: 15px 0; padding: 12px; color: #fff;'>
                            \"" . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . "\"
                        </blockquote>
                        <p style='margin-top: 20px;'>
                            <a href='https://beardedviking.org/users/dashboard.php#post-" . $postId . "' style='background: #4facfe; color: #fff; padding: 10px 18px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>View Signal Log</a>
                        </p>
                    </div>
                ";

                @mail($ownerEmail, $subject, $emailBody, $headers);
            }
        }

        echo json_encode([
            'success'        => true,
            'comment_id'     => $newCommentId,
            'content'        => nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')),
            'display_name'   => $commenterName,
            'avatar_url'     => $commenterAvatar,
            'formatted_time' => date('M j, Y \a\t g:i a')
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false, 
            'error'   => 'ADD COMMENT ERROR: ' . $e->getMessage()
        ]);
    }
    exit();
}

// -------------------------------------------------------------------
// 3. TOGGLE REACTION
// -------------------------------------------------------------------
if ($action === 'toggle_reaction') {
    $postId       = (int)($_POST['post_id'] ?? 0);
    $reactionType = trim($_POST['reaction_type'] ?? '');
    $allowedTypes = ['valhalla', 'honor', 'dishonor', 'strike'];

    if (!$postId || !in_array($reactionType, $allowedTypes, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid reaction parameter']);
        exit();
    }

    try {
        $checkAuthor = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $checkAuthor->execute([$postId]);
        $postOwnerId = (int)$checkAuthor->fetchColumn();

        if ($postOwnerId === $userId) {
            echo json_encode(['success' => false, 'error' => 'Authors cannot react to their own signals']);
            exit();
        }

        $stmt = $pdo->prepare("SELECT id, reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $activeUserReaction = null;

        if ($existing) {
            if ($existing['reaction_type'] === $reactionType) {
                $del = $pdo->prepare("DELETE FROM post_reactions WHERE id = ?");
                $del->execute([$existing['id']]);
            } else {
                $upd = $pdo->prepare("UPDATE post_reactions SET reaction_type = ? WHERE id = ?");
                $upd->execute([$reactionType, $existing['id']]);
                $activeUserReaction = $reactionType;
            }
        } else {
            $ins = $pdo->prepare("INSERT INTO post_reactions (post_id, user_id, reaction_type, created_at) VALUES (?, ?, ?, NOW())");
            $ins->execute([$postId, $userId, $reactionType]);
            $activeUserReaction = $reactionType;

            if ($postOwnerId && $postOwnerId !== $userId) {
                try {
                    $displayNameSql = getDisplayNameColumnSelect('users');
                    $userStmt = $pdo->prepare("SELECT {$displayNameSql} FROM users WHERE id = ?");
                    $userStmt->execute([$userId]);
                    $actorName = $userStmt->fetchColumn() ?: 'Viking';

                    $notifMsg = "{$actorName} reacted to your signal with " . ucfirst($reactionType) . ".";
                    $notifStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, actor_id, post_id, type, message, is_read, created_at) 
                        VALUES (?, ?, ?, 'reaction', ?, 0, NOW())
                    ");
                    $notifStmt->execute([$postOwnerId, $userId, $postId, $notifMsg]);
                } catch (Throwable $notifEx) {
                    try {
                        $notifStmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, actor_id, post_id, type, message, created_at) 
                            VALUES (?, ?, ?, 'reaction', ?, NOW())
                        ");
                        $notifStmt->execute([$postOwnerId, $userId, $postId, $notifMsg]);
                    } catch (Throwable $e) {}
                }
            }
        }

        $countStmt = $pdo->prepare("
            SELECT reaction_type, COUNT(*) as cnt 
            FROM post_reactions 
            WHERE post_id = ? 
            GROUP BY reaction_type
        ");
        $countStmt->execute([$postId]);
        $rawCounts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $breakdown = [
            'valhalla' => (int)($rawCounts['valhalla'] ?? 0),
            'honor'    => (int)($rawCounts['honor'] ?? 0),
            'dishonor' => (int)($rawCounts['dishonor'] ?? 0),
            'strike'   => (int)($rawCounts['strike'] ?? 0)
        ];

        echo json_encode([
            'success'       => true,
            'user_reaction' => $activeUserReaction,
            'breakdown'     => $breakdown
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false, 
            'error'   => 'REACTION ERROR: ' . $e->getMessage()
        ]);
    }
    exit();
}

// -------------------------------------------------------------------
// 4. FETCH COMMENT COUNT
// -------------------------------------------------------------------
if ($action === 'get_comment_count') {
    $postId = (int)($_GET['post_id'] ?? 0);
    if (!$postId) {
        echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?");
        $stmt->execute([$postId]);
        $count = (int)$stmt->fetchColumn();

        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false, 
            'error'   => 'COUNT ERROR: ' . $e->getMessage()
        ]);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action requested']);
exit();