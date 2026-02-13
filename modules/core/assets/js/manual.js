// File: assets/js/manual.js
jQuery(document).ready(function($) {
  console.log
  const $clockButton = $('#timegrow-submit');
  const $isBillable = $('#timegrow-submit');

  // Make project tiles draggable
  $('.timegrow-project-tile').attr('draggable', true);

  $('.timegrow-project-tile').on('dragstart', function (e) {
    e.originalEvent.dataTransfer.setData('project-id', $(this).data('project-id'));
    e.originalEvent.dataTransfer.setData('name', $(this).data('project-name'));
    e.originalEvent.dataTransfer.setData('desc', $(this).data('project-desc'));
  });

  $('#manual-drop-zone')
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
        $('#manual-project-id').val(projectId);
        $(this).html(`Project selected: ${projectName} (${projectId}) <br /> ${projectDesc}`).css('color', '#28a745');


        $('.timegrow-project-tile').css('opacity', .5);
        updateButtonState();
        console.log('Checking if project is billable...');
        const isBillable = checkIsBillable(projectId);
        if (isBillable) {
          $isBillable.addClass('check');
          $isBillable.prop('checked', true);
          $isBillable.prop('disabled', false);
        } else {
          $isBillable.removeClass('check');
          $isBillable.prop('checked', false);
          $isBillable.prop('disabled', true);
        }
      }
      $(this).removeClass('dragging-over');
    });

  $('#manual-entry-form').on('submit', function (e) {
    e.preventDefault();
    const data = $(this).serialize();
    console.log('Submitting:', data);

    // Submit via AJAX or handle as needed
  });


  function updateButtonState() {
      $clockButton.removeClass('disabled').addClass('active');
      $clockButton.css('background-color', '#000000');
  }

});

