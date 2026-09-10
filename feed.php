<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php?error=" . urlencode("Authentication required to access network feed."));
    exit();
}

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$userId = (int)$_SESSION['user_id'];
$successMessage = $_GET['success'] ?? '';

// Inputs for filtering, pagination, and hashtag search
$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['public', 'network', 'all'], true) 
    ? $_GET['filter'] 
    : 'all';

$searchTag = trim($_GET['tag'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Helper function to derive rank badges
function getNodeBadge(int $rep): array {
    if ($rep >= 5000) return ['title' => 'VALKYRIE PRIME', 'class' => 'bg-danger text-light', 'icon' => 'fa-crown'];
    if ($rep >= 2000) return ['title' => 'COMMANDER', 'class' => 'bg-warning text-dark', 'icon' => 'fa-shield-halved'];
    if ($rep >= 750)  return ['title' => 'SHIELDBEARER', 'class' => 'bg-accent text-dark', 'icon' => 'fa-shield'];
    if ($rep >= 200)  return ['title' => 'BERSERKER', 'class' => 'bg-info text-dark', 'icon' => 'fa-bolt'];
    return ['title' => 'INITIATE', 'class' => 'bg-secondary text-light', 'icon' => 'fa-seedling'];
}

// Format sanitized display text
function clean_display_text(?string $text): string {
    if (empty($text)) return '';
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return nl2br(htmlspecialchars($decoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

$posts = [];
$totalPosts = 0;

if (isset($pdo)) {
    try {
        $whereConditions = [];
        $params = [];

        // 1. Visibility Filter Logic
        if ($filter === 'public') {
            $whereConditions[] = "p.visibility = 'public'";
        } elseif ($filter === 'network') {
            $whereConditions[] = "(
                p.visibility = 'network' AND (
                    p.user_id = :uid_net1 
                    OR EXISTS (
                        SELECT 1 FROM connections c 
                        WHERE c.status = 'accepted' 
                          AND ((c.requester_id = :uid_net2 AND c.addressee_id = p.user_id)
                            OR (c.addressee_id = :uid_net3 AND c.requester_id = p.user_id))
                    )
                )
            )";
            $params[':uid_net1'] = $userId;
            $params[':uid_net2'] = $userId;
            $params[':uid_net3'] = $userId;
        } else {
            $whereConditions[] = "(
                p.user_id = :uid_all1
                OR p.visibility = 'public'
                OR (
                    p.visibility = 'network' AND EXISTS (
                        SELECT 1 FROM connections c 
                        WHERE c.status = 'accepted' 
                          AND ((c.requester_id = :uid_all2 AND c.addressee_id = p.user_id)
                            OR (c.addressee_id = :uid_all3 AND c.requester_id = p.user_id))
                    )
                )
            )";
            $params[':uid_all1'] = $userId;
            $params[':uid_all2'] = $userId;
            $params[':uid_all3'] = $userId;
        }

        // 2. Hashtag Search Logic
        if (!empty($searchTag)) {
            $formattedTag = '#' . ltrim($searchTag, '#');
            $whereConditions[] = "p.tags LIKE :search_tag";
            $params[':search_tag'] = '%' . $formattedTag . '%';
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Count query for pagination calculation
        $countSql = "SELECT COUNT(*) FROM posts p WHERE {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalPosts = (int)$countStmt->fetchColumn();

        // Optimized Main Stream Query
        $params[':current_user_id'] = $userId;
        
        $sql = "
            SELECT 
                p.id AS post_id, 
                p.content, 
                p.visibility, 
                p.attachment, 
                p.attachment_type, 
                p.tags, 
                p.created_at AS post_created_at,
                u.id AS author_id, 
                u.username, 
                COALESCE(u.display_name, u.username) AS display_name, 
                u.avatar,
                COALESCE(u.reputation_points, 0) AS reputation_points,
                (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.id) AS comment_count,
                COUNT(CASE WHEN pr.reaction_type = 'valhalla' THEN 1 END) AS valhalla_count,
                COUNT(CASE WHEN pr.reaction_type = 'honor' THEN 1 END) AS honor_count,
                COUNT(CASE WHEN pr.reaction_type = 'dishonor' THEN 1 END) AS dishonor_count,
                COUNT(CASE WHEN pr.reaction_type = 'strike' THEN 1 END) AS strike_count,
                MAX(CASE WHEN pr.user_id = :current_user_id THEN pr.reaction_type ELSE NULL END) AS user_reaction
            FROM posts p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN post_reactions pr ON pr.post_id = p.id
            WHERE {$whereClause}
            GROUP BY p.id, u.id
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = "System fault: Failed to retrieve network stream.";
    }
}

$totalPages = ceil($totalPosts / $perPage);
?>

<!-- Header Hero -->
<header class="py-4 bg-dark border-bottom border-secondary">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <span class="badge bg-info text-dark px-3 py-1 rounded-pill mb-2">
                    <i class="fa-solid fa-network-wired me-1"></i>VALKYRIN STREAM
                </span>
                <h1 class="font-cinzel display-6 fw-bold text-white mb-0">NETWORK BROADCASTS</h1>
            </div>
            <div>
                <a href="/posts/create.php" class="btn btn-info rounded-pill px-4 fw-bold text-dark">
                    <i class="fa-solid fa-pen-to-square me-2"></i>Transmit Signal
                </a>
            </div>
        </div>

        <!-- Filter Controls Bar -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 border-top border-secondary">
            <ul class="nav nav-pills gap-2">
                <li class="nav-item">
                    <a class="nav-link rounded-pill <?= $filter === 'all' ? 'active bg-info text-dark fw-bold' : 'text-light bg-dark border border-secondary'; ?>" 
                       href="?filter=all<?= !empty($searchTag) ? '&tag=' . urlencode($searchTag) : ''; ?>">
                       <i class="fa-solid fa-globe me-1"></i> All Feed
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill <?= $filter === 'public' ? 'active bg-info text-dark fw-bold' : 'text-light bg-dark border border-secondary'; ?>" 
                       href="?filter=public<?= !empty($searchTag) ? '&tag=' . urlencode($searchTag) : ''; ?>">
                       <i class="fa-solid fa-unlock me-1"></i> Public Only
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill <?= $filter === 'network' ? 'active bg-info text-dark fw-bold' : 'text-light bg-dark border border-secondary'; ?>" 
                       href="?filter=network<?= !empty($searchTag) ? '&tag=' . urlencode($searchTag) : ''; ?>">
                       <i class="fa-solid fa-users me-1"></i> Mutual Connections
                    </a>
                </li>
            </ul>

            <!-- Hashtag Search Form -->
            <form method="GET" action="" class="d-flex gap-2">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary text-muted">#</span>
                    <input type="text" name="tag" class="form-control bg-dark text-light border-secondary" 
                           placeholder="Filter tag..." value="<?= htmlspecialchars($searchTag, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-outline-info">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                    <?php if (!empty($searchTag)): ?>
                        <a href="?filter=<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</header>

<main class="py-5">
    <div class="container" style="max-width: 800px;">

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success bg-success bg-opacity-20 border-0 text-light mb-4">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger bg-danger bg-opacity-20 border-0 text-light mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
            <div class="vk-card p-5 text-center border border-secondary rounded">
                <i class="fa-solid fa-radar fa-3x text-muted mb-3"></i>
                <h4 class="font-cinzel text-light">No Active Signals Found</h4>
                <p class="text-muted small mb-4">
                    <?= !empty($searchTag) ? 'No broadcasts found matching standard tag <strong>#' . htmlspecialchars($searchTag, ENT_QUOTES, 'UTF-8') . '</strong>.' : 'Connect with other nodes or alter your view filter.'; ?>
                </p>
                <a href="/posts/create.php" class="btn btn-outline-info rounded-pill px-4 btn-sm fw-bold">
                    <i class="fa-solid fa-plus me-1"></i>Create First Broadcast
                </a>
            </div>
        <?php else: ?>

            <?php foreach ($posts as $post): ?>
                <?php 
                    $badge = getNodeBadge((int)($post['reputation_points'] ?? 0));
                    $avatarFilename = $post['avatar'] ?? '';
                    $avatarServerPath = __DIR__ . '/uploads/profiles/' . $avatarFilename;
                    $avatarPath = (!empty($avatarFilename) && file_exists($avatarServerPath))
                        ? '/uploads/profiles/' . $avatarFilename
                        : '/assets/images/default_avatar.png';

                    $isAuthor = ((int)$post['author_id'] === $userId);
                    $displayName = !empty($post['display_name']) ? $post['display_name'] : $post['username'];
                ?>
                <article class="vk-card p-4 mb-4 border border-secondary rounded" id="post-<?= (int)$post['post_id']; ?>">
                    
                    <!-- Post Author Info Header -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8'); ?>" class="rounded-circle border border-info" width="48" height="48" style="object-fit:cover;" alt="Node Avatar">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="font-cinzel mb-0 text-light fw-bold">
                                        <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                                    </h6>
                                    <span class="badge <?= $badge['class']; ?> extra-small rounded-pill">
                                        <i class="fa-solid <?= $badge['icon']; ?> me-1"></i><?= $badge['title']; ?>
                                    </span>
                                </div>
                                <small class="text-muted extra-small">
                                    @<?= htmlspecialchars($post['username'], ENT_QUOTES, 'UTF-8'); ?> • <?= date('M j, Y @ H:i', strtotime($post['post_created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <div>
                            <span class="badge bg-dark border border-secondary text-muted extra-small">
                                <i class="fa-solid <?= $post['visibility'] === 'public' ? 'fa-globe' : 'fa-user-group'; ?> me-1"></i>
                                <?= strtoupper(htmlspecialchars($post['visibility'], ENT_QUOTES, 'UTF-8')); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Post Content -->
                    <div class="post-body text-light mb-3">
                        <p class="mb-2"><?= clean_display_text($post['content']); ?></p>
                        
                        <!-- Tags -->
                        <?php if (!empty($post['tags'])): ?>
                            <div class="mb-2">
                                <?php foreach (explode(' ', $post['tags']) as $tag): ?>
                                    <?php 
                                        $cleanTag = ltrim(trim($tag), '#'); 
                                        if (empty($cleanTag)) continue;
                                    ?>
                                    <a href="?filter=<?= urlencode($filter); ?>&tag=<?= urlencode($cleanTag); ?>" 
                                       class="badge bg-info bg-opacity-10 text-info text-decoration-none me-1">
                                        #<?= htmlspecialchars($cleanTag, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Attachments -->
                        <?php if (!empty($post['attachment'])): ?>
                            <div class="mt-3 p-2 bg-dark rounded border border-secondary text-center">
                                <?php if ($post['attachment_type'] === 'image'): ?>
                                    <img src="/uploads/attachments/<?= htmlspecialchars($post['attachment'], ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid rounded" style="max-height: 400px;" alt="Payload Attachment">
                                <?php elseif ($post['attachment_type'] === 'video'): ?>
                                    <video controls class="w-100 rounded" style="max-height: 400px;">
                                        <source src="/uploads/attachments/<?= htmlspecialchars($post['attachment'], ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                                    </video>
                                <?php else: ?>
                                    <a href="/uploads/attachments/<?= htmlspecialchars($post['attachment'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-info btn-sm rounded-pill my-2" download>
                                        <i class="fa-solid fa-download me-2"></i>Download Payload Document
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Post Telemetry & Actions Bar -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between border-top border-secondary pt-3 mt-3 gap-2">
                        
                        <?php if ($isAuthor): ?>
                            <!-- Read-only stats for post authors -->
                            <div class="btn-group btn-group-sm rounded-pill border border-secondary p-1 bg-dark opacity-75" title="Authors cannot vote on their own broadcasts">
                                <span class="btn btn-dark text-danger border-0 rounded-pill px-2 disabled">
                                    <i class="fa-solid fa-shield-halved me-1"></i><?= (int)$post['valhalla_count']; ?>
                                </span>
                                <span class="btn btn-dark text-success border-0 rounded-pill px-2 disabled">
                                    <i class="fa-solid fa-thumbs-up me-1"></i><?= (int)$post['honor_count']; ?>
                                </span>
                                <span class="btn btn-dark text-warning border-0 rounded-pill px-2 disabled">
                                    <i class="fa-solid fa-thumbs-down me-1"></i><?= (int)$post['dishonor_count']; ?>
                                </span>
                                <span class="btn btn-dark text-secondary border-0 rounded-pill px-2 disabled">
                                    <i class="fa-solid fa-skull me-1"></i><?= (int)$post['strike_count']; ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <!-- Fully interactive reaction bar for all other logged-in users -->
                            <div class="btn-group btn-group-sm rounded-pill border border-secondary p-1 bg-dark reaction-bar" id="reaction-bar-<?= (int)$post['post_id']; ?>">
                                <button type="button" 
                                        class="btn btn-dark text-danger border-0 rounded-pill px-2 rx-btn <?= $post['user_reaction'] === 'valhalla' ? 'active bg-danger text-white fw-bold' : ''; ?>" 
                                        data-post-id="<?= (int)$post['post_id']; ?>" data-type="valhalla" title="Valhalla (+4 Rep)">
                                    <i class="fa-solid fa-shield-halved me-1"></i> 
                                    <span class="count-valhalla"><?= (int)$post['valhalla_count']; ?></span>
                                </button>
                                
                                <button type="button" 
                                        class="btn btn-dark text-success border-0 rounded-pill px-2 rx-btn <?= $post['user_reaction'] === 'honor' ? 'active bg-success text-white fw-bold' : ''; ?>" 
                                        data-post-id="<?= (int)$post['post_id']; ?>" data-type="honor" title="Honor (+2 Rep)">
                                    <i class="fa-solid fa-thumbs-up me-1"></i> 
                                    <span class="count-honor"><?= (int)$post['honor_count']; ?></span>
                                </button>
                                
                                <button type="button" 
                                        class="btn btn-dark text-warning border-0 rounded-pill px-2 rx-btn <?= $post['user_reaction'] === 'dishonor' ? 'active bg-warning text-dark fw-bold' : ''; ?>" 
                                        data-post-id="<?= (int)$post['post_id']; ?>" data-type="dishonor" title="Dishonor (-2 Rep)">
                                    <i class="fa-solid fa-thumbs-down me-1"></i> 
                                    <span class="count-dishonor"><?= (int)$post['dishonor_count']; ?></span>
                                </button>
                                
                                <button type="button" 
                                        class="btn btn-dark text-secondary border-0 rounded-pill px-2 rx-btn <?= $post['user_reaction'] === 'strike' ? 'active bg-secondary text-white fw-bold' : ''; ?>" 
                                        data-post-id="<?= (int)$post['post_id']; ?>" data-type="strike" title="Strike (-4 Rep)">
                                    <i class="fa-solid fa-skull me-1"></i> 
                                    <span class="count-strike"><?= (int)$post['strike_count']; ?></span>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Logs Toggle -->
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 toggle-comments" data-post-id="<?= (int)$post['post_id']; ?>">
                            <i class="fa-solid fa-comments me-1"></i>
                            <span class="comment-count"><?= (int)$post['comment_count']; ?></span> Logs
                        </button>
                    </div>

                    <!-- Comments Container -->
                    <div class="comments-container mt-3 pt-3 border-top border-secondary" style="display:none;" id="comments-<?= (int)$post['post_id']; ?>">
                        <div class="comments-list mb-3" id="comments-list-<?= (int)$post['post_id']; ?>">
                            <div class="text-center text-muted py-2 loading-logs"><i class="fa-solid fa-spinner fa-spin me-1"></i> Accessing logs...</div>
                        </div>
                        <form class="comment-form d-flex gap-2" data-post-id="<?= (int)$post['post_id']; ?>">
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary comment-input" placeholder="Append comment to log..." required>
                            <button type="submit" class="btn btn-info btn-sm rounded-pill px-3 fw-bold text-dark">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>

                </article>
            <?php endforeach; ?>

            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link bg-dark text-info border-secondary" href="?page=<?= $page - 1; ?>&filter=<?= urlencode($filter); ?><?= !empty($searchTag) ? '&tag=' . urlencode($searchTag) : ''; ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                                <a class="page-link <?= $i === $page ? 'bg-info text-dark border-info' : 'bg-dark text-info border-secondary'; ?>" href="?page=<?= $i; ?>&filter=<?= urlencode($filter); ?><?= !empty($searchTag) ? '&tag=' . urlencode($searchTag) : ''; ?>"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link bg-dark text-info border-secondary" href="?page=<?= $page + 1; ?>&filter=<?= urlencode($filter); ?><?= !empty($searchTag) ? '&tag=' . urlencode($searchTag) : ''; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // 1. REACTION EVENT LISTENERS & DISPATCH
    document.addEventListener('click', function(e) {
        const rxBtn = e.target.closest('.rx-btn');
        if (rxBtn) {
            e.preventDefault();
            const postId = rxBtn.dataset.postId;
            const reactionType = rxBtn.dataset.type;
            castReaction(postId, reactionType, rxBtn);
        }

        const commentToggle = e.target.closest('.toggle-comments');
        if (commentToggle) {
            e.preventDefault();
            const postId = commentToggle.dataset.postId;
            const commentsContainer = document.getElementById(`comments-${postId}`);
            if (commentsContainer) {
                const isHidden = commentsContainer.style.display === 'none' || !commentsContainer.style.display;
                commentsContainer.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    loadComments(postId);
                }
            }
        }
    });

    function castReaction(postId, reactionType, clickedBtn) {
        const formData = new FormData();
        formData.append('action', 'toggle_reaction');
        formData.append('post_id', postId);
        formData.append('reaction_type', reactionType);

        fetch('/posts/interact.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const bar = document.getElementById(`reaction-bar-${postId}`);
                if (!bar) return;

                // Reset visual state across buttons
                bar.querySelectorAll('.rx-btn').forEach(btn => {
                    const type = btn.dataset.type;
                    btn.classList.remove('active', 'fw-bold', 'bg-danger', 'bg-success', 'bg-warning', 'bg-secondary', 'text-white', 'text-dark');
                    
                    if (data.breakdown && data.breakdown[type] !== undefined) {
                        const countSpan = btn.querySelector(`.count-${type}`);
                        if (countSpan) countSpan.textContent = data.breakdown[type];
                    }
                });

                // Apply active state when selected
                if (data.user_reaction === reactionType) {
                    clickedBtn.classList.add('active', 'fw-bold');
                    if (reactionType === 'valhalla') clickedBtn.classList.add('bg-danger', 'text-white');
                    if (reactionType === 'honor') clickedBtn.classList.add('bg-success', 'text-white');
                    if (reactionType === 'dishonor') clickedBtn.classList.add('bg-warning', 'text-dark');
                    if (reactionType === 'strike') clickedBtn.classList.add('bg-secondary', 'text-white');
                }
            } else {
                alert(data.error || 'Unable to log reaction');
            }
        })
        .catch(err => console.error('Reaction dispatch failed:', err));
    }

    // 2. FETCH COMMENTS VIA AJAX
    function loadComments(postId) {
        const listDiv = document.getElementById(`comments-list-${postId}`);
        if (!listDiv) return;

        listDiv.innerHTML = '<div class="text-center text-muted p-2"><i class="fas fa-spinner fa-spin me-2"></i>Loading logs...</div>';

        fetch(`/posts/interact.php?action=fetch_comments&post_id=${postId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (!data.comments || data.comments.length === 0) {
                    listDiv.innerHTML = '<div class="text-center text-muted p-2 extra-small">No logs recorded yet. Be the first to transmit.</div>';
                    return;
                }

                listDiv.innerHTML = data.comments.map(c => {
                    const authorHandle = c.display_name ? c.display_name : c.username;
                    return `
                    <div class="p-2 mb-2 bg-dark rounded border border-secondary extra-small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="text-info">@${escapeHtml(authorHandle)}</strong>
                            <span class="text-muted extra-small">${escapeHtml(c.created_at)}</span>
                        </div>
                        <div class="text-light">${escapeHtml(c.content)}</div>
                    </div>
                `}).join('');
            } else {
                listDiv.innerHTML = `<div class="text-danger p-2 extra-small">${escapeHtml(data.error)}</div>`;
            }
        })
        .catch(err => {
            console.error('Failed to load comments:', err);
            listDiv.innerHTML = '<div class="text-danger p-2 extra-small">Failed to load log transmissions.</div>';
        });
    }

    // 3. SUBMIT COMMENT
    document.addEventListener('submit', function(e) {
        if (!e.target.classList.contains('comment-form')) return;
        
        e.preventDefault();
        const form = e.target;
        const postId = form.dataset.postId;
        const input = form.querySelector('.comment-input');
        const commentText = input.value.trim();

        if (!commentText) return;

        const formData = new FormData();
        formData.append('action', 'add_comment');
        formData.append('post_id', postId);
        formData.append('content', commentText);

        fetch('/posts/interact.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                
                const countSpan = document.querySelector(`#post-${postId} .comment-count`);
                if (countSpan) {
                    countSpan.textContent = parseInt(countSpan.textContent || '0', 10) + 1;
                }

                // Reload comment stream directly to ensure author details and timestamps align
                loadComments(postId);
            } else {
                alert(data.error || 'Could not append comment.');
            }
        })
        .catch(err => console.error('Error logging comment:', err));
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>