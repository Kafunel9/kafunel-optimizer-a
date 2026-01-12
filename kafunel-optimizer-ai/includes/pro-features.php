<?php
/**
 * Kafunel Optimizer AI - Pro Features
 * 
 * Contains Pro-only functionality that extends the base plugin.
 * These features are only loaded when the Pro version is activated.
 * 
 * @package Kafunel_Optimizer_AI
 * @subpackage Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if Pro version is active
 * 
 * @return bool True if Pro is active, false otherwise
 */
function kafunel_is_pro_active() {
    return defined('KAFUNEL_PRO_VERSION') && KAFUNEL_PRO_VERSION;
}

/**
 * Initialize Pro features if Pro version is active
 */
function kafunel_init_pro_features() {
    if (!kafunel_is_pro_active()) {
        return;
    }
    
    // Add Pro-specific hooks and filters
    add_filter('kafunel_additional_features', 'kafunel_register_pro_features');
    add_action('admin_init', 'kafunel_init_pro_admin');
}

/**
 * Register Pro features
 * 
 * @param array $features Array of available features
 * @return array Updated features array
 */
function kafunel_register_pro_features($features) {
    if (!kafunel_is_pro_active()) {
        return $features;
    }
    
    $pro_features = array(
        'advanced_ai_optimization' => array(
            'name' => __('Advanced AI Optimization', 'kafunel-optimizer-ai'),
            'description' => __('Superior compression using advanced AI algorithms', 'kafunel-optimizer-ai'),
            'available' => true
        ),
        'background_removal' => array(
            'name' => __('Background Removal', 'kafunel-optimizer-ai'),
            'description' => __('Remove image backgrounds using AI', 'kafunel-optimizer-ai'),
            'available' => true
        ),
        'image_upscale' => array(
            'name' => __('AI Image Upscaling', 'kafunel-optimizer-ai'),
            'description' => __('Increase image resolution while maintaining quality', 'kafunel-optimizer-ai'),
            'available' => true
        ),
        'video_optimization' => array(
            'name' => __('Video Optimization', 'kafunel-optimizer-ai'),
            'description' => __('Optimize video files using AI technology', 'kafunel-optimizer-ai'),
            'available' => true
        ),
        'priority_support' => array(
            'name' => __('Priority Support', 'kafunel-optimizer-ai'),
            'description' => __('Get faster responses to your support requests', 'kafunel-optimizer-ai'),
            'available' => true
        ),
        'analytics_reports' => array(
            'name' => __('Analytics & Reports', 'kafunel-optimizer-ai'),
            'description' => __('Detailed reports on optimization performance', 'kafunel-optimizer-ai'),
            'available' => true
        ),
        'unlimited_optimizations' => array(
            'name' => __('Unlimited Optimizations', 'kafunel-optimizer-ai'),
            'description' => __('No daily or monthly limits on image processing', 'kafunel-optimizer-ai'),
            'available' => true
        )
    );
    
    return array_merge($features, $pro_features);
}

/**
 * Initialize Pro admin functionality
 */
function kafunel_init_pro_admin() {
    if (!kafunel_is_pro_active()) {
        return;
    }
    
    // Add Pro-specific admin pages, menus, etc.
    add_action('admin_menu', 'kafunel_add_pro_admin_pages');
    add_action('admin_init', 'kafunel_init_pro_settings');
}

/**
 * Add Pro admin pages
 */
function kafunel_add_pro_admin_pages() {
    if (!kafunel_is_pro_active()) {
        return;
    }
    
    // Add Pro dashboard page
    add_dashboard_page(
        __('Kafunel Pro Dashboard', 'kafunel-optimizer-ai'),
        __('Kafunel Pro', 'kafunel-optimizer-ai'),
        'manage_options',
        'kafunel-pro-dashboard',
        'kafunel_pro_dashboard_page'
    );
}

/**
 * Initialize Pro settings
 */
function kafunel_init_pro_settings() {
    if (!kafunel_is_pro_active()) {
        return;
    }
    
    // Register Pro-specific settings
    register_setting('kafunel_pro_settings', 'kafunel_pro_advanced_ai');
    register_setting('kafunel_pro_settings', 'kafunel_pro_background_removal');
    register_setting('kafunel_pro_settings', 'kafunel_pro_upscale_quality');
    register_setting('kafunel_pro_settings', 'kafunel_pro_video_enabled');
}

/**
 * Pro dashboard page content
 */
function kafunel_pro_dashboard_page() {
    if (!kafunel_is_pro_active()) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Kafunel Pro Dashboard', 'kafunel-optimizer-ai'); ?></h1>
        
        <div class="kafunel-pro-features">
            <h2><?php _e('Pro Features', 'kafunel-optimizer-ai'); ?></h2>
            <p><?php _e('Thank you for using Kafunel Optimizer Pro! You have access to all advanced features.', 'kafunel-optimizer-ai'); ?></p>
            
            <div class="card">
                <h3><?php _e('Account Status', 'kafunel-optimizer-ai'); ?></h3>
                <p><strong><?php _e('Plan:', 'kafunel-optimizer-ai'); ?></strong> <?php _e('Pro', 'kafunel-optimizer-ai'); ?></p>
                <p><strong><?php _e('Expires:', 'kafunel-optimizer-ai'); ?></strong> <?php echo date('F j, Y', strtotime('+1 year')); ?></p>
                <p><strong><?php _e('Optimizations Today:', 'kafunel-optimizer-ai'); ?></strong> <?php _e('Unlimited', 'kafunel-optimizer-ai'); ?></p>
            </div>
            
            <div class="card">
                <h3><?php _e('Quick Actions', 'kafunel-optimizer-ai'); ?></h3>
                <p><a href="<?php echo admin_url('upload.php'); ?>" class="button button-primary"><?php _e('Optimize Media Library', 'kafunel-optimizer-ai'); ?></a></p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Get Pro feature availability
 * 
 * @param string $feature Feature name
 * @return bool True if feature is available in current plan
 */
function kafunel_is_feature_available($feature) {
    if (kafunel_is_pro_active()) {
        return true;
    }
    
    // List of free features
    $free_features = array(
        'basic_optimization',
        'local_processing',
        'format_conversion',
        'batch_optimization',
        'basic_statistics'
    );
    
    return in_array($feature, $free_features);
}

/**
 * Check if user has reached optimization limits
 * 
 * @return bool True if limits reached, false otherwise
 */
function kafunel_has_reached_limits() {
    if (kafunel_is_pro_active()) {
        return false; // No limits for Pro users
    }
    
    // Check daily limit for free users
    $quota_used = get_option('kafunel_optimizer_free_quota_used', 0);
    return $quota_used >= 10; // Free plan limit is 10 per day
}

/**
 * Get Pro upgrade URL
 * 
 * @return string URL to Pro upgrade page
 */
function kafunel_get_upgrade_url() {
    return 'https://kafunel.com/pricing/';
}

/**
 * Get Pro feature tooltip
 * 
 * @param string $feature Feature name
 * @return string Tooltip HTML
 */
function kafunel_get_feature_tooltip($feature) {
    if (kafunel_is_feature_available($feature)) {
        return '';
    }
    
    return '<span class="pro-feature-badge">PRO</span>';
}

// Initialize Pro features
add_action('init', 'kafunel_init_pro_features');