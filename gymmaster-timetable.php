<?php
/**
 * Plugin Name: Gym Timetable TV Display
 * Description: Displays gym class timetable for TV screens with auto-refresh
 * Version: 1.0.0
 * Text Domain: gym-timetable-tv
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('GTT_VERSION', '1.0.0');
define('GTT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GTT_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GTT_PLUGIN_DIR . 'includes/class-api-handler.php';
require_once GTT_PLUGIN_DIR . 'includes/class-timetable-renderer.php';
require_once GTT_PLUGIN_DIR . 'admin/settings-page.php';

/**
 * Initialize the plugin
 */
function gtt_init() {
    add_shortcode('gym_timetable_tv', 'gtt_render_timetable');
}
add_action('init', 'gtt_init');

/**
 * Enqueue frontend styles and scripts
 */
function gtt_enqueue_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gym_timetable_tv')) {
        wp_enqueue_style(
            'gtt-tv-display',
            GTT_PLUGIN_URL . 'assets/css/tv-display.css',
            array(),
            GTT_VERSION
        );
        
        wp_enqueue_script(
            'gtt-auto-refresh',
            GTT_PLUGIN_URL . 'assets/js/auto-refresh.js',
            array('jquery'),
            GTT_VERSION,
            true
        );
        
        $refresh_interval = get_option('gtt_refresh_interval', 5) * 60 * 1000;
        wp_localize_script('gtt-auto-refresh', 'gttData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'refreshInterval' => $refresh_interval,
            'nonce' => wp_create_nonce('gtt_refresh_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'gtt_enqueue_assets');

/**
 * Shortcode callback to render the timetable
 */
function gtt_render_timetable($atts) {
    remove_filter('the_content', 'wpautop');
    $renderer = new GTT_Timetable_Renderer();
    return $renderer->render();
}

/**
 * AJAX handler for auto-refresh
 */
function gtt_ajax_refresh_timetable() {
    check_ajax_referer('gtt_refresh_nonce', 'nonce');

    remove_filter('the_content', 'wpautop');
    
    $renderer = new GTT_Timetable_Renderer();
    echo $renderer->render_classes_only();
    
    wp_die();
}
add_action('wp_ajax_gtt_refresh_timetable', 'gtt_ajax_refresh_timetable');
add_action('wp_ajax_nopriv_gtt_refresh_timetable', 'gtt_ajax_refresh_timetable');

/**
 * Activation hook
 */
function gtt_activate() {
    add_option('gtt_api_endpoint', '');
    add_option('gtt_api_key', '');
    add_option('gtt_refresh_interval', 5);
    add_option('gtt_timezone', 'Pacific/Auckland');
    add_option('gtt_use_sample_data', 1);
}
register_activation_hook(__FILE__, 'gtt_activate');

/**
 * Deactivation hook
 */
function gtt_deactivate() {
    delete_transient('gtt_classes_cache');
}
register_deactivation_hook(__FILE__, 'gtt_deactivate');

/**
 * Register custom page template
 */
function gtt_register_template() {
	if ( ! function_exists('register_block_template') ) {
        return;
    }

    register_block_template(
        'gymmaster-timetable//fullscreen-blank',
        array(
            'title'       => __('Fullscreen Blank', 'gymmaster-timetable'),
            'description' => __('Full width page with no header or footer.', 'gymmaster-timetable'),
            'content'     => '
                <!-- wp:group {"tagName":"main","layout":{"type":"full-width"}} -->
                <main class="wp-block-group" style="min-height:100vh;margin:0;padding:0">

                    <!-- wp:post-content {"layout":{"type":"full-width"}} /-->

                </main>
                <!-- /wp:group -->
            ',
            'post_types'  => array( 'page' ),
        )
    );
}
add_action( 'init', 'gtt_register_template' );