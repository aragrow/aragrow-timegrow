// File: assets/js/voice-log.js
// Saving voice conversations for review (Settings > AI Conversations), when that is on. Messages go to
// the server with the chat's next request (withLog), or on their own a few seconds later, and when the
// chat closes or the page is left. One session per conversation.
(function (window) {
  function newKey() {
    try { if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID(); } catch (e) {}
    return 'tg-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
  }

  /**
   * @param {Object} opts { enabled, ajaxUrl, nonce, post: (url, data) => void }
   */
  function create(opts) {
    let sessionKey = null;
    let queue = [];
    let timer = null;

    function turn(who, text) {
      if (!opts.enabled || !String(text || '').trim()) return;
      if (!sessionKey) sessionKey = newKey();
      queue.push({ who: who, text: String(text).slice(0, 2000) });
      clearTimeout(timer);
      timer = setTimeout(flush, 4000);
    }
    /** Adds the session and the waiting messages to a request, so they are saved in order. */
    function withLog(data) {
      if (!opts.enabled || !sessionKey) return data;
      clearTimeout(timer);
      data.session_key = sessionKey;
      data.log_turns = JSON.stringify(queue.splice(0));
      return data;
    }
    function flush(end) {
      if (!opts.enabled || !sessionKey || (!queue.length && !end)) return;
      const data = withLog({ action: 'timegrow_voice_log', nonce: opts.nonce });
      if (end) data.end = 1;
      opts.post(opts.ajaxUrl, data);
    }
    /** The conversation ends (the chat closes): send what is waiting, and start a new session next time. */
    function end() {
      flush(true);
      sessionKey = null;
      queue = [];
    }
    window.addEventListener('pagehide', () => {
      if (!opts.enabled || !sessionKey || !navigator.sendBeacon) return;
      const form = new FormData();
      const data = withLog({ action: 'timegrow_voice_log', nonce: opts.nonce, end: 1 });
      Object.keys(data).forEach(k => form.append(k, data[k]));
      navigator.sendBeacon(opts.ajaxUrl, form);
    });
    return { turn: turn, withLog: withLog, end: end };
  }

  window.TimeGrowVoiceLog = { create: create };
})(window);
