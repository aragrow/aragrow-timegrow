// File: assets/js/voice-approval.js
// Human approval for the voice assistant (voice-entry.js): nothing is saved, created or clocked in or
// out until the person approves it. With Settings > AI Voice Entry > "Saving needs a click" on (the
// default), only a tap on the button approves; a spoken or typed "yes" points at the button instead.
// The server checks it again (TimeGrowNexusVoice::approval_problem).
(function (window) {
  const on = v => v === true || v === 1 || v === '1'; // wp_localize_script sends true / false as "1" / ""

  /** The buttons for each confirmation; the first one approves. */
  function buttons(state, clockAction) {
    return {
      confirm: [{ label: 'Save entry', value: 'save' }, { label: 'Change something', value: 'change' }, { label: 'Start over', value: 'restart' }],
      'draft:confirm': [{ label: 'Create', value: 'create' }, { label: 'Change something', value: 'change' }, { label: 'Cancel', value: 'cancel' }],
      'clock:confirm': [{ label: clockAction === 'out' ? 'Clock out' : 'Clock in', value: 'save' }, { label: 'Change something', value: 'change' }, { label: 'Cancel', value: 'cancel' }],
    }[state];
  }

  window.TimeGrowVoiceApproval = {
    /** Whether only a click approves (the setting; on when it is not sent). */
    needsClick: cfg => cfg.confirmClick === undefined || on(cfg.confirmClick),
    /** How the person approved: a tapped button is a click, else spoken (from the mic) or typed. Sent with the save. */
    how: (chip, spoken) => (chip ? 'click' : (spoken ? 'spoken' : 'typed')),
    /** The message for a "yes" in words when a click is needed: { text, chips, spoken }. */
    tapToApprove(state, clockAction) {
      const chips = buttons(state, clockAction);
      const button = chips[0].label;
      return { text: `To approve it, tap ${button} below.`, chips: chips, spoken: `Please tap ${button} to approve it.` };
    },
  };
})(window);
