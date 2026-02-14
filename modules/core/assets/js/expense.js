
// DEBUG: Check if file is loaded
console.log('=== EXPENSE.JS FILE LOADED ===');

jQuery(document).ready(function($) {

    console.log('TimeGrow Expense JS loaded');
    console.log('jQuery version:', $.fn.jquery);
    console.log('timegrow_ajax available:', typeof timegrow_ajax !== 'undefined');
    if (typeof timegrow_ajax !== 'undefined') {
        console.log('AJAX URL:', timegrow_ajax.ajax_url);
        console.log('Nonce:', timegrow_ajax.nonce ? 'Present' : 'Missing');
    }

    const dropzone = $('#file-dropzone');
    const fileInput = $('#file_upload');
    const status = $('#file-upload-status');

    console.log('Dropzone found:', dropzone.length);
    console.log('File input found:', fileInput.length);
    console.log('Status element found:', status.length);

    // Click to open file dialog
    dropzone.on('click', function() {
        console.log('Dropzone clicked!');
        fileInput.click();
        console.log('File input click triggered');
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
            // Trigger change event to analyze with AI
            fileInput.trigger('change');
        }
    });

    // File input change event - trigger real-time AI analysis
    fileInput.on('change', function() {
        if (this.files.length > 0) {
            const fileName = this.files[0].name;
            status.text(`File selected: ${fileName}`);
            status.css('color', '#007cba');

            console.log('File selected:', fileName);

            // Check if AI is enabled
            var aiEnabled = $('#ai_analysis_enabled').length > 0 && $('#ai_analysis_enabled').val() === '1';
            console.log('AI analysis enabled:', aiEnabled);
            console.log('AI flag element:', $('#ai_analysis_enabled').length);
            console.log('AI flag value:', $('#ai_analysis_enabled').val());

            if (aiEnabled) {
                console.log('AI is enabled - starting analysis');
                // Show AI analysis approval checkbox
                $('#ai-analysis-approval').slideDown(300);
                $('#approve_ai_analysis').prop('checked', true);

                // Trigger real-time AI analysis
                analyzeReceiptRealtime(this.files[0]);
            } else {
                console.log('AI is NOT enabled - showing notification');
                // Show notification that AI is not configured
                const noticeHtml = `
                    <div id="ai-not-configured" class="notice notice-warning" style="margin-top: 15px; padding: 12px;">
                        <p style="margin: 0;">
                            <strong>ℹ️ AI Analysis Not Configured</strong><br>
                            To enable automatic receipt analysis, please configure your AI provider in
                            <a href="admin.php?page=timegrow-settings&tab=ai">Settings → AI Provider</a>.
                        </p>
                    </div>
                `;
                $('#ai-not-configured').remove();
                $('#file-upload-status').after(noticeHtml);
            }
        } else {
            // Hide AI analysis approval if no file selected
            $('#ai-analysis-approval').slideUp(300);
            $('#approve_ai_analysis').prop('checked', false);
            $('#ai-not-configured').remove();
        }
    });

    /**
     * Analyze receipt in real-time using AJAX
     */
    function analyzeReceiptRealtime(file) {
        // Check if AJAX data is available
        if (typeof timegrow_ajax === 'undefined') {
            console.error('timegrow_ajax is not defined. AJAX localization failed.');
            showAIAnalysisError('AJAX configuration error. Please refresh the page.');
            return;
        }

        console.log('Starting real-time receipt analysis...', file.name);

        // Show loading indicator
        showAIAnalysisInProgress();

        // Prepare form data
        var formData = new FormData();
        formData.append('action', 'analyze_receipt_realtime');
        formData.append('nonce', timegrow_ajax.nonce);
        formData.append('receipt_file', file);

        console.log('Sending AJAX request to:', timegrow_ajax.ajax_url);

        // Make AJAX request
        $.ajax({
            url: timegrow_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('AJAX response received:', response);

                if (response.success) {
                    console.log('Analysis successful:', response.data);
                    // Auto-populate form fields
                    populateFormFields(response.data);

                    // Show success message
                    showAIAnalysisSuccess(response.data);
                } else {
                    console.error('Analysis failed:', response.data);
                    // Show error message
                    showAIAnalysisError(response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', {
                    status: jqXHR.status,
                    statusText: textStatus,
                    error: errorThrown,
                    response: jqXHR.responseText
                });
                showAIAnalysisError('Network error: ' + errorThrown);
            }
        });
    }

    /**
     * Show AI analysis in progress
     */
    function showAIAnalysisInProgress() {
        // Remove any existing progress indicators
        $('#ai-analysis-loading').remove();
        $('#ai-progress-bar').remove();

        // Add progress bar below the dropzone
        const progressHtml = `
            <div id="ai-progress-bar">
                <div class="progress-header">
                    <span class="spinner is-active"></span>
                    <span class="progress-title">🤖 Analyzing receipt with AI...</span>
                </div>
                <div class="progress-bar-container">
                    <div id="ai-progress-fill"></div>
                </div>
                <div id="ai-progress-text">Uploading receipt...</div>
            </div>
        `;

        // Insert after file dropzone or status
        if ($('#file-dropzone').length) {
            $('#file-dropzone').after(progressHtml);
        } else if ($('#file-upload-status').length) {
            $('#file-upload-status').after(progressHtml);
        }

        // Animate progress bar
        animateProgress();
    }

    /**
     * Animate progress bar during AI analysis
     */
    function animateProgress() {
        const steps = [
            { progress: 20, text: 'Uploading receipt...', delay: 300 },
            { progress: 40, text: 'Processing image...', delay: 800 },
            { progress: 60, text: 'Analyzing with AI...', delay: 1500 },
            { progress: 80, text: 'Extracting data...', delay: 2200 },
            { progress: 95, text: 'Almost done...', delay: 3000 }
        ];

        steps.forEach(step => {
            setTimeout(() => {
                $('#ai-progress-fill').css('width', step.progress + '%');
                $('#ai-progress-text').text(step.text);
            }, step.delay);
        });
    }

    /**
     * Show AI analysis success message
     */
    function showAIAnalysisSuccess(data) {
        // Complete the progress bar
        $('#ai-progress-fill').css('width', '100%');
        $('#ai-progress-text').html('<span style="color: #00a32a; font-weight: 600;">✓ Analysis complete!</span>');

        // Remove progress bar after animation
        setTimeout(function() {
            $('#ai-progress-bar').fadeOut(400, function() {
                $(this).remove();
            });
        }, 1000);

        // Remove any old results
        $('#ai-analysis-loading').remove();
        $('#ai-analysis-result').remove();

        const successHtml = `
            <div id="ai-analysis-result" class="notice notice-success is-dismissible" style="margin: 20px 0; padding: 15px;">
                <p style="margin: 0; font-weight: 500;">✅ ${data.message}</p>
                ${data.low_confidence ? '<p style="margin: 5px 0 0 0; color: #856404;">⚠️ ' + data.message + ' Please review the extracted data carefully.</p>' : ''}
            </div>
        `;
        $('.timegrow-modern-header').after(successHtml);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('#ai-analysis-result').fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }

    /**
     * Show AI analysis error message
     */
    function showAIAnalysisError(errorMessage) {
        // Show error in progress bar
        $('#ai-progress-fill').css({
            'width': '100%',
            'background': 'linear-gradient(90deg, #dc3232, #f86368)'
        });
        $('#ai-progress-text').html('<span style="color: #dc3232; font-weight: 600;">✗ Analysis failed</span>');

        // Remove progress bar after showing error
        setTimeout(function() {
            $('#ai-progress-bar').fadeOut(400, function() {
                $(this).remove();
            });
        }, 1500);

        $('#ai-analysis-loading').remove();
        $('#ai-analysis-result').remove();

        const errorHtml = `
            <div id="ai-analysis-result" class="notice notice-error is-dismissible" style="margin: 20px 0; padding: 15px;">
                <p style="margin: 0;">❌ AI Analysis Failed: ${errorMessage}</p>
            </div>
        `;
        $('.timegrow-modern-header').after(errorHtml);
    }

    /**
     * Auto-populate form fields with AI analysis results
     */
    function populateFormFields(data) {
        // Only populate empty fields

        // Amount
        if (data.amount > 0 && !$('#amount').val()) {
            $('#amount').val(data.amount).addClass('ai-populated');
        }

        // Date
        if (data.expense_date && !$('#expense_date').val()) {
            $('#expense_date').val(data.expense_date).addClass('ai-populated');
        }

        // Vendor/Name
        if (data.expense_name && !$('#expense_name').val()) {
            $('#expense_name').val(data.expense_name).addClass('ai-populated');
        }

        // Description
        if (data.expense_description && !$('#expense_description').val()) {
            $('#expense_description').val(data.expense_description).addClass('ai-populated');
        }

        // Category - now using category_id directly from backend
        if (data.category_id && !$('#expense_category_id').val()) {
            $('#expense_category_id').val(data.category_id).addClass('ai-populated');
            console.log('AI populated category ID:', data.category_id);
        }

        // Assignment (Client/Project)
        if (data.assigned_to && data.assigned_to !== 'general' && $('#assigned_to').val() === 'general') {
            $('#assigned_to').val(data.assigned_to).trigger('change').addClass('ai-populated');

            // Set assigned_to_id if available
            if (data.assigned_to_id > 0) {
                $('#assigned_to_id').val(data.assigned_to_id).addClass('ai-populated');
            }
        }

        // Add visual indicator for AI-populated fields
        $('.ai-populated').css({
            'background-color': '#e7f5ff',
            'border-left': '3px solid #2271b1'
        });
    }


    // Delete button confirmation
    $('.delete-button').on('click', function(e) {
        if (!confirm("Are you sure you want to delete? This action cannot be undone.")) {
            e.preventDefault(); // Prevent navigation if the user cancels
        }
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

