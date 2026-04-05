<?php
require '../includes/auth.php';
require '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Доступ запрещен");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $task_start = $_POST['task_start'];
    $show_leaderboard = isset($_POST['show_leaderboard']) ? 1 : 0;
    $show_registration = isset($_POST['show_registration']) ? 1 : 0;

    $taskFilename = '';
    if (isset($_FILES['task']) && $_FILES['task']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../storage/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $extension = pathinfo($_FILES['task']['name'], PATHINFO_EXTENSION);
        $taskFilename = uniqid('task_') . '.' . $extension;

        if (!move_uploaded_file($_FILES['task']['tmp_name'], $uploadDir . $taskFilename)) {
            $error = 'Ошибка при загрузке файла.';
        }
    }

    if (empty($title)) {
        $error = 'Введите название хакатона.';
    }

    if (!$error) {
        $event = R::dispense('events');
        $event->title = $title;
        $event->description = trim($_POST['description'] ?? '');
        $event->task_start = $task_start;
        $event->task = $taskFilename;
        $event->show_leaderboard = $show_leaderboard;
        $event->show_registration = $show_registration;
        $event->status = 'active';
        $event->slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $event->type = '';

        $newEventId = R::store($event);

        // Критерии
        if (!empty($_POST['criteria'])) {
            $criteriaNames = array_filter(array_map('trim', explode("\n", $_POST['criteria'])));
            foreach ($criteriaNames as $name) {
                $c = R::dispense('criteria');
                $c->name = $name;
                $c->event_id = $newEventId;
                R::store($c);
            }
        }

        // Команды
        if (!empty($_POST['teams'])) {
            $teamNames = array_filter(array_map('trim', explode("\n", $_POST['teams'])));
            foreach ($teamNames as $name) {
                $t = R::dispense('teams');
                $t->name = $name;
                $t->event_id = $newEventId;
                R::store($t);
            }
        }

        header('Location: /organizer/event_detail.php?id=' . $newEventId);
        exit();
    }
}
?>

<div class="container mx-auto mt-10 p-5">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Создать Хакатон</h2>
        <a href="/organizer/" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Назад</a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8 max-w-2xl mx-auto">
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-gray-700 font-bold mb-2">Название</label>
                <input type="text" name="title" class="w-full p-2 border rounded" required>
            </div>

            <div>
                <label class="block text-gray-700 font-bold mb-2">Описание</label>
                <textarea name="description" rows="3" class="w-full p-2 border rounded" placeholder="Краткое описание мероприятия..."></textarea>
            </div>

            <div>
                <label class="block text-gray-700 font-bold mb-2">Дата и время начала заданий</label>
                <input type="datetime-local" name="task_start" class="w-full p-2 border rounded">
            </div>

            <div>
                <label class="block text-gray-700 font-bold mb-2">Файл задания (PDF/DOCX)</label>
                <input type="file" name="task" class="w-full p-2 border rounded">
            </div>

            <div class="flex flex-col gap-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="show_leaderboard" class="w-5 h-5 text-blue-600" checked>
                    <span class="text-gray-700 font-bold">Показывать результаты</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="show_registration" class="w-5 h-5 text-blue-600" checked>
                    <span class="text-gray-700 font-bold">Показывать регистрацию</span>
                </label>
            </div>

            <div>
                <label class="block text-gray-700 font-bold mb-2">Критерии оценки <span class="font-normal text-gray-400">(по одному на строку)</span></label>
                <textarea name="criteria" rows="4" class="w-full p-2 border rounded" placeholder="Инновационность&#10;Техническая реализация&#10;Дизайн и UX&#10;Презентация"></textarea>
            </div>

            <div>
                <label class="block text-gray-700 font-bold mb-2">Команды <span class="font-normal text-gray-400">(по одной на строку)</span></label>
                <textarea name="teams" rows="4" class="w-full p-2 border rounded" placeholder="AlphaCode&#10;ByteForce&#10;CodeCraft"></textarea>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded text-lg">
                    Создать
                </button>
            </div>
        </form>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
