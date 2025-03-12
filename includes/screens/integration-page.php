<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

?>
<div class="wrap">
    <h1>Timeflies Integrations</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('timeflies_integration_group');
        do_settings_sections('timeflies-integrations');
        submit_button('Save Settings');
        ?>
    </form>
</div>
