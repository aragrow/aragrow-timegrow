// File: assets/js/project-picker.js
// Client dropdown that narrows the grouped Project dropdown (see TimeGrowProjectPicker.php).
jQuery(document).ready(function($) {
    $('.timegrow-client-picker').each(function() {
        var $client  = $(this);
        var $project = $client.closest('form').find('.timegrow-project-picker');
        if (!$project.length) {
            return;
        }

        // Keep a full copy: hiding <option> elements does not work in every browser, so the list is rebuilt
        var $all = $project.children().clone();

        function narrow() {
            var client   = $client.val();
            var selected = $project.val();
            $project.empty().append($all.clone());
            if (client !== '') {
                $project.children('optgroup').filter(function() {
                    return String($(this).data('client')) !== client;
                }).remove();
            }
            // Keep the chosen project if it is still listed, otherwise ask for a new one
            if ($project.find('option[value="' + selected + '"]').length) {
                $project.val(selected);
            } else {
                $project.val('');
            }
        }

        // Picking a project sets its client, so the two always agree
        $project.on('change', function() {
            var client = $project.find('option:selected').data('client');
            if (client !== undefined && $client.val() !== String(client)) {
                $client.val(String(client));
                narrow();
            }
        });

        $client.on('change', narrow);
        narrow();
    });
});
