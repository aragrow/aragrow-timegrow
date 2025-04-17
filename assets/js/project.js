jQuery(document).ready(function($) {

    // Initialize the slider
    $('#estimate_hours_slider').slider({
        range: 'min',
        min: 0,
        max: 1000,
        step: 5,
        value: 5, // Default value
        slide: function(event, ui) {
            // Update the input field with the selected value
            $('#estimate_hours').val(ui.value);
        }
    });
    
    // Set the initial value in the input field
    $('#estimate_hours_slider').slider('option', 'value', $('#estimate_hours').val());
    $('#estimate_hours').val($('#estimate_hours_slider').slider('value'));

    $('#product_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const selectedLabel = selectedOption.text();
        const nameField = $('#name');

        if (nameField.val().trim() === '') {
            nameField.val(selectedLabel);
        }
    });

});
