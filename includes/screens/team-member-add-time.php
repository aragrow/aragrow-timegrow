<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;
// Get the current user
$current_user = wp_get_current_user();

// Ensure the user is logged in
if ($current_user->ID) {
    // Prepare and execute the query securely
    $sql = $wpdb->prepare(
        "SELECT p.ID, p.name, c.name AS client_name
        FROM {$prefix}timeflies_team_members m
        JOIN {$prefix}timeflies_team_member_projects mp ON mp.team_member_id = m.ID
        JOIN {$prefix}timeflies_projects p ON mp.project_id = p.ID
        JOIN {$prefix}timeflies_clients c ON p.client_id = c.ID
        WHERE m.user_id = %d",
        $current_user->ID
    );

    // Fetch the results as an associative array
    $projects = $wpdb->get_results($sql, ARRAY_A);
    var_dump($wpdb->last_query);

} else {
    echo '<p>You must be logged in to view your projects.</p>';
}
?>

<div class="wrap time-tracker-wrapper">
    <h2>Time Tracker</h2>

    <div class="time-tracker-card">
        <?php if (!empty($projects)) : ?>
        <div class="project-buttons">
            <?php foreach ($projects as $project) : ?>
                <button class="project-button" 
                        data-client-id="<?php echo esc_attr($project['client_id']); ?>"
                        data-project-id="<?php echo esc_attr($project['project_id']); ?>">
                    <?php echo esc_html($project['name'] . ' - ' . $project['client_name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="tracker-status">
            <?php if ($active_entry) : ?>
                <div class="active-session">
                    <h3>Current Session:</h3>
                    <p><?php echo esc_html($active_entry['project_name'] . ' - ' . $active_entry['client_name']); ?></p>
                    <p>Started: <?php echo date('M j, Y g:i A', strtotime($active_entry['start_time'])); ?></p>
                    <p>Duration: <span id="current-duration"><?php echo human_time_diff(strtotime($active_entry['start_time'])); ?></span></p>
                </div>
            <?php else : ?>
                <div class="no-session">
                    <p>Select a project above to start tracking time</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="tracker-controls">
            <?php if ($active_entry) : ?>
                <button id="clock-out-btn" class="button button-danger button-hero">Clock Out</button>
            <?php else : ?>
                <div class="button-group">
                    <button id="clock-in-btn" class="button button-primary button-hero">Clock In</button>
                    <button id="manual-entry-btn" class="button button-secondary button-hero">Manual Entry</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Manual Entry Modal -->
    <div id="manual-entry-modal" class="wp-core-ui postbox" style="display:none; max-width: 600px; margin: 20px auto;">
        <h2 class="hndle"><span>Manual Time Entry</span></h2>
        <div class="inside">
            <div class="form-row">
                <label for="manual-date">Date:</label>
                <input type="text" id="manual-date" class="regular-text" value="<?php echo date('Y-m-d'); ?>" placeholder="Select a date">
            </div>
            <div class="form-row">
                <label for="hours-slider">Hours:</label>
                <div id="hours-slider" style="margin: 10px 0;"></div>
                <input type="text" id="hours" name="hours" readonly class="small-text" style="border: none; background: transparent;" value="0">
            </div>
            <div class="form-row">
                <label for="minutes-slider">Minutes:</label>
                <div id="minutes-slider" style="margin: 10px 0;"></div>
                <input type="text" id="minutes" name="minutes" readonly class="small-text" style="border: none; background: transparent;" value="0">
            </div>
            <div class="form-row">
                <label for="manual-description">Description:</label>
                <textarea id="manual-description" rows="3" class="large-text"></textarea>
            </div>
            <div class="modal-actions">
                <button id="save-manual-entry" class="button button-primary">Save Entry</button>
                <button id="cancel-manual-entry" class="button">Cancel</button>
            </div>
        </div>
    </div>

    <div class="recent-entries">
        <h3>Recent Entries</h3>
        <div id="recent-entries-list">
            <!-- AJAX content will be loaded here -->
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize the date picker
    $('#manual-date').datepicker({
        dateFormat: 'yy-mm-dd', // Format matches WordPress's default format
        changeMonth: true,
        changeYear: true,
    });

    // Initialize the hours slider
    $('#hours-slider').slider({
        range: 'min',
        min: 0,
        max: 23,
        value: 0,
        slide: function(event, ui) {
            $('#hours').val(ui.value);
        }
    });

    // Initialize the minutes slider
    $('#minutes-slider').slider({
        range: 'min',
        min: 0,
        max: 59,
        step: 5,
        value: 0,
        slide: function(event, ui) {
            $('#minutes').val(ui.value);
        }
    });

    // Cancel button hides the modal
    $('#cancel-manual-entry').click(function() {
        $('#manual-entry-modal').hide();
    });

    // Save button (example functionality)
    $('#save-manual-entry').click(function() {
        const date = $('#manual-date').val();
        const hours = $('#hours').val();
        const minutes = $('#minutes').val();
        const description = $('#manual-description').val();

        if (!date || hours === '' || minutes === '') {
            alert('Please fill in all required fields.');
            return;
        }

        // Example AJAX call to save the entry
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_manual_entry',
                date,
                hours,
                minutes,
                description,
                security: '<?php echo wp_create_nonce('manual_entry_nonce'); ?>'
            },
            success(response) {
                if (response.success) {
                    alert('Time entry saved successfully!');
                    location.reload(); // Reload the page or update the UI dynamically
                } else {
                    alert(response.data.message || 'An error occurred.');
                }
            }
        });
    });
});


