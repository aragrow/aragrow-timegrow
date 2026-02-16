// File: assets/js/expense-recorder.js
jQuery(document).ready(function($) {
    console.log('Expense Recorder JS Initializing...');

    // Detect if device is mobile/touch
    const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

    // Create searchable dropdown
    createProjectDropdown();

    // --- DOM Elements ---
    const $projectTiles = $('.timegrow-project-tile');
    const $expenseProjectDropDisplay = $('#expense-drop-zone-display'); // Updated ID
    const $selectedExpenseProjectIdInput = $('#selected-expense-project-id'); // Hidden input
    const $projectDropPlaceholder = $expenseProjectDropDisplay.find('.project-drop-placeholder');
    const $selectedProjectDetailsDiv = $expenseProjectDropDisplay.find('.selected-project-details');
    const $clearDroppedProjectBtn = $('#clear-dropped-project-btn');

    // ... (other DOM elements like receipt drop zone, form, etc.)

    // --- State Variables ---
    let selectedProjectIdForExpense = null; // To store the ID of the dropped project for the expense

    /**
     * Create searchable dropdown for project selection
     */
    function createProjectDropdown() {
        // Collect all projects from tiles
        const projects = [];
        $('.timegrow-project-tile').each(function() {
            projects.push({
                id: $(this).data('project-id'),
                name: $(this).data('project-name'),
                desc: $(this).data('project-desc')
            });
        });

        // Only create if there are projects
        if (projects.length === 0) {
            return;
        }

        // Sort alphabetically
        projects.sort((a, b) => a.name.localeCompare(b.name));

        // Create dropdown wrapper
        const $wrapper = $('<div id="expense-project-selector-wrapper" style="margin-bottom: 20px;"></div>');
        const $label = $('<label for="expense-project-search" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 15px;">Select Project (Optional)</label>');
        const $searchInput = $('<input type="text" id="expense-project-search" placeholder="Search projects..." style="width: 100%; min-height: 50px; padding: 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 8px; margin-bottom: 10px; box-sizing: border-box;" />');
        const $datalist = $('<datalist id="expense-projects-datalist"></datalist>');
        const $select = $('<select id="expense-project-selector" style="width: 100%; min-height: 50px; padding: 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 8px; box-sizing: border-box;"></select>');

        // Add options
        $select.append('<option value="">-- Choose a Project (Optional) --</option>');
        projects.forEach(function(project) {
            $select.append(`<option value="${project.id}">${project.name}</option>`);
            $datalist.append(`<option value="${project.name}" data-id="${project.id}">${project.name}</option>`);
        });

        $searchInput.attr('list', 'expense-projects-datalist');

        // Assemble (hide the select dropdown, keep for data management)
        $wrapper.append($label).append($searchInput).append($datalist).append($select.hide());

        // Insert before expense project drop section
        if ($('#expense-project-drop-section').length > 0) {
            $('#expense-project-drop-section').before($wrapper);
        } else {
            $('#expense-form').prepend($wrapper);
        }

        // Handle search input
        $searchInput.on('input change', function() {
            const searchValue = $(this).val();
            const selectedProject = projects.find(p => p.name === searchValue);
            if (selectedProject) {
                $select.val(selectedProject.id).trigger('change');
                $(this).blur();
            }
        });

        // Handle selection - sync with dropdown
        $select.on('change', function() {
            const projectId = $(this).val();
            if (projectId) {
                const selectedProject = projects.find(p => p.id == projectId);
                $searchInput.val(selectedProject.name);
            } else {
                $searchInput.val('');
            }
        });

        // Handle selection - update form state
        $select.on('change', function() {
            const projectId = $(this).val();

            if (projectId) {
                const selectedProject = projects.find(p => p.id == projectId);

                selectedProjectIdForExpense = projectId;
                $selectedExpenseProjectIdInput.val(projectId);

                $projectDropPlaceholder.hide();
                $selectedProjectDetailsDiv
                    .html(`✓ Project Selected: <strong>${selectedProject.name}</strong>`)
                    .css('color', '#46b450')
                    .show();
                $clearDroppedProjectBtn.show();

                $expenseProjectDropDisplay.addClass('has-project');
                $('.timegrow-project-tile').css('opacity', 0.6);
            } else {
                // Reset if no selection
                selectedProjectIdForExpense = null;
                $selectedExpenseProjectIdInput.val('');

                $selectedProjectDetailsDiv.html('').hide();
                $projectDropPlaceholder.show();
                $clearDroppedProjectBtn.hide();

                $expenseProjectDropDisplay.removeClass('has-project');
                $('.timegrow-project-tile').css('opacity', 1);
            }
        });

        // Also allow click to select on tiles
        if (!isTouchDevice) {
            $('.timegrow-project-tile').on('click', function(e) {
                if (!selectedProjectIdForExpense) {
                    e.preventDefault();
                    const projectId = $(this).data('project-id');
                    if (projectId) {
                        $select.val(projectId).trigger('change');
                    }
                }
            });
        }
    }

    // --- Project Tile Drag/Drop Logic ---
    $projectTiles.attr('draggable', true);

    $projectTiles.on('dragstart', function (e) {
        console.log('Project dragstart');
        // Prevent dragging if a project is already selected for the expense
        if (selectedProjectIdForExpense) {
            e.preventDefault();
            console.log("Drag prevented: A project is already selected for the expense.");
            return;
        }
        e.originalEvent.dataTransfer.setData('project-id', $(this).data('project-id'));
        e.originalEvent.dataTransfer.setData('name', $(this).data('project-name'));
        e.originalEvent.dataTransfer.setData('desc', $(this).data('project-desc'));
    });

    $expenseProjectDropDisplay
        .on('dragover', function (e) {
            e.preventDefault();
            if (!selectedProjectIdForExpense) { // Only show dragging-over if no project is selected
                $(this).addClass('dragging-over');
            }
        })
        .on('dragleave', function () {
            $(this).removeClass('dragging-over');
        })
        .on('drop', function (e) {
            e.preventDefault();
            if (selectedProjectIdForExpense) { // If a project already selected, don't allow another drop
                console.log("Drop ignored: Project already selected for expense.");
                $(this).removeClass('dragging-over');
                return;
            }

            console.log('Project dropped on expense assignment zone');
            const projectId = e.originalEvent.dataTransfer.getData('project-id');
            const projectName = e.originalEvent.dataTransfer.getData('name');
            const projectDesc = e.originalEvent.dataTransfer.getData('desc');

            if (projectId) {
                selectedProjectIdForExpense = projectId; // Store the selected project ID
                $selectedExpenseProjectIdInput.val(projectId); // Set hidden input value

                $projectDropPlaceholder.hide(); // Hide initial placeholder text
                $selectedProjectDetailsDiv
                    .html(`Project: ${projectName} (ID: ${projectId}) <br /> <small style="color: #555;">${projectDesc || 'No description'}</small>`)
                    .show(); // Show and populate details
                $clearDroppedProjectBtn.show(); // Show the clear button

                $(this).addClass('has-project'); // Add class for styling selected state
                $projectTiles.css('opacity', 0.6).attr('draggable', false); // Visually indicate other projects are now not draggable
            }
            $(this).removeClass('dragging-over');
        });

    // --- Clear Dropped Project Button Functionality ---
    $clearDroppedProjectBtn.on('click', function() {
        console.log('Clearing selected project for expense.');
        selectedProjectIdForExpense = null; // Clear stored ID
        $selectedExpenseProjectIdInput.val(''); // Clear hidden input

        $selectedProjectDetailsDiv.html('').hide(); // Clear and hide details
        $projectDropPlaceholder.show(); // Show initial placeholder text
        $clearDroppedProjectBtn.hide(); // Hide the clear button

        $expenseProjectDropDisplay.removeClass('has-project'); // Remove selected state styling
        $projectTiles.css('opacity', 1).attr('draggable', true); // Re-enable project tiles for dragging

        // Clear dropdown selection
        $('#expense-project-selector').val('');
        $('#expense-project-search').val('');
    });


    // --- Receipt File Drag and Drop & Input Logic ---
    // ... (Your existing receipt handling code: $receiptDropZone, $receiptFileInput, handleFiles, addReceiptPreview, remove preview) ...
    const $receiptDropZone = $('#receipt-drop-zone');
    const $receiptFileInput = $('#receipt-file-input');
    const $receiptPreviewArea = $('#receipt-preview-area');
    let receiptFiles = [];

    if ($receiptDropZone.length && $receiptFileInput.length) {
        // ... (Keep the receipt drop/click logic from previous correct version)
        // Example snippet:
        $receiptDropZone
            .on('dragover', function(e) { /* ... */ $(this).addClass('dragging-over'); })
            .on('dragleave', function(e) { /* ... */ $(this).removeClass('dragging-over'); })
            .on('drop', function(e) {
                e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragging-over');
                if (e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files) {
                    handleFiles(e.originalEvent.dataTransfer.files);
                }
            });
        $receiptDropZone.on('click', function() { $receiptFileInput.click(); });
        $receiptFileInput.on('change', function(e) { if (e.target.files) { handleFiles(e.target.files); } $(this).val(''); });
    }
    function handleFiles(files) { /* ... your file handling logic ... */ }
    function addReceiptPreview(file) { /* ... your preview logic ... */ }
    if ($receiptPreviewArea && $receiptPreviewArea.length) {
        $receiptPreviewArea.on('click', '.remove-receipt-btn', function() { /* ... your remove preview logic ... */ });
    }


    // --- Form Submission Logic ---
    // $('#expense-form').on('submit', function(e) {
    //      e.preventDefault();
    //      const formData = new FormData(this);
    //      // Make sure to append selectedProjectIdForExpense if it's set:
    //      if (selectedProjectIdForExpense) {
    //          formData.append('expense_project_id', selectedProjectIdForExpense); // Or use the name of your hidden input
    //      }
    //      // ... append receiptFiles ...
    //      // ... AJAX call ...
    // });

    console.log('Expense Recorder JS Fully Initialized.');
});