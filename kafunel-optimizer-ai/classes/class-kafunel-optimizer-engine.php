<?php
/**
 * Kafunel Optimizer Engine
 * 
 * Handles the core optimization functionality for the Kafunel Optimizer AI plugin.
 * Supports local optimization (GD/ImageMagick) and external AI services.
 * 
 * @package Kafunel_Optimizer_AI
 * @subpackage Engine
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Kafunel_Optimizer_Engine')) {

    class Kafunel_Optimizer_Engine
    {
        /**
         * Supported input formats
         */
        private $supported_input_formats = array('jpeg', 'jpg', 'png', 'gif', 'webp');

        /**
         * Supported output formats
         */
        private $supported_output_formats = array('jpeg', 'jpg', 'png', 'webp', 'avif');

        /**
         * Constructor
         */
        public function __construct()
        {
            // Initialize any required resources
        }

        /**
         * Optimize a single attachment
         * 
         * @param int $attachment_id The ID of the attachment to optimize
         * @return bool True on success, false on failure
         */
        public function optimize_attachment($attachment_id)
        {
            try {
                // Get the attached file path
                $file_path = get_attached_file($attachment_id);
                
                if (!$file_path || !file_exists($file_path)) {
                    error_log("Kafunel Optimizer: File not found for attachment ID {$attachment_id}");
                    return false;
                }

                // Check if it's an image
                if (!wp_attachment_is_image($attachment_id)) {
                    error_log("Kafunel Optimizer: Attachment ID {$attachment_id} is not an image");
                    return false;
                }

                // Get image metadata
                $image_meta = wp_get_attachment_metadata($attachment_id);
                $original_size = filesize($file_path);

                // Determine optimization settings
                $compression_level = get_option('kafunel_optimizer_compression_level', 'optimal');
                $output_format = get_option('kafunel_optimizer_output_format', 'original');
                $auto_convert = get_option('kafunel_optimizer_auto_convert', 0);
                $resize_enabled = get_option('kafunel_optimizer_resize_enabled', 0);
                $resize_width = get_option('kafunel_optimizer_resize_width', 1920);
                $resize_height = get_option('kafunel_optimizer_resize_height', 1080);

                // Update output format if auto-convert is enabled
                if ($auto_convert && $output_format === 'original') {
                    $output_format = 'webp'; // Default to webp if auto-converting
                }

                // Prepare optimization options
                $options = array(
                    'compression_level' => $compression_level,
                    'output_format' => $output_format,
                    'resize_enabled' => $resize_enabled,
                    'resize_dimensions' => array(
                        'width' => $resize_width,
                        'height' => $resize_height
                    )
                );

                // Perform optimization
                $optimized_file_path = $this->perform_optimization($file_path, $options);

                if ($optimized_file_path && file_exists($optimized_file_path)) {
                    // Replace the original file with the optimized version
                    $replace_result = $this->replace_original_file($file_path, $optimized_file_path, $attachment_id);
                    
                    if ($replace_result) {
                        // Update attachment metadata
                        $this->update_attachment_metadata($attachment_id, $image_meta, $original_size);
                        
                        // Mark as optimized
                        update_post_meta($attachment_id, '_kafunel_optimized', true);
                        update_post_meta($attachment_id, '_kafunel_optimized_date', current_time('mysql'));
                        
                        return true;
                    } else {
                        error_log("Kafunel Optimizer: Failed to replace original file for attachment ID {$attachment_id}");
                        return false;
                    }
                } else {
                    error_log("Kafunel Optimizer: Failed to optimize file for attachment ID {$attachment_id}");
                    return false;
                }
            } catch (Exception $e) {
                error_log("Kafunel Optimizer: Error optimizing attachment ID {$attachment_id}: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Perform the actual optimization process
         * 
         * @param string $file_path Path to the original file
         * @param array $options Optimization options
         * @return string|false Path to optimized file or false on failure
         */
        private function perform_optimization($file_path, $options)
        {
            // Determine target format
            $target_format = $options['output_format'];
            if ($target_format === 'original') {
                $target_format = $this->get_file_extension($file_path);
            }

            // Check if we need to convert format
            $current_format = $this->get_file_extension($file_path);
            $needs_conversion = strtolower($current_format) !== strtolower($target_format);

            // Prepare temporary file path
            $temp_dir = wp_upload_dir()['basedir'] . '/kafunel_temp';
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $filename = pathinfo($file_path, PATHINFO_FILENAME);
            $extension = $target_format;
            $temp_file_path = $temp_dir . '/' . $filename . '_optimized.' . $extension;

            // First, resize if needed
            if ($options['resize_enabled']) {
                $resized_file = $this->resize_image($file_path, $options['resize_dimensions']);
                if ($resized_file) {
                    $file_path = $resized_file; // Use the resized version for further processing
                }
            }

            // Then optimize based on compression level and format
            $optimized = false;

            // Try AI optimization first if API key is available
            $api_key = get_option('kafunel_optimizer_api_key');
            if (!empty($api_key)) {
                $optimized = $this->optimize_with_ai_api($file_path, $options);
            }

            // Fall back to local optimization if AI failed or not available
            if (!$optimized) {
                $optimized = $this->optimize_locally($file_path, $temp_file_path, $options);
            }

            // If format conversion is needed, handle that separately
            if ($needs_conversion && $optimized) {
                $converted_file = $this->convert_format($optimized, $target_format);
                if ($converted_file) {
                    unlink($optimized); // Remove temp optimized file
                    return $converted_file;
                }
            }

            return $optimized ?: false;
        }

        /**
         * Optimize image locally using GD or ImageMagick
         * 
         * @param string $source_file Source file path
         * @param string $target_file Target file path
         * @param array $options Optimization options
         * @return string|false Path to optimized file or false on failure
         */
        private function optimize_locally($source_file, $target_file, $options)
        {
            try {
                // Determine target format and quality based on compression level
                $quality = $this->get_quality_by_level($options['compression_level']);
                
                // Get image info
                $image_info = getimagesize($source_file);
                if (!$image_info) {
                    return false;
                }
                
                $width = $image_info[0];
                $height = $image_info[1];
                $mime_type = $image_info['mime'];

                // Create image resource based on source format
                $image = $this->create_image_from_file($source_file, $mime_type);
                if (!$image) {
                    return false;
                }

                // Determine target format
                $target_format = $options['output_format'];
                if ($target_format === 'original') {
                    $target_format = $this->get_file_extension($source_file);
                }

                // Save optimized image based on format
                $success = false;
                
                switch (strtolower($target_format)) {
                    case 'jpeg':
                    case 'jpg':
                        $success = imagejpeg($image, $target_file, $quality);
                        break;
                    case 'png':
                        // PNG quality is 0-9 (0 = best quality, 9 = smallest file)
                        $png_quality = $this->get_png_quality_by_level($options['compression_level']);
                        $success = imagepng($image, $target_file, $png_quality);
                        break;
                    case 'webp':
                        if (function_exists('imagewebp')) {
                            $success = imagewebp($image, $target_file, $quality);
                        }
                        break;
                    case 'gif':
                        $success = imagegif($image, $target_file);
                        break;
                    case 'avif':
                        // AVIF requires special handling - might need external tools
                        if (function_exists('imageavif')) {
                            $success = imageavif($image, $target_file, $quality);
                        }
                        break;
                }

                // Free up memory
                imagedestroy($image);

                if ($success && file_exists($target_file)) {
                    return $target_file;
                } else {
                    return false;
                }
            } catch (Exception $e) {
                error_log("Kafunel Optimizer: Local optimization failed: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Optimize image using external AI API
         * 
         * @param string $file_path Path to the file to optimize
         * @param array $options Optimization options
         * @return string|false Path to optimized file or false on failure
         */
        private function optimize_with_ai_api($file_path, $options)
        {
            // This is a placeholder implementation
            // In a real implementation, this would call an external AI service
            
            // For now, just return false to force local optimization
            // Real implementation would:
            // 1. Upload file to AI service
            // 2. Process with AI
            // 3. Download optimized file
            // 4. Return path to optimized file
            
            return false;
        }

        /**
         * Resize image if it exceeds dimensions
         * 
         * @param string $file_path Path to the file to resize
         * @param array $dimensions Width and height limits
         * @return string|false Path to resized file or false on failure
         */
        private function resize_image($file_path, $dimensions)
        {
            try {
                // Get image info
                $image_info = getimagesize($file_path);
                if (!$image_info) {
                    return false;
                }
                
                $width = $image_info[0];
                $height = $image_info[1];
                $mime_type = $image_info['mime'];

                // Only resize if image is larger than the specified dimensions
                if ($width <= $dimensions['width'] && $height <= $dimensions['height']) {
                    return $file_path; // No need to resize
                }

                // Calculate new dimensions maintaining aspect ratio
                $new_width = $width;
                $new_height = $height;

                if ($width > $dimensions['width']) {
                    $ratio = $dimensions['width'] / $width;
                    $new_width = $dimensions['width'];
                    $new_height = $height * $ratio;
                }

                if ($new_height > $dimensions['height']) {
                    $ratio = $dimensions['height'] / $new_height;
                    $new_height = $dimensions['height'];
                    $new_width = $new_width * $ratio;
                }

                // Create image resource
                $source_image = $this->create_image_from_file($file_path, $mime_type);
                if (!$source_image) {
                    return false;
                }

                // Create destination image
                $dest_image = imagecreatetruecolor($new_width, $new_height);
                
                // Preserve transparency for PNG and GIF
                if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
                    imagealphablending($dest_image, false);
                    imagesavealpha($dest_image, true);
                    $transparent = imagecolorallocatealpha($dest_image, 255, 255, 255, 127);
                    imagefilledrectangle($dest_image, 0, 0, $new_width, $new_height, $transparent);
                }

                // Resize the image
                imagecopyresampled(
                    $dest_image,
                    $source_image,
                    0, 0, 0, 0,
                    $new_width,
                    $new_height,
                    $width,
                    $height
                );

                // Prepare temporary file path
                $temp_dir = wp_upload_dir()['basedir'] . '/kafunel_temp';
                if (!file_exists($temp_dir)) {
                    wp_mkdir_p($temp_dir);
                }
                
                $filename = pathinfo($file_path, PATHINFO_FILENAME);
                $extension = pathinfo($file_path, PATHINFO_EXTENSION);
                $temp_file_path = $temp_dir . '/' . $filename . '_resized.' . $extension;

                // Save the resized image
                $success = false;
                switch ($mime_type) {
                    case 'image/jpeg':
                        $success = imagejpeg($dest_image, $temp_file_path, 90); // Keep high quality for resized image
                        break;
                    case 'image/png':
                        $success = imagepng($dest_image, $temp_file_path, 6); // Medium compression
                        break;
                    case 'image/gif':
                        $success = imagegif($dest_image, $temp_file_path);
                        break;
                    case 'image/webp':
                        if (function_exists('imagewebp')) {
                            $success = imagewebp($dest_image, $temp_file_path, 90);
                        }
                        break;
                }

                // Free up memory
                imagedestroy($source_image);
                imagedestroy($dest_image);

                if ($success && file_exists($temp_file_path)) {
                    return $temp_file_path;
                } else {
                    return false;
                }
            } catch (Exception $e) {
                error_log("Kafunel Optimizer: Image resize failed: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Convert image format
         * 
         * @param string $file_path Path to the file to convert
         * @param string $target_format Target format
         * @return string|false Path to converted file or false on failure
         */
        private function convert_format($file_path, $target_format)
        {
            try {
                // Get image info
                $image_info = getimagesize($file_path);
                if (!$image_info) {
                    return false;
                }
                
                $mime_type = $image_info['mime'];
                
                // Create image resource
                $image = $this->create_image_from_file($file_path, $mime_type);
                if (!$image) {
                    return false;
                }

                // Prepare temporary file path
                $temp_dir = wp_upload_dir()['basedir'] . '/kafunel_temp';
                if (!file_exists($temp_dir)) {
                    wp_mkdir_p($temp_dir);
                }
                
                $filename = pathinfo($file_path, PATHINFO_FILENAME);
                $temp_file_path = $temp_dir . '/' . $filename . '.' . $target_format;

                // Save in target format
                $success = false;
                $quality = 85; // Default quality
                
                switch (strtolower($target_format)) {
                    case 'jpeg':
                    case 'jpg':
                        $success = imagejpeg($image, $temp_file_path, $quality);
                        break;
                    case 'png':
                        $success = imagepng($image, $temp_file_path, 6); // Medium compression
                        break;
                    case 'webp':
                        if (function_exists('imagewebp')) {
                            $success = imagewebp($image, $temp_file_path, $quality);
                        }
                        break;
                    case 'gif':
                        $success = imagegif($image, $temp_file_path);
                        break;
                    case 'avif':
                        if (function_exists('imageavif')) {
                            $success = imageavif($image, $temp_file_path, $quality);
                        }
                        break;
                }

                // Free up memory
                imagedestroy($image);

                if ($success && file_exists($temp_file_path)) {
                    return $temp_file_path;
                } else {
                    return false;
                }
            } catch (Exception $e) {
                error_log("Kafunel Optimizer: Format conversion failed: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Create image resource from file
         * 
         * @param string $file_path Path to the image file
         * @param string $mime_type MIME type of the image
         * @return resource|false Image resource or false on failure
         */
        private function create_image_from_file($file_path, $mime_type)
        {
            switch ($mime_type) {
                case 'image/jpeg':
                    return imagecreatefromjpeg($file_path);
                case 'image/png':
                    return imagecreatefrompng($file_path);
                case 'image/gif':
                    return imagecreatefromgif($file_path);
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        return imagecreatefromwebp($file_path);
                    }
                    break;
                case 'image/avif':
                    if (function_exists('imagecreatefromavif')) {
                        return imagecreatefromavif($file_path);
                    }
                    break;
            }
            
            return false;
        }

        /**
         * Replace original file with optimized version
         * 
         * @param string $original_path Path to original file
         * @param string $optimized_path Path to optimized file
         * @param int $attachment_id Attachment ID
         * @return bool True on success, false on failure
         */
        private function replace_original_file($original_path, $optimized_path, $attachment_id)
        {
            try {
                // Backup original file
                $backup_path = $original_path . '.bak';
                if (!copy($original_path, $backup_path)) {
                    error_log("Kafunel Optimizer: Could not create backup of original file");
                    return false;
                }

                // Replace original file with optimized version
                if (!copy($optimized_path, $original_path)) {
                    // Restore backup if replacement fails
                    copy($backup_path, $original_path);
                    unlink($backup_path);
                    return false;
                }

                // Update attachment metadata with new file size
                $new_size = filesize($original_path);
                $image_meta = wp_get_attachment_metadata($attachment_id);
                $image_meta['filesize'] = $new_size;
                
                wp_update_attachment_metadata($attachment_id, $image_meta);

                // Clean up temporary files
                if (file_exists($backup_path)) {
                    unlink($backup_path);
                }
                
                if (file_exists($optimized_path)) {
                    unlink($optimized_path);
                }

                return true;
            } catch (Exception $e) {
                error_log("Kafunel Optimizer: Failed to replace original file: " . $e->getMessage());
                return false;
            }
        }

        /**
         * Update attachment metadata after optimization
         * 
         * @param int $attachment_id Attachment ID
         * @param array $image_meta Original image metadata
         * @param int $original_size Original file size
         */
        private function update_attachment_metadata($attachment_id, $image_meta, $original_size)
        {
            // Get new file size
            $file_path = get_attached_file($attachment_id);
            $new_size = $file_path ? filesize($file_path) : 0;
            
            // Calculate savings
            $savings = $original_size - $new_size;
            $savings_percent = $original_size > 0 ? round(($savings / $original_size) * 100, 2) : 0;
            
            // Update metadata
            $image_meta['kafunel_optimization'] = array(
                'original_size' => $original_size,
                'new_size' => $new_size,
                'savings_bytes' => $savings,
                'savings_percent' => $savings_percent,
                'optimized_date' => current_time('mysql')
            );
            
            wp_update_attachment_metadata($attachment_id, $image_meta);
        }

        /**
         * Get quality value based on compression level
         * 
         * @param string $level Compression level
         * @return int Quality percentage (1-100)
         */
        private function get_quality_by_level($level)
        {
            switch ($level) {
                case 'lossless':
                    return 95; // High quality for lossless
                case 'optimal':
                    return 85; // Good balance
                case 'aggressive':
                    return 70; // More aggressive compression
                case 'maximum':
                    return 50; // Maximum compression
                default:
                    return 85; // Default to optimal
            }
        }

        /**
         * Get PNG quality based on compression level
         * 
         * @param string $level Compression level
         * @return int PNG compression level (0-9)
         */
        private function get_png_quality_by_level($level)
        {
            switch ($level) {
                case 'lossless':
                    return 0; // Best quality
                case 'optimal':
                    return 3; // Good quality/compression
                case 'aggressive':
                    return 6; // More compression
                case 'maximum':
                    return 9; // Maximum compression
                default:
                    return 3; // Default to optimal
            }
        }

        /**
         * Get file extension from path
         * 
         * @param string $file_path File path
         * @return string File extension
         */
        private function get_file_extension($file_path)
        {
            return pathinfo($file_path, PATHINFO_EXTENSION);
        }

        /**
         * Get supported input formats
         * 
         * @return array Supported input formats
         */
        public function get_supported_input_formats()
        {
            return $this->supported_input_formats;
        }

        /**
         * Get supported output formats
         * 
         * @return array Supported output formats
         */
        public function get_supported_output_formats()
        {
            return $this->supported_output_formats;
        }

        /**
         * Check if format is supported for input
         * 
         * @param string $format Format to check
         * @return bool True if supported, false otherwise
         */
        public function supports_input_format($format)
        {
            return in_array(strtolower($format), $this->supported_input_formats);
        }

        /**
         * Check if format is supported for output
         * 
         * @param string $format Format to check
         * @return bool True if supported, false otherwise
         */
        public function supports_output_format($format)
        {
            return in_array(strtolower($format), $this->supported_output_formats);
        }
    }
}