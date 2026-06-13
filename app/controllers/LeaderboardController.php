<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Leaderboard;
use App\Models\Season;

class LeaderboardController
{
    private Leaderboard $leaderboardModel;
    private Season $seasonModel;

    public function __construct()
    {
        $this->leaderboardModel = new Leaderboard();
        $this->seasonModel = new Season();
    }

    public function index(): void
    {
        header('Content-Type: application/json');
        $seasonId = isset($_GET['season_id']) ? (int) $_GET['season_id'] : null;

        if ($seasonId) {
            $data = $this->leaderboardModel->topBySeason($seasonId, 20);
        } else {
            $data = $this->leaderboardModel->topOverall(20);
        }

        echo json_encode(['data' => $data]);
    }

    public function seasons(): void
    {
        header('Content-Type: application/json');
        $seasons = $this->seasonModel->all();
        echo json_encode(['seasons' => $seasons]);
    }
}
