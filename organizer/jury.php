<?php
require '../includes/auth.php';
require '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /organizer/');
    exit();
}

$error = '';
$success = '';

// Handle Batch Add Jury
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_jury'])) {
    $names = $_POST['jury_name'] ?? [];
    $passwords = $_POST['jury_password'] ?? [];
    $eventIds = $_POST['jury_events'] ?? [];

    $added = 0;
    foreach ($names as $i => $rawName) {
        $name = trim($rawName);
        $password = $passwords[$i] ?? '';

        if (empty($name) || empty($password)) continue;

        $user = R::dispense('users');
        $user->name = $name;
        $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $user->role = 'jury';
        $user->event_id = 0;
        $userId = R::store($user);

        if (isset($eventIds[$i]) && is_array($eventIds[$i])) {
            foreach ($eventIds[$i] as $eid) {
                $je = R::dispense('juryevent');
                $je->user_id = $userId;
                $je->event_id = (int)$eid;
                R::store($je);
            }
        }
        $added++;
    }

    if ($added > 0) {
        $success = "Добавлено членов жюри: $added";
    } else {
        $error = 'Заполните имя и пароль хотя бы для одного жюри.';
    }
}

// Handle Update Jury
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_jury'])) {
    $id = $_POST['jury_id'];
    $name = trim($_POST['name']);
    $password = $_POST['password'];
    $selectedEvents = $_POST['events'] ?? [];

    $user = R::load('users', $id);
    if ($user->id && $user->role === 'jury') {
        if (empty($name)) {
            $error = 'Имя обязательно.';
        } else {
            $user->name = $name;
            if (!empty($password)) {
                $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            }
            R::store($user);

            R::exec('DELETE FROM juryevent WHERE user_id = ?', [$id]);
            foreach ($selectedEvents as $eid) {
                $je = R::dispense('juryevent');
                $je->user_id = $id;
                $je->event_id = (int)$eid;
                R::store($je);
            }

            $success = 'Данные жюри обновлены!';
            header('Location: /organizer/jury.php');
            exit();
        }
    }
}

// Handle Delete Jury
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $user = R::load('users', $id);
    if ($user->id && $user->role === 'jury') {
        R::exec('DELETE FROM juryevent WHERE user_id = ?', [$id]);
        R::trash($user);
        header('Location: /organizer/jury.php');
        exit();
    }
}

$editJury = null;
$editJuryEvents = [];
if (isset($_GET['edit'])) {
    $editJury = R::load('users', $_GET['edit']);
    if (!$editJury->id || $editJury->role !== 'jury') {
        $editJury = null;
    } else {
        $editJuryEvents = R::getCol('SELECT event_id FROM juryevent WHERE user_id = ?', [$editJury->id]);
        if (empty($editJuryEvents) && $editJury->event_id > 0) {
            $editJuryEvents = [$editJury->event_id];
        }
    }
}

$events = R::findAll('events');
$juryMembers = R::findAll('users', 'role = ?', ['jury']);
?>

