
jQuery(document).ready(function($) {

    $('#timeflies-client-form').submit(function(e) {

        console.log('#timeflies-add-client-form.submit');

        e.preventDefault();

        formData = $(this).serialize();

        console.log(formData);

        $.ajax({
            url: timeflies_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=save_client',
            dataType: 'json',
            async: true,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = timeflies_clients_list.list_url;
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

    $('.delete-client').click(function(e) {
        e.preventDefault();
        var clientId = $(this).data('id');

        if (confirm('Are you sure you want to delete this client?')) {
            $.ajax({
                url: timeflies_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_client',
                    client_id: clientId,
                    timeflies_client_nonce_field: timeflies_client.nonce
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

