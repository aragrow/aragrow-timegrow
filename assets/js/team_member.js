
jQuery(document).ready(function($) {

    $('#timeflies-team-member-form').submit(function(e) {

        console.log('#timeflies-team-member-form.submit');

        e.preventDefault();

        formData = $(this).serialize();

        console.log(formData);

        $.ajax({
            url: timeflies_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=save_team_member',
            dataType: 'json',
            async: true,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = timeflies_team_member_list.list_url;
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

    $('.delete-team-member').click(function(e) {
        e.preventDefault();
        var teamMemberId = $(this).data('id');

        if (confirm('Are you sure you want to delete this team member?')) {
            $.ajax({
                url: timeflies_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_team_member',
                    team_member_id: teamMemberId,
                    timeflies_team_member_nonce_field: timeflies_team_member.nonce
                },
                dataType: 'json',
                async: true,
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

