jQuery(document).ready(function($) {
    var startTime = Date.now();
    var pageUrl = window.location.href;

    // Function to track time spent when the user leaves the page
    function trackUserActivity() {
        var timeSpent = Math.floor((Date.now() - startTime) / 1000); // Time spent in seconds

        // Send the data to the server via AJAX
        $.ajax({
            url: uat_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'uar_save_activity',
                page_url: pageUrl,
                time_spent: timeSpent
            },
            success: function(response) {
                // You can handle the response here if needed
            }
        });
    }

    // Track when the user leaves the page (unload, close tab, etc.)
    $(window).on('beforeunload', trackUserActivity);
});