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

// Максимум баллов от одного жюри = maxScore * criteriaCount (напр. 10 * 5 = 50)
$adminMax = $maxScore * $criteriaCount;

// Баллы каждой команды от ДРУГИХ жюри (без админа)
$baseScores = [];
foreach ($teams as $team) {
    $baseScores[$team->id] = (int) R::getCell(
        "SELECT COALESCE(SUM(score),0) FROM scores WHERE team_id = ? AND jury_id != ?",
        [$team->id, $adminId]
    );
}

$placed = [];
if ($place1) $placed[] = $place1;
if ($place2) $placed[] = $place2;
if ($place3) $placed[] = $place3;

// Реалистичные оценки админа по критериям:
// 1 место: maxScore     (напр. 10 по каждому критерию)
// 2 место: maxScore - 1 (напр. 9)
// 3 место: maxScore - 2 (напр. 8)
// Остальные: половина   (напр. 5)
$adminPerCriteria = [];
if ($place1) $adminPerCriteria[$place1] = $maxScore;
if ($place2) $adminPerCriteria[$place2] = max($maxScore - 1, 0);
if ($place3) $adminPerCriteria[$place3] = max($maxScore - 2, 0);

$moderateScore = max(1, (int)floor($maxScore / 2));

// Считаем итоги для призёров
$placedTotals = [];
foreach ($placed as $tid) {
    $placedTotals[$tid] = $baseScores[$tid] + $adminPerCriteria[$tid] * $criteriaCount;
}

// Проверяем порядок между призёрами и корректируем если нужно
// Идём с конца: 3 место, потом 2, потом 1
$placedCount = count($placed);
for ($i = $placedCount - 1; $i >= 1; $i--) {
    $lower = $placed[$i];
    $upper = $placed[$i - 1];
    // Если верхнее место не выше нижнего — уменьшаем нижнее
    if ($placedTotals[$lower] >= $placedTotals[$upper]) {
        // Нужный итог нижнего = итог верхнего - criteriaCount
        $needTotal = $placedTotals[$upper] - $criteriaCount;
        $needPerCriteria = max(0, (int)floor(($needTotal - $baseScores[$lower]) / $criteriaCount));
        $needPerCriteria = min($needPerCriteria, $maxScore);
        $adminPerCriteria[$lower] = $needPerCriteria;
        $placedTotals[$lower] = $baseScores[$lower] + $needPerCriteria * $criteriaCount;
    }
}

// Минимальный итог среди призёров (3 место или последний призёр)
$lowestPlacedTotal = $placedTotals[$placed[$placedCount - 1]];

// Для остальных команд: даём средний балл, но итог должен быть ниже 3 места
foreach ($teams as $team) {
    if (in_array($team->id, $placed)) continue;

    $tryScore = $moderateScore;
    while ($tryScore > 0 && ($baseScores[$team->id] + $tryScore * $criteriaCount) >= $lowestPlacedTotal) {
        $tryScore--;
    }
    $adminPerCriteria[$team->id] = $tryScore;
}

// Удаляем старые оценки админа
$placeholders = implode(',', array_fill(0, count($teamIds), '?'));
R::exec("DELETE FROM scores WHERE team_id IN ($placeholders) AND jury_id = ?", array_merge($teamIds, [$adminId]));

// Записываем оценки админа
$results = [];
foreach ($teams as $team) {
    $scorePerCriteria = $adminPerCriteria[$team->id] ?? 0;
    $adminTotal = $scorePerCriteria * $criteriaCount;

    foreach ($criteria as $cr) {
        $entry = R::dispense('scores');
        $entry->team_id = $team->id;
        $entry->criteria_id = $cr->id;
        $entry->jury_id = $adminId;
        $entry->score = $scorePerCriteria;
        R::store($entry);
    }

    $results[] = [
        'team_id' => $team->id,
        'name' => $team->name,
        'base' => $baseScores[$team->id],
        'admin' => $adminTotal,
        'total' => $baseScores[$team->id] + $adminTotal,
    ];
}

usort($results, fn($a, $b) => $b['total'] <=> $a['total']);

echo json_encode([
    'status' => 'success',
    'max_per_jury' => $adminMax,
    'results' => $results,
]);
