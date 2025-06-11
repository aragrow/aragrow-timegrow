// clock.js

jQuery(document).ready(function ($) {
  console.log('clock.js loaded');
  console.log('Raw timegrowClockAppVanillaData from window:', window.timegrowClockAppVanillaData); // DEBUG
  const appData = window.timegrowClockAppVanillaData || {}; // Fallback just in case
  
  let currentEntryId = appData.entryId || 0;
  let clockInTimestamp = appData.clockInTimestamp || null // Store as UNIX timestamp
  let clockOutTimestamp = appData.clockOutTimestamp || null;
  let isClockedIn = appData.status === 'clocked_in';
  let isClientIn = appData.project === false;
  const $clockButton = $('#timegrow-clock-toggle');
  const $clockStatusText = $('#timegrow-clock-status-text');
  const $clockInTime = $('#timegrow-clock-in-time');
  const $clockOutTime = $('#timegrow-clock-out-time');
  const $clockTotalDuration = $('#timegrow-clock-total-duration');
  const $timegrowCurrentDate = $('#timegrow-current-date');
  const $timegrowCurrentTime = $('#timegrow-current-time');

  updateButtonState();
  updateStatusDisplay();

  $clockButton.on('click', function () {
    console.log('Clock button clicked');
    $.ajax({
      url: timegrow_ajax.ajax_url,
      method: 'POST',
      data: {
        action: 'timegrow_toggle_clock',
        security: timegrow_ajax.nonce,
        entryId: currentEntryId
      },
      success: function (response) {
        console.log('AJAX success:', response);
        if (response.success) {
          const result = response.data;
          isClockedIn = result.status === 'clocked_in';
          currentEntryId = result.entryId;
          clockInTimestamp = result.clockInTimestamp;
          clockOutTimestamp = result.clockOutTimestamp || null;
          updateButtonState();
          updateStatusDisplay();
        } else {
          console.error('Error:', response.data);
        }
      },
      error: function (error) {
        console.error('AJAX error:', error);
      }
    });
  });

  function updateButtonState() {
    if (isClockedIn) {
      $clockButton.text(appData.clockOut);
    } else {
      $clockButton.text(appData.clockIn);
    }
  }


  function updateStatusDisplay() {
    console.log('Exec: updateStatusDisplay');

    let clockInDate = clockInTimestamp ? new Date(clockInTimestamp * 1000) : null;
    let clockOutDate = clockOutTimestamp ? new Date(clockOutTimestamp * 1000) : null;

    if (isClockedIn && clockInDate) {
      const clockInStr = `${clockInDate.toLocaleTimeString()} (${clockInDate.toLocaleDateString()})`;
      $clockStatusText.html(`${appData.youAreClockedInAt} <strong>${clockInStr}</strong>`);
      $clockInTime.text(`${appData.clockInTime}: ${clockInStr}`).show();
      $clockOutTime.hide();
      $clockTotalDuration.hide();
    } else if (!isClockedIn && clockInDate && clockOutDate) {
      const clockInStr = `${clockInDate.toLocaleTimeString()} (${clockInDate.toLocaleDateString()})`;
      const clockOutStr = `${clockOutDate.toLocaleTimeString()} (${clockOutDate.toLocaleDateString()})`;
      const durationSeconds = Math.floor((clockOutDate - clockInDate) / 1000);
      const hours = Math.floor(durationSeconds / 3600);
      const minutes = Math.floor((durationSeconds % 3600) / 60);
      const seconds = durationSeconds % 60;

      $clockStatusText.html(appData.youAreClockedOut);
      $clockInTime.text(`${appData.clockInTime}: ${clockInStr}`).show();
      $clockOutTime.text(`${appData.clockOutTime}: ${clockOutStr}`).show();
      $clockTotalDuration.text(`${appData.totalWorkedDuration}: ${hours}h ${minutes}m ${seconds}s`).show();
    } else {
      $clockStatusText.html(appData.youAreClockedOut);
      $clockInTime.hide();
      $clockOutTime.hide();
      $clockTotalDuration.hide();
    }
  }
  // **** FUNCTION TO UPDATE LIVE DATE AND TIME ****
  function updateLiveClockDisplay() {
        const now = new Date();
        $timegrowCurrentDate.text(now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }));
        $timegrowCurrentTime.text(now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
  }
  // **** END FUNCTION TO UPDATE LIVE DATE AND TIME ****

  console.log('Initializing live clock display.');
  updateLiveClockDisplay(); // Call once immediately
  setInterval(updateLiveClockDisplay, 1000); // Update every second

});

jQuery(document).ready(function($) {
    const isClockedIn = false; // Replace with dynamic PHP/JS logic as needed

    if (!isClockedIn) {
        $('#project-drop-section').show();
        $('#project-tiles-container').show();
    }

    // Make project tiles draggable
    $('.timegrow-project-tile').attr('draggable', true);

    $('.timegrow-project-tile').on('dragstart', function (e) {
        e.originalEvent.dataTransfer.setData('project-id', $(this).data('project-id'));
        e.originalEvent.dataTransfer.setData('name', $(this).data('project-name'));
        e.originalEvent.dataTransfer.setData('desc', $(this).data('project-desc') || 'No description available');
    });

    $('#drop-zone')
        .on('dragover', function (e) {
            e.preventDefault();
            $(this).addClass('dragging-over');
        })
        .on('dragleave', function () {
            $(this).removeClass('dragging-over');
        })
        .on('drop', function (e) {
            e.preventDefault();
            $(this).removeClass('dragging-over');

            const projectId = e.originalEvent.dataTransfer.getData('project-id');
            const projectName = e.originalEvent.dataTransfer.getData('name');
            const projectDesc = e.originalEvent.dataTransfer.getData('desc') || 'No description available';
            if (projectId) {
                // ✅ Trigger your clock-in logic here (e.g. AJAX)
                console.log('Clocking in with project ID:', projectId);

                // Optionally disable tiles/drop zone
                $('.timegrow-project-tile').prop('draggable', false).css('opacity', 0.5);
                $('#drop-zone').html(`Clocked in with ${projectName} (${projectId}) <br /> ${projectDesc}`).css('color', '#28a745')
            }
        });
});