/**
 * VALKYRIN Real-Time Messaging & Media Engine
 * Updated with Google Gemini Neural Integration
 */

let activeConversationId = null;
let lastKnownMessageId = 0;
let pollInterval = null;

// Adjust this base path if your API sits in a subfolder (e.g., '/users/api')
const API_BASE = '/../api';

document.addEventListener('DOMContentLoaded', () => {
    loadConversations();
    checkUrlDeepLink();
    pollInterval = setInterval(pollEngine, 3000);

    const msgForm = document.getElementById('sendMessageForm');
    if (msgForm) msgForm.addEventListener('submit', handleSendMessage);

    const createForm = document.getElementById('createChatForm');
    if (createForm) createForm.addEventListener('submit', handleCreateChat);

    const fileInput = document.getElementById('fileAttachmentInput');
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                document.getElementById('previewFileName').innerText = 'Attached: ' + e.target.files[0].name;
                document.getElementById('attachmentPreview').classList.remove('d-none');
                document.getElementById('attachmentPreview').classList.add('d-flex');
            }
        });
    }

    const clearFileBtn = document.getElementById('clearAttachmentBtn');
    if (clearFileBtn) {
        clearFileBtn.addEventListener('click', () => {
            document.getElementById('fileAttachmentInput').value = '';
            document.getElementById('attachmentPreview').classList.add('d-none');
            document.getElementById('attachmentPreview').classList.remove('d-flex');
        });
    }

    const typeSelect = document.getElementById('chatTypeSelect');
    if (typeSelect) {
        typeSelect.addEventListener('change', (e) => {
            const groupWrapper = document.getElementById('groupTitleWrapper');
            if (e.target.value === 'group') groupWrapper.classList.remove('d-none');
            else groupWrapper.classList.add('d-none');
        });
    }

    const backBtn = document.getElementById('backToSidebarBtn');
    if (backBtn) backBtn.addEventListener('click', showMobileSidebarView);
});

/**
 * Helper to safely resolve user avatar paths
 */
function resolveAvatarPath(avatar) {
    if (!avatar || avatar === 'default_avatar.png') {
        return '/uploads/profiles/default_avatar.png';
    }
    if (avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/')) {
        return avatar;
    }
    return `/uploads/profiles/${avatar}`;
}

/**
 * Handle direct notification deep linking via URL parameter: ?conversation_id=X
 */
async function checkUrlDeepLink() {
    const urlParams = new URLSearchParams(window.location.search);
    const convId = urlParams.get('conversation_id');
    if (convId) {
        const id = parseInt(convId, 10);
        if (id) {
            selectConversation(id, 'Signal Channel', '/uploads/profiles/default_avatar.png');
        }
    }
}

