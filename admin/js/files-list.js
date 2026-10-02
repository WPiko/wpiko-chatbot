jQuery(document).ready(function($) {

// Uploaded Files list
function refreshFileList(retryCount = 0) {
    var $assistantFilesList = jQuery('#assistant-files-list');
    $assistantFilesList.html('<li class="loading">Loading...</li>');

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
                files.forEach(function(file) {
                    // Skip files that start with "page_", "qa_data_" or "woocommerce_"
                    if (!file.filename.startsWith('page_') && !file.filename.startsWith('qa_data_') && !file.filename.startsWith('woocommerce_')) {
                        hasFiles = true;
                        var fileSize = (file.bytes / 1024).toFixed(2) + ' KB';
                        var fileDate = new Date(file.created_at * 1000).toLocaleString();
                        var listItem = $(
                            '<li>' +
                            '<div class="file-info">' +
                            '<span class="file-name">' + file.filename + '</span>' +
                            '<span class="file-meta">(' + fileSize + ') - Uploaded on ' + fileDate + '</span>' +
                            '</div>' +
                            '<span class="qa-status active">Active</span>' +
                            '<button type="button" class="delete-file" data-file-id="' + file.id + '"></button>' +
                            '</li>'
                        );
                        $assistantFilesList.append(listItem);
                    }
                });
                if (!hasFiles) {
                    $assistantFilesList.html('<li class="empty-list">Empty List</li>');
                } else {
                    // Show file count and performance info
                    var uploadedFiles = files.filter(function(file) {
                        return !file.filename.startsWith('page_') && !file.filename.startsWith('qa_data_') && !file.filename.startsWith('woocommerce_');
                    });
                    var totalFiles = uploadedFiles.length;
                    
                    if (totalFiles > 0) {
                        var perfMsg = 'Loaded ' + totalFiles + ' uploaded files';
                        if (performance && performance.file_breakdown) {
                            // Use accurate file breakdown from backend
                            var uploadedFromAPI = performance.file_breakdown.uploaded_files;
                            var cacheRatio = performance.cached_files / performance.total_files;
                            var cachedUploadedFiles = Math.round(uploadedFromAPI * cacheRatio);
                            var apiUploadedFiles = uploadedFromAPI - cachedUploadedFiles;
                            
                            perfMsg += ' (' + cachedUploadedFiles + ' from cache, ' + apiUploadedFiles + ' from API)';
                        } else if (performance) {
                            // Fallback to estimation method
                            var uploadedFileRatio = totalFiles / performance.total_files;
                            var cachedUploadedFiles = Math.round(performance.cached_files * uploadedFileRatio);
                            var apiUploadedFiles = Math.round(performance.api_fetched * uploadedFileRatio);
                            perfMsg += ' (' + cachedUploadedFiles + ' from cache, ' + apiUploadedFiles + ' from API)';
                        }
                        console.log(perfMsg);
                        
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
        console.log('URL processing files list element not found');
        return;
    }
    
    $urlProcessingFilesList.html('<li class="loading">Loading...</li>');

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

                    var listItem = $(
                        '<li>' +
                        '<div class="file-info">' +
                        '<span class="file-name">' + displayName + '</span>' +
                        '<span class="file-meta"> (' + fileSize + ') - Uploaded on ' + fileDate + '</span>' +
                        '</div>' +
                        '<span class="qa-status active">Active</span>' +
                        '<button class="delete-page-file" data-file-id="' + file.id + '"></button>' +
                        '</li>'
                    );
                    $urlProcessingFilesList.append(listItem);
                });
                
                if (!hasUrlProcessingFiles) {
                    $urlProcessingFilesList.html('<li class="empty-list"><div class="file-info">No scanned pages found</div><span class="qa-status disabled">Disabled</span></li>');
                } else {
                    // Show page file count and performance info
                    var pageFileCount = pageFiles.length;
                    var perfMsg = 'Loaded ' + pageFileCount + ' scanned page files';
                    if (performance && performance.file_breakdown) {
                        // Use accurate count from backend
                        var actualPageFiles = performance.file_breakdown.page_files;
                        perfMsg = 'Loaded ' + actualPageFiles + ' scanned page files (optimized loading)';
                    } else if (performance) {
                        perfMsg += ' (optimized loading)';
                    }
                    console.log(perfMsg);
                    
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
    $qaFilesList.html('<li class="loading">Loading...</li>');

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
                    var listItem = $(
                        '<li>' +
                        '<div class="file-info">' +
                        '<span class="file-name">Questions & Answers</span>' +
                        '<span class="file-meta"> (' + fileSize + ') - Uploaded on ' + fileDate + '</span>' +
                        '</div>' +
                        '<span class="qa-status active">Active</span>' +
                        '</li>'
                    );
                    $qaFilesList.append(listItem);
                });
                
                if (!hasQAFiles) {
                    $qaFilesList.html('<li class="empty-list"><div class="file-info">No Q&A Files Found</div><span class="qa-status disabled">Disabled</span></li>');
                } else {
                    // Show Q&A file count and performance info
                    var qaFileCount = qaFiles.length;
                    var perfMsg = 'Loaded ' + qaFileCount + ' Q&A files';
                    if (performance && performance.file_breakdown) {
                        // Use accurate count from backend
                        var actualQAFiles = performance.file_breakdown.qa_files;
                        perfMsg = 'Loaded ' + actualQAFiles + ' Q&A files (optimized loading)';
                    } else if (performance) {
                        perfMsg += ' (optimized loading)';
                    }
                    console.log(perfMsg);
                    
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
    $woocommerceFilesList.html('<li class="loading">Loading...</li>');

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
                    var listItem = $(
                        '<li>' +
                        '<div class="file-info">' +
                        '<span class="file-name">' + displayName + '</span>' +
                        '<span class="file-meta"> (' + fileSize + ') - Uploaded on ' + fileDate + '</span>' +
                        '</div>' +
                        '<span class="qa-status active">Active</span>' +
                        '<button class="delete-woo-file" data-file-id="' + file.id + '">×</button>' +
                        '</li>'
                    );
                    $woocommerceFilesList.append(listItem);
                });
                
                if (!hasWooCommerceFiles) {
                    $woocommerceFilesList.html('<li class="empty-list"><div class="file-info">No WooCommerce Files Found</div><span class="qa-status disabled">Disabled</span></li>');
                } else {
                    // Show WooCommerce file count and performance info
                    var wooFileCount = wooFiles.length;
                    var perfMsg = 'Loaded ' + wooFileCount + ' WooCommerce files';
                    if (performance && performance.file_breakdown) {
                        // Use accurate count from backend
                        var actualWooFiles = performance.file_breakdown.woocommerce_files;
                        perfMsg = 'Loaded ' + actualWooFiles + ' WooCommerce files (optimized loading)';
                    } else if (performance) {
                        perfMsg += ' (optimized loading)';
                    }
                    console.log(perfMsg);
                    
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
function deleteFile(fileId, refreshFunction) {
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
                alert('File deleted successfully');
                refreshFunction();
            } else {
                alert('Error deleting file: ' + (response.data ? response.data.message : 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            // Check if the file was actually deleted despite the AJAX error
            refreshFunction();
            alert('The file may have been deleted, but there was an error in the response. Please check and try again if necessary.');
        }
    });
}

    // Event listener for Uploaded file delete buttons
    $(document).on('click', '.delete-file', function(e) {
        e.preventDefault(); // Prevent any default action
        var fileId = $(this).data('file-id');
        if (confirm('Are you sure you want to delete this file?')) {
            deleteFile(fileId, refreshFileList);
        }
    });

    // Event listener for Content processing file delete buttons
    $(document).on('click', '.delete-page-file', function(e) {
        e.preventDefault(); // Prevent any default action
        var fileId = $(this).data('file-id');
        if (confirm('Are you sure you want to delete this page file?')) {
            deleteFile(fileId, refreshUrlProcessingFileList);
        }
    });
    
    // Event listener for Q&A file delete buttons
    $(document).on('click', '.delete-qa-file', function(e) {
        e.preventDefault();
        var fileId = $(this).data('file-id');
        if (confirm('Are you sure you want to delete this Q&A file?')) {
            deleteFile(fileId, refreshQAFileList);
        }
    });

    // Event listener for WooCommerce file delete buttons
    $(document).on('click', '.delete-woo-file', function(e) {
        e.preventDefault(); // Prevent any default action
        var fileId = $(this).data('file-id');
        if (confirm('Are you sure you want to delete this WooCommerce file?')) {
            deleteFile(fileId, refreshWooCommerceFileList);
        }
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
        $assistantFilesList.html('<li class="loading">Refreshing cache...</li>');
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
                    console.log('File cache refreshed:', response.data.message);
                    
                    if (response.data.performance) {
                        console.log('Cache refresh performance: ' + response.data.performance.total_files + ' total files loaded from API');
                        if (response.data.performance.file_breakdown) {
                            var breakdown = response.data.performance.file_breakdown;
                            console.log('File breakdown: ' + breakdown.uploaded_files + ' uploaded, ' + breakdown.page_files + ' pages, ' + breakdown.qa_files + ' Q&A, ' + breakdown.woocommerce_files + ' WooCommerce');
                        }
                    }
                    
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
        refreshFileCache();
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
