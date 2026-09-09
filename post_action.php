<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/gamification.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// --- ACTION 1: CREATE POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_post') {
    $rawContent = $_POST['content'] ?? '';
    $cleanContent = ValkyrinSecurity::sanitizeAndRedactPII($rawContent);

    if (empty($cleanContent) && empty($_FILES['attachment']['name'])) {
        header("Location: /users/dashboard.php?error=" . urlencode("Post transmission cannot be empty."));
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert Post
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (:user_id, :content)");
        $stmt->execute([':user_id' => $currentUserId, ':content' => $cleanContent]);
        $postId = (int)$pdo->lastInsertId();

        // 2. Process Attachment if present
        if (!empty($_FILES['attachment']['name'])) {
            $validation = ValkyrinSecurity::validateUpload($_FILES['attachment']);
            if (!$validation['success']) {
                $pdo->rollBack();
                header("Location: /users/dashboard.php?error=" . urlencode($validation['error']));
                exit();
            }

            $uploadDir = __DIR__ . '/uploads/posts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $storedFilename = bin2hex(random_bytes(16)) . '.' . $validation['extension'];
            $targetPath = $uploadDir . $storedFilename;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                ValkyrinSecurity::stripExifMetadata($targetPath, $validation['mime']);

                $attachStmt = $pdo->prepare("
                    INSERT INTO post_attachments (post_id, original_filename, stored_filename, file_path, file_size, mime_type, file_type)
                    VALUES (:post_id, :orig_name, :stored_name, :path, :size, :mime, :type)
                ");
                $attachStmt->execute([
                    ':post_id'     => $postId,
                    ':orig_name'   => ValkyrinSecurity::sanitizeAndRedactPII($_FILES['attachment']['name']),
                    ':stored_name' => $storedFilename,
                    ':path'        => '/uploads/posts/' . $storedFilename,
                    ':size'        => $_FILES['attachment']['size'],
                    ':mime'        => $validation['mime'],
                    ':type'        => $validation['type']
                ]);
            }
        }

        // 3. Award Reputation for posting (+3 pts)
        ValkyrinGamification::adjustReputation($pdo, $currentUserId, 3);

        // 4. Check & Award Badges
        ValkyrinGamification::checkAndAwardTierBadges($pdo, $currentUserId, 'post');

        $pdo->commit();
        header("Location: /users/dashboard.php?message=" . urlencode("Transmission published successfully (+3 Rep)."));
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        header("Location: /users/dashboard.php?error=" . urlencode("System Fault: " . $e->getMessage()));
        exit();
    }
}

// --- ACTION 2: REACT TO POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'react_post') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $type = $_POST['reaction_type'] ?? '';

    $validReactions = [
        'like'    => 2,
        'heart'   => 4,
        'dislike' => -2,
        'angry'   => -4
    ];

    if (!$postId || !array_key_exists($type, $validReactions)) {
        header("Location: /users/dashboard.php?error=" . urlencode("Invalid reaction request."));
        exit();
    }

    try {
        // Fetch post author
        $authorStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = :id LIMIT 1");
        $authorStmt->execute([':id' => $postId]);
        $authorId = (int)$authorStmt->fetchColumn();

        if (!$authorId) {
            header("Location: /users/dashboard.php?error=" . urlencode("Target post not found."));
            exit();
        }

        // Check existing reaction
        $existingStmt = $pdo->prepare("SELECT reaction_type FROM post_reactions WHERE post_id = :post_id AND user_id = :user_id");
        $existingStmt->execute([':post_id' => $postId, ':user_id' => $currentUserId]);
        $existingReaction = $existingStmt->fetchColumn();

        if ($existingReaction) {
            if ($existingReaction === $type) {
                // Toggle OFF reaction
                $deleteStmt = $pdo->prepare("DELETE FROM post_reactions WHERE post_id = :post_id AND user_id = :user_id");
                $deleteStmt->execute([':post_id' => $postId, ':user_id' => $currentUserId]);

                // Revert author reputation
                ValkyrinGamification::adjustReputation($pdo, $authorId, -$validReactions[$type]);
            } else {
                // Change reaction type
                $updateStmt = $pdo->prepare("UPDATE post_reactions SET reaction_type = :type WHERE post_id = :post_id AND user_id = :user_id");
                $updateStmt->execute([':type' => $type, ':post_id' => $postId, ':user_id' => $currentUserId]);

                // Revert old points and add new points
                $diff = $validReactions[$type] - $validReactions[$existingReaction];
                ValkyrinGamification::adjustReputation($pdo, $authorId, $diff);
            }
        } else {
            // New reaction
            $insertStmt = $pdo->prepare("INSERT INTO post_reactions (post_id, user_id, reaction_type) VALUES (:post_id, :user_id, :type)");
            $insertStmt->execute([':post_id' => $postId, ':user_id' => $currentUserId, ':type' => $type]);

            // Add reputation to author
            ValkyrinGamification::adjustReputation($pdo, $authorId, $validReactions[$type]);
        }

        header("Location: /users/dashboard.php#post-" . $postId);
        exit();

    } catch (Exception $e) {
        header("Location: /users/dashboard.php?error=" . urlencode("Reaction Fault: " . $e->getMessage()));
        exit();
    }
}

// --- ACTION 3: ADD COMMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_comment') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $commentText = ValkyrinSecurity::sanitizeAndRedactPII($_POST['comment'] ?? '');

    if (!$postId || empty($commentText)) {
        header("Location: /users/dashboard.php?error=" . urlencode("Comment cannot be blank."));
        exit();
    }

    try {
        $authorStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = :id LIMIT 1");
        $authorStmt->execute([':id' => $postId]);
        $authorId = (int)$authorStmt->fetchColumn();

        if ($authorId) {
            $stmt = $pdo->prepare("INSERT INTO post_comments (post_id, user_id, comment) VALUES (:post_id, :user_id, :comment)");
            $stmt->execute([':post_id' => $postId, ':user_id' => $currentUserId, ':comment' => $commentText]);

            // Award +5 Reputation to the Post Author
            ValkyrinGamification::adjustReputation($pdo, $authorId, 5);

            // Check & Award Badges for Commenter
            ValkyrinGamification::checkAndAwardTierBadges($pdo, $currentUserId, 'comment');
        }

        header("Location: /users/dashboard.php#post-" . $postId);
        exit();

    } catch (Exception $e) {
        header("Location: /users/dashboard.php?error=" . urlencode("Comment Fault: " . $e->getMessage()));
        exit();
    }
}