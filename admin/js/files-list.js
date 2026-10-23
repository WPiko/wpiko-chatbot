jQuery(document).ready(function($) {

// ---------------------------------------------------------------------
// Shared row rendering for the Train AI file lists.
// ---------------------------------------------------------------------
function formatFileSize(bytes) {
    bytes = Number(bytes) || 0;
    if (bytes < 1024) {
        return bytes + ' B';
    }
    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(1) + ' KB';
    }
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function formatFileDate(timestamp) {
    var date = new Date((Number(timestamp) || 0) * 1000);
    var seconds = (Date.now() - date.getTime()) / 1000;
    if (seconds < 60) {
        return 'just now';
    }
    if (seconds < 3600) {
        return Math.floor(seconds / 60) + ' min ago';
    }
    if (seconds < 86400) {
        var hours = Math.floor(seconds / 3600);
        return hours + (hours === 1 ? ' hour ago' : ' hours ago');
    }
    if (seconds < 86400 * 7) {
        var days = Math.floor(seconds / 86400);
        return days + (days === 1 ? ' day ago' : ' days ago');
    }
    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function fileTypeIcon(filename) {
    var ext = String(filename || '').split('.').pop().toLowerCase();
    switch (ext) {
        case 'pdf': return 'dashicons-pdf';
        case 'doc':
        case 'docx': return 'dashicons-media-document';
        case 'json':
        case 'html': return 'dashicons-media-code';
        default: return 'dashicons-media-text';
    }
}

function fileRow(options) {
    var $row = $('<li class="wpiko-file-row"></li>');
    $row.append(
        $('<span class="wpiko-file-type" aria-hidden="true"></span>')
            .append($('<span class="dashicons"></span>').addClass(options.icon || 'dashicons-media-default'))
    );
    $row.append(
        $('<div class="file-info"></div>')
            .append($('<span class="file-name"></span>').text(options.name).attr('title', options.title || options.name))
            .append($('<span class="file-meta"></span>').text(options.meta || ''))
    );
    if (options.deleteClass) {
        $row.append(
            $('<button type="button" class="wpiko-icon-button is-danger"></button>')
                .addClass(options.deleteClass)
                .attr('data-file-id', options.fileId)
                .attr('aria-label', 'Delete ' + options.name)
                .attr('title', 'Delete')
                .append('<span class="dashicons dashicons-trash" aria-hidden="true"></span>')
        );
    }
    return $row;
}

function emptyRow(icon, title, text) {
    var $row = $('<li class="wpiko-list-empty"></li>');
    $row.append($('<span class="dashicons" aria-hidden="true"></span>').addClass(icon));
    $row.append($('<strong></strong>').text(title));
    if (text) {
        $row.append($('<span></span>').text(text));
    }
    return $row;
}

function setListCount(id, count) {
    var $count = $('#' + id);
    if ($count.length) {
        $count.text(count).prop('hidden', !count);
    }
}

function confirmDelete(title, message) {
    if (window.wpikoModal && typeof window.wpikoModal.confirm === 'function') {
        return window.wpikoModal.confirm({
            title: title,
            message: message,
            confirmLabel: 'Delete',
            danger: true
        });
    }
    return $.Deferred().resolve(window.confirm(title)).promise();
}

// Uploaded Files list
function refreshFileList(retryCount = 0) {
    var $assistantFilesList = jQuery('#assistant-files-list');
    $assistantFilesList.html('<li class="loading">Loading files…</li>');

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'wpiko_chatbot_list_files',
            security: wpikoChatbotAdmin.nonce
        },
        timeout: 45000, // Increase timeout to 45 seconds for large file lists
        success: function(response) {
            if (response.success) {
                var files = response.data.files;
                var performance = response.data.performance;
                
                $assistantFilesList.empty();
                var hasFiles = false;
                var uploadedCount = 0;
                files.forEach(function(file) {
                    // Skip files that start with "page_", "qa_data_" or "woocommerce_"
                    if (!file.filename.startsWith('page_') && !file.filename.startsWith('qa_data_') && !file.filename.startsWith('woocommerce_')) {
                        hasFiles = true;
                        uploadedCount++;
                        $assistantFilesList.append(fileRow({
                            icon: fileTypeIcon(file.filename),
                            name: file.filename,
                            meta: formatFileSize(file.bytes) + ' · Uploaded ' + formatFileDate(file.created_at),
                            deleteClass: 'delete-file',
                            fileId: file.id
                        }));
                    }
                });
                setListCount('assistant-files-count', uploadedCount);
                if (!hasFiles) {
                    $assistantFilesList.empty().append(emptyRow('dashicons-media-default', 'No files yet', 'Upload a document above and your assistant can use it in its answers.'));
                } else {
                    // Show file count and performance info
                    var uploadedFiles = files.filter(function(file) {
                        return !file.filename.startsWith('page_') && !file.filename.startsWith('qa_data_') && !file.filename.startsWith('woocommerce_');
                    });
                    var totalFiles = uploadedFiles.length;
                    
                    if (totalFiles > 0) {
                        // Add performance info to UI if there are many uploaded files
                        if (performance && totalFiles > 10) {
                            var perfText = '';
                            if (performance.file_breakdown) {
                                var uploadedFromAPI = performance.file_breakdown.uploaded_files;
                                var cacheRatio = performance.cached_files / performance.total_files;
                                var cachedDisplay = Math.round(uploadedFromAPI * cacheRatio);
                                var apiDisplay = uploadedFromAPI - cachedDisplay;
                                perfText = 'Performance: ' + cachedDisplay + ' uploaded files from cache, ' + apiDisplay + ' fetched from API';
                            } else {
                                // Fallback display
                                var cachedDisplay = Math.round(performance.cached_files * (totalFiles / performance.total_files));
                                var apiDisplay = Math.round(performance.api_fetched * (totalFiles / performance.total_files));
                                perfText = 'Performance: ' + cachedDisplay + ' uploaded files from cache, ' + apiDisplay + ' fetched from API';
                            }
                            
                            var perfInfo = jQuery('<div class="file-performance-info">').html('<small>' + perfText + '</small>');
                            $assistantFilesList.prepend(perfInfo);
                        }
                    }
                }
                refreshUrlProcessingFileList();
                refreshWooCommerceFileList();
            } else {
                $assistantFilesList.html('<li class="error">Error loading files: ' + response.data.message + '</li>');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
            if (retryCount < 3) {
                setTimeout(function() {
                    refreshFileList(retryCount + 1);
                }, 2000); // Wait 2 seconds before retrying
            } else {
                $assistantFilesList.html('<li class="error">Error loading files. Please try again. If the problem persists, refresh the page.</li>');
            }
        }
    });
}

// Content processing files list
function refreshUrlProcessingFileList(retryCount = 0) {
    var $urlProcessingFilesList = jQuery('#url-processing-files-list');
    
    // Check if the element exists
    if (!$urlProcessingFilesList.length) {
        return;
    }
    
    $urlProcessingFilesList.html('<li class="loading">Loading pages…</li>');

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'wpiko_chatbot_list_files',
            security: wpikoChatbotAdmin.nonce
        },
        timeout: 45000, // Increase timeout to 45 seconds for large file lists
        success: function(response) {
            if (response.success) {
                var files = response.data.files;
                var performance = response.data.performance;
                
                $urlProcessingFilesList.empty();
                var hasUrlProcessingFiles = false;
                
                // Filter and sort page files by creation date (newest first)
                var pageFiles = files.filter(function(file) {
                    return file.filename.startsWith('page_');
                }).sort(function(a, b) {
                    return b.created_at - a.created_at;
                });
                
                pageFiles.forEach(function(file) {
                    hasUrlProcessingFiles = true;
                    var fileSize = (file.bytes / 1024).toFixed(2) + ' KB';
                    var fileDate = new Date(file.created_at * 1000).toLocaleString();
                    
                    // Improve display name formatting
                    var displayName = file.filename
                        .replace(/^page_/, '')
                        .replace(/\.txt$/, '')
                        .replace(/-/g, ' ')
                        .split(' ')
                        .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                        .join(' ');

                    $urlProcessingFilesList.append(fileRow({
                        icon: 'dashicons-admin-page',
                        name: displayName,
                        meta: formatFileSize(file.bytes) + ' · Scanned ' + formatFileDate(file.created_at),
                        deleteClass: 'delete-page-file',
                        fileId: file.id
                    }));
                });
                
                setListCount('url-processing-files-count', pageFiles.length);
                if (!hasUrlProcessingFiles) {
                    $urlProcessingFilesList.empty().append(emptyRow('dashicons-admin-page', 'No pages scanned yet', 'Find a page above and generate its Q&A.'));
                } else {
                    // Show page file count and performance info
                    var pageFileCount = pageFiles.length;
                    // Add performance info to UI if there are many page files
                    if (performance && pageFileCount > 5) {
                        var perfInfo = jQuery('<div class="file-performance-info">').html(
                            '<small>Performance: ' + pageFileCount + ' page files loaded efficiently</small>'
                        );
                        $urlProcessingFilesList.prepend(perfInfo);
                    }
                }
            } else {
                $urlProcessingFilesList.html(
                    '<li class="error">Error loading URL processing files: ' + 
                    (response.data ? response.data.message : 'Unknown error') + 
                    '</li>'
                );
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
            if (retryCount < 3) {
                setTimeout(function() {
                    refreshUrlProcessingFileList(retryCount + 1);
                }, 2000);
            } else {
                $urlProcessingFilesList.html(
                    '<li class="error">Error loading files. Please try again later.</li>'
                );
            }
        }
    });
}

