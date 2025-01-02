<?php
/**
 * Plugin Name: User Activity Tracker
 * Description: Tracks user activities (e.g., pages viewed, time spent) and provides insights and reports to admins.
 * Version: 1.0
 * Author: Your Name
 * License: GPL2
 */

// Enqueue Chart.js library
function uat_enqueue_chartjs() {
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
}
add_action('admin_enqueue_scripts', 'uat_enqueue_chartjs');

// Enqueue Font Awesome for Icons
function uat_enqueue_font_awesome() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css', array(), null);
}
add_action('admin_enqueue_scripts', 'uat_enqueue_font_awesome');

// Custom Styles for Admin Page
function uat_custom_admin_styles() {
    echo '<style>
        .wrap h2 { font-size: 24px; font-weight: bold; }
        .widefat th, .widefat td { padding: 10px; }
        .button { margin-top: 10px; }
        .chart-container { width: 80%; margin: 0 auto; }
    </style>';
}
add_action('admin_head', 'uat_custom_admin_styles');

// Add the User Activity page to the admin menu
function uat_add_user_activity_page() {
    add_menu_page(
        'User Activity Tracker',       // Page Title
        'User Activity',               // Menu Title
        'manage_options',              // Capability
        'user-activity-tracker',       // Menu Slug
        'uat_display_user_activity',   // Callback Function
        'dashicons-chart-bar',         // Icon URL
        6                              // Position
    );
}
add_action('admin_menu', 'uat_add_user_activity_page');

// Display the User Activity Summary page
function uat_display_user_activity() {
    global $wpdb;

    // Fetch all users
    $users = get_users();

    // Begin output
    echo '<div class="wrap"><h2>User Activity Summary</h2>';

    echo '<table class="widefat fixed" cellspacing="0">
        <thead>
            <tr>
                <th>User</th>
                <th>Total Time Spent (Seconds)</th>
                <th>Most Visited Page</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($users as $user) {
        // Get the total time spent by the user and the most visited page
        $total_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(time_spent) FROM {$wpdb->prefix}user_activity WHERE user_id = %d",
            $user->ID
        ));

        // Get most visited page for this user
        $most_visited = $wpdb->get_row($wpdb->prepare(
            "SELECT page_url, COUNT(page_url) AS count FROM {$wpdb->prefix}user_activity WHERE user_id = %d GROUP BY page_url ORDER BY count DESC LIMIT 1",
            $user->ID
        ));

        // Display user data
        echo '<tr>
            <td>' . esc_html($user->display_name) . '</td>
            <td>' . esc_html($total_time) . '</td>
            <td>' . (isset($most_visited->page_url) ? esc_html($most_visited->page_url) : 'N/A') . '</td>
            <td><a href="' . admin_url('admin.php?page=user-activity-details&user_id=' . $user->ID) . '" class="button">Details</a></td>
        </tr>';
    }

    echo '</tbody></table></div>';
}

// Show User Activity Details Page
function uat_display_user_activity_details() {
    if (!isset($_GET['user_id'])) {
        return;
    }

    $user_id = intval($_GET['user_id']);
    global $wpdb;

    // Get activity data for the user
    $activity_data = $wpdb->get_results($wpdb->prepare(
        "SELECT page_url, SUM(time_spent) AS total_time FROM {$wpdb->prefix}user_activity WHERE user_id = %d GROUP BY page_url ORDER BY total_time DESC",
        $user_id
    ));

    // Get the user's info
    $user_info = get_user_by('ID', $user_id);

    // Begin output
    echo '<div class="wrap"><h2>Activity Details for ' . esc_html($user_info->display_name) . '</h2>';

    // Prepare data for Chart.js
    $page_urls = array();
    $time_spent = array();

    foreach ($activity_data as $row) {
        $page_urls[] = $row->page_url;
        $time_spent[] = $row->total_time;
    }

    // Chart.js data
    echo '<canvas id="userActivityChart"></canvas>';
    echo '<script>
        var ctx = document.getElementById("userActivityChart").getContext("2d");
        var chart = new Chart(ctx, {
            type: "bar",
            data: {
                labels: ' . json_encode($page_urls) . ',
                datasets: [{
                    label: "Time Spent (Seconds)",
                    data: ' . json_encode($time_spent) . ',
                    backgroundColor: "rgba(75, 192, 192, 0.2)",
                    borderColor: "rgba(75, 192, 192, 1)",
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>';

    // Display the most visited pages/posts
    echo '<h3>Most Visited Pages</h3>';
    echo '<ul>';
    foreach ($activity_data as $row) {
        echo '<li>' . esc_html($row->page_url) . ' - ' . esc_html($row->total_time) . ' seconds</li>';
    }
    echo '</ul>';

    echo '</div>';
}

// Add sub-menu page for User Activity Details
function uat_add_user_activity_details_page() {
    add_submenu_page(
        'user-activity-tracker',
        'User Activity Details',
        'Activity Details',
        'manage_options',
        'user-activity-details',
        'uat_display_user_activity_details'
    );
}
add_action('admin_menu', 'uat_add_user_activity_details_page');

// Register a custom database table to store user activity data
function uat_create_activity_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'user_activity';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            page_url varchar(255) NOT NULL,
            time_spent int NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'uat_create_activity_table');

// Hook into page view and track user activity
function uat_track_user_activity() {
    if (is_user_logged_in()) {
        global $wpdb;
        $user_id = get_current_user_id();
        $page_url = $_SERVER['REQUEST_URI']; // Current page URL
        $time_spent = rand(30, 300); // Placeholder for time spent (in seconds)

        // Insert activity data into the database
        $wpdb->insert(
            $wpdb->prefix . 'user_activity',
            array(
                'user_id' => $user_id,
                'page_url' => $page_url,
                'time_spent' => $time_spent,
            )
        );
    }
}
add_action('wp_footer', 'uat_track_user_activity');
