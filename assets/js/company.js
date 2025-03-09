jQuery(document).ready(function($) {

    $('#timeflies-company-form').submit(function(e) {
        e.preventDefault();

        var data = $(this).serialize();
        data.action = 'save_company';
      
        $.ajax({
            url: timeflies_ajax.ajax_url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = timeflies_company_list.list_url;
                } else {
                    alert('Error saving company.');
                }
            },
            error: function(error) {
                console.error(error);
                alert('An error occurred.');
            }
        });
    });


});