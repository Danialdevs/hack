<?php
include '../includes/auth.php';

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
$criteriaCount = count($criteria);

if ($criteriaCount === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Нет критериев']);
    exit;
}

$teamIds = array_map(fn($t) => $t->id, $teams);
if (empty($teamIds)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Нет команд']);
    exit;
}

// Баллы каждой команды от ДРУГИХ жюри (без админа)
$baseScores = [];
foreach ($teams as $team) {
    $baseScores[$team->id] = (int) R::getCell(
        "SELECT COALESCE(SUM(score),0) FROM scores WHERE team_id = ? AND jury_id != ?",
        [$team->id, $adminId]
    );
}

// Максимум что админ может дать одной команде
$adminMax = $maxScore * $criteriaCount;

// Определяем порядок: [1 место, 2 место, 3 место, остальные по убыванию базы]
$placed = [];
if ($place1) $placed[] = $place1;
if ($place2) $placed[] = $place2;
if ($place3) $placed[] = $place3;

$others = [];
foreach ($teams as $team) {
    if (!in_array($team->id, $placed)) {
        $others[] = $team->id;
    }
}
usort($others, fn($a, $b) => $baseScores[$b] <=> $baseScores[$a]);

// Полный порядок от 1-го места до последнего
$order = array_merge($placed, $others);

// Считаем снизу вверх: каждая команда выше должна иметь итог хотя бы на 1 больше
// Начинаем с последней команды — админ даёт ей 0
$adminScores = [];
$lastTeam = end($order);
$adminScores[$lastTeam] = 0;
$prevTotal = $baseScores[$lastTeam]; // итог последней команды

for ($i = count($order) - 2; $i >= 0; $i--) {
    $tid = $order[$i];
    $base = $baseScores[$tid];
    // Нужный итог: хотя бы prevTotal + 1
    $needTotal = $prevTotal + 1;
    $needAdmin = $needTotal - $base;

    if ($needAdmin < 0) $needAdmin = 0;
    if ($needAdmin > $adminMax) $needAdmin = $adminMax;

    $adminScores[$tid] = $needAdmin;
    $prevTotal = $base + $needAdmin;
}

// Удаляем старые оценки админа
$placeholders = implode(',', array_fill(0, count($teamIds), '?'));
R::exec("DELETE FROM scores WHERE team_id IN ($placeholders) AND jury_id = ?", array_merge($teamIds, [$adminId]));

// Записываем новые оценки админа
$results = [];
foreach ($teams as $team) {
    $needed = $adminScores[$team->id] ?? 0;
    $perCriteria = (int) floor($needed / $criteriaCount);
    $remainder = $needed % $criteriaCount;
    $perCriteria = min($perCriteria, $maxScore);

    $i = 0;
    foreach ($criteria as $cr) {
        $val = $perCriteria;
        if ($i < $remainder) $val = min($val + 1, $maxScore);
        $i++;

        $entry = R::dispense('scores');
        $entry->team_id = $team->id;
        $entry->criteria_id = $cr->id;
        $entry->jury_id = $adminId;
        $entry->score = $val;
        R::store($entry);
    }

    $results[] = [
        'team_id' => $team->id,
        'name' => $team->name,
        'base' => $baseScores[$team->id],
        'admin' => $needed,
        'total' => $baseScores[$team->id] + $needed,
    ];
}

// Сортируем по итогу
usort($results, fn($a, $b) => $b['total'] <=> $a['total']);

echo json_encode(['status' => 'success', 'results' => $results]);
