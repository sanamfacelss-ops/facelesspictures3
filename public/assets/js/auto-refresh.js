/**
 * Auto-Refresh System for Video Status Updates
 * 
 * Automatically refreshes video lists when AI processing completes
 * Works on all pages - dashboard, moderation, admin, etc.
 * Silent refresh - no page reload, just updates the data
 */

class VideoStatusRefresher {
    constructor(options = {}) {
        this.pollInterval = options.pollInterval || 5000; // 5 seconds default
        this.maxRetries = options.maxRetries || 3;
        this.retryCount = 0;
        this.isPolling = false;
        this.pollTimer = null;
        this.lastKnownStatuses = new Map(); // Track status changes
        this.onStatusChange = options.onStatusChange || null;
    }

    /**
     * Start polling for status updates
     */
    start() {
        if (this.isPolling) {
            console.log('[Auto-Refresh] Already polling');
            return;
        }

        console.log('[Auto-Refresh] Starting status polling...');
        this.isPolling = true;
        this.poll();
    }

    /**
     * Stop polling
     */
    stop() {
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
        this.isPolling = false;
        console.log('[Auto-Refresh] Stopped polling');
    }

    /**
     * Poll for status updates
     */
    async poll() {
        if (!this.isPolling) return;

        try {
            const response = await fetch('/api/videos/status-check', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success && data.videos) {
                this.handleStatusUpdates(data.videos);
                this.retryCount = 0; // Reset on success
            }

        } catch (error) {
            console.error('[Auto-Refresh] Poll error:', error);
            this.retryCount++;

            if (this.retryCount >= this.maxRetries) {
                console.error('[Auto-Refresh] Max retries reached, stopping');
                this.stop();
                return;
            }
        }

        // Schedule next poll
        this.pollTimer = setTimeout(() => this.poll(), this.pollInterval);
    }

    /**
     * Handle status updates from server
     */
    handleStatusUpdates(videos) {
        let hasChanges = false;

        videos.forEach(video => {
            const videoId = video.id;
            const currentStatus = video.status;
            const lastStatus = this.lastKnownStatuses.get(videoId);

            // Check if status changed
            if (lastStatus && lastStatus !== currentStatus) {
                console.log(`[Auto-Refresh] Video ${videoId} status: ${lastStatus} → ${currentStatus}`);
                hasChanges = true;

                // Call custom callback if provided
                if (this.onStatusChange) {
                    this.onStatusChange(video, lastStatus, currentStatus);
                }

                // Show notification
                this.showNotification(video, lastStatus, currentStatus);

                // Update UI element if exists
                this.updateUIElement(video);
            }

            // Store current status
            this.lastKnownStatuses.set(videoId, currentStatus);
        });

        // If changes detected, refresh the current view
        if (hasChanges) {
            this.refreshCurrentView();
        }
    }