<div class="container mx-auto mt-10 p-5">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Управление Жюри</h2>
        <a href="/organizer/" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Назад</a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($editJury): ?>
    <!-- Edit Jury Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h3 class="text-xl font-semibold mb-4">Редактировать жюри</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="jury_id" value="<?= $editJury->id ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">ФИО</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editJury->name) ?>" class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Пароль <span class="font-normal text-gray-400">(пустое = не менять)</span></label>
                    <input type="text" name="password" class="w-full p-2 border rounded" placeholder="Новый пароль">
                </div>
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Доступ к мероприятиям</label>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($events as $event): ?>
                        <label class="flex items-center gap-2 bg-gray-50 px-3 py-2 rounded border cursor-pointer hover:bg-blue-50 transition">
                            <input type="checkbox" name="events[]" value="<?= $event->id ?>"
                                <?= in_array($event->id, $editJuryEvents) ? 'checked' : '' ?>
                                class="w-4 h-4 text-blue-600">
                            <?= htmlspecialchars($event->title) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" name="update_jury" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-6 rounded">Сохранить</button>
                <a href="jury.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-6 rounded inline-flex items-center">Отмена</a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <!-- Add Jury Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h3 class="text-xl font-semibold mb-4">Добавить жюри</h3>
        <form method="POST">
            <div id="jury-rows">
                <div class="jury-row border rounded-lg p-4 mb-3 bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-gray-700 font-bold mb-1">ФИО</label>
                            <input type="text" name="jury_name[0]" class="w-full p-2 border rounded" required placeholder="Иванов Иван Иванович">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-bold mb-1">Пароль</label>
                            <input type="text" name="jury_password[0]" class="w-full p-2 border rounded" required placeholder="Пароль для входа">
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-1">Доступ к мероприятиям</label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($events as $event): ?>
                                <label class="flex items-center gap-1 bg-white px-3 py-1.5 rounded border cursor-pointer hover:bg-blue-50 text-sm transition">
                                    <input type="checkbox" name="jury_events[0][]" value="<?= $event->id ?>" class="w-4 h-4 text-blue-600">
                                    <?= htmlspecialchars($event->title) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex gap-3 mt-4">
                <button type="button" id="add-jury-row" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded transition">
                    + Ещё жюри
                </button>
                <button type="submit" name="add_jury" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition">
                    Добавить
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Jury List -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-3 px-4 text-left">ID</th>
                    <th class="py-3 px-4 text-left">ФИО</th>
                    <th class="py-3 px-4 text-left">Мероприятия</th>
                    <th class="py-3 px-4 text-center">Действия</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                <?php foreach ($juryMembers as $jury): ?>
                    <?php
                    $assignedEvents = R::findAll('juryevent', 'user_id = ?', [$jury->id]);
                    $eventNames = [];
                    foreach ($assignedEvents as $ae) {
                        $ev = R::load('events', $ae->event_id);
                        if ($ev->id) $eventNames[] = $ev->title;
                    }
                    if (empty($eventNames) && $jury->event_id > 0) {
                        $ev = R::load('events', $jury->event_id);
                        if ($ev->id) $eventNames[] = $ev->title;
                    }
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 px-4 font-mono text-sm"><?= $jury->id ?></td>
                        <td class="py-3 px-4 font-semibold"><?= htmlspecialchars($jury->name) ?></td>
                        <td class="py-3 px-4">
                            <?php if ($eventNames): ?>
                                <?php foreach ($eventNames as $en): ?>
                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-1 mb-1"><?= htmlspecialchars($en) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-gray-400 text-sm">Не назначен</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            <a href="?edit=<?= $jury->id ?>" class="text-blue-500 hover:text-blue-700 font-bold mr-3">Ред.</a>
                            <a href="?delete=<?= $jury->id ?>" onclick="return confirm('Удалить этого члена жюри?')" class="text-red-500 hover:text-red-700 font-bold">Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($juryMembers)): ?>
                    <tr><td colspan="4" class="py-8 text-center text-gray-400">Нет членов жюри</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let juryRowIndex = 1;
const eventsData = <?= json_encode(array_values(array_map(function($e) { return ['id' => $e->id, 'title' => $e->title]; }, $events))) ?>;

document.getElementById('add-jury-row').addEventListener('click', function() {
    const container = document.getElementById('jury-rows');
    const row = document.createElement('div');
    row.className = 'jury-row border rounded-lg p-4 mb-3 bg-gray-50 fade-in';

    let eventsHtml = '';
    eventsData.forEach(function(e) {
        eventsHtml += '<label class="flex items-center gap-1 bg-white px-3 py-1.5 rounded border cursor-pointer hover:bg-blue-50 text-sm transition">' +
            '<input type="checkbox" name="jury_events[' + juryRowIndex + '][]" value="' + e.id + '" class="w-4 h-4 text-blue-600"> ' +
            e.title + '</label>';
    });

    row.innerHTML =
        '<div class="flex justify-between items-start mb-3">' +
            '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">' +
                '<div><label class="block text-gray-700 font-bold mb-1">ФИО</label>' +
                '<input type="text" name="jury_name[' + juryRowIndex + ']" class="w-full p-2 border rounded" required placeholder="ФИО"></div>' +
                '<div><label class="block text-gray-700 font-bold mb-1">Пароль</label>' +
                '<input type="text" name="jury_password[' + juryRowIndex + ']" class="w-full p-2 border rounded" required placeholder="Пароль"></div>' +
            '</div>' +
            '<button type="button" onclick="this.closest(\'.jury-row\').remove()" class="ml-3 mt-6 text-red-400 hover:text-red-600 text-2xl font-bold leading-none">&times;</button>' +
        '</div>' +
        '<div><label class="block text-gray-700 font-bold mb-1">Доступ к мероприятиям</label>' +
        '<div class="flex flex-wrap gap-2">' + eventsHtml + '</div></div>';

    container.appendChild(row);
    juryRowIndex++;
});
</script>

<?php require '../includes/footer.php'; ?>
