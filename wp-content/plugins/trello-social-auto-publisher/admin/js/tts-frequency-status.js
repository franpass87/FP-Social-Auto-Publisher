/**
 * Publishing Frequency Status Page JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';

    // Refresh status button handler
    $('#tts-refresh-status').on('click', function(e) {
        e.preventDefault();
        refreshStatus();
    });

    // Check all clients now button handler
    $('#tts-check-now').on('click', function(e) {
        e.preventDefault();
        checkAllClients();
    });

    /**
     * Refresh the frequency status display
     */
    function refreshStatus() {
        var $button = $('#tts-refresh-status');
        var $container = $('#tts-frequency-status-container');
        
        // Show loading state
        $button.prop('disabled', true).text(ttsFrequencyStatus.strings.refreshing);
        $container.addClass('tts-loading');

        $.ajax({
            url: ttsFrequencyStatus.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_refresh_frequency_status',
                nonce: ttsFrequencyStatus.nonce
            },
            success: function(response) {
                if (response.success) {
                    $container.html(response.data.html);
                    showNotice('Status refreshed successfully', 'success');
                } else {
                    showNotice(ttsFrequencyStatus.strings.error, 'error');
                }
            },
            error: function() {
                showNotice(ttsFrequencyStatus.strings.error, 'error');
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false).text('Refresh Status');
                $container.removeClass('tts-loading');
            }
        });
    }

    /**
     * Trigger frequency check for all clients
     */
    function checkAllClients() {
        var $button = $('#tts-check-now');
        
        // Show loading state
        $button.prop('disabled', true).text('Checking...');

        $.ajax({
            url: ttsFrequencyStatus.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tts_check_all_frequencies',
                nonce: ttsFrequencyStatus.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Frequency check completed. Alerts sent if needed.', 'success');
                    // Refresh the status after check
                    setTimeout(refreshStatus, 1000);
                } else {
                    showNotice('Error checking frequencies', 'error');
                }
            },
            error: function() {
                showNotice('Error checking frequencies', 'error');
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false).text('Check All Clients Now');
            }
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
        
        // Handle dismiss button
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut();
        });
    }

    /**
     * Auto-refresh status every 5 minutes
     */
    setInterval(function() {
        refreshStatus();
    }, 300000); // 5 minutes

    /**
     * Add tooltips to progress bars
     */
    $(document).on('mouseenter', '.tts-progress-bar', function() {
        var $row = $(this).closest('tr');
        var published = $row.find('td:nth-child(3)').text();
        var target = $row.find('td:nth-child(2)').text().split(' / ')[0];
        var remaining = $row.find('td:nth-child(4)').text();
        
        $(this).attr('title', 'Published: ' + published + ' / Target: ' + target + ' / Remaining: ' + remaining);
    });

    /**
     * Highlight urgent rows
     */
    function highlightUrgentRows() {
        $('.tts-channel-row.status-urgent, .tts-channel-row.status-overdue').each(function() {
            $(this).effect('highlight', {color: '#fef2f2'}, 1000);
        });
    }

    // Initial highlight
    highlightUrgentRows();

    /**
     * Add sorting functionality to tables
     */
    $('.tts-client-status-table table th').css('cursor', 'pointer').on('click', function() {
        var $table = $(this).closest('table');
        var $tbody = $table.find('tbody');
        var $rows = $tbody.find('tr').toArray();
        var column = $(this).index();
        var isAscending = !$(this).hasClass('sorted-asc');
        
        // Remove previous sorting classes
        $(this).siblings().removeClass('sorted-asc sorted-desc');
        
        // Add current sorting class
        $(this).addClass(isAscending ? 'sorted-asc' : 'sorted-desc');
        
        // Sort rows
        $rows.sort(function(a, b) {
            var aVal = $(a).find('td').eq(column).text().trim();
            var bVal = $(b).find('td').eq(column).text().trim();
            
            // Try to parse as numbers
            var aNum = parseFloat(aVal);
            var bNum = parseFloat(bVal);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAscending ? aNum - bNum : bNum - aNum;
            } else {
                return isAscending ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            }
        });
        
        // Reorder rows in DOM
        $tbody.empty().append($rows);
    });
});