
<?php

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Only logged-in users can access the leaderboard
if (!isset($_SESSION['user_id'])) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Please login first.'
    ]);

    exit;
}

try {

    // Get each user's highest score
    $stmt = $pdo->prepare("
        SELECT
            users.id,
            users.name,
            MAX(game_scores.score) AS best_score

        FROM users

        INNER JOIN game_scores
            ON users.id = game_scores.user_id

        GROUP BY users.id, users.name

        ORDER BY best_score DESC, users.name ASC

        LIMIT 10
    ");

    $stmt->execute();

    $players = $stmt->fetchAll();

    // Assign rankings
    $rank = 1;

    foreach ($players as &$player) {

        $player['rank'] = $rank++;

        $player['best_score'] = (int) $player['best_score'];
    }

    unset($player);

    echo json_encode([
        'success' => true,
        'leaderboard' => $players
    ]);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load leaderboard.'
    ]);
}

?>
