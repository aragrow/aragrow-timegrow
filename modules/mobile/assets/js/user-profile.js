/**
 * TimeGrow Mobile User Profile Scripts
 *
 * Handles PIN generation and management in user profile pages
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Generate PIN button
        $('#timegrow-generate-new-pin').on('click', function(e) {
            e.preventDefault();
            generatePIN();
        });

        // Disable mobile access button
        $('#timegrow-disable-mobile-access').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to disable mobile access? The user will no longer be able to login via mobile.')) {
                disableMobileAccess();
            }
        });

        // Unlock account button
        $('#timegrow-unlock-account').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to unlock this account?')) {
                unlockAccount();
            }
        });
    });

    /**
     * Generate a random 6-character alphanumeric PIN and display it
     */
    function generatePIN() {
        console.log('generatePIN() called');

        // Generate random 6-character alphanumeric PIN
        const chars = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Removed I and O to avoid confusion
        let pin = '';
        for (let i = 0; i < 6; i++) {
            pin += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        console.log('Generated PIN:', pin);

        // Get user ID from the page
        var userId = $('#user_id').val() || timegrowMobileProfile.currentUserId;
        console.log('User ID:', userId);
        console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'UNDEFINED!');

        // Show loading state
        var $btn = $('#timegrow-generate-new-pin');
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Generating...');

        // Save PIN via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'timegrow_generate_pin',
                user_id: userId,
                pin: pin,
                nonce: $('#timegrow_mobile_pin_nonce').val()
            },
            success: function(response) {
                console.log('AJAX Success:', response);
                if (response.success) {
                    // Show success banner with PIN
                    showPINBanner(pin, response.data.phone);

                    // Update button text
                    $btn.text('Generate New PIN');

                    // Update status display after showing banner
                    setTimeout(function() {
                        updatePINStatus(true);
                    }, 2000);
                } else {
                    console.error('Server returned error:', response);
                    alert('Error: ' + (response.data.message || 'Failed to generate PIN'));
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr);
                alert('Error: Failed to generate PIN. Please try again.');
                $btn.prop('disabled', false).text(originalText);
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Show PIN generated banner as a modal popup
     */
    function showPINBanner(pin, phone) {
        // Remove any existing modal
        $('#timegrow-pin-modal-overlay').remove();

        // Create SMS line if phone exists
        var smsLine = '';
        if (phone) {
            smsLine = '<p style="margin: 15px 0 5px 0; color: #555; font-size: 13px; text-align: center;"><span class="dashicons dashicons-smartphone" style="vertical-align: middle;"></span> An SMS notification has been sent to <strong>' + phone + '</strong></p>';
        }

        // Create modal overlay
        var modal = $('<div id="timegrow-pin-modal-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 100000; display: flex; align-items: center; justify-content: center; animation: fadeIn 0.3s;">' +
            '<div id="timegrow-pin-modal" style="background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); max-width: 600px; width: 90%; max-height: 90vh; overflow: auto; animation: slideUp 0.3s;">' +
                '<div style="padding: 30px;">' +
                    '<div style="text-align: center; margin-bottom: 20px;">' +
                        '<span class="dashicons dashicons-yes-alt" style="font-size: 60px; color: #46b450; width: 60px; height: 60px;"></span>' +
                        '<h2 style="margin: 15px 0 10px 0; font-size: 24px; color: #333;">Mobile PIN Successfully Generated!</h2>' +
                    '</div>' +
                    '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; text-align: center; margin: 20px 0;">' +
                        '<p style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9; text-transform: uppercase; letter-spacing: 1px;">6-Character PIN</p>' +
                        '<div style="font-size: 48px; font-weight: bold; letter-spacing: 15px; font-family: monospace; user-select: all;">' + pin + '</div>' +
                    '</div>' +
                    '<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px 0;">' +
                        '<p style="margin: 0; color: #856404; font-weight: 600; text-align: center;"><span class="dashicons dashicons-warning" style="color: #ffc107; vertical-align: middle;"></span> Important: Save this PIN now. It will not be shown again.</p>' +
                    '</div>' +
                    smsLine +
                    '<p style="margin: 15px 0 5px 0; color: #555; font-size: 13px; text-align: center;">' +
                        '<span class="dashicons dashicons-admin-links" style="vertical-align: middle;"></span> Mobile Login URL:<br>' +
                        '<strong><a href="' + timegrowMobileProfile.mobileLoginUrl + '" target="_blank" style="color: #667eea; text-decoration: none;">' + timegrowMobileProfile.mobileLoginUrl + '</a></strong>' +
                    '</p>' +
                    '<div style="text-align: center; margin-top: 25px;">' +
                        '<button type="button" id="close-pin-modal" style="background: #667eea; color: white; border: none; padding: 12px 30px; font-size: 16px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: background 0.2s;">Got It</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>');

        // Add CSS animations
        var style = $('<style>' +
            '@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }' +
            '@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }' +
            '#timegrow-pin-modal-overlay:hover { cursor: pointer; }' +
            '#timegrow-pin-modal { cursor: default; }' +
            '#close-pin-modal:hover { background: #5568d3; }' +
        '</style>');

        $('head').append(style);

        // Add modal to body
        $('body').append(modal);

        // Close modal on button click
        $('#close-pin-modal').on('click', function() {
            $('#timegrow-pin-modal-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        });

        // Close modal when clicking outside
        $('#timegrow-pin-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });

        // Close modal on Escape key
        $(document).on('keydown.pinmodal', function(e) {
            if (e.key === 'Escape') {
                $('#timegrow-pin-modal-overlay').fadeOut(300, function() {
                    $(this).remove();
                });
                $(document).off('keydown.pinmodal');
            }
        });
    }

    /**
     * Update PIN status display
     */
    function updatePINStatus(isActive) {
        // Update the status banner to show PIN is active
        var statusHtml = '<div style="background: linear-gradient(135deg, #46b450 0%, #399942 100%); color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(70, 180, 80, 0.2);">' +
            '<div style="display: flex; align-items: center; gap: 12px;">' +
                '<span class="dashicons dashicons-yes-alt" style="font-size: 24px; width: 24px; height: 24px;"></span>' +
                '<div style="flex-grow: 1;">' +
                    '<p style="margin: 0; font-size: 15px; font-weight: 600;">Mobile PIN is Active</p>' +
                    '<p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;">PIN was just generated</p>' +
                '</div>' +
            '</div>' +
        '</div>';

        // Replace or insert the status banner
        var $section = $('#timegrow-mobile-pin-section');
        var $existingStatus = $section.find('> div:first-child');

        if ($existingStatus.length > 0) {
            $existingStatus.replaceWith(statusHtml);
        } else {
            $section.prepend(statusHtml);
        }

        // Update button text
        $('#timegrow-generate-new-pin').text('Generate New PIN');
    }

    /**
     * Disable mobile access
     */
    function disableMobileAccess() {
        $('#timegrow_mobile_action').val('disable');
        $('#timegrow_new_pin').val('');

        // Submit the form
        $('form#your-profile').submit();
    }

    /**
     * Unlock account
     */
    function unlockAccount() {
        $('#timegrow_mobile_action').val('unlock');

        // Submit the form
        $('form#your-profile').submit();
    }

})(jQuery);
