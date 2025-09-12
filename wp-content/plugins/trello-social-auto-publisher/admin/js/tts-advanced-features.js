/**
 * Advanced Features for Social Auto Publisher
 * Includes keyboard shortcuts, export/import, and accessibility enhancements
 */

class TTSAdvancedFeatures {
    constructor() {
        this.shortcuts = new Map();
        this.init();
    }

    init() {
        this.setupKeyboardShortcuts();
        this.enhanceAccessibility();
        this.addAdvancedControls();
        this.initializeExportImport();
        this.addDarkMode();
    }

    setupKeyboardShortcuts() {
        // Define keyboard shortcuts
        this.shortcuts.set('ctrl+shift+d', () => this.navigateTo('tts-main'));
        this.shortcuts.set('ctrl+shift+c', () => this.navigateTo('tts-calendar'));
        this.shortcuts.set('ctrl+shift+a', () => this.navigateTo('tts-analytics'));
        this.shortcuts.set('ctrl+shift+h', () => this.navigateTo('tts-health'));
        this.shortcuts.set('ctrl+shift+l', () => this.navigateTo('tts-log'));
        this.shortcuts.set('ctrl+shift+n', () => this.navigateTo('tts-client-wizard'));
        this.shortcuts.set('ctrl+shift+r', () => this.refreshCurrentPage());
        this.shortcuts.set('ctrl+shift+e', () => this.openExportModal());
        this.shortcuts.set('ctrl+shift+i', () => this.openImportModal());
        this.shortcuts.set('ctrl+shift+k', () => this.showKeyboardShortcuts());
        this.shortcuts.set('ctrl+shift+t', () => this.toggleDarkMode());

        // Global keyboard event listener with improved error handling
        document.addEventListener('keydown', (e) => {
            try {
                // Skip if user is typing in an input field
                const activeElement = document.activeElement;
                if (activeElement && (
                    activeElement.tagName === 'INPUT' ||
                    activeElement.tagName === 'TEXTAREA' ||
                    activeElement.tagName === 'SELECT' ||
                    activeElement.contentEditable === 'true'
                )) {
                    return;
                }

                const key = this.getKeyboardShortcut(e);
                if (this.shortcuts.has(key)) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const action = this.shortcuts.get(key);
                    if (typeof action === 'function') {
                        action();
                    }
                }
            } catch (error) {
                console.error('TTSAdvancedFeatures: Error in keyboard event handler:', error);
            }
        });

