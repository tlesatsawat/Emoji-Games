// Global JavaScript for the Emoji Games platform.
//
// This file defines a few helper utilities that pages can reuse.  It
// provides a wrapper around the Emoji Games SDK for ease of use and
// includes basic UI helpers such as showing messages.

export function showMessage(message, type = 'info') {
    const el = document.createElement('div');
    el.textContent = message;
    el.className = `message message-${type}`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

// Example wrapper around the SDK to handle errors uniformly.
export async function startGame(slug) {
    try {
        const result = await window.EmojiGamesSDK.startRun(slug);
        return result;
    } catch (err) {
        console.error(err);
        showMessage(err.message || 'Failed to start game', 'error');
        throw err;
    }
}
