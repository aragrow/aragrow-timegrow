
jQuery(document).ready(function($) {
    
    const dropzone = $('#file-dropzone');
    const fileInput = $('#file_upload');
    const status = $('#file-upload-status');

    // Click to open file dialog
    dropzone.on('click', function() {
        fileInput.click();
    });

    // Prevent click propagation from fileInput to dropzone
    fileInput.on('click', function(e) {
        e.stopPropagation();
    });

    // Drag over styling
    dropzone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('border-color', '#007cba');
    });

    // Drag leave styling
    dropzone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('border-color', '#ccc');
    });

    // Handle dropped files
    dropzone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('border-color', '#ccc');

        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            fileInput.prop('files', files);
            status.text(`File selected: ${files[0].name}`);
        }
    });

    // File input change event
    fileInput.on('change', function() {
        if (this.files.length > 0) {
            status.text(`File selected: ${this.files[0].name}`);
        }
    });

    jQuery(document).ready(function($) {
        $('.delete-button').on('click', function(e) {
            if (!confirm("Are you sure you want to delete? This action cannot be undone.")) {
                e.preventDefault(); // Prevent navigation if the user cancels
            }
        });
    });

    // Handle filter button click
    $('#filter_expenses').on('click', function(e) {
        e.preventDefault();

        var filterSearch = $('#filter_search').val();
        var filterAssignedTo = $('#filter_assigned_to').val();
        var filterDateFrom = $('#filter_date_from').val();
        var filterDateTo = $('#filter_date_to').val();

        var url = new URL(window.location.href);
        url.searchParams.delete('s');
        url.searchParams.delete('filter_assigned_to');
        url.searchParams.delete('filter_date_from');
        url.searchParams.delete('filter_date_to');

        if (filterSearch) url.searchParams.set('s', filterSearch);
        if (filterAssignedTo) url.searchParams.set('filter_assigned_to', filterAssignedTo);
        if (filterDateFrom) url.searchParams.set('filter_date_from', filterDateFrom);
        if (filterDateTo) url.searchParams.set('filter_date_to', filterDateTo);

        window.location.href = url.toString();
    });

    // Allow Enter key to trigger filter
    $('#filter_search, #filter_assigned_to, #filter_date_from, #filter_date_to').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#filter_expenses').click();
        }
    });

});

