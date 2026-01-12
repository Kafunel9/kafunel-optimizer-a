<?php
/**
 * Kafunel Optimizer Settings
 * 
 * Handles the settings page and related functionality for the Kafunel Optimizer AI plugin.
 * 
 * @package Kafunel_Optimizer_AI
 * @subpackage Settings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Kafunel_Optimizer_Settings')) {

    class Kafunel_Optimizer_Settings
    {
        /**
         * Constructor
         */
        public function __construct()
        {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'settings_init'));
        }

        /**
         * Add admin menu items
         */
        public function add_admin_menu()
        {
            add_options_page(
                __('Kafunel Optimizer AI Settings', 'kafunel-optimizer-ai'),
                __('Kafunel Optimizer', 'kafunel-optimizer-ai'),
                'manage_options',
                'kafunel-optimizer-ai',
                array($this, 'options_page')
            );
        }

        /**
         * Initialize settings
         */
        public function settings_init()
        {
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_api_key');
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_compression_level');
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_output_format');
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_auto_convert');
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_resize_enabled');
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_resize_width');
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_resize_height');
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_webp_support');
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_avif_support');
            register_setting('kafunel_optimizer_settings', 'kafunel_optimizer_backup_originals');

            add_settings_section(
                'kafunel_optimizer_settings_section',
                __('Kafunel Optimizer Settings', 'kafunel-optimizer-ai'),
                array($this, 'settings_section_callback'),
                'kafunel-optimizer-ai'
            );

            add_settings_field(
                'kafunel_optimizer_api_key',
                __('AI API Key', 'kafunel-optimizer-ai'),
                array($this, 'api_key_render'),
                'kafunel-optimizer-ai',
                'kafunel_optimizer_settings_section'
            );

            add_settings_field(
                'kafunel_optimizer_compression_level',
                __('Compression Level', 'kafunel-optimizer-ai'),
                array($this, 'compression_level_render'),
                'kafunel-optimizer-ai',
                'kafunel_optimizer_settings_section'
            );

            add_settings_field(
                'kafunel_optimizer_output_format',
                __('Output Format', 'kafunel-optimizer-ai'),
                array($this, 'output_format_render'),
                'kafunel-optimizer-ai',
                'kafunel_optimizer_settings_section'
            );

            add_settings_field(
                'kafunel_optimizer_auto_convert',
                __('Auto Convert to Next-Gen Formats', 'kafunel-optimizer-ai'),
                array($this, 'auto_convert_render'),
                'kafunel-optimizer-ai',
                'kafunel_optimizer_settings_section'
            );

            add_settings_field(
                'kafunel_optimizer_resize_enabled',
                __('Enable Auto Resize', 'kafunel-optimizer-ai'),
                array($this, 'resize_enabled_render'),
                'kafunel-optimizer-ai',
                'kafunel_optimizer_settings_section'
            );

            add_settings_field(
                'kafunel_optimizer_resize_width',
                __('Max Width for Resizing', 'kafunel-optimizer-ai'),
                array($this, 'resize_width_render'),
                'kafunel-optimizer-ai',
                'kafunel_optimizer_settings_section'
            );

            add_settings_field(
                'kafunel_optimizer_resize_height',
                __('Max Height for Resizing', 'kafunel-optimizer-ai'),
                array($this, 'resize_height_render'),
                'kafunel-optimizer-ai',
                'kafunel_optimizer_settings_section'
            );

            add_settings_field(
                'kafunel_optimizer_backup_originals',
                __('Backup Original Images', 'kafunel-optimizer-ai'),
                array($this, 'backup_originals_render'),
                'kafunel-optimizer-ai',
                'kafunel_optimizer_settings_section'
            );
        }

        /**
         * Render API key field
         */
        public function api_key_render()
        {
            $api_key = get_option('kafunel_optimizer_api_key');
            ?>
            <input type="password" id="kafunel_optimizer_api_key" name="kafunel_optimizer_api_key" value="<?php echo esc_attr($api_key); ?>" size="50">
            <p class="description"><?php _e('Enter your Kafunel AI API key. Leave blank to use local optimization only.', 'kafunel-optimizer-ai'); ?></p>
            <?php
        }

        /**
         * Render compression level field
         */
        public function compression_level_render()
        {
            $compression_level = get_option('kafunel_optimizer_compression_level', 'optimal');
            ?>
            <select name="kafunel_optimizer_compression_level" id="kafunel_optimizer_compression_level">
                <option value="lossless" <?php selected($compression_level, 'lossless'); ?>><?php _e('Lossless', 'kafunel-optimizer-ai'); ?></option>
                <option value="optimal" <?php selected($compression_level, 'optimal'); ?>><?php _e('Light (Optimal)', 'kafunel-optimizer-ai'); ?></option>
                <option value="aggressive" <?php selected($compression_level, 'aggressive'); ?>><?php _e('Aggressive (With Loss)', 'kafunel-optimizer-ai'); ?></option>
                <option value="maximum" <?php selected($compression_level, 'maximum'); ?>><?php _e('Maximum (Ultra Compression)', 'kafunel-optimizer-ai'); ?></option>
            </select>
            <p class="description"><?php _e('Choose the compression level for optimization.', 'kafunel-optimizer-ai'); ?></p>
            <?php
        }

        /**
         * Render output format field
         */
        public function output_format_render()
        {
            $output_format = get_option('kafunel_optimizer_output_format', 'original');
            ?>
            <select name="kafunel_optimizer_output_format" id="kafunel_optimizer_output_format">
                <option value="original" <?php selected($output_format, 'original'); ?>><?php _e('Keep Original Format', 'kafunel-optimizer-ai'); ?></option>
                <option value="webp" <?php selected($output_format, 'webp'); ?>><?php _e('WebP', 'kafunel-optimizer-ai'); ?></option>
                <option value="avif" <?php selected($output_format, 'avif'); ?>><?php _e('AVIF', 'kafunel-optimizer-ai'); ?></option>
                <option value="jpeg" <?php selected($output_format, 'jpeg'); ?>><?php _e('JPEG', 'kafunel-optimizer-ai'); ?></option>
                <option value="png" <?php selected($output_format, 'png'); ?>><?php _e('PNG', 'kafunel-optimizer-ai'); ?></option>
            </select>
            <p class="description"><?php _e('Select the output format for optimized images.', 'kafunel-optimizer-ai'); ?></p>
            <?php
        }

        /**
         * Render auto convert field
         */
        public function auto_convert_render()
        {
            $auto_convert = get_option('kafunel_optimizer_auto_convert', 0);
            ?>
            <input type="checkbox" name="kafunel_optimizer_auto_convert" id="kafunel_optimizer_auto_convert" value="1" <?php checked($auto_convert, 1); ?>>
            <label for="kafunel_optimizer_auto_convert"><?php _e('Automatically convert images to WebP/AVIF when possible', 'kafunel-optimizer-ai'); ?></label>
            <p class="description"><?php _e('Create next-gen format versions of your images for better performance.', 'kafunel-optimizer-ai'); ?></p>
            <?php
        }

        /**
         * Render resize enabled field
         */
        public function resize_enabled_render()
        {
            $resize_enabled = get_option('kafunel_optimizer_resize_enabled', 0);
            ?>
            <input type="checkbox" name="kafunel_optimizer_resize_enabled" id="kafunel_optimizer_resize_enabled" value="1" <?php checked($resize_enabled, 1); ?>>
            <label for="kafunel_optimizer_resize_enabled"><?php _e('Enable automatic resizing of large images', 'kafunel-optimizer-ai'); ?></label>
            <p class="description"><?php _e('Reduce unnecessarily large images to improve loading times.', 'kafunel-optimizer-ai'); ?></p>
            <?php
        }

        /**
         * Render resize width field
         */
        public function resize_width_render()
        {
            $resize_width = get_option('kafunel_optimizer_resize_width', 1920);
            ?>
            <input type="number" name="kafunel_optimizer_resize_width" id="kafunel_optimizer_resize_width" value="<?php echo esc_attr($resize_width); ?>" min="100" max="10000" size="6"> px
            <p class="description"><?php _e('Maximum width for resized images (in pixels).', 'kafunel-optimizer-ai'); ?></p>
            <?php
        }

        /**
         * Render resize height field
         */
        public function resize_height_render()
        {
            $resize_height = get_option('kafunel_optimizer_resize_height', 1080);
            ?>
            <input type="number" name="kafunel_optimizer_resize_height" id="kafunel_optimizer_resize_height" value="<?php echo esc_attr($resize_height); ?>" min="100" max="10000" size="6"> px
            <p class="description"><?php _e('Maximum height for resized images (in pixels).', 'kafunel-optimizer-ai'); ?></p>
            <?php
        }

        /**
         * Render backup originals field
         */
        public function backup_originals_render()
        {
            $backup_originals = get_option('kafunel_optimizer_backup_originals', 1);
            ?>
            <input type="checkbox" name="kafunel_optimizer_backup_originals" id="kafunel_optimizer_backup_originals" value="1" <?php checked($backup_originals, 1); ?>>
            <label for="kafunel_optimizer_backup_originals"><?php _e('Create backups of original images before optimization', 'kafunel-optimizer-ai'); ?></label>
            <p class="description"><?php _e('Saves original images with .bak extension for recovery purposes.', 'kafunel-optimizer-ai'); ?></p>
            <?php
        }

        /**
         * Settings section callback
         */
        public function settings_section_callback()
        {
            echo '<p>' . __('Configure your Kafunel Optimizer AI settings here.', 'kafunel-optimizer-ai') . '</p>';
        }

        /**
         * Options page HTML
         */
        public function options_page()
        {
            ?>
            <div class="wrap">
                <h1><?php _e('Kafunel Optimizer AI Settings', 'kafunel-optimizer-ai'); ?></h1>
                
                <form action='options.php' method='post'>
                    <?php
                    settings_fields('kafunel_optimizer_settings');
                    do_settings_sections('kafunel-optimizer-ai');
                    submit_button();
                    ?>
                </form>
                
                <div class="card" style="margin-top: 20px;">
                    <h2><?php _e('Usage Information', 'kafunel-optimizer-ai'); ?></h2>
                    <?php
                    $today = date('Y-m-d');
                    $last_reset = get_option('kafunel_optimizer_last_reset_date', '');
                    
                    // Reset quota if it's a new day
                    if ($last_reset !== $today) {
                        update_option('kafunel_optimizer_free_quota_used', 0);
                        update_option('kafunel_optimizer_last_reset_date', $today);
                    }
                    
                    $quota_used = get_option('kafunel_optimizer_free_quota_used', 0);
                    $quota_limit = 10; // Free plan limit
                    
                    echo '<p><strong>' . __('Free Plan Limit:', 'kafunel-optimizer-ai') . '</strong> ';
                    printf(
                        __('You have used %d of %d daily optimizations.', 'kafunel-optimizer-ai'),
                        $quota_used,
                        $quota_limit
                    );
                    echo '</p>';
                    
                    // Show reset time
                    $tomorrow = strtotime('+1 day', strtotime($today));
                    $reset_time = date('g:i A', $tomorrow);
                    echo '<p><small>' . sprintf(__('Your daily quota will reset at %s.', 'kafunel-optimizer-ai'), $reset_time) . '</small></p>';
                    
                    echo '<p><small>' . __('Upgrade to Pro for unlimited optimizations and advanced AI features like background removal and upscale.', 'kafunel-optimizer-ai') . '</small></p>';
                    ?>
                </div>
                
                <div class="card" style="margin-top: 20px;">
                    <h2><?php _e('System Status', 'kafunel-optimizer-ai'); ?></h2>
                    <?php
                    // Check if various image libraries are available
                    $gd_info = extension_loaded('gd') ? gd_info() : false;
                    $imagick = extension_loaded('imagick');
                    
                    echo '<p><strong>' . __('GD Library:', 'kafunel-optimizer-ai') . '</strong> ';
                    if ($gd_info) {
                        echo '<span style="color: #008a20;">' . __('Available', 'kafunel-optimizer-ai') . '</span>';
                        echo '<br><small>' . __('JPG Support:', 'kafunel-optimizer-ai') . ' ' . ($gd_info['JPEG Support'] ? __('Yes', 'kafunel-optimizer-ai') : __('No', 'kafunel-optimizer-ai')) . '</small>';
                        echo '<br><small>' . __('PNG Support:', 'kafunel-optimizer-ai') . ' ' . ($gd_info['PNG Support'] ? __('Yes', 'kafunel-optimizer-ai') : __('No', 'kafunel-optimizer-ai')) . '</small>';
                        echo '<br><small>' . __('WebP Support:', 'kafunel-optimizer-ai') . ' ' . ($gd_info['WebP Support'] ? __('Yes', 'kafunel-optimizer-ai') : __('No', 'kafunel-optimizer-ai')) . '</small>';
                        if (function_exists('imageavif')) {
                            echo '<br><small>' . __('AVIF Support:', 'kafunel-optimizer-ai') . ' <span style="color: #008a20;">' . __('Yes', 'kafunel-optimizer-ai') . '</span></small>';
                        } else {
                            echo '<br><small>' . __('AVIF Support:', 'kafunel-optimizer-ai') . ' <span style="color: #d63638;">' . __('No', 'kafunel-optimizer-ai') . '</span></small>';
                        }
                    } else {
                        echo '<span style="color: #d63638;">' . __('Not available', 'kafunel-optimizer-ai') . '</span>';
                    }
                    echo '</p>';
                    
                    echo '<p><strong>' . __('ImageMagick:', 'kafunel-optimizer-ai') . '</strong> ';
                    if ($imagick) {
                        echo '<span style="color: #008a20;">' . __('Available', 'kafunel-optimizer-ai') . '</span>';
                    } else {
                        echo '<span style="color: #d63638;">' . __('Not available', 'kafunel-optimizer-ai') . '</span>';
                    }
                    echo '</p>';
                    ?>
                </div>
            </div>
            <?php
        }
    }
}