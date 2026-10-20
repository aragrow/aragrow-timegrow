// File: assets/js/voice-speech.js
// Speaking and listening for the voice assistant (voice-entry.js): the browser's speech synthesis,
// with the most natural voice it has, and its speech recognition (Web Speech API). No chat state:
// the chat decides when to speak and listen.
(function (window) {
  // macOS joke voices and old robotic ones
  const NOVELTY = /\b(albert|bad news|bahh|bells|boing|bubbles|cellos|good news|jester|organ|superstar|trinoids|whisper|wobble|zarvox|fred|junior|ralph|kathy|grandma|grandpa|rocko|shelley|flo|eddy|reed|sandy|hysterical|deranged|pipe organ)\b/i;

  /**
   * How natural a voice should sound, from its name: neural / "Natural" / "Premium" / "Enhanced"
   * and Google voices first, joke voices last. Voices in another language don't count. Pure.
   */
  function voiceScore(voice, lang) {
    const want = String(lang || 'en-US').toLowerCase(), have = String(voice.lang || '').toLowerCase().replace('_', '-');
    if (have.split('-')[0] !== want.split('-')[0]) return -1;
    const name = String(voice.name || '');
    if (NOVELTY.test(name)) return 0;
    let score = 10;
    if (/natural|neural/i.test(name)) score += 50;
    else if (/premium/i.test(name)) score += 45;
    else if (/enhanced/i.test(name)) score += 40;
    else if (/^google/i.test(name)) score += 30;
    if (/\b(ava|samantha|allison|evan|zoe|jenny|aria|guy|michelle|emma|brian|andrew|daniel|serena|karen|moira|tessa|libby|sonia|ryan)\b/i.test(name)) score += 10;
    if (have === want) score += 5;
    if (voice.default) score += 1;
    return score;
  }

  function bestVoice(voices, lang) {
    let best = null, bestScore = 0;
    (voices || []).forEach(v => { const sc = voiceScore(v, lang); if (sc > bestScore) { best = v; bestScore = sc; } });
    return best;
  }

  /** Split into sentences: short utterances sound better and avoid Chrome cutting long ones off. */
  function sentences(text) {
    return String(text).match(/[^.!?]+[.!?]+["”]?|[^.!?]+$/g)?.map(t => t.trim()).filter(Boolean) || [String(text)];
  }

  /** What to tell the person when listening fails, by the recognizer's error code. */
  const ERRORS = {
    'not-allowed': 'Microphone access is blocked. Allow the microphone for this site in your browser, or type your answer.',
    'service-not-allowed': 'Microphone access is blocked. Allow the microphone for this site in your browser, or type your answer.',
    'no-speech': "I didn't hear anything. Tap the microphone to try again, or type your answer.",
    'audio-capture': 'No microphone was found. Type your answer instead.',
    'network': 'Speech recognition needs an internet connection. Type your answer instead.',
  };

  /**
   * A speaker and listener for one chat.
   *
   * @param {Object} opts { lang: () => language code, siteVoice: the default voice's name (Settings > AI Voice Entry) }
   */
  function create(opts) {
    const synth = window.speechSynthesis && window.SpeechSynthesisUtterance ? window.speechSynthesis : null;
    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let chosen = null, recognizer = null;

    /**
     * Pick the voice: the person's own pick in the chat, else the site's, else the most natural one.
     * Returns the voices worth offering, most natural first; null when the browser has none yet.
     */
    function load() {
      if (!synth) return null;
      const voices = synth.getVoices() || [];
      if (!voices.length) return null;
      let savedName = null;
      try { savedName = window.localStorage.getItem('timegrowVoiceName'); } catch (e) {}
      chosen = voices.find(v => v.name === savedName) || voices.find(v => opts.siteVoice && v.name === opts.siteVoice) || bestVoice(voices, opts.lang());
      return voices.filter(v => voiceScore(v, opts.lang()) > 0).sort((a, b) => voiceScore(b, opts.lang()) - voiceScore(a, opts.lang()));
    }

    /** The person picks a voice in the chat: kept for next time. */
    function choose(name) {
      const v = synth ? (synth.getVoices() || []).find(x => x.name === name) : null;
      if (v) { chosen = v; try { window.localStorage.setItem('timegrowVoiceName', v.name); } catch (e) {} }
    }

    /** Read the text aloud, sentence by sentence. `append` waits for what is being read; onDone runs at the end. */
    function speak(text, append, onDone) {
      if (!synth) return;
      if (!append) synth.cancel();
      const parts = sentences(text);
      parts.forEach((part, i) => {
        const u = new window.SpeechSynthesisUtterance(part);
        if (chosen) { u.voice = chosen; u.lang = chosen.lang; } else { u.lang = opts.lang(); }
        u.rate = 1.03;  // a touch quicker than the default sounds less like reading aloud
        u.pitch = 1;
        if (i === parts.length - 1) u.onend = onDone;
        synth.speak(u);
      });
    }

    function abort() {
      try { if (recognizer) recognizer.abort(); } catch (e) {}
    }

    /** Listen once. on: { start, end, error(code), heard(text) }. */
    function listen(on) {
      if (!Recognition) return;
      abort();
      recognizer = new Recognition();
      recognizer.lang = opts.lang();
      recognizer.interimResults = false;
      recognizer.maxAlternatives = 1;
      recognizer.onstart = on.start;
      recognizer.onend = on.end;
      recognizer.onerror = e => on.error(e.error);
      recognizer.onresult = e => { const heard = e.results[0][0].transcript; if (heard) on.heard(heard); };
      recognizer.start();
    }

    return {
      canSpeak: !!synth,
      canListen: !!Recognition,
      load: load,
      chosen: () => chosen,
      choose: choose,
      onVoicesChanged: fn => { if (synth && 'onvoiceschanged' in synth) synth.addEventListener('voiceschanged', fn); },
      speak: speak,
      cancel: () => { if (window.speechSynthesis) window.speechSynthesis.cancel(); },
      listen: listen,
      abort: abort,
    };
  }

  window.TimeGrowVoiceSpeech = { voiceScore: voiceScore, bestVoice: bestVoice, sentences: sentences, ERRORS: ERRORS, create: create };
})(window);
