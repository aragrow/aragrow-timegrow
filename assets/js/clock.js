// File: assets/js/clock.js

jQuery(document).ready(function($) { // Use jQuery's DOM ready and pass $ as an alias
    // Access the global data object
    const appData = window.timegrowClockAppVanillaData || {}; // Keep this as it's from wp_localize_script
    console.log(appData);
    const { i18n, initialStatus, apiNonce, timegrowApiEndpoint } = appData;

    console.log('initialStatus: ', initialStatus);
    // DOM Elements (jQuery selectors)
    const $clockInBtn = $('#timegrow-clock-in-btn');
    const $clockOutBtn = $('#timegrow-clock-out-btn');
    const $messageArea = $('#timegrow-message-area');
    const $statusDisplayArea = $('#timegrow-status-display-area');
    const $currentDateEl = $('#timegrow-current-date');
    const $currentTimeEl = $('#timegrow-current-time');

    let isClockedIn = initialStatus.status === 'clocked_in';
    let currentEntryId = initialStatus.entryId;
    let clockInTimestamp = initialStatus.clockInTimestamp; // Store as UNIX timestamp
    let isLoading = false;

    // --- Helper Functions ---
    function showMessage(message, isError = false) {
        $messageArea.text(message)
                    .removeClass('success error')
                    .addClass(isError ? 'error' : 'success')
                    .show();
    }

    function clearMessage() {
        $messageArea.text('').hide();
    }

    function updateButtonStates() {
        if (isClockedIn || isLoading) {
            $clockInBtn.prop('disabled', true).removeClass('active').addClass('disabled');
        } else {
            $clockInBtn.prop('disabled', false).removeClass('disabled').addClass('active');
        }

        if (!isClockedIn || isLoading) {
            $clockOutBtn.prop('disabled', true).removeClass('active').addClass('disabled');
        } else {
            $clockOutBtn.prop('disabled', false).removeClass('disabled').addClass('active');
        }
        // Update button text if loading
        $clockInBtn.text((isLoading && !isClockedIn) ? i18n.loading : i18n.clockIn);
        $clockOutBtn.text((isLoading && isClockedIn) ? i18n.loading : i18n.clockOut);
    }

    function updateStatusDisplay() {
        console.log('Exec: updateStatusDisplay');
        let statusHtml = '';
        if (isClockedIn && clockInTimestamp) {
            const date = new Date(clockInTimestamp * 1000);
            const timeString = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            const dateString = date.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            statusHtml = `<p>${i18n.youAreClockedInAt} <strong>${timeString} (${dateString})</strong></p>`;
        } else {
            statusHtml = `<p>${i18n.youAreClockedOut}</p>`;
        }
        $statusDisplayArea.html(statusHtml);
    }

    function updateCurrentTimeDisplay() {
        console.log('Exec: updateCurrentTimeDisplay');
        const now = new Date();
        if ($currentDateEl.length) { // Check if element exists
            $currentDateEl.text(now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));
        }
        if ($currentTimeEl.length) { // Check if element exists
            $currentTimeEl.text(now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
        }
        console.log('dateSet: '+$currentDateEl.text()+' '+$currentTimeEl.text())
    }

    // --- API Call Function (using jQuery.ajax) ---
    // Note: async/await can still be used with jQuery's Deferred/Promise objects, but the structure changes slightly.
    // For simplicity with $.ajax, we'll use its .done(), .fail(), .always() methods.
    function makeApiCall(endpoint, method = 'POST', data = {}) {
        isLoading = true;
        updateButtonStates();
        clearMessage();

        return $.ajax({
            url: timegrowApiEndpoint + endpoint,
            method: method,
            contentType: 'application/json',
            dataType: 'json', // Expect JSON response
            data: Object.keys(data).length ? JSON.stringify(data) : null,
            headers: {
                'X-WP-Nonce': apiNonce
            }
        }).always(function() { // This will run after done or fail
            isLoading = false;
            // updateButtonStates(); // Update states again in done/fail for more specific feedback
        });
        // The promise returned by $.ajax can be used with .then() if preferred,
        // but .done() and .fail() are classic jQuery.
    }

    // --- Event Handlers ---
    if ($clockInBtn.length) { // Check if element exists
        $clockInBtn.on('click', function () {
            if (isClockedIn || isLoading) return;

            makeApiCall('clock-in', 'POST')
                .done(function(result) {
                    if (result && result.success) {
                        isClockedIn = true;
                        currentEntryId = result.data.entryId;
                        clockInTimestamp = result.data.clockInTimestamp;
                        showMessage(result.message || i18n.clockedInSuccess);
                    } else {
                        showMessage(result.message || i18n.clockInError, true);
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    let errorMsg = i18n.clockInError;
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorMsg = jqXHR.responseJSON.message;
                    } else if (errorThrown) {
                        errorMsg = errorThrown;
                    }
                    console.error('Clock In API Error:', textStatus, errorThrown, jqXHR.responseText);
                    showMessage(errorMsg, true);
                })
                .always(function() {
                    updateButtonStates();
                    updateStatusDisplay();
                });
        });
    }

    if ($clockOutBtn.length) { // Check if element exists
        $clockOutBtn.on('click', function () {
            if (!isClockedIn || isLoading || !currentEntryId) return;

            makeApiCall('clock-out', 'POST', { entryId: currentEntryId })
                .done(function(result) {
                    if (result && result.success) {
                        isClockedIn = false;
                        currentEntryId = null;
                        clockInTimestamp = null;
                        showMessage(result.message || i18n.clockedOutSuccess);
                    } else {
                        showMessage(result.message || i18n.clockOutError, true);
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    let errorMsg = i18n.clockOutError;
                     if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorMsg = jqXHR.responseJSON.message;
                    } else if (errorThrown) {
                        errorMsg = errorThrown;
                    }
                    console.error('Clock Out API Error:', textStatus, errorThrown, jqXHR.responseText);
                    showMessage(errorMsg, true);
                })
                .always(function() {
                    updateButtonStates();
                    updateStatusDisplay();
                });
        });
    }

    // --- Initial Setup ---
    updateButtonStates();
    updateCurrentTimeDisplay();
    setInterval(updateCurrentTimeDisplay, 1000);
});