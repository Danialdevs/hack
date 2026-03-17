<?php
include '../includes/db.php';
include '../includes/header.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    die("Ошибка: требуется авторизация.");
}

$id = $_GET['id'] ?? 0;
$event = R::load('events', $id);
$teams = R::findAll('teams', 'event_id = ?', [$id]);
$criteria = R::findAll('criteria', 'event_id = ? ', [$id]);

$juryId = $_SESSION['user_id'];

// Enforce Jury Event Restriction
if ($_SESSION['user_role'] === 'jury') {
    $hasAccess = R::findOne('juryevent', 'user_id = ? AND event_id = ?', [$_SESSION['user_id'], $id]);
    if (!$hasAccess && isset($_SESSION['user_event_id']) && $_SESSION['user_event_id'] == $id) {
        $hasAccess = true;
    }
    if (!$hasAccess) {
        die("Ошибка: У вас нет доступа к этому мероприятию.");
    }
}
?>

<div class="mx-4 sm:mx-6 mt-5">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <a href="/organizer/" class="text-gray-500 hover:text-gray-700 text-sm font-medium">&larr; Назад</a>
            <h3 class="text-xl font-semibold"><?= htmlspecialchars($event->title) ?></h3>
        </div>
    </div>

    <div class="overflow-x-auto">
    <table class="min-w-full bg-white shadow-lg rounded-lg overflow-hidden">
        <thead>
        <tr class="bg-gray-300 text-gray-700">
            <th class="py-3 px-6 text-left">#</th>
            <th class="py-3 px-6 text-left">Команда</th>
            <?php foreach ($criteria as $criterion): ?>
                <th class="py-3 px-6 text-left"><?= htmlspecialchars($criterion->name) ?></th>
            <?php endforeach; ?>
            <th class="py-3 px-6 text-left">Итого (Вы)</th>
            <th class="py-3 px-6 text-left">Общий балл</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $teamData = [];
        foreach ($teams as $team) {
            $globalScore = (int) R::getCell("SELECT SUM(score) FROM scores WHERE team_id = ?", [$team->id]);
            $team->global_score = $globalScore;
            $localScore = (int) R::getCell("SELECT SUM(score) FROM scores WHERE team_id = ? AND jury_id = ?", [$team->id, $juryId]);
            $team->local_score = $localScore;
            $teamData[] = $team;
        }

        usort($teamData, function($a, $b) {
            return $b->global_score <=> $a->global_score;
        });

        $rank = 0;
        foreach ($teamData as $team):
            $rank++;
        ?>
            <tr class="hover:bg-gray-100" data-team-id="<?= $team->id ?>">
                <td class="py-3 px-6 border-b font-bold"><?= $rank ?></td>
                <td class="py-3 px-6 border-b"><?= htmlspecialchars($team->name) ?></td>
                <?php foreach ($criteria as $criterion): ?>
                    <?php
                    $existingScore = R::findOne(
                        'scores',
                        'team_id = ? AND criteria_id = ? AND jury_id = ?',
                        [$team->id, $criterion->id, $juryId]
                    );
                    $scoreValue = $existingScore ? $existingScore->score : 0;
                    ?>
                    <td class="py-3 px-6 border-b">
                        <input type="number"
                               min="0" max="10"
                               value="<?= $scoreValue ?>"
                               class="score-input border rounded w-16 text-center"
                               data-team-id="<?= $team->id ?>"
                               data-criteria-id="<?= $criterion->id ?>"
                               data-jury-id="<?= $juryId ?>">
                    </td>
                <?php endforeach; ?>
                <td class="py-3 px-6 border-b font-bold text-gray-700 total-score">
                    <?= $team->local_score ?>
                </td>
                <td class="py-3 px-6 border-b font-bold text-lg text-blue-600 global-score">
                    <?= $team->global_score ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($_SESSION['user_role'] === 'admin'): ?>
