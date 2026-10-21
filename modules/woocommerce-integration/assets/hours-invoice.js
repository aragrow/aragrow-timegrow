/**
 * Hours invoices (created by the TimeGrow order creator): each hours line shows an
 * Hours box instead of the Quantity box (lines added by hand keep their Quantity box). The hours are saved on the line and the
 * quantity stays 1. Typing hours updates the line subtotal and total at the line's rate.
 */
jQuery(function ($) {
    var config = window.timegrowHoursInvoice || {};
    if (!config.enabled) return;

    function money(amount) {
        var m = window.woocommerce_admin_meta_boxes;
        return accounting.formatMoney(amount, {
            symbol: m.currency_format_symbol,
            decimal: m.currency_format_decimal_sep,
            thousand: m.currency_format_thousand_sep,
            precision: m.currency_format_num_decimals,
            format: m.currency_format
        });
    }

    // Rate from the server (the line's own rate, else project, client, company)
    function rateFor($row) {
        var rates = config.rates || {};
        return parseFloat(rates[$row.data('order_item_id')]) || 0;
    }

    function apply() {
        // With lines added by hand on the invoice, the columns hold both
        var mixed = $('#woocommerce-order-items tr.item').filter(function () {
            return !$(this).find('.timegrow-line-hours').length;
        }).length > 0;
        $('#woocommerce-order-items th.quantity').text(mixed ? 'Hours / Qty' : 'Hours');
        $('#woocommerce-order-items th.item_cost').text(mixed ? 'Rate / Cost' : 'Rate');

        $('#woocommerce-order-items tr.item').each(function () {
            var $row = $(this);
            var $wrap = $row.find('.timegrow-line-hours');
            var $qty = $row.find('td.quantity input[name^="order_item_qty"]');
            if (!$wrap.length || !$qty.length) return;

            var hours = parseFloat($wrap.find('input').val()) || 0;

            // The Hours box takes the Quantity box's place; quantity stays 1
            $wrap.appendTo($row.find('td.quantity .edit'));
            $qty.hide().val(1);
            $row.find('td.quantity .view').text(hours.toFixed(2));

            var rate = rateFor($row);
            if (rate > 0 && window.accounting) $row.find('td.item_cost .view').html(money(rate));
        });
    }

    // Typing hours prices the line at its rate; the server saves it on Update or line Save
    $(document).on('input change', '.timegrow-line-hours input', function () {
        var $row = $(this).closest('tr.item');
        var hours = parseFloat($(this).val());
        if (isNaN(hours) || hours < 0) return;

        var rate = rateFor($row);
        if (rate > 0) {
            var amount = (Math.round(rate * hours * 100) / 100).toString();
            $row.find('input.line_subtotal, input.line_total').val(amount);
        }
        $row.find('td.quantity .view').text(hours.toFixed(2));
    });

    // WooCommerce redraws the item rows after a save, recalculate or reload without a
    // reliable event, so watch the items box and re-apply when the rows are replaced.
    var box = document.getElementById('woocommerce-order-items');
    if (box && window.MutationObserver) {
        var timer = null;
        var observer = new MutationObserver(function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                observer.disconnect();   // avoid reacting to our own changes
                apply();
                observer.observe(box, { childList: true, subtree: true });
            }, 50);
        });
        observer.observe(box, { childList: true, subtree: true });
    }
    apply();
});
