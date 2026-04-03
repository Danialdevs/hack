<?php
session_start();
require '../includes/db.php';
require '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /organizer/');
    exit();
}

$eventId = (int)($_GET['event_id'] ?? 0);
$teamId = (int)($_GET['team_id'] ?? 0);

$event = R::load('events', $eventId);
$team = R::load('teams', $teamId);

if (!$event->id || !$team->id || $team->event_id != $eventId) {
    header('Location: /organizer/events.php');
    exit();
}

$error = '';
$success = '';

// Handle Add Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $fullName = trim($_POST['full_name']);
    $role = $_POST['role'];
    if ($fullName) {
        $m = R::dispense('teamuser');
        $m->team_id = $teamId;
        $m->event_id = $eventId;
        $m->full_name = $fullName;
        $m->role = $role;
        R::store($m);
        header("Location: /organizer/team_detail.php?event_id=$eventId&team_id=$teamId");
        exit();
    } else {
        $error = 'Введите ФИО участника.';
    }
}

// Handle Update Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_member'])) {
    $mid = (int)$_POST['member_id'];
    $member = R::load('teamuser', $mid);
    if ($member->id && $member->team_id == $teamId) {
        $fullName = trim($_POST['full_name']);
        if ($fullName) {
            $member->full_name = $fullName;
            $member->role = $_POST['role'];
            R::store($member);
            header("Location: /organizer/team_detail.php?event_id=$eventId&team_id=$teamId");
            exit();
        } else {
            $error = 'ФИО не может быть пустым.';
        }
    }
}

// Handle Update Team Name
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_team_name'])) {
    $newName = trim($_POST['team_name']);
    if ($newName) {
        $team->name = $newName;
        R::store($team);
        header("Location: /organizer/team_detail.php?event_id=$eventId&team_id=$teamId");
        exit();
    } else {
        $error = 'Название команды не может быть пустым.';
    }
}

// Handle Delete Member
if (isset($_GET['delete_member'])) {
    $mid = (int)$_GET['delete_member'];
    $member = R::load('teamuser', $mid);
    if ($member->id && $member->team_id == $teamId) {
        R::trash($member);
        header("Location: /organizer/team_detail.php?event_id=$eventId&team_id=$teamId");
        exit();
    }
}

// Edit mode
$editMember = null;
if (isset($_GET['edit_member'])) {
    $editMember = R::load('teamuser', (int)$_GET['edit_member']);
    if (!$editMember->id || $editMember->team_id != $teamId) $editMember = null;
}

$editTeamName = isset($_GET['edit_team_name']);

// Load data
$members = R::findAll('teamuser', 'team_id = ? ORDER BY id', [$teamId]);
$totalScore = R::getCell('SELECT COALESCE(SUM(score), 0) FROM scores WHERE team_id = ?', [$teamId]);
$memberCount = count($members);
?>

