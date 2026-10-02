jQuery(document).ready(function($) {
    function initializeFileManagement() {
        var $container = $('#file-management-container');
        
        // Check if container exists
        if (!$container.length) {
            return;
        }

        // Cache DOM elements
        var $fileManagementSection = $('#file-management-section');
        var $fileUploadStatus = $('#file-upload-status');
        var $assistantFilesList = $('#assistant-files-list');
        var $uploadButton = $('#upload_file');
        var $fileInput = $('#assistant_file');

        // Unbind existing events to prevent duplicates
        $uploadButton.off('click');

        function showStatus(message, type) {
            $fileUploadStatus
                .text(message)
                .removeClass('error success info')
                .addClass(type)
                .show();
            
            if (type !== 'error' && type !== 'info') {
                setTimeout(function() {
                    $fileUploadStatus.fadeOut();
                }, 3000);
            }
        }

        function createProgressElement(file) {
            var $progress = $('<div class="file-progress">')
                .append(
                    $('<div class="file-progress-header">')
                        .append($('<span class="file-name">').text(file.name))
                        .append($('<span class="progress-status">').text('0%'))
                )
                .append(
                    $('<div class="progress-bar">')
                        .append($('<div class="progress-bar-fill">').css('width', '0%'))
                );
            return $progress;
        }

        function updateProgress($progress, percent, status) {
            $progress.find('.progress-status').text(status || percent + '%');
            $progress.find('.progress-bar-fill').css('width', percent + '%');
        }

        function validateFile(file) {
            var maxSize = 20 * 1024 * 1024; // 20MB max size
            var allowedTypes = [
                'text/plain', 
                'text/markdown', 
                'application/json',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/pdf',
                'text/html'
            ];
            var allowedExtensions = ['.txt', '.md', '.json', '.doc', '.docx', '.pdf', '.html'];
            var fileExtension = '.' + file.name.split('.').pop().toLowerCase();

            if (file.size > maxSize) {
                return 'File size exceeds 20MB limit.';
            }

            if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
                return 'Only .txt, .md, .json, .doc, .docx, .pdf, and .html files are allowed.';
            }

            return null;
        }

        async function uploadFile(file, $progress) {
            return new Promise((resolve, reject) => {
                var formData = new FormData();
                // Route upload to appropriate handler based on selected API type
                var actionName = (window.wpikoChatbotAdmin && wpikoChatbotAdmin.apiType === 'responses')
                    ? 'wpiko_chatbot_upload_file_responses'
                    : 'wpiko_chatbot_upload_file';
                formData.append('action', actionName);
                formData.append('security', wpikoChatbotAdmin.nonce);
                formData.append('file', file);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(evt) {
                            if (evt.lengthComputable) {
                                var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                                updateProgress($progress, percentComplete);
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        if (response.success) {
                            $progress.addClass('success');
                            updateProgress($progress, 100, 'Completed');
                            resolve(response);
                        } else {
                            $progress.addClass('error');
                            updateProgress($progress, 0, 'Failed');
                            reject(response.data ? response.data.message : 'Unknown error');
                        }
                    },
                    error: function(xhr, status, error) {
                        $progress.addClass('error');
                        updateProgress($progress, 0, 'Failed');
                        reject(error);
                    }
                });
            });
        }

        // File Upload Handler
        $uploadButton.on('click', async function(e) {
            e.preventDefault();
            var files = Array.from($fileInput[0].files);

            if (files.length === 0) {
                showStatus('Please select files to upload.', 'error');
                return;
            }

            // Clear previous progress elements
            $('#file-upload-progress').empty();
            $uploadButton.prop('disabled', true);

            try {
                for (let file of files) {
                    // Validate file
                    const error = validateFile(file);
                    if (error) {
                        showStatus(`Error with ${file.name}: ${error}`, 'error');
                        continue;
                    }

                    // Create and append progress element
                    const $progress = createProgressElement(file);
                    $('#file-upload-progress').append($progress);

                    // Upload file
                    try {
                        await uploadFile(file, $progress);
                    } catch (error) {
                        console.error('Upload error:', error);
                    }
                }

                // Refresh file list
                if (typeof wpikoChatbotFileManagement !== 'undefined') {
                    wpikoChatbotFileManagement.refreshFileList();
                }

                // Clear file input
                $fileInput.val('');
            } catch (error) {
                console.error('Upload process error:', error);
            } finally {
                $uploadButton.prop('disabled', false);
            }
        });

        // File Input Change Handler
        $fileInput.on('change', function() {
            var fileCount = this.files.length;
            var fileText = fileCount === 0 ? 'No files chosen' :
                          fileCount === 1 ? this.files[0].name :
                          fileCount + ' files selected';
            $(this).next('.file-name').text(fileText);
        });

        // Drag and Drop Handlers
        var $dropZone = $('#file-drop-zone');
        if ($dropZone.length) {
            $dropZone
                .on('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('dragover');
                })
                .on('dragleave', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('dragover');
                })
                .on('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('dragover');

                    var files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        $fileInput[0].files = files;
                        $fileInput.trigger('change');
                    }
                });
        }

        // Initialize the file list if section is visible
        if ($fileManagementSection.is(':visible')) {
            if (typeof wpikoChatbotFileManagement !== 'undefined') {
                wpikoChatbotFileManagement.refreshFileList();
            }
        }
    }

    // Initialize on document ready and when modal content is loaded
    $(document).on('fileManagementLoaded', initializeFileManagement);
    initializeFileManagement();

    // Make functions available globally
    window.wpikoChatbotFileManagement = {
        ...window.wpikoChatbotFileManagement, // Preserve existing methods
        initializeFileManagement: initializeFileManagement
    };
});
