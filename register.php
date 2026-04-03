<?php
require 'includes/db.php';

$event_id = $_GET['event_id'] ?? 0;
$event = $event_id ? R::load('events', $event_id) : null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = (int)$_POST['event_id'];
    $event = R::load('events', $event_id);

    $school = trim($_POST['school'] ?? '');
    $team_name = trim($_POST['team_name'] ?? '');
    $leader_name = trim($_POST['leader_name'] ?? '');
    $leader_phone = trim($_POST['leader_phone'] ?? '');
    $leader_email = trim($_POST['leader_email'] ?? '');
    $member_names = $_POST['member_name'] ?? [];
    $member_dates = $_POST['member_date'] ?? [];

    if (!$event->id) {
        $error = 'Мероприятие не найдено.';
    } elseif (empty($school) || empty($team_name) || empty($leader_name) || empty($leader_phone)) {
        $error = 'Заполните все обязательные поля.';
    } else {
        $members = [];
        foreach ($member_names as $i => $mn) {
            $mn = trim($mn);
            $md = trim($member_dates[$i] ?? '');
            if (!empty($mn)) {
                $members[] = ['name' => $mn, 'date' => $md];
            }
        }

        if (empty($members)) {
            $error = 'Добавьте хотя бы одного участника.';
        }
    }

    if (!$error) {
        $reg = R::dispense('registration');
        $reg->event_id = $event_id;
        $reg->school = $school;
        $reg->team_name = $team_name;
        $reg->leader_name = $leader_name;
        $reg->leader_phone = $leader_phone;
        $reg->leader_email = $leader_email;
        $reg->status = 'pending';
        $reg->created_at = date('Y-m-d H:i:s');
        $regId = R::store($reg);

        foreach ($members as $m) {
            $rm = R::dispense('regmember');
            $rm->registration_id = $regId;
            $rm->full_name = $m['name'];
            $rm->birth_date = $m['date'];
            R::store($rm);
        }

        $success = 'Заявка успешно отправлена! Ваша команда будет рассмотрена организаторами.';
    }
}

if (!$event || !$event->id) {
    $events = R::findAll('events', 'status = ?', ['active']);
}

include 'includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-white max-w-2xl w-full mx-auto mt-6 p-6 rounded-lg shadow text-center">
    <h2 class="text-xl font-bold text-green-700 mb-2">Заявка отправлена!</h2>
    <p class="text-gray-600 text-sm"><?= htmlspecialchars($success) ?></p>
    <a href="event.php?id=<?= $event_id ?>" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition text-sm">
        Вернуться к мероприятию
    </a>
</div>

<?php elseif (!$event || !$event->id): ?>
<div class="bg-white max-w-2xl w-full mx-auto mt-6 p-6 rounded-lg shadow text-center">
    <h2 class="text-xl font-semibold mb-4">Регистрация на хакатон</h2>
    <p class="text-gray-500 text-sm mb-4">Выберите мероприятие для регистрации:</p>
    <?php if (empty($events)): ?>
        <p class="text-gray-400 text-sm">Нет доступных мероприятий</p>
    <?php else: ?>
        <?php foreach ($events as $ev): ?>
            <a href="register.php?event_id=<?= $ev->id ?>" class="button"><?= htmlspecialchars($ev->title) ?></a>
        <?php endforeach; ?>
    <?php endif; ?>
    <a href="index.php" class="block mt-4 text-gray-500 hover:text-gray-700 text-sm">Назад</a>
</div>

