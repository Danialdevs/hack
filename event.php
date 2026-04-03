<?php
include 'includes/db.php';
include 'includes/header.php';

$id = $_GET['id'] ?? 0;
$event = R::load('events', $id);
$teams = R::findAll('teams', 'event_id = ?', [$id]);

$team_id = $_GET['team_id'] ?? null;
?>

<div class="bg-white w-full mx-auto mt-6 p-6 rounded-lg shadow text-center" style="max-width:95vw">
    <h3 class="text-xl font-semibold"><?= $event->title ?></h3>
    <?php if (!empty($event->description)): ?>
        <p class="text-gray-600 mt-2 text-sm"><?= nl2br(htmlspecialchars($event->description)) ?></p>
    <?php endif; ?>
    <?php if ($event->show_registration === null || (int)$event->show_registration): ?>
    <a href="register.php?event_id=<?= $id ?>" class="inline-block mt-3 mb-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition text-sm">
        Зарегистрировать команду
    </a>
    <?php endif; ?>


<?php
$now = new DateTime();
$taskStart = !empty($event->task_start) ? new DateTime($event->task_start) : null;
$showTaskLink = $taskStart && $now >= $taskStart; // Показывать ссылку только если время уже наступило
?>

<?php if (!empty($event->task_start)): ?>
    <div id="timer" class="text-2xl font-bold"></div>

    <script>
        function updateTimer() {
            const now = new Date();
            const targetDate = new Date("<?= $event->task_start ?>");

            if (isNaN(targetDate)) {
                document.getElementById("timer").innerText = "Некорректная дата";
                return;
            }

            const diff = targetDate - now;
            if (diff <= 0) {
                <?php if ($showTaskLink): ?> 
                    document.getElementById("timer").innerHTML = `<a href="/storage/<?= htmlspecialchars($event->task, ENT_QUOTES, 'UTF-8') ?>" class="text-blue-500 underline">Задачи</a>`;
                <?php endif; ?>
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            let text = '';
            if (days > 0) text += `${days}д `;
            text += `${hours}ч ${minutes}м ${seconds}с`;
            document.getElementById("timer").innerText = text;

            setTimeout(updateTimer, 1000);
        }

        updateTimer();
    </script>
<?php endif; ?>



<?php
// Prepare data for all views
$criteria = R::findAll('criteria', 'event_id = ?', [$id]);
$teamScores = [];
foreach ($teams as $team) {
    $totalScore = (int) R::getCell("SELECT COALESCE(SUM(score),0) FROM scores WHERE team_id = ?", [$team->id]);
    $team->total_score = $totalScore;
    $teamScores[] = $team;
}
usort($teamScores, function($a, $b) { return $b->total_score <=> $a->total_score; });

$juryIdsPublic = R::getCol('SELECT DISTINCT s.jury_id FROM scores s JOIN teams t ON s.team_id = t.id WHERE t.event_id = ? AND s.score > 0', [$id]);
$juryCount = count($juryIdsPublic);

// Calculate average and re-sort by average
foreach ($teamScores as $ts) {
    $ts->avg_score = $juryCount > 0 ? round($ts->total_score / $juryCount, 1) : 0;
}
usort($teamScores, function($a, $b) {
    return $b->avg_score <=> $a->avg_score ?: $b->total_score <=> $a->total_score;
});
?>

    <div class="flex gap-2 justify-center mt-4">
        <a href="index.php" class="button">Назад</a>
    </div>

<?php if ((int)$event->show_leaderboard && !empty($teamScores)) : ?>
<?php
    $criteriaArr = array_values(is_array($criteria) ? $criteria : iterator_to_array($criteria));
    $criteriaCount = count($criteriaArr);
    $colsPerJury = $criteriaCount + 1; // criteria + итого
?>

    <h3 class="font-semibold mt-6 text-lg">Результаты</h3>

    <div class="overflow-x-auto mt-3">
    <table class="border-collapse border border-gray-400 text-xs" style="min-width:100%">
        <!-- Header row 1: № | Команда | Жюри 1 (colspan) | Жюри 2 (colspan) | ... | Общий -->
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-gray-400 p-2 text-center" rowspan="2">№</th>
                <th class="border border-gray-400 p-2 text-left" rowspan="2">Команда</th>
                <?php for ($ji = 0; $ji < $juryCount; $ji++): ?>
                    <th class="border border-gray-400 p-2 text-center bg-blue-50 text-blue-800" colspan="<?= $colsPerJury ?>">Жюри <?= $ji + 1 ?></th>
                <?php endfor; ?>
                <th class="border border-gray-400 p-2 text-center bg-gray-800 text-white" rowspan="2">Общий<br>балл</th>
                <th class="border border-gray-400 p-2 text-center bg-green-700 text-white" rowspan="2">Средний<br>балл</th>
            </tr>
            <!-- Header row 2: criteria names repeated per jury -->
            <tr class="bg-gray-50">
                <?php for ($ji = 0; $ji < $juryCount; $ji++): ?>
                    <?php foreach ($criteriaArr as $cr): ?>
                        <th class="border border-gray-400 p-1.5 text-center font-medium text-gray-700" style="font-size:10px;min-width:60px"><?= htmlspecialchars($cr->name) ?></th>
                    <?php endforeach; ?>
                    <th class="border border-gray-400 p-1.5 text-center font-bold bg-blue-50 text-blue-800" style="font-size:10px">Итого</th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php $rank = 0; foreach ($teamScores as $ts): $rank++; ?>
            <tr class="hover:bg-yellow-50 <?= $rank % 2 === 0 ? 'bg-gray-50' : 'bg-white' ?>">
                <td class="border border-gray-400 p-2 text-center font-bold"><?= $rank ?></td>
                <td class="border border-gray-400 p-2 font-semibold text-left whitespace-nowrap"><?= htmlspecialchars($ts->name) ?></td>
                <?php foreach ($juryIdsPublic as $ji => $jid):
                    $juryTotal = 0;
                    foreach ($criteriaArr as $cr):
                        $s = (int) R::getCell('SELECT COALESCE(score,0) FROM scores WHERE team_id = ? AND criteria_id = ? AND jury_id = ?', [$ts->id, $cr->id, $jid]);
                        $juryTotal += $s;
                ?>
                        <td class="border border-gray-300 p-1.5 text-center <?= $s > 0 ? '' : 'text-gray-300' ?>"><?= $s ?></td>
                    <?php endforeach; ?>
                    <td class="border border-gray-400 p-1.5 text-center font-bold bg-blue-50 text-blue-800"><?= $juryTotal ?></td>
                <?php endforeach; ?>
                <td class="border border-gray-400 p-2 text-center font-bold text-lg bg-gray-100"><?= $ts->total_score ?></td>
                <td class="border border-gray-400 p-2 text-center font-bold text-lg bg-green-50 text-green-800"><?= $juryCount > 0 ? round($ts->total_score / $juryCount, 1) : 0 ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

<?php endif; ?>

    <!-- Team select for certificates/members -->
    <h3 class="font-semibold mt-6 text-lg">Команда</h3>
    <form method="GET" class="mt-3">
        <input type="hidden" name="id" value="<?= $id ?>">
        <select name="team_id" class="w-full p-3 border border-gray-300 rounded-lg text-gray-700 bg-white focus:border-blue-500 outline-none">
            <option value="">Выберите команду</option>
            <?php foreach ($teams as $team) : ?>
                <option value="<?= $team->id ?>" <?= ($team_id == $team->id) ? 'selected' : '' ?>><?= $team->name ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="mt-3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition text-sm">
            Показать
        </button>
    </form>

    <?php if ($team_id) :
        $selectedTeam = R::findOne('teams', 'id = ?', [$team_id]);
        $members = R::findAll('teamuser', 'team_id = ?', [$team_id]);
        if ($members) : ?>

        <div class="mt-5 fade-in">
            <div class="bg-blue-600 text-white px-5 py-3 rounded-t-lg flex items-center justify-between">
                <div>
                    <span class="font-bold text-base"><?= htmlspecialchars($selectedTeam->name ?? 'Команда') ?></span>
                    <span class="text-blue-200 text-sm ml-2">(<?= count($members) ?>)</span>
                </div>
                <?php
                $certificates = R::find("certificates", "team_id = ? AND type = ?", [$team_id, "team"]);
                if ($certificates) :
                    foreach ($certificates as $cert) : ?>
                        <a href="certificate.php?id=<?= $cert->id ?>" class="bg-white text-blue-600 hover:bg-blue-50 font-bold py-1.5 px-4 rounded-lg transition text-sm">
                            Скачать диплом
                        </a>
                    <?php endforeach;
                endif; ?>
            </div>

            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-200 px-4 py-2 text-left w-12">№</th>
                        <th class="border border-gray-200 px-4 py-2 text-left">Ф.И.О.</th>
                        <th class="border border-gray-200 px-4 py-2 text-left w-24">Роль</th>
                        <th class="border border-gray-200 px-4 py-2 text-center w-32"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $mi = 0; foreach ($members as $member) : $mi++; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 px-4 py-2.5 text-gray-500"><?= $mi ?></td>
                            <td class="border border-gray-200 px-4 py-2.5 font-medium text-gray-800"><?= htmlspecialchars($member->full_name) ?></td>
                            <td class="border border-gray-200 px-4 py-2.5">
                                <?php if ($member->role === 'mentor') : ?>
                                    <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Ментор</span>
                                <?php else : ?>
                                    <span class="text-xs text-gray-400">Участник</span>
                                <?php endif; ?>
                            </td>
                            <td class="border border-gray-200 px-4 py-2.5 text-center">
                                <?php
                                $certs = R::find("certificates", "team_user_id = ? AND type = ?", [$member->id, "team_user"]);
                                if ($certs) :
                                    foreach ($certs as $cert) : ?>
                                        <a href="certificate.php?id=<?= $cert->id ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium hover:underline">Сертификат</a>
                                    <?php endforeach;
                                endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else : ?>
        <p class="text-gray-400 mt-4 text-sm">В этой команде пока нет участников.</p>
    <?php endif; endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
