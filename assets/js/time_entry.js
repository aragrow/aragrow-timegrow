
jQuery(document).ready(function($) {
    // Toggle entry type
    $('input[name="entry_type"]').change(function() {
        if ($(this).val() === 'clock') {
            $('.manual-entry').hide().find('input').prop('disabled', true);
            $('.clock-entry').show();
        } else {
            $('.manual-entry').show().find('input').prop('disabled', false);
            $('.clock-entry').hide();
        }
    });

    $('#timeflies-project-form').submit(function(e) {

        console.log('#timeflies-entry-form.submit');

        e.preventDefault();

        formData = $(this).serialize();

        console.log(formData);

        $.ajax({
            url: timeflies_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=save_time_entry',
            dataType: 'json',
            async: true,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = timeflies_time_entries_list.list_url;
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

    // Load projects when client changes
    $('#client_id').change(function() {
        var clientId = $(this).val();
        var memberId = $('#member_id').val();
        $('#project_id').empty().append('<option value="">Loading projects...</option>');
        const formData = $('#timeflies-time-entry-form').serialize();
        console.log(formData);
        $.ajax({
            url: timeflies_ajax.ajax_url,
            type: 'POST',
            async: true,
            data: formData + '&action=get_projects_by_client',
            success: function(response) {
                $('#project_id').empty();
                console.log(response);
                if (response.success) {
                    $.each(response.data, function(key, project) {
                        $('#project_id').append($('<option>', {
                            value: project.id,
                            text: project.name
                        }));
                    });
                } else {
                    $('#project_id').append($('<option>', {
                        value: '',
                        text: 'No projects found'
                    }));
                }
            },
            error: function(error) {
                console.error(error);
                alert('An error occurred.');
            }
        });
    });

    // Clock in/out functionality
    $('#clock-in-btn').click(function() {
        var time = new Date().toISOString().slice(0, 16);
        $('#start_time').val(time).prop('disabled', true);
        $('#clock-out-btn').prop('disabled', false);
        $(this).prop('disabled', true);
    });

    $('#clock-out-btn').click(function() {
        var time = new Date().toISOString().slice(0, 16);
        $('#end_time').val(time).prop('disabled', true);
        $(this).prop('disabled', true);
    });

});
