<?php
/**
 * Database Seeder - Run once to create admin account and sample data
 * Usage: php database/seed.php
 */

require_once __DIR__ . '/../app/config/config.php';

use App\Config\Database;

echo "=== Faceless Pictures 3 - Database Seeder ===\n\n";

try {
    $db = Database::getConnection();
    echo "✓ Database connected\n";
    
    // Admin credentials
    $adminEmail = 'sanamfacelss@gmail.com';
    $adminPassword = 'FP3@dmin2024!';  // Strong password
    $adminName = 'Sanam Admin';
    
    // Check if admin exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    
    if ($stmt->fetch()) {
        echo "! Admin account already exists\n";
        
        // Update to ensure is_admin is set
        $stmt = $db->prepare("UPDATE users SET is_admin = 1 WHERE email = ?");
        $stmt->execute([$adminEmail]);
        echo "✓ Admin privileges confirmed\n";
    } else {
        // Create admin account
        $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare(
            "INSERT INTO users (name, email, password, role, is_admin, created_at) VALUES (?, ?, ?, 'admin', 1, NOW())"
        );
        $stmt->execute([$adminName, $adminEmail, $hashedPassword]);
        echo "✓ Admin account created\n";
    }
    
    echo "\n=== Admin Credentials ===\n";
    echo "Email: $adminEmail\n";
    echo "Password: $adminPassword\n";
    echo "\n⚠️  IMPORTANT: Change this password after first login!\n";
    echo "=========================\n\n";
    
    // Seed scripts for each category
    echo "=== Seeding Scripts ===\n";
    
    $scripts = [
        // ACTOR scripts
        [
            'title' => 'The Confession',
            'content' => "You've been keeping a secret for years. Today, you finally have to tell your best friend the truth about what happened that night.\n\nStart calm, but let the emotion build as you reveal more details. End with asking for forgiveness.\n\nShow us the internal struggle between guilt and relief.",
            'category' => 'actor',
            'difficulty' => 'intermediate',
            'duration_hint' => '60-90 seconds'
        ],
        [
            'title' => 'Job Interview Gone Wrong',
            'content' => "You're in the most important job interview of your life. Everything was going well until they asked about your biggest failure.\n\nShow the struggle between being honest and wanting to impress. Let us see your character think on their feet.",
            'category' => 'actor',
            'difficulty' => 'beginner',
            'duration_hint' => '45-60 seconds'
        ],
        [
            'title' => 'The Breakup Call',
            'content' => "You're on a video call, breaking up with someone you still care about. They can't understand why.\n\nShow the internal conflict - you know this is right, but it still hurts. We only see and hear your side of the conversation.",
            'category' => 'actor',
            'difficulty' => 'advanced',
            'duration_hint' => '90-120 seconds'
        ],
        [
            'title' => 'Unexpected News',
            'content' => "You just received life-changing news - it could be good or bad, you decide.\n\nShow us your character processing this information in real-time. Start with shock, move through various emotions, and end with acceptance or determination.",
            'category' => 'actor',
            'difficulty' => 'beginner',
            'duration_hint' => '30-60 seconds'
        ],
        
        // DIRECTOR scripts
        [
            'title' => 'The Reunion Scene',
            'content' => "Two estranged siblings meet at their childhood home after 10 years. One has come to make amends, the other isn't ready to forgive.\n\nExplain how you would direct this scene:\n- What's the mood/atmosphere?\n- How would you use the space?\n- What would you tell each actor?\n- Key camera angles?",
            'category' => 'director',
            'difficulty' => 'intermediate',
            'duration_hint' => '90-120 seconds'
        ],
        [
            'title' => 'The Chase',
            'content' => "A character is being pursued through a crowded marketplace. They're trying to blend in while being hunted.\n\nBreak down your directorial vision:\n- Pacing and rhythm\n- Sound design choices\n- How to build tension\n- The moment they're spotted",
            'category' => 'director',
            'difficulty' => 'advanced',
            'duration_hint' => '90-120 seconds'
        ],
        [
            'title' => 'First Date',
            'content' => "Two people meet for a blind date at a coffee shop. There's immediate chemistry, but also awkwardness.\n\nShare your vision:\n- Tone (comedy, romance, drama?)\n- How to capture the chemistry\n- Use of close-ups vs wide shots\n- The ending moment",
            'category' => 'director',
            'difficulty' => 'beginner',
            'duration_hint' => '60-90 seconds'
        ],
        
        // WRITER scripts (story prompts to continue)
        [
            'title' => 'The Letter',
            'content' => "STORY OPENING:\n\nMaya found the letter tucked inside her grandmother's old recipe book. The envelope was yellowed, the handwriting unfamiliar. It was addressed to someone named 'Elizabeth' - but that wasn't her grandmother's name.\n\nShe opened it carefully, and the first line made her heart stop:\n\n'If you're reading this, then I'm already gone, and you deserve to know the truth about who you really are...'\n\n---\nCONTINUE THIS STORY. What does the letter reveal? Who wrote it? How does Maya react?",
            'category' => 'writer',
            'difficulty' => 'beginner',
            'duration_hint' => '90-120 seconds'
        ],
        [
            'title' => 'The Last Train',
            'content' => "STORY OPENING:\n\nThe platform was empty except for one figure at the far end. James checked his watch - 11:47 PM. The last train was supposed to arrive three minutes ago.\n\nThen he noticed the figure was walking toward him. As they got closer, he recognized the face - someone he'd spent five years trying to forget.\n\n'Hello, James. Did you really think you could just disappear?'\n\n---\nCONTINUE THIS STORY. Who is this person? What's their history? What happens next?",
            'category' => 'writer',
            'difficulty' => 'intermediate',
            'duration_hint' => '90-120 seconds'
        ],
        [
            'title' => 'Room 237',
            'content' => "STORY OPENING:\n\nThe hotel had been abandoned for thirty years, but tonight every window on the third floor was glowing.\n\nSarah gripped her flashlight tighter. She was here to document the building for the historical society, nothing more. But her brother had stayed in Room 237 the night before the hotel closed - and he'd never been the same after.\n\nThe front door creaked open on its own.\n\n---\nCONTINUE THIS STORY. What happened to her brother? What will Sarah find inside?",
            'category' => 'writer',
            'difficulty' => 'advanced',
            'duration_hint' => '90-120 seconds'
        ],
    ];
    
    $insertStmt = $db->prepare(
        "INSERT INTO scripts (title, content, category, difficulty, duration_hint, is_active) 
         VALUES (?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE content = VALUES(content), difficulty = VALUES(difficulty), duration_hint = VALUES(duration_hint)"
    );
    
    foreach ($scripts as $script) {
        $insertStmt->execute([
            $script['title'],
            $script['content'],
            $script['category'],
            $script['difficulty'],
            $script['duration_hint']
        ]);
    }
    
    echo "✓ " . count($scripts) . " scripts seeded/updated\n";
    
    // Count scripts by category
    $stmt = $db->query("SELECT category, COUNT(*) as count FROM scripts WHERE is_active = 1 GROUP BY category");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($counts as $row) {
        echo "  - {$row['category']}: {$row['count']} scripts\n";
    }
    
    // Seed a default season if none exists
    echo "\n=== Checking Seasons ===\n";
    $stmt = $db->query("SELECT COUNT(*) FROM seasons");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO seasons (title, brief, start_date, end_date, status) VALUES 
            ('Season 3', 'The Turning Point - Show us the moment everything changed', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'active')");
        echo "✓ Default season created\n";
    } else {
        echo "! Seasons already exist\n";
    }
    
    echo "\n=== Seeding Complete ===\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
