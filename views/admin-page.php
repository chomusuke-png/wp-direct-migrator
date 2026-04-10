<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1>WP Direct Migrator</h1>
    
    <style>
        .wdm-container { background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 700px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .wdm-log-container { margin-top: 20px; height: 400px; overflow-y: auto; background: #1e1e1e; padding: 15px; border-radius: 4px; }
        .wdm-log-item { margin-bottom: 8px; font-family: monospace; font-size: 13px; line-height: 1.4; }
        .wdm-error { color: #ff5f56; }
        .wdm-success { color: #27c93f; }
        .wdm-info { color: #ffbd2e; }
        .wdm-actions { margin-top: 15px; display: flex; gap: 10px; }
        .wdm-separator { margin: 20px 0; border-top: 1px solid #eee; }
    </style>

    <div class="wdm-container">
        <p>Enter the base URL of the remote WordPress site to migrate posts and images natively.</p>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="wdm_target_url">Remote Site URL</label></th>
                <td>
                    <input type="url" id="wdm_target_url" class="regular-text" placeholder="https://example.com" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wdm_start_page">Start Page</label></th>
                <td>
                    <input type="number" id="wdm_start_page" class="small-text" value="1" min="1" required>
                    <p class="description">Useful for resuming a stopped migration.</p>
                </td>
            </tr>
        </table>
        <div class="wdm-actions">
            <button type="button" id="wdm_start_btn" class="button button-primary">Start Migration</button>
            <button type="button" id="wdm_stop_btn" class="button button-secondary" disabled>Stop Migration</button>
        </div>
        
        <div class="wdm-separator"></div>
        
        <h3>Maintenance</h3>
        <p class="description">Scan and permanently delete junk images (like lazy-load spinners) that were accidentally imported.</p>
        <div class="wdm-actions">
            <button type="button" id="wdm_cleanup_btn" class="button button-secondary">Clean Up Junk Images</button>
        </div>
    </div>

    <div class="wdm-log-container" id="wdm_log_container" style="display:none;">
        <div id="wdm_log_output"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startBtn     = document.getElementById('wdm_start_btn');
            const stopBtn      = document.getElementById('wdm_stop_btn');
            const cleanupBtn   = document.getElementById('wdm_cleanup_btn');
            const urlInput     = document.getElementById('wdm_target_url');
            const pageInput    = document.getElementById('wdm_start_page');
            const logContainer = document.getElementById('wdm_log_container');
            const logOutput    = document.getElementById('wdm_log_output');
            
            let isMigrating = false;

            function appendLog(message, type = 'wdm-info') {
                const el = document.createElement('div');
                el.className = 'wdm-log-item ' + type;
                const time = new Date().toLocaleTimeString();
                el.textContent = `[${time}] ${message}`;
                logOutput.appendChild(el);
                logContainer.scrollTop = logContainer.scrollHeight;
            }

            function toggleButtons(running) {
                startBtn.disabled   = running;
                stopBtn.disabled    = !running;
                cleanupBtn.disabled = running;
                urlInput.readOnly   = running;
                pageInput.readOnly  = running;
            }

            function processBatch(url, page) {
                if (!isMigrating) {
                    appendLog('Migration forcefully stopped by user.', 'wdm-info');
                    toggleButtons(false);
                    pageInput.value = page;
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'wdm_run_migration');
                formData.append('nonce', '<?php echo wp_create_nonce("wdm_migration_nonce"); ?>');
                formData.append('target_url', url);
                formData.append('page', page);

                appendLog(`Fetching page ${page}...`, 'wdm-info');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(response => {
                    if (!response.success) {
                        appendLog(`Fatal Error: ${response.data.message}`, 'wdm-error');
                        isMigrating = false;
                        toggleButtons(false);
                        return;
                    }

                    const data = response.data;
                    appendLog(`Page ${page} of ${data.total_pages} processed. Imported ${data.imported} posts.`, 'wdm-success');

                    if (data.skipped && data.skipped.length > 0) {
                        data.skipped.forEach(skipMsg => {
                            appendLog(`SKIPPED: ${skipMsg}`, 'wdm-error');
                        });
                    }

                    if (data.next_page && isMigrating) {
                        appendLog(`Cooling down server for 2 seconds...`, 'wdm-info');
                        setTimeout(function() {
                            if (isMigrating) {
                                processBatch(url, data.next_page);
                            }
                        }, 2000);
                    } else if (!data.next_page) {
                        appendLog('Migration completed successfully!', 'wdm-success');
                        isMigrating = false;
                        toggleButtons(false);
                    } else if (!isMigrating) {
                        appendLog('Migration stopped. You can resume later.', 'wdm-info');
                        toggleButtons(false);
                        pageInput.value = data.next_page;
                    }
                })
                .catch(error => {
                    appendLog(`Request failed: ${error.message}`, 'wdm-error');
                    isMigrating = false;
                    toggleButtons(false);
                });
            }

            function runCleanupBatch(totalDeleted) {
                const formData = new FormData();
                formData.append('action', 'wdm_cleanup_media');
                formData.append('nonce', '<?php echo wp_create_nonce("wdm_migration_nonce"); ?>');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(response => {
                    if (!response.success) {
                        appendLog(`Cleanup Error: ${response.data.message}`, 'wdm-error');
                        cleanupBtn.disabled = false;
                        startBtn.disabled   = false;
                        return;
                    }

                    const data = response.data;
                    const currentDeleted = data.deletedCount;
                    
                    if (data.deletedItems && data.deletedItems.length > 0) {
                        data.deletedItems.forEach(item => {
                            appendLog(`Deleted junk image: ${item}`, 'wdm-info');
                        });
                    }

                    if (!data.completed) {
                        runCleanupBatch(totalDeleted + currentDeleted);
                    } else {
                        appendLog(`Cleanup finished. Total junk images removed: ${totalDeleted + currentDeleted}`, 'wdm-success');
                        cleanupBtn.disabled = false;
                        startBtn.disabled   = false;
                    }
                })
                .catch(error => {
                    appendLog(`Cleanup request failed: ${error.message}`, 'wdm-error');
                    cleanupBtn.disabled = false;
                    startBtn.disabled   = false;
                });
            }

            startBtn.addEventListener('click', function() {
                const url = urlInput.value.trim();
                const startPage = parseInt(pageInput.value, 10);
                
                if (!url) {
                    alert('Please enter a valid URL.');
                    return;
                }
                
                if (isNaN(startPage) || startPage < 1) {
                    alert('Please enter a valid start page.');
                    return;
                }

                isMigrating = true;
                toggleButtons(true);
                logContainer.style.display = 'block';
                logOutput.innerHTML = '';
                appendLog(`Starting migration from ${url} at page ${startPage}`, 'wdm-info');
                
                processBatch(url, startPage);
            });

            stopBtn.addEventListener('click', function() {
                if (isMigrating) {
                    appendLog('Stopping migration... please wait for the current batch to finish.', 'wdm-info');
                    isMigrating = false;
                    stopBtn.disabled = true;
                }
            });

            cleanupBtn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to scan and delete junk images from your Media Library? This action cannot be undone.')) {
                    return;
                }

                logContainer.style.display = 'block';
                logOutput.innerHTML = '';
                appendLog('Starting media cleanup. Scanning for lazy-load placeholders...', 'wdm-info');
                
                cleanupBtn.disabled = true;
                startBtn.disabled   = true;
                
                runCleanupBatch(0);
            });
        });
    </script>
</div>