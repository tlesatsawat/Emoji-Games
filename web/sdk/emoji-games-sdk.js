/*
 * Emoji Games SDK
 *
 * This lightweight SDK wraps the core REST API endpoints used by all
 * mini‑games.  It abstracts away error handling and provides a simple
 * Promise‑based interface for starting runs, submitting results and
 * fetching leaderboards.
 */

(() => {
  const defaultHeaders = {
    'Content-Type': 'application/json'
  };

  async function request(path, options = {}) {
    const response = await fetch(path, {
      credentials: 'include',
      headers: defaultHeaders,
      ...options
    });
    const json = await response.json().catch(() => ({ ok: false, error: 'Invalid JSON' }));
    if (!json.ok) {
      const err = new Error(json.error || 'API error');
      err.code = json.code;
      throw err;
    }
    return json.data;
  }

  const EmojiGamesSDK = {
    /**
     * Start a new run for the specified game.  Returns an object
     * containing a nonce, server seed and expiry timestamp.  The
     * caller should include these values when submitting the final
     * score.  See `/api/game/start` in the backend for details.
     *
     * @param {string} gameSlug The slug identifying the game folder
     */
    async startRun(gameSlug) {
      return await request('/api/game/start', {
        method: 'POST',
        body: JSON.stringify({ game: gameSlug })
      });
    },

    /**
     * Submit a finished run.  The payload must include the game slug,
     * score, duration in milliseconds, the nonce returned from
     * `startRun` and a client‑side signature if desired.  The server
     * verifies the HMAC and updates leaderboards.
     *
     * @param {string} gameSlug The slug identifying the game folder
     * @param {Object} result An object containing score, duration_ms, stats, nonce and client_sig
     */
    async submitRun(gameSlug, result) {
      return await request('/api/game/submit', {
        method: 'POST',
        body: JSON.stringify({ game: gameSlug, ...result })
      });
    },

    /**
     * Fetch a leaderboard for a given game and period.  Returns an
     * array of leaderboard entries sorted by score descending.
     *
     * @param {string} slug Game slug
     * @param {string} [period='alltime'] One of 'daily', 'weekly' or 'alltime'
     * @param {number} [limit=50] Number of entries to return
     * @param {number} [offset=0] Pagination offset
     */
    async getLeaderboard(slug, period = 'alltime', limit = 50, offset = 0) {
      const params = new URLSearchParams({ game: slug, period, limit: String(limit), offset: String(offset) });
      return await request('/api/leaderboard?' + params.toString(), {
        method: 'GET'
      });
    }
  };

  // Expose globally
  window.EmojiGamesSDK = EmojiGamesSDK;
})();
