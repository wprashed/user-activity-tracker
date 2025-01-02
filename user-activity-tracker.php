<?php
/**
 * Plugin Name: Real-Time User Activity Tracker
 * Description: Tracks real-time user activity and time spent on pages/posts.
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Enqueue Scripts for Tracking
function uat_enqueue_scripts() {
    wp_enqueue_script( 'user-activity-tracker', plugin_dir_url( __FILE__ ) . 'user-activity-tracker.js', array( 'jquery' ), null, true );

    // Pass AJAX URL to JavaScript
    wp_localize_script( 'user-activity-tracker', 'uat_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ));
}

add_action( 'wp_enqueue_scripts', 'uat_enqueue_scripts' );

// Handle AJAX Request to Save Time
function uat_save_user_activity() {
    if ( is_user_logged_in() ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $page_url = sanitize_text_field( $_POST['page_url'] );
        $time_spent = intval( $_POST['time_spent'] ); // Time in seconds

        // Insert or update user activity
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_activity WHERE user_id = %d AND page_url = %s",
            $user_id,
            $page_url
        ));

        if ( $existing > 0 ) {
            // Update time spent for the page
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}user_activity SET time_spent = time_spent + %d WHERE user_id = %d AND page_url = %s",
                $time_spent,
                $user_id,
                $page_url
            ));
        } else {
            // Insert new record for the page visit
            $wpdb->insert(
                "{$wpdb->prefix}user_activity",
                array(
                    'user_id'   => $user_id,
                    'page_url'  => $page_url,
                    'time_spent' => $time_spent,
                ),
                array( '%d', '%s', '%d' )
            );
        }
    }
    wp_die();
}

add_action( 'wp_ajax_uar_save_activity', 'uat_save_user_activity' );
add_action( 'wp_ajax_nopriv_uar_save_activity', 'uat_save_user_activity' );

// Create Database Table for User Activity
function uat_create_activity_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_activity';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        page_url varchar(255) NOT NULL,
        time_spent int NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta( $sql );
}

register_activation_hook( __FILE__, 'uat_create_activity_table' );

// Add the "User Activity" menu item to the WordPress admin
function uat_add_admin_menu() {
    add_menu_page(
        'User Activity',          // Page title
        'User Activity',          // Menu title
        'manage_options',         // Capability
        'user-activity',          // Menu slug
        'uat_display_user_activity' // Callback function to display the content
    );
}

add_action('admin_menu', 'uat_add_admin_menu');

// Display User Activity on the Admin Page
function uat_display_user_activity() {
    global $wpdb;

    // Fetch all users from the database
    $users = get_users();

    // If the table doesn't exist, create it
    if ( ! $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}user_activity'") ) {
        echo '<div class="error"><p>The user activity table is not found. Please check the plugin installation.</p></div>';
        return;
    }

    // Fetch activity for each user
    echo '<div class="wrap"><h2>User Activity</h2>';
    echo '<table class="widefat fixed" cellspacing="0">
        <thead>
            <tr>
                <th>User</th>
                <th>Page URL</th>
                <th>Time Spent (Seconds)</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($users as $user) {
        // Get activity for the specific user
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}user_activity WHERE user_id = %d ORDER BY timestamp DESC",
                $user->ID
            )
        );

        if ($results) {
            foreach ($results as $row) {
                echo '<tr>
                    <td>' . esc_html($user->display_name) . '</td>
                    <td>' . esc_html($row->page_url) . '</td>
                    <td>' . esc_html($row->time_spent) . ' seconds</td>
                    <td>' . esc_html($row->timestamp) . '</td>
                </tr>';
            }
        }
    }

    echo '</tbody></table></div>';
}