<div class="container mx-auto mt-10 p-5">
    <!-- Breadcrumbs -->
    <nav class="text-sm text-gray-500 mb-4">
        <a href="/organizer/" class="hover:text-gray-700">Панель</a>
        <span class="mx-1">/</span>
        <a href="/organizer/events.php" class="hover:text-gray-700">Хакатоны</a>
        <span class="mx-1">/</span>
        <a href="/organizer/event_detail.php?id=<?= $eventId ?>" class="hover:text-gray-700"><?= htmlspecialchars($event->title) ?></a>
        <span class="mx-1">/</span>
        <span class="text-gray-800 font-semibold"><?= htmlspecialchars($team->name) ?></span>
    </nav>

    <!-- Team Info Card -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <?php if ($editTeamName): ?>
                <form method="POST" class="flex gap-2 items-center">
                    <input type="text" name="team_name" value="<?= htmlspecialchars($team->name) ?>" class="text-2xl font-bold p-2 border rounded" required>
                    <button type="submit" name="update_team_name" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">Сохранить</button>
                    <a href="team_detail.php?event_id=<?= $eventId ?>&team_id=<?= $teamId ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-4 rounded">Отмена</a>
                </form>
                <?php else: ?>
                <h2 class="text-3xl font-bold text-gray-800">
                    <?= htmlspecialchars($team->name) ?>
                    <a href="?event_id=<?= $eventId ?>&team_id=<?= $teamId ?>&edit_team_name=1" class="text-blue-500 hover:text-blue-700 text-base ml-2">Ред.</a>
                </h2>
                <?php endif; ?>
                <div class="flex flex-wrap gap-2 mt-2">
                    <span class="inline-block text-xs px-2 py-1 rounded bg-blue-100 text-blue-800">Участников: <?= $memberCount ?></span>
                    <span class="inline-block text-xs px-2 py-1 rounded bg-purple-100 text-purple-800">Баллы: <?= $totalScore ?></span>
                </div>
            </div>
            <a href="/organizer/event_detail.php?id=<?= $eventId ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Назад к мероприятию</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Members CRUD -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold mb-4">Участники</h3>

        <?php if ($editMember): ?>
        <form method="POST" class="flex flex-col sm:flex-row gap-2 mb-4">
            <input type="hidden" name="member_id" value="<?= $editMember->id ?>">
            <input type="text" name="full_name" value="<?= htmlspecialchars($editMember->full_name) ?>" class="flex-1 p-2 border rounded" required placeholder="ФИО">
            <select name="role" class="p-2 border rounded">
                <option value="participant" <?= $editMember->role === 'participant' ? 'selected' : '' ?>>Участник</option>
                <option value="mentor" <?= $editMember->role === 'mentor' ? 'selected' : '' ?>>Ментор</option>
            </select>
            <button type="submit" name="update_member" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">Сохранить</button>
            <a href="team_detail.php?event_id=<?= $eventId ?>&team_id=<?= $teamId ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-4 rounded inline-flex items-center">Отмена</a>
        </form>
        <?php else: ?>
        <form method="POST" class="flex flex-col sm:flex-row gap-2 mb-4">
            <input type="text" name="full_name" placeholder="ФИО участника" class="flex-1 p-2 border rounded" required>
            <select name="role" class="p-2 border rounded">
                <option value="participant">Участник</option>
                <option value="mentor">Ментор</option>
            </select>
            <button type="submit" name="add_member" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Добавить</button>
        </form>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="py-2 px-3 text-left">#</th>
                        <th class="py-2 px-3 text-left">ФИО</th>
                        <th class="py-2 px-3 text-center">Роль</th>
                        <th class="py-2 px-3 text-center">Действия</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php $mi = 1; foreach ($members as $m): ?>
                        <?php
                        $roleBadge = $m->role === 'mentor'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-blue-100 text-blue-800';
                        $roleLabel = $m->role === 'mentor' ? 'Ментор' : 'Участник';
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-3"><?= $mi++ ?></td>
                            <td class="py-2 px-3 font-semibold"><?= htmlspecialchars($m->full_name) ?></td>
                            <td class="py-2 px-3 text-center">
                                <span class="inline-block text-xs px-2 py-1 rounded <?= $roleBadge ?>"><?= $roleLabel ?></span>
                            </td>
                            <td class="py-2 px-3 text-center whitespace-nowrap">
                                <a href="?event_id=<?= $eventId ?>&team_id=<?= $teamId ?>&edit_member=<?= $m->id ?>" class="text-blue-500 hover:text-blue-700 font-bold mr-2">Ред.</a>
                                <a href="?event_id=<?= $eventId ?>&team_id=<?= $teamId ?>&delete_member=<?= $m->id ?>" onclick="return confirm('Удалить участника?')" class="text-red-500 hover:text-red-700 font-bold">Удалить</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($members)): ?>
                        <tr><td colspan="4" class="py-6 text-center text-gray-400">Нет участников</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
