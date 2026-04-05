<?php
include '../includes/auth.php';
include '../includes/header.php';

if (isset($_GET['logout'])) {
    // Удаляем remember-токен из БД
    if (isset($_SESSION['user_id'])) {
        $logoutUser = R::load('users', $_SESSION['user_id']);
        if ($logoutUser->id) {
            $logoutUser->remember_token = null;
            R::store($logoutUser);
        }
    }
    setcookie('remember_token', '', time() - 3600, '/');
    session_unset();
    session_destroy();
    header('Location: /organizer/');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (isset($_SESSION['user_id'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        $user = R::load('users', $_SESSION['user_id']);

        if (!password_verify($currentPassword, $user->password_hash)) {
            $passError = 'Неверный текущий пароль.';
        } elseif (strlen($newPassword) < 4) {
            $passError = 'Новый пароль должен быть не менее 4 символов.';
        } elseif ($newPassword !== $confirmPassword) {
            $passError = 'Пароли не совпадают.';
        } else {
            $user->password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
            R::store($user);
            $passSuccess = 'Пароль успешно изменён.';
        }
    }
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

        // Remember-токен на 30 дней
        $token = bin2hex(random_bytes(32));
        $user->remember_token = $token;
        R::store($user);
        setcookie('remember_token', $token, time() + 2592000, '/');

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

        <button onclick="document.getElementById('passwordModal').classList.remove('hidden')" class="mx-auto mt-4 bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded-lg w-3/4 font-semibold transition block text-center">
            Изменить пароль
        </button>
        <a href="?logout=true" class="mx-auto mt-2 bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg w-3/4 font-semibold transition block text-center">
            Выйти
        </a>
    </div>

    <div id="passwordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center <?= (isset($passError) || isset($passSuccess)) ? '' : 'hidden' ?>">
        <div class="bg-white rounded-lg p-6 w-full max-w-sm mx-4">
            <h3 class="text-lg font-semibold mb-4 text-center">Изменить пароль</h3>

            <?php if (isset($passError)): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-3 text-sm"><?= htmlspecialchars($passError) ?></div>
            <?php endif; ?>
            <?php if (isset($passSuccess)): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded mb-3 text-sm"><?= htmlspecialchars($passSuccess) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="change_password" value="1">
                <input type="password" name="current_password" placeholder="Текущий пароль" required class="w-full p-3 border rounded-lg mb-2 text-gray-700 bg-gray-100">
                <input type="password" name="new_password" placeholder="Новый пароль" required class="w-full p-3 border rounded-lg mb-2 text-gray-700 bg-gray-100">
                <input type="password" name="confirm_password" placeholder="Подтвердите пароль" required class="w-full p-3 border rounded-lg mb-3 text-gray-700 bg-gray-100">
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded-lg font-semibold transition">Сохранить</button>
            </form>
            <button onclick="document.getElementById('passwordModal').classList.add('hidden')" class="w-full mt-2 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 rounded-lg font-semibold transition">Отмена</button>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
