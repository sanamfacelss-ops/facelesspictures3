<?php
/**
 * Migration Runner
 * Run this script from CLI to apply pending migrations:
 *   php database/run-migrations.php
 * 
 * Or visit /database/run-migrations.php in browser (will be blocked in production by htaccess)
 */

// Only allow CLI or local access
if (php_sapi_name() !== 'cli') {
    // Block remote access
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remote, ['127.0.0.1', '::1', ''])) {
        http_response_code(403);
        die('Forbidden');
    }
}

require_once __DIR__ . '/../app/config/config.php';

$db = App\Config\Database::getConnection();

// Create migrations tracking table if it doesn't exist
$db->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Get already-applied migrations
$applied = $db->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

// Get all migration files
$migrationDir = __DIR__ . '/migrations';
$files = glob($migrationDir . '/*.sql');
sort($files);

$ran = 0;
$skipped = 0;

foreach ($files as $file) {
    $filename = basename($file);
    
    if (isset($applied[$filename])) {
        echo "  SKIP: $filename (already applied)\n";
        $skipped++;
        continue;
    }
    
    $sql = file_get_contents($file);
    
    // Split on semicolons to run multiple statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s)
    );
    
    try {
        foreach ($statements as $statement) {
            $db->exec($statement);
        }
        
        // Record migration as applied
        $db->prepare("INSERT INTO migrations (filename) VALUES (?)")->execute([$filename]);
        echo "   RAN: $filename\n";
        $ran++;
    } catch (\Exception $e) {
        echo " ERROR: $filename — " . $e->getMessage() . "\n";
    }
}

echo "\nDone. $ran applied, $skipped skipped.\n";
