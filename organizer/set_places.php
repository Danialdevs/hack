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

$others = [];
foreach ($teams as $team) {
    if (!in_array($team->id, $placed)) {
        $others[] = $team->id;
    }
}
usort($others, fn($a, $b) => $baseScores[$b] <=> $baseScores[$a]);

// Полный порядок: [1 место, 2 место, 3 место, остальные по убыванию базы]
$order = array_merge($placed, $others);

// Реалистичный разрыв между местами
$gap = max(2, $criteriaCount);

// Считаем нужные итоги СНИЗУ ВВЕРХ
// Последняя команда: база + средний админский балл
$moderateTotal = max(1, (int)floor($maxScore * 0.4)) * $criteriaCount;
$adminTotals = [];

$lastTid = end($order);
$adminTotals[$lastTid] = min($moderateTotal, $adminMax);
$prevFinal = $baseScores[$lastTid] + $adminTotals[$lastTid];

for ($i = count($order) - 2; $i >= 0; $i--) {
    $tid = $order[$i];
    $base = $baseScores[$tid];
    $needFinal = $prevFinal + $gap;
    $needAdmin = $needFinal - $base;

    // Ограничиваем: не меньше 0, не больше adminMax
    $needAdmin = max(0, min($needAdmin, $adminMax));
    $adminTotals[$tid] = $needAdmin;
    $prevFinal = $base + $needAdmin;
}

// Раскидываем админские баллы по критериям С РАЗБРОСОМ (не ровные числа)
function distributeNatural($total, $criteriaCount, $maxScore) {
    if ($criteriaCount === 0) return [];
    $total = max(0, min($total, $maxScore * $criteriaCount));

    $scores = [];
    $remaining = $total;

    for ($i = 0; $i < $criteriaCount; $i++) {
        $left = $criteriaCount - $i;

        if ($left === 1) {
            $scores[] = max(0, min($maxScore, $remaining));
            break;
        }

        $avg = $remaining / $left;
        // Разброс ±2 от среднего
        $spread = min(2, (int)floor($avg));
        $low = max(0, (int)floor($avg) - $spread);
        $high = min($maxScore, (int)ceil($avg) + $spread);

        // Не дать слишком мало/много чтобы хватило остальным
        $minHere = max(0, $remaining - $maxScore * ($left - 1));
        $maxHere = min($maxScore, $remaining);

        $low = max($low, $minHere);
        $high = min($high, $maxHere);
        if ($low > $high) $low = $high;

        $val = rand($low, $high);
        $scores[] = $val;
        $remaining -= $val;
    }

    // Перемешиваем чтобы не было паттерна (убывание/возрастание)
    shuffle($scores);
    return $scores;
}

// Удаляем старые оценки админа
$placeholders = implode(',', array_fill(0, count($teamIds), '?'));
R::exec("DELETE FROM scores WHERE team_id IN ($placeholders) AND jury_id = ?", array_merge($teamIds, [$adminId]));

// Записываем
$results = [];
foreach ($teams as $team) {
    $adminTotal = $adminTotals[$team->id] ?? 0;
    $perCriteria = distributeNatural($adminTotal, $criteriaCount, $maxScore);

    $i = 0;
    foreach ($criteria as $cr) {
        $entry = R::dispense('scores');
        $entry->team_id = $team->id;
        $entry->criteria_id = $cr->id;
        $entry->jury_id = $adminId;
        $entry->score = $perCriteria[$i] ?? 0;
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
