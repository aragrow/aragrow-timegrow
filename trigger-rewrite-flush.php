<?php
/**
 * Trigger Rewrite Rules Flush
 *
 * Visit this URL to trigger a rewrite rules flush:
 * http://localhost:10003/wp-content/plugins/aragrow-timegrow/trigger-rewrite-flush.php
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;

    // Set the flag that will trigger flush on next admin page load
    update_option('timegrow_mobile_flush_rewrite_rules', '1');

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Rewrite Rules Flush Triggered</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                max-width: 600px;
                margin: 100px auto;
                padding: 20px;
                line-height: 1.6;
            }
            .success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .next-steps {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                color: #0c5460;
                padding: 15px;
                border-radius: 4px;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
            }
            a {
                color: #007bff;
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            code {
                background: #f4f4f4;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
            }
        </style>
    </head>
    <body>
        <h1>✓ Rewrite Rules Flush Triggered!</h1>

        <div class="success">
            <p><strong>Success!</strong> The rewrite flush flag has been set.</p>
        </div>

        <div class="next-steps">
            <p><strong>Next Steps:</strong></p>
            <ol>
                <li>The rewrite rules will be automatically flushed on your next visit to any WordPress admin page.</li>
                <li>After that, you can access the mobile login at:<br>
                    <a href="<?php echo esc_url(home_url('/mobile-login')); ?>"><?php echo esc_html(home_url('/mobile-login')); ?></a>
                </li>
            </ol>
        </div>

        <p style="margin-top: 30px; color: #666;">
            <small>You can delete this file after the flush is complete: <code>trigger-rewrite-flush.php</code></small>
        </p>
    </body>
    </html>
    <?php
} else {
    echo "Error: Could not load WordPress. Please check the file path.";
}