<?php else: ?>
<div class="bg-white max-w-2xl w-full mx-auto mt-6 p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold text-center">Регистрация команды</h2>
    <p class="text-gray-500 text-sm text-center mt-1"><?= htmlspecialchars($event->title) ?></p>

    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded-lg mt-4 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="mt-5 space-y-4">
        <input type="hidden" name="event_id" value="<?= $event->id ?>">

        <div>
            <label class="block text-gray-700 font-bold mb-1 text-sm">Мектеп / Школа <span class="text-red-500">*</span></label>
            <input type="text" name="school" required value="<?= htmlspecialchars($_POST['school'] ?? '') ?>"
                   class="w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 outline-none text-sm"
                   placeholder="Например: НИШ ФМН г. Астана">
        </div>

        <div>
            <label class="block text-gray-700 font-bold mb-1 text-sm">Топтың атауы / Название команды <span class="text-red-500">*</span></label>
            <input type="text" name="team_name" required value="<?= htmlspecialchars($_POST['team_name'] ?? '') ?>"
                   class="w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 outline-none text-sm"
                   placeholder="Например: AlphaCode">
        </div>

        <div class="border-t pt-4">
            <h3 class="font-bold text-gray-800 mb-3">Жетекші / Руководитель</h3>

            <div class="space-y-3">
                <div>
                    <label class="block text-gray-700 font-bold mb-1 text-sm">Жетекшінің Т.А.Ә. / ФИО руководителя <span class="text-red-500">*</span></label>
                    <input type="text" name="leader_name" required value="<?= htmlspecialchars($_POST['leader_name'] ?? '') ?>"
                           class="w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 outline-none text-sm"
                           placeholder="Нұрланов Ермек Қайратұлы">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 font-bold mb-1 text-sm">Телефон нөмірі <span class="text-red-500">*</span></label>
                        <input type="tel" name="leader_phone" required value="<?= htmlspecialchars($_POST['leader_phone'] ?? '') ?>"
                               class="w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 outline-none text-sm"
                               placeholder="+7 (7XX) XXX-XX-XX">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-1 text-sm">Электронды пошта</label>
                        <input type="email" name="leader_email" value="<?= htmlspecialchars($_POST['leader_email'] ?? '') ?>"
                               class="w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 outline-none text-sm"
                               placeholder="email@example.com">
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t pt-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-bold text-gray-800">Қатысушылар / Участники <span class="text-red-500">*</span></h3>
                <button type="button" id="add-member-btn"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm transition">
                    + Қосу
                </button>
            </div>

            <div id="members-container" class="space-y-3">
                <div class="member-row border border-gray-200 rounded-lg p-3">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-bold text-gray-500 member-num">1.</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-gray-600 text-xs font-semibold mb-1">Т.А.Ә. / ФИО</label>
                            <input type="text" name="member_name[]" required
                                   class="w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 outline-none text-sm"
                                   placeholder="Оспанов Данияр Бауыржанұлы">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-xs font-semibold mb-1">Туған күні / Дата рождения</label>
                            <input type="date" name="member_date[]"
                                   class="w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 outline-none text-sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">
            Жіберу / Отправить заявку
        </button>
    </form>
</div>

<script>
function renumberMembers() {
    document.querySelectorAll('.member-row').forEach(function(row, i) {
        row.querySelector('.member-num').textContent = (i + 1) + '.';
    });
}

function removeMember(btn) {
    var rows = document.querySelectorAll('.member-row');
    if (rows.length <= 1) return;
    btn.closest('.member-row').remove();
    renumberMembers();
}

document.getElementById('add-member-btn').addEventListener('click', function() {
    var container = document.getElementById('members-container');
    var count = container.querySelectorAll('.member-row').length + 1;

    var row = document.createElement('div');
    row.className = 'member-row border border-gray-200 rounded-lg p-3 fade-in';
    row.innerHTML =
        '<div class="flex items-center justify-between mb-2">' +
            '<span class="text-sm font-bold text-gray-500 member-num">' + count + '.</span>' +
            '<button type="button" onclick="removeMember(this)" class="text-red-400 hover:text-red-600 text-sm font-bold">&times; Удалить</button>' +
        '</div>' +
        '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">' +
            '<div><label class="block text-gray-600 text-xs font-semibold mb-1">Т.А.Ә. / ФИО</label>' +
            '<input type="text" name="member_name[]" required class="w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 outline-none text-sm" placeholder="ФИО участника"></div>' +
            '<div><label class="block text-gray-600 text-xs font-semibold mb-1">Туған күні / Дата рождения</label>' +
            '<input type="date" name="member_date[]" class="w-full p-2 border border-gray-300 rounded-lg focus:border-blue-500 outline-none text-sm"></div>' +
        '</div>';

    container.appendChild(row);
    row.querySelector('input[type="text"]').focus();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
