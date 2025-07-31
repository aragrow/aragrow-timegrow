jQuery(document).ready(function($) {
    
    /**
     * Check if project is billable
     */
    function checkIsBillable(projectId, objectId) {
        $.ajax({
            url: timegrow_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            cache: false,
            headers: {
                'X-WP-Nonce': timegrow_ajax.nonce // Use the nonce for security
            },
            async: false,
            data: {
                action: 'check_is_billable',
                project_id: projectId,
                nonce: timegrow_ajax.nonce
            },
            beforeSend: function() {
                // Show loading indicator
                console.log('Checking billable status...');
            },
            success: function(response) {
                if (response.success) {
                    console.log('Success:', response.data);
                    // Handle success response
                    if (response.data.is_billable ) {
                        console.log('Project is billable, enabling objectId');
                        objectId.prop('checked', true);
                        objectId.prop('disabled', false);
                    } else {
                        alert('Project is not billable.');
                        objectId.prop('checked', false);
                        objectId.prop('disabled', true);
                    }
                } else {
                    console.log('Error:', response.data);
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', error);
                alert('AJAX request failed: ' + error);
            }
        });
    }
    
    // Example: Trigger AJAX call on button click
    $(document).on('click', '.check-billable-btn', function(e) {
        e.preventDefault();
        var projectId = $(this).data('project-id');  
        checkIsBillable(projectId);
    });
    
    // Make function globally available
    window.checkIsBillable = checkIsBillable;
});