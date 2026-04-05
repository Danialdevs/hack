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

$placeholders = implode(',', array_fill(0, count($teamIds), '?'));

// Ищем жюри, которое назначено на мероприятие но НЕ голосовало (все нули)
$assignedJury = R::getCol('SELECT user_id FROM juryevent WHERE event_id = ?', [$eventId]);
$targetJuryId = $adminId; // fallback — сам админ

foreach ($assignedJury as $jid) {
    $hasScores = (int) R::getCell(
        "SELECT COUNT(*) FROM scores s JOIN teams t ON s.team_id = t.id WHERE s.jury_id = ? AND t.event_id = ? AND s.score > 0",
        [$jid, $eventId]
    );
    if ($hasScores === 0) {
        $targetJuryId = (int)$jid;
        break;
    }
}

$adminMax = $maxScore * $criteriaCount;

// Баллы каждой команды БЕЗ оценок целевого жюри
$baseScores = [];
foreach ($teams as $team) {
    $baseScores[$team->id] = (int) R::getCell(
        "SELECT COALESCE(SUM(score),0) FROM scores WHERE team_id = ? AND jury_id != ?",
        [$team->id, $targetJuryId]
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

// Разрыв между местами
$gap = max(2, $criteriaCount);

// Считаем нужные итоги СНИЗУ ВВЕРХ
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

    $needAdmin = max(0, min($needAdmin, $adminMax));
    $adminTotals[$tid] = $needAdmin;
    $prevFinal = $base + $needAdmin;
}

// Раскидываем баллы по критериям с натуральным разбросом
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
        $spread = min(2, (int)floor($avg));
        $low = max(0, (int)floor($avg) - $spread);
        $high = min($maxScore, (int)ceil($avg) + $spread);

        $minHere = max(0, $remaining - $maxScore * ($left - 1));
        $maxHere = min($maxScore, $remaining);

        $low = max($low, $minHere);
        $high = min($high, $maxHere);
        if ($low > $high) $low = $high;

        $val = rand($low, $high);
        $scores[] = $val;
        $remaining -= $val;
    }

    shuffle($scores);
    return $scores;
}

// Удаляем старые оценки целевого жюри для этого мероприятия
R::exec("DELETE FROM scores WHERE team_id IN ($placeholders) AND jury_id = ?", array_merge($teamIds, [$targetJuryId]));

// Записываем
$results = [];
$juryUser = R::load('users', $targetJuryId);
foreach ($teams as $team) {
    $adminTotal = $adminTotals[$team->id] ?? 0;
    $perCriteria = distributeNatural($adminTotal, $criteriaCount, $maxScore);

    $i = 0;
    foreach ($criteria as $cr) {
        $entry = R::dispense('scores');
        $entry->team_id = $team->id;
        $entry->criteria_id = $cr->id;
        $entry->jury_id = $targetJuryId;
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
    'jury_name' => $juryUser->name ?? 'Админ',
    'jury_id' => $targetJuryId,
    'results' => $results,
]);
