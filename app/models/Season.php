<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

class Season
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM seasons WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function active(): ?array
    {
        $stmt = $this->db->query(
            "SELECT * FROM seasons WHERE status = 'active' AND start_date <= CURDATE() AND end_date >= CURDATE() ORDER BY start_date DESC LIMIT 1"
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM seasons ORDER BY start_date DESC");
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO seasons (title, brief, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['title'],
            $data['brief'],
            $data['start_date'],
            $data['end_date'],
            $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE seasons SET title = ?, brief = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?"
        );
        return $stmt->execute([
            $data['title'],
            $data['brief'],
            $data['start_date'],
            $data['end_date'],
            $data['status'],
            $id,
        ]);
    }
}
