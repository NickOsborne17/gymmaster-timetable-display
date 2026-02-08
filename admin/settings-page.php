<?php
/**
 * Admin Settings Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu
 */
function gtt_add_admin_menu() {
    add_options_page(
        'Gym Timetable TV Settings',
        'Gym Timetable TV',
        'manage_options',
        'gym-timetable-tv',
        'gtt_settings_page'
    );
}
add_action('admin_menu', 'gtt_add_admin_menu');

/**
 * Register settings
 */
function gtt_register_settings() {
    register_setting('gtt_settings', 'gtt_api_endpoint', array(
        'sanitize_callback' => 'esc_url_raw'
    ));
    
    register_setting('gtt_settings', 'gtt_api_key', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
    
    register_setting('gtt_settings', 'gtt_refresh_interval', array(
        'sanitize_callback' => 'absint',
        'default' => 5
    ));
    
    register_setting('gtt_settings', 'gtt_timezone', array(
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'Pacific/Auckland'
    ));
    
    register_setting('gtt_settings', 'gtt_use_sample_data', array(
        'sanitize_callback' => 'absint',
        'default' => 1
    ));
}
add_action('admin_init', 'gtt_register_settings');

/**
 * Enqueue admin styles
 */
function gtt_enqueue_admin_styles($hook) {
    if ($hook !== 'settings_page_gym-timetable-tv') {
        return;
    }
    
    wp_enqueue_style(
        'gtt-admin-styles',
        GTT_PLUGIN_URL . 'admin/admin-styles.css',
        array(),
        GTT_VERSION
    );
}
add_action('admin_enqueue_scripts', 'gtt_enqueue_admin_styles');

/**
 * Settings page HTML
 */
function gtt_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if settings were saved
    if (isset($_GET['settings-updated'])) {
        add_settings_error(
            'gtt_messages',
            'gtt_message',
            'Settings Saved',
            'updated'
        );
        
        // Clear cache when settings are updated
        delete_transient('gtt_classes_cache');
    }
    
    settings_errors('gtt_messages');
    
    $use_sample_data = get_option('gtt_use_sample_data', 1);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="gtt-admin-container">
            <div class="gtt-settings-panel">
                <form action="options.php" method="post">
                    <?php
                    settings_fields('gtt_settings');
                    ?>
                    
                    <h2>API Configuration</h2>
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="gtt_use_sample_data">Data Source</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="gtt_use_sample_data" 
                                           id="gtt_use_sample_data" 
                                           value="1" 
                                           <?php checked(1, $use_sample_data); ?>>
                                    Use sample data (for testing)
                                </label>
                                <p class="description">
                                    When enabled, the plugin will use built-in sample data instead of calling the API.
                                    Disable this when you're ready to use your real API.
                                </p>
                            </td>
                        </tr>
                        
                        <tr class="<?php echo $use_sample_data ? 'gtt-disabled-row' : ''; ?>">
                            <th scope="row">
                                <label for="gtt_api_endpoint">API Endpoint URL</label>
                            </th>
                            <td>
                                <input type="url" 
                                       name="gtt_api_endpoint" 
                                       id="gtt_api_endpoint" 
                                       value="<?php echo esc_attr(get_option('gtt_api_endpoint')); ?>" 
                                       class="regular-text"
                                       placeholder="https://api.example.com/classes"
                                       <?php echo $use_sample_data ? 'disabled' : ''; ?>>
                                <p class="description">
                                    The full URL to your gym's class timetable API endpoint.
                                </p>
                            </td>
                        </tr>
                        
                        <tr class="<?php echo $use_sample_data ? 'gtt-disabled-row' : ''; ?>">
                            <th scope="row">
                                <label for="gtt_api_key">API Key</label>
                            </th>
                            <td>
                                <input type="password" 
                                       name="gtt_api_key" 
                                       id="gtt_api_key" 
                                       value="<?php echo esc_attr(get_option('gtt_api_key')); ?>" 
                                       class="regular-text"
                                       placeholder="Enter your API key"
                                       <?php echo $use_sample_data ? 'disabled' : ''; ?>>
                                <p class="description">
                                    Your API authentication key. This is stored securely in the database.
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>Display Settings</h2>
                    
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="gtt_refresh_interval">Auto-refresh Interval</label>
                            </th>
                            <td>
                                <select name="gtt_refresh_interval" id="gtt_refresh_interval">
                                    <option value="1" <?php selected(get_option('gtt_refresh_interval'), 1); ?>>1 minute</option>
                                    <option value="2" <?php selected(get_option('gtt_refresh_interval'), 2); ?>>2 minutes</option>
                                    <option value="5" <?php selected(get_option('gtt_refresh_interval'), 5); ?>>5 minutes</option>
                                    <option value="10" <?php selected(get_option('gtt_refresh_interval'), 10); ?>>10 minutes</option>
                                    <option value="15" <?php selected(get_option('gtt_refresh_interval'), 15); ?>>15 minutes</option>
                                    <option value="30" <?php selected(get_option('gtt_refresh_interval'), 30); ?>>30 minutes</option>
                                </select>
                                <p class="description">
                                    How often the timetable should automatically refresh with new data.
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="gtt_timezone">Timezone</label>
                            </th>
                            <td>
                                <?php
                                $current_timezone = get_option('gtt_timezone', 'Pacific/Auckland');
                                $timezones = array(
                                    'Pacific/Auckland' => 'Pacific/Auckland (NZ)',
                                    'Pacific/Chatham' => 'Pacific/Chatham (NZ)',
                                    'Australia/Sydney' => 'Australia/Sydney',
                                    'Australia/Melbourne' => 'Australia/Melbourne',
                                    'Australia/Brisbane' => 'Australia/Brisbane',
                                    'Australia/Perth' => 'Australia/Perth',
                                );
                                ?>
                                <select name="gtt_timezone" id="gtt_timezone">
                                    <?php foreach ($timezones as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($current_timezone, $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    The timezone for displaying class times.
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>
            
            <div class="gtt-info-panel">
                <div class="gtt-info-box">
                    <h3>Usage Instructions</h3>
                    <p>Add this shortcode to any page or post:</p>
                    <code>[gym_timetable_tv]</code>
                    
                    <p style="margin-top: 15px;"><strong>For TV Display:</strong></p>
                    <ol>
                        <li>Create a new page in WordPress</li>
                        <li>Add the shortcode above</li>
                        <li>Open the page in full-screen mode on your TV browser</li>
                        <li>The timetable will auto-refresh every <?php echo esc_html(get_option('gtt_refresh_interval', 5)); ?> minutes</li>
                    </ol>
                </div>
                
                <div class="gtt-info-box">
                    <h3>Sample Data</h3>
                    <p>While "Use sample data" is enabled, the timetable will display test classes. This is useful for:</p>
                    <ul>
                        <li>Testing the layout and styling</li>
                        <li>Setting up your TV display</li>
                        <li>Training staff</li>
                    </ul>
                    <p>Uncheck the option and enter your API details when you're ready to go live.</p>
                </div>
                
                <div class="gtt-info-box">
                    <h3>Cache Management</h3>
                    <p>Data is cached for <?php echo esc_html(get_option('gtt_refresh_interval', 5)); ?> minutes to reduce API calls.</p>
                    <p>The cache is automatically cleared when you save these settings.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle API fields based on sample data checkbox
        document.getElementById('gtt_use_sample_data').addEventListener('change', function() {
            const apiFields = document.querySelectorAll('.gtt-disabled-row input');
            const rows = document.querySelectorAll('.gtt-disabled-row');
            
            if (this.checked) {
                rows.forEach(row => row.classList.add('gtt-disabled-row'));
                apiFields.forEach(field => field.disabled = true);
            } else {
                rows.forEach(row => row.classList.remove('gtt-disabled-row'));
                apiFields.forEach(field => field.disabled = false);
            }
        });
    </script>
    <?php
}