<?php
session_start();
require '../includes/db.php';
require '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /organizer/');
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$event = R::load('events', $id);
if (!$event->id) {
    header('Location: /organizer/events.php');
    exit();
}

$error = '';
$success = '';

// --- SETTINGS HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $event->show_leaderboard = isset($_POST['show_leaderboard']) ? 1 : 0;
    $event->show_registration = isset($_POST['show_registration']) ? 1 : 0;
    $event->task_start = $_POST['task_start'] ?? '';
    R::store($event);
    header("Location: /organizer/event_detail.php?id=$id");
    exit();
}

// --- CRITERIA HANDLERS ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_criteria'])) {
    $name = trim($_POST['criteria_name']);
    if ($name) {
        $c = R::dispense('criteria');
        $c->name = $name;
        $c->event_id = $id;
        R::store($c);
        header("Location: /organizer/event_detail.php?id=$id");
        exit();
    } else {
        $error = 'Введите название критерия.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_criteria'])) {
    $cid = (int)$_POST['criteria_id'];
    $criteria = R::load('criteria', $cid);
    if ($criteria->id && $criteria->event_id == $id) {
        $name = trim($_POST['criteria_name']);
        if ($name) {
            $criteria->name = $name;
            R::store($criteria);
            header("Location: /organizer/event_detail.php?id=$id");
            exit();
        } else {
            $error = 'Название критерия не может быть пустым.';
        }
    }
}

if (isset($_GET['delete_criteria'])) {
    $cid = (int)$_GET['delete_criteria'];
    $criteria = R::load('criteria', $cid);
    if ($criteria->id && $criteria->event_id == $id) {
        R::exec('DELETE FROM scores WHERE criteria_id = ?', [$cid]);
        R::trash($criteria);
        header("Location: /organizer/event_detail.php?id=$id");
        exit();
    }
}

$editCriteria = null;
if (isset($_GET['edit_criteria'])) {
    $editCriteria = R::load('criteria', (int)$_GET['edit_criteria']);
    if (!$editCriteria->id || $editCriteria->event_id != $id) $editCriteria = null;
}

// --- TEAM HANDLERS ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_team'])) {
    $name = trim($_POST['team_name']);
    if ($name) {
        $t = R::dispense('teams');
        $t->name = $name;
        $t->event_id = $id;
        R::store($t);
        header("Location: /organizer/event_detail.php?id=$id");
        exit();
    } else {
        $error = 'Введите название команды.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_team'])) {
    $tid = (int)$_POST['team_id'];
    $team = R::load('teams', $tid);
    if ($team->id && $team->event_id == $id) {
        $name = trim($_POST['team_name']);
        if ($name) {
            $team->name = $name;
            R::store($team);
            header("Location: /organizer/event_detail.php?id=$id");
            exit();
        } else {
            $error = 'Название команды не может быть пустым.';
        }
    }
}

if (isset($_GET['delete_team'])) {
    $tid = (int)$_GET['delete_team'];
    $team = R::load('teams', $tid);
    if ($team->id && $team->event_id == $id) {
        R::exec('DELETE FROM scores WHERE team_id = ?', [$tid]);
        R::exec('DELETE FROM teamuser WHERE team_id = ?', [$tid]);
        R::trash($team);
        header("Location: /organizer/event_detail.php?id=$id");
        exit();
    }
}

$editTeam = null;
if (isset($_GET['edit_team'])) {
    $editTeam = R::load('teams', (int)$_GET['edit_team']);
    if (!$editTeam->id || $editTeam->event_id != $id) $editTeam = null;
}

// --- LOAD DATA ---
$criteriaList = R::findAll('criteria', 'event_id = ? ORDER BY id', [$id]);
$teams = R::findAll('teams', 'event_id = ? ORDER BY id', [$id]);

$statusBadge = match($event->status) {
    'active' => 'bg-green-100 text-green-800',
    'pending' => 'bg-yellow-100 text-yellow-800',
    'archived' => 'bg-gray-100 text-gray-800',
    default => 'bg-gray-100 text-gray-800',
};
?>