jQuery(document).ready(function($) {
    let selectedProject = null;
    let timerInterval;

    // Project selection
    $('.project-button').click(function() {
        $('.project-button').removeClass('active');
        $(this).addClass('active');
        selectedProject = {
            clientId: $(this).data('client-id'),
            projectId: $(this).data('project-id'),
            projectName: $(this).text()
        };
    });

    // Clock In handler
    $('#clock-in-btn').click(function() {
        if (!selectedProject) {
            alert('Please select a project first');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'clock_in',
                client_id: selectedProject.clientId,
                project_id: selectedProject.projectId,
                nonce: '<?php echo wp_create_nonce('timeflies_check_in'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Clock Out handler
    $('#clock-out-btn').click(function() {
        $.ajax({
            url:  ajaxurl,
            type: 'POST',
            data: {
                action: 'clock_out',
                nonce: '<?php echo wp_create_nonce('timeflies_check_out'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Manual Entry handlers
    $('#manual-entry-btn').click(function() {
        if (!selectedProject) {
            alert('Please select a project first');
            return;
        }
        $('#manual-entry-modal').show();
    });

    $('#save-manual-entry').click(function() {
        const entryData = {
            client_id: selectedProject.clientId,
            project_id: selectedProject.projectId,
            date: $('#manual-date').val(),
            start_time: $('#manual-start-time').val(),
            end_time: $('#manual-end-time').val(),
            description: $('#manual-description').val()
        };

        if (!validateManualEntry(entryData)) return;

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'save_manual_entry',
                ...entryData,
                nonce: '<?php echo wp_create_nonce('timeflies_manual_entry'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#manual-entry-modal').hide();
                    loadRecentEntries();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    function validateManualEntry(data) {
        const start = new Date(`${data.date}T${data.start_time}`);
        const end = new Date(`${data.date}T${data.end_time}`);
        
        if (start >= end) {
            alert('End time must be after start time');
            return false;
        }
        return true;
    }
});
</script>

<style>
.project-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.project-button {
    padding: 15px;
    text-align: left;
    background:chartreuse;
    border: 1px solid #dcdcde;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.2s;
}

.project-button:hover {
    background: #e0e0e1;
}

.project-button.active {
    background: #2271b1;
    color: white;
    border-color: #135e96;
}

.button-group {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.button-hero {
    padding: 15px 25px;
    font-size: 1.1em;
}

/* Style adjustments for WordPress admin look */
#manual-entry-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 10000;
    background-color: #fff;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    border-radius: 4px;
}

.modal-actions {
    margin-top: 20px;
}

.form-row {
    margin-bottom: 15px;
}

.form-row label {
    font-weight: bold;
}

#hours, #minutes {
    font-size: 16px;
}
</style>
