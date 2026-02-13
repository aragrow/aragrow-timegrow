
// File: assets/js/clock.js
jQuery(document).ready(function($) {
  const $clockInButton = $('#timegrow-clock-in-btn');
  const $clockOutButton = $('#timegrow-clock-out-btn');
  const $entryType = $('#entry_type');
  const $timegrowCurrentDate = $('#timegrow-current-date');
  const $timegrowCurrentTime = $('#timegrow-current-time');

  // Make project tiles draggable
  $('.timegrow-project-tile').attr('draggable', true);

  $('.timegrow-project-tile').on('dragstart', function (e) {
    e.originalEvent.dataTransfer.setData('project-id', $(this).data('project-id'));
    e.originalEvent.dataTransfer.setData('name', $(this).data('project-name'));
    e.originalEvent.dataTransfer.setData('desc', $(this).data('project-desc'));
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
      console.log('Dropping project...');
      e.preventDefault();
      const projectId = e.originalEvent.dataTransfer.getData('project-id');
      const projectName = e.originalEvent.dataTransfer.getData('name');
      const projectDesc = e.originalEvent.dataTransfer.getData('desc');
      if (projectId) {
        $('#project_id').val(projectId);
        $(this).html(`Project selected: ${projectName} (${projectId}) <br /> ${projectDesc}`).css('color', '#28a745');

      $('.timegrow-project-tile').css('opacity', .5);
        updateButtonState();
      }
      $(this).removeClass('dragging-over');
    });

  $clockOutButton.on('click', function(e) {
    $entryType.val('CLOCK_OUT');
  })

  $('#timegrow-nexus-entry-form').on('submit', function (e) {
      e.preventDefault();

      var payload = $(this).serialize();
   
      // Get the current time from the div
      const currentTime = $('#timegrow-current-time').text();
      const prettyDate = $('#timegrow-current-date').text();

      var dateObj = new Date(prettyDate);
      var currentDate = dateObj.getFullYear() + '-' +
                      String(dateObj.getMonth() + 1).padStart(2, '0') + '-' +
                      String(dateObj.getDate()).padStart(2, '0');

      // Combine date and time (assuming you want both)
      const clockTime = currentDate + ' ' + currentTime;

      // Replace the empty clock_time with the actual time
      payload = payload.replace('&clock_time=', '&clock_time=' + encodeURIComponent(clockTime));

      console.log('Submitting:', payload);

      // Before AJAX
      $clockInButton.prop('disabled', true);
      $clockOutButton.prop('disabled', true);
      
      // Submit via AJAX or handle as needed

      $.ajax({
        url: timegrow_ajax.ajax_url,
        method: 'POST',
        data: payload,
        async: true,
        success: function (response) {
          console.log('AJAX success:', response);
          alert('Time Recorded Successfully.');
          window.location.href = 'admin.php?page=timegrow-nexus';
        },
        error: function (error) {
          alert('Unable to record time.');
          console.error('AJAX error:', error);
        }
    });
  });

  function updateButtonState() {
      if ($entryType.val() == 'CLOCK_IN') {
        $clockInButton.removeClass('disabled').addClass('active');
        $clockInButton.css('background-color', '#000000');
      } else {
        $clockOutButton.removeClass('disabled').addClass('active');
        $clockOutButton.css('background-color', '#000000');
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

