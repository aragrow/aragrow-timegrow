
// File: assets/js/clock.js
jQuery(document).ready(function($) {
  const $clockInButton = $('#timegrow-clock-in-btn');
  const $clockOutButton = $('#timegrow-clock-out-btn');
  const $entryType = $('#entry_type');
  const $timegrowCurrentDate = $('#timegrow-current-date');
  const $timegrowCurrentTime = $('#timegrow-current-time');

  // Detect if device is mobile/touch
  const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

  // Create dropdown for mobile users
  createProjectDropdown();

  // Enable drag and drop on desktop
  if (!isTouchDevice) {
    enableDragAndDrop();
  }

  /**
   * Create searchable dropdown for project selection
   */
  function createProjectDropdown() {
    // Collect all projects from tiles
    const projects = [];
    $('.timegrow-project-tile').each(function() {
      projects.push({
        id: $(this).data('project-id'),
        name: $(this).data('project-name'),
        desc: $(this).data('project-desc')
      });
    });

    // Only create if there are projects and dropdown doesn't exist
    if (projects.length === 0 || $('#clock-project-selector').length > 0) {
      return;
    }

    // Sort alphabetically
    projects.sort((a, b) => a.name.localeCompare(b.name));

    // Create dropdown wrapper
    const $wrapper = $('<div id="clock-project-selector-wrapper" style="margin-bottom: 20px;"></div>');
    const $label = $('<label for="clock-project-search" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 15px;">Select Project</label>');
    const $searchInput = $('<input type="text" id="clock-project-search" placeholder="Search projects..." style="width: 100%; min-height: 50px; padding: 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 8px; margin-bottom: 10px; box-sizing: border-box;" />');
    const $datalist = $('<datalist id="clock-projects-datalist"></datalist>');
    const $select = $('<select id="clock-project-selector" style="width: 100%; min-height: 50px; padding: 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 8px; box-sizing: border-box;"></select>');

    // Add options
    $select.append('<option value="">-- Choose a Project --</option>');
    projects.forEach(function(project) {
      $select.append(`<option value="${project.id}">${project.name}</option>`);
      $datalist.append(`<option value="${project.name}" data-id="${project.id}">${project.name}</option>`);
    });

    $searchInput.attr('list', 'clock-projects-datalist');

    // Assemble (hide the select dropdown, keep for data management)
    $wrapper.append($label).append($searchInput).append($datalist).append($select.hide());

    // Insert before drop zone or at start of form
    if ($('#drop-zone').length > 0) {
      $('#drop-zone').before($wrapper);
    } else {
      $('#timegrow-nexus-entry-form').prepend($wrapper);
    }

    // Handle search input
    $searchInput.on('input change', function() {
      const searchValue = $(this).val();
      const selectedProject = projects.find(p => p.name === searchValue);
      if (selectedProject) {
        $select.val(selectedProject.id).trigger('change');
        $(this).blur();
      }
    });

    // Handle selection - sync with dropdown
    $select.on('change', function() {
      const projectId = $(this).val();
      if (projectId) {
        const selectedProject = projects.find(p => p.id == projectId);
        $searchInput.val(selectedProject.name);
      } else {
        $searchInput.val('');
      }
    });

    // Handle selection - update form state
    $select.on('change', function() {
      const projectId = $(this).val();

      if (projectId) {
        const selectedProject = projects.find(p => p.id == projectId);

        // Update hidden field
        $('#project_id').val(projectId);

        // Update drop zone with green styling (same as manual entry)
        if ($('#drop-zone').length > 0) {
          $('#drop-zone')
            .html(`✓ Project Selected: <strong>${selectedProject.name}</strong>`)
            .css('color', '#46b450')
            .css('background-color', '#e8f5e9')
            .css('border-color', '#46b450');
        }

        // Fade out project tiles to indicate selection
        $('.timegrow-project-tile').css('opacity', 0.5);

        // Enable clock in button
        updateButtonState();
      } else {
        // Reset if no selection
        $('#project_id').val('');

        if ($('#drop-zone').length > 0) {
          $('#drop-zone')
            .html('Drop Project Here')
            .css('color', '')
            .css('background-color', '')
            .css('border-color', '');
        }

        $('.timegrow-project-tile').css('opacity', 1);

        // Disable clock in button
        $clockInButton.prop('disabled', true).removeClass('active').addClass('disabled');
      }
    });

    // Hide select on mobile
    if (isTouchDevice) {
      $select.hide();
    }
  }

  /**
   * Enable drag and drop for desktop
   */
  function enableDragAndDrop() {
    $('.timegrow-project-tile').attr('draggable', true);

    $('.timegrow-project-tile').on('dragstart', function (e) {
      e.originalEvent.dataTransfer.setData('project-id', $(this).data('project-id'));
      e.originalEvent.dataTransfer.setData('name', $(this).data('project-name'));
      e.originalEvent.dataTransfer.setData('desc', $(this).data('project-desc'));
    });

    // Also allow click to select
    $('.timegrow-project-tile').on('click', function(e) {
      e.preventDefault();
      const projectId = $(this).data('project-id');
      if (projectId) {
        $('#clock-project-selector').val(projectId).trigger('change');
      }
    });
  }

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

      if (projectId) {
        // Update dropdown to match (this will trigger all the styling updates)
        $('#clock-project-selector').val(projectId).trigger('change');
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

