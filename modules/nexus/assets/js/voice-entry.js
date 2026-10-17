// File: assets/js/voice-entry.js
// Voice entry: a chat that asks for the client, project, day, start time, hours, billable and
// description, by voice (Web Speech API) or typing. Free-form answers are read by the site's AI
// (TimeGrowNexusVoice.php); short answers and taps are read here. Nothing is saved until the person
// confirms.
// A round mic icon floats in the bottom-right corner of every admin page; it opens the chat above it.
// The first thing said can also be a question about TimeGrow data ("how many hours this week?") or,
// for people allowed to, a request to add a client or project (TimeGrowNexusAssistant.php). The
// server decides what is allowed and writes the answers; adding always asks for a confirmation.
// - Manual Time Entry page: the normal form saves the entry.
// - Any other admin page: it saves through the same AJAX action (save_time_entry) without leaving the page.
jQuery(document).ready(function($) {
  const cfg = window.timegrowVoice || {};
  const on = v => v === true || v === 1 || v === '1'; // wp_localize_script sends true / false as "1" / ""
  const $form = $('#timegrow-nexus-entry-form');
  const floating = !$form.length;

  // The person's projects: sent by the server, else read from the Manual page's project tiles
  let projects = [];
  if (Array.isArray(cfg.projects) && cfg.projects.length) {
    projects = cfg.projects.map(p => {
      const project = { id: p.id, name: String(p.name), clientId: String(p.clientId || ''), clientName: String(p.clientName || '') };
      project.label = project.clientName ? project.name + ' (' + project.clientName + ')' : project.name;
      return project;
    }).sort((a, b) => a.clientName.localeCompare(b.clientName) || a.name.localeCompare(b.name));
  } else if (!floating && window.TimeGrowProjectClients) {
    projects = window.TimeGrowProjectClients.collect();
  }
  if (!floating && !projects.length && !window.TimeGrowProjectClients) return;
  const ORDER = ['client_id', 'project_id', 'date', 'start_time', 'hours', 'billable', 'description']
    .filter(k => k !== 'start_time' || cfg.askStartTime === undefined || on(cfg.askStartTime)); // Settings > Voice Entry
  const LABELS = { client_id: 'client', project_id: 'project', date: 'day', start_time: 'start time', hours: 'hours', billable: 'billable', description: 'description' };
  const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;

  let fields = {};
  let draft = null;         // a client or project being added: { type, values }
  const allClients = Array.isArray(cfg.allClients) ? cfg.allClients.map(c => ({ id: Number(c.id), name: String(c.name) })) : [];
  let asking = '';          // field being asked, '' for the opening, 'confirm' or 'change'
  let voiceMode = false;    // the person is talking: listen again after each question
  let muted = cfg.speak !== undefined && !on(cfg.speak); // Settings > Voice Entry default; each person's choice wins
  try { const saved = window.localStorage.getItem('timegrowVoiceMuted'); if (saved !== null) muted = saved === '1'; } catch (e) {}

  // ------------------------------------------------------------------
  // Reading short answers (no AI needed)
  // ------------------------------------------------------------------

  const clients = [];
  projects.forEach(p => { if (p.clientId && !clients.some(c => c.id === p.clientId)) clients.push({ id: p.clientId, name: p.clientName }); });
  const norm = s => String(s || '').toLowerCase().replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();

  function matchOne(text, items, name) {
    const t = norm(text);
    if (!t) return null;
    const exact = items.filter(i => norm(name(i)) === t);
    if (exact.length === 1) return exact[0];
    const partial = items.filter(i => norm(name(i)).indexOf(t) !== -1 || t.indexOf(norm(name(i))) !== -1);
    return partial.length === 1 ? partial[0] : null;
  }

  function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function todayDate() { const p = String(cfg.today || ymd(new Date())).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }

  function parseDate(text) {
    const t = norm(text), today = todayDate();
    if (/\btoday\b/.test(t)) return ymd(today);
    if (/day before yesterday/.test(t)) { today.setDate(today.getDate() - 2); return ymd(today); }
    if (/\byesterday\b/.test(t)) { today.setDate(today.getDate() - 1); return ymd(today); }
    const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    const day = days.findIndex(d => t.indexOf(d) !== -1);
    if (day !== -1) {
      let back = (today.getDay() - day + 7) % 7;
      if (back === 0 && /\blast\b/.test(t)) back = 7; // "last Friday" said on a Friday
      today.setDate(today.getDate() - back);
      return ymd(today);
    }
    const iso = String(text).match(/(\d{4})-(\d{1,2})-(\d{1,2})/);
    if (iso) { const d = new Date(+iso[1], +iso[2] - 1, +iso[3]); return d <= todayDate() ? ymd(d) : null; }
    return null;
  }

  const TIME = String.raw`(\d{1,2})(?:[:. ](\d{2}))?\s*(a\.?\s?m\.?|p\.?\s?m\.?)?`;

  function timeFrom(h, min, ampm) {
    h = +h; min = min ? +min : 0;
    const mer = ampm ? ampm.replace(/[^ap]/g, '') : '';
    if (mer === 'p' && h < 12) h += 12;
    if (mer === 'a' && h === 12) h = 0;
    return h <= 23 && min <= 59 ? String(h).padStart(2, '0') + ':' + String(min).padStart(2, '0') : null;
  }

  function parseTime(text) {
    const t = norm(text);
    if (/^(skip|no|none|nope|no time|skip it)$/.test(t)) return 'skip';
    if (/\bnoon\b/.test(t)) return '12:00';
    const m = String(text).toLowerCase().match(new RegExp(TIME));
    return m ? timeFrom(m[1], m[2], m[3]) : null;
  }

  function parseHours(text) {
    const lower = String(text).toLowerCase();
    let t = lower.replace(/[^a-z0-9.: ]/g, ' ').replace(/\.(?!\d)/g, ' ').replace(/\s+/g, ' ').trim(); // keeps "2.5"
    if (/three quarters? of an? hour/.test(t)) return 0.75;
    if (/^(a )?quarter (of an? )?hour$/.test(t)) return 0.25;
    if (/^(a )?half (an? )?hour$/.test(t)) return 0.5;

    // "from 9 to 11:30", "9am to noon": the time between
    const range = lower.match(new RegExp(String.raw`(?:from\s+)?` + TIME + String.raw`\s*(?:to|until|till|-)\s*(?:` + TIME + String.raw`|(noon))`));
    if (range) {
      const start = timeFrom(range[1], range[2], range[3]);
      const end = range[7] ? '12:00' : timeFrom(range[4], range[5], range[6]);
      if (start && end) {
        let mins = (+end.slice(0, 2) * 60 + +end.slice(3)) - (+start.slice(0, 2) * 60 + +start.slice(3));
        if (mins <= 0) mins += 12 * 60; // "9 to 1" means 1 pm
        return mins > 0 && mins <= 24 * 60 ? Math.round(mins / 15) / 4 : null;
      }
    }

    const words = { one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9, ten: 10, eleven: 11, twelve: 12 };
    t = t.replace(/\b(one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\b/g, w => words[w]).replace(/\ban? hour\b/g, '1 hour');
    let total = null;
    const hm = t.match(/(\d+(?:\.\d+)?)\s*(?:h|hr|hrs|hours?)\b(?:\s*(?:and\s*)?(\d+)\s*(?:m|min|mins|minutes?)\b)?/);
    const minutes = t.match(/^(\d+)\s*(?:m|min|mins|minutes?)$/);
    const bare = t.match(/^(\d+(?:\.\d+)?)\b/);
    if (hm) total = +hm[1] + (hm[2] ? +hm[2] / 60 : 0);
    else if (minutes) total = +minutes[1] / 60;
    else if (bare) total = +bare[1];
    if (total === null) return null;
    if (/and a half/.test(t)) total += 0.5;
    else if (/and a quarter/.test(t)) total += 0.25;
    else if (/and 3 quarters/.test(t)) total += 0.75;
    return total > 0 && total <= 24 ? Math.round(total * 4) / 4 : null;
  }

  function parseBillable(text) {
    const t = norm(text);
    if (/^(no|nope|not|not billable|non billable|nonbillable|false)\b/.test(t)) return 0;
    if (/^(yes|yeah|yep|sure|billable|it is|true|of course)\b/.test(t)) return 1;
    return null;
  }

  /**
   * Read an answer only when it is plainly simple (a bare number, "yes", a name exactly as listed).
   * Anything else goes to the AI, which reads phrases better; readLocally() is the fallback without AI.
   */
  function readStrict(key, text) {
    const t = norm(text), raw = String(text).trim().toLowerCase();
    switch (key) {
      case 'client_id': { const c = clients.find(c => norm(c.name) === t); return c ? c.id : null; }
      case 'project_id': {
        const pool = projects.filter(p => !fields.client_id || p.clientId === String(fields.client_id));
        const hits = pool.filter(p => norm(p.name) === t || norm(p.label) === t);
        return hits.length === 1 ? hits[0].id : null;
      }
      case 'date': return /^(today|yesterday)$/.test(t) || /^\d{4}-\d{1,2}-\d{1,2}$/.test(raw) ? parseDate(text) : null;
      case 'start_time': return /^(skip|no|none)$/.test(t) || new RegExp('^' + TIME + '$').test(raw) ? parseTime(text) : null;
      case 'hours': return /^\d+(\.\d+)?\s*(h|hrs?|hours?)?\.?$/.test(raw) || /^\d+\s*(m|mins?|minutes?)\.?$/.test(raw) ? parseHours(text) : null;
      case 'billable': return /^(yes|no|yeah|yep|nope)$/.test(t) ? parseBillable(text) : null;
      case 'description': return readLocally('description', text);
    }
    return null;
  }

  /** Read an answer to one question without the AI. null when it can't tell. */
  function readLocally(key, text) {
    switch (key) {
      case 'client_id': { const c = matchOne(text, clients, c => c.name); return c ? c.id : null; }
      case 'project_id': {
        const pool = projects.filter(p => !fields.client_id || p.clientId === String(fields.client_id));
        const p = matchOne(text, pool, p => p.name) || matchOne(text, pool, p => p.label);
        return p ? p.id : null;
      }
      case 'date': return parseDate(text);
      case 'start_time': return parseTime(text);
      case 'hours': return parseHours(text);
      case 'billable': return parseBillable(text);
      case 'description': return String(text).trim().length > 1 ? String(text).trim().replace(/^./, c => c.toUpperCase()) : null;
    }
    return null;
  }

  // ------------------------------------------------------------------
  // The chat
  // ------------------------------------------------------------------

  const MIC = '<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 14.5a3.5 3.5 0 0 0 3.5-3.5V5.5a3.5 3.5 0 1 0-7 0V11a3.5 3.5 0 0 0 3.5 3.5Z"/><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" d="M18.5 11a6.5 6.5 0 0 1-13 0M12 17.5V21M8.5 21h7"/></svg>';
  const $card = $(`
    <div class="timegrow-voice">
      <button type="button" class="timegrow-voice-fab" aria-expanded="false" aria-label="Record time by voice" title="Record time by voice">
        <span class="timegrow-voice-fab-icon">${MIC}</span>
        <span class="timegrow-voice-fab-close" aria-hidden="true">&times;</span>
      </button>
      <div class="timegrow-voice-panel" role="dialog" aria-label="Voice time entry" hidden>
        <div class="timegrow-voice-head">
          <span class="timegrow-voice-title"><span class="timegrow-voice-avatar">${MIC}</span><strong>Voice time entry</strong></span>
          <span>
            <button type="button" class="timegrow-voice-voices button-link" aria-expanded="false">Voice</button>
            <button type="button" class="timegrow-voice-mute button-link"></button>
            <button type="button" class="timegrow-voice-close button-link" aria-label="Close">&times;</button>
          </span>
        </div>
        <div class="timegrow-voice-picker" hidden>
          <label>Voice <select class="timegrow-voice-pick"></select></label>
          <button type="button" class="button button-small timegrow-voice-try">Try it</button>
        </div>
        <div class="timegrow-voice-log" aria-live="polite"></div>
        <div class="timegrow-voice-chips"></div>
        <div class="timegrow-voice-bar">
          <button type="button" class="timegrow-voice-mic button" aria-label="Speak"><span class="dashicons dashicons-microphone"></span></button>
          <input type="text" class="timegrow-voice-text" placeholder="Type or tap the mic" />
          <button type="button" class="timegrow-voice-send button button-primary">Send</button>
        </div>
      </div>
    </div>`);
  $('body').append($card);
  const $panel = $card.find('.timegrow-voice-panel'), $log = $card.find('.timegrow-voice-log');
  const $chips = $card.find('.timegrow-voice-chips'), $text = $card.find('.timegrow-voice-text'), $mic = $card.find('.timegrow-voice-mic');
  if (!Recognition || window.isSecureContext === false) $mic.hide(); // the mic needs a supported browser and https
  const setMuteLabel = () => $card.find('.timegrow-voice-mute').text(muted ? 'Sound off' : 'Sound on');
  setMuteLabel();

  function bubble(who, text) {
    $log.append($('<div>', { class: 'timegrow-voice-msg timegrow-voice-' + who }).text(text));
    $log.scrollTop($log[0].scrollHeight);
  }

  /** Show a message; read it aloud as `spoken` when given (shorter and more natural to hear). */
  function say(text, chips, spoken) {
    bubble('bot', text);
    $chips.empty();
    (chips || []).forEach(c => $chips.append($('<button>', { type: 'button', class: 'button timegrow-voice-chip', text: c.label }).data('chip', c)));
    speak(spoken || text);
  }

  // ------------------------------------------------------------------
  // Speaking: the most natural voice the browser has
  // ------------------------------------------------------------------

  const synth = window.speechSynthesis && window.SpeechSynthesisUtterance ? window.speechSynthesis : null;
  const speechLang = () => (cfg.lang || document.documentElement.lang || 'en-US');
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

  let chosenVoice = null;
  function loadVoices() {
    if (!synth) return;
    const voices = synth.getVoices() || [];
    if (!voices.length) return;
    let savedName = null;
    try { savedName = window.localStorage.getItem('timegrowVoiceName'); } catch (e) {}
    chosenVoice = voices.find(v => v.name === savedName) || bestVoice(voices, speechLang());
    const usable = voices.filter(v => voiceScore(v, speechLang()) > 0).sort((a, b) => voiceScore(b, speechLang()) - voiceScore(a, speechLang()));
    const $pick = $card.find('.timegrow-voice-pick').empty();
    usable.forEach(v => $pick.append($('<option>', { value: v.name, text: v.name.replace(/\s*\(.*\)$/, '') + (voiceScore(v, speechLang()) >= 40 ? ' ★' : '') })));
    if (chosenVoice) $pick.val(chosenVoice.name);
  }

  /** Split into sentences: short utterances sound better and avoid Chrome cutting long ones off. */
  function sentences(text) {
    return String(text).match(/[^.!?]+[.!?]+["”]?|[^.!?]+$/g)?.map(t => t.trim()).filter(Boolean) || [String(text)];
  }

  function speak(text) {
    if (muted || !synth) { if (voiceMode) setTimeout(listen, 300); return; }
    synth.cancel();
    const parts = sentences(text);
    parts.forEach((part, i) => {
      const u = new window.SpeechSynthesisUtterance(part);
      if (chosenVoice) { u.voice = chosenVoice; u.lang = chosenVoice.lang; } else { u.lang = speechLang(); }
      u.rate = 1.03;  // a touch quicker than the default sounds less like reading aloud
      u.pitch = 1;
      if (i === parts.length - 1) u.onend = () => { if (voiceMode) listen(); };
      synth.speak(u);
    });
  }

  let recognizer = null;
  function listen() {
    if (!Recognition || $panel.prop('hidden')) return;
    try { if (recognizer) recognizer.abort(); } catch (e) {}
    recognizer = new Recognition();
    recognizer.lang = cfg.lang || document.documentElement.lang || 'en-US';
    recognizer.interimResults = false;
    recognizer.maxAlternatives = 1;
    recognizer.onstart = () => { $mic.addClass('is-listening'); $card.addClass('is-listening'); };
    recognizer.onend = () => { $mic.removeClass('is-listening'); $card.removeClass('is-listening'); };
    recognizer.onerror = e => {
      $mic.removeClass('is-listening'); $card.removeClass('is-listening');
      voiceMode = false; // don't keep reopening the mic
      const why = {
        'not-allowed': 'Microphone access is blocked. Allow the microphone for this site in your browser, or type your answer.',
        'service-not-allowed': 'Microphone access is blocked. Allow the microphone for this site in your browser, or type your answer.',
        'no-speech': "I didn't hear anything. Tap the microphone to try again, or type your answer.",
        'audio-capture': 'No microphone was found. Type your answer instead.',
        'network': 'Speech recognition needs an internet connection. Type your answer instead.',
      }[e.error];
      if (why) bubble('bot', why);
    };
    recognizer.onresult = e => { const heard = e.results[0][0].transcript; if (heard) answer(heard); };
    recognizer.start();
  }

  // ------------------------------------------------------------------
  // Questions
  // ------------------------------------------------------------------

  const fmtHours = h => { const m = Math.round(h * 60); return Math.floor(m / 60) + 'h' + (m % 60 ? ' ' + (m % 60) + 'm' : ''); };
  const fmtDate = d => { const p = d.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }); };
  const fmtTime = t => { const p = t.split(':'); const d = new Date(2000, 0, 1, +p[0], +p[1]); return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }); };
  const project = () => projects.find(p => String(p.id) === String(fields.project_id));
  const clientName = () => (clients.find(c => c.id === String(fields.client_id)) || {}).name || '';

  function autoFill() {
    if (!fields.client_id && clients.length === 1) fields.client_id = clients[0].id;
    if (fields.client_id && !fields.project_id) {
      const theirs = projects.filter(p => p.clientId === String(fields.client_id));
      if (theirs.length === 1) fields.project_id = theirs[0].id;
    }
  }

  // Spoken only: a short, varied acknowledgement before the next question, like a person would
  const ACKS = ['Got it.', 'Okay.', 'Great.', 'Sounds good.', 'Perfect.', 'Alright.'];
  let ackNext = false, lastAck = '';
  function ack() {
    if (!ackNext) return '';
    ackNext = false;
    const pool = ACKS.filter(a => a !== lastAck);
    lastAck = pool[Math.floor(Math.random() * pool.length)];
    return lastAck + ' ';
  }

  function ask(key) {
    asking = key;
    const today = todayDate(), yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
    const a = ack();
    switch (key) {
      case 'client_id':
        return say('Which client is this for?', clients.map(c => ({ label: c.name, value: c.id })), a + 'Which client is this for?');
      case 'project_id': {
        const theirs = projects.filter(p => p.clientId === String(fields.client_id));
        return say(`Which project for ${clientName()}?`, theirs.map(p => ({ label: p.name, value: p.id })), a + `And which project for ${clientName()}?`);
      }
      case 'date':
        return say('Which day was it?', [{ label: 'Today', value: ymd(today) }, { label: 'Yesterday', value: ymd(yesterday) }], a + 'Which day was this?');
      case 'start_time':
        return say('What time did you start? You can skip this.', [{ label: 'Skip', value: 'skip' }], a + 'What time did you start? Or just say skip.');
      case 'hours':
        return say('How many hours?', [0.5, 1, 1.5, 2, 3, 4, 8].map(h => ({ label: fmtHours(h), value: h })), a + 'How long did you work?');
      case 'billable':
        return say('Is it billable?', [{ label: 'Yes', value: 1 }, { label: 'No', value: 0 }], a + 'Is it billable?');
      case 'description':
        return say('What did you work on?', null, a + 'What did you work on?');
    }
  }

  // Natural spoken forms: "two and a half hours", "Thursday, October 15th", "nine thirty A M"
  function spokenHours(h) {
    const whole = Math.floor(h), mins = Math.round((h - whole) * 60);
    if (!whole) return mins + ' minutes';
    const unit = whole === 1 ? 'hour' : 'hours';
    if (mins === 30) return whole + ' and a half ' + unit;
    if (mins === 15) return whole + ' ' + unit + ' and 15 minutes';
    if (mins === 45) return whole + ' ' + unit + ' and 45 minutes';
    return whole + ' ' + unit;
  }
  function spokenDate(d) {
    if (d === ymd(todayDate())) return 'today';
    const y = todayDate(); y.setDate(y.getDate() - 1);
    if (d === ymd(y)) return 'yesterday';
    const p = d.split('-');
    return 'on ' + new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString(speechLang(), { weekday: 'long', month: 'long', day: 'numeric' });
  }
  function spokenSummary() {
    const p = project();
    return `${spokenHours(+fields.hours)} ${spokenDate(fields.date)}` +
      (fields.start_time && fields.start_time !== 'skip' ? `, starting at ${fmtTime(fields.start_time)}` : '') +
      `, for ${clientName()}, ${p ? p.name : ''}. ${fields.billable ? 'Billable' : 'Not billable'}. ${fields.description}`;
  }

  function summary() {
    const p = project();
    return `${clientName()}, ${p ? p.name : ''}. ${fmtDate(fields.date)}` +
      (fields.start_time && fields.start_time !== 'skip' ? ` at ${fmtTime(fields.start_time)}` : '') +
      `, ${fmtHours(fields.hours)}, ${fields.billable ? 'billable' : 'not billable'}. "${fields.description}".`;
  }

  function next() {
    autoFill();
    const missing = ORDER.find(k => fields[k] === null || fields[k] === undefined || fields[k] === '');
    if (missing) return ask(missing);
    asking = 'confirm';
    say('Here is your entry: ' + summary() + ' Save it?', [
      { label: 'Save entry', value: 'save' }, { label: 'Change something', value: 'change' }, { label: 'Start over', value: 'restart' },
    ], ack() + 'Here is what I have. ' + spokenSummary() + '. Should I save it?');
  }

  // ------------------------------------------------------------------
  // Answers
  // ------------------------------------------------------------------

  function setField(key, value) {
    fields[key] = value;
    if (key === 'project_id') { const p = project(); if (p) fields.client_id = p.clientId; }
    if (key === 'client_id' && fields.project_id && (project() || {}).clientId !== String(value)) fields.project_id = null;
  }

  function answer(text, chip) {
    text = String(text || '').trim();
    if (!text && !chip) return;
    if (asking === 'saving') return;
    if (asking === 'done') { fields = {}; ORDER.forEach(k => { fields[k] = null; }); asking = ''; } // a new entry
    bubble('me', chip ? chip.label : text);
    $chips.empty();
    $text.val('');

    if (draft) return answerDraft(text, chip);

    if (asking === 'confirm') {
      const v = chip ? chip.value : (/^(save|yes|yeah|yep|ok|okay|sure)\b/i.test(text) ? 'save' : /change|edit|fix/i.test(text) ? 'change' : /start over|restart|again/i.test(text) ? 'restart' : '');
      if (v === 'save') return save();
      if (v === 'restart') return start();
      if (v === 'change') {
        asking = 'change';
        return say('What do you want to change?', ORDER.map(k => ({ label: LABELS[k][0].toUpperCase() + LABELS[k].slice(1), value: k })));
      }
      return say('Say "save", "change something" or "start over".', [{ label: 'Save entry', value: 'save' }, { label: 'Change something', value: 'change' }]);
    }

    if (asking === 'change') {
      const key = chip ? chip.value : ORDER.find(k => norm(text).indexOf(LABELS[k]) !== -1);
      if (!key) return say('Which part? For example "hours" or "description".');
      fields[key] = null;
      if (key === 'client_id') fields.project_id = null;
      return next();
    }

    ackNext = true; // the next question starts with "Got it." and the like (spoken only)
    if (chip) { setField(asking, chip.value); return next(); }

    // A plainly simple answer is read here; anything else goes to the AI when it's on
    const strict = asking ? readStrict(asking, text) : null;
    if (strict !== null) { setField(asking, strict); return next(); }
    const local = asking ? readLocally(asking, text) : null; // fallback when the AI can't help

    // The first request always goes to the server: it may be a question or adding something, which
    // it can read without AI too. Later answers only go there when the AI is on.
    if (!on(cfg.aiReady) && asking) {
      if (local !== null) { setField(asking, local); return next(); }
      return retry();
    }

    const $thinking = $('<div>', { class: 'timegrow-voice-msg timegrow-voice-bot timegrow-voice-thinking', text: '...' }).appendTo($log);
    $.post(cfg.ajaxUrl, { action: 'timegrow_voice_parse', nonce: cfg.nonce, transcript: text, asking: asking, known: JSON.stringify(fields) })
      .done(res => {
        $thinking.remove();
        if (res && res.success && res.data && !asking && res.data.intent && res.data.intent !== 'record_time') return handleIntent(res.data);
        if (res && res.success && res.data && res.data.fields) {
          const before = asking ? fields[asking] : null;
          ORDER.forEach(k => { if (res.data.fields[k] !== null && res.data.fields[k] !== undefined) fields[k] = res.data.fields[k]; });
          if (fields.project_id) { const p = project(); if (p) fields.client_id = p.clientId; else fields.project_id = null; } // client always the project's
          if (asking && (fields[asking] === null || fields[asking] === undefined) && before === null) {
            if (local !== null) { setField(asking, local); return next(); } // the AI missed it; our reading may still have it
            return retry();
          }
          return next();
        }
        if (res && res.data && res.data.code === 'blocked') bubble('bot', res.data.message); // looked like instructions to the AI
        if (asking && local !== null) { setField(asking, local); return next(); }
        return asking ? retry() : next();
      })
      .fail(() => {
        $thinking.remove();
        if (asking && local !== null) { setField(asking, local); return next(); }
        return asking ? retry() : next();
      });
  }

  // ------------------------------------------------------------------
  // Questions and adding (the server checks what this person may do)
  // ------------------------------------------------------------------

  const suggestionChips = (n) => (Array.isArray(cfg.suggestions) ? cfg.suggestions : []).slice(0, n).map(t => ({ label: t, value: '__say', say: t }));

  function handleIntent(data) {
    ackNext = false;
    if (data.intent === 'question' || data.intent === 'denied') return showAnswer(data.answer || {});
    if (data.intent === 'add_client') return startDraft('client', data.draft);
    if (data.intent === 'add_project') return startDraft('project', data.draft);
    asking = '';
    say('I can record your time or answer questions about TimeGrow. Try one of these, or tell me what you worked on.',
      [{ label: 'Record time', value: '__questions' }].concat(suggestionChips(6)),
      'I can record your time, or answer questions about TimeGrow. What would you like?');
  }

  /** The answer, with its list (links open in the same tab only for pages the person can use). */
  function showAnswer(a) {
    asking = '';
    bubble('bot', a.text || '');
    if (Array.isArray(a.rows) && a.rows.length) {
      const $list = $('<ul>', { class: 'timegrow-voice-msg timegrow-voice-bot timegrow-voice-list' });
      a.rows.forEach(r => {
        const $li = $('<li>');
        $li.append(r.url ? $('<a>', { href: r.url, text: r.title }) : $('<strong>', { text: r.title }));
        if (r.meta) $li.append($('<span>', { text: r.meta }));
        $list.append($li);
      });
      $log.append($list);
      $log.scrollTop($log[0].scrollHeight);
    }
    $chips.empty();
    [{ label: 'Record time', value: '__questions' }].concat(suggestionChips(3)).forEach(c => $chips.append($('<button>', { type: 'button', class: 'button timegrow-voice-chip', text: c.label }).data('chip', c)));
    speak(a.spoken || a.text || '');
  }

  const DRAFTS = {
    client: { order: ['name', 'email', 'company'], labels: { name: 'name', email: 'email', company: 'company' } },
    project: { order: ['client_id', 'name', 'rate', 'billable'], labels: { client_id: 'client', name: 'name', rate: 'rate', billable: 'billable' } },
  };
  const SKIP = /^(skip|no|none|nope|no thanks|skip it|not now)$/;
  const blank = v => v === null || v === undefined || v === '';

  function startDraft(type, values) {
    draft = { type: type, values: {} };
    Object.keys(values || {}).forEach(k => { if (!blank(values[k])) draft.values[k] = values[k]; });
    if (type === 'project' && draft.values.client_id && !allClients.some(c => c.id === Number(draft.values.client_id))) delete draft.values.client_id;
    askDraft();
  }

  function askDraft() {
    const key = DRAFTS[draft.type].order.find(k => blank(draft.values[k]));
    if (!key) return confirmDraft();
    asking = 'draft:' + key;
    const a = ack();
    const skip = [{ label: 'Skip', value: 'skip' }];
    const Q = {
      'client:name': ['What is the client\'s name?', null, 'What is the new client\'s name?'],
      'client:email': ['What is their email address? Invoices are sent there. You can skip this.', skip, 'What is their email address? Or say skip.'],
      'client:company': ['Company name? You can skip this.', skip, 'And the company name? Or say skip.'],
      'project:client_id': ['Which client is the project for?', allClients.map(c => ({ label: c.name, value: c.id })), 'Which client is the project for?'],
      'project:name': ['What should the project be called?', null, 'What should the project be called?'],
      'project:rate': ['Hourly rate? Skip to use the client\'s or company rate.', skip, 'What is the hourly rate? Or say skip to use the usual rate.'],
      'project:billable': ['Is it billable?', [{ label: 'Yes', value: 1 }, { label: 'No', value: 0 }], 'Is it billable?'],
    }[draft.type + ':' + key];
    say(Q[0], Q[1], a + Q[2]);
  }

  function readDraft(key, text) {
    const t = norm(text);
    switch (draft.type + ':' + key) {
      case 'client:name': case 'project:name': {
        const v = String(text).trim().replace(/[.!?]+$/, '');
        if (v.length < 2) return null;
        return v === v.toLowerCase() ? v.replace(/\b\w/g, c => c.toUpperCase()) : v; // speech comes in lower case
      }
      case 'client:email': {
        if (SKIP.test(t)) return 'skip';
        const v = String(text).toLowerCase().replace(/\s+at\s+/g, '@').replace(/\s+dot\s+/g, '.').replace(/\s+/g, '').replace(/\.$/, '');
        return /^[^@\s]+@[^@\s]+\.[a-z]{2,}$/.test(v) ? v : null;
      }
      case 'client:company': return SKIP.test(t) ? 'skip' : (String(text).trim().length > 1 ? String(text).trim().replace(/[.]+$/, '') : null);
      case 'project:client_id': { const c = matchOne(text, allClients, c => c.name); return c ? c.id : null; }
      case 'project:rate': {
        if (SKIP.test(t)) return 'skip';
        const m = String(text).replace(/,/g, '').match(/(\d+(?:\.\d+)?)/);
        return m && +m[1] >= 0 && +m[1] <= 10000 ? +m[1] : null;
      }
      case 'project:billable': return parseBillable(text);
    }
    return null;
  }

  function draftSummary() {
    const v = draft.values, real = x => (x === 'skip' ? '' : x);
    if (draft.type === 'client') {
      return 'New client: ' + v.name + (real(v.email) ? ', ' + v.email : ', no email') + (real(v.company) ? ', ' + v.company : '') + '.';
    }
    const client = (allClients.find(c => c.id === Number(v.client_id)) || {}).name || '';
    return 'New project: ' + v.name + ' for ' + client + ', ' + (real(v.rate) !== '' && v.rate !== undefined ? '$' + v.rate + ' an hour' : 'the usual rate') + ', ' + (+v.billable ? 'billable' : 'not billable') + '.';
  }

  function confirmDraft() {
    asking = 'draft:confirm';
    say(draftSummary() + ' Create it?', [{ label: 'Create', value: 'create' }, { label: 'Change something', value: 'change' }, { label: 'Cancel', value: 'cancel' }],
      ack() + draftSummary() + ' Should I create it?');
  }

  function answerDraft(text, chip) {
    if (asking === 'draft:creating') return;
    if (asking === 'draft:confirm') {
      const v = chip ? chip.value : (/^(create|yes|yeah|yep|ok|okay|sure|do it|add it)\b/i.test(text) ? 'create' : /change|edit|fix/i.test(text) ? 'change' : /cancel|stop|never ?mind|no\b/i.test(text) ? 'cancel' : '');
      if (v === 'create') return createDraft();
      if (v === 'cancel') { draft = null; asking = ''; return say('Okay, nothing was added.', suggestionChips(3)); }
      if (v === 'change') {
        asking = 'draft:change';
        const labels = DRAFTS[draft.type].labels;
        return say('What do you want to change?', DRAFTS[draft.type].order.map(k => ({ label: labels[k][0].toUpperCase() + labels[k].slice(1), value: k })));
      }
      return say('Say "create", "change something" or "cancel".', [{ label: 'Create', value: 'create' }, { label: 'Change something', value: 'change' }, { label: 'Cancel', value: 'cancel' }]);
    }
    if (asking === 'draft:change') {
      const labels = DRAFTS[draft.type].labels;
      const key = chip ? chip.value : DRAFTS[draft.type].order.find(k => norm(text).indexOf(labels[k]) !== -1);
      if (!key) return say('Which part? For example "name".');
      delete draft.values[key];
      return askDraft();
    }
    const key = asking.slice(6);
    const value = chip ? chip.value : readDraft(key, text);
    if (value === null || value === undefined) {
      bubble('bot', `Sorry, I didn't get the ${DRAFTS[draft.type].labels[key]}.`);
      ackNext = false;
      return askDraft();
    }
    draft.values[key] = value;
    ackNext = true;
    askDraft();
  }

  function createDraft() {
    asking = 'draft:creating';
    say('Adding it now.');
    const values = {};
    Object.keys(draft.values).forEach(k => { values[k] = draft.values[k] === 'skip' ? '' : draft.values[k]; });
    const type = draft.type;
    $.post(cfg.ajaxUrl, { action: 'timegrow_voice_create', nonce: cfg.nonce, type: type, values: JSON.stringify(values) })
      .done(res => {
        if (res && res.success) {
          const d = res.data || {};
          draft = null;
          asking = '';
          if (d.client) allClients.push({ id: Number(d.client.id), name: String(d.client.name) });
          if (d.project) {
            const p = { id: d.project.id, name: d.project.name, clientId: String(d.project.clientId), clientName: String(d.project.clientName) };
            p.label = p.name + ' (' + p.clientName + ')';
            projects.push(p);
            if (!clients.some(c => c.id === p.clientId)) clients.push({ id: p.clientId, name: p.clientName });
          }
          const next = [{ label: 'Record time', value: '__questions' }];
          if (d.client && on(cfg.canAddProject)) next.unshift({ label: 'Add a project for them', value: '__project_for', clientId: Number(d.client.id) });
          next.push({ label: 'Close', value: '__close' });
          showAnswer({ text: d.message, spoken: 'Done. ' + d.message, rows: d.url ? [{ title: 'Open it', meta: '', url: d.url }] : [] });
          $chips.empty();
          next.forEach(c => $chips.append($('<button>', { type: 'button', class: 'button timegrow-voice-chip', text: c.label }).data('chip', c)));
          return;
        }
        asking = 'draft:confirm';
        say(`That wasn't added: ${(res && res.data && res.data.message) || 'unknown error'}`, [{ label: 'Change something', value: 'change' }, { label: 'Cancel', value: 'cancel' }]);
      })
      .fail(() => { asking = 'draft:confirm'; say("That wasn't added: the site could not be reached.", [{ label: 'Create', value: 'create' }, { label: 'Cancel', value: 'cancel' }]); });
  }

  function retry() {
    const key = asking;
    bubble('bot', `Sorry, I didn't get the ${LABELS[key]}.`);
    ackNext = false;
    ask(key);
  }

  // ------------------------------------------------------------------
  // Saving: through the normal Manual form, or straight to save_time_entry from other pages
  // ------------------------------------------------------------------

  function save() {
    if (floating) return saveDirect();
    const p = project();
    $('#project_id').val(fields.project_id);
    $('#mobile-project-selector').val(fields.project_id); // no change event: it would re-check billable
    $('#project-search').val(p ? p.label : '');
    $('#drop-zone').html('✓ Project Selected: ').append($('<strong>').text(p ? p.label : ''));
    $('#manual-datetime').val(fields.date);
    const $hours = $('#manual-hours');
    if (!$hours.find('option').filter((i, o) => +o.value === +fields.hours).length) {
      $hours.append($('<option>', { value: fields.hours, text: fmtHours(fields.hours) }));
    }
    $hours.find('option').filter((i, o) => +o.value === +fields.hours).prop('selected', true);
    $('#description').val(fields.description);
    $('#nexus-manual_billable').prop('checked', !!+fields.billable);
    $form.find('input[name="start_time"], input[name="client_id"]').remove();
    $form.append($('<input>', { type: 'hidden', name: 'client_id', value: fields.client_id })); // the save checks it is the project's client
    if (fields.start_time && fields.start_time !== 'skip') {
      $form.append($('<input>', { type: 'hidden', name: 'start_time', value: fields.start_time }));
    }
    $('#timegrow-submit').removeClass('disabled').addClass('active');
    voiceMode = false;
    say('Saving your entry.');
    $form.data('voice', true);
    $form.off('.timegrowVoice')
      .one('timegrow:saved.timegrowVoice', () => { $form.off('.timegrowVoice'); say('Saved. Taking you back to the dashboard.', null, 'All done. It\'s saved.'); })
      .one('timegrow:save-failed.timegrowVoice', (e, message) => {
        $form.off('.timegrowVoice').data('voice', false);
        asking = 'confirm';
        say(`That wasn't saved: ${message}`, [{ label: 'Save entry', value: 'save' }, { label: 'Change something', value: 'change' }]);
      });
    $form.trigger('submit');
  }

  function saveDirect() {
    voiceMode = false;
    asking = 'saving';
    say('Saving your entry.');
    const failed = message => {
      asking = 'confirm';
      say(`That wasn't saved: ${message}`, [{ label: 'Save entry', value: 'save' }, { label: 'Change something', value: 'change' }]);
    };
    const data = {
      action: 'save_time_entry', entry_type: 'MAN', time_entry_id: 0, member_id: 0,
      timegrow_time_nexus_nonce_field: cfg.saveNonce, project_id: fields.project_id, client_id: fields.client_id, date: fields.date,
      hours: fields.hours, billable: fields.billable ? 1 : 0, description: fields.description,
    };
    if (fields.start_time && fields.start_time !== 'skip') data.start_time = fields.start_time;
    $.ajax({ url: cfg.ajaxUrl, method: 'POST', data: data })
      .done(res => {
        if (res && res.success) {
          asking = 'done';
          return say('Saved: ' + summary(), [{ label: 'Record another', value: '__restart' }, { label: 'Close', value: '__close' }], 'All done. It\'s saved. Anything else?');
        }
        failed(String((res && res.data && (res.data.message || res.data)) || 'unknown error'));
      })
      .fail(() => failed('The site could not be reached.'));
  }

  function start() {
    fields = {}; ORDER.forEach(k => { fields[k] = null; });
    draft = null;
    asking = '';
    say('Tell me what you worked on, for example: "Two hours yesterday on the SEO audit for Copperleaf, keyword research." Or answer my questions one at a time.',
      [{ label: 'Ask me the questions', value: '__questions' }].concat(suggestionChips(2)),
      'Hi! What did you work on? You can tell me in one go, or ask me something about TimeGrow.');
  }

  // ------------------------------------------------------------------
  // Events
  // ------------------------------------------------------------------

  function open() {
    if (!$panel.prop('hidden')) { $text.trigger('focus'); return; } // already open: keep the conversation
    $panel.prop('hidden', false);
    $card.addClass('is-open').find('.timegrow-voice-fab').attr({ 'aria-expanded': 'true', 'aria-label': 'Close voice entry', title: 'Close voice entry' });
    if (!projects.length) {
      $card.find('.timegrow-voice-bar').hide();
      return say("You don't have any projects to record time on yet. Ask an administrator to add you to a project.");
    }
    start();
    $text.trigger('focus');
  }

  function close() {
    $panel.prop('hidden', true);
    $card.removeClass('is-open is-listening').find('.timegrow-voice-fab').attr({ 'aria-expanded': 'false', 'aria-label': 'Record time by voice', title: 'Record time by voice' });
    voiceMode = false;
    try { if (recognizer) recognizer.abort(); } catch (e) {}
    if (window.speechSynthesis) window.speechSynthesis.cancel();
  }

  $card.on('click', '.timegrow-voice-fab', () => ($panel.prop('hidden') ? open() : close()));
  $card.on('click', '.timegrow-voice-close', close);
  $card.on('keydown', e => { if (e.key === 'Escape') { close(); $card.find('.timegrow-voice-fab').trigger('focus'); } });
  $card.on('click', '.timegrow-voice-voices', function() {
    const $row = $card.find('.timegrow-voice-picker'), show = $row.prop('hidden');
    loadVoices();
    $row.prop('hidden', !show);
    $(this).attr('aria-expanded', show ? 'true' : 'false');
  });
  $card.on('change', '.timegrow-voice-pick', function() {
    const v = synth ? (synth.getVoices() || []).find(x => x.name === this.value) : null;
    if (v) { chosenVoice = v; try { window.localStorage.setItem('timegrowVoiceName', v.name); } catch (e) {} }
  });
  $card.on('click', '.timegrow-voice-try', () => { const was = muted; muted = false; speak("Hi! This is how I'll sound when I ask about your time."); muted = was; });
  if (synth) { loadVoices(); if ('onvoiceschanged' in synth) synth.addEventListener('voiceschanged', loadVoices); }
  if (!synth) $card.find('.timegrow-voice-voices').hide();

  $card.on('click', '.timegrow-voice-mute', function() {
    muted = !muted;
    try { window.localStorage.setItem('timegrowVoiceMuted', muted ? '1' : '0'); } catch (e) {}
    if (muted && window.speechSynthesis) window.speechSynthesis.cancel();
    setMuteLabel();
  });
  $card.on('click', '.timegrow-voice-chip', function() {
    const chip = $(this).data('chip');
    if (chip.value === '__questions') { bubble('me', chip.label); $chips.empty(); draft = null; ackNext = false; return next(); }
    if (chip.value === '__restart') return start();
    if (chip.value === '__close') return close();
    if (chip.value === '__say') return answer(chip.say);                       // a suggested question
    if (chip.value === '__project_for') { bubble('me', chip.label); $chips.empty(); ackNext = false; return startDraft('project', { client_id: chip.clientId }); }
    answer('', chip);
  });
  $mic.on('click', function() { voiceMode = true; listen(); });
  $card.on('click', '.timegrow-voice-send', () => { voiceMode = false; answer($text.val()); });
  $text.on('keypress', e => { if (e.which === 13) { e.preventDefault(); voiceMode = false; answer($text.val()); } });

  // For tests
  window.TimeGrowVoiceEntry = { open: open, close: close, voiceScore: voiceScore, bestVoice: bestVoice, sentences: sentences, spokenHours: spokenHours, answer: answer, fields: () => fields, asking: () => asking, readLocally: readLocally, readStrict: readStrict };
});
