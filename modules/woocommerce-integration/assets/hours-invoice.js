/**
 * Hours invoices: replace the Quantity box with the Project Hours box on each order line.
 * Typing hours updates the hidden Quantity box and fires its change event, so
 * WooCommerce recalculates the line total and taxes from the unit rate.
 */
jQuery(function ($) {
    var $flag = $('#timekeeping_invoice');
    if (!$flag.length) return;

    function qtyBox($row) {
        return $row.find('td.quantity input[name^="order_item_qty"]');
    }

    function rateFor($row) {
        var rates = (window.timegrowHoursInvoice && window.timegrowHoursInvoice.rates) || {};
        return parseFloat(rates[$row.data('order_item_id')]) || 0;
    }

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

    function apply() {
        var on = $flag.is(':checked');

        // Column headers and the "x" in front of the quantity
        var $qtyHead = $('#woocommerce-order-items th.quantity');
        if (!$qtyHead.data('orig')) $qtyHead.data('orig', $qtyHead.text());
        $qtyHead.text(on ? 'Hours' : $qtyHead.data('orig'));
        var $priceHead = $('#woocommerce-order-items th.item_cost');
        if (!$priceHead.data('orig')) $priceHead.data('orig', $priceHead.text());
        $priceHead.text(on ? 'Rate' : $priceHead.data('orig'));
        $('#woocommerce-order-items td.quantity .view .times').toggle(!on);

        $('#woocommerce-order-items tr.item').each(function () {
            var $row = $(this);
            var $wrap = $row.find('.timegrow-line-hours');
            var $qty = qtyBox($row);
            if (!$wrap.length || !$qty.length) return;

            var $input = $wrap.find('input');
            var rate = rateFor($row);
            if (on && rate > 0 && window.accounting) {
                $row.find('td.item_cost .view').html(money(rate));
            }
            var $editCell = $row.find('td.quantity .edit');

            if (on) {
                if (!$wrap.data('home')) $wrap.data('home', $wrap.parent());
                $wrap.appendTo($editCell);
                $qty.hide();
                $wrap.find('.description').hide();
                // Hours field starts from the current quantity and drives it
                $input.val(parseFloat($qty.val() || 0).toFixed(2));
            } else if ($wrap.data('home')) {
                $wrap.appendTo($wrap.data('home'));
                $wrap.find('.description').show();
                $qty.show();
            }
        });
    }

    // Hours field drives the Quantity box and WooCommerce's own recalculation
    $(document).on('input change', '.timegrow-line-hours input', function () {
        if (!$flag.is(':checked')) return;
        var $row = $(this).closest('tr.item');
        var hours = parseFloat($(this).val());
        if (isNaN(hours) || hours < 0) return;
        var $qty = qtyBox($row);
        $qty.val(hours).trigger('change'); // WooCommerce rescales the line from the old rate

        // Then price the line from the project or default rate
        var rate = rateFor($row);
        if (rate > 0) {
            var amount = (Math.round(rate * hours * 100) / 100).toString();
            $row.find('input.line_subtotal, input.line_total').val(amount);
        }
    });

    $flag.on('change', apply);
    // WooCommerce replaces the item rows after a save or reload, so re-apply
    $(document.body).on('wc_order_items_reloaded', apply);
    $(document).on('items_saved', apply);
    apply();
});
