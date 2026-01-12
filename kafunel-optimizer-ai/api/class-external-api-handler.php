<?php
/**
 * External API Handler
 * 
 * Handles communication with external AI services for advanced optimization.
 * This is a template that can be expanded with actual API integrations.
 * 
 * @package Kafunel_Optimizer_AI
 * @subpackage API
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Kafunel_External_API_Handler')) {

    class Kafunel_External_API_Handler
    {
        private $api_base_url = 'https://api.kafunel.com/v1';
        private $api_key;
        private $timeout = 30;

        public function __construct($api_key = null)
        {
            $this->api_key = $api_key ?: get_option('kafunel_optimizer_api_key');
        }

        /**
         * Optimize an image using external AI service
         * 
         * @param string $file_path Path to the image file
         * @param array $options Optimization options
         * @return array|false Array with result info or false on failure
         */
        public function optimize_image($file_path, $options = array())
        {
            if (empty($this->api_key)) {
                error_log('Kafunel Optimizer: No API key provided for external optimization');
                return false;
            }

            if (!file_exists($file_path)) {
                error_log("Kafunel Optimizer: File does not exist: {$file_path}");
                return false;
            }

            // Prepare request data
            $request_args = array(
                'method' => 'POST',
                'timeout' => $this->timeout,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept' => 'application/json',
                ),
                'body' => array(
                    'compression_level' => isset($options['compression_level']) ? $options['compression_level'] : 'optimal',
                    'output_format' => isset($options['output_format']) ? $options['output_format'] : 'original',
                    'features' => array()
                )
            );

            // Add features based on options
            if (!empty($options['remove_background'])) {
                $request_args['body']['features'][] = 'background_removal';
            }
            
            if (!empty($options['upscale'])) {
                $request_args['body']['features'][] = 'upscale';
            }

            // Create multipart request with file
            $boundary = '----KafunelBoundary' . md5(time());
            $request_args['headers']['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
            
            // Read file content
            $file_content = file_get_contents($file_path);
            if ($file_content === false) {
                error_log("Kafunel Optimizer: Could not read file: {$file_path}");
                return false;
            }

            // Build multipart body
            $body = '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . '"' . "\r\n";
            $body .= 'Content-Type: application/octet-stream' . "\r\n\r\n";
            $body .= $file_content . "\r\n";
            $body .= '--' . $boundary . "--\r\n";
            
            $request_args['body'] = $body;

            // Make API request
            $response = wp_remote_post($this->api_base_url . '/optimize', $request_args);

            if (is_wp_error($response)) {
                error_log('Kafunel Optimizer: API request failed - ' . $response->get_error_message());
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                error_log("Kafunel Optimizer: API returned error code {$response_code}: {$response_body}");
                return false;
            }

            $result = json_decode($response_body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Kafunel Optimizer: Invalid JSON response from API');
                return false;
            }

            // Process the result
            if (!isset($result['success']) || !$result['success']) {
                error_log('Kafunel Optimizer: API returned unsuccessful result: ' . print_r($result, true));
                return false;
            }

            // Download optimized file if URL is provided
            if (!empty($result['optimized_file_url'])) {
                $downloaded_file = $this->download_optimized_file($result['optimized_file_url']);
                if ($downloaded_file) {
                    return array(
                        'success' => true,
                        'file_path' => $downloaded_file,
                        'original_size' => filesize($file_path),
                        'new_size' => filesize($downloaded_file),
                        'savings' => filesize($file_path) - filesize($downloaded_file),
                        'details' => $result
                    );
                }
            }

            return $result;
        }

        /**
         * Remove background from image using AI
         * 
         * @param string $file_path Path to the image file
         * @return array|false Array with result info or false on failure
         */
        public function remove_background($file_path)
        {
            if (empty($this->api_key)) {
                error_log('Kafunel Optimizer: No API key provided for background removal');
                return false;
            }

            // Similar implementation to optimize_image but with background removal feature
            $options = array(
                'compression_level' => 'optimal',
                'output_format' => 'png', // Usually PNG for transparency
                'remove_background' => true
            );

            return $this->optimize_image($file_path, $options);
        }

        /**
         * Upscale image using AI
         * 
         * @param string $file_path Path to the image file
         * @param int $scale_factor Scale factor (2, 4, etc.)
         * @return array|false Array with result info or false on failure
         */
        public function upscale_image($file_path, $scale_factor = 2)
        {
            if (empty($this->api_key)) {
                error_log('Kafunel Optimizer: No API key provided for upscale');
                return false;
            }

            $options = array(
                'compression_level' => 'optimal',
                'output_format' => 'original',
                'upscale' => true,
                'scale_factor' => $scale_factor
            );

            return $this->optimize_image($file_path, $options);
        }

        /**
         * Download optimized file from API response
         * 
         * @param string $file_url URL to download the file from
         * @return string|false Path to downloaded file or false on failure
         */
        private function download_optimized_file($file_url)
        {
            $temp_dir = wp_upload_dir()['basedir'] . '/kafunel_temp';
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }

            $filename = 'optimized_' . uniqid() . '_' . basename($file_url);
            $temp_file_path = $temp_dir . '/' . $filename;

            // Download file
            $response = wp_remote_get($file_url, array(
                'timeout' => $this->timeout
            ));

            if (is_wp_error($response)) {
                error_log('Kafunel Optimizer: Failed to download optimized file - ' . $response->get_error_message());
                return false;
            }

            $file_content = wp_remote_retrieve_body($response);
            if (empty($file_content)) {
                error_log('Kafunel Optimizer: Empty response when downloading optimized file');
                return false;
            }

            // Write to temporary file
            $bytes_written = file_put_contents($temp_file_path, $file_content);
            if ($bytes_written === false) {
                error_log('Kafunel Optimizer: Failed to write optimized file to disk');
                return false;
            }

            return $temp_file_path;
        }

        /**
         * Check API key validity
         * 
         * @return bool True if valid, false otherwise
         */
        public function validate_api_key()
        {
            if (empty($this->api_key)) {
                return false;
            }

            $response = wp_remote_get($this->api_base_url . '/validate', array(
                'timeout' => $this->timeout,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept' => 'application/json',
                )
            ));

            if (is_wp_error($response)) {
                error_log('Kafunel Optimizer: API validation failed - ' . $response->get_error_message());
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                return false;
            }

            $result = json_decode($response_body, true);

            return isset($result['valid']) && $result['valid'] === true;
        }

        /**
         * Get account information and usage stats
         * 
         * @return array|false Account info or false on failure
         */
        public function get_account_info()
        {
            if (empty($this->api_key)) {
                return false;
            }

            $response = wp_remote_get($this->api_base_url . '/account', array(
                'timeout' => $this->timeout,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept' => 'application/json',
                )
            ));

            if (is_wp_error($response)) {
                error_log('Kafunel Optimizer: Failed to get account info - ' . $response->get_error_message());
                return false;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code !== 200) {
                return false;
            }

            $result = json_decode($response_body, true);

            return $result;
        }
    }
}