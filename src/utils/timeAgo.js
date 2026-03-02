/**
 * Calculate a human-readable "time ago" string from a Unix timestamp (seconds).
 *
 * @param {number} timestamp  Unix timestamp in seconds.
 * @returns {string}
 */
export function calculateTimeAgo(timestamp) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - timestamp;

    if (diff < 60) return `${diff} seconds`;
    if (diff < 3600) return `${Math.floor(diff / 60)} minutes`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} hours`;
    return `${Math.floor(diff / 86400)} days`;
}