<div class="container mx-auto mt-10 p-5">
    <!-- Breadcrumbs -->
    <nav class="text-sm text-gray-500 mb-4">
        <a href="/organizer/" class="hover:text-gray-700">Панель</a>
        <span class="mx-1">/</span>
        <a href="/organizer/events.php" class="hover:text-gray-700">Хакатоны</a>
        <span class="mx-1">/</span>
        <span class="text-gray-800 font-semibold"><?= htmlspecialchars($event->title) ?></span>
    </nav>

    <!-- Event Info Card -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5">
            <div>
                <h2 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($event->title) ?></h2>
                <span class="inline-block text-xs px-2 py-1 rounded mt-2 <?= $statusBadge ?>"><?= htmlspecialchars($event->status ?? 'active') ?></span>
            </div>
            <div class="flex gap-2">
                <a href="/organizer/certificates.php?event=<?= $id ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">Сертификаты</a>
                <a href="/organizer/events.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Назад к списку</a>
            </div>
        </div>

        <!-- Settings -->
        <form method="POST" class="border-t pt-4">
            <h4 class="font-semibold text-gray-700 mb-3">Настройки</h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Дата начала задач</label>
                    <input type="datetime-local" name="task_start" value="<?= htmlspecialchars($event->task_start) ?>" class="w-full p-2 border rounded text-sm">
                    <?php if (empty($event->task_start)): ?>
                        <p class="text-xs text-gray-400 mt-1">Не задано — таймер не показывается</p>
                    <?php endif; ?>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="show_registration" class="w-4 h-4" <?= ($event->show_registration === null || $event->show_registration) ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-700">Регистрация</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="show_leaderboard" class="w-4 h-4" <?= $event->show_leaderboard ? 'checked' : '' ?>>
                        <span class="text-sm text-gray-700">Результаты</span>
                    </label>
                </div>
                <div>
                    <button type="submit" name="update_settings" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded text-sm">Сохранить</button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Two-column layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- LEFT: Criteria -->
        <div>
            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <h3 class="text-xl font-semibold mb-4">Критерии оценки</h3>

                <?php if ($editCriteria): ?>
                <form method="POST" class="flex gap-2 mb-4">
                    <input type="hidden" name="criteria_id" value="<?= $editCriteria->id ?>">
                    <input type="text" name="criteria_name" value="<?= htmlspecialchars($editCriteria->name) ?>" class="flex-1 p-2 border rounded" required>
                    <button type="submit" name="update_criteria" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">Сохранить</button>
                    <a href="event_detail.php?id=<?= $id ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-4 rounded inline-flex items-center">Отмена</a>
                </form>
                <?php else: ?>
                <form method="POST" class="flex gap-2 mb-4">
                    <input type="text" name="criteria_name" placeholder="Новый критерий" class="flex-1 p-2 border rounded" required>
                    <button type="submit" name="add_criteria" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Добавить</button>
                </form>
                <?php endif; ?>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="py-2 px-3 text-left">#</th>
                                <th class="py-2 px-3 text-left">Название</th>
                                <th class="py-2 px-3 text-center">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <?php $ci = 1; foreach ($criteriaList as $c): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-2 px-3"><?= $ci++ ?></td>
                                    <td class="py-2 px-3"><?= htmlspecialchars($c->name) ?></td>
                                    <td class="py-2 px-3 text-center whitespace-nowrap">
                                        <a href="?id=<?= $id ?>&edit_criteria=<?= $c->id ?>" class="text-blue-500 hover:text-blue-700 font-bold mr-2">Ред.</a>
                                        <a href="?id=<?= $id ?>&delete_criteria=<?= $c->id ?>" onclick="return confirm('Удалить критерий и связанные баллы?')" class="text-red-500 hover:text-red-700 font-bold">Удалить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($criteriaList)): ?>
                                <tr><td colspan="3" class="py-6 text-center text-gray-400">Нет критериев</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT: Teams -->
        <div>
            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <h3 class="text-xl font-semibold mb-4">Команды</h3>

                <?php if ($editTeam): ?>
                <form method="POST" class="flex gap-2 mb-4">
                    <input type="hidden" name="team_id" value="<?= $editTeam->id ?>">
                    <input type="text" name="team_name" value="<?= htmlspecialchars($editTeam->name) ?>" class="flex-1 p-2 border rounded" required>
                    <button type="submit" name="update_team" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">Сохранить</button>
                    <a href="event_detail.php?id=<?= $id ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-4 rounded inline-flex items-center">Отмена</a>
                </form>
                <?php else: ?>
                <form method="POST" class="flex gap-2 mb-4">
                    <input type="text" name="team_name" placeholder="Новая команда" class="flex-1 p-2 border rounded" required>
                    <button type="submit" name="add_team" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Добавить</button>
                </form>
                <?php endif; ?>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-800 text-white">
                            <tr>
                                <th class="py-2 px-3 text-left">#</th>
                                <th class="py-2 px-3 text-left">Название</th>
                                <th class="py-2 px-3 text-center">Участников</th>
                                <th class="py-2 px-3 text-center">Баллы</th>
                                <th class="py-2 px-3 text-center">Действия</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                            <?php $ti = 1; foreach ($teams as $t): ?>
                                <?php
                                $memberCount = R::count('teamuser', 'team_id = ?', [$t->id]);
                                $totalScore = R::getCell('SELECT COALESCE(SUM(score), 0) FROM scores WHERE team_id = ?', [$t->id]);
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="py-2 px-3"><?= $ti++ ?></td>
                                    <td class="py-2 px-3 font-semibold"><?= htmlspecialchars($t->name) ?></td>
                                    <td class="py-2 px-3 text-center"><?= $memberCount ?></td>
                                    <td class="py-2 px-3 text-center"><?= $totalScore ?></td>
                                    <td class="py-2 px-3 text-center whitespace-nowrap">
                                        <a href="team_detail.php?event_id=<?= $id ?>&team_id=<?= $t->id ?>" class="text-green-600 hover:text-green-800 font-bold mr-2">Подробнее</a>
                                        <a href="?id=<?= $id ?>&edit_team=<?= $t->id ?>" class="text-blue-500 hover:text-blue-700 font-bold mr-2">Ред.</a>
                                        <a href="?id=<?= $id ?>&delete_team=<?= $t->id ?>" onclick="return confirm('Удалить команду и все связанные данные?')" class="text-red-500 hover:text-red-700 font-bold">Удалить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($teams)): ?>
                                <tr><td colspan="5" class="py-6 text-center text-gray-400">Нет команд</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require '../includes/footer.php'; ?>
