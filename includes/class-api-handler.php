<?php
/**
 * API Handler Class
 * Handles API requests and data caching
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GTT_API_Handler {
    
    /**
     * Sample data for testing
     */
    private static function get_sample_data() {
        $json_data = plugin_dir_path( __DIR__ ) . 'sample-data.json';
        $data =  wp_json_file_decode($json_data, ['associative' => true]);
        return $data;
    }
    
    /**
     * Get this week's classes
     */
    public static function get_todays_classes() {
        $use_sample = get_option('gtt_use_sample_data', 1);
        
        if ($use_sample) {
            return self::get_sample_data();
        }

        $cache_key = 'gtt_classes_cache';
        $cached_data = get_transient($cache_key);
        
        if (false !== $cached_data) {
            return $cached_data;
        }
        
        $data = self::fetch_from_api();
        
        if ($data && !isset($data['error'])) {
            $cache_duration = get_option('gtt_refresh_interval', 5) * 60;
            set_transient($cache_key, $data, $cache_duration);
        }
        
        return $data;
    }
    
    /**
     * Fetch data from the API
     */
    private static function fetch_from_api() {
        $api_endpoint = get_option('gtt_api_endpoint');
        $api_key = get_option('gtt_api_key');
        
        if (empty($api_endpoint)) {
            return array(
                'result' => array(),
                'error' => 'API endpoint not configured'
            );
        }
        
        $timezone = get_option('gtt_timezone', 'Pacific/Auckland');
        date_default_timezone_set($timezone);
        $today = date('Y-m-d');
        
        $url = add_query_arg(array(
            'date' => $today,
            'api_key' => $api_key
        ), $api_endpoint);
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'result' => array(),
                'error' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'result' => array(),
                'error' => 'Invalid JSON response from API'
            );
        }
        
        return $data;
    }
    
    /**
     * Clear the cache manually
     */
    public static function clear_cache() {
        delete_transient('gtt_classes_cache');
    }
}