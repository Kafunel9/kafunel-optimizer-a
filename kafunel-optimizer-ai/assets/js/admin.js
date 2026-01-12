/**
 * Kafunel Optimizer AI - Admin JavaScript
 * 
 * Handles the admin interface functionality for the Kafunel Optimizer AI plugin.
 */

jQuery(document).ready(function($) {
    'use strict';

    // Single image optimization
    $(document).on('click', '.kafunel-optimize-btn, .kafunel-optimize-link', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var attachmentId = $button.data('id');
        var nonce = $button.data('nonce');
        
        // Show processing state
        $button.addClass('kafunel-processing');
        $button.text(kafunel_ajax.loading_text);

        // Make AJAX request
        $.post(ajaxurl, {
            action: 'kafunel_optimize_image',
            id: attachmentId,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                // Update button text
                $button.removeClass('kafunel-processing');
                $button.text(kafunel_ajax.success_text);
                
                // Update status indicator
                var $status = $button.siblings('.kafunel-status');
                if ($status.length) {
                    $status.removeClass('pending').addClass('optimized');
                    $status.text('Optimized');
                } else {
                    $button.after('<br><span class="kafunel-status optimized">Optimized</span>');
                }
                
                // Show success notice
                showNotice(response.data.message, 'success');
            } else {
                $button.removeClass('kafunel-processing');
                $button.text('Optimize with Kafunel');
                
                // Show error notice
                var errorMessage = response.data && response.data.message ? response.data.message : 'Failed to optimize image.';
                showNotice(errorMessage, 'error');
            }
        }).fail(function() {
            $button.removeClass('kafunel-processing');
            $button.text('Optimize with Kafunel');
            
            showNotice('An error occurred while optimizing the image.', 'error');
        });
    });

    // Bulk optimization
    $(document).on('click', '#doaction, #doaction2', function(e) {
        var action = $(this).is('#doaction') ? $('#bulk-action-selector-top').val() : $('#bulk-action-selector-bottom').val();
        
        if (action === 'kafunel_bulk_optimize') {
            e.preventDefault();
            
            // Get selected attachments
            var attachments = [];
            $('input[name="media[]"]:checked').each(function() {
                attachments.push($(this).val());
            });
            
            if (attachments.length === 0) {
                showNotice('Please select images to optimize.', 'error');
                return;
            }
            
            // Confirm bulk action
            if (!confirm('Optimize ' + attachments.length + ' images with Kafunel?')) {
                return;
            }
            
            // Disable buttons to prevent multiple clicks
            $(this).prop('disabled', true);
            var $originalText = $(this).text();
            $(this).text('Optimizing...');
            
            // Make AJAX request for bulk optimization
            $.post(ajaxurl, {
                action: 'kafunel_bulk_optimize',
                ids: attachments,
                nonce: kafunel_ajax.nonce
            }, function(response) {
                if (response.success) {
                    var results = response.data;
                    var message = results.success + ' images optimized successfully.';
                    
                    if (results.failed > 0) {
                        message += ' ' + results.failed + ' images failed.';
                    }
                    
                    showNotice(message, 'success');
                    
                    // Update individual status indicators
                    $.each(attachments, function(index, id) {
                        var $row = $('input[name="media[]"][value="' + id + '"]').closest('tr');
                        var $status = $row.find('.kafunel-status');
                        
                        if ($status.length) {
                            $status.removeClass('pending').addClass('optimized');
                            $status.text('Optimized');
                        } else {
                            var $button = $row.find('.kafunel-optimize-btn');
                            if ($button.length) {
                                $button.after('<br><span class="kafunel-status optimized">Optimized</span>');
                            }
                        }
                    });
                } else {
                    var errorMessage = response.data && response.data.message ? response.data.message : 'Bulk optimization failed.';
                    showNotice(errorMessage, 'error');
                }
            }).fail(function() {
                showNotice('An error occurred during bulk optimization.', 'error');
            }).always(function() {
                // Re-enable buttons
                $('#doaction, #doaction2').prop('disabled', false).text($originalText);
            });
        }
    });

    // Add bulk action to select dropdowns
    function addBulkActions() {
        var bulkActions = [
            {value: 'kafunel_bulk_optimize', text: 'Optimize with Kafunel'}
        ];
        
        // Add to both bulk action selectors
        $.each(bulkActions, function(index, action) {
            var $action1 = $('#bulk-action-selector-top option[value="' + action.value + '"]');
            var $action2 = $('#bulk-action-selector-bottom option[value="' + action.value + '"]');
            
            if ($action1.length === 0) {
                $('#bulk-action-selector-top').append('<option value="' + action.value + '">' + action.text + '</option>');
            }
            
            if ($action2.length === 0) {
                $('#bulk-action-selector-bottom').append('<option value="' + action.value + '">' + action.text + '</option>');
            }
        });
    }
    
    // Run on page load and when media items are loaded via AJAX
    addBulkActions();
    $(document).ajaxComplete(function() {
        addBulkActions();
    });

    // Show notice function
    function showNotice(message, type) {
        var noticeClass = 'notice';
        if (type === 'success') {
            noticeClass += ' notice-success is-dismissible';
        } else if (type === 'error') {
            noticeClass += ' notice-error is-dismissible';
        } else {
            noticeClass += ' notice-info is-dismissible';
        }
        
        var $notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
        $('.wrap').prepend($notice);
        
        // Auto-dismiss success notices after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Add dismiss functionality
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    // Add Kafunel status to media library grid view
    function updateGridStatus() {
        $('.attachments .attachment').each(function() {
            var $attachment = $(this);
            var attachmentId = $attachment.attr('data-id');
            
            if (attachmentId) {
                // Check if already has Kafunel status
                if (!$attachment.find('.kafunel-status').length) {
                    // Add status indicator
                    var $status = $('<div class="kafunel-status-grid">Optimizing...</div>');
                    $attachment.append($status);
                    
                    // In a real implementation, we would check the optimization status via AJAX
                    // For now, we'll just add a placeholder
                    $status.remove();
                }
            }
        });
    }
    
    // Run when media library loads
    $(document).on('wpQueueAddComplete', function() {
        updateGridStatus();
    });
    
    // Initial run
    updateGridStatus();
});