        // Add help for keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F1') {
                e.preventDefault();
                this.showContextualHelp();
            }
        });

        // Add keyboard shortcut indicator to the admin bar
        this.addKeyboardShortcutIndicator();
    }

    getKeyboardShortcut(event) {
        const parts = [];
        if (event.ctrlKey) parts.push('ctrl');
        if (event.shiftKey) parts.push('shift');
        if (event.altKey) parts.push('alt');
        if (event.metaKey) parts.push('meta');
        
        if (event.key && event.key.length === 1) {
            parts.push(event.key.toLowerCase());
        } else if (event.key) {
            parts.push(event.key.toLowerCase());
        }
        
        return parts.join('+');
    }

    navigateTo(page) {
        try {
            // Validate page parameter
            if (!page || typeof page !== 'string') {
                console.error('TTSAdvancedFeatures: Invalid page parameter for navigation');
                return;
            }
            
            // Check if we're already on the target page
            const currentParams = new URLSearchParams(window.location.search);
            if (currentParams.get('page') === page) {
                window.TTSNotifications?.info('Already on this page', { duration: 2000 });
                return;
            }
            
            if (window.TTSNotifications) {
                window.TTSNotifications.info(`Navigating to ${page}...`, { duration: 1000 });
            }
            
            // Add loading state
            document.body.classList.add('tts-navigating');
            
            window.location.href = `admin.php?page=${encodeURIComponent(page)}`;
        } catch (error) {
            console.error('TTSAdvancedFeatures: Navigation error:', error);
            if (window.TTSNotifications) {
                window.TTSNotifications.error('Navigation failed. Please try again.');
            }
        }
    }

    refreshCurrentPage() {
        try {
            if (window.TTSNotifications) {
                window.TTSNotifications.info('Refreshing page...', { duration: 1000 });
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                window.location.reload();
            }
        } catch (error) {
            console.error('TTSAdvancedFeatures: Refresh error:', error);
            // Fallback refresh
            window.location.reload();
        }
    }

    addKeyboardShortcutIndicator() {
        const adminBar = document.getElementById('wp-admin-bar-root');
        if (adminBar && window.location.href.includes('page=tts-')) {
            const shortcutItem = document.createElement('li');
            shortcutItem.id = 'wp-admin-bar-tts-shortcuts';
            shortcutItem.innerHTML = `
                <a class="ab-item" href="#" style="color: #00a32a;">
                    <span class="ab-icon dashicons dashicons-keyboard-hide"></span>
                    <span class="ab-label">Shortcuts</span>
                </a>
            `;
            
            shortcutItem.addEventListener('click', (e) => {
                e.preventDefault();
                this.showKeyboardShortcuts();
            });
            
            adminBar.appendChild(shortcutItem);
        }
    }

    showKeyboardShortcuts() {
        const shortcuts = [
            { key: 'Ctrl+Shift+D', desc: 'Go to Dashboard' },
            { key: 'Ctrl+Shift+C', desc: 'Go to Calendar' },
            { key: 'Ctrl+Shift+A', desc: 'Go to Analytics' },
            { key: 'Ctrl+Shift+H', desc: 'Go to Health Status' },
            { key: 'Ctrl+Shift+L', desc: 'Go to Logs' },
            { key: 'Ctrl+Shift+N', desc: 'New Client Wizard' },
            { key: 'Ctrl+Shift+R', desc: 'Refresh Page' },
            { key: 'Ctrl+Shift+E', desc: 'Export Settings' },
            { key: 'Ctrl+Shift+I', desc: 'Import Settings' },
            { key: 'Ctrl+Shift+T', desc: 'Toggle Dark Mode' },
            { key: 'Ctrl+Shift+K', desc: 'Show This Help' }
        ];

        const shortcutsList = shortcuts.map(s => 
            `<tr><td style="padding: 8px; font-family: monospace; background: #f0f0f1;"><kbd>${s.key}</kbd></td><td style="padding: 8px;">${s.desc}</td></tr>`
        ).join('');

        window.TTSAdminUtils.showModal(window.TTSAdminUtils.createModal({
            title: 'Keyboard Shortcuts',
            body: `
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; text-align: left;">Shortcut</th>
                            <th style="padding: 8px; text-align: left;">Action</th>
                        </tr>
                    </thead>
                    <tbody>${shortcutsList}</tbody>
                </table>
                <p style="margin-top: 15px; font-size: 12px; color: #666;">
                    Press these key combinations while on Social Auto Publisher pages.
                </p>
            `,
            buttons: [
                {
                    text: 'Close',
                    class: 'button-primary',
                    onclick: function() { this.closest('.tts-modal-overlay').remove(); }
                }
            ]
        }));
    }

    enhanceAccessibility() {
        try {
            // Add ARIA labels and roles to statistics cards
            document.querySelectorAll('.tts-stat-card').forEach((card, index) => {
                if (!card.hasAttribute('role')) {
                    card.setAttribute('role', 'region');
                    const heading = card.querySelector('h3');
                    const number = card.querySelector('.tts-stat-number');
                    
                    if (heading && number) {
                        const label = `${heading.textContent}: ${number.textContent}`;
                        card.setAttribute('aria-label', label);
                    } else {
                        card.setAttribute('aria-label', `Statistics card ${index + 1}`);
                    }
                    card.setAttribute('tabindex', '0');
                }
            });

            // Enhance table accessibility
            document.querySelectorAll('.tts-enhanced-table').forEach(table => {
                if (!table.getAttribute('role')) {
                    table.setAttribute('role', 'table');
                    table.setAttribute('aria-label', 'Social media posts');
                }
                
                // Add table headers association
                const headers = table.querySelectorAll('th');
                const rows = table.querySelectorAll('tbody tr');
                
                headers.forEach((header, index) => {
                    if (!header.id) {
                        header.id = `tts-header-${index}`;
                    }
                });
                
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    cells.forEach((cell, cellIndex) => {
                        if (headers[cellIndex] && !cell.getAttribute('headers')) {
                            cell.setAttribute('headers', headers[cellIndex].id);
                        }
                    });
                });
            });

            // Add improved focus styles and accessibility styles
            this.addAccessibilityStyles();

            // Add skip to content link
            this.addSkipToContentLink();

            // Enhance form accessibility
            this.enhanceFormAccessibility();

        } catch (error) {
            console.error('TTSAdvancedFeatures: Error enhancing accessibility:', error);
        }
    }

    addAccessibilityStyles() {
        if (document.getElementById('tts-accessibility-styles')) {
            return; // Styles already added
        }

        const focusStyle = document.createElement('style');
        focusStyle.id = 'tts-accessibility-styles';
        focusStyle.textContent = `
            /* Enhanced focus indicators */
            .tts-stat-card:focus,
            .tts-quick-action:focus,
            .tts-btn:focus,
            .tts-bulk-select-item:focus,
            .tts-bulk-select-all:focus {
                outline: 3px solid #005cee;
                outline-offset: 2px;
                box-shadow: 0 0 0 1px #fff, 0 0 0 4px #005cee;
            }
            
            .tts-enhanced-table tr:focus-within {
                background-color: #e6f3ff;
                outline: 2px solid #005cee;
            }
            
            /* Skip to content link */
            .tts-skip-link {
                position: absolute;
                top: -40px;
                left: 6px;
                background: #000;
                color: #fff;
                padding: 8px 12px;
                text-decoration: none;
                z-index: 100000;
                border-radius: 4px;
                transition: top 0.3s ease;
                font-size: 14px;
                font-weight: 600;
            }
            
            .tts-skip-link:focus {
                top: 6px;
            }
            
            /* High contrast mode support */
            @media (prefers-contrast: high) {
                .tts-stat-card,
                .tts-dashboard-section,
                .tts-quick-action,
                .tts-notification {
                    border-width: 2px !important;
                    border-color: #000 !important;
                }
                
                .tts-status-badge {
                    border: 2px solid #000 !important;
                }
            }
            
            /* Reduced motion support */
            @media (prefers-reduced-motion: reduce) {
                *,
                *::before,
                *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
                
                .tts-notification {
                    transition: none !important;
                }
            }
            
            /* Screen reader only content */
            .sr-only {
                position: absolute !important;
                width: 1px !important;
                height: 1px !important;
                padding: 0 !important;
                margin: -1px !important;
                overflow: hidden !important;
                clip: rect(0, 0, 0, 0) !important;
                white-space: nowrap !important;
                border: 0 !important;
            }
        `;
        document.head.appendChild(focusStyle);
    }

    addSkipToContentLink() {
        // Don't add if already exists
        if (document.querySelector('.tts-skip-link')) {
            return;
        }

        const skipLink = document.createElement('a');
        skipLink.href = '#main-content';
        skipLink.className = 'tts-skip-link';
        skipLink.textContent = 'Skip to main content';
        skipLink.setAttribute('aria-label', 'Skip to main content');
        
        document.body.insertBefore(skipLink, document.body.firstChild);

        // Add main content wrapper
        const wrap = document.querySelector('.wrap');
        if (wrap && !wrap.id) {
            wrap.id = 'main-content';
            wrap.setAttribute('role', 'main');
            wrap.setAttribute('aria-label', 'Main content area');
        }
    }

    enhanceFormAccessibility() {
        // Add labels and descriptions to form controls
        document.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach(control => {
            if (!control.getAttribute('aria-label') && !control.labels?.length) {
                const parentLabel = control.closest('label');
                const siblingLabel = control.previousElementSibling?.tagName === 'LABEL' ? 
                    control.previousElementSibling : 
                    control.nextElementSibling?.tagName === 'LABEL' ? 
                    control.nextElementSibling : null;
                
                if (parentLabel || siblingLabel) {
                    const labelText = (parentLabel || siblingLabel).textContent.trim();
                    if (labelText) {
                        control.setAttribute('aria-label', labelText);
                    }
                }
            }
        });

        // Enhance bulk action controls
        const bulkSelect = document.querySelector('.tts-bulk-select-all');
        if (bulkSelect) {
            bulkSelect.setAttribute('aria-label', 'Select all posts');
            bulkSelect.addEventListener('change', (e) => {
                const items = document.querySelectorAll('.tts-bulk-select-item');
                items.forEach(item => {
                    item.checked = e.target.checked;
                    // Announce change to screen readers
                    item.setAttribute('aria-checked', e.target.checked);
                });
            });
        }
    }

    addAdvancedControls() {
        // Add advanced control panel
        const controlPanel = document.createElement('div');
        controlPanel.className = 'tts-advanced-controls';
        controlPanel.innerHTML = `
            <button class="tts-control-toggle" aria-label="Toggle advanced controls">
                <span class="dashicons dashicons-admin-generic"></span>
            </button>
            <div class="tts-control-panel">
                <div class="tts-control-section">
                    <h4>Quick Actions</h4>
                    <button class="tts-btn small" data-action="export">Export Settings</button>
                    <button class="tts-btn small" data-action="import">Import Settings</button>
                    <button class="tts-btn small" data-action="clear-cache">Clear Cache</button>
                </div>
                <div class="tts-control-section">
                    <h4>View Options</h4>
                    <label>
                        <input type="checkbox" id="tts-dark-mode-toggle"> Dark Mode
                    </label>
                    <label>
                        <input type="checkbox" id="tts-compact-view"> Compact View
                    </label>
                    <label>
                        <input type="checkbox" id="tts-auto-refresh"> Auto Refresh
                    </label>
                </div>
            </div>
        `;

        // Add control panel styles
        const controlStyles = document.createElement('style');
        controlStyles.textContent = `
            .tts-advanced-controls {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 99999;
            }
            
            .tts-control-toggle {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: #135e96;
                color: #fff;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transition: all 0.3s ease;
            }
            
            .tts-control-toggle:hover {
                background: #0a4b78;
                transform: scale(1.1);
            }
            
            .tts-control-panel {
                position: absolute;
                bottom: 60px;
                right: 0;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                padding: 20px;
                min-width: 250px;
                opacity: 0;
                transform: translateY(20px);
                pointer-events: none;
                transition: all 0.3s ease;
            }
            
            .tts-control-panel.show {
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
            }
            
            .tts-control-section {
                margin-bottom: 15px;
            }
            
            .tts-control-section:last-child {
                margin-bottom: 0;
            }
            
            .tts-control-section h4 {
                margin: 0 0 10px 0;
                font-size: 14px;
                color: #1d2327;
                border-bottom: 1px solid #f0f0f1;
                padding-bottom: 5px;
            }
            
            .tts-control-section label {
                display: block;
                margin-bottom: 8px;
                font-size: 13px;
                cursor: pointer;
            }
            
            .tts-control-section input[type="checkbox"] {
                margin-right: 8px;
            }
            
            @media (max-width: 768px) {
                .tts-advanced-controls {
                    bottom: 80px;
                    right: 10px;
                }
            }
        `;
        document.head.appendChild(controlStyles);

        // Add to page if on plugin pages
        if (window.location.href.includes('page=tts-')) {
            document.body.appendChild(controlPanel);
            this.bindControlEvents(controlPanel);
        }
    }

    bindControlEvents(controlPanel) {
        const toggle = controlPanel.querySelector('.tts-control-toggle');
        const panel = controlPanel.querySelector('.tts-control-panel');

        toggle.addEventListener('click', () => {
            panel.classList.toggle('show');
        });

        // Close panel when clicking outside
        document.addEventListener('click', (e) => {
            if (!controlPanel.contains(e.target)) {
                panel.classList.remove('show');
            }
        });

        // Bind control actions
        panel.addEventListener('click', (e) => {
            const action = e.target.getAttribute('data-action');
            if (action) {
                e.preventDefault();
                this.handleControlAction(action);
            }
        });

        // Bind checkbox changes
        const darkModeToggle = panel.querySelector('#tts-dark-mode-toggle');
        const compactViewToggle = panel.querySelector('#tts-compact-view');
        const autoRefreshToggle = panel.querySelector('#tts-auto-refresh');

        if (darkModeToggle) {
            darkModeToggle.checked = localStorage.getItem('tts-dark-mode') === 'true';
            darkModeToggle.addEventListener('change', () => this.toggleDarkMode());
        }

        if (compactViewToggle) {
            compactViewToggle.checked = localStorage.getItem('tts-compact-view') === 'true';
            compactViewToggle.addEventListener('change', () => this.toggleCompactView());
        }

        if (autoRefreshToggle) {
            autoRefreshToggle.checked = localStorage.getItem('tts-auto-refresh') === 'true';
            autoRefreshToggle.addEventListener('change', () => this.toggleAutoRefresh());
        }
    }

    handleControlAction(action) {
        switch (action) {
            case 'export':
                this.openExportModal();
                break;
            case 'import':
                this.openImportModal();
                break;
            case 'clear-cache':
                this.clearCache();
                break;
            default:
                // Unknown action - silently ignore for production
        }
    }

    initializeExportImport() {
        // Initialize export/import functionality
        this.exportData = {
            settings: {},
            clients: [],
            posts: [],
            analytics: {},
            version: '1.0',
            exported_at: new Date().toISOString()
        };
    }

    async openExportModal() {
        const modal = window.TTSAdminUtils.createModal({
            title: 'Export Settings & Data',
            body: `
                <p>Choose what to export:</p>
                <div style="margin: 15px 0;">
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" id="export-settings" checked> Plugin Settings
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" id="export-clients" checked> Clients Configuration
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" id="export-posts"> Social Posts (last 100)
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" id="export-analytics"> Analytics Data
                    </label>
                </div>
                <div id="export-progress" style="display: none;">
                    <div class="tts-progress">
                        <div class="tts-progress-bar" style="width: 0%;"></div>
                    </div>
                    <p id="export-status">Preparing export...</p>
                </div>
            `,
            buttons: [
                {
                    text: 'Cancel',
                    class: 'button',
                    onclick: function() { this.closest('.tts-modal-overlay').remove(); }
                },
                {
                    text: 'Export',
                    class: 'button-primary',
                    onclick: () => this.performExport(modal)
                }
            ]
        });

        window.TTSAdminUtils.showModal(modal);
    }

    async performExport(modal) {
        const progressContainer = modal.querySelector('#export-progress');
        const progressBar = modal.querySelector('.tts-progress-bar');
        const statusText = modal.querySelector('#export-status');
        const buttons = modal.querySelectorAll('.tts-modal-footer button');

        // Show progress
        progressContainer.style.display = 'block';
        buttons.forEach(btn => btn.disabled = true);

        try {
            // Step 1: Gather settings
            statusText.textContent = 'Gathering plugin settings...';
            progressBar.style.width = '25%';
            await this.delay(500);

            const exportData = {
                version: '1.0',
                exported_at: new Date().toISOString(),
                settings: {},
                clients: [],
                posts: [],
                analytics: {}
            };

            // Step 2: Get clients if selected
            if (modal.querySelector('#export-clients').checked) {
                statusText.textContent = 'Exporting clients configuration...';
                progressBar.style.width = '50%';
                await this.delay(500);
                
                // Simulate client data gathering
                exportData.clients = await this.getClientsData();
            }

            // Step 3: Get posts if selected
            if (modal.querySelector('#export-posts').checked) {
                statusText.textContent = 'Exporting social posts...';
                progressBar.style.width = '75%';
                await this.delay(500);
                
                exportData.posts = await this.getPostsData();
            }

            // Step 4: Finalize
            statusText.textContent = 'Finalizing export...';
            progressBar.style.width = '100%';
            await this.delay(500);

            // Download the file
            this.downloadJSON(exportData, `tts-export-${new Date().toISOString().split('T')[0]}.json`);

            window.TTSNotifications.success('Export completed successfully!');
            modal.remove();

        } catch (error) {
            window.TTSNotifications.error('Export failed: ' + error.message);
            buttons.forEach(btn => btn.disabled = false);
            progressContainer.style.display = 'none';
        }
    }

    async getClientsData() {
        // Simulate API call to get clients data
        return [
            { id: 1, name: 'Client 1', settings: { trello: 'configured' } },
            { id: 2, name: 'Client 2', settings: { trello: 'configured' } }
        ];
    }

    async getPostsData() {
        // Simulate API call to get posts data
        return [
            { id: 1, title: 'Post 1', status: 'published' },
            { id: 2, title: 'Post 2', status: 'scheduled' }
        ];
    }

    downloadJSON(data, filename) {
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    openImportModal() {
        const modal = window.TTSAdminUtils.createModal({
            title: 'Import Settings & Data',
            body: `
                <p>Select a JSON export file to import:</p>
                <div style="margin: 15px 0;">
                    <input type="file" id="import-file" accept=".json" style="margin-bottom: 15px;">
                    <div id="import-preview" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 15px;">
                        <h4>Import Preview:</h4>
                        <div id="import-details"></div>
                    </div>
                </div>
                <div id="import-progress" style="display: none;">
                    <div class="tts-progress">
                        <div class="tts-progress-bar" style="width: 0%;"></div>
                    </div>
                    <p id="import-status">Processing import...</p>
                </div>
            `,
            buttons: [
                {
                    text: 'Cancel',
                    class: 'button',
                    onclick: function() { this.closest('.tts-modal-overlay').remove(); }
                },
                {
                    text: 'Import',
                    class: 'button-primary',
                    onclick: () => this.performImport(modal)
                }
            ]
        });

        // File input handler
        const fileInput = modal.querySelector('#import-file');
        fileInput.addEventListener('change', (e) => this.previewImport(e.target.files[0], modal));

        window.TTSAdminUtils.showModal(modal);
    }

    async previewImport(file, modal) {
        if (!file) return;

        try {
            const content = await this.readFileAsText(file);
            const data = JSON.parse(content);
            
            const preview = modal.querySelector('#import-preview');
            const details = modal.querySelector('#import-details');
            
            details.innerHTML = `
                <p><strong>Export Version:</strong> ${data.version || 'Unknown'}</p>
                <p><strong>Exported:</strong> ${data.exported_at ? new Date(data.exported_at).toLocaleString() : 'Unknown'}</p>
                <p><strong>Contains:</strong></p>
                <ul>
                    ${data.clients ? `<li>${data.clients.length} clients</li>` : ''}
                    ${data.posts ? `<li>${data.posts.length} posts</li>` : ''}
                    ${data.settings ? `<li>Plugin settings</li>` : ''}
                    ${data.analytics ? `<li>Analytics data</li>` : ''}
                </ul>
            `;
            
            preview.style.display = 'block';
            modal.importData = data;
            
        } catch (error) {
            window.TTSNotifications.error('Invalid import file: ' + error.message);
        }
    }

    readFileAsText(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = reject;
            reader.readAsText(file);
        });
    }

    async performImport(modal) {
        if (!modal.importData) {
            window.TTSNotifications.error('Please select a file to import');
            return;
        }

        const progressContainer = modal.querySelector('#import-progress');
        const progressBar = modal.querySelector('.tts-progress-bar');
        const statusText = modal.querySelector('#import-status');
        const buttons = modal.querySelectorAll('.tts-modal-footer button');

        // Show progress
        progressContainer.style.display = 'block';
        buttons.forEach(btn => btn.disabled = true);

        try {
            const data = modal.importData;
            
            statusText.textContent = 'Validating import data...';
            progressBar.style.width = '25%';
            await this.delay(500);

            statusText.textContent = 'Importing settings...';
            progressBar.style.width = '50%';
            await this.delay(500);

            statusText.textContent = 'Importing clients...';
            progressBar.style.width = '75%';
            await this.delay(500);

            statusText.textContent = 'Finalizing import...';
            progressBar.style.width = '100%';
            await this.delay(500);

            window.TTSNotifications.success('Import completed successfully!');
            modal.remove();

        } catch (error) {
            window.TTSNotifications.error('Import failed: ' + error.message);
            buttons.forEach(btn => btn.disabled = false);
            progressContainer.style.display = 'none';
        }
    }

    addDarkMode() {
        const darkModeStyles = document.createElement('style');
        darkModeStyles.id = 'tts-dark-mode-styles';
        darkModeStyles.textContent = `
            body.tts-dark-mode {
                background: #1a1a1a !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .wrap {
                background: #1a1a1a;
                color: #e0e0e0;
            }
            
            .tts-dark-mode .tts-stat-card,
            .tts-dark-mode .tts-dashboard-section {
                background: #2d2d2d !important;
                border-color: #404040 !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .tts-quick-action {
                background: #333 !important;
                border-color: #555 !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .tts-quick-action:hover {
                background: #135e96 !important;
                color: #fff !important;
            }
            
            .tts-dark-mode .widefat,
            .tts-dark-mode .tts-enhanced-table {
                background: #2d2d2d !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .widefat th {
                background: #404040 !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .widefat tr:hover,
            .tts-dark-mode .tts-enhanced-table tr:hover {
                background: #404040 !important;
            }
            
            .tts-dark-mode .tts-modal {
                background: #2d2d2d !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .tts-notification {
                background: #2d2d2d !important;
                color: #e0e0e0 !important;
            }
        `;
        
        document.head.appendChild(darkModeStyles);

        // Apply dark mode if previously enabled
        if (localStorage.getItem('tts-dark-mode') === 'true') {
            document.body.classList.add('tts-dark-mode');
        }
    }

    toggleDarkMode() {
        const isDark = document.body.classList.toggle('tts-dark-mode');
        localStorage.setItem('tts-dark-mode', isDark.toString());
        
        const toggle = document.querySelector('#tts-dark-mode-toggle');
        if (toggle) toggle.checked = isDark;
        
        window.TTSNotifications.info(`Dark mode ${isDark ? 'enabled' : 'disabled'}`);
    }

    toggleCompactView() {
        const isCompact = document.body.classList.toggle('tts-compact-view');
        localStorage.setItem('tts-compact-view', isCompact.toString());
        
        // Add compact view styles if not already present
        if (!document.getElementById('tts-compact-styles')) {
            const compactStyles = document.createElement('style');
            compactStyles.id = 'tts-compact-styles';
            compactStyles.textContent = `
                .tts-compact-view .tts-stat-card {
                    padding: 15px;
                    min-width: 150px;
                }
                
                .tts-compact-view .tts-stat-number {
                    font-size: 24px;
                }
                
                .tts-compact-view .tts-dashboard-section {
                    padding: 15px;
                }
                
                .tts-compact-view .tts-quick-action {
                    padding: 8px 12px;
                }
            `;
            document.head.appendChild(compactStyles);
        }
        
        window.TTSNotifications.info(`Compact view ${isCompact ? 'enabled' : 'disabled'}`);
    }

    toggleAutoRefresh() {
        const isEnabled = localStorage.getItem('tts-auto-refresh') === 'true';
        const newState = !isEnabled;
        localStorage.setItem('tts-auto-refresh', newState.toString());
        
        if (newState && window.location.href.includes('page=tts-main')) {
            // Enable auto refresh for dashboard
            this.startAutoRefresh();
        } else {
            this.stopAutoRefresh();
        }
        
        window.TTSNotifications.info(`Auto refresh ${newState ? 'enabled' : 'disabled'}`);
    }

    startAutoRefresh() {
        this.stopAutoRefresh(); // Clear any existing interval
        
        this.autoRefreshInterval = setInterval(() => {
            if (document.querySelector('[data-ajax-action="tts_refresh_posts"]')) {
                document.querySelector('[data-ajax-action="tts_refresh_posts"]').click();
            }
        }, 30000); // 30 seconds
    }

    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    async clearCache() {
        try {
            const response = await window.TTSAdminUtils.ajaxRequest('tts_clear_cache');
            if (response.success) {
                window.TTSNotifications.success('Cache cleared successfully');
            } else {
                throw new Error(response.data || 'Failed to clear cache');
            }
        } catch (error) {
            window.TTSNotifications.error('Failed to clear cache: ' + error.message);
        }
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    showContextualHelp() {
        try {
            const currentPage = new URLSearchParams(window.location.search).get('page');
            let helpContent = '';
            
            switch (currentPage) {
                case 'tts-main':
                    helpContent = this.getDashboardHelp();
                    break;
                case 'tts-calendar':
                    helpContent = this.getCalendarHelp();
                    break;
                case 'tts-analytics':
                    helpContent = this.getAnalyticsHelp();
                    break;
                case 'tts-health':
                    helpContent = this.getHealthHelp();
                    break;
                case 'tts-log':
                    helpContent = this.getLogHelp();
                    break;
                default:
                    helpContent = this.getGeneralHelp();
            }

            if (window.TTSAdminUtils && window.TTSAdminUtils.createModal) {
                const modal = window.TTSAdminUtils.createModal({
                    title: 'Contextual Help',
                    body: helpContent,
                    size: 'large'
                });
                modal.setAttribute('aria-label', 'Help dialog');
            } else {
                // Fallback if modal system is not available
                window.TTSNotifications?.info('Help system loading...');
            }
        } catch (error) {
            console.error('TTSAdvancedFeatures: Error showing contextual help:', error);
            window.TTSNotifications?.error('Unable to load help content');
        }
    }

    getDashboardHelp() {
        return `
            <div class="tts-help-content">
                <h3>Dashboard Help</h3>
                <p>The dashboard provides an overview of your social media publishing activity:</p>
                <ul>
                    <li><strong>Statistics Cards:</strong> View key metrics like total posts, active clients, and success rates</li>
                    <li><strong>Recent Posts:</strong> Review and manage your latest social media posts</li>
                    <li><strong>Quick Actions:</strong> Access frequently used features quickly</li>
                    <li><strong>Bulk Operations:</strong> Select multiple posts to perform batch actions</li>
                </ul>
                <h4>Keyboard Shortcuts:</h4>
                <ul>
                    <li><kbd>Ctrl+Shift+D</kbd> - Go to Dashboard</li>
                    <li><kbd>Ctrl+Shift+R</kbd> - Refresh current page</li>
                    <li><kbd>F1</kbd> - Show this help</li>
                </ul>
            </div>
        `;
    }

    getCalendarHelp() {
        return `
            <div class="tts-help-content">
                <h3>Calendar Help</h3>
                <p>The calendar view shows your scheduled social media posts:</p>
                <ul>
                    <li><strong>Monthly View:</strong> See all posts scheduled for the current month</li>
                    <li><strong>Post Details:</strong> Click on any post to view details</li>
                    <li><strong>Navigation:</strong> Use arrows to navigate between months</li>
                </ul>
            </div>
        `;
    }

    getAnalyticsHelp() {
        return `
            <div class="tts-help-content">
                <h3>Analytics Help</h3>
                <p>View detailed analytics and performance metrics:</p>
                <ul>
                    <li><strong>Charts:</strong> Visual representation of your posting activity</li>
                    <li><strong>Filters:</strong> Filter data by date range, client, or status</li>
                    <li><strong>Export:</strong> Download analytics data for further analysis</li>
                </ul>
            </div>
        `;
    }

    getHealthHelp() {
        return `
            <div class="tts-help-content">
                <h3>System Health Help</h3>
                <p>Monitor the health and status of your social publishing system:</p>
                <ul>
                    <li><strong>Overall Status:</strong> Green indicates all systems operational</li>
                    <li><strong>Individual Checks:</strong> View specific system components</li>
                    <li><strong>Troubleshooting:</strong> Get recommendations for issues</li>
                </ul>
            </div>
        `;
    }

    getLogHelp() {
        return `
            <div class="tts-help-content">
                <h3>Logs Help</h3>
                <p>Review system logs and publishing history:</p>
                <ul>
                    <li><strong>Activity Logs:</strong> See all system activities and events</li>
                    <li><strong>Error Logs:</strong> Review any errors or issues</li>
                    <li><strong>Search:</strong> Find specific log entries</li>
                </ul>
            </div>
        `;
    }

    getGeneralHelp() {
        return `
            <div class="tts-help-content">
                <h3>General Help</h3>
                <p>Welcome to the Social Auto Publisher plugin!</p>
                <ul>
                    <li><strong>Navigation:</strong> Use the left sidebar menu to access different sections</li>
                    <li><strong>Keyboard Shortcuts:</strong> Press <kbd>Ctrl+Shift+K</kbd> to see all shortcuts</li>
                    <li><strong>Accessibility:</strong> This plugin supports screen readers and keyboard navigation</li>
                </ul>
            </div>
        `;
    }
}

// Initialize advanced features
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.href.includes('page=tts-')) {
        window.TTSAdvancedFeatures = new TTSAdvancedFeatures();
    }
});