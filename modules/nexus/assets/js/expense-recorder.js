// File: assets/js/expense-recorder.js
// Nexus > Record Expenses: receipts (drop or choose, preview, remove), who the expense is for (myself,
// a project or a client), optional reading of the first receipt with AI, and the save
// (AJAX timegrow_nexus_save_expense). The server checks who may record what; the choices on the page
// are only the ones this person may use (TimeGrowExpenseRules).
jQuery(document).ready(function($) {
    const cfg = window.timegrowExpense || {};
    const $form = $('#expense-form');
    if (!$form.length) return;

    const $drop = $('#receipt-drop-zone');
    const $input = $('#receipt-file-input');
    const $previews = $('#receipt-preview-area');
    const $message = $('#expense-form-message');
    const $save = $('#save-expense-btn');
    const $readAi = $('#read-receipt-ai');
    const maxSize = Number(cfg.maxSize) || 524288;
    const maxFiles = Number(cfg.maxFiles) || 5;
    let receipts = []; // File objects, in the order added

    function show(text, kind) {
        $message.removeClass('success error').addClass(kind).text(text).prop('hidden', false);
    }
    function sizeText(bytes) {
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.round(bytes / 1024) + ' KB';
    }

    // --- Receipts -----------------------------------------------------------

    function addFiles(files) {
        const problems = [];
        Array.from(files || []).forEach(file => {
            if (!/\.(jpe?g|png|pdf)$/i.test(file.name)) { problems.push(`${file.name} is not a JPG, PNG or PDF.`); return; }
            if (file.size > maxSize) { problems.push(`${file.name} is ${sizeText(file.size)}; the limit is ${sizeText(maxSize)}.`); return; }
            if (receipts.length >= maxFiles) { problems.push(`Attach at most ${maxFiles} receipts.`); return; }
            if (receipts.some(r => r.name === file.name && r.size === file.size)) return; // already added
            receipts.push(file);
        });
        renderReceipts();
        if (problems.length) show(problems.join(' '), 'error');
        else $message.prop('hidden', true);
    }

    function renderReceipts() {
        $previews.empty();
        receipts.forEach((file, i) => {
            const $item = $('<div>', { class: 'receipt-preview-item' });
            if (/^image\//.test(file.type) && window.URL) {
                const url = URL.createObjectURL(file);
                $item.append($('<img>', { src: url, alt: '' }).on('load', () => URL.revokeObjectURL(url)));
            } else {
                $item.append($('<span>', { class: 'pdf-icon dashicons dashicons-media-document', 'aria-hidden': 'true' }));
            }
            $item.append($('<span>', { class: 'receipt-name', text: file.name, title: file.name }));
            $item.append($('<button>', { type: 'button', class: 'remove-receipt-btn', text: '×', 'aria-label': 'Remove ' + file.name }).data('index', i));
            $previews.append($item);
        });
        $readAi.prop('disabled', !receipts.length);
    }

    $drop.on('click', () => $input.trigger('click'))
        .on('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $input.trigger('click'); } })
        .on('dragover', e => { e.preventDefault(); $drop.addClass('dragging-over'); })
        .on('dragleave', () => $drop.removeClass('dragging-over'))
        .on('drop', e => {
            e.preventDefault();
            $drop.removeClass('dragging-over');
            if (e.originalEvent.dataTransfer) addFiles(e.originalEvent.dataTransfer.files);
        });
    $input.on('change', function() { addFiles(this.files); $(this).val(''); });
    $previews.on('click', '.remove-receipt-btn', function() {
        receipts.splice(Number($(this).data('index')), 1);
        renderReceipts();
    });

    // --- Who it is for --------------------------------------------------------

    function showAssign() {
        const v = $form.find('input[name="assigned_to"]:checked').val();
        $('#expense-project-group').prop('hidden', v !== 'project');
        $('#expense-client-group').prop('hidden', v !== 'client');
        $('#expense-project').prop('required', v === 'project'); // the browser asks for it before saving
        $('#expense-client').prop('required', v === 'client');
    }
    $form.on('change', 'input[name="assigned_to"]', showAssign);
    showAssign();

    // --- Reading a receipt with AI (optional) ---------------------------------

    $readAi.on('click', function() {
        if (!receipts.length) return;
        const data = new FormData();
        data.append('action', 'analyze_receipt_realtime');
        data.append('nonce', cfg.aiNonce);
        data.append('receipt_file', receipts[0]);
        $readAi.prop('disabled', true);
        show('Reading the receipt…', 'success');
        $.ajax({ url: cfg.ajaxUrl, method: 'POST', data: data, processData: false, contentType: false })
            .done(res => {
                if (!res || !res.success) { show((res && res.data) || 'The receipt could not be read.', 'error'); return; }
                const d = res.data;
                if (d.expense_name) $('#expense-name').val(d.expense_name);
                if (d.expense_date && d.expense_date <= (cfg.today || d.expense_date)) $('#expense-date').val(d.expense_date);
                if (d.amount > 0) $('#expense-amount').val(Number(d.amount).toFixed(2));
                if (d.expense_description) $('#expense-description').val(d.expense_description);
                if (d.category_id && $('#expense-category option[value="' + Number(d.category_id) + '"]').length) $('#expense-category').val(String(d.category_id));
                show(d.message || 'Receipt read. Check every field before saving.', d.low_confidence || d.suspicious || d.refund ? 'error' : 'success');
            })
            .fail(() => show('The receipt could not be read: the site could not be reached.', 'error'))
            .always(() => $readAi.prop('disabled', !receipts.length));
    });

    // --- Saving ----------------------------------------------------------------

    function clearForm() {
        $form[0].reset();
        $('#expense-date').val(cfg.today || '');
        receipts = [];
        renderReceipts();
        showAssign();
    }
    $('#clear-expense-form-btn').on('click', () => { clearForm(); $message.prop('hidden', true); });

    $form.on('submit', function(e) {
        e.preventDefault();
        if (!this.checkValidity()) { this.reportValidity(); return; }
        const data = new FormData(this);
        data.append('action', 'timegrow_nexus_save_expense');
        data.append('nonce', cfg.nonce);
        receipts.forEach(file => data.append('receipts[]', file, file.name));
        $save.prop('disabled', true).text('Saving…');
        $.ajax({ url: cfg.ajaxUrl, method: 'POST', data: data, processData: false, contentType: false })
            .done(res => {
                if (res && res.success) {
                    show(res.data.message + ' Reloading the list…', 'success');
                    clearForm();
                    setTimeout(() => window.location.reload(), 900); // the list under the form shows it
                    return;
                }
                show((res && res.data && res.data.message) || 'The expense could not be saved.', 'error');
            })
            .fail(xhr => show(xhr && xhr.status === 403 ? 'You are not allowed to record expenses.' : 'The expense could not be saved: the site could not be reached.', 'error'))
            .always(() => $save.prop('disabled', false).text('Save expense'));
    });
});
