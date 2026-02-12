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

    // Handle filter button click
    $('#filter_projects').on('click', function(e) {
        e.preventDefault();

        var filterSearch = $('#filter_search').val();
        var filterClient = $('#filter_client').val();
        var filterStatus = $('#filter_status').val();
        var filterBillable = $('#filter_billable').val();

        var url = new URL(window.location.href);
        url.searchParams.delete('s');
        url.searchParams.delete('filter_client');
        url.searchParams.delete('filter_status');
        url.searchParams.delete('filter_billable');

        if (filterSearch) url.searchParams.set('s', filterSearch);
        if (filterClient) url.searchParams.set('filter_client', filterClient);
        if (filterStatus) url.searchParams.set('filter_status', filterStatus);
        if (filterBillable) url.searchParams.set('filter_billable', filterBillable);

        window.location.href = url.toString();
    });

    // Allow Enter key to trigger filter
    $('#filter_search, #filter_client, #filter_status, #filter_billable').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#filter_projects').click();
        }
    });

});
