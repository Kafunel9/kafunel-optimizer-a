<?php
/**
 * Plugin Name: Kafunel Optimizer AI
 * Plugin URI: https://kafunel.com/
 * Description: Advanced image optimization plugin with AI integration for WordPress. Optimize images with local processing (GD/ImageMagick) and external AI services.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Kafunel Team
 * Author URI: https://kafunel.com/
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: kafunel-optimizer-ai
 * Domain Path: /languages
 * 
 * Kafunel Optimizer AI - Advanced Image Optimization Plugin
 * Copyright (C) 2023 Kafunel Team  support@kafunel.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KAFUNEL_VERSION', '1.0.0');
define('KAFUNEL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KAFUNEL_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('KAFUNEL_BASENAME', plugin_basename(__FILE__));

// Check if class already exists to prevent conflicts
if (!class_exists('Kafunel_Optimizer_AI')) {

    class Kafunel_Optimizer_AI
    {
        private static $instance = null;

        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
            $this->init_hooks();
        }

        private function init_hooks()
        {
            // Initialize on plugins loaded
            add_action('plugins_loaded', array($this, 'load_textdomain'));
            
            // Admin specific hooks
            if (is_admin()) {
                add_action('admin_menu', array($this, 'add_admin_menu'));
                add_action('admin_init', array($this, 'settings_init'));
                add_filter('manage_media_columns', array($this, 'add_media_columns'));
                add_action('manage_media_custom_column', array($this, 'media_column_content'), 10, 2);
                add_filter('media_row_actions', array($this, 'add_media_row_actions'), 10, 2);
                add_action('wp_ajax_kafunel_optimize_image', array($this, 'ajax_optimize_image'));
                add_action('wp_ajax_kafunel_bulk_optimize', array($this, 'ajax_bulk_optimize'));
                
                // Enqueue admin scripts and styles
                add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            }

            // Activation and deactivation hooks
            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        }

        /**
         * Load plugin text domain for translations
         */
        public function load_textdomain()
        {
            load_plugin_textdomain(
                'kafunel-optimizer-ai',
                false,
                dirname(plugin_basename(__FILE__)) . '/languages'
            );
        }

        /**
         * Plugin activation tasks
         */
        public function activate()
        {
            // Create necessary database entries or options
            add_option('kafunel_optimizer_version', KAFUNEL_VERSION);
            add_option('kafunel_optimizer_free_quota_used', 0);
            add_option('kafunel_optimizer_last_reset_date', date('Y-m-d'));
        }

        /**
         * Plugin deactivation tasks
         */
        public function deactivate()
        {
            // Cleanup tasks if needed
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

            add_settings_section(
                'kafunel_optimizer_settings_section',
                __('Kafunel Optimizer Settings', 'kafunel-optimizer-ai'),
                array($this, 'settings_section_callback'),
                'kafunel-optimizer-ai'
            );

            add_settings_field(
                'kafunel_optimizer_api_key',
                __('API Key', 'kafunel-optimizer-ai'),
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
                __('Auto Convert to WebP/AVIF', 'kafunel-optimizer-ai'),
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
                __('Resize Width', 'kafunel-optimizer-ai'),
                array($this, 'resize_width_render'),
                'kafunel-optimizer-ai',
                'kafunel_optimizer_settings_section'
            );

            add_settings_field(
                'kafunel_optimizer_resize_height',
                __('Resize Height', 'kafunel-optimizer-ai'),
                array($this, 'resize_height_render'),
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
            <input type="password" name="kafunel_optimizer_api_key" value="<?php echo esc_attr($api_key); ?>" size="50">
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
            <select name="kafunel_optimizer_compression_level">
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
            <select name="kafunel_optimizer_output_format">
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
            <input type="checkbox" name="kafunel_optimizer_auto_convert" value="1" <?php checked($auto_convert, 1); ?>>
            <label><?php _e('Automatically convert images to WebP/AVIF when possible', 'kafunel-optimizer-ai'); ?></label>
            <?php
        }

        /**
         * Render resize enabled field
         */
        public function resize_enabled_render()
        {
            $resize_enabled = get_option('kafunel_optimizer_resize_enabled', 0);
            ?>
            <input type="checkbox" name="kafunel_optimizer_resize_enabled" value="1" <?php checked($resize_enabled, 1); ?>>
            <label><?php _e('Enable automatic resizing of large images', 'kafunel-optimizer-ai'); ?></label>
            <?php
        }

        /**
         * Render resize width field
         */
        public function resize_width_render()
        {
            $resize_width = get_option('kafunel_optimizer_resize_width', 1920);
            ?>
            <input type="number" name="kafunel_optimizer_resize_width" value="<?php echo esc_attr($resize_width); ?>" min="100" max="10000" size="6">
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
            <input type="number" name="kafunel_optimizer_resize_height" value="<?php echo esc_attr($resize_height); ?>" min="100" max="10000" size="6">
            <p class="description"><?php _e('Maximum height for resized images (in pixels).', 'kafunel-optimizer-ai'); ?></p>
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
                <h1><?php _e('Kafunel Optimizer AI', 'kafunel-optimizer-ai'); ?></h1>
                
                <form action='options.php' method='post'>
                    <?php
                    settings_fields('kafunel_optimizer_settings');
                    do_settings_sections('kafunel-optimizer-ai');
                    submit_button();
                    ?>
                </form>
                
                <div class="card">
                    <h2><?php _e('Usage Information', 'kafunel-optimizer-ai'); ?></h2>
                    <p><strong><?php _e('Free Plan Limit:', 'kafunel-optimizer-ai'); ?></strong> 
                       <?php 
                       $quota_used = get_option('kafunel_optimizer_free_quota_used', 0);
                       echo sprintf(__('You have used %d of 10 daily optimizations.', 'kafunel-optimizer-ai'), $quota_used);
                       ?>
                    </p>
                    <p><small><?php _e('Upgrade to Pro for unlimited optimizations and advanced AI features.', 'kafunel-optimizer-ai'); ?></small></p>
                </div>
            </div>
            <?php
        }

        /**
         * Add custom column to media library
         */
        public function add_media_columns($columns)
        {
            $columns['kafunel_optimization'] = __('Kafunel Optimization', 'kafunel-optimizer-ai');
            return $columns;
        }

        /**
         * Content for the custom media column
         */
        public function media_column_content($column_name, $post_id)
        {
            if ($column_name !== 'kafunel_optimization') {
                return;
            }

            $attachment_url = wp_get_attachment_url($post_id);
            $file_path = get_attached_file($post_id);
            
            // Check if it's an image
            if (!wp_attachment_is_image($post_id)) {
                echo __('Not an image', 'kafunel-optimizer-ai');
                return;
            }

            // Check if file exists
            if (!$file_path || !file_exists($file_path)) {
                echo __('File not found', 'kafunel-optimizer-ai');
                return;
            }

            // Generate nonce for security
            $nonce = wp_create_nonce('kafunel_optimize_' . $post_id);

            echo '<button type="button" class="button button-secondary kafunel-optimize-btn" data-id="' . esc_attr($post_id) . '" data-nonce="' . esc_attr($nonce) . '">';
            echo __('Optimize with Kafunel', 'kafunel-optimizer-ai');
            echo '</button>';

            // Show optimization status if available
            $optimized = get_post_meta($post_id, '_kafunel_optimized', true);
            if ($optimized) {
                echo '<br><span class="kafunel-status optimized">' . __('Optimized', 'kafunel-optimizer-ai') . '</span>';
            } else {
                echo '<br><span class="kafunel-status pending">' . __('Not optimized', 'kafunel-optimizer-ai') . '</span>';
            }
        }

        /**
         * Add row actions to media items
         */
        public function add_media_row_actions($actions, $post)
        {
            if (!wp_attachment_is_image($post->ID)) {
                return $actions;
            }

            $nonce = wp_create_nonce('kafunel_optimize_' . $post->ID);
            $actions['kafunel_optimize'] = sprintf(
                '<a href="#" class="kafunel-optimize-link" data-id="%s" data-nonce="%s">%s</a>',
                esc_attr($post->ID),
                esc_attr($nonce),
                __('Optimize with Kafunel', 'kafunel-optimizer-ai')
            );

            return $actions;
        }

        /**
         * Handle AJAX image optimization
         */
        public function ajax_optimize_image()
        {
            // Verify nonce and permissions
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kafunel_optimize_' . intval($_POST['id']))) {
                wp_die(__('Security check failed', 'kafunel-optimizer-ai'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions', 'kafunel-optimizer-ai'));
            }

            $attachment_id = intval($_POST['id']);
            
            // Check free plan quota
            if (!$this->check_free_quota()) {
                wp_send_json_error(__('Free plan daily limit reached. Please upgrade to Pro for unlimited optimizations.', 'kafunel-optimizer-ai'));
            }

            // Include the optimizer engine
            if (!class_exists('Kafunel_Optimizer_Engine')) {
                include_once KAFUNEL_PLUGIN_PATH . 'classes/class-kafunel-optimizer-engine.php';
            }

            $engine = new Kafunel_Optimizer_Engine();
            $result = $engine->optimize_attachment($attachment_id);

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Image optimized successfully!', 'kafunel-optimizer-ai'),
                    'id' => $attachment_id
                ));
            } else {
                wp_send_json_error(__('Failed to optimize image.', 'kafunel-optimizer-ai'));
            }
        }

        /**
         * Handle bulk optimization
         */
        public function ajax_bulk_optimize()
        {
            // Verify nonce and permissions
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kafunel_bulk_optimize')) {
                wp_die(__('Security check failed', 'kafunel-optimizer-ai'));
            }

            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions', 'kafunel-optimizer-ai'));
            }

            $attachment_ids = array_map('intval', $_POST['ids']);
            $results = array(
                'success' => 0,
                'failed' => 0,
                'errors' => array()
            );

            // Check free plan quota (max 20 per batch)
            if (count($attachment_ids) > 20 && !$this->is_pro_active()) {
                wp_send_json_error(__('Free plan allows up to 20 images per batch. Upgrade to Pro for larger batches.', 'kafunel-optimizer-ai'));
            }

            // Process each attachment
            foreach ($attachment_ids as $attachment_id) {
                // Check free plan quota for individual items
                if (!$this->check_free_quota()) {
                    break; // Stop if quota exceeded
                }

                // Include the optimizer engine
                if (!class_exists('Kafunel_Optimizer_Engine')) {
                    include_once KAFUNEL_PLUGIN_PATH . 'classes/class-kafunel-optimizer-engine.php';
                }

                $engine = new Kafunel_Optimizer_Engine();
                $result = $engine->optimize_attachment($attachment_id);

                if ($result) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = $attachment_id;
                }
            }

            wp_send_json_success($results);
        }

        /**
         * Check if free plan quota is available
         */
        private function check_free_quota()
        {
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
            
            // Increment quota counter
            update_option('kafunel_optimizer_free_quota_used', $quota_used + 1);
            
            return true;
        }

        /**
         * Check if Pro version is active
         */
        private function is_pro_active()
        {
            // Placeholder - implement actual Pro detection
            return defined('KAFUNEL_PRO_VERSION');
        }

        /**
         * Enqueue admin scripts and styles
         */
        public function enqueue_admin_scripts($hook)
        {
            if ($hook !== 'settings_page_kafunel-optimizer-ai' && strpos($hook, 'upload.php') === false) {
                return;
            }

            wp_enqueue_script(
                'kafunel-admin-js',
                KAFUNEL_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                KAFUNEL_VERSION,
                true
            );

            wp_localize_script('kafunel-admin-js', 'kafunel_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kafunel_nonce'),
                'loading_text' => __('Optimizing...', 'kafunel-optimizer-ai'),
                'success_text' => __('Optimized!', 'kafunel-optimizer-ai')
            ));

            wp_enqueue_style(
                'kafunel-admin-css',
                KAFUNEL_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                KAFUNEL_VERSION
            );
        }
    }

    // Initialize the plugin
    function kafunel_optimizer_ai_init()
    {
        return Kafunel_Optimizer_AI::get_instance();
    }

    // Start the plugin
    add_action('after_setup_theme', 'kafunel_optimizer_ai_init');
}