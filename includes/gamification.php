<?php
// includes/gamification.php
if (!defined('VALKYRIN_EXEC')) {
    exit('Direct access strictly prohibited.');
}

class ValkyrinGamification {

    /**
     * Updates user reputation score in the database.
     */
    public static function adjustReputation(PDO $pdo, int $userId, int $points): void {
        $stmt = $pdo->prepare("UPDATE users SET reputation = GREATEST(0, reputation + :points) WHERE id = :user_id");
        $stmt->execute([':points' => $points, ':user_id' => $userId]);
    }

    /**
     * Evaluates and awards tiered badges (1st, 10th, 100th, 1000th) for a given metric.
     */
    public static function checkAndAwardTierBadges(PDO $pdo, int $userId, string $metricType): array {
        $awarded = [];
        $count = 0;

        if ($metricType === 'post') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            $count = (int)$stmt->fetchColumn();
        } elseif ($metricType === 'comment') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_comments WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            $count = (int)$stmt->fetchColumn();
        }

        $thresholds = [
            1    => $metricType . '_1',
            10   => $metricType . '_10',
            100  => $metricType . '_100',
            1000 => $metricType . '_1000'
        ];

        foreach ($thresholds as $targetCount => $badgeSlug) {
            if ($count >= $targetCount) {
                if (self::grantBadgeBySlug($pdo, $userId, $badgeSlug)) {
                    $awarded[] = $badgeSlug;
                }
            }
        }

        return $awarded;
    }

    /**
     * Unlocks a specific badge for a user if they don't already possess it.
     */
    public static function grantBadgeBySlug(PDO $pdo, int $userId, string $badgeSlug): bool {
        // Fetch badge ID
        $stmt = $pdo->prepare("SELECT id FROM badges WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $badgeSlug]);
        $badgeId = $stmt->fetchColumn();

        if (!$badgeId) {
            return false;
        }

        // Insert unique award record
        try {
            $insertStmt = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (:user_id, :badge_id)");
            return $insertStmt->execute([':user_id' => $userId, ':badge_id' => $badgeId]);
        } catch (PDOException $e) {
            // Already earned badge (unique constraint caught)
            return false;
        }
    }
}