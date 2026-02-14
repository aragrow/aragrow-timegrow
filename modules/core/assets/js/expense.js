
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
            // Trigger change event to show AI approval
            fileInput.trigger('change');
        }
    });

    // File input change event
    fileInput.on('change', function() {
        if (this.files.length > 0) {
            status.text(`File selected: ${this.files[0].name}`);

            // Show AI analysis approval if AI is configured
            var aiEnabled = $('#ai_analysis_enabled').length > 0 && $('#ai_analysis_enabled').val() === '1';
            if (aiEnabled) {
                $('#ai-analysis-approval').slideDown(300);
                // Auto-check the box for convenience (user can uncheck if they don't want AI)
                $('#approve_ai_analysis').prop('checked', true);
            }
        } else {
            // Hide AI analysis approval if no file selected
            $('#ai-analysis-approval').slideUp(300);
            $('#approve_ai_analysis').prop('checked', false);
        }
    });

    // Show AI analysis loading indicator
    function showAIAnalysisLoading() {
        // Remove any existing loading notices
        $('#ai-analysis-loading').remove();

        const loadingHtml = `
            <div id="ai-analysis-loading" class="notice notice-info" style="margin: 20px 0; padding: 15px; display: flex; align-items: center;">
                <span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
                <span style="font-weight: 500;">Analyzing receipt with AI... This may take a few seconds.</span>
            </div>
        `;
        $('.wrap h1').first().after(loadingHtml);
    }

    // Show AI analysis loading on form submit if file is attached AND user approved
    $('form[name="expense_form"]').on('submit', function(e) {
        if (fileInput[0] && fileInput[0].files && fileInput[0].files.length > 0) {
            // Check if AI analysis is approved by user
            var aiApproved = $('#approve_ai_analysis').is(':checked');
            if (aiApproved) {
                showAIAnalysisLoading();
            }
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

