/**
 * VALKYRIN Core Engine Script
 */
document.addEventListener('DOMContentLoaded', () => {
    console.log('VALKYRIN Engine Active | Near-ZK Telemetry Engaged');

    // Auto-Highlight Active Navigation Link
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });
});

/**
 * Client-Side Input Sanitizer Utility using DOMPurify
 */
function sanitizeInput(dirty) {
    if (typeof DOMPurify !== 'undefined') {
        return DOMPurify.sanitize(dirty);
    }
    console.warn('DOMPurify missing! Input raw-passed.');
    return dirty;
}

/**
 * Trigger Reward Confetti Engine
 */
function triggerConfetti() {
    if (typeof confetti !== 'undefined') {
        confetti({
            particleCount: 80,
            spread: 60,
            origin: { y: 0.8 }
        });
    }
}