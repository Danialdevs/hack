<?php
include '../includes/db.php';
session_start();

if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Доступ запрещён']);
    exit;
}

$eventId = (int)($_POST['event_id'] ?? 0);
$place1 = (int)($_POST['place_1'] ?? 0);
$place2 = (int)($_POST['place_2'] ?? 0);
$place3 = (int)($_POST['place_3'] ?? 0);

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Не указан event_id']);
    exit;
}

$event = R::load('events', $eventId);
$maxScore = (int)($event->max_score ?: 10);
$criteria = R::findAll('criteria', 'event_id = ?', [$eventId]);
$teams = R::findAll('teams', 'event_id = ?', [$eventId]);
$adminId = $_SESSION['user_id'];

if (empty($criteria)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Нет критериев для этого мероприятия']);
    exit;
}

// Удаляем все существующие оценки от всех жюри для этого мероприятия
$teamIds = array_map(fn($t) => $t->id, $teams);
if (!empty($teamIds)) {
    $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
    R::exec("DELETE FROM scores WHERE team_id IN ($placeholders)", $teamIds);
}

// Назначаем баллы: 1 место = max, 2 = max-1, 3 = max-2, остальные = 0
$placeScores = [];
if ($place1) $placeScores[$place1] = $maxScore;
if ($place2) $placeScores[$place2] = max($maxScore - 1, 0);
if ($place3) $placeScores[$place3] = max($maxScore - 2, 0);

foreach ($teams as $team) {
    $scoreValue = $placeScores[$team->id] ?? 0;
    foreach ($criteria as $cr) {
        $entry = R::dispense('scores');
        $entry->team_id = $team->id;
        $entry->criteria_id = $cr->id;
        $entry->jury_id = $adminId;
        $entry->score = $scoreValue;
        R::store($entry);
    }
}

echo json_encode(['status' => 'success']);