async function loadConversations() {
    const container = document.getElementById('chatListContainer');
    try {
        const res = await fetch(`${API_BASE}/get_conversations.php`);
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const data = await res.json();

        if (!data.success) {
            container.innerHTML = `<div class="p-3 text-center text-danger small">${escapeHtml(data.message) || 'Error loading signals'}</div>`;
            return;
        }

        if (data.conversations.length === 0) {
            container.innerHTML = `<div class="p-4 text-center text-muted small">No active signals found.</div>`;
            return;
        }

        let html = '';
        data.conversations.forEach(conv => {
            const isActive = conv.id === activeConversationId ? 'active' : '';
            const avatarSrc = resolveAvatarPath(conv.avatar);

            html += `
                <div class="list-group-item list-group-item-action bg-transparent text-light border-0 chat-item p-3 ${isActive}" 
                     onclick="selectConversation(${conv.id}, '${escapeHtml(conv.title)}', '${avatarSrc}')">
                    <div class="d-flex align-items-center">
                        <img src="${avatarSrc}" class="rounded-circle me-3 border border-secondary" style="width: 44px; height: 44px; object-fit: cover;">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="mb-0 text-truncate font-cinzel small fw-bold text-light">${escapeHtml(conv.title)}</h6>
                                <small class="extra-small text-muted">${conv.time_ago}</small>
                            </div>
                            <p class="mb-0 small text-muted text-truncate">${escapeHtml(conv.last_message || '')}</p>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    } catch (err) {
        console.error("Conversations fetch error:", err);
        if (container) {
            container.innerHTML = `<div class="p-3 text-center text-danger small">Signal connection offline. (${err.message})</div>`;
        }
    }
}

async function selectConversation(id, title, avatar) {
    if (activeConversationId === id) return; // Prevent unnecessary DOM teardown

    activeConversationId = id;
    lastKnownMessageId = 0;

    const noChat = document.getElementById('noChatSelected');
    if (noChat) noChat.classList.add('d-none');
    
    const activeArea = document.getElementById('activeChatArea');
    if (activeArea) {
        activeArea.classList.remove('d-none');
        activeArea.classList.add('d-flex');
    }

    const titleEl = document.getElementById('activeChatTitle');
    if (titleEl) titleEl.innerText = title;

    const avatarEl = document.getElementById('activeChatAvatar');
    if (avatarEl) avatarEl.src = resolveAvatarPath(avatar);

    const stream = document.getElementById('messagesStream');
    if (stream) stream.innerHTML = ''; // Clear stream DOM for fresh channel load

    showMobileChatView();
    loadConversations();
    await fetchMessages(true);
}

async function fetchMessages(forceScroll = false) {
    if (!activeConversationId) return;
    const stream = document.getElementById('messagesStream');
    if (!stream) return;

    try {
        const res = await fetch(`${API_BASE}/get_messages.php?conversation_id=${activeConversationId}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();

        if (!data.success) {
            stream.innerHTML = `<div class="p-3 text-center text-danger small">${escapeHtml(data.message)}</div>`;
            return;
        }

        if (data.messages.length === 0) {
            stream.innerHTML = `<div class="text-center text-muted my-auto small">No messages in this stream yet.</div>`;
            return;
        }

        // Clean initial empty placeholder if present
        const placeholder = stream.querySelector('.text-muted.my-auto');
        if (placeholder) {
            placeholder.remove();
        }

        let isAtBottom = stream.scrollHeight - stream.clientHeight <= stream.scrollTop + 50;
        let newMessagesAppended = false;

        data.messages.forEach(msg => {
            // Check 1: Prevent duplicate DOM appends by explicit Message ID lookup
            if (msg.id && stream.querySelector(`[data-message-id="${msg.id}"]`)) {
                if (msg.id > lastKnownMessageId) lastKnownMessageId = msg.id;
                return;
            }

            // Check 2: Remove corresponding optimistic node if server data has arrived
            if (msg.body) {
                const pendingNode = stream.querySelector(`[data-pending-body="${CSS.escape(msg.body)}"]`);
                if (pendingNode) {
                    pendingNode.remove();
                }
            }

            const messageNode = buildMessageElement(msg);
            stream.appendChild(messageNode);

            if (msg.id && msg.id > lastKnownMessageId) {
                lastKnownMessageId = msg.id;
            }
            newMessagesAppended = true;
        });

        if (forceScroll || (isAtBottom && newMessagesAppended)) {
            stream.scrollTop = stream.scrollHeight;
        }

    } catch (err) {
        console.error("Fetch error:", err);
    }
}

/**
 * Builds DOM node for single message entry with Markdown parsing
 */
function buildMessageElement(msg) {
    const wrapper = document.createElement('div');
    const alignment = msg.is_owner ? 'justify-content-end' : 'justify-content-start';
    const bubbleStyle = msg.is_owner ? 'msg-owner' : (msg.sender_id == 9999 ? 'msg-ai' : 'msg-peer');

    wrapper.className = `d-flex ${alignment} my-1`;
    
    // Bind unique message ID to wrapper node for strict DOM deduplication
    if (msg.id) {
        wrapper.setAttribute('data-message-id', msg.id);
    }

    let mediaHtml = '';
    if (msg.attachment_path) {
        if (msg.attachment_type === 'image') {
            mediaHtml = `<div class="my-2"><a href="${msg.attachment_path}" target="_blank"><img src="${msg.attachment_path}" class="chat-img-attachment border border-secondary" style="max-width:100%; border-radius:8px;"></a></div>`;
        } else if (msg.attachment_type === 'video') {
            mediaHtml = `<div class="my-2"><video src="${msg.attachment_path}" controls style="max-width:260px; border-radius:8px;"></video></div>`;
        } else {
            mediaHtml = `<div class="my-2"><a href="${msg.attachment_path}" download class="btn btn-sm btn-outline-info text-truncate"><i class="fa-solid fa-download me-1"></i> Download File</a></div>`;
        }
    }

    const formattedBody = msg.body ? formatMessageBody(msg.body) : '';

    wrapper.innerHTML = `
        <div class="msg-bubble ${bubbleStyle}">
            ${!msg.is_owner ? `<div class="extra-small fw-bold text-info mb-1">${escapeHtml(msg.sender_name)}</div>` : ''}
            ${mediaHtml}
            ${formattedBody ? `<div>${formattedBody}</div>` : ''}
            <div class="extra-small text-end opacity-75 mt-1" style="font-size: 0.65rem;">${msg.created_at || 'Just now'}</div>
        </div>
    `;

    return wrapper;
}

async function handleSendMessage(e) {
    e.preventDefault();

    const input = document.getElementById('messageBodyInput');
    const fileInput = document.getElementById('fileAttachmentInput');
    const stream = document.getElementById('messagesStream');
    const body = input.value.trim();

    if ((!body && fileInput.files.length === 0) || !activeConversationId) return;

    // Optimistic UI Append for human sender
    if (body && stream) {
        const optimisticMsg = {
            id: 0,
            is_owner: true,
            sender_name: 'You',
            body: body,
            created_at: 'Sending...'
        };
        const optNode = buildMessageElement(optimisticMsg);
        optNode.setAttribute('data-pending-body', body);
        optNode.style.opacity = '0.6';
        stream.appendChild(optNode);
        stream.scrollTop = stream.scrollHeight;
    }

    const formData = new FormData();
    formData.append('conversation_id', activeConversationId);
    formData.append('body', body);
    if (fileInput.files.length > 0) {
        formData.append('attachment', fileInput.files[0]);
    }

    input.value = '';
    fileInput.value = '';
    const preview = document.getElementById('attachmentPreview');
    if (preview) preview.classList.add('d-none');

    try {
        const res = await fetch(`${API_BASE}/send_message.php`, {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        if (data.success) {
            await fetchMessages(true);
            loadConversations();

            // Check and trigger Gemini AI response asynchronously
            triggerAiResponseIfNeeded(activeConversationId, body);
        } else {
            alert(data.message || 'Transmission failed.');
            await fetchMessages(true);
        }
    } catch (err) {
        console.error("Send failed:", err);
    }
}

/**
 * Asynchronous trigger invoking Gemini AI engine when tagged or present
 */
async function triggerAiResponseIfNeeded(conversationId, messageText) {
    const stream = document.getElementById('messagesStream');
    
    // Check if message targets VALKYRIN_AI via tags or direct message
    const isAiTargeted = /@(VALKYRIN_AI|VALKYRIN|9999)\b/i.test(messageText);

    // Show temporary typing indicator if AI is likely to respond
    let typingNode = null;
    if (isAiTargeted && stream) {
        typingNode = document.createElement('div');
        typingNode.className = 'd-flex justify-content-start my-1 id-ai-typing';
        typingNode.innerHTML = `
            <div class="msg-bubble msg-ai border border-info border-opacity-25 opacity-75">
                <div class="extra-small fw-bold text-info mb-1">VALKYRIN_AI</div>
                <div class="small text-muted font-monospace"><i class="fa-solid fa-brain fa-spin me-2 text-info"></i>Gemini Neural Processing...</div>
            </div>
        `;
        stream.appendChild(typingNode);
        stream.scrollTop = stream.scrollHeight;
    }

    try {
        const aiFormData = new FormData();
        aiFormData.append('conversation_id', conversationId);
        aiFormData.append('message', messageText);

        const aiRes = await fetch(`${API_BASE}/ai_reply_handler.php`, {
            method: 'POST',
            body: aiFormData
        });

        const aiData = await aiRes.json();
        
        // Remove typing indicator if active
        if (typingNode) typingNode.remove();

        // Refresh UI if Gemini generated a response
        if (aiData.success && aiData.triggered) {
            await fetchMessages(true);
            loadConversations();
        }
    } catch (err) {
        if (typingNode) typingNode.remove();
        console.error("Gemini AI Auto-Trigger Error:", err);
    }
}

async function handleCreateChat(e) {
    e.preventDefault();
    const type = document.getElementById('chatTypeSelect').value;
    const title = document.getElementById('groupTitleInput').value.trim();
    const checkedBoxes = document.querySelectorAll('.participant-checkbox:checked');
    const participantIds = Array.from(checkedBoxes).map(cb => cb.value);

    if (participantIds.length === 0) {
        alert('Please select at least one target node.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('type', type);
        formData.append('title', title);
        participantIds.forEach(id => formData.append('participants[]', id));

        const res = await fetch(`${API_BASE}/create_conversation.php`, {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        if (data.success) {
            const modalEl = document.getElementById('newChatModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }

            await loadConversations();
            selectConversation(data.conversation_id, data.title || 'Signal Channel', '/uploads/profiles/default_avatar.png');
        } else {
            alert(data.message || 'Creation failed.');
        }
    } catch (err) {
        console.error("Create chat error:", err);
    }
}

function pollEngine() {
    loadConversations();
    if (activeConversationId) fetchMessages(false);
}

function showMobileChatView() {
    if (window.innerWidth < 768) {
        const sidebar = document.getElementById('chatSidebar');
        const main = document.getElementById('mainChatWindow');
        if (sidebar) sidebar.classList.add('mobile-hidden');
        if (main) main.classList.remove('mobile-hidden');
    }
}

function showMobileSidebarView() {
    if (window.innerWidth < 768) {
        const sidebar = document.getElementById('chatSidebar');
        const main = document.getElementById('mainChatWindow');
        if (sidebar) sidebar.classList.remove('mobile-hidden');
        if (main) main.classList.add('mobile-hidden');
    }
}

/**
 * Format message text supporting Code Blocks and Markdown Bolding/Italics
 */
function formatMessageBody(text) {
    if (!text) return '';
    let escaped = escapeHtml(text);

    // Code blocks ```code```
    escaped = escaped.replace(/```([\s\S]*?)```/g, '<pre class="bg-dark text-info p-2 rounded my-1 font-monospace extra-small"><code>$1</code></pre>');
    // Inline code `code`
    escaped = escaped.replace(/`([^`]+)`/g, '<code class="bg-dark text-warning px-1 rounded extra-small">$1</code>');
    // Bold **text**
    escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    // Italics *text*
    escaped = escaped.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    // Line breaks
    escaped = escaped.replace(/\n/g, '<br>');

    return escaped;
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}