// Q&A files list
function refreshQAFileList(retryCount = 0) {
    var $qaFilesList = jQuery('#qa-files-list');
    $qaFilesList.html('<li class="loading">Loading…</li>');

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'wpiko_chatbot_list_files',
            security: wpikoChatbotAdmin.nonce
        },
        timeout: 45000, // Increase timeout to 45 seconds for large file lists
        success: function(response) {
            if (response.success) {
                var files = response.data.files;
                var performance = response.data.performance;
                
                $qaFilesList.empty();
                var hasQAFiles = false;
                
                // Filter and sort Q&A files by creation date (newest first)
                var qaFiles = files.filter(function(file) {
                    return file.filename.startsWith('qa_data_');
                }).sort(function(a, b) {
                    return b.created_at - a.created_at;
                });
                
                qaFiles.forEach(function(file) {
                    hasQAFiles = true;
                    var fileSize = (file.bytes / 1024).toFixed(2) + ' KB';
                    var fileDate = new Date(file.created_at * 1000).toLocaleString();
                    $qaFilesList.append(fileRow({
                        icon: 'dashicons-editor-help',
                        name: 'Questions & Answers',
                        title: file.filename,
                        meta: formatFileSize(file.bytes) + ' · Uploaded ' + formatFileDate(file.created_at)
                    }));
                });
                
                if (!hasQAFiles) {
                    // The sync summary above already says there is no file yet.
                    $qaFilesList.empty();
                } else {
                    // Show Q&A file count and performance info
                    var qaFileCount = qaFiles.length;
                    // Add performance info to UI if there are multiple Q&A files
                    if (performance && qaFileCount > 1) {
                        var perfInfo = jQuery('<div class="file-performance-info">').html(
                            '<small>Performance: ' + qaFileCount + ' Q&A files loaded efficiently</small>'
                        );
                        $qaFilesList.prepend(perfInfo);
                    }
                }
            } else {
                $qaFilesList.html('<li class="error">Error loading Q&A files: ' + response.data.message + '</li>');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
            if (retryCount < 3) {
                setTimeout(function() {
                    refreshQAFileList(retryCount + 1);
                }, 2000); // Wait 2 seconds before retrying
            } else {
                $qaFilesList.html('<li class="error">Error loading Q&A files. Please try again. If the problem persists, refresh the page.</li>');
            }
        }
    });
}

