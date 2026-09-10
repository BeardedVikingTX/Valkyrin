<?php
// includes/messaging_helper.php

if (!function_exists('isConnected')) {
    /**
     * Checks if user A is connected with user B in the connections table.
     */
    function isConnected(PDO $pdo, int $userIdA, int $userIdB): bool {
        if ($userIdA === $userIdB) {
            return true;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM connections 
            WHERE ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))
              AND status = 'accepted'
        ");
        $stmt->execute([$userIdA, $userIdB, $userIdB, $userIdA]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('isConnectedToAll')) {
    /**
     * Verifies if the initiator is connected to ALL participants in a single batch query.
     */
    function isConnectedToAll(PDO $pdo, int $initiatorId, array $participantIds): bool {
        // Filter out initiator ID and deduplicate
        $targetIds = array_values(array_unique(array_filter(
            array_map('intval', $participantIds),
            fn($id) => $id !== $initiatorId
        )));

        if (empty($targetIds)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        
        // Count how many of these target users have an accepted connection with the initiator
        $sql = "
            SELECT COUNT(DISTINCT CASE 
                WHEN requester_id = ? THEN addressee_id 
                ELSE requester_id 
            END)
            FROM connections
            WHERE status = 'accepted'
              AND (
                (requester_id = ? AND addressee_id IN ($placeholders)) OR
                (addressee_id = ? AND requester_id IN ($placeholders))
              )
        ";

        $params = array_merge([$initiatorId, $initiatorId], $targetIds, [$initiatorId], $targetIds);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() === count($targetIds);
    }
}

if (!function_exists('isParticipant')) {
    /**
     * Checks if a user is an active participant in a specific conversation.
     */
    function isParticipant(PDO $pdo, int $conversationId, int $userId): bool {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM conversation_participants 
                WHERE conversation_id = ? AND user_id = ?
            ");
            $stmt->execute([$conversationId, $userId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('findExistingPrivateConversation')) {
    /**
     * Checks if a 1-on-1 conversation already exists between two users (or self-chat).
     */
    function findExistingPrivateConversation(PDO $pdo, int $userA, int $userB): ?int {
        if ($userA === $userB) {
            // Find a private conversation where userA is the ONLY participant
            $stmt = $pdo->prepare("
                SELECT c.id
                FROM conversations c
                JOIN conversation_participants cp ON c.id = cp.conversation_id
                WHERE c.type = 'private'
                  AND cp.user_id = ?
                GROUP BY c.id
                HAVING COUNT(cp.user_id) = 1
                LIMIT 1
            ");
            $stmt->execute([$userA]);
        } else {
            // Find a private conversation shared between two distinct users
            $stmt = $pdo->prepare("
                SELECT cp1.conversation_id 
                FROM conversation_participants cp1
                JOIN conversation_participants cp2 ON cp1.conversation_id = cp2.conversation_id
                JOIN conversations c ON c.id = cp1.conversation_id
                WHERE c.type = 'private'
                  AND cp1.user_id = ? 
                  AND cp2.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userA, $userB]);
        }

        $res = $stmt->fetchColumn();
        return $res ? (int)$res : null;
    }
}