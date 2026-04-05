<?php
require '../includes/auth.php';
require '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /organizer/');
    exit();
}

$error = '';
$success = '';

// Handle Update Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $id = (int)$_POST['event_id'];
    $event = R::load('events', $id);
    if ($event->id) {
        $title = trim($_POST['title']);
        if (empty($title)) {
            $error = 'Название обязательно.';
        } else {
            $event->title = $title;
            $event->task_start = $_POST['task_start'];
            $event->show_leaderboard = isset($_POST['show_leaderboard']) ? 1 : 0;
            $event->show_registration = isset($_POST['show_registration']) ? 1 : 0;
            $event->status = $_POST['status'];
            $event->slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

            if (isset($_FILES['task']) && $_FILES['task']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../storage/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $extension = pathinfo($_FILES['task']['name'], PATHINFO_EXTENSION);
                $taskFilename = uniqid('task_') . '.' . $extension;
                if (move_uploaded_file($_FILES['task']['tmp_name'], $uploadDir . $taskFilename)) {
                    $event->task = $taskFilename;
                }
            }

            R::store($event);
            $success = 'Хакатон обновлён!';
            header('Location: /organizer/events.php');
            exit();
        }
    }
}

// Handle Delete Event (cascade)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $event = R::load('events', $id);
    if ($event->id) {
        // Delete scores for this event's criteria and teams
        $criteriaIds = R::getCol('SELECT id FROM criteria WHERE event_id = ?', [$id]);
        $teamIds = R::getCol('SELECT id FROM teams WHERE event_id = ?', [$id]);

        if (!empty($criteriaIds)) {
            $ph = implode(',', array_fill(0, count($criteriaIds), '?'));
            R::exec("DELETE FROM scores WHERE criteria_id IN ($ph)", $criteriaIds);
        }
        if (!empty($teamIds)) {
            $ph = implode(',', array_fill(0, count($teamIds), '?'));
            R::exec("DELETE FROM scores WHERE team_id IN ($ph)", $teamIds);
            R::exec("DELETE FROM teamuser WHERE team_id IN ($ph)", $teamIds);
        }

        R::exec('DELETE FROM criteria WHERE event_id = ?', [$id]);
        R::exec('DELETE FROM teams WHERE event_id = ?', [$id]);
        R::exec('DELETE FROM juryevent WHERE event_id = ?', [$id]);
        R::trash($event);

        header('Location: /organizer/events.php');
        exit();
    }
}

// Handle Edit mode
$editEvent = null;
if (isset($_GET['edit'])) {
    $editEvent = R::load('events', (int)$_GET['edit']);
    if (!$editEvent->id) $editEvent = null;
}

$events = R::findAll('events', 'ORDER BY id DESC');
?>

<div class="container mx-auto mt-10 p-5">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Хакатоны</h2>
        <div class="flex gap-2">
            <a href="/organizer/event_add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Создать хакатон</a>
            <a href="/organizer/" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Назад</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($editEvent): ?>
    <!-- Edit Event Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h3 class="text-xl font-semibold mb-4">Редактировать хакатон</h3>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="event_id" value="<?= $editEvent->id ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Название</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($editEvent->title) ?>" class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Дата начала заданий</label>
                    <input type="datetime-local" name="task_start" value="<?= htmlspecialchars($editEvent->task_start) ?>" class="w-full p-2 border rounded">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Статус</label>
                    <select name="status" class="w-full p-2 border rounded">
                        <option value="active" <?= $editEvent->status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="pending" <?= $editEvent->status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="archived" <?= $editEvent->status === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Файл задания</label>
                    <input type="file" name="task" class="w-full p-2 border rounded">
                    <?php if ($editEvent->task): ?>
                        <p class="text-sm text-gray-500 mt-1">Текущий: <?= htmlspecialchars($editEvent->task) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="show_leaderboard" class="w-5 h-5 text-blue-600" <?= $editEvent->show_leaderboard ? 'checked' : '' ?>>
                    <span class="text-gray-700 font-bold">Показывать результаты</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="show_registration" class="w-5 h-5 text-blue-600" <?= $editEvent->show_registration ? 'checked' : '' ?>>
                    <span class="text-gray-700 font-bold">Показывать регистрацию</span>
                </label>
            </div>
            <div class="flex gap-2">
                <button type="submit" name="update_event" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-6 rounded">Сохранить</button>
                <a href="events.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-6 rounded inline-flex items-center">Отмена</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Events List -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 text-left">ID</th>
                    <th class="py-3 px-4 text-left">Название</th>
                    <th class="py-3 px-4 text-center">Статус</th>
                    <th class="py-3 px-4 text-center">Лидерборд</th>
                    <th class="py-3 px-4 text-center">Команд</th>
                    <th class="py-3 px-4 text-center">Критериев</th>
                    <th class="py-3 px-4 text-center">Действия</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                <?php foreach ($events as $event): ?>
                    <?php
                    $teamCount = R::count('teams', 'event_id = ?', [$event->id]);
                    $criteriaCount = R::count('criteria', 'event_id = ?', [$event->id]);
                    $statusBadge = match($event->status) {
                        'active' => 'bg-green-100 text-green-800',
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'archived' => 'bg-gray-100 text-gray-800',
                        default => 'bg-gray-100 text-gray-800',
                    };
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4 font-mono text-sm"><?= $event->id ?></td>
                        <td class="py-3 px-4 font-semibold"><?= htmlspecialchars($event->title) ?></td>
                        <td class="py-3 px-4 text-center">
                            <span class="inline-block text-xs px-2 py-1 rounded <?= $statusBadge ?>"><?= htmlspecialchars($event->status ?? 'active') ?></span>
                        </td>
                        <td class="py-3 px-4 text-center"><?= $event->show_leaderboard ? 'Да' : 'Нет' ?></td>
                        <td class="py-3 px-4 text-center"><?= $teamCount ?></td>
                        <td class="py-3 px-4 text-center"><?= $criteriaCount ?></td>
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            <a href="event_detail.php?id=<?= $event->id ?>" class="text-green-600 hover:text-green-800 font-bold mr-2">Подробнее</a>
                            <a href="?edit=<?= $event->id ?>" class="text-blue-500 hover:text-blue-700 font-bold mr-2">Ред.</a>
                            <a href="?delete=<?= $event->id ?>" onclick="return confirm('Удалить хакатон и все связанные данные (команды, критерии, баллы)?')" class="text-red-500 hover:text-red-700 font-bold">Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($events)): ?>
                    <tr><td colspan="7" class="py-8 text-center text-gray-400">Нет хакатонов</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