<!-- Admin: кто как проголосовал -->
<div class="mx-4 sm:mx-6 mt-8">
    <h3 class="text-xl font-semibold mb-4">Голосование жюри (детали)</h3>
    <?php
    // Get all jury members who scored in this event
    $juryIds = R::getCol('SELECT DISTINCT s.jury_id FROM scores s JOIN teams t ON s.team_id = t.id WHERE t.event_id = ?', [$id]);
    $juryMembers = [];
    foreach ($juryIds as $jid) {
        $u = R::load('users', $jid);
        if ($u->id) $juryMembers[] = $u;
    }

    if (empty($juryMembers)): ?>
        <div class="bg-white rounded-lg shadow p-6 text-center text-gray-400">Пока нет оценок</div>
    <?php else: ?>

    <!-- Summary: jury totals per team -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
        <div class="px-4 py-3 bg-gray-100 border-b font-semibold text-gray-700 text-sm">Итого по жюри</div>
        <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr class="bg-gray-800 text-white">
                    <th class="py-2 px-4 text-left">Жюри</th>
                    <?php foreach ($teamData as $team): ?>
                        <th class="py-2 px-4 text-center"><?= htmlspecialchars($team->name) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($juryMembers as $jm): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-2 px-4 font-semibold"><?= htmlspecialchars($jm->name) ?></td>
                    <?php foreach ($teamData as $team):
                        $jTotal = (int) R::getCell('SELECT COALESCE(SUM(score),0) FROM scores WHERE team_id = ? AND jury_id = ?', [$team->id, $jm->id]);
                    ?>
                        <td class="py-2 px-4 text-center font-bold <?= $jTotal > 0 ? 'text-blue-600' : 'text-gray-300' ?>"><?= $jTotal ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Detailed: per jury per team per criteria -->
    <?php foreach ($juryMembers as $jm): ?>
    <details class="bg-white rounded-lg shadow mb-4">
        <summary class="px-4 py-3 cursor-pointer hover:bg-gray-50 font-semibold text-gray-700 flex items-center gap-2">
            <span><?= htmlspecialchars($jm->name) ?></span>
            <span class="text-xs font-normal text-gray-400">(нажмите для деталей)</span>
        </summary>
        <div class="overflow-x-auto border-t">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gray-100 text-gray-600">
                    <th class="py-2 px-4 text-left">Команда</th>
                    <?php foreach ($criteria as $cr): ?>
                        <th class="py-2 px-4 text-center"><?= htmlspecialchars($cr->name) ?></th>
                    <?php endforeach; ?>
                    <th class="py-2 px-4 text-center font-bold">Итого</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teamData as $team):
                    $rowTotal = 0;
                ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-2 px-4 font-semibold"><?= htmlspecialchars($team->name) ?></td>
                    <?php foreach ($criteria as $cr):
                        $s = (int) R::getCell('SELECT COALESCE(score,0) FROM scores WHERE team_id = ? AND criteria_id = ? AND jury_id = ?', [$team->id, $cr->id, $jm->id]);
                        $rowTotal += $s;
                    ?>
                        <td class="py-2 px-4 text-center <?= $s > 0 ? '' : 'text-gray-300' ?>"><?= $s ?></td>
                    <?php endforeach; ?>
                    <td class="py-2 px-4 text-center font-bold text-blue-600"><?= $rowTotal ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </details>
    <?php endforeach; ?>

    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($_SESSION['user_role'] === 'admin'): ?>
