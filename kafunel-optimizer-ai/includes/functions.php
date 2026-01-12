<?php
/**
 * Kafunel Optimizer AI - Utility Functions
 * 
 * Contains utility functions for the Kafunel Optimizer AI plugin.
 * 
 * @package Kafunel_Optimizer_AI
 * @subpackage Includes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if an image format is supported for input
 * 
 * @param string $format Image format
 * @return bool True if supported, false otherwise
 */
function kafunel_is_input_format_supported($format) {
    if (!class_exists('Kafunel_Optimizer_Engine')) {
        include_once KAFUNEL_PLUGIN_PATH . 'classes/class-kafunel-optimizer-engine.php';
    }
    
    $engine = new Kafunel_Optimizer_Engine();
    return $engine->supports_input_format($format);
}

/**
 * Check if an image format is supported for output
 * 
 * @param string $format Image format
 * @return bool True if supported, false otherwise
 */
function kafunel_is_output_format_supported($format) {
    if (!class_exists('Kafunel_Optimizer_Engine')) {
        include_once KAFUNEL_PLUGIN_PATH . 'classes/class-kafunel-optimizer-engine.php';
    }
    
    $engine = new Kafunel_Optimizer_Engine();
    return $engine->supports_output_format($format);
}

/**
 * Get formatted file size
 * 
 * @param int $size File size in bytes
 * @return string Formatted file size
 */
function kafunel_format_file_size($size) {
    if ($size > 1048576) {
        return round($size / 1048576, 2) . ' MB';
    } elseif ($size > 1024) {
        return round($size / 1024, 2) . ' KB';
    } else {
        return $size . ' B';
    }
}

/**
 * Get savings percentage between two sizes
 * 
 * @param int $original_size Original file size
 * @param int $new_size New file size
 * @return float Savings percentage
 */
function kafunel_calculate_savings_percentage($original_size, $new_size) {
    if ($original_size <= 0) {
        return 0;
    }
    
    return round((($original_size - $new_size) / $original_size) * 100, 2);
}

/**
 * Get optimization status for an attachment
 * 
 * @param int $attachment_id Attachment ID
 * @return array Optimization status information
 */
function kafunel_get_optimization_status($attachment_id) {
    $optimized = get_post_meta($attachment_id, '_kafunel_optimized', true);
    
    if (!$optimized) {
        return array(
            'status' => 'not_optimized',
            'message' => __('Not optimized yet', 'kafunel-optimizer-ai')
        );
    }
    
    $optimization_data = wp_get_attachment_metadata($attachment_id);
    
    if (isset($optimization_data['kafunel_optimization'])) {
        $opt_info = $optimization_data['kafunel_optimization'];
        return array(
            'status' => 'optimized',
            'original_size' => isset($opt_info['original_size']) ? $opt_info['original_size'] : 0,
            'new_size' => isset($opt_info['new_size']) ? $opt_info['new_size'] : 0,
            'savings_bytes' => isset($opt_info['savings_bytes']) ? $opt_info['savings_bytes'] : 0,
            'savings_percent' => isset($opt_info['savings_percent']) ? $opt_info['savings_percent'] : 0,
            'optimized_date' => isset($opt_info['optimized_date']) ? $opt_info['optimized_date'] : '',
            'message' => sprintf(
                __('Optimized: %s saved (%s%%)', 'kafunel-optimizer-ai'),
                kafunel_format_file_size($opt_info['savings_bytes']),
                $opt_info['savings_percent']
            )
        );
    }
    
    return array(
        'status' => 'optimized',
        'message' => __('Optimized', 'kafunel-optimizer-ai')
    );
}

/**
 * Check if we're on a Pro plan
 * 
 * @return bool True if Pro is active, false otherwise
 */
function kafunel_is_pro_active() {
    // Placeholder - implement actual Pro detection
    return defined('KAFUNEL_PRO_VERSION');
}

/**
 * Check if free plan quota is available
 * 
 * @return bool True if quota available, false otherwise
 */
function kafunel_check_free_quota() {
    $today = date('Y-m-d');
    $last_reset = get_option('kafunel_optimizer_last_reset_date', '');
    
    // Reset quota if it's a new day
    if ($last_reset !== $today) {
        update_option('kafunel_optimizer_free_quota_used', 0);
        update_option('kafunel_optimizer_last_reset_date', $today);
    }
    
    $quota_used = get_option('kafunel_optimizer_free_quota_used', 0);
    
    // Free plan allows max 10 optimizations per day
    if ($quota_used >= 10) {
        return false;
    }
    
    return true;
}

/**
 * Increment free plan quota counter
 */
function kafunel_increment_free_quota() {
    $quota_used = get_option('kafunel_optimizer_free_quota_used', 0);
    update_option('kafunel_optimizer_free_quota_used', $quota_used + 1);
}

/**
 * Get remaining free optimizations for today
 * 
 * @return int Number of remaining optimizations
 */
function kafunel_get_remaining_free_optimizations() {
    $quota_used = get_option('kafunel_optimizer_free_quota_used', 0);
    return max(0, 10 - $quota_used); // Free plan limit is 10 per day
}

/**
 * Validate API key format (basic validation)
 * 
 * @param string $api_key API key to validate
 * @return bool True if valid format, false otherwise
 */
function kafunel_validate_api_key($api_key) {
    // Basic validation: check if it's not empty and has reasonable length
    if (empty($api_key) || strlen($api_key) < 10) {
        return false;
    }
    
    // Additional validation could be implemented here
    return true;
}

/**
 * Get current compression level
 * 
 * @return string Current compression level setting
 */
function kafunel_get_current_compression_level() {
    return get_option('kafunel_optimizer_compression_level', 'optimal');
}

/**
 * Get current output format
 * 
 * @return string Current output format setting
 */
function kafunel_get_current_output_format() {
    return get_option('kafunel_optimizer_output_format', 'original');
}

/**
 * Check if auto-convert to next-gen formats is enabled
 * 
 * @return bool True if enabled, false otherwise
 */
function kafunel_is_auto_convert_enabled() {
    return (bool) get_option('kafunel_optimizer_auto_convert', 0);
}

/**
 * Check if auto-resize is enabled
 * 
 * @return bool True if enabled, false otherwise
 */
function kafunel_is_auto_resize_enabled() {
    return (bool) get_option('kafunel_optimizer_resize_enabled', 0);
}

/**
 * Get the plugin version
 * 
 * @return string Plugin version
 */
function kafunel_get_version() {
    return defined('KAFUNEL_VERSION') ? KAFUNEL_VERSION : '1.0.0';
}

/**
 * Log a message to the error log with plugin prefix
 * 
 * @param string $message Message to log
 * @param string $level Error level (default: 'info')
 */
function kafunel_log($message, $level = 'info') {
    error_log('[Kafunel Optimizer AI - ' . strtoupper($level) . '] ' . $message);
}