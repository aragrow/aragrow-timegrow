
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

    $('#timeflies-time-entry-form').submit(function(e) {

        console.log('#timeflies-time-entry-form.submit');

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

    // Handle filter button click
    $('#filter_time_entries').on('click', function(e) {
        e.preventDefault();

        var filterSearch = $('#filter_search').val();
        var filterProject = $('#filter_project').val();
        var filterMember = $('#filter_member').val();
        var filterBillable = $('#filter_billable').val();
        var filterBilled = $('#filter_billed').val();
        var filterEntryType = $('#filter_entry_type').val();

        var url = new URL(window.location.href);
        url.searchParams.delete('s');
        url.searchParams.delete('filter_project');
        url.searchParams.delete('filter_member');
        url.searchParams.delete('filter_billable');
        url.searchParams.delete('filter_billed');
        url.searchParams.delete('filter_entry_type');

        if (filterSearch) url.searchParams.set('s', filterSearch);
        if (filterProject) url.searchParams.set('filter_project', filterProject);
        if (filterMember) url.searchParams.set('filter_member', filterMember);
        if (filterBillable) url.searchParams.set('filter_billable', filterBillable);
        if (filterBilled) url.searchParams.set('filter_billed', filterBilled);
        if (filterEntryType) url.searchParams.set('filter_entry_type', filterEntryType);

        window.location.href = url.toString();
    });

    // Allow Enter key to trigger filter
    $('#filter_search, #filter_project, #filter_member, #filter_billable, #filter_billed, #filter_entry_type').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#filter_time_entries').click();
        }
    });

});
