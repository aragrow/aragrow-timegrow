<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client + project picker for time entry forms.
 *
 * A "Client" dropdown narrows a "Project" dropdown whose projects are grouped by client, so two
 * clients' projects with the same name can't be confused. Only active projects are listed, plus
 * the project already on the entry. Without JavaScript the grouped project list works on its own
 * (the client dropdown is only a filter and is not submitted). Script: assets/js/project-picker.js.
 */
class TimeGrowProjectPicker {

    const ACTIVE_STATUS = 1;

    /**
     * A client's display name (cached per request), or '' when there is none.
     */
    public static function client_name($client_id) {
        static $names = [];
        $client_id = (int) $client_id;
        if (!isset($names[$client_id])) {
            $user = $client_id ? get_userdata($client_id) : false;
            $names[$client_id] = $user ? $user->display_name : '';
        }
        return $names[$client_id];
    }

    /**
     * Projects grouped by client, sorted by client then project name. Uses client_name on the
     * project when it is there, otherwise looks it up.
     *
     * @param object[] $projects   Projects with ID, name, client_id, client_name and status.
     * @param int      $selected_id Project to keep even when it is not active.
     * @return array[] client_id => [ 'name' => client name, 'projects' => object[] ]
     */
    public static function group_by_client($projects, $selected_id = 0) {
        $groups = [];
        foreach ((array) $projects as $project) {
            $active = (int) ($project->status ?? self::ACTIVE_STATUS) === self::ACTIVE_STATUS;
            if (!$active && (int) $project->ID !== (int) $selected_id) {
                continue;
            }
            $client_id = (int) ($project->client_id ?? 0);
            if (!isset($groups[$client_id])) {
                $name = trim((string) ($project->client_name ?? self::client_name($client_id)));
                $groups[$client_id] = ['name' => $name !== '' ? $name : __('No client', 'timegrow'), 'projects' => []];
            }
            $groups[$client_id]['projects'][] = $project;
        }

        uasort($groups, function ($a, $b) { return strnatcasecmp($a['name'], $b['name']); });
        foreach ($groups as &$group) {
            usort($group['projects'], function ($a, $b) { return strnatcasecmp($a->name, $b->name); });
        }
        unset($group);
        return $groups;
    }

    /**
     * Print the two table rows (Client, Project) for a form-table.
     */
    public static function render_rows($projects, $selected_id = 0) {
        $groups          = self::group_by_client($projects, $selected_id);
        $selected_client = '';
        foreach ($groups as $client_id => $group) {
            foreach ($group['projects'] as $project) {
                if ((int) $project->ID === (int) $selected_id) {
                    $selected_client = (string) $client_id;
                }
            }
        }
        ?>
        <tr>
            <th scope="row"><label for="timegrow_client_picker"><?php esc_html_e('Client', 'timegrow'); ?></label></th>
            <td>
                <select id="timegrow_client_picker" class="large-text timegrow-client-picker">
                    <option value=""><?php esc_html_e('All clients', 'timegrow'); ?></option>
                    <?php foreach ($groups as $client_id => $group) : ?>
                        <option value="<?php echo esc_attr($client_id); ?>" <?php selected($selected_client, (string) $client_id); ?>><?php echo esc_html($group['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Pick a client to list only their projects.', 'timegrow'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="project_id">Project <span class="required">*</span></label></th>
            <td>
                <select id="project_id" name="project_id" class="large-text timegrow-project-picker" required>
                    <option value=""><?php esc_html_e('Select a Project', 'timegrow'); ?></option>
                    <?php foreach ($groups as $client_id => $group) : ?>
                        <optgroup label="<?php echo esc_attr($group['name']); ?>" data-client="<?php echo esc_attr($client_id); ?>">
                            <?php foreach ($group['projects'] as $project) : ?>
                                <?php $inactive = (int) ($project->status ?? self::ACTIVE_STATUS) !== self::ACTIVE_STATUS; ?>
                                <option value="<?php echo esc_attr($project->ID); ?>" data-client="<?php echo esc_attr($client_id); ?>" <?php selected((int) $selected_id, (int) $project->ID); ?>>
                                    <?php echo esc_html($project->name . ($inactive ? ' ' . __('(not active)', 'timegrow') : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php
    }
}
