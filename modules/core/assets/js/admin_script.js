jQuery(document).ready(function($) {

    // Double-click functionality
    $('.project-item').on('dblclick', function() {
        var itemId = $(this).data('id');
        var item = $(this);
        var sourceList = $(this).parent();

        if (sourceList.attr('id') === 'available-projects-list') {
            if (confirm('Are you sure you want to assign this project?') !== null) {
                targetList = $('#assigned-projects-list');
                item.addClass('assigned');
            } else return;
        } else {
            if (confirm('Are you sure you want to unassign this project?') !== null) {
                targetList = $('#available-projects-list');
                item.removeClass('assigned');
            } else return;
        }

        item.appendTo(targetList); // Directly append item
        sourceList.find('[data-id="' + item.data('id') + '"]').remove();

        assignedProjectIds = [];
        $('#assigned-projects-list li').each(function() {
            assignedProjectIds.push($(this).data('id'));
        });
        $('#project_ids_hidden').val(assignedProjectIds.join(','));
    });

    // Search functionality
    $('#available-projects-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('#available-projects-list li').each(function() {
            var projectName = $(this).text().toLowerCase();
            if (projectName.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#assigned-projects-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('#assigned-projects-list li').each(function() {
            var projectName = $(this).text().toLowerCase();
            if (projectName.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

})