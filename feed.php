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

// Inputs for filtering and hashtag search
$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['public', 'network', 'all']) 
    ? $_GET['filter'] 
    : 'all';

$searchTag = trim($_GET['tag'] ?? '');

// Helper function to derive rank badges
function getNodeBadge($rep) {
    $rep = (int)$rep;
    if ($rep >= 5000) return ['title' => 'VALKYRIE PRIME', 'class' => 'bg-danger text-light', 'icon' => 'fa-crown'];
    if ($rep >= 2000) return ['title' => 'COMMANDER', 'class' => 'bg-warning text-dark', 'icon' => 'fa-shield-halved'];
    if ($rep >= 750)  return ['title' => 'SHIELDBEARER', 'class' => 'bg-accent text-dark', 'icon' => 'fa-shield'];
    if ($rep >= 200)  return ['title' => 'BERSERKER', 'class' => 'bg-info text-dark', 'icon' => 'fa-bolt'];
    return ['title' => 'INITIATE', 'class' => 'bg-secondary text-light', 'icon' => 'fa-seedling'];
}

// Clean and decode text safe for display
function clean_display_text($text) {
    if (empty($text)) return '';
    $previous = '';
    while ($text !== $previous) {
        $previous = $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = str_replace(['&#13;', '&#10;', '&amp;#13;', '&amp;#10;'], '', $text);
    return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
}

$posts = [];
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

        $params[':current_user_id'] = $userId;
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "
            SELECT 
                p.id AS post_id, p.content, p.visibility, p.attachment, p.attachment_type, p.tags, p.created_at AS post_created_at,
                u.id AS author_id, u.username, u.display_name, u.avatar,
                COALESCE(u.reputation_points, u.reputation, 0) AS reputation_points,
                (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id) AS reaction_count,
                (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id AND reaction_type = 'valhalla') AS valhalla_count,
                (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id AND reaction_type = 'honor') AS honor_count,
                (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id AND reaction_type = 'dishonor') AS dishonor_count,
                (SELECT COUNT(*) FROM post_reactions WHERE post_id = p.id AND reaction_type = 'strike') AS strike_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
                (SELECT reaction_type FROM post_reactions WHERE post_id = p.id AND user_id = :current_user_id LIMIT 1) AS user_reaction
            FROM posts p
            JOIN users u ON p.user_id = u.id
            WHERE {$whereClause}
            ORDER BY p.created_at DESC
            LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = "System fault: Failed to retrieve network stream.";
    }
}
?>

