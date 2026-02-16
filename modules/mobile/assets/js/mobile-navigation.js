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
     * Create hamburger menu navigation
     */
    function createHamburgerMenu() {
        const currentPage = new URLSearchParams(window.location.search).get('page');

        // Create header with hamburger button
        const header = $('<div class="timegrow-mobile-header"></div>');
        header.append('<button class="timegrow-mobile-hamburger"><span class="dashicons dashicons-menu"></span></button>');
        header.append('<span class="timegrow-mobile-title">TimeGrow</span>');

        // Create slide-out menu
        const menu = $('<div class="timegrow-mobile-menu"></div>');
        const menuList = $('<ul class="timegrow-mobile-menu-list"></ul>');

        // Dashboard (Reports) - always available if user has at least one capability
        if (timegrowMobile.hasTimeTracking || timegrowMobile.hasExpenses) {
            menuList.append(createMenuItem(
                'timegrow-nexus-reports',
                'dashicons-dashboard',
                'Dashboard',
                currentPage === 'timegrow-nexus-reports'
            ));
        }

        // Time - shows if user has time tracking capability
        if (timegrowMobile.hasTimeTracking) {
            menuList.append(createMenuItem(
                'timegrow-nexus-clock',
                'dashicons-clock',
                'Time',
                currentPage === 'timegrow-nexus-clock' || currentPage === 'timegrow-nexus-manual'
            ));
        }

        // Expenses - shows if user has expenses capability
        if (timegrowMobile.hasExpenses) {
            menuList.append(createMenuItem(
                'timegrow-nexus-expenses',
                'dashicons-money-alt',
                'Expenses',
                currentPage === 'timegrow-nexus-expenses'
            ));
        }

        menu.append(menuList);

        // Append to body
        $('body').prepend(menu);
        $('body').prepend(header);

        // Add padding to body to account for fixed header
        $('body').css('padding-top', '50px');
    }

    /**
     * Create menu item
     *
     * @param {string} page Page slug
     * @param {string} icon Dashicons class
     * @param {string} label Menu label
     * @param {boolean} isActive Whether this is the active page
     * @return {jQuery} Menu item element
     */
    function createMenuItem(page, icon, label, isActive) {
        const url = 'admin.php?page=' + page;
        const activeClass = isActive ? ' active' : '';

        return $('<li>')
            .addClass('timegrow-mobile-menu-item' + activeClass)
            .html(
                '<a href="' + url + '">' +
                '<span class="dashicons ' + icon + '"></span>' +
                '<span>' + label + '</span>' +
                '</a>'
            );
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
