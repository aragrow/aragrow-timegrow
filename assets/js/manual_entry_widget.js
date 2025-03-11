jQuery(document).ready(function($) {
    // Handle clock in/out
    $('#save-btn').click(function() {

        console.log(0);

        formData = $('#timeflies-manual-entry').serialize();
        //    console.log(formData);

        $.ajax({
            url: timeflies_ajax.ajax_url,
            type: 'POST',
            data: formData,
            async: true,
            success: function(response) {
                console.log(timeflies_ajax.ajaxurl);
                console.log(response);
                location.reload(); // Reload the current page
            },
            error: function() {
                console.log(3);
            },
        });
    });
});