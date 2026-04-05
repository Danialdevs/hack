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

// Средний балл за критерий для каждого уровня
// 1 место: ~90-100% от max (напр. 8-10 при max=10)
// 2 место: ~70-90%  (напр. 7-9)
// 3 место: ~60-80%  (напр. 6-8)
// Остальные: ~30-60% (напр. 3-6)
$ranges = [
    0 => [max(1, $maxScore - 2), $maxScore],           // 1 место: max-2 .. max
    1 => [max(1, $maxScore - 3), max(1, $maxScore - 1)], // 2 место: max-3 .. max-1
    2 => [max(1, $maxScore - 4), max(1, $maxScore - 2)], // 3 место: max-4 .. max-2
];
$otherRange = [max(1, (int)floor($maxScore * 0.3)), max(1, (int)floor($maxScore * 0.6))];

// Генерирует массив случайных баллов по критериям с нужной суммой в диапазоне
function generateScores($criteriaCount, $minPerCrit, $maxPerCrit, $maxScore) {
    $scores = [];
    for ($i = 0; $i < $criteriaCount; $i++) {
        $scores[] = rand($minPerCrit, $maxPerCrit);
    }
    // Ограничиваем [0, maxScore]
    return array_map(fn($s) => max(0, min($maxScore, $s)), $scores);
}

// Генерируем баллы для призёров
$teamScores = []; // team_id => [score1, score2, ...]
$teamTotals = [];

foreach ($placed as $idx => $tid) {
    $range = $ranges[$idx];
    $teamScores[$tid] = generateScores($criteriaCount, $range[0], $range[1], $maxScore);
    $teamTotals[$tid] = $baseScores[$tid] + array_sum($teamScores[$tid]);
}

// Проверяем порядок призёров и корректируем
$placedCount = count($placed);
for ($i = 1; $i < $placedCount; $i++) {
    $upper = $placed[$i - 1];
    $lower = $placed[$i];
    // Если нижнее место >= верхнего — понижаем баллы нижнего
    $attempts = 0;
    while ($teamTotals[$lower] >= $teamTotals[$upper] && $attempts < 50) {
        // Находим самый высокий балл и уменьшаем
        $maxIdx = array_keys($teamScores[$lower], max($teamScores[$lower]))[0];
        if ($teamScores[$lower][$maxIdx] > 0) {
            $teamScores[$lower][$maxIdx]--;
            $teamTotals[$lower]--;
        }
        $attempts++;
    }
}

// Минимальный итог среди призёров
$lowestPlacedTotal = $teamTotals[$placed[$placedCount - 1]];

// Генерируем баллы для остальных команд
foreach ($teams as $team) {
    if (in_array($team->id, $placed)) continue;

    $teamScores[$team->id] = generateScores($criteriaCount, $otherRange[0], $otherRange[1], $maxScore);
    $teamTotals[$team->id] = $baseScores[$team->id] + array_sum($teamScores[$team->id]);

    // Понижаем если обгоняет 3 место
    $attempts = 0;
    while ($teamTotals[$team->id] >= $lowestPlacedTotal && $attempts < 100) {
        $maxIdx = array_keys($teamScores[$team->id], max($teamScores[$team->id]))[0];
        if ($teamScores[$team->id][$maxIdx] > 0) {
            $teamScores[$team->id][$maxIdx]--;
            $teamTotals[$team->id]--;
        } else {
            break;
        }
        $attempts++;
    }
}

// Удаляем старые оценки админа
$placeholders = implode(',', array_fill(0, count($teamIds), '?'));
R::exec("DELETE FROM scores WHERE team_id IN ($placeholders) AND jury_id = ?", array_merge($teamIds, [$adminId]));

// Записываем оценки админа
$results = [];
foreach ($teams as $team) {
    $scores = $teamScores[$team->id];
    $adminTotal = array_sum($scores);

    $i = 0;
    foreach ($criteria as $cr) {
        $entry = R::dispense('scores');
        $entry->team_id = $team->id;
        $entry->criteria_id = $cr->id;
        $entry->jury_id = $adminId;
        $entry->score = $scores[$i];
        R::store($entry);
        $i++;
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
