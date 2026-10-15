/**
 * Hours invoices: each order line shows an Hours box instead of the Quantity box.
 * The hours are saved on the line and the quantity stays 1. Typing hours updates the
 * line subtotal and total from the rate (project, client, then company rate).
 */
jQuery(function ($) {
    var $flag = $('#timekeeping_invoice');
    if (!$flag.length) return;

    // Old orders built from time entries count quantity in 10 minute units: leave them alone
    if (window.timegrowHoursInvoice && window.timegrowHoursInvoice.enabled === false) return;

    function qtyBox($row) {
        return $row.find('td.quantity input[name^="order_item_qty"]');
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

    // Rate for a line: the server rate, else the rate the line already carries
    function rateFor($row, hours) {
        var rates = (window.timegrowHoursInvoice && window.timegrowHoursInvoice.rates) || {};
        var rate = parseFloat(rates[$row.data('order_item_id')]) || 0;
        if (rate > 0) return rate;
        if ($row.data('rate0') === undefined) {
            var sub = parseFloat($row.find('input.line_subtotal').val()) || 0;
            $row.data('rate0', hours > 0 ? sub / hours : 0);
        }
        return $row.data('rate0');
    }

    function hoursOf($row) {
        return parseFloat($row.find('.timegrow-line-hours input[name^="timegrow_line_hours"]').val()) || 0;
    }

    function apply() {
        var on = $flag.is(':checked');

        // Column headers
        var $qtyHead = $('#woocommerce-order-items th.quantity');
        if (!$qtyHead.data('orig')) $qtyHead.data('orig', $qtyHead.text());
        $qtyHead.text(on ? 'Hours' : $qtyHead.data('orig'));
        var $priceHead = $('#woocommerce-order-items th.item_cost');
        if (!$priceHead.data('orig')) $priceHead.data('orig', $priceHead.text());
        $priceHead.text(on ? 'Rate' : $priceHead.data('orig'));

        $('#woocommerce-order-items tr.item').each(function () {
            var $row = $(this);
            var $wrap = $row.find('.timegrow-line-hours');
            var $qty = qtyBox($row);
            if (!$wrap.length || !$qty.length) return;

            var hours = hoursOf($row);
            var $view = $row.find('td.quantity .view');
            var $editCell = $row.find('td.quantity .edit');

            if (on) {
                if (!$wrap.data('home')) $wrap.data('home', $wrap.parent());
                if (!$view.data('orig')) $view.data('orig', $view.html());

                $wrap.appendTo($editCell);
                $wrap.find('.description').hide();
                if (!$wrap.find('.timegrow-hours-mode').length) {
                    $wrap.append('<input type="hidden" class="timegrow-hours-mode" name="timegrow_hours_mode[' + $row.data('order_item_id') + ']" value="1">');
                }

                // Quantity stays 1 on an hours invoice; the hours are billed from the Hours box
                $qty.hide().val(1);
                $view.text(hours.toFixed(2));

                var rate = rateFor($row, hours);
                if (rate > 0 && window.accounting) $row.find('td.item_cost .view').html(money(rate));
            } else {
                $wrap.find('.timegrow-hours-mode').remove();
                if ($wrap.data('home')) {
                    $wrap.appendTo($wrap.data('home'));
                    $wrap.find('.description').show();
                }
                if ($view.data('orig')) $view.html($view.data('orig'));
                // Give the line its hours back as a normal quantity
                if ($qty.is(':hidden') && hours > 0) $qty.val(hours);
                $qty.show();
            }
        });
    }

    // Typing hours prices the line; the server saves hours and sets the quantity to 0
    $(document).on('input change', '.timegrow-line-hours input[name^="timegrow_line_hours"]', function () {
        if (!$flag.is(':checked')) return;
        var $row = $(this).closest('tr.item');
        var hours = parseFloat($(this).val());
        if (isNaN(hours) || hours < 0) return;

        var rate = rateFor($row, hours);
        if (rate > 0) {
            var amount = (Math.round(rate * hours * 100) / 100).toString();
            $row.find('input.line_subtotal, input.line_total').val(amount);
        }
        $row.find('td.quantity .view').text(hours.toFixed(2));
    });

    $flag.on('change', apply);
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
