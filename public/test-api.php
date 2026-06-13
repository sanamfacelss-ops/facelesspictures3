<?php
/**
 * Debug Log Viewer - Like WP Debug Log
 * Visit /test-api.php to see all logs
 * Click "Clear Logs" to reset
 */

require_once __DIR__ . '/../app/config/config.php';

$logDir = __DIR__ . '/../logs';
$debugLog = $logDir . '/debug.log';
$errorLog = $logDir . '/error.log';

// Handle clear logs
if (isset($_GET['clear'])) {
    file_put_contents($debugLog, '');
    file_put_contents($errorLog, '');
    header('Location: /test-api.php?cleared=1');
    exit;
}

// Handle JSON API request
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'debug_log' => file_exists($debugLog) ? file_get_contents($debugLog) : '',
        'error_log' => file_exists($errorLog) ? file_get_contents($errorLog) : '',
        'time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$debugContent = file_exists($debugLog) ? file_get_contents($debugLog) : 'No debug.log yet';
$errorContent = file_exists($errorLog) ? file_get_contents($errorLog) : 'No error.log yet';

// System status
$status = [];
try {
    $db = \App\Config\Database::getConnection();
    $status['database'] = '✅ Connected';
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
    $status['users'] = $stmt->fetch()['cnt'] . ' users';
} catch (Throwable $e) {
    $status['database'] = '❌ ' . $e->getMessage();
}
$status['debug_mode'] = FP3_DEBUG ? '✅ ON' : '❌ OFF';
$status['session'] = session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive';
?>
<!DOCTYPE html>
<html>
<head>
    <title>FP3 Debug Logs</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: monospace; background: #1a1a2e; color: #eee; padding: 20px; }
        h1 { color: #dc2626; margin-bottom: 20px; }
        .status { background: #16213e; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .status span { margin-right: 30px; }
        .actions { margin-bottom: 20px; }
        .actions a, .actions button { 
            background: #dc2626; color: white; padding: 10px 20px; 
            text-decoration: none; border-radius: 5px; margin-right: 10px;
            border: none; cursor: pointer; font-size: 14px;
        }
        .actions a:hover, .actions button:hover { background: #b91c1c; }
        .log-section { margin-bottom: 30px; }
        .log-section h2 { color: #fbbf24; margin-bottom: 10px; }
        .log-box { 
            background: #0f0f23; padding: 15px; border-radius: 8px; 
            max-height: 400px; overflow-y: auto; white-space: pre-wrap;
            font-size: 12px; line-height: 1.6; border: 1px solid #333;
        }
        .log-box:empty::before { content: 'No logs yet. Try login/signup then refresh.'; color: #666; }
        .error { color: #f87171; }
        .success { color: #4ade80; }
        .cleared { background: #166534; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        #auto-refresh { margin-left: 20px; }
    </style>
</head>
<body>
    <h1>🔧 FP3 Debug Logs</h1>
    
    <?php if (isset($_GET['cleared'])): ?>
    <div class="cleared">✅ Logs cleared!</div>
    <?php endif; ?>
    
    <div class="status">
        <strong>Status:</strong>
        <span>Database: <?= $status['database'] ?></span>
        <span>Users: <?= $status['users'] ?? 'N/A' ?></span>
        <span>Debug Mode: <?= $status['debug_mode'] ?></span>
        <span>Session: <?= $status['session'] ?></span>
    </div>
    
    <div class="actions">
        <a href="/test-api.php?clear=1" onclick="return confirm('Clear all logs?')">🗑️ Clear Logs</a>
        <button onclick="location.reload()">🔄 Refresh</button>
        <label id="auto-refresh">
            <input type="checkbox" id="autoRefresh"> Auto-refresh (3s)
        </label>
    </div>
    
    <div class="log-section">
        <h2>📝 debug.log (Auth & App Logs)</h2>
        <div class="log-box" id="debug-log"><?= htmlspecialchars($debugContent) ?></div>
    </div>
    
    <div class="log-section">
        <h2>❌ error.log (Exceptions)</h2>
        <div class="log-box error" id="error-log"><?= htmlspecialchars($errorContent) ?></div>
    </div>
    
    <script>
        // Auto-refresh
        let interval;
        document.getElementById('autoRefresh').addEventListener('change', function() {
            if (this.checked) {
                interval = setInterval(() => location.reload(), 3000);
            } else {
                clearInterval(interval);
            }
        });
        
        // Scroll logs to bottom
        document.querySelectorAll('.log-box').forEach(el => {
            el.scrollTop = el.scrollHeight;
        });
    </script>
</body>
</html>
