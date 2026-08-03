/**
 * Admin Panel Enhancements
 * Auto-refresh for submissions and overview
 */

// ==================== Auto-Refresh System ====================

class AdminAutoRefresh {
    constructor() {
        this.pollInterval = 5000; // 5 seconds
        this.isPolling = false;
        this.pollTimer = null;
        this.lastChecked = {};
    }

    start() {
        if (this.isPolling) return;
        this.isPolling = true;
        this.poll();
    }

    stop() {
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
        this.isPolling = false;
    }

    async poll() {
        if (!this.isPolling) return;

        try {
            // Check for status updates
            const response = await fetch('/api/videos/status-check');
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.videos) {
                    this.handleUpdates(data.videos);
                }
            }
        } catch (error) {
            // Silent fail - retry on next poll
        }

        // Schedule next poll
        this.pollTimer = setTimeout(() => this.poll(), this.pollInterval);
    }

    handleUpdates(videos) {
        let hasChanges = false;

        videos.forEach(video => {
            const lastStatus = this.lastChecked[video.id];
            if (lastStatus && lastStatus !== video.status) {
                hasChanges = true;
                this.showNotification(video, lastStatus);
            }
            this.lastChecked[video.id] = video.status;
        });

        if (hasChanges) {
            this.refreshAdminView();
        }
    }

    showNotification(video, oldStatus) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: white;
            border-left: 4px solid ${this.getStatusColor(video.status)};
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 99999;
            min-width: 320px;
            animation: slideIn 0.3s ease;
        `;

        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 24px;">${this.getStatusIcon(video.status)}</span>
                <div>
                    <strong style="font-size: 14px; color: #111;">${video.title || 'Video'}</strong>
                    <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0 0;">
                        ${oldStatus} → <strong>${video.status}</strong>
                    </p>
                </div>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    refreshAdminView() {
        // Trigger Alpine.js refresh if available
        if (window.Alpine && window.Alpine.store) {
            // Refresh submissions
            if (typeof window.loadSubmissions === 'function') {
                window.loadSubmissions();
            }
            // Refresh videos
            if (typeof window.fetchVideos === 'function') {
                window.fetchVideos();
            }
        }

        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('admin-data-updated'));
    }

    getStatusIcon(status) {
        const icons = {
            'pending': '⏳',
            'processing': '🔄',
            'approved': '✅',
            'rejected': '❌',
            'flagged': '🚩',
            'published': '📺'
        };
        return icons[status] || '•';
    }

    getStatusColor(status) {
        const colors = {
            'pending': '#f59e0b',
            'processing': '#3b82f6',
            'approved': '#10b981',
            'rejected': '#ef4444',
            'flagged': '#f59e0b',
            'published': '#8b5cf6'
        };
        return colors[status] || '#6b7280';
    }
}

// Add animations
const style = document.createElement('style');
style.textContent = `
@keyframes slideIn {
    from { transform: translateX(400px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(400px); opacity: 0; }
}
`;
document.head.appendChild(style);

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Start auto-refresh
    window.adminAutoRefresh = new AdminAutoRefresh();
    window.adminAutoRefresh.start();

    // Stop when leaving page
    window.addEventListener('beforeunload', () => {
        window.adminAutoRefresh.stop();
    });

    // Pause/resume on visibility change
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            window.adminAutoRefresh.stop();
        } else {
            window.adminAutoRefresh.start();
        }
    });
});
