
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

    // Make available projects draggable
    // .draggable({ ... }) Activates drag functionality using jQuery UI.
    // connectToSortable: "#assigned-projects-list" - Says: "Hey, these items can be dragged into this other sortable list."
    // helper: "clone" - Instead of moving the original element while dragging, it creates a visual copy (clone). This keeps the original item in place until it's actually dropped.
    // revert: "invalid"  -If the item is not dropped in the valid connected sortable area, it will snap back to its original location.
    $('#available-projects-list .project-item').draggable({
        connectToSortable: "#assigned-projects-list", 
        helper: "clone",
        revert: "invalid",
        start: function(event, ui) {
            // Ensure the data-id is copied to the helper
            ui.helper.attr('data-id', $(this).data('id'));
        }
    });

    // Make assigned projects sortable and droppable
    // #assigned-projects-list: The sortable list that accepts dropped items (from draggable).
    // .sortable({ receive: ... }): Makes this list accept elements from outside and lets you hook into the drop event.
    // receive: This function runs when a draggable item is dropped into the sortable list.
    // ui.item: Refers to the element that was just dropped.
    // What the receive function is doing:
    //  Removes unwanted styles and classes:
    //        .available-projects → the class that shouldn't exist once assigned.
    //        ui-draggable & ui-draggable-handle → jQuery UI classes, often unnecessary after drop.
    // Adds the .assigned class so that it visually/semantically fits the new list.
    // .appendTo(this): Ensures the dropped element is properly inserted into the sortable list (might not be necessary depending on behavior).

    $('#assigned-projects-list').sortable({
        tolerance: "pointer",
        items: "> li",
        receive: function(event, ui) {
            
            // Only allow once by checking if it already exists
            droppedId = ui.item.attr('data-id');
            if (!droppedId) {
                alert("No data-id found on dropped item");
                return;
            }

            // Prevent duplicates by checking if the item already exists
            if ($('#assigned-projects-list .project-item[data-id="' + droppedId + '"]').length > 1) {
                $(ui.sender).sortable('cancel'); // undo the drop
                return;
            }

            ui.item
                .removeClass("available-projects ui-draggable ui-draggable-handle")
                .addClass("assigned-projects");

            alert(ui.item.attr('class'));

            // Optionally: Remove from the available list completely
            $('#available-projects-list .project-item[data-id="' + droppedId + '"]').remove();
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

