// File: assets/js/expense-recorder.js
jQuery(document).ready(function($) {
    console.log('Expense Recorder JS Initializing...');

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