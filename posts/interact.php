<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// -----------------------------------------------------------------------------
// 1. SECURITY & INITIALIZATION
// -----------------------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

require_once __DIR__ . '/../includes/database.php'; // Provides PDO instance $pdo

$currentUserId = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

// Point values map for reactions
$pointWeights = [
    'valhalla' => 10,
    'honor'    => 5,
    'dishonor' => -2,
    'strike'   => -5
];

// Helper Function: Send Email Alerts
function sendNotificationEmail($toEmail, $subject, $bodyText) {
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $headers  = "From: VALKYRIN System <noreply@beardedviking.org>\r\n";
    $headers .= "Reply-To: no-reply@beardedviking.org\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($toEmail, $subject, $bodyText, $headers);
}

// -----------------------------------------------------------------------------
// 2. ROUTE: TOGGLE / CAST REACTION
// -----------------------------------------------------------------------------
if ($action === 'toggle_reaction') {
    $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $reactionType = $_POST['reaction_type'] ?? '';

    if ($postId <= 0 || !array_key_exists($reactionType, $pointWeights)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Check post existence & ownership
        $stmt = $pdo->prepare("
            SELECT p.id, p.user_id AS author_id, u.email AS author_email, u.username AS author_name 
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = :post_id
        ");
        $stmt->execute([':post_id' => $postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Signal node not found']);
            exit();
        }

        // Rule: Authors cannot vote on their own posts
        if ((int)$post['author_id'] === $currentUserId) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Authors cannot cast votes on their own signals']);
            exit();
        }

        // Check if user has already reacted
        $stmt = $pdo->prepare("SELECT reaction_type FROM post_reactions WHERE post_id = :post_id AND user_id = :user_id");
        $stmt->execute([':post_id' => $postId, ':user_id' => $currentUserId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Reaction locked. You have already voted on this signal']);
            exit();
        }

        // Insert new reaction
        $stmt = $pdo->prepare("
            INSERT INTO post_reactions (post_id, user_id, reaction_type) 
            VALUES (:post_id, :user_id, :reaction_type)
        ");
        $stmt->execute([
            ':post_id'       => $postId,
            ':user_id'       => $currentUserId,
            ':reaction_type' => $reactionType
        ]);

        // Award/Deduct Points to Post Author
        $earnedPoints = $pointWeights[$reactionType];
        $stmt = $pdo->prepare("UPDATE users SET points = points + :pts WHERE id = :author_id");
        $stmt->execute([':pts' => $earnedPoints, ':author_id' => $post['author_id']]);

        // Fetch actor details for notifications
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :uid");
        $stmt->execute([':uid' => $currentUserId]);
        $actor = $stmt->fetch(PDO::FETCH_ASSOC);
        $actorName = $actor['username'] ?? 'A user';

        // Insert Database Notification
        $notifMsg = "{$actorName} cast a {$reactionType} reaction on your signal.";
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, actor_id, type, target_id, message)
            VALUES (:user_id, :actor_id, 'reaction', :target_id, :message)
        ");
        $stmt->execute([
            ':user_id'  => $post['author_id'],
            ':actor_id' => $currentUserId,
            ':target_id'=> $postId,
            ':message'  => $notifMsg
        ]);

        $pdo->commit();

        // Dispatch Email Alert to Post Author
        $emailSubject = "VALKYRIN - New Signal Reaction";
        $emailBody    = "Greetings {$post['author_name']},\n\n"
                      . "User '{$actorName}' cast a [{$reactionType}] reaction on your signal node (#{$postId}).\n"
                      . "Reputation Point adjustment: {$earnedPoints}\n\n"
                      . "View your feed to inspect details.\n\n-- VALKYRIN Network";
        sendNotificationEmail($post['author_email'], $emailSubject, $emailBody);

        // Fetch updated reaction breakdown
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN reaction_type = 'valhalla' THEN 1 ELSE 0 END), 0) AS valhalla,
                COALESCE(SUM(CASE WHEN reaction_type = 'honor' THEN 1 ELSE 0 END), 0) AS honor,
                COALESCE(SUM(CASE WHEN reaction_type = 'dishonor' THEN 1 ELSE 0 END), 0) AS dishonor,
                COALESCE(SUM(CASE WHEN reaction_type = 'strike' THEN 1 ELSE 0 END), 0) AS strike
            FROM post_reactions 
            WHERE post_id = :post_id
        ");
        $stmt->execute([':post_id' => $postId]);
        $breakdown = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'       => true,
            'user_reaction' => $reactionType,
            'breakdown'     => $breakdown
        ]);
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'Database failure during reaction processing']);
        exit();
    }
}

