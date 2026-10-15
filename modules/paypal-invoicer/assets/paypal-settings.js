/**
 * PayPal settings: the client secret is shown masked (last 8 characters only).
 * "Update" reveals an empty field for a new secret; "Cancel" keeps the saved one.
 * The field stays disabled until then, so the saved secret is not overwritten.
 */
jQuery(function ($) {
    $(document).on('click', '.timegrow-secret-update', function () {
        var $wrap = $(this).closest('.timegrow-secret-field');
        var $input = $wrap.find('input[type="password"]');
        var editing = $input.prop('disabled');

        $input.prop('disabled', !editing).prop('hidden', !editing).val('');
        $wrap.find('.timegrow-secret-mask').prop('hidden', editing);
        $(this).text(editing ? $(this).data('cancel') : $(this).data('update'));
        if (editing) $input.trigger('focus');
    });
});
