// File: assets/js/voice-text.js
// Reading and writing dates, times and hours for the voice assistant (voice-entry.js): "yesterday",
// "9:30 am", "two and a half hours" in; "Thu, Oct 15", "2h 30m", "two and a half hours" out.
// Pure functions: no chat state. `today` is the site's date, YYYY-MM-DD.
(function (window) {
  const norm = s => String(s || '').toLowerCase().replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();

  /** The one item whose name matches: exactly, else the only partial match. null otherwise. */
  function matchOne(text, items, name) {
    const t = norm(text);
    if (!t) return null;
    const exact = items.filter(i => norm(name(i)) === t);
    if (exact.length === 1) return exact[0];
    const partial = items.filter(i => norm(name(i)).indexOf(t) !== -1 || t.indexOf(norm(name(i))) !== -1);
    return partial.length === 1 ? partial[0] : null;
  }

  function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function todayDate(today) { const p = String(today || ymd(new Date())).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }

  function parseDate(text, todayYmd) {
    const t = norm(text), today = todayDate(todayYmd);
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
    if (iso) { const d = new Date(+iso[1], +iso[2] - 1, +iso[3]); return d <= todayDate(todayYmd) ? ymd(d) : null; }
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

  const fmtHours = h => { const m = Math.round(h * 60); return Math.floor(m / 60) + 'h' + (m % 60 ? ' ' + (m % 60) + 'm' : ''); };
  const fmtDate = d => { const p = d.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }); };
  const fmtTime = t => { const p = t.split(':'); const d = new Date(2000, 0, 1, +p[0], +p[1]); return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }); };

  // Natural spoken forms: "two and a half hours", "Thursday, October 15th"
  function spokenHours(h) {
    const whole = Math.floor(h), mins = Math.round((h - whole) * 60);
    if (!whole) return mins + ' minutes';
    const unit = whole === 1 ? 'hour' : 'hours';
    if (mins === 30) return whole + ' and a half ' + unit;
    if (mins === 15) return whole + ' ' + unit + ' and 15 minutes';
    if (mins === 45) return whole + ' ' + unit + ' and 45 minutes';
    return whole + ' ' + unit;
  }
  function spokenDate(d, todayYmd, lang) {
    if (d === ymd(todayDate(todayYmd))) return 'today';
    const y = todayDate(todayYmd); y.setDate(y.getDate() - 1);
    if (d === ymd(y)) return 'yesterday';
    const p = d.split('-');
    return 'on ' + new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString(lang, { weekday: 'long', month: 'long', day: 'numeric' });
  }

  window.TimeGrowVoiceText = {
    TIME: TIME, norm: norm, matchOne: matchOne, ymd: ymd, todayDate: todayDate, parseDate: parseDate, timeFrom: timeFrom,
    parseTime: parseTime, parseHours: parseHours, parseBillable: parseBillable,
    fmtHours: fmtHours, fmtDate: fmtDate, fmtTime: fmtTime, spokenHours: spokenHours, spokenDate: spokenDate,
  };
})(window);
