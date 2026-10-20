<?php
// includes/admin-menu.php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The TimeGrow menu: the top-level item, and the order of its submenu.
 *
 * The submenu is grouped by what people do there:
 *   Dashboard (the Nexus dashboard, opened by the TimeGrow item itself)
 *   Work:  Time Entries, Expenses, Reports
 *   Setup: Clients, Projects, Team Members, Companies, Expense Categories
 *   Settings, User Guide
 * The group names are headings drawn with CSS on the first item each person can see, so they are
 * not links. Items added by other code go before Settings. The old placeholder page and the old
 * Reports list forward to the Nexus pages. Add / edit pages keep their list's menu item highlighted.
 */
class TimeFlies_Admin_Menu {
    private static $instance;

    /** Submenu slugs (after the "timegrow-" prefix) in their groups. */
    const GROUPS = [
        ''      => ['nexus'],
        'work'  => ['time-entries-list', 'expenses-list', 'nexus-reports'],
        'setup' => ['clients-list', 'projects-list', 'team-member-list', 'companies-list', 'expense-categories'],
        'end'   => ['settings', 'user-guide'],
    ];

    /** Pages not in the menu, and the menu item that stays highlighted on them (both without the prefix). */
    const HIGHLIGHT = [
        'client-add' => 'clients-list', 'client-edit' => 'clients-list',
        'project-add' => 'projects-list', 'project-edit' => 'projects-list',
        'team-member-add' => 'team-member-list', 'team-member-edit' => 'team-member-list',
        'company-add' => 'companies-list', 'company-edit' => 'companies-list',
        'expense-add' => 'expenses-list', 'expense-edit' => 'expenses-list', 'expense-receipt-delete' => 'expenses-list',
        'expense-category-edit' => 'expense-categories',
        'time-entry-add' => 'time-entries-list', 'time-entry-edit' => 'time-entries-list',
        'nexus-clock' => 'nexus', 'nexus-manual' => 'nexus', 'nexus-expenses' => 'nexus',
        'nexus-process-time' => 'nexus', 'nexus-sync-payments' => 'nexus', 'nexus-print-mail' => 'nexus',
        'nexus-a-report' => 'nexus-reports', 'reports' => 'nexus-reports',
        'mobile-settings' => 'settings',
    ];

