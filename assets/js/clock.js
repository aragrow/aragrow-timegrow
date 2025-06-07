// clock.js

jQuery(document).ready(function ($) {
  console.log('clock.js loaded');

  let currentEntryId = initialStatus.entryId;
  let clockInTimestamp = initialStatus.clockInTimestamp; // Store as UNIX timestamp
  let clockOutTimestamp = initialStatus.clockOutTimestamp || null;
  let isClockedIn = initialStatus.status === 'clocked_in';
  let isClientIn = initialStatus.client === false;
  const $clockButton = $('#timegrow-clock-toggle');
  const $clockStatusText = $('#timegrow-clock-status-text');
  const $clockInTime = $('#timegrow-clock-in-time');
  const $clockOutTime = $('#timegrow-clock-out-time');
  const $clockTotalDuration = $('#timegrow-clock-total-duration');

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
      $clockButton.text(i18n.clockOut);
    } else {
      $clockButton.text(i18n.clockIn);
    }
  }

  function updateStatusDisplay() {
    console.log('Exec: updateStatusDisplay');

    let clockInDate = clockInTimestamp ? new Date(clockInTimestamp * 1000) : null;
    let clockOutDate = clockOutTimestamp ? new Date(clockOutTimestamp * 1000) : null;

    if (isClockedIn && clockInDate) {
      const clockInStr = `${clockInDate.toLocaleTimeString()} (${clockInDate.toLocaleDateString()})`;
      $clockStatusText.html(`${i18n.youAreClockedInAt} <strong>${clockInStr}</strong>`);
      $clockInTime.text(`${i18n.clockInTime}: ${clockInStr}`).show();
      $clockOutTime.hide();
      $clockTotalDuration.hide();
    } else if (!isClockedIn && clockInDate && clockOutDate) {
      const clockInStr = `${clockInDate.toLocaleTimeString()} (${clockInDate.toLocaleDateString()})`;
      const clockOutStr = `${clockOutDate.toLocaleTimeString()} (${clockOutDate.toLocaleDateString()})`;
      const durationSeconds = Math.floor((clockOutDate - clockInDate) / 1000);
      const hours = Math.floor(durationSeconds / 3600);
      const minutes = Math.floor((durationSeconds % 3600) / 60);
      const seconds = durationSeconds % 60;

      $clockStatusText.html(i18n.youAreClockedOut);
      $clockInTime.text(`${i18n.clockInTime}: ${clockInStr}`).show();
      $clockOutTime.text(`${i18n.clockOutTime}: ${clockOutStr}`).show();
      $clockTotalDuration.text(`${i18n.totalWorkedDuration}: ${hours}h ${minutes}m ${seconds}s`).show();
    } else {
      $clockStatusText.html(i18n.youAreClockedOut);
      $clockInTime.hide();
      $clockOutTime.hide();
      $clockTotalDuration.hide();
    }
  }
});

jQuery(document).ready(function($) {
    const isClockedIn = false; // Replace with dynamic PHP/JS logic as needed

    if (!isClockedIn) {
        $('#client-drop-section').show();
        $('#client-tiles-container').show();
    }

    // Make client tiles draggable
    $('.timegrow-client-tile').attr('draggable', true);

    $('.timegrow-client-tile').on('dragstart', function (e) {
        e.originalEvent.dataTransfer.setData('client-id', $(this).data('client-id'));
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

            const clientId = e.originalEvent.dataTransfer.getData('client-id');
            if (clientId) {
                // ✅ Trigger your clock-in logic here (e.g. AJAX)
                console.log('Clocking in with client ID:', clientId);

                // Optionally disable tiles/drop zone
                $('.timegrow-client-tile').prop('draggable', false).css('opacity', 0.5);
                $('#drop-zone').text(`Clocked in with ${clientId}`).css('color', '#28a745');
            }
        });
});