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
    echo json_encode(['status' => 'error', 'message' => 'Нет критериев для этого мероприятия']);
    exit;
}

$teamIds = array_map(fn($t) => $t->id, $teams);
if (empty($teamIds)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Нет команд']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($teamIds), '?'));

// Базовые баллы каждой команды БЕЗ оценок админа
$baseScores = [];
foreach ($teams as $team) {
    $base = (int) R::getCell(
        "SELECT COALESCE(SUM(score),0) FROM scores WHERE team_id = ? AND jury_id != ?",
        [$team->id, $adminId]
    );
    $baseScores[$team->id] = $base;
}

// Находим максимальный базовый балл
$maxBase = max($baseScores) ?: 0;

// Целевые итоги: все близко, но порядок правильный
// 1 место = maxBase + 3*criteriaCount, 2 = +2, 3 = +1
// Остальные команды — админ даёт 0
$targets = [];
if ($place1) $targets[$place1] = $maxBase + $criteriaCount * 3;
if ($place2) $targets[$place2] = $maxBase + $criteriaCount * 2;
if ($place3) $targets[$place3] = $maxBase + $criteriaCount * 1;

// Удаляем старые оценки админа для этого мероприятия
R::exec("DELETE FROM scores WHERE team_id IN ($placeholders) AND jury_id = ?", array_merge($teamIds, [$adminId]));

// Выставляем оценки админа
foreach ($teams as $team) {
    if (isset($targets[$team->id])) {
        // Сколько всего баллов админ должен добавить
        $needed = max($targets[$team->id] - $baseScores[$team->id], 0);
        // Распределяем по критериям равномерно
        $perCriteria = floor($needed / $criteriaCount);
        $remainder = $needed % $criteriaCount;
        // Ограничиваем max_score
        $perCriteria = min($perCriteria, $maxScore);

        $i = 0;
        foreach ($criteria as $cr) {
            $val = $perCriteria;
            // Остаток раскидываем по первым критериям
            if ($i < $remainder) $val = min($val + 1, $maxScore);
            $i++;

            $entry = R::dispense('scores');
            $entry->team_id = $team->id;
            $entry->criteria_id = $cr->id;
            $entry->jury_id = $adminId;
            $entry->score = $val;
            R::store($entry);
        }
    } else {
        // Остальные команды — админ ставит 0
        foreach ($criteria as $cr) {
            $entry = R::dispense('scores');
            $entry->team_id = $team->id;
            $entry->criteria_id = $cr->id;
            $entry->jury_id = $adminId;
            $entry->score = 0;
            R::store($entry);
        }
    }
}

echo json_encode(['status' => 'success']);