    /** Old pages and where they live now. */
    const MOVED = ['' => 'nexus', 'reports-list' => 'nexus-reports'];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_menu', array($this, 'order_submenu'), 1000); // after every module (and the mobile filter at 999)
        add_action('admin_init', array($this, 'redirect_moved_pages'));
        add_filter('parent_file', array($this, 'parent_file'));
        add_filter('submenu_file', array($this, 'submenu_file'), 10, 2);
        add_action('admin_head', array($this, 'menu_styles'));
    }

    public function register_admin_menu() {
        add_menu_page(
            'TimeGrow',
            'TimeGrow',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU,
            array($this, 'dashboard_page'),
            'dashicons-clock',
            25
        );
    }

    /** The old placeholder page (only reached if the redirect could not run). */
    public function dashboard_page() {
        echo '<div class="wrap"><p><a href="' . esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus')) . '">' . esc_html__('Go to the TimeGrow dashboard', 'timegrow') . '</a></p></div>';
    }

    /**
     * The submenu in its groups. Items the person can't use are skipped when choosing where a heading
     * goes; the old placeholder and the old Reports list are left out. Pure.
     *
     * @param array    $items Submenu items as WordPress keeps them: [title, capability, slug, page title, (class)].
     * @param callable $can   fn(capability) => bool.
     * @return array The items in order; the first visible item of Work, Setup and Settings has class tg-menu-work / -setup / -end.
     */
    public static function ordered(array $items, callable $can, $prefix = 'timegrow') {
        $short = function ($slug) use ($prefix) {
            return $slug === $prefix ? '' : (strpos($slug, $prefix . '-') === 0 ? substr($slug, strlen($prefix) + 1) : null);
        };
        $by_slug = [];
        $others  = [];
        foreach ($items as $item) {
            $key = $short((string) ($item[2] ?? ''));
            if ($key !== null && array_key_exists($key, self::MOVED)) {
                continue; // forwarded elsewhere
            }
            if ($key !== null && in_array($key, array_merge(...array_values(self::GROUPS)), true)) {
                $by_slug[$key] = $item;
            } else {
                $others[] = $item; // added by other code: before Settings
            }
        }
        $out = [];
        foreach (self::GROUPS as $group => $slugs) {
            if ($group === 'end') {
                $out = array_merge($out, $others);
            }
            $headed = $group === ''; // Work and Setup get a heading, Settings a divider
            foreach ($slugs as $slug) {
                if (!isset($by_slug[$slug])) {
                    continue;
                }
                $item = $by_slug[$slug];
                if ($slug === 'nexus') {
                    $item[0] = __('Dashboard', 'timegrow');
                }
                if (!$headed && $can($item[1])) {
                    $item[4] = trim(($item[4] ?? '') . ' tg-menu-' . $group);
                    $headed = true;
                }
                $out[] = $item;
            }
        }
        return $out;
    }

    public function order_submenu() {
        global $submenu;
        if (empty($submenu[TIMEGROW_PARENT_MENU])) {
            return;
        }
        $submenu[TIMEGROW_PARENT_MENU] = self::ordered($submenu[TIMEGROW_PARENT_MENU], 'current_user_can', TIMEGROW_PARENT_MENU);
    }

    /** The old placeholder and the old Reports list: forward to the Nexus pages. */
    public function redirect_moved_pages() {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        foreach (self::MOVED as $old => $new) {
            if ($page === ($old === '' ? TIMEGROW_PARENT_MENU : TIMEGROW_PARENT_MENU . '-' . $old)) {
                wp_safe_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-' . $new));
                exit;
            }
        }
    }

    private static function current_page_key() {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        return strpos($page, TIMEGROW_PARENT_MENU . '-') === 0 ? substr($page, strlen(TIMEGROW_PARENT_MENU) + 1) : null;
    }

    /** Add / edit pages (not in the menu) open the TimeGrow menu. */
    public function parent_file($parent_file) {
        $key = self::current_page_key();
        return $key !== null && isset(self::HIGHLIGHT[$key]) ? TIMEGROW_PARENT_MENU : $parent_file;
    }

    /** ...and highlight their list's item. */
    public function submenu_file($submenu_file, $parent_file) {
        $key = self::current_page_key();
        return $key !== null && isset(self::HIGHLIGHT[$key]) ? TIMEGROW_PARENT_MENU . '-' . self::HIGHLIGHT[$key] : $submenu_file;
    }

    /** The group headings, above the first item of each group. */
    public function menu_styles() {
        $work  = esc_attr__('Work', 'timegrow');
        $setup = esc_attr__('Setup', 'timegrow');
        echo '<style>
            #adminmenu .wp-submenu li.tg-menu-work::before, #adminmenu .wp-submenu li.tg-menu-setup::before {
                display: block; margin: 8px 12px 2px; padding-top: 8px; border-top: 1px solid rgba(240,246,252,.15);
                font-size: 10px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: rgba(240,246,252,.55); }
            #adminmenu .wp-submenu li.tg-menu-work::before { content: "' . $work . '"; }
            #adminmenu .wp-submenu li.tg-menu-setup::before { content: "' . $setup . '"; }
            #adminmenu li.wp-has-current-submenu .wp-submenu li.tg-menu-work::before,
            #adminmenu li.wp-has-current-submenu .wp-submenu li.tg-menu-setup::before { color: rgba(240,246,252,.6); }
            #adminmenu .wp-submenu li.tg-menu-end { margin-top: 6px; padding-top: 6px; border-top: 1px solid rgba(240,246,252,.15); }
        </style>';
    }
}

TimeFlies_Admin_Menu::get_instance();
