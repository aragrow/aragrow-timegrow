
// File: assets/js/manual.js
jQuery(document).ready(function($) {
  const $clockButton = $('#timegrow-submit');
  const $isBillable = $('#nexus-manual_billable');

  // Detect if device is mobile/touch
  const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

  // Always create dropdown for better UX
  createProjectDropdown();

  // Also enable drag and drop on desktop
  if (!isTouchDevice) {
    enableDragAndDrop();
  }

  /**
   * Create dropdown for project selection (works on all devices)
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

    // Sort projects alphabetically by name
    projects.sort((a, b) => a.name.localeCompare(b.name));

    // Create dropdown wrapper with search
    const $wrapper = $('<div id="mobile-project-selector-wrapper"></div>');
    const $label = $('<label for="mobile-project-selector">Select Project</label>');

    // Add search input
    const $searchInput = $('<input type="text" id="project-search" placeholder="Search projects..." />');

    // Create datalist for native autocomplete
    const $datalist = $('<datalist id="projects-datalist"></datalist>');
    const $select = $('<select id="mobile-project-selector"></select>');

    // Add default option
    $select.append('<option value="">-- Choose a Project --</option>');

    // Add project options to both select and datalist
    projects.forEach(function(project) {
      $select.append(`<option value="${project.id}">${project.name}</option>`);
      $datalist.append(`<option value="${project.name}" data-id="${project.id}">${project.name}</option>`);
    });

    // Link search to datalist
    $searchInput.attr('list', 'projects-datalist');

    // Assemble and insert before form (hide the select dropdown, keep for data management)
    $wrapper.append($label).append($searchInput).append($datalist).append($select.hide());
    $('#timegrow-nexus-entry-form').prepend($wrapper);

    // Handle search input selection
    $searchInput.on('input change', function() {
      const searchValue = $(this).val();
      const selectedProject = projects.find(p => p.name === searchValue);

      if (selectedProject) {
        $select.val(selectedProject.id).trigger('change');
        $(this).blur(); // Hide keyboard on mobile
      }
    });

    // Handle direct select change
    $select.on('change', function() {
      const projectId = $(this).val();
      if (projectId) {
        const selectedProject = projects.find(p => p.id == projectId);
        $searchInput.val(selectedProject.name);
      } else {
        $searchInput.val('');
      }
    });

    // Handle selection
    $select.on('change', function() {
      const projectId = $(this).val();

      if (projectId) {
        const selectedProject = projects.find(p => p.id == projectId);

        // Update hidden field
        $('#project_id').val(projectId);

        // Update drop zone to show selection
        $('#drop-zone')
          .html(`✓ Project Selected: <strong>${selectedProject.name}</strong>`)
          .css('color', '#46b450')
          .css('background-color', '#e8f5e9')
          .css('border-color', '#46b450');

        // Enable submit button
        updateButtonState();

        // Check if billable
        const objectId = $('#nexus-manual_billable');
        checkIsBillable(projectId, objectId);
      } else {
        // Reset if no selection
        $('#project_id').val('');
        $('#drop-zone')
          .html('No Project Selected')
          .css('color', '')
          .css('background-color', '')
          .css('border-color', '');
        $clockButton.removeClass('active').addClass('disabled');
      }
    });
  }

  /**
   * Enable drag and drop for desktop users
   */
  function enableDragAndDrop() {
    // Make project tiles draggable
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

    // Also allow click to select on desktop
    $('.timegrow-project-tile').on('click', function(e) {
      e.preventDefault();

      const projectId = $(this).data('project-id');
      const projectName = $(this).data('project-name');

      if (projectId) {
        // Update dropdown to match selection
        $('#mobile-project-selector').val(projectId).trigger('change');
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