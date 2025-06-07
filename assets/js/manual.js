// File: assets/js/manual.js
jQuery(document).ready(function($) {

  const $clockButton = $('#timegrow-submit');

  // Make client tiles draggable
  $('.timegrow-client-tile').attr('draggable', true);

  $('.timegrow-client-tile').on('dragstart', function (e) {
    e.originalEvent.dataTransfer.setData('client-id', $(this).data('client-id'));
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
      e.preventDefault();
      const clientId = e.originalEvent.dataTransfer.getData('client-id');
      if (clientId) {
        $('#manual-client-id').val(clientId);
        $(this).text(`Client selected: ${clientId}`).css('color', '#28a745');
        $('.timegrow-client-tile').css('opacity', 0.5);
        updateButtonState();
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
  }

});

