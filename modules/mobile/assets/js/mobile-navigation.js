/**
 * TimeGrow Mobile Navigation
 *
 * Simplified hamburger menu navigation for mobile users
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Check if user has mobile-only mode
        if (typeof timegrowMobile === 'undefined') {
            return;
        }

        // Create hamburger menu
        createHamburgerMenu();

        // Handle menu toggle
        $(document).on('click', '.timegrow-mobile-hamburger', function(e) {
            e.preventDefault();
            toggleMenu();
        });

        // Close menu when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.timegrow-mobile-header, .timegrow-mobile-menu').length) {
                closeMenu();
            }
        });

        // Close menu when clicking a menu item
        $(document).on('click', '.timegrow-mobile-menu-item', function() {
            closeMenu();
        });
    });

    /**
     * The header and slide-out menu: the person's pages (time entry, expenses and receipts) and Log out.
     */
    function createHamburgerMenu() {
        const currentPage = new URLSearchParams(window.location.search).get('page');

        const header = $('<div class="timegrow-mobile-header"></div>');
        header.append('<button type="button" class="timegrow-mobile-hamburger" aria-label="Menu"><span class="dashicons dashicons-menu"></span></button>');
        header.append($('<span class="timegrow-mobile-title"></span>').text(timegrowMobile.title || 'TimeGrow'));

        const menu = $('<div class="timegrow-mobile-menu"></div>');
        const menuList = $('<ul class="timegrow-mobile-menu-list"></ul>');
        (timegrowMobile.items || []).forEach(function(item) {
            menuList.append(createMenuItem('admin.php?page=' + encodeURIComponent(item.page), item.icon, item.label, currentPage === item.page));
        });
        menuList.append(createMenuItem(timegrowMobile.logoutUrl, 'dashicons-exit', 'Log out', false));
        menu.append(menuList);

        $('body').prepend(menu);
        $('body').prepend(header);
        $('body').css('padding-top', '50px'); // room for the fixed header
    }

    /** One menu item (labels are text, never markup). */
    function createMenuItem(url, icon, label, isActive) {
        return $('<li>')
            .addClass('timegrow-mobile-menu-item' + (isActive ? ' active' : ''))
            .append($('<a>', { href: url })
                .append($('<span>', { 'class': 'dashicons ' + icon, 'aria-hidden': 'true' }))
                .append($('<span>').text(label)));
    }

    /**
     * Toggle menu open/closed
     */
    function toggleMenu() {
        const menu = $('.timegrow-mobile-menu');
        const isOpen = menu.hasClass('open');

        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    /**
     * Open menu
     */
    function openMenu() {
        $('.timegrow-mobile-menu').addClass('open');
        $('.timegrow-mobile-hamburger').addClass('active');
    }

    /**
     * Close menu
     */
    function closeMenu() {
        $('.timegrow-mobile-menu').removeClass('open');
        $('.timegrow-mobile-hamburger').removeClass('active');
    }

})(jQuery);