// WooCommerce files list
function refreshWooCommerceFileList(retryCount = 0) {
    var $woocommerceFilesList = jQuery('#woocommerce-files-list');
    $woocommerceFilesList.html('<li class="loading">Loading product files…</li>');

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'wpiko_chatbot_list_files',
            security: wpikoChatbotAdmin.nonce
        },
        timeout: 45000, // Increase timeout to 45 seconds for large file lists
        success: function(response) {
            if (response.success) {
                var files = response.data.files;
                var performance = response.data.performance;
                
                $woocommerceFilesList.empty();
                var hasWooCommerceFiles = false;
                
                // Filter and sort WooCommerce files by creation date (newest first)
                var wooFiles = files.filter(function(file) {
                    return file.filename.startsWith('woocommerce_');
                }).sort(function(a, b) {
                    return b.created_at - a.created_at;
                });
                
                wooFiles.forEach(function(file) {
                    hasWooCommerceFiles = true;
                    var fileSize = (file.bytes / 1024).toFixed(2) + ' KB';
                    var fileDate = new Date(file.created_at * 1000).toLocaleString();
                    var displayName = file.filename.replace('woocommerce_', '').replace('.json', '');
                    displayName = displayName.charAt(0).toUpperCase() + displayName.slice(1); // Capitalize first letter
                    $woocommerceFilesList.append(fileRow({
                        icon: 'dashicons-cart',
                        name: displayName,
                        title: file.filename,
                        meta: formatFileSize(file.bytes) + ' · Synced ' + formatFileDate(file.created_at),
                        deleteClass: 'delete-woo-file',
                        fileId: file.id
                    }));
                });
                
                setListCount('woocommerce-files-count', wooFiles.length);
                if (!hasWooCommerceFiles) {
                    $woocommerceFilesList.empty().append(emptyRow('dashicons-cart', 'No product files yet', 'Turn on the integration and sync your products to create them.'));
                } else {
                    // Show WooCommerce file count and performance info
                    var wooFileCount = wooFiles.length;
                    // Add performance info to UI if there are multiple WooCommerce files
                    if (performance && wooFileCount > 2) {
                        var perfInfo = jQuery('<div class="file-performance-info">').html(
                            '<small>Performance: ' + wooFileCount + ' WooCommerce files loaded efficiently</small>'
                        );
                        $woocommerceFilesList.prepend(perfInfo);
                    }
                }
            } else {
                $woocommerceFilesList.html('<li class="error">Error loading WooCommerce files: ' + response.data.message + '</li>');
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
            if (retryCount < 3) {
                setTimeout(function() {
                    refreshWooCommerceFileList(retryCount + 1);
                }, 2000); // Wait 2 seconds before retrying
            } else {
                $woocommerceFilesList.html('<li class="error">Error loading WooCommerce files. Please try again. If the problem persists, refresh the page.</li>');
            }
        }
    });
}
    
