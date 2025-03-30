
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
});

