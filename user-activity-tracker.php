<?php
/**
 * Plugin Name: User Activity Tracker
 * Description: Tracks user activities (pages viewed, time spent) and provides insights to admins with charts and detailed reports.
 * Version: 1.0
 * Author: Your Name
 */

// Enqueue Chart.js Library for Visualizations
function uat_enqueue_chartjs() {
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
    wp_enqueue_style('uat-custom-styles', plugin_dir_url(__FILE__) . 'styles.css');
}
add_action('admin_enqueue_scripts', 'uat_enqueue_chartjs');

// Add Menu Page for User Activity Tracker
function uat_add_user_activity_page() {
    add_menu_page(
        'User Activity Tracker',
        'User Activity',
        'manage_options',
        'user-activity-tracker',
        'uat_display_user_activity',
        'dashicons-chart-bar',
        6
    );
}
add_action('admin_menu', 'uat_add_user_activity_page');

// Display User Activity Overview
function uat_display_user_activity() {
    global $wpdb;
    $users = get_users();

    echo '<div class="wrap"><h1>User Activity Overview</h1>';

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
        $total_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(time_spent) FROM {$wpdb->prefix}user_activity WHERE user_id = %d",
            $user->ID
        ));

        $most_visited = $wpdb->get_row($wpdb->prepare(
            "SELECT page_url, COUNT(page_url) AS count FROM {$wpdb->prefix}user_activity WHERE user_id = %d GROUP BY page_url ORDER BY count DESC LIMIT 1",
            $user->ID
        ));

        echo '<tr>
            <td>' . esc_html($user->display_name) . '</td>
            <td>' . esc_html($total_time) . '</td>
            <td>' . (isset($most_visited->page_url) ? esc_html($most_visited->page_url) : 'N/A') . '</td>
            <td><a href="' . admin_url('admin.php?page=user-activity-details&user_id=' . $user->ID) . '" class="button">View Details</a></td>
        </tr>';
    }

    echo '</tbody></table></div>';
}

// Display User Activity Details
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

    $user_info = get_user_by('ID', $user_id);
    echo '<div class="wrap"><h1>Activity Details for ' . esc_html($user_info->display_name) . '</h1>';

    // Prepare data for the chart
    $page_urls = [];
    $time_spent = [];

    foreach ($activity_data as $row) {
        $page_urls[] = $row->page_url;
        $time_spent[] = $row->total_time;
    }

    // Chart.js data for the activity
    echo '<div class="chart-container" style="max-width: 800px; margin: 0 auto; padding-bottom: 30px;">
            <canvas id="userActivityChart"></canvas>
        </div>';

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
                responsive: true,
                plugins: {
                    legend: {
                        position: "top",
                    },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                return tooltipItem.raw + " seconds";
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 100,
                        }
                    }
                }
            }
        });
    </script>';

    echo '<h3>Most Visited Pages</h3>';
    echo '<ul>';
    foreach ($activity_data as $row) {
        echo '<li>' . esc_html($row->page_url) . ' - ' . esc_html($row->total_time) . ' seconds</li>';
    }
    echo '</ul>';

    echo '</div>';
}

// Add Activity Details Submenu
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

// Register User Activity Table
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

// Track User Activity (Page Views, Time Spent)
function uat_track_user_activity() {
    if (is_user_logged_in()) {
        global $wpdb;
        $user_id = get_current_user_id();
        $page_url = $_SERVER['REQUEST_URI']; // Current page URL
        $time_spent = rand(30, 300); // Random time spent between 30 to 300 seconds for demo

        // Insert user activity into the database
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