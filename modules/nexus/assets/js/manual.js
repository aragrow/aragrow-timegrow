
// File: assets/js/manual.js
jQuery(document).ready(function($) {
  const $clockButton = $('#timegrow-submit');
  const $isBillable = $('#nexus-manual_billable');

  // Detect if device is mobile/touch
  const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

  // Create mobile dropdown if on touch device
  if (isTouchDevice) {
    createMobileProjectDropdown();
  } else {
    // Desktop: Make project tiles draggable
    $('.timegrow-project-tile').attr('draggable', true);

    // Desktop drag handlers
    $('.timegrow-project-tile').on('dragstart', function (e) {
      e.originalEvent.dataTransfer.setData('project-id', $(this).data('project-id'));
      e.originalEvent.dataTransfer.setData('name', $(this).data('project-name'));
      e.originalEvent.dataTransfer.setData('desc', $(this).data('project-desc'));
      $(this).addClass('dragging');
    });

    $('.timegrow-project-tile').on('dragend', function (e) {
      $(this).removeClass('dragging');
    });
  }

  /**
   * Create mobile-friendly dropdown for project selection
   */
  function createMobileProjectDropdown() {
    // Collect all projects from tiles
    const projects = [];
    $('.timegrow-project-tile').each(function() {
      projects.push({
        id: $(this).data('project-id'),
        name: $(this).data('project-name'),
        desc: $(this).data('project-desc')
      });
    });

    // Sort projects alphabetically by name
    projects.sort((a, b) => a.name.localeCompare(b.name));

    // Create dropdown wrapper
    const $wrapper = $('<div id="mobile-project-selector-wrapper"></div>');
    const $label = $('<label for="mobile-project-selector">Select Project</label>');
    const $select = $('<select id="mobile-project-selector"></select>');

    // Add default option
    $select.append('<option value="">-- Choose a Project --</option>');

    // Add project options
    projects.forEach(function(project) {
      $select.append(`<option value="${project.id}">${project.name}</option>`);
    });

    // Assemble and insert before form
    $wrapper.append($label).append($select);
    $('#timegrow-nexus-entry-form').prepend($wrapper);

    // Handle selection
    $select.on('change', function() {
      const projectId = $(this).val();

      if (projectId) {
        const selectedProject = projects.find(p => p.id == projectId);

        // Update hidden field
        $('#project_id').val(projectId);

        // Enable submit button
        updateButtonState();

        // Check if billable
        const objectId = $('#nexus-manual_billable');
        checkIsBillable(projectId, objectId);
      } else {
        // Reset if no selection
        $('#project_id').val('');
        $clockButton.removeClass('active').addClass('disabled');
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
      const projectDesc = e.originalEvent.dataTransfer.getData('desc');
      console.log(projectId);
      if (projectId) {
        $('#project_id').val(projectId);
        $(this).html(`Project selected: ${projectName} (${projectId}) <br /> ${projectDesc}`).css('color', '#28a745');


        $('.timegrow-project-tile').css('opacity', .5);
        updateButtonState();
        console.log('Checking if project is billable...');
        objectId = $('#nexus-manual_billable'); // Get object by id (escape dot)
        checkIsBillable(projectId, objectId);
      }
      $(this).removeClass('dragging-over');
    });

  $('#timegrow-nexus-entry-form').on('submit', function (e) {
    e.preventDefault();
    const data = $(this).serialize();
    console.log('Submitting:', data);

      $.ajax({
        url: timegrow_ajax.ajax_url,
        method: 'POST',
        data: data,
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
      // Submit via AJAX or handle as needed
    });

  function updateButtonState() {
      $clockButton.removeClass('disabled').addClass('active');
      $clockButton.css('background-color', '#000000');
  }

});