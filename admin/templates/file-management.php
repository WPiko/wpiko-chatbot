<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
?>

<!-- File Management Content -->
<div id="file-management-container">
    <div id="file-management-section">
        <h3><span class="dashicons dashicons-upload"></span> Upload Files</h3>
        <div id="file-management-content">
            <p class="description">Upload files to the AI Assistant knowledge base. Supported: PDF (.pdf), text (.txt), JSON (.json), Word (.doc, .docx), HTML (.html), and Markdown (.md).</p>
            <div class="file-upload-container">
                <input type="file" name="assistant_file" id="assistant_file" accept=".txt,.pdf,.json,.doc,.docx,.html,.md" multiple>
                <button type="button" id="upload_file" class="button button-primary">Upload Files</button>
            </div>
            <div id="file-upload-status" style="display: none;"></div>
            <div id="file-upload-progress" class="upload-progress-container"></div>
            
            <div class="uploaded-files-section">
                <div id="file-list-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="margin: 0;">Uploaded Files List</h3>
                        <div class="refresh-cache-container">
                            <button type="button" class="refresh-file-cache button button-secondary" title="Refresh file cache for better performance">
                                ↻ Refresh Cache
                            </button>
                            <div class="info-icon-container">
                                <span class="dashicons dashicons-info-outline"></span>
                                <div class="tooltip">
                                    <strong>When to use Refresh Cache:</strong><br>
                                    • Files appear outdated or missing<br>
                                    • After uploading/deleting files externally<br>
                                    • File list seems incorrect or incomplete<br>
                                    • Performance metrics show unexpected results
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="description">View and manage uploaded knowledge files.</p>
                    <ul id="assistant-files-list">
                        <!-- List items will be dynamically populated with this structure:
                        <li>
                            <div class="file-info">
                                <span class="file-name"></span>
                            </div>
                            <span class="qa-status active">Active</span>
                            <button type="button" class="delete-file" title="Delete file"></button>
                        </li>
                        -->
                    </ul>
                </div>
            </div>
        </div> 
    </div>
</div>
