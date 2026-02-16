
// File: assets/js/manual.js
jQuery(document).ready(function($) {
  const $clockButton = $('#timegrow-submit');
  const $isBillable = $('#nexus-manual_billable');

  // Detect if device is mobile/touch
  const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

  // Make project tiles draggable (for desktop)
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

  // Mobile tap-to-select handler
  if (isTouchDevice) {
    $('.timegrow-project-tile').on('click touchend', function(e) {
      e.preventDefault();
      e.stopPropagation();

      const projectId = $(this).data('project-id');
      const projectName = $(this).data('project-name');
      const projectDesc = $(this).data('project-desc');

      if (projectId) {
        // Visual feedback
        $('.timegrow-project-tile').removeClass('selected').css('opacity', 0.5);
        $(this).addClass('selected').css('opacity', 1);

        // Update form
        $('#project_id').val(projectId);
        $('#drop-zone')
          .html(`✓ Project Selected: <strong>${projectName}</strong><br><small>${projectDesc}</small>`)
          .css('color', '#46b450')
          .css('background-color', '#e8f5e9')
          .css('border-color', '#46b450');

        updateButtonState();

        // Check if billable
        const objectId = $('#nexus-manual_billable');
        checkIsBillable(projectId, objectId);

        // Smooth scroll to form
        $('html, body').animate({
          scrollTop: $('#timegrow-nexus-entry-form').offset().top - 20
        }, 300);
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