// -----------------------------------------------------------------------------
// 3. ROUTE: FETCH COMMENTS
// -----------------------------------------------------------------------------
if ($action === 'fetch_comments') {
    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

    if ($postId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid post reference']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id, 
                c.post_id, 
                c.parent_id, 
                c.content, 
                c.created_at, 
                u.username, 
                u.avatar
            FROM post_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.post_id = :post_id
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([':post_id' => $postId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format dates cleanly
        foreach ($comments as &$c) {
            $c['created_at'] = date('M j, Y - H:i', strtotime($c['created_at']));
        }

        echo json_encode(['success' => true, 'comments' => $comments]);
        exit();

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve signal logs']);
        exit();
    }
}

// -----------------------------------------------------------------------------
// 4. ROUTE: ADD COMMENT / REPLY
// -----------------------------------------------------------------------------
if ($action === 'add_comment') {
    $postId   = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
    $content  = isset($_POST['content']) ? trim($_POST['content']) : '';

    if ($postId <= 0 || empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Log transmission cannot be empty']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Fetch post and author details
        $stmt = $pdo->prepare("
            SELECT p.id, p.user_id AS author_id, u.email AS author_email, u.username AS author_name 
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = :post_id
        ");
        $stmt->execute([':post_id' => $postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Signal node missing']);
            exit();
        }

        // 2. Fetch commenting user details
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = :uid");
        $stmt->execute([':uid' => $currentUserId]);
        $commenter = $stmt->fetch(PDO::FETCH_ASSOC);
        $commenterName = $commenter['username'] ?? 'A user';

        // 3. Insert Comment
        $stmt = $pdo->prepare("
            INSERT INTO post_comments (post_id, user_id, parent_id, content) 
            VALUES (:post_id, :user_id, :parent_id, :content)
        ");
        $stmt->execute([
            ':post_id'   => $postId,
            ':user_id'   => $currentUserId,
            ':parent_id' => $parentId,
            ':content'   => $content
        ]);
        $newCommentId = $pdo->lastInsertId();

        // 4. Award Points for participating (+2 points for posting a comment)
        $stmt = $pdo->prepare("UPDATE users SET points = points + 2 WHERE id = :uid");
        $stmt->execute([':uid' => $currentUserId]);

        // 5. Determine Notification Target (Parent comment author OR Post author)
        $targetUserId = $post['author_id'];
        $targetEmail  = $post['author_email'];
        $targetName   = $post['author_name'];
        $notifType    = 'comment';

        if ($parentId > 0) {
            $stmt = $pdo->prepare("
                SELECT c.user_id, u.email, u.username 
                FROM post_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.id = :parent_id
            ");
            $stmt->execute([':parent_id' => $parentId]);
            $parentComment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($parentComment) {
                $targetUserId = $parentComment['user_id'];
                $targetEmail  = $parentComment['email'];
                $targetName   = $parentComment['username'];
                $notifType    = 'reply';
            }
        }

        // Avoid sending notification if commenting on one's own post/comment
        if ($targetUserId !== $currentUserId) {
            $notifMsg = ($notifType === 'reply')
                ? "{$commenterName} replied to your log entry."
                : "{$commenterName} logged a response on your signal node.";

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, actor_id, type, target_id, message)
                VALUES (:user_id, :actor_id, :type, :target_id, :message)
            ");
            $stmt->execute([
                ':user_id'  => $targetUserId,
                ':actor_id' => $currentUserId,
                ':type'     => $notifType,
                ':target_id'=> $newCommentId,
                ':message'  => $notifMsg
            ]);

            $emailSubject = "VALKYRIN - New Log Transmission";
            $emailBody    = "Greetings {$targetName},\n\n"
                          . "{$commenterName} left a response: \"{$content}\"\n\n"
                          . "Log into VALKYRIN to view the thread.\n\n-- VALKYRIN Network";
            
            sendNotificationEmail($targetEmail, $emailSubject, $emailBody);
        }

        $pdo->commit();

        echo json_encode([
            'success'    => true,
            'comment_id' => $newCommentId
        ]);
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'Database error while logging comment']);
        exit();
    }
}

// Fallback for unhandled action routes
echo json_encode(['success' => false, 'error' => 'Invalid or missing endpoint action']);
exit();