<?php
include '../includes/db.php';
session_start();
$teamId = $_POST['team_id'] ?? null;
$criteriaId = $_POST['criteria_id'] ?? null;
$score = $_POST['score'] ?? null;
$jury_id = $_SESSION["user_id"];

if ($teamId && $criteriaId !== null && $score !== null) {
    if ($score < 0 || $score > 10) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Score must be between 0 and 10']);
        exit;
    }

    try {
        $scoreEntry = R::findOne('scores', 'team_id = ? AND criteria_id = ? AND jury_id = ?', [$teamId, $criteriaId, $jury_id]);

        if ($scoreEntry) {
            $scoreEntry->score = $score;
        } else {
            $scoreEntry = R::dispense('scores');
            $scoreEntry->team_id = $teamId;
            $scoreEntry->criteria_id = $criteriaId;
            $scoreEntry->score = $score;
            $scoreEntry->jury_id = $jury_id;
        }

        R::store($scoreEntry);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
}

