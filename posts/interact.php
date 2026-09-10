<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

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

function getDisplayNameColumnSelect($pdo, $tableAlias = 'u') {
    static $hasDisplayName = null;
    if ($hasDisplayName === null) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'display_name'");
            $hasDisplayName = (bool)$stmt->fetch();
        } catch (Throwable $e) {
            $hasDisplayName = false;
        }
    }

    if ($hasDisplayName) {
        return "COALESCE(NULLIF({$tableAlias}.display_name, ''), {$tableAlias}.username, 'Viking')";
    }
    return "{$tableAlias}.username";
}

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
        $displayNameSql = getDisplayNameColumnSelect($pdo, 'u');
        $avatarSql      = getAvatarColumnSelect($pdo, 'u');

        $stmt = $pdo->prepare("
            SELECT 
                c.id, 
                c.content, 
                c.created_at, 
                u.id AS user_id, 
                u.username,
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
            
            // Highlight user tags (@username)
            $formattedText = htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8');
            $formattedText = preg_replace('/@([a-zA-Z0-9_]+)/', '<span class="text-info fw-bold">@$1</span>', $formattedText);
            $comment['content'] = nl2br($formattedText);
        }

        echo json_encode([
            'success'  => true, 
            'comments' => $comments,
            'count'    => count($comments)
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'FETCH ERROR: ' . $e->getMessage()]);
    }
    exit();
}