// Generic deleteFile
function deleteFile(fileId, refreshFunction, $anchor) {
    // Messages appear above the list the file was deleted from.
    var noticeOptions = $anchor && $anchor.length ? { before: $anchor } : {};
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'wpiko_chatbot_delete_file',
            security: wpikoChatbotAdmin.nonce,
            file_id: fileId
        },
        success: function(response) {
            if (response.success) {
                window.wpikoChatbotNotices.show('File deleted successfully.', 'success', noticeOptions);
                refreshFunction();
            } else {
                window.wpikoChatbotNotices.show('Error deleting file: ' + (response.data ? response.data.message : 'Unknown error'), 'error', noticeOptions);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            // Check if the file was actually deleted despite the AJAX error
            refreshFunction();
            window.wpikoChatbotNotices.show('The file may have been deleted, but there was an error in the response. Please check and try again if necessary.', 'warning', noticeOptions);
        }
    });
}

    // Event listener for Uploaded file delete buttons
    $(document).on('click', '.delete-file', function(e) {
        e.preventDefault();
        var $button = $(this);
        var fileId = $button.data('file-id');
        var $list = $button.closest('ul');
        confirmDelete('Delete this file?', 'Your assistant will stop using it in its answers. This cannot be undone.').then(function(ok) {
            if (ok) {
                $button.prop('disabled', true);
                deleteFile(fileId, refreshFileList, $list);
            }
        });
    });

    // Event listener for Content processing file delete buttons
    $(document).on('click', '.delete-page-file', function(e) {
        e.preventDefault();
        var $button = $(this);
        var fileId = $button.data('file-id');
        var $list = $button.closest('ul');
        confirmDelete('Delete this scanned page?', 'Its Q&A will be removed from the knowledge base. You can scan the page again later.').then(function(ok) {
            if (ok) {
                $button.prop('disabled', true);
                deleteFile(fileId, refreshUrlProcessingFileList, $list);
            }
        });
    });
    
    // Event listener for Q&A file delete buttons
    $(document).on('click', '.delete-qa-file', function(e) {
        e.preventDefault();
        var $button = $(this);
        var fileId = $button.data('file-id');
        var $list = $button.closest('ul');
        confirmDelete('Delete the Q&A file?', 'Your saved Q&A pairs stay in the builder, but the assistant stops using them until you save again.').then(function(ok) {
            if (ok) {
                $button.prop('disabled', true);
                deleteFile(fileId, refreshQAFileList, $list);
            }
        });
    });

    // Event listener for WooCommerce file delete buttons
    $(document).on('click', '.delete-woo-file', function(e) {
        e.preventDefault();
        var $button = $(this);
        var fileId = $button.data('file-id');
        var $list = $button.closest('ul');
        confirmDelete('Delete this product file?', 'The assistant stops using it until you sync your products again.').then(function(ok) {
            if (ok) {
                $button.prop('disabled', true);
                deleteFile(fileId, refreshWooCommerceFileList, $list);
            }
        });
    });

    // Initial file list load
    var $fileManagementSection = $('#file-management-section');
    var $qaManagementSection = $('#qa-management-section');
    var $wooCommerceSection = $('#woocommerce-integration-section');
    
    if ($fileManagementSection.is(':visible')) {
        refreshFileList();
        refreshQAFileList();
    }
    
    if ($qaManagementSection.is(':visible') && $('#qa-files-list').length) {
        refreshQAFileList();
    }
    
    if ($wooCommerceSection.is(':visible') && $('#woocommerce-files-list').length) {
        refreshWooCommerceFileList();
    }

    // Refresh file list when assistant is created or updated
    $(document).on('assistant_updated', function() {
        $fileManagementSection.show();
        refreshFileList();
        refreshQAFileList();
        if ($('#woocommerce-files-list').length) {
            refreshWooCommerceFileList();
        }
    });
    
    // Cache refresh functionality
    function refreshFileCache() {
        var $assistantFilesList = jQuery('#assistant-files-list');
        var $urlProcessingFilesList = jQuery('#url-processing-files-list');
        var $qaFilesList = jQuery('#qa-files-list');
        var $woocommerceFilesList = jQuery('#woocommerce-files-list');
        
        // Show loading state on all lists
        $assistantFilesList.html('<li class="loading">Reloading files…</li>');
        if ($urlProcessingFilesList.length) {
            $urlProcessingFilesList.html('<li class="loading">Refreshing cache...</li>');
        }
        if ($qaFilesList.length) {
            $qaFilesList.html('<li class="loading">Refreshing cache...</li>');
        }
        if ($woocommerceFilesList.length) {
            $woocommerceFilesList.html('<li class="loading">Refreshing cache...</li>');
        }
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpiko_chatbot_refresh_file_cache',
                security: wpikoChatbotAdmin.nonce
            },
            timeout: 60000, // 60 seconds for cache refresh
            success: function(response) {
                if (response.success) {
                    // Refresh all visible file lists to show updated data
                    refreshFileList(); // Main uploaded files
                    
                    if ($urlProcessingFilesList.length) {
                        refreshUrlProcessingFileList(); // Page/content files
                    }
                    if ($qaFilesList.length) {
                        refreshQAFileList(); // Q&A files
                    }
                    if ($woocommerceFilesList.length) {
                        refreshWooCommerceFileList(); // WooCommerce files
                    }
                    
                } else {
                    console.error('Cache refresh failed:', response.data.message);
                    $assistantFilesList.html('<li class="error">Cache refresh failed: ' + response.data.message + '</li>');
                    
                    // Show error on other lists too
                    if ($urlProcessingFilesList.length) {
                        $urlProcessingFilesList.html('<li class="error">Cache refresh failed</li>');
                    }
                    if ($qaFilesList.length) {
                        $qaFilesList.html('<li class="error">Cache refresh failed</li>');
                    }
                    if ($woocommerceFilesList.length) {
                        $woocommerceFilesList.html('<li class="error">Cache refresh failed</li>');
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Cache refresh AJAX error:', textStatus, errorThrown);
                $assistantFilesList.html('<li class="error">Cache refresh failed. Please try again.</li>');
                
                // Show error on other lists too
                if ($urlProcessingFilesList.length) {
                    $urlProcessingFilesList.html('<li class="error">Cache refresh failed</li>');
                }
                if ($qaFilesList.length) {
                    $qaFilesList.html('<li class="error">Cache refresh failed</li>');
                }
                if ($woocommerceFilesList.length) {
                    $woocommerceFilesList.html('<li class="error">Cache refresh failed</li>');
                }
            }
        });
    }
    
    // Add cache refresh button handler (if button exists)
    $(document).on('click', '.refresh-file-cache', function() {
        var $button = $(this).addClass('is-spinning').prop('disabled', true);
        refreshFileCache();
        setTimeout(function() {
            $button.removeClass('is-spinning').prop('disabled', false);
        }, 1500);
    });
    
    // Make functions and variables available globally
    window.wpikoChatbotFileManagement = {
        refreshFileList: refreshFileList,
        refreshUrlProcessingFileList: refreshUrlProcessingFileList,
        refreshQAFileList: refreshQAFileList,
        refreshWooCommerceFileList: refreshWooCommerceFileList,
        refreshFileCache: refreshFileCache
    };   
});
