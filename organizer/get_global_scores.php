<?php
require '../includes/db.php';
header('Content-Type: application/json');

$eventId = $_GET['id'] ?? null;

if (!$eventId) {
    echo json_encode(['error' => 'Event ID required']);
    exit;
}

$teams = R::findAll('teams', 'event_id = ?', [$eventId]);
$data = [];

foreach ($teams as $team) {
    $globalScore = (int) R::getCell("SELECT SUM(score) FROM scores WHERE team_id = ?", [$team->id]);
    $data[] = [
        'team_id' => $team->id,
        'global_score' => $globalScore
    ];
}

echo json_encode($data);
