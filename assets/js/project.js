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


    $('#timeflies-project-form').submit(function(e) {

        console.log('#timeflies-project-form.submit');

        e.preventDefault();

        formData = $(this).serialize();

        console.log(formData);

        $.ajax({
            url: timeflies_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=save_project',
            dataType: 'json',
            async: true,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = timeflies_projects_list.list_url;
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(error) {
                console.error(error);
                alert('An error occurred.');
            }
        });
    });

    $('.delete-project').click(function(e) {
        e.preventDefault();
        var projectId = $(this).data('id');

        if (confirm('Are you sure you want to delete this project?')) {
            $.ajax({
                url: timeflies_project.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_project',
                    project_id: projectId,
                    timeflies_project_nonce_field: timeflies_project.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function(error) {
                    console.error(error);
                    alert('An error occurred.');
                }
            });
        }
    });

});
