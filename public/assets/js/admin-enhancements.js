/**
 * Admin Panel Enhancements
 * 1. Simplified YouTube Setup Guide
 * 2. Auto-refresh for submissions and overview
 */

// ==================== YouTube Setup Guide ====================
const youtubeSetupSteps = [
    {
        step: 1,
        title: "Enable YouTube API",
        actions: [
            "Go to Google Cloud Console",
            "Create project → Enable YouTube Data API v3"
        ],
        result: null
    },
    {
        step: 2,
        title: "Get API Key",
        actions: [
            "Go to: Credentials → Create Credentials → API Key",
            "Copy the key"
        ],
        result: {
            field: "YouTube API Key",
            value: "AIzaSy...",
            paste: "Paste in field above"
        }
    },
    {
        step: 3,
        title: "Configure OAuth",
        actions: [
            "OAuth consent screen → External → Fill app name & email",
            "Add scopes: youtube.upload, youtube.force-ssl"
        ],
        result: null
    },
    {
        step: 4,
        title: "✨ IMPORTANT: Publish App",
        actions: [
            "On OAuth consent screen → Click 'PUBLISH APP'",
            "⚠️ Without this, token expires in 7 days!"
        ],
        result: null,
        highlight: true
    },
    {
        step: 5,
        title: "Create OAuth Client",
        actions: [
            "Credentials → Create → OAuth client ID → Web application",
            "Add redirect URI: https://yourdomain.com/api/auth/google/callback"
        ],
        result: {
            field1: "YouTube Client ID",
            value1: "xxxxx.apps.googleusercontent.com",
            field2: "YouTube Client Secret", 
            value2: "GOCSPX-xxxxx",
            paste: "Paste both fields above"
        }
    },
    {
        step: 6,
        title: "Generate Refresh Token",
        actions: [
            "Replace YOUR_CLIENT_ID in this URL:",
            "https://accounts.google.com/o/oauth2/v2/auth?client_id=YOUR_CLIENT_ID&redirect_uri=http://localhost&response_type=code&scope=https://www.googleapis.com/auth/youtube.upload%20https://www.googleapis.com/auth/youtube.force-ssl&access_type=offline&prompt=consent",
            "Open URL → Login → Allow permissions",
            "Copy 'code' from redirect URL"
        ],
        result: null
    },
    {
        step: 7,
        title: "Exchange Code for Token",
        actions: [
            "Run this curl command (replace values):",
            `curl -X POST https://oauth2.googleapis.com/token \\
  -d "code=YOUR_CODE" \\
  -d "client_id=YOUR_CLIENT_ID" \\
  -d "client_secret=YOUR_CLIENT_SECRET" \\
  -d "redirect_uri=http://localhost" \\
  -d "grant_type=authorization_code"`
        ],
        result: {
            field: "YouTube Refresh Token",
            value: "1//0gxxx-yyy-zzz",
            paste: "Copy 'refresh_token' from response → Paste above"
        }
    },
    {
        step: 8,
        title: "Get Channel ID",
        actions: [
            "Go to YouTube Studio → Settings → Channel → Advanced",
            "Copy your Channel ID"
        ],
        result: {
            field: "YouTube Channel ID",
            value: "UCxxxxxxxxxxxxxxxx",
            paste: "Paste in field above"
        }
    }
];

// Render YouTube guide
function renderYouTubeGuide() {
    const guideHTML = `
        <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-6 mb-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-blue-900 flex items-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        Quick Setup Guide
                    </h3>
                    <p class="text-sm text-blue-700 mt-1">Follow these steps to connect YouTube</p>
                </div>
                <button onclick="document.getElementById('yt-guide').classList.toggle('hidden')" 
                    class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    Show/Hide
                </button>
            </div>

            <div id="yt-guide" class="space-y-4">
                ${youtubeSetupSteps.map(step => `
                    <div class="bg-white rounded-lg p-4 ${step.highlight ? 'border-4 border-amber-400' : 'border border-blue-200'}">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full ${step.highlight ? 'bg-amber-500' : 'bg-blue-500'} text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                                ${step.step}
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 text-sm mb-2">${step.title}</h4>
                                <ul class="space-y-1 text-xs text-gray-700">
                                    ${step.actions.map(action => `
                                        <li class="flex items-start gap-2">
                                            <span class="text-blue-500 mt-0.5">•</span>
                                            <span class="font-mono bg-gray-50 px-2 py-1 rounded break-all">${action}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                                ${step.result ? `
                                    <div class="mt-3 bg-green-50 border border-green-200 rounded-lg p-3">
                                        <p class="text-xs font-semibold text-green-800 mb-2">→ Result:</p>
                                        ${step.result.field ? `
                                            <div class="text-xs space-y-1">
                                                <div><strong>Field:</strong> <span class="text-blue-700">${step.result.field}</span></div>
                                                <div><strong>Value:</strong> <code class="bg-white px-2 py-0.5 rounded">${step.result.value}</code></div>
                                                <div class="text-green-700 font-medium">${step.result.paste}</div>
                                            </div>
                                        ` : ''}
                                        ${step.result.field1 ? `
                                            <div class="text-xs space-y-2">
                                                <div>
                                                    <div><strong>Field 1:</strong> <span class="text-blue-700">${step.result.field1}</span></div>
                                                    <div><strong>Value:</strong> <code class="bg-white px-2 py-0.5 rounded">${step.result.value1}</code></div>
                                                </div>
                                                <div>
                                                    <div><strong>Field 2:</strong> <span class="text-blue-700">${step.result.field2}</span></div>
                                                    <div><strong>Value:</strong> <code class="bg-white px-2 py-0.5 rounded">${step.result.value2}</code></div>
                                                </div>
                                                <div class="text-green-700 font-medium">${step.result.paste}</div>
                                            </div>
                                        ` : ''}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>

            <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-sm text-green-800 font-medium">
                    ✅ After completing all steps, click "Test YouTube + AI Connection" above to verify!
                </p>
            </div>
        </div>
    `;

    return guideHTML;
}

// Inject guide into YouTube tab
function injectYouTubeGuide() {
    // Wait for Alpine.js to be ready
    setTimeout(() => {
        const youtubeTab = document.querySelector('[x-show="activeTab === \'youtube\'"]');
        if (youtubeTab) {
            const firstCard = youtubeTab.querySelector('.lg\\:col-span-3');
            if (firstCard) {
                const guideDiv = document.createElement('div');
                guideDiv.className = 'lg:col-span-3';
                guideDiv.innerHTML = renderYouTubeGuide();
                firstCard.after(guideDiv);
                console.log('[Admin] YouTube guide injected');
            }
        }
    }, 1000);
}

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
        console.log('[Admin Auto-Refresh] Starting...');
        this.isPolling = true;
        this.poll();
    }

    stop() {
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
        this.isPolling = false;
        console.log('[Admin Auto-Refresh] Stopped');
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
            console.error('[Admin Auto-Refresh] Error:', error);
        }

        // Schedule next poll
        this.pollTimer = setTimeout(() => this.poll(), this.pollInterval);
    }

    handleUpdates(videos) {
        let hasChanges = false;

        videos.forEach(video => {
            const lastStatus = this.lastChecked[video.id];
            if (lastStatus && lastStatus !== video.status) {
                console.log(`[Admin] Video ${video.id} status changed: ${lastStatus} → ${video.status}`);
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
        console.log('[Admin] Refreshing view...');
        
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
    // Inject YouTube guide
    injectYouTubeGuide();

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

    console.log('[Admin] Enhancements loaded');
});
