<?php
// users/messages.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/messaging_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];

// Query accepted user connections
$connStmt = $pdo->prepare("
    SELECT u.id, u.display_name, u.username, u.avatar 
    FROM connections c
    JOIN users u ON (c.addressee_id = u.id AND c.requester_id = ?) OR (c.requester_id = u.id AND c.addressee_id = ?)
    WHERE c.status = 'accepted' AND u.id != ?
");
$connStmt->execute([$currentUserId, $currentUserId, $currentUserId]);
$connections = $connStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch VALKYRIN_AI system node if present, or create synthetic connection target
$aiStmt = $pdo->prepare("SELECT id, display_name, username, avatar FROM users WHERE id = 9999 LIMIT 1");
$aiStmt->execute();
$aiUser = $aiStmt->fetch(PDO::FETCH_ASSOC);

if ($aiUser) {
    // Prepend VALKYRIN_AI to top of node selection list
    array_unshift($connections, $aiUser);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<style>
.chat-wrapper {
    height: calc(100vh - 120px);
    min-height: 550px;
    background: var(--valkyrin-surface);
    border: 1px solid rgba(0, 242, 254, 0.15);
}

.chat-sidebar {
    background: rgba(10, 13, 20, 0.6);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}

.chat-item {
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    cursor: pointer;
}

.chat-item:hover, .chat-item.active {
    background: rgba(0, 242, 254, 0.08) !important;
    border-left: 3px solid var(--valkyrin-primary);
}

.msg-bubble {
    max-width: 75%;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    word-break: break-word;
}

.msg-owner {
    background: linear-gradient(135deg, var(--valkyrin-primary), var(--valkyrin-secondary));
    color: #000;
    font-weight: 500;
    border-bottom-right-radius: 2px;
}

.msg-peer {
    background: #1a2332;
    color: var(--valkyrin-text);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-bottom-left-radius: 2px;
}

/* Custom Tactical Styling for Gemini AI Response Nodes */
.msg-ai {
    background: rgba(13, 202, 240, 0.08);
    color: #e0f7fc;
    border: 1px solid rgba(13, 202, 240, 0.3);
    border-bottom-left-radius: 2px;
    box-shadow: 0 0 10px rgba(0, 242, 254, 0.05);
}

.chat-img-attachment {
    max-width: 260px;
    max-height: 200px;
    border-radius: 8px;
    object-fit: cover;
}

@media (max-width: 767.98px) {
    .chat-wrapper {
        height: calc(100vh - 85px);
        border-radius: 0;
    }
    .mobile-hidden {
        display: none !important;
    }
}
</style>

<div class="container-fluid px-md-4 py-2 py-md-3">
    <div class="card valkyrin-card overflow-hidden chat-wrapper">
        <div class="row g-0 h-100">
            
            <div class="col-md-4 col-lg-4 d-flex flex-column h-100 chat-sidebar" id="chatSidebar">
                <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold font-cinzel text-gradient">
                        <i class="fa-solid fa-satellite-dish me-2 text-accent"></i>SIGNALS
                    </h5>
                    <button class="btn btn-sm btn-outline-info rounded-circle" data-bs-toggle="modal" data-bs-target="#newChatModal" title="New Signal">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </div>
                
                <div class="list-group list-group-flush overflow-auto flex-grow-1" id="chatListContainer">
                    <div class="p-4 text-center text-muted" id="chatListSpinner">
                        <div class="spinner-border spinner-border-sm text-info mb-2" role="status"></div>
                        <p class="mb-0 small">Scanning terminal connections...</p>
                    </div>
                </div>
            </div>

            <div class="col-md-8 col-lg-8 d-flex flex-column h-100 chat-main mobile-hidden" id="mainChatWindow">
                
                <div class="d-flex flex-column justify-content-center align-items-center h-100 text-muted p-4" id="noChatSelected">
                    <div class="rounded-circle p-4 mb-3" style="background: rgba(0, 242, 254, 0.05);">
                        <i class="fa-solid fa-comments display-4 text-info opacity-50"></i>
                    </div>
                    <h5 class="fw-bold font-cinzel text-light">Terminal Standby</h5>
                    <p class="small text-muted text-center" style="max-width: 320px;">Select an active signal stream or initiate direct encryption with a connected node.</p>
                </div>

                <div class="d-none flex-column h-100" id="activeChatArea">
                    <div class="p-3 border-bottom border-secondary d-flex align-items-center justify-content-between" style="background: rgba(10, 13, 20, 0.8);">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-link text-info p-0 me-3 d-md-none" id="backToSidebarBtn" title="Back to Signals">
                                <i class="fa-solid fa-arrow-left fa-lg"></i>
                            </button>
                            <img id="activeChatAvatar" src="/uploads/profiles/default_avatar.png" class="rounded-circle me-3 border border-info" style="width: 42px; height: 42px; object-fit: cover;">
                            <div>
                                <h6 class="mb-0 fw-bold text-light" id="activeChatTitle">Node Channel</h6>
                                <small class="text-info extra-small" id="activeChatMeta">Secure Connection</small>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 overflow-auto flex-grow-1 d-flex flex-column gap-3" id="messagesStream" style="background: #080b11;">
                    </div>

                    <!-- File Preview Strip -->
                    <div id="attachmentPreview" class="px-3 py-2 bg-dark border-top border-secondary d-none justify-content-between align-items-center">
                        <small class="text-info text-truncate me-2" id="previewFileName"></small>
                        <button type="button" class="btn-close btn-close-white btn-sm" id="clearAttachmentBtn"></button>
                    </div>

                    <div class="p-3 border-top border-secondary" style="background: var(--valkyrin-surface);">
                        <form id="sendMessageForm" class="d-flex gap-2 align-items-center">
                            <label for="fileAttachmentInput" class="btn btn-outline-secondary rounded-circle d-flex align-items-center justify-content-center text-info" style="width: 42px; height: 42px; cursor: pointer;">
                                <i class="fa-solid fa-paperclip"></i>
                            </label>
                            <input type="file" id="fileAttachmentInput" class="d-none" accept="image/*,video/*,.pdf,.doc,.docx,.zip">
                            
                            <input type="text" id="messageBodyInput" class="form-control bg-dark text-light border-secondary rounded-pill px-3 py-2" placeholder="Transmit signal (Tag @VALKYRIN_AI for neural reply)..." autocomplete="off">
                            <button type="submit" class="btn btn-info rounded-circle px-3 py-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa-solid fa-paper-plane text-dark"></i>
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newChatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold font-cinzel text-info">Initiate Signal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createChatForm">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Channel Type</label>
                        <select class="form-select bg-dark text-light border-secondary" id="chatTypeSelect">
                            <option value="private">Direct Encryption (1-on-1)</option>
                            <option value="group">Group Broadcast Channel</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="groupTitleWrapper">
                        <label class="form-label small fw-bold text-uppercase text-muted">Group Title</label>
                        <input type="text" id="groupTitleInput" class="form-control bg-dark text-light border-secondary" placeholder="e.g. VALKYRIN Core Devs">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Select Target Nodes</label>
                        <div class="border border-secondary rounded p-2 overflow-auto bg-black" style="max-height: 200px;">
                            <?php if (empty($connections)): ?>
                                <p class="text-muted small mb-0 p-3 text-center">No active node connections found.</p>
                            <?php else: ?>
                                <?php foreach ($connections as $conn): 
                                    $avatarPath = $conn['avatar'] ?: 'default_avatar.png';
                                    if (!str_starts_with($avatarPath, 'http') && !str_starts_with($avatarPath, '/')) {
                                        $avatarPath = '/uploads/profiles/' . $avatarPath;
                                    }
                                ?>
                                    <div class="form-check p-2 rounded mb-1 border-bottom border-secondary">
                                        <input class="form-check-input participant-checkbox" type="checkbox" value="<?= $conn['id'] ?>" id="conn_<?= $conn['id'] ?>">
                                        <label class="form-check-label d-flex align-items-center cursor-pointer ms-2 text-light" for="conn_<?= $conn['id'] ?>">
                                            <img src="<?= htmlspecialchars($avatarPath) ?>" class="rounded-circle me-2 border border-secondary" style="width: 28px; height: 28px; object-fit: cover;">
                                            <span class="fw-medium">
                                                <?= htmlspecialchars($conn['display_name'] ?: $conn['username']) ?>
                                                <?= $conn['id'] == 9999 ? ' <span class="badge bg-info text-dark ms-1 extra-small">AI Node</span>' : '' ?>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-info px-4 rounded-pill">Open Channel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/messaging.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>