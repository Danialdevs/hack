<?php
session_start();
include '../includes/db.php';
include '../includes/header.php';

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: /organizer/');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'];
    $password = $_POST['password'];
    $user = R::load('users', $userId);

    if ($user && password_verify($password, $user->password_hash)) {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['user_event_id'] = $user->event_id ?? 0;
        header('Location: /organizer');
        exit();
    } else {
        $error = 'Неверный ID пользователя или пароль.';
    }
}

$users = R::findAll('users');
?>

<?php if (!isset($_SESSION['user_id'])): ?>
    <div class="bg-white w-full max-w-md mx-auto mt-6 p-6 rounded-lg shadow-lg text-center">
        <h3 class="text-xl font-semibold">Авторизация</h3>
        <?php if (isset($error)): ?>
            <div class="text-red-500 mt-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <select name="user_id" class="mt-3 p-3 border rounded-lg w-3/4 text-gray-700 bg-gray-100">
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user->id ?>"><?= htmlspecialchars($user->name) ?> (<?= $user->role ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="password" name="password" placeholder="пароль" class="mt-2 p-3 border rounded-lg w-3/4 text-gray-700 bg-gray-100">
            <button type="submit" class="button w-3/4 mx-auto">вход</button>
        </form>
    </div>

<?php else: ?>
    <div class="bg-white w-full max-w-md mx-auto mt-6 p-6 rounded-lg shadow-lg text-center">
        <h3 class="text-xl font-semibold">
            Здравствуйте, <?= htmlspecialchars($_SESSION["user_name"]) ?>
        </h3>

        <?php if ($_SESSION["user_role"] == "admin"): ?>
            <a href="/organizer/events.php" class="button bg-blue-500">Хакатоны</a>
            <a href="/organizer/jury.php" class="button bg-green-500">Управление Жюри</a>
            <a href="/organizer/registrations.php" class="button" style="background-color:#8b5cf6">Заявки на регистрацию</a>
            <a href="/organizer/certificates.php" class="button" style="background-color:#f59e0b">Сертификаты</a>
        <?php endif; ?>

        <?php
        if ($_SESSION["user_role"] == "jury") {
            $juryEventIds = R::getCol('SELECT event_id FROM juryevent WHERE user_id = ?', [$_SESSION['user_id']]);
            if (empty($juryEventIds) && isset($_SESSION['user_event_id']) && $_SESSION['user_event_id'] > 0) {
                $juryEventIds = [$_SESSION['user_event_id']];
            }
            if (!empty($juryEventIds)) {
                $placeholders = implode(',', array_fill(0, count($juryEventIds), '?'));
                $events = R::findAll('events', "id IN ($placeholders)", $juryEventIds);
            } else {
                $events = [];
            }
        } else {
            $events = R::findAll('events');
        }
        foreach ($events as $event) {
            echo '<a href="/organizer/event.php?id=' . $event->id . '" class="button">' . htmlspecialchars($event->title) . '</a>';
        }
        ?>

        <a href="?logout=true" class="mx-auto mt-4 bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg w-3/4 font-semibold transition block text-center">
            Выйти
        </a>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
