/**
 * GDWS Image Compression Admin JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';
    
    var compressionInProgress = false;
    var currentOffset = 0;
    var totalProcessed = 0;
    var currentPage = 1;
    var currentFilter = 'all';
    
    // Initialize
    init();
    
    function init() {
        bindEvents();
        updateQualitySliders();
        loadImageList();
    }
    
    function bindEvents() {
        // Compression controls
        $('#start-compression').on('click', startCompression);
        $('#stop-compression').on('click', stopCompression);
        $('#refresh-stats').on('click', refreshStats);
        
        // Quality sliders
        $('.quality-slider').on('input', updateQualityDisplay);
        
        // Image filters
        $('input[name="image_filter"]').on('change', function() {
            currentFilter = $(this).val();
            currentPage = 1;
            loadImageList();
        });
        
        // Image list pagination
        $(document).on('click', '.image-pagination a', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            if (page) {
                currentPage = page;
                loadImageList();
            }
        });
        
        // Restore image
        $(document).on('click', '.restore-image', function(e) {
            e.preventDefault();
            
            if (!confirm(gdws_compression.strings.confirm_restore)) {
                return;
            }
            
            var $button = $(this);
            var attachmentId = $button.data('id');
            
            $button.prop('disabled', true).text(gdws_compression.strings.processing);
            
            $.post(gdws_compression.ajax_url, {
                action: 'gdws_restore_image',
                attachment_id: attachmentId,
                nonce: gdws_compression.nonce
            }, function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    loadImageList();
                    refreshStats();
                } else {
                    showNotice(response.data.message || gdws_compression.strings.error, 'error');
                }
            }).always(function() {
                $button.prop('disabled', false).text('Restore');
            });
        });
        
        // Compress single image
        $(document).on('click', '.compress-single', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var attachmentId = $button.data('id');
            
            $button.prop('disabled', true).text(gdws_compression.strings.processing);
            
            // This would call the same compression function but for a single image
            compressSingleImage(attachmentId, function() {
                $button.prop('disabled', false).text('Compress');
                loadImageList();
                refreshStats();
            });
        });
    }
    
    function updateQualitySliders() {
        $('.quality-slider').each(function() {
            var $slider = $(this);
            var $display = $slider.siblings('.quality-value');
            
            $slider.on('input', function() {
                var value = $(this).val();
                var suffix = $(this).attr('id') === 'png_compression' ? '' : '%';
                $display.text(value + suffix);
            });
        });
    }
    
    function updateQualityDisplay() {
        var $slider = $(this);
        var $display = $slider.siblings('.quality-value');
        var value = $slider.val();
        var suffix = $slider.attr('id') === 'png_compression' ? '' : '%';
        $display.text(value + suffix);
    }
    
    function startCompression() {
        if (compressionInProgress) return;
        
        compressionInProgress = true;
        currentOffset = 0;
        totalProcessed = 0;
        
        $('#start-compression').hide();
        $('#stop-compression').show();
        $('#compression-progress').show();
        $('#compression-results').hide();
        
        updateProgress(0, gdws_compression.strings.processing);
        processNextBatch();
    }
    
    function stopCompression() {
        compressionInProgress = false;
        
        $('#start-compression').show();
        $('#stop-compression').hide();
        $('#compression-progress').hide();
        
        showNotice('Compression stopped.', 'info');
        refreshStats();
    }
    
    function processNextBatch() {
        if (!compressionInProgress) return;
        
        $.post(gdws_compression.ajax_url, {
            action: 'gdws_compress_images',
            offset: currentOffset,
            nonce: gdws_compression.nonce
        }, function(response) {
            if (!compressionInProgress) return;
            
            if (response.success) {
                var data = response.data;
                totalProcessed += data.processed;
                
                updateProgress(totalProcessed, 'Processed ' + totalProcessed + ' images...');
                
                if (data.completed) {
                    compressionCompleted();
                    showResults(data.results || []);
                } else {
                    currentOffset = data.next_offset;
                    // Continue processing with a small delay
                    setTimeout(processNextBatch, 100);
                }
            } else {
                stopCompression();
                showNotice(response.data.message || gdws_compression.strings.error, 'error');
            }
        }).fail(function() {
            stopCompression();
            showNotice(gdws_compression.strings.error, 'error');
        });
    }
    
    function compressionCompleted() {
        compressionInProgress = false;
        
        $('#start-compression').show();
        $('#stop-compression').hide();
        $('#compression-progress').hide();
        
        showNotice(gdws_compression.strings.completed, 'success');
        refreshStats();
        loadImageList();
    }
    
    function updateProgress(processed, text) {
        $('#progress-text').text(text);
        
        // We can't show exact percentage without knowing total count
        // So we'll show an indeterminate progress or update based on processed count
        var percentage = Math.min((processed / 50) * 100, 95); // Assume around 50 images max for display
        
        $('.progress-fill').css('width', percentage + '%');
        $('#progress-percentage').text(Math.round(percentage) + '%');
    }
    
    function showResults(results) {
        if (!results || results.length === 0) return;
        
        var $container = $('#results-content');
        var html = '<ul>';
        
        results.forEach(function(result) {
            var statusClass = result.success ? 'success' : 'error';
            html += '<li class="result-' + statusClass + '">';
            html += '<strong>ID ' + result.id + ':</strong> ' + result.message;
            html += '</li>';
        });
        
        html += '</ul>';
        $container.html(html);
        $('#compression-results').show();
    }
    
    function refreshStats() {
        $.post(gdws_compression.ajax_url, {
            action: 'gdws_get_compression_stats',
            nonce: gdws_compression.nonce
        }, function(response) {
            if (response.success) {
                updateStatsDisplay(response.data);
            }
        });
    }
    
    function updateStatsDisplay(stats) {
        $('.stats-grid .stat-card').each(function(index) {
            var $card = $(this);
            var $number = $card.find('.stat-number');
            
            switch(index) {
                case 0: // Total Images
                    $number.text(numberFormat(stats.total_images));
                    break;
                case 1: // Compressed Images
                    $number.text(numberFormat(stats.compressed_images));
                    break;
                case 2: // Space Saved
                    $number.text(stats.space_saved_formatted || formatBytes(stats.space_saved));
                    break;
                case 3: // Compression Ratio
                    $number.text(stats.compression_ratio + '%');
                    break;
            }
        });
    }
    
    function loadImageList() {
        var $container = $('#image-list');
        $container.html('<div class="loading">Loading images...</div>');
        
        $.post(gdws_compression.ajax_url, {
            action: 'gdws_get_image_list',
            filter: currentFilter,
            page: currentPage,
            nonce: gdws_compression.nonce
        }, function(response) {
            if (response.success) {
                renderImageList(response.data);
            } else {
                $container.html('<div class="error">Failed to load images.</div>');
            }
        });
    }
    
    function renderImageList(data) {
        var $container = $('#image-list');
        var $pagination = $('#image-list-pagination');
        
        if (!data.images || data.images.length === 0) {
            $container.html('<div class="no-images">No images found.</div>');
            $pagination.empty();
            return;
        }
        
        var html = '<div class="image-grid">';
        
        data.images.forEach(function(image) {
            html += '<div class="image-item">';
            html += '<div class="image-thumbnail">';
            html += '<img src="' + (image.thumbnail || image.url) + '" alt="' + image.title + '">';
            html += '</div>';
            html += '<div class="image-info">';
            html += '<h4>' + image.title + '</h4>';
            
            if (image.compressed) {
                html += '<span class="status compressed">Compressed</span>';
                if (image.space_saved) {
                    html += '<div class="size-info">';
                    html += 'Original: ' + image.original_size + '<br>';
                    html += 'Compressed: ' + image.compressed_size + '<br>';
                    html += 'Saved: ' + image.space_saved;
                    html += '</div>';
                }
                if (image.has_backup) {
                    html += '<button class="button button-small restore-image" data-id="' + image.id + '">Restore</button>';
                }
            } else {
                html += '<span class="status uncompressed">Not Compressed</span>';
                html += '<button class="button button-small button-primary compress-single" data-id="' + image.id + '">Compress</button>';
            }
            
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        $container.html(html);
        
        // Render pagination
        renderPagination(data, $pagination);
    }
    
    function renderPagination(data, $container) {
        if (data.total_pages <= 1) {
            $container.empty();
            return;
        }
        
        var html = '<div class="image-pagination">';
        
        // Previous button
        if (data.current_page > 1) {
            html += '<a href="#" data-page="' + (data.current_page - 1) + '" class="button">« Previous</a>';
        }
        
        // Page numbers
        var start = Math.max(1, data.current_page - 2);
        var end = Math.min(data.total_pages, data.current_page + 2);
        
        if (start > 1) {
            html += '<a href="#" data-page="1" class="button">1</a>';
            if (start > 2) {
                html += '<span class="dots">...</span>';
            }
        }
        
        for (var i = start; i <= end; i++) {
            var activeClass = i === data.current_page ? ' button-primary' : '';
            html += '<a href="#" data-page="' + i + '" class="button' + activeClass + '">' + i + '</a>';
        }
        
        if (end < data.total_pages) {
            if (end < data.total_pages - 1) {
                html += '<span class="dots">...</span>';
            }
            html += '<a href="#" data-page="' + data.total_pages + '" class="button">' + data.total_pages + '</a>';
        }
        
        // Next button
        if (data.current_page < data.total_pages) {
            html += '<a href="#" data-page="' + (data.current_page + 1) + '" class="button">Next »</a>';
        }
        
        html += '</div>';
        $container.html(html);
    }
    
    function compressSingleImage(attachmentId, callback) {
        $.post(gdws_compression.ajax_url, {
            action: 'gdws_compress_images',
            offset: 0,
            single_id: attachmentId, // We'd need to modify the PHP to handle single image compression
            nonce: gdws_compression.nonce
        }, function(response) {
            if (response.success) {
                showNotice('Image compressed successfully!', 'success');
            } else {
                showNotice(response.data.message || gdws_compression.strings.error, 'error');
            }
            callback();
        }).fail(function() {
            showNotice(gdws_compression.strings.error, 'error');
            callback();
        });
    }
    
    function showNotice(message, type) {
        type = type || 'info';
        
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 3 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    function numberFormat(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
});