// -------------------------------------------------------------------
// 2. ADD COMMENT & PROCESS TAGS
// -------------------------------------------------------------------
if ($action === 'add_comment') {
    $postId  = (int)($_POST['post_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (!$postId || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Comment content cannot be empty']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$postId, $userId, $content]);
        $newCommentId = $pdo->lastInsertId();

        // Fetch Post Owner Details
        $displayNameSql = getDisplayNameColumnSelect($pdo, 'u');
        $postStmt = $pdo->prepare("
            SELECT p.user_id AS owner_id, u.email AS owner_email, {$displayNameSql} AS owner_name 
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.id = ?
        ");
        $postStmt->execute([$postId]);
        $postOwner = $postStmt->fetch(PDO::FETCH_ASSOC);

        // Fetch Commenter Details
        $commenterDisplayNameSql = getDisplayNameColumnSelect($pdo, 'users');
        $commenterAvatarSql      = getAvatarColumnSelect($pdo, 'users');

        $commenterStmt = $pdo->prepare("
            SELECT username, {$commenterDisplayNameSql} AS display_name, {$commenterAvatarSql} AS raw_avatar 
            FROM users 
            WHERE id = ?
        ");
        $commenterStmt->execute([$userId]);
        $commenter = $commenterStmt->fetch(PDO::FETCH_ASSOC);

        $commenterAvatar = formatAvatarUrl($commenter['raw_avatar'] ?? '');
        $commenterName   = $commenter['display_name'] ?? 'Viking';

        // Notify Post Owner
        if ($postOwner && (int)$postOwner['owner_id'] !== $userId) {
            $ownerId    = (int)$postOwner['owner_id'];
            $ownerEmail = $postOwner['owner_email'];
            $notifMsg   = "{$commenterName} commented on your signal.";

            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, actor_id, entity_id, type, message, is_read, created_at) 
                VALUES (?, ?, ?, 'post_comment', ?, 0, NOW())
            ");
            $notifStmt->execute([$ownerId, $userId, $postId, $notifMsg]);

            if (!empty($ownerEmail) && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $subject = "New Signal Comment from " . $commenterName;
                $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: VALKYRIN <no-reply@beardedviking.org>\r\n";
                $emailBody = "
                    <div style='font-family: Arial, sans-serif; background: #0f1115; color: #e0e0e0; padding: 20px; border-radius: 8px;'>
                        <h2 style='color: #4facfe; margin-top: 0;'>New Signal Log Received</h2>
                        <p>Hail, <strong>" . htmlspecialchars($postOwner['owner_name'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                        <p><strong>" . htmlspecialchars($commenterName, ENT_QUOTES, 'UTF-8') . "</strong> left a comment on your signal:</p>
                        <blockquote style='background: #1a1d24; border-left: 4px solid #4facfe; margin: 15px 0; padding: 12px; color: #fff;'>
                            \"" . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . "\"
                        </blockquote>
                        <p style='margin-top: 20px;'>
                            <a href='https://beardedviking.org/feed.php#post-" . $postId . "' style='background: #4facfe; color: #fff; padding: 10px 18px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>View Signal Log</a>
                        </p>
                    </div>
                ";
                @mail($ownerEmail, $subject, $emailBody, $headers);
            }
        }

        // Process User Tagging (@mentions)
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        if (!empty($matches[1])) {
            $taggedUsernames = array_unique($matches[1]);
            $tagStmt = $pdo->prepare("SELECT id, email, username, " . getDisplayNameColumnSelect($pdo) . " AS display_name FROM users WHERE username = ?");
            
            foreach ($taggedUsernames as $taggedUsername) {
                $tagStmt->execute([$taggedUsername]);
                $taggedUser = $tagStmt->fetch(PDO::FETCH_ASSOC);

                if ($taggedUser && (int)$taggedUser['id'] !== $userId && (int)$taggedUser['id'] !== (int)($postOwner['owner_id'] ?? 0)) {
                    $taggedId = (int)$taggedUser['id'];
                    $taggedMsg = "{$commenterName} tagged you in a comment.";

                    $notifTagStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, actor_id, entity_id, type, message, is_read, created_at) 
                        VALUES (?, ?, ?, 'mention', ?, 0, NOW())
                    ");
                    $notifTagStmt->execute([$taggedId, $userId, $postId, $taggedMsg]);

                    if (!empty($taggedUser['email']) && filter_var($taggedUser['email'], FILTER_VALIDATE_EMAIL)) {
                        $subject = "You were tagged in a signal by " . $commenterName;
                        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: VALKYRIN <no-reply@beardedviking.org>\r\n";
                        $emailBody = "
                            <div style='font-family: Arial, sans-serif; background: #0f1115; color: #e0e0e0; padding: 20px; border-radius: 8px;'>
                                <h2 style='color: #4facfe; margin-top: 0;'>Tagged in Signal Transmission</h2>
                                <p>Hail, <strong>" . htmlspecialchars($taggedUser['display_name'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                                <p><strong>" . htmlspecialchars($commenterName, ENT_QUOTES, 'UTF-8') . "</strong> tagged you in a comment:</p>
                                <blockquote style='background: #1a1d24; border-left: 4px solid #4facfe; margin: 15px 0; padding: 12px; color: #fff;'>
                                    \"" . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . "\"
                                </blockquote>
                                <p style='margin-top: 20px;'>
                                    <a href='https://beardedviking.org/feed.php#post-" . $postId . "' style='background: #4facfe; color: #fff; padding: 10px 18px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>View Transmission</a>
                                </p>
                            </div>
                        ";
                        @mail($taggedUser['email'], $subject, $emailBody, $headers);
                    }
                }
            }
        }

        $pdo->commit();

        $formattedText = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $formattedText = preg_replace('/@([a-zA-Z0-9_]+)/', '<span class="text-info fw-bold">@$1</span>', $formattedText);

        echo json_encode([
            'success'        => true,
            'comment_id'     => $newCommentId,
            'content'        => nl2br($formattedText),
            'display_name'   => $commenterName,
            'avatar_url'     => $commenterAvatar,
            'formatted_time' => date('M j, Y \a\t g:i a')
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'ADD COMMENT ERROR: ' . $e->getMessage()]);
    }
    exit();
}

