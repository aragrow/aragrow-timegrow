jQuery(document).ready(function($) {
    // Update clocks every second
    // Update the local time display to use stored timezone
    function updateClocks() {
        const now = new Date();
        
       // GMT Time
        const gmtOptions = {
            hour12: true,
            timeZone: 'UTC'
        };
        const gmtTime = now.toLocaleTimeString('en-US', gmtOptions);
        const gmtDate = now.toLocaleDateString('en-US', {
            weekday: 'short',
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            timeZone: 'UTC'
        });
        $('#gmt-clock').html(`<div>${gmtDate} - ${gmtTime}</div>`);

        // Local Time with stored timezone
        const options = {
            hour12: true,
            timeZone: timeflies_time_entry_list.user_timezone // Get from localized variable
        };
        
        try {
            const localTime = now.toLocaleTimeString('en-US', options);
            const localDate = now.toLocaleDateString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'numeric',
                day: 'numeric',
                timeZone: timeflies_time_entry_list.user_timezone
            });
            
            $('#local-clock').html(`<div>${localDate} - ${localTime}</div>`);
        } catch (e) {
            console.error('Invalid timezone:', timeflies_time_entry_list.user_timezone);
            $('#local-clock').text('Error: Invalid timezone setting');
        }

        setTimeout(updateClocks, 1000);
    }
    updateClocks();

    // Handle clock in/out
    $('.clock-btn').click(function() {

        console.log(0);
        const button = $(this);
        $('#entry_type').val(button.attr('id'));
        // 1. Decode the URL-encoded string
        const encodedDate = $('#gmt-clock').text();
        const decodedDate = decodeURIComponent(encodedDate);
        // 2. Parse the date string
        const dateParts = decodedDate.match(/(\d+)\/(\d+)\/(\d+).*?(\d+):(\d+):(\d+)/);
        const [_, month, day, year, hours, minutes, seconds] = dateParts;
        // 3. Create Date object (months are 0-based in JavaScript)
        const date = new Date(year, month - 1, day, hours, minutes, seconds);
        // 4. Format for database (ISO 8601 format)
        const dbFormattedDate = date.toISOString().slice(0, 19).replace('T', ' ');

        $('#gmt_clock_field').val(dbFormattedDate);
        const timezone = timeflies_clock.timezone;
        formData = $('#timeflies-clock-in-out').serialize();
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