
// File: assets/js/manual.js
jQuery(document).ready(function($) {
  const $clockButton = $('#timegrow-submit');
  const $isBillable = $('#nexus-manual_billable');

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