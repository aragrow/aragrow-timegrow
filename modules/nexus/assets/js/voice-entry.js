// File: assets/js/voice-entry.js
// Voice entry on the Manual Time Entry page: a chat that asks for the client, project, day, start
// time, hours, billable and description, by voice (Web Speech API) or typing. Free-form answers are
// read by the site's AI (TimeGrowNexusVoice.php); short answers and taps are read here. Nothing is
// saved until the person confirms; then the normal Manual form saves the entry.
jQuery(document).ready(function($) {
  const cfg = window.timegrowVoice || {};
  const on = v => v === true || v === 1 || v === '1'; // wp_localize_script sends true / false as "1" / ""
  const $form = $('#timegrow-nexus-entry-form');
  if (!$form.length || !window.TimeGrowProjectClients) return;

  const projects = window.TimeGrowProjectClients.collect();
  const ORDER = ['client_id', 'project_id', 'date', 'start_time', 'hours', 'billable', 'description']
    .filter(k => k !== 'start_time' || cfg.askStartTime === undefined || on(cfg.askStartTime)); // Settings > Voice Entry
  const LABELS = { client_id: 'client', project_id: 'project', date: 'day', start_time: 'start time', hours: 'hours', billable: 'billable', description: 'description' };
  const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;

  let fields = {};
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

  const $card = $(`
    <div class="timegrow-voice">
      <button type="button" class="timegrow-voice-open button"><span class="dashicons dashicons-microphone"></span> Speak an entry</button>
      <div class="timegrow-voice-panel" hidden>
        <div class="timegrow-voice-head">
          <strong>Voice entry</strong>
          <span>
            <button type="button" class="timegrow-voice-mute button-link"></button>
            <button type="button" class="timegrow-voice-close button-link" aria-label="Close">&times;</button>
          </span>
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
  $form.closest('.timegrow-nexus-container').before($card);
  const $panel = $card.find('.timegrow-voice-panel'), $log = $card.find('.timegrow-voice-log');
  const $chips = $card.find('.timegrow-voice-chips'), $text = $card.find('.timegrow-voice-text'), $mic = $card.find('.timegrow-voice-mic');
  if (!Recognition || window.isSecureContext === false) $mic.hide(); // the mic needs a supported browser and https
  const setMuteLabel = () => $card.find('.timegrow-voice-mute').text(muted ? 'Sound off' : 'Sound on');
  setMuteLabel();

  function bubble(who, text) {
    $log.append($('<div>', { class: 'timegrow-voice-msg timegrow-voice-' + who }).text(text));
    $log.scrollTop($log[0].scrollHeight);
  }

  function say(text, chips) {
    bubble('bot', text);
    $chips.empty();
    (chips || []).forEach(c => $chips.append($('<button>', { type: 'button', class: 'button timegrow-voice-chip', text: c.label }).data('chip', c)));
    speak(text);
  }

  function speak(text) {
    if (muted || !window.speechSynthesis || !window.SpeechSynthesisUtterance) { if (voiceMode) setTimeout(listen, 300); return; }
    window.speechSynthesis.cancel();
    const u = new window.SpeechSynthesisUtterance(text);
    u.onend = () => { if (voiceMode) listen(); };
    window.speechSynthesis.speak(u);
  }

  let recognizer = null;
  function listen() {
    if (!Recognition || $panel.prop('hidden')) return;
    try { if (recognizer) recognizer.abort(); } catch (e) {}
    recognizer = new Recognition();
    recognizer.lang = cfg.lang || document.documentElement.lang || 'en-US';
    recognizer.interimResults = false;
    recognizer.maxAlternatives = 1;
    recognizer.onstart = () => $mic.addClass('is-listening');
    recognizer.onend = () => $mic.removeClass('is-listening');
    recognizer.onerror = e => {
      $mic.removeClass('is-listening');
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

  function ask(key) {
    asking = key;
    const today = todayDate(), yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
    switch (key) {
      case 'client_id':
        return say('Which client is this for?', clients.map(c => ({ label: c.name, value: c.id })));
      case 'project_id': {
        const theirs = projects.filter(p => p.clientId === String(fields.client_id));
        return say(`Which project for ${clientName()}?`, theirs.map(p => ({ label: p.name, value: p.id })));
      }
      case 'date':
        return say('Which day was it?', [{ label: 'Today', value: ymd(today) }, { label: 'Yesterday', value: ymd(yesterday) }]);
      case 'start_time':
        return say('What time did you start? You can skip this.', [{ label: 'Skip', value: 'skip' }]);
      case 'hours':
        return say('How many hours?', [0.5, 1, 1.5, 2, 3, 4, 8].map(h => ({ label: fmtHours(h), value: h })));
      case 'billable':
        return say('Is it billable?', [{ label: 'Yes', value: 1 }, { label: 'No', value: 0 }]);
      case 'description':
        return say('What did you work on?');
    }
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
    ]);
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
    bubble('me', chip ? chip.label : text);
    $chips.empty();
    $text.val('');

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

    if (chip) { setField(asking, chip.value); return next(); }

    // A plainly simple answer is read here; anything else goes to the AI when it's on
    const strict = asking ? readStrict(asking, text) : null;
    if (strict !== null) { setField(asking, strict); return next(); }
    const local = asking ? readLocally(asking, text) : null; // fallback when the AI can't help

    if (!on(cfg.aiReady)) {
      if (!asking) return next(); // no AI: just ask the questions
      if (local !== null) { setField(asking, local); return next(); }
      return retry();
    }

    const $thinking = $('<div>', { class: 'timegrow-voice-msg timegrow-voice-bot timegrow-voice-thinking', text: '...' }).appendTo($log);
    $.post(cfg.ajaxUrl, { action: 'timegrow_voice_parse', nonce: cfg.nonce, transcript: text, asking: asking, known: JSON.stringify(fields) })
      .done(res => {
        $thinking.remove();
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
        if (asking && local !== null) { setField(asking, local); return next(); }
        return asking ? retry() : next();
      })
      .fail(() => {
        $thinking.remove();
        if (asking && local !== null) { setField(asking, local); return next(); }
        return asking ? retry() : next();
      });
  }

  function retry() {
    const key = asking;
    bubble('bot', `Sorry, I didn't get the ${LABELS[key]}.`);
    ask(key);
  }

  // ------------------------------------------------------------------
  // Saving through the normal Manual form
  // ------------------------------------------------------------------

  function save() {
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
    $form.find('input[name="start_time"]').remove();
    if (fields.start_time && fields.start_time !== 'skip') {
      $form.append($('<input>', { type: 'hidden', name: 'start_time', value: fields.start_time }));
    }
    $('#timegrow-submit').removeClass('disabled').addClass('active');
    voiceMode = false;
    say('Saving your entry.');
    $form.data('voice', true);
    $form.off('.timegrowVoice')
      .one('timegrow:saved.timegrowVoice', () => { $form.off('.timegrowVoice'); say('Saved. Taking you back to the dashboard.'); })
      .one('timegrow:save-failed.timegrowVoice', (e, message) => {
        $form.off('.timegrowVoice').data('voice', false);
        asking = 'confirm';
        say(`That wasn't saved: ${message}`, [{ label: 'Save entry', value: 'save' }, { label: 'Change something', value: 'change' }]);
      });
    $form.trigger('submit');
  }

  function start() {
    fields = {}; ORDER.forEach(k => { fields[k] = null; });
    asking = '';
    say('Tell me what you worked on, for example: "Two hours yesterday on the SEO audit for Copperleaf, keyword research." Or answer my questions one at a time.',
      [{ label: 'Ask me the questions', value: '__questions' }]);
  }

  // ------------------------------------------------------------------
  // Events
  // ------------------------------------------------------------------

  $card.on('click', '.timegrow-voice-open', function() {
    $panel.prop('hidden', false);
    $(this).hide();
    if (!projects.length) {
      $card.find('.timegrow-voice-bar').hide();
      return say("You don't have any projects to record time on yet. Ask an administrator to add you to a project.");
    }
    start();
  });
  $card.on('click', '.timegrow-voice-close', function() {
    $panel.prop('hidden', true);
    $card.find('.timegrow-voice-open').show();
    voiceMode = false;
    try { if (recognizer) recognizer.abort(); } catch (e) {}
    if (window.speechSynthesis) window.speechSynthesis.cancel();
  });
  $card.on('click', '.timegrow-voice-mute', function() {
    muted = !muted;
    try { window.localStorage.setItem('timegrowVoiceMuted', muted ? '1' : '0'); } catch (e) {}
    if (muted && window.speechSynthesis) window.speechSynthesis.cancel();
    setMuteLabel();
  });
  $card.on('click', '.timegrow-voice-chip', function() {
    const chip = $(this).data('chip');
    if (chip.value === '__questions') { bubble('me', chip.label); $chips.empty(); return next(); }
    answer('', chip);
  });
  $mic.on('click', function() { voiceMode = true; listen(); });
  $card.on('click', '.timegrow-voice-send', () => { voiceMode = false; answer($text.val()); });
  $text.on('keypress', e => { if (e.which === 13) { e.preventDefault(); voiceMode = false; answer($text.val()); } });

  // For tests
  window.TimeGrowVoiceEntry = { answer: answer, fields: () => fields, asking: () => asking, readLocally: readLocally, readStrict: readStrict };
});
