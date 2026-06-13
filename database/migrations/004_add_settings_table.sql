-- Migration 004: Add settings table for admin-editable content
-- Run this after previous migrations

-- Settings table for key-value storage (guides, texts, etc.)
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value LONGTEXT,
    setting_type ENUM('text', 'html', 'json') DEFAULT 'text',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default guide texts for each role
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('guide_actor', 'As an actor, you''ll perform dramatic monologues, character scenes, and emotional pieces. Your voice and expression are your tools.\n\n**Tips for Success:**\n• Find a quiet space with good lighting\n• Practice your script a few times before recording\n• Focus on emotion and delivery, not perfection\n• Keep your performance between 60-90 seconds\n• Use natural pauses for dramatic effect', 'text', 'Guide text shown to actors on the Create screen'),
('guide_director', 'As a director, you''ll pitch your creative vision for scenes, share your directorial approach, and explain how you''d bring stories to life.\n\n**Tips for Success:**\n• Be specific about your creative vision\n• Explain your choices with confidence\n• Reference visual styles or influences if relevant\n• Keep pitches focused and under 2 minutes\n• Show passion for the story you want to tell', 'text', 'Guide text shown to directors on the Create screen'),
('guide_writer', 'As a writer, you''ll present your original work, pitch story concepts, or perform readings of your scripts and screenplays.\n\n**Tips for Success:**\n• Read your work with conviction\n• Vary your pacing to maintain interest\n• Let your unique voice come through\n• Keep readings concise and impactful\n• Consider the emotional journey of your piece', 'text', 'Guide text shown to writers on the Create screen')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
