
jQuery(document).ready(function($) {

    $('#timeflies-team-member-form').submit(function(e) {

        console.log('#timeflies-team-member-form.submit');

        e.preventDefault();

        formData = $(this).serialize();

        console.log(formData);

        $.ajax({
            url: timegrow.ajax_url,
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

    $availableList = $('#available-projects-list');
    $assignedList = $('#assigned-projects-list');

    // Make project items draggable
    $availableList.find('.project-item').draggable({
        connectToSortable: "#assigned-projects-list",
        helper: "clone",
        revert: "invalid",
        start: function (event, ui) {
            // Copy data-id to the clone helper
            ui.helper.attr('data-id', $(this).data('id'));
        }
    });

    // Make the assigned projects list sortable and droppable
    $assignedList.sortable({
        tolerance: "pointer",
        items: "> li",
        receive: function (event, ui) {
            droppedId = ui.item.attr('data-id');

            if (!droppedId) {
                alert("No data-id found on dropped item.");
                return;
            }

            // Prevent duplicates
            if ($assignedList.find(`.project-item[data-id="${droppedId}"]`).length > 1) {
                $(ui.sender).sortable('cancel'); // Undo the drop
                return;
            }

            // Update the class to reflect assignment
            // Delay class cleanup to after DOM drop finishes
            setTimeout(() => {
                ui.item
                    .removeClass("available-projects ui-draggable ui-draggable-handle")
                    .addClass("assigned-projects");
            }, 10);

            // Optional: Log or update the UI
            console.log(`Item with ID ${droppedId} assigned.`);

            // Remove the original from the available list
            $availableList.find(`.project-item[data-id="${droppedId}"]`).remove();
        }
    }).disableSelection();

    

    // Search filtering
    function setupSearch(inputSelector, listSelector) {
        $(inputSelector).on("keyup", function() {
            const query = $(this).val().toLowerCase();
            $(listSelector).children(".project-item").each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(query) !== -1);
            });
        });
    }

    setupSearch("#available-projects-search", "#available-projects-list");
    setupSearch("#assigned-projects-search", "#assigned-projects-list");

});