// -------------------------------------------------------------------
// 3. CAST REACTION (STRICT SINGLE REACTION, PERMANENT LOCK)
// -------------------------------------------------------------------
// 3. CAST REACTION
if ($action === 'toggle_reaction') {
    $postId       = (int)($_POST['post_id'] ?? 0);
    $reactionType = strtolower(trim($_POST['reaction_type'] ?? ''));
    $allowedTypes = ['valkyrie', 'commander', 'shieldbearer', 'berserker'];

    if (!$postId || !in_array($reactionType, $allowedTypes, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid reaction parameter']);
        exit();
    }


    try {
        // Fetch Post Owner Info
        // Fetch Post Owner Info with COALESCE safe fallback
        $checkAuthor = $pdo->prepare("
            SELECT 
                p.user_id, 
                u.email, 
                COALESCE(NULLIF(u.display_name, ''), u.username) AS owner_name 
            FROM posts p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.id = ?
        ");
        $checkAuthor->execute([$postId]);
        $postAuthor = $checkAuthor->fetch(PDO::FETCH_ASSOC);

        if (!$postAuthor) {
            echo json_encode(['success' => false, 'error' => 'Post not found']);
            exit();
        }

        $postOwnerId = (int)$postAuthor['user_id'];

        if ($postOwnerId === $userId) {
            echo json_encode(['success' => false, 'error' => 'Authors cannot react to their own signals']);
            exit();
        }

        // Check for Existing Reaction
        $stmt = $pdo->prepare("SELECT id, reaction_type FROM post_reactions WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode([
                'success' => false, 
                'error'   => 'You have already reacted to this signal. Your reaction is locked.'
            ]);
            exit();
        }

        // Insert Reaction
        // Explicitly bind reaction_type
        $ins = $pdo->prepare("INSERT INTO post_reactions (post_id, user_id, reaction_type, created_at) VALUES (:post_id, :user_id, :reaction_type, NOW())");
        $ins->execute([
            ':post_id'       => $postId,
            ':user_id'       => $userId,
            ':reaction_type' => $reactionType
        ]);

        // Fetch Reactor Details
        $userStmt = $pdo->prepare("SELECT " . getDisplayNameColumnSelect($pdo) . " AS actor_name FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $actorName = $userStmt->fetchColumn() ?: 'Viking';

        // Notify Author via In-App Browser Notification
        $notifMsg = "{$actorName} reacted to your signal with " . strtoupper($reactionType) . ".";
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, actor_id, entity_id, type, message, is_read, created_at) 
            VALUES (?, ?, ?, 'post_reaction', ?, 0, NOW())
        ");
        $notifStmt->execute([$postOwnerId, $userId, $postId, $notifMsg]);

        // Send HTML Email to Author
        if (!empty($postAuthor['email']) && filter_var($postAuthor['email'], FILTER_VALIDATE_EMAIL)) {
            $subject = "New Signal Reaction from " . $actorName;
            $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: VALKYRIN <no-reply@beardedviking.org>\r\n";
            $emailBody = "
                <div style='font-family: Arial, sans-serif; background: #0f1115; color: #e0e0e0; padding: 20px; border-radius: 8px;'>
                    <h2 style='color: #4facfe; margin-top: 0;'>New Reaction Received</h2>
                    <p>Hail, <strong>" . htmlspecialchars($postAuthor['owner_name'], ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                    <p><strong>" . htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') . "</strong> reacted to your signal with <strong>" . strtoupper($reactionType) . "</strong>!</p>
                    <p style='margin-top: 20px;'>
                        <a href='https://beardedviking.org/feed.php#post-" . $postId . "' style='background: #4facfe; color: #fff; padding: 10px 18px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>View Signal Broadcast</a>
                    </p>
                </div>
            ";
            @mail($postAuthor['email'], $subject, $emailBody, $headers);
        }

        // Return Updated Breakdown Counts
        $countStmt = $pdo->prepare("
            SELECT reaction_type, COUNT(*) as cnt 
            FROM post_reactions 
            WHERE post_id = ? 
            GROUP BY reaction_type
        ");
        $countStmt->execute([$postId]);
        $rawCounts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $breakdown = [
            'valkyrie'     => (int)($rawCounts['valkyrie'] ?? 0),
            'commander'    => (int)($rawCounts['commander'] ?? 0),
            'shieldbearer' => (int)($rawCounts['shieldbearer'] ?? 0),
            'berserker'    => (int)($rawCounts['berserker'] ?? 0)
        ];

        echo json_encode([
            'success'       => true,
            'user_reaction' => $reactionType,
            'has_reacted'   => true,
            'breakdown'     => $breakdown
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'REACTION ERROR: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action requested']);
exit();