<!-- Header hero -->
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
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary text-muted-custom">#</span>
                    <input type="text" name="tag" class="form-control bg-dark text-light border-secondary" 
                           placeholder="Filter tag..." value="<?= htmlspecialchars($searchTag); ?>">
                    <button type="submit" class="btn btn-outline-info">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                    <?php if (!empty($searchTag)): ?>
                        <a href="?filter=<?= htmlspecialchars($filter); ?>" class="btn btn-outline-secondary">
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
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
            <div class="vk-card p-5 text-center border border-secondary rounded">
                <i class="fa-solid fa-radar fa-3x text-muted mb-3"></i>
                <h4 class="font-cinzel text-light">No Active Signals Found</h4>
                <p class="text-muted small mb-4">
                    <?= !empty($searchTag) ? 'No broadcasts found matching standard tag <strong>#' . htmlspecialchars($searchTag) . '</strong>.' : 'Connect with other nodes or alter your view filter.'; ?>
                </p>
                <a href="/posts/create.php" class="btn btn-outline-info rounded-pill px-4 btn-sm fw-bold">
                    <i class="fa-solid fa-plus me-1"></i>Create First Broadcast
                </a>
            </div>
        <?php else: ?>

            <?php foreach ($posts as $post): ?>
                <?php 
                    $badge = getNodeBadge($post['reputation_points'] ?? 0);
                    $avatarFilename = $post['avatar'] ?? '';
                    $avatarServerPath = __DIR__ . '/uploads/profiles/' . $avatarFilename;
                    $avatarPath = (!empty($avatarFilename) && file_exists($avatarServerPath))
                        ? '/uploads/profiles/' . $avatarFilename
                        : '/assets/images/default_avatar.png';

                    $isAuthor = ((int)$post['author_id'] === $userId);
                    $hasVoted = !empty($post['user_reaction']);
                ?>
                <article class="vk-card p-4 mb-4 border border-secondary rounded" id="post-<?= $post['post_id']; ?>">
                    
                    <!-- Post Author Info Header -->
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($avatarPath); ?>" class="rounded-circle border border-info" width="48" height="48" style="object-fit:cover;" alt="Node Avatar">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="font-cinzel mb-0 text-light fw-bold">
                                        <?= htmlspecialchars(html_entity_decode($post['display_name'] ?: $post['username'], ENT_QUOTES, 'UTF-8')); ?>
                                    </h6>
                                    <span class="badge <?= $badge['class']; ?> extra-small rounded-pill">
                                        <i class="fa-solid <?= $badge['icon']; ?> me-1"></i><?= $badge['title']; ?>
                                    </span>
                                </div>
                                <small class="text-muted extra-small">
                                    @<?= htmlspecialchars($post['username']); ?> • <?= date('M j, Y @ H:i', strtotime($post['post_created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <div>
                            <span class="badge bg-dark border border-secondary text-muted extra-small">
                                <i class="fa-solid <?= $post['visibility'] === 'public' ? 'fa-globe' : 'fa-user-group'; ?> me-1"></i>
                                <?= strtoupper($post['visibility']); ?>
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
                                        $cleanTag = ltrim(html_entity_decode($tag, ENT_QUOTES, 'UTF-8'), '#'); 
                                        if (empty($cleanTag)) continue;
                                    ?>
                                    <a href="?filter=<?= urlencode($filter); ?>&tag=<?= urlencode($cleanTag); ?>" 
                                       class="badge bg-info bg-opacity-10 text-info text-decoration-none me-1">
                                        #<?= htmlspecialchars($cleanTag); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Attachments -->
                        <?php if (!empty($post['attachment'])): ?>
                            <div class="mt-3 p-2 bg-dark rounded border border-secondary text-center">
                                <?php if ($post['attachment_type'] === 'image'): ?>
                                    <img src="/uploads/attachments/<?= htmlspecialchars($post['attachment']); ?>" class="img-fluid rounded" style="max-height: 400px;" alt="Payload Attachment">
                                <?php elseif ($post['attachment_type'] === 'video'): ?>
                                    <video controls class="w-100 rounded" style="max-height: 400px;">
                                        <source src="/uploads/attachments/<?= htmlspecialchars($post['attachment']); ?>" type="video/mp4">
                                    </video>
                                <?php else: ?>
                                    <a href="/uploads/attachments/<?= htmlspecialchars($post['attachment']); ?>" class="btn btn-outline-info btn-sm rounded-pill my-2" download>
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
                            <div class="btn-group btn-group-sm rounded-pill border border-secondary p-1 bg-dark reaction-bar" id="reaction-bar-<?= $post['post_id']; ?>">
                                <button type="button" 
                                        class="btn btn-dark text-danger border-0 rounded-pill px-2 rx-btn <?= $post['user_reaction'] === 'valhalla' ? 'active bg-danger text-white fw-bold' : ''; ?>" 
                                        data-post-id="<?= $post['post_id']; ?>" data-type="valhalla" title="Valhalla (+4 Rep)">
                                    <i class="fa-solid fa-shield-halved"></i> 
                                    <span class="count-valhalla"><?= (int)$post['valhalla_count']; ?></span>
                                </button>
                                
                                <button type="button" 
                                        class="btn btn-dark text-success border-0 rounded-pill px-2 rx-btn <?= $post['user_reaction'] === 'honor' ? 'active bg-success text-white fw-bold' : ''; ?>" 
                                        data-post-id="<?= $post['post_id']; ?>" data-type="honor" title="Honor (+2 Rep)">
                                    <i class="fa-solid fa-thumbs-up"></i> 
                                    <span class="count-honor"><?= (int)$post['honor_count']; ?></span>
                                </button>
                                
                                <button type="button" 
                                        class="btn btn-dark text-warning border-0 rounded-pill px-2 rx-btn <?= $post['user_reaction'] === 'dishonor' ? 'active bg-warning text-dark fw-bold' : ''; ?>" 
                                        data-post-id="<?= $post['post_id']; ?>" data-type="dishonor" title="Dishonor (-2 Rep)">
                                    <i class="fa-solid fa-thumbs-down"></i> 
                                    <span class="count-dishonor"><?= (int)$post['dishonor_count']; ?></span>
                                </button>
                                
                                <button type="button" 
                                        class="btn btn-dark text-secondary border-0 rounded-pill px-2 rx-btn <?= $post['user_reaction'] === 'strike' ? 'active bg-secondary text-white fw-bold' : ''; ?>" 
                                        data-post-id="<?= $post['post_id']; ?>" data-type="strike" title="Strike (-4 Rep)">
                                    <i class="fa-solid fa-skull"></i> 
                                    <span class="count-strike"><?= (int)$post['strike_count']; ?></span>
                                </button>
                            </div>
                        <?php endif; ?>
                    
                        <!-- Logs Toggle -->
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 toggle-comments" data-post-id="<?= $post['post_id']; ?>">
                            <i class="fa-solid fa-comments me-1"></i>
                            <span class="comment-count"><?= (int)$post['comment_count']; ?></span> Logs
                        </button>
                    </div>

                    <!-- Comments Container -->
                    <div class="comments-container mt-3 pt-3 border-top border-secondary" style="display:none;" id="comments-<?= $post['post_id']; ?>">
                        <div class="comments-list mb-3" id="comments-list-<?= $post['post_id']; ?>">
                            <div class="text-center text-muted py-2 loading-logs"><i class="fa-solid fa-spinner fa-spin me-1"></i> Accessing logs...</div>
                        </div>
                        <form class="comment-form d-flex gap-2" data-post-id="<?= $post['post_id']; ?>">
                            <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary comment-input" placeholder="Append comment to log..." required>
                            <button type="submit" class="btn btn-info btn-sm rounded-pill px-3 fw-bold text-dark">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>

                </article>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Delegate click handling for non-author reaction buttons
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.rx-btn');
        if (!btn) return;

        const postId = btn.dataset.postId;
        const reactionType = btn.dataset.type;
        const bar = document.getElementById(`reaction-bar-${postId}`);

        fetch('/posts/interact.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=toggle_reaction&post_id=${postId}&reaction_type=${reactionType}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const b = data.breakdown;
                const userRx = data.user_reaction;

                // Update tallies dynamically
                bar.querySelector('.count-valhalla').textContent = b.valhalla;
                bar.querySelector('.count-honor').textContent = b.honor;
                bar.querySelector('.count-dishonor').textContent = b.dishonor;
                bar.querySelector('.count-strike').textContent = b.strike;

                // Update active button highlighting
                bar.querySelectorAll('.rx-btn').forEach(button => {
                    const type = button.dataset.type;
                    button.classList.remove('active', 'bg-danger', 'bg-success', 'bg-warning', 'bg-secondary', 'text-white', 'text-dark');
                    button.classList.add('btn-dark');

                    if (type === userRx) {
                        button.classList.add('active');
                        if (type === 'valhalla') button.classList.add('bg-danger', 'text-white');
                        if (type === 'honor') button.classList.add('bg-success', 'text-white');
                        if (type === 'dishonor') button.classList.add('bg-warning', 'text-dark');
                        if (type === 'strike') button.classList.add('bg-secondary', 'text-white');
                    }
                });
            } else {
                alert(data.error || 'Failed to update reaction state.');
            }
        })
        .catch(err => console.error('Reaction request error:', err));
    });

    // Toggle Comments Visibility & Fetch Logs
    document.querySelectorAll('.toggle-comments').forEach(btn => {
        btn.addEventListener('click', function() {
            const postId = this.dataset.postId;
            const commentsDiv = document.getElementById(`comments-${postId}`);
            const listDiv = document.getElementById(`comments-list-${postId}`);
            
            if (commentsDiv.style.display === 'none') {
                commentsDiv.style.display = 'block';
                
                // Fetch comments if list isn't populated yet
                fetch(`/posts/interact.php?action=get_comments&post_id=${postId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && Array.isArray(data.comments)) {
                            if (data.comments.length === 0) {
                                listDiv.innerHTML = '<p class="text-muted extra-small mb-0 text-center">No logs recorded yet.</p>';
                            } else {
                                listDiv.innerHTML = data.comments.map(c => `
                                    <div class="p-2 mb-2 bg-dark rounded border border-secondary extra-small">
                                        <strong class="text-info">@${c.username}</strong> 
                                        <span class="text-light ms-1">${c.comment}</span>
                                        <div class="text-muted me-1 mt-1">${c.created_at}</div>
                                    </div>
                                `).join('');
                            }
                        }
                    })
                    .catch(() => {
                        listDiv.innerHTML = '<p class="text-danger extra-small mb-0 text-center">Failed to load logs.</p>';
                    });
            } else {
                commentsDiv.style.display = 'none';
            }
        });
    });

    // Submit Comments via AJAX
    document.querySelectorAll('.comment-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const postId = this.dataset.postId;
            const input = this.querySelector('.comment-input');
            const commentText = input.value.trim();
            const listDiv = document.getElementById(`comments-list-${postId}`);

            if(!commentText) return;

            fetch('/posts/interact.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=add_comment&post_id=${postId}&comment=${encodeURIComponent(commentText)}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    input.value = '';
                    const logBtn = document.querySelector(`#post-${postId} .comment-count`);
                    if(logBtn) {
                        logBtn.textContent = parseInt(logBtn.textContent || 0) + 1;
                    }
                    
                    // Prepend/Append new comment to active comment stream
                    const newLog = document.createElement('div');
                    newLog.className = 'p-2 mb-2 bg-dark rounded border border-secondary extra-small';
                    newLog.innerHTML = `<strong class="text-info">You</strong> <span class="text-light ms-1">${data.comment || commentText}</span>`;
                    
                    if (listDiv.querySelector('.text-center')) {
                        listDiv.innerHTML = '';
                    }
                    listDiv.appendChild(newLog);
                } else {
                    alert(data.error || 'Could not append comment.');
                }
            });
        });
    });

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>