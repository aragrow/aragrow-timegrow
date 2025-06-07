<?php
// #### TimeGrowManualView.php ####

if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusManualView {

    /**
     * Displays the Manual Time Entry page.
     *
     * @param WP_User $user The current WordPress user object.
     * @param array $args Additional arguments.
     */
    public function display($user, $args = []) {
        if (!$user || !$user->ID) {
            echo '<div class="wrap"><p>' . esc_html__('Error: User not found or not logged in.', 'timegrow') . '</p></div>';
            return;
        }

        $clients = $this->get_clients_for_user($user->ID);

        // Hours in 15 min increments up to 12 hrs
        $hour_options = [];
        for ($i = 0.25; $i <= 12; $i += 0.25) {
            $minutes = $i * 60;
            $label = floor($minutes / 60) . 'h';
            $min = $minutes % 60;
            if ($min > 0) $label .= ' ' . $min . 'm';
            $hour_options[] = [
                'value' => $i,
                'label' => $label,
            ];
        }

        $js_data = [
            'userId'             => $user->ID,
            'userName'           => $user->display_name,
            'apiNonce'           => wp_create_nonce('wp_rest'),
            'timegrowApiEndpoint'=> rest_url('timegrow/v1/manual'),
        ];

        ?>
        <div class="wrap timegrow-page-container timegrow-manual-page-container">
            <h1><?php esc_html_e('Manual Time Entry', 'timegrow'); ?></h1>

            <div class="timegrow-manual-container">

                <!-- Manual Entry Form -->
                <form id="manual-entry-form">
                    <label for="manual-datetime"><?php esc_html_e('Select Date & Time:', 'timegrow'); ?></label>
                    <input type="datetime-local" id="manual-datetime" name="datetime" required>

                    <label for="manual-hours"><?php esc_html_e('Hours Worked:', 'timegrow'); ?></label>
                    <select id="manual-hours" name="hours">
                        <?php foreach ($hour_options as $opt): ?>
                            <option value="<?php echo esc_attr($opt['value']); ?>">
                                <?php echo esc_html($opt['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="hidden" id="manual-client-id" name="client_id">
                    <button type="submit" class="timegrow-button active" id="timegrow-submit">
                        <?php esc_html_e('Submit Entry', 'timegrow'); ?>
                    </button>
                     <!-- Client Drop Section -->
                    <div id="manual-client-drop-section" class="timegrow-drop-section">
                        <p class="drop-zone-text"><?php esc_html_e('Drop a client here to assign', 'timegrow'); ?></p>
                        <div id="manual-drop-zone" class="timegrow-drop-zone"><?php esc_html_e('Drop Client Here', 'timegrow'); ?></div>
                    </div>

                    <!-- Client Tiles -->
                    <div id="manual-client-tiles-container" class="timegrow-client-tiles">
                        <?php foreach ($clients as $client): ?>
                            <div class="timegrow-client-tile" data-client-id="<?php echo esc_attr($client['id']); ?>">
                                <?php echo esc_html($client['name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>

            <?php
            // Pass data to JS
            wp_localize_script('timegrow-manual-js', 'timegrowManualAppData', $js_data);
            ?>
        </div>
        <?php
    }

    /**
     * Gets clients for a user. Replace with your actual implementation.
     */
    private function get_clients_for_user($user_id) {
        // Example hard-coded; replace with CPT, user_meta, etc.
        return [
            ['id' => 'client_1', 'name' => 'Client A'],
            ['id' => 'client_2', 'name' => 'Client B'],
            ['id' => 'client_3', 'name' => 'Client C'],
        ];
    }
}