    /**
     * Show notification for status change
     */
    showNotification(video, oldStatus, newStatus) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'status-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${this.getStatusIcon(newStatus)}</span>
                <div class="notification-text">
                    <strong>${video.title || 'Video'}</strong>
                    <p>Status: ${oldStatus} → ${newStatus}</p>
                </div>
            </div>
        `;

        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            background: white;
            border-left: 4px solid ${this.getStatusColor(newStatus)};
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    /**
     * Update specific UI element for video
     */
    updateUIElement(video) {
        const videoId = video.id;

        // Update status badge
        const statusBadge = document.querySelector(`[data-video-id="${videoId}"] .status-badge`);
        if (statusBadge) {
            statusBadge.textContent = video.status;
            statusBadge.className = `status-badge status-${video.status}`;
        }

        // Update status text
        const statusText = document.querySelector(`[data-video-id="${videoId}"] .status-text`);
        if (statusText) {
            statusText.textContent = this.getStatusLabel(video.status);
        }

        // Update row class
        const row = document.querySelector(`[data-video-id="${videoId}"]`);
        if (row) {
            row.className = `video-row status-${video.status}`;
        }

        // If video approved, show success indicator
        if (video.status === 'approved') {
            const indicator = document.querySelector(`[data-video-id="${videoId}"] .ai-indicator`);
            if (indicator) {
                indicator.innerHTML = '<span style="color: #10b981;">✓ Approved</span>';
            }
        }

        // If video rejected, show reason
        if (video.status === 'rejected' && video.rejection_reason) {
            const reasonEl = document.querySelector(`[data-video-id="${videoId}"] .rejection-reason`);
            if (reasonEl) {
                reasonEl.textContent = video.rejection_reason;
                reasonEl.style.display = 'block';
            }
        }
    }

    /**
     * Refresh the current view (table/list)
     */
    refreshCurrentView() {
        console.log('[Auto-Refresh] Refreshing current view...');

        // If there's a custom refresh function, call it
        if (window.refreshVideoList && typeof window.refreshVideoList === 'function') {
            window.refreshVideoList();
            return;
        }

        // Otherwise, trigger a data refresh for common frameworks
        
        // Alpine.js
        if (window.Alpine) {
            window.Alpine.store('videos')?.fetch?.();
        }

        // Vue.js
        if (window.vm && window.vm.fetchVideos) {
            window.vm.fetchVideos();
        }

        // React (if using global state)
        if (window.refreshVideos) {
            window.refreshVideos();
        }

        // Vanilla JS - dispatch custom event
        window.dispatchEvent(new CustomEvent('videos-updated'));
    }

    /**
     * Get status icon
     */
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

    /**
     * Get status color
     */
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

    /**
     * Get human-readable status label
     */
    getStatusLabel(status) {
        const labels = {
            'pending': 'Pending Review',
            'processing': 'AI Processing...',
            'approved': 'Approved',
            'rejected': 'Rejected',
            'flagged': 'Flagged for Review',
            'published': 'Published to YouTube'
        };
        return labels[status] || status;
    }
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

.status-notification {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.notification-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.notification-text strong {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #111;
    margin-bottom: 4px;
}

.notification-text p {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-processing { background: #dbeafe; color: #1e40af; }
.status-approved { background: #d1fae5; color: #065f46; }
.status-rejected { background: #fee2e2; color: #991b1b; }
.status-flagged { background: #fed7aa; color: #9a3412; }
.status-published { background: #e9d5ff; color: #6b21a8; }
`;
document.head.appendChild(style);

// Auto-initialize on pages that need it
document.addEventListener('DOMContentLoaded', () => {
    // Only start on pages with video lists
    const videoPages = [
        '/dashboard',
        '/admin',
        '/moderation',
        '/creator/videos',
        '/creator/dashboard'
    ];

    const currentPath = window.location.pathname;
    const shouldAutoStart = videoPages.some(page => currentPath.includes(page));

    if (shouldAutoStart) {
        console.log('[Auto-Refresh] Initializing on page:', currentPath);
        
        // Create global instance
        window.videoRefresher = new VideoStatusRefresher({
            pollInterval: 5000, // Check every 5 seconds
            maxRetries: 5,
            onStatusChange: (video, oldStatus, newStatus) => {
                console.log(`Video ${video.id} changed: ${oldStatus} → ${newStatus}`);
                
                // Play sound for status changes (optional)
                if (newStatus === 'approved') {
                    playNotificationSound('success');
                } else if (newStatus === 'rejected') {
                    playNotificationSound('error');
                }
            }
        });

        // Start polling
        window.videoRefresher.start();

        // Stop polling when user leaves page
        window.addEventListener('beforeunload', () => {
            window.videoRefresher.stop();
        });

        // Pause when tab is hidden, resume when visible
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                window.videoRefresher.stop();
            } else {
                window.videoRefresher.start();
            }
        });
    }
});

/**
 * Play notification sound (optional)
 */
function playNotificationSound(type = 'default') {
    // Use Web Audio API for subtle notification sounds
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();

    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);

    // Different frequencies for different notifications
    const frequencies = {
        'success': 800,
        'error': 400,
        'default': 600
    };

    oscillator.frequency.value = frequencies[type] || frequencies.default;
    oscillator.type = 'sine';

    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);

    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.1);
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VideoStatusRefresher;
}