<!-- Per-team breakdown -->
<div class="mx-4 sm:mx-6 mt-8 mb-8">
    <h3 class="text-xl font-semibold mb-3">Оценки по команде</h3>
    <?php
    $viewTeamId = $_GET['view_team'] ?? null;
    ?>
    <form method="GET" class="flex items-center gap-3 mb-4">
        <input type="hidden" name="id" value="<?= $id ?>">
        <select name="view_team" class="p-2 border rounded text-sm" onchange="this.form.submit()">
            <option value="">Выберите команду</option>
            <?php foreach ($teamData as $td): ?>
                <option value="<?= $td->id ?>" <?= $viewTeamId == $td->id ? 'selected' : '' ?>><?= htmlspecialchars($td->name) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($viewTeamId):
        $viewTeam = R::load('teams', (int)$viewTeamId);
        $juryIdsForTeam = R::getCol('SELECT DISTINCT jury_id FROM scores WHERE team_id = ? AND score > 0', [$viewTeamId]);
        $juryForTeam = [];
        foreach ($juryIdsForTeam as $jid) {
            $u = R::load('users', $jid);
            if ($u->id) $juryForTeam[] = $u;
        }
    ?>
        <?php if (empty($juryForTeam)): ?>
            <div class="bg-white rounded-lg shadow p-4 text-center text-gray-400 text-sm">Нет оценок для этой команды</div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="bg-blue-600 text-white px-4 py-2 flex items-center justify-between">
                    <span class="font-bold"><?= htmlspecialchars($viewTeam->name) ?></span>
                    <span class="text-blue-200 text-sm"><?= count($juryForTeam) ?> жюри оценило</span>
                </div>
                <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-gray-600">
                            <th class="py-2 px-4 text-left">Жюри</th>
                            <?php foreach ($criteria as $cr): ?>
                                <th class="py-2 px-4 text-center"><?= htmlspecialchars($cr->name) ?></th>
                            <?php endforeach; ?>
                            <th class="py-2 px-4 text-center font-bold">Итого</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($juryForTeam as $jm):
                            $rowTotal = 0;
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4 font-semibold"><?= htmlspecialchars($jm->name) ?></td>
                            <?php foreach ($criteria as $cr):
                                $s = (int) R::getCell('SELECT COALESCE(score,0) FROM scores WHERE team_id = ? AND criteria_id = ? AND jury_id = ?', [$viewTeamId, $cr->id, $jm->id]);
                                $rowTotal += $s;
                            ?>
                                <td class="py-2 px-4 text-center <?= $s > 0 ? '' : 'text-gray-300' ?>"><?= $s ?></td>
                            <?php endforeach; ?>
                            <td class="py-2 px-4 text-center font-bold text-blue-600"><?= $rowTotal ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Average row -->
                        <tr class="bg-gray-50 font-bold">
                            <td class="py-2 px-4 text-gray-600">Среднее</td>
                            <?php
                            $juryCountTeam = count($juryForTeam);
                            $grandTotal = 0;
                            foreach ($criteria as $cr):
                                $critSum = (int) R::getCell('SELECT COALESCE(SUM(score),0) FROM scores WHERE team_id = ? AND criteria_id = ? AND score > 0', [$viewTeamId, $cr->id]);
                                $critAvg = $juryCountTeam > 0 ? round($critSum / $juryCountTeam, 1) : 0;
                                $grandTotal += $critSum;
                            ?>
                                <td class="py-2 px-4 text-center"><?= $critAvg ?></td>
                            <?php endforeach; ?>
                            <td class="py-2 px-4 text-center text-blue-600"><?= $juryCountTeam > 0 ? round($grandTotal / $juryCountTeam, 1) : 0 ?></td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const inputs = document.querySelectorAll('.score-input');
        const debounceTimers = {};

        inputs.forEach(input => {
            const statusIndicator = document.createElement('span');
            statusIndicator.className = 'text-xs ml-1 font-semibold';
            input.parentNode.appendChild(statusIndicator);

            input.addEventListener('input', function () {
                let value = parseInt(this.value);
                if (value > 10) { this.value = 10; value = 10; }
                else if (value < 0) { this.value = 0; value = 0; }

                const teamId = this.dataset.teamId;
                const criteriaId = this.dataset.criteriaId;
                const juryId = this.dataset.juryId;
                const score = this.value;
                const key = `${teamId}-${criteriaId}`;

                statusIndicator.textContent = '...';
                statusIndicator.className = 'text-xs ml-1 font-semibold text-gray-500';
                updateTotalScore(teamId);

                if (debounceTimers[key]) clearTimeout(debounceTimers[key]);

                debounceTimers[key] = setTimeout(() => {
                    saveScore(teamId, criteriaId, juryId, score, statusIndicator);
                }, 800);
            });
        });

        function saveScore(teamId, criteriaId, juryId, score, statusIndicator) {
            statusIndicator.textContent = 'Сохр...';
            statusIndicator.className = 'text-xs ml-1 font-semibold text-orange-500';

            fetch('save_score.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `team_id=${teamId}&criteria_id=${criteriaId}&jury_id=${juryId}&score=${score}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    statusIndicator.textContent = 'OK';
                    statusIndicator.className = 'text-xs ml-1 font-semibold text-green-500';
                    setTimeout(() => {
                        if (statusIndicator.textContent === 'OK') statusIndicator.textContent = '';
                    }, 2000);
                } else {
                    statusIndicator.textContent = 'Ошибка';
                    statusIndicator.className = 'text-xs ml-1 font-semibold text-red-500';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusIndicator.textContent = 'Err';
                statusIndicator.className = 'text-xs ml-1 font-semibold text-red-500';
            });
        }

        function updateTotalScore(teamId) {
            let total = 0;
            document.querySelectorAll(`input[data-team-id='${teamId}']`).forEach(input => {
                total += parseInt(input.value) || 0;
            });
            const totalCell = document.querySelector(`tr[data-team-id='${teamId}'] .total-score`);
            if (totalCell) totalCell.textContent = total;
        }

        setInterval(function() {
            const eventId = <?= $id ?>;
            fetch(`get_global_scores.php?id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(item => {
                        const globalCell = document.querySelector(`tr[data-team-id='${item.team_id}'] .global-score`);
                        if (globalCell) {
                            if (globalCell.textContent != item.global_score) {
                                globalCell.style.color = '#2563eb';
                                setTimeout(() => globalCell.style.color = '', 500);
                            }
                            globalCell.textContent = item.global_score;
                        }
                    });
                })
                .catch(error => console.error('Error fetching global scores:', error));
        }, 5000);
    });
</script>

<?php include '../includes/footer.php'; ?>
