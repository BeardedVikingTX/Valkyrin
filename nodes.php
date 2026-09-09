<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$userId = (int)$_SESSION['user_id'];
$search = trim($_GET['q'] ?? '');

$users = [];
$error = '';

if (isset($pdo)) {
    try {
        // Using indexed positional parameters to prevent driver binding mismatches
        $params = [$userId, $userId, $userId];
        $whereSql = "";

        if (!empty($search)) {
            $whereSql .= " AND (u.username LIKE ? OR u.display_name LIKE ?) ";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Fetch users EXCLUDING those who have explicitly hidden themselves from current user
        $sql = "
            SELECT 
                u.id, 
                u.username, 
                u.display_name, 
                u.avatar, 
                u.banner,
                c.status AS connection_status,
                c.requester_id
            FROM users u
            LEFT JOIN connections c ON (
                (c.requester_id = ? AND c.addressee_id = u.id) 
                OR (c.addressee_id = ? AND c.requester_id = u.id)
            )
            WHERE 1=1 {$whereSql}
              AND NOT EXISTS (
                  SELECT 1 FROM connections h 
                  WHERE h.requester_id = u.id 
                    AND h.addressee_id = ? 
                    AND h.status = 'hidden'
              )
            ORDER BY u.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = "Unable to retrieve network nodes: " . $e->getMessage();
    }
}
?>

<header class="py-4 bg-dark border-bottom border-secondary">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <span class="badge bg-info text-dark px-3 py-1 rounded-pill mb-2">
                <i class="fa-solid fa-network-wired me-1"></i>VALKYRIN DIRECTORY
            </span>
            <h1 class="font-cinzel display-6 fw-bold text-white mb-0">NETWORK NODES</h1>
        </div>
        
        <form method="GET" action="" class="d-flex gap-2" style="max-width: 300px;">
            <div class="input-group">
                <input type="text" name="q" class="form-control bg-dark text-light border-secondary" 
                       placeholder="Search nodes..." value="<?= htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-outline-info"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>
        </form>
    </div>
</header>

<main class="py-5">
    <div class="container">
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger bg-danger bg-opacity-20 text-light border-0 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($users)): ?>
            <div class="vk-card p-5 text-center border border-secondary rounded">
                <i class="fa-solid fa-user-slash fa-3x text-muted mb-3"></i>
                <h4 class="font-cinzel text-light">No Network Nodes Discovered</h4>
                <p class="text-muted small mb-0">No active network entities match your query.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($users as $node): ?>
                    <?php
                        $nodeId = (int)$node['id'];
                        $isSelf = ($nodeId === $userId);

                        // Resolve Avatar Path
                        $avatarPath = '/assets/images/default_avatar.png';
                        if (!empty($node['avatar'])) {
                            if (file_exists(__DIR__ . '/uploads/profiles/' . $node['avatar'])) {
                                $avatarPath = '/uploads/profiles/' . $node['avatar'];
                            } elseif (file_exists(__DIR__ . '/assets/images/' . $node['avatar'])) {
                                $avatarPath = '/assets/images/' . $node['avatar'];
                            }
                        }

                        // Resolve Banner Path
                        $bannerPath = '/assets/images/default_banner.jpg';
                        if (!empty($node['banner'])) {
                            if (file_exists(__DIR__ . '/uploads/banners/' . $node['banner'])) {
                                $bannerPath = '/uploads/banners/' . $node['banner'];
                            } elseif (file_exists(__DIR__ . '/assets/images/' . $node['banner'])) {
                                $bannerPath = '/assets/images/' . $node['banner'];
                            }
                        }

                        $status = $node['connection_status'] ?? 'none';
                        $isRequester = ((int)$node['requester_id'] === $userId);
                    ?>
                    <div class="col-md-6 col-lg-4" id="node-card-<?= $nodeId; ?>">
                        <div class="vk-card border border-secondary rounded overflow-hidden h-100 d-flex flex-column bg-dark">
                            
                            <!-- Banner Header -->
                            <div style="height: 100px; background: url('<?= htmlspecialchars($bannerPath); ?>') center/cover no-repeat;" class="position-relative border-bottom border-secondary">
                                <!-- Avatar Overlay -->
                                <div class="position-absolute start-0 bottom-0 translate-middle-y ms-3">
                                    <img src="<?= htmlspecialchars($avatarPath); ?>" 
                                         class="rounded-circle border border-2 border-info bg-dark shadow" 
                                         width="64" height="64" 
                                         style="object-fit: cover;" 
                                         alt="Node Avatar">
                                </div>
                                <?php if ($isSelf): ?>
                                    <span class="position-absolute top-0 end-0 m-2 badge bg-info text-dark font-cinzel">YOUR NODE</span>
                                <?php endif; ?>
                            </div>

                            <!-- Card Details -->
                            <div class="p-3 pt-4 flex-grow-1 d-flex flex-column justify-content-between">
                                <div>
                                    <h5 class="font-cinzel text-light fw-bold mb-0">
                                        <?= htmlspecialchars(html_entity_decode($node['display_name'] ?: $node['username'], ENT_QUOTES, 'UTF-8')); ?>
                                    </h5>
                                    <p class="text-muted small mb-3">@<?= htmlspecialchars($node['username']); ?></p>
                                </div>

                                <!-- Connection & Hide Control Bar -->
                                <div class="border-top border-secondary pt-3 mt-2 d-flex align-items-center justify-content-between gap-2" id="action-container-<?= $nodeId; ?>">
                                    
                                    <?php if ($isSelf): ?>
                                        <a href="/users/dashboard.php" class="btn btn-sm btn-outline-info rounded-pill w-100 fw-bold">
                                            <i class="fa-solid fa-sliders me-1"></i>Manage Node
                                        </a>
                                    <?php else: ?>

                                        <!-- Connection State Actions -->
                                        <?php if ($status === 'accepted'): ?>
                                            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 action-btn" data-id="<?= $nodeId; ?>" data-action="sever">
                                                <i class="fa-solid fa-link-slash me-1"></i>Sever Connection
                                            </button>
                                            <a href="/user.php?id=<?= $node['id']; ?>" class="btn btn-outline-info rounded-pill btn-sm fw-bold">
                                                <i class="fa-solid fa-user me-1"></i> View Profile
                                            </a>
                                        <?php elseif ($status === 'pending'): ?>
                                            <?php if ($isRequester): ?>
                                                <button class="btn btn-sm btn-secondary rounded-pill px-3" disabled>
                                                    <i class="fa-solid fa-clock me-1"></i>Request Pending
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-success rounded-pill px-3 action-btn" data-id="<?= $nodeId; ?>" data-action="accept">
                                                    <i class="fa-solid fa-check me-1"></i>Accept Request
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-info rounded-pill px-3 fw-bold text-dark action-btn" data-id="<?= $nodeId; ?>" data-action="request">
                                                <i class="fa-solid fa-user-plus me-1"></i>Request Connection
                                            </button>
                                        <?php endif; ?>

                                        <!-- Privacy Hide Action -->
                                        <?php if ($status === 'hidden'): ?>
                                            <button class="btn btn-sm btn-warning text-dark rounded-pill px-2 action-btn" data-id="<?= $nodeId; ?>" data-action="unhide" title="You have hidden your node from this user. Click to unhide.">
                                                <i class="fa-solid fa-eye-slash me-1"></i>Hidden
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 action-btn" data-id="<?= $nodeId; ?>" data-action="hide" title="Hide your profile from this user entirely">
                                                <i class="fa-solid fa-eye-slash me-1"></i>Hide Me
                                            </button>
                                        <?php endif; ?>

                                    <?php endif; ?>

                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.id;
            const action = this.dataset.action;

            if (action === 'hide' && !confirm("Hiding your node will prevent this user from seeing your profile or connecting with you. Proceed?")) {
                return;
            }

            fetch('/connections_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `target_id=${encodeURIComponent(targetId)}&action=${encodeURIComponent(action)}`
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Raw Server Response:", text);
                    throw new Error("Server returned non-JSON response. Check console for raw output.");
                }
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Operation failed.');
                }
            })
            .catch(err => {
                alert('Server Error: ' + err.message);
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>