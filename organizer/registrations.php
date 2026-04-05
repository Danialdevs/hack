<?php
require '../includes/auth.php';
require '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /organizer/');
    exit();
}

$success = '';

// Approve registration -> create team + members
if (isset($_GET['approve'])) {
    $regId = (int)$_GET['approve'];
    $reg = R::load('registration', $regId);

    if ($reg->id && $reg->status === 'pending') {
        // Create team
        $team = R::dispense('teams');
        $team->name = $reg->team_name;
        $team->event_id = $reg->event_id;
        $teamId = R::store($team);

        // Add leader as mentor
        $leader = R::dispense('teamuser');
        $leader->team_id = $teamId;
        $leader->full_name = $reg->leader_name;
        $leader->role = 'mentor';
        $leader->event_id = $reg->event_id;
        R::store($leader);

        // Add members
        $members = R::findAll('regmember', 'registration_id = ?', [$regId]);
        foreach ($members as $m) {
            $tu = R::dispense('teamuser');
            $tu->team_id = $teamId;
            $tu->full_name = $m->full_name;
            $tu->role = 'participant';
            $tu->event_id = $reg->event_id;
            R::store($tu);
        }

        $reg->status = 'approved';
        R::store($reg);
        $success = 'Заявка одобрена! Команда "' . htmlspecialchars($reg->team_name) . '" создана.';
    }
}

// Reject registration
if (isset($_GET['reject'])) {
    $regId = (int)$_GET['reject'];
    $reg = R::load('registration', $regId);
    if ($reg->id && $reg->status === 'pending') {
        $reg->status = 'rejected';
        R::store($reg);
        $success = 'Заявка отклонена.';
    }
}

// Delete registration
if (isset($_GET['delete'])) {
    $regId = (int)$_GET['delete'];
    $reg = R::load('registration', $regId);
    if ($reg->id) {
        R::exec('DELETE FROM regmember WHERE registration_id = ?', [$regId]);
        R::trash($reg);
        header('Location: /organizer/registrations.php');
        exit();
    }
}

$filterEvent = $_GET['event'] ?? '';
$events = R::findAll('events');

if ($filterEvent) {
    $registrations = R::findAll('registration', 'event_id = ? ORDER BY created_at DESC', [$filterEvent]);
} else {
    $registrations = R::findAll('registration', 'ORDER BY created_at DESC');
}
?>

<div class="container mx-auto mt-10 p-5">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Заявки на регистрацию</h2>
        <a href="/organizer/" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Назад</a>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= $success ?></div>
    <?php endif; ?>

    <!-- Filter by event -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-6 flex items-center gap-4 flex-wrap">
        <span class="font-bold text-gray-700">Фильтр:</span>
        <a href="registrations.php" class="px-3 py-1 rounded text-sm font-semibold <?= !$filterEvent ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition">
            Все
        </a>
        <?php foreach ($events as $ev): ?>
            <a href="registrations.php?event=<?= $ev->id ?>" class="px-3 py-1 rounded text-sm font-semibold <?= $filterEvent == $ev->id ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition">
                <?= htmlspecialchars($ev->title) ?>
            </a>
        <?php endforeach; ?>

        <span class="ml-auto text-gray-400 text-sm">Всего: <?= count($registrations) ?></span>
    </div>

    <?php if (empty($registrations)): ?>
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-400">Заявок пока нет</div>
    <?php else: ?>

    <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-2 px-3 text-left">№</th>
                    <th class="py-2 px-3 text-left">Команда</th>
                    <?php if (!$filterEvent): ?>
                        <th class="py-2 px-3 text-left">Хакатон</th>
                    <?php endif; ?>
                    <th class="py-2 px-3 text-left">Школа</th>
                    <th class="py-2 px-3 text-left">Руководитель</th>
                    <th class="py-2 px-3 text-center">Уч.</th>
                    <th class="py-2 px-3 text-center">Статус</th>
                    <th class="py-2 px-3 text-center">Дата</th>
                    <th class="py-2 px-3 text-center">Действия</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                <?php $ri = 0; foreach ($registrations as $reg): $ri++;
                    $regEvent = R::load('events', $reg->event_id);
                    $members = R::findAll('regmember', 'registration_id = ?', [$reg->id]);
                    $statusColors = ['pending' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'];
                    $statusLabels = ['pending' => 'Ожидает', 'approved' => 'Одобрена', 'rejected' => 'Отклонена'];
                    $sc = $statusColors[$reg->status] ?? 'bg-gray-100 text-gray-800';
                    $sl = $statusLabels[$reg->status] ?? $reg->status;
                ?>
                <tr class="border-b hover:bg-gray-50 cursor-pointer" onclick="document.getElementById('detail-<?= $reg->id ?>').classList.toggle('hidden')">
                    <td class="py-2 px-3"><?= $ri ?></td>
                    <td class="py-2 px-3 font-semibold"><?= htmlspecialchars($reg->team_name) ?></td>
                    <?php if (!$filterEvent): ?>
                        <td class="py-2 px-3 text-blue-600"><?= htmlspecialchars($regEvent->title ?? '—') ?></td>
                    <?php endif; ?>
                    <td class="py-2 px-3"><?= htmlspecialchars($reg->school) ?></td>
                    <td class="py-2 px-3"><?= htmlspecialchars($reg->leader_name) ?></td>
                    <td class="py-2 px-3 text-center"><?= count($members) ?></td>
                    <td class="py-2 px-3 text-center"><span class="px-2 py-0.5 rounded text-xs font-bold <?= $sc ?>"><?= $sl ?></span></td>
                    <td class="py-2 px-3 text-center text-xs text-gray-400 whitespace-nowrap"><?= $reg->created_at ?></td>
                    <td class="py-2 px-3 text-center whitespace-nowrap">
                        <?php if ($reg->status === 'pending'): ?>
                            <a href="?approve=<?= $reg->id ?><?= $filterEvent ? '&event=' . $filterEvent : '' ?>" onclick="event.stopPropagation(); return confirm('Одобрить заявку и создать команду?')" class="text-green-600 hover:text-green-800 font-bold mr-1">Одобрить</a>
                            <a href="?reject=<?= $reg->id ?><?= $filterEvent ? '&event=' . $filterEvent : '' ?>" onclick="event.stopPropagation(); return confirm('Отклонить заявку?')" class="text-red-500 hover:text-red-700 font-bold mr-1">Откл.</a>
                        <?php endif; ?>
                        <a href="?delete=<?= $reg->id ?><?= $filterEvent ? '&event=' . $filterEvent : '' ?>" onclick="event.stopPropagation(); return confirm('Удалить заявку навсегда?')" class="text-gray-400 hover:text-gray-600 font-bold">Удалить</a>
                    </td>
                </tr>
                <!-- Expandable detail row -->
                <tr id="detail-<?= $reg->id ?>" class="hidden">
                    <td colspan="<?= $filterEvent ? '8' : '9' ?>" class="bg-gray-50 px-4 py-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500 mb-1">Руководитель</p>
                                <p class="font-semibold"><?= htmlspecialchars($reg->leader_name) ?></p>
                                <p class="text-gray-600"><?= htmlspecialchars($reg->leader_phone) ?></p>
                                <?php if ($reg->leader_email): ?>
                                    <p class="text-gray-600"><?= htmlspecialchars($reg->leader_email) ?></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-gray-500 mb-1">Участники (<?= count($members) ?>)</p>
                                <?php $mi = 0; foreach ($members as $m): $mi++; ?>
                                    <p class="py-0.5"><?= $mi ?>. <span class="font-semibold"><?= htmlspecialchars($m->full_name) ?></span>
                                    <?php if ($m->birth_date): ?><span class="text-gray-400 text-xs ml-1"><?= htmlspecialchars($m->birth_date) ?></span><?php endif; ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Registration Links -->
    <div class="bg-white rounded-lg shadow-md p-5 mt-8">
        <h3 class="font-bold text-gray-800 mb-3">Ссылки для регистрации</h3>
        <div class="space-y-2">
            <?php foreach ($events as $ev): ?>
                <div class="flex items-center gap-3 bg-gray-50 rounded px-4 py-2">
                    <span class="font-semibold text-sm"><?= htmlspecialchars($ev->title) ?>:</span>
                    <code class="text-blue-600 text-sm bg-blue-50 px-2 py-1 rounded flex-1">/register.php?event_id=<?= $ev->id ?></code>
                    <button onclick="copyLink(this, '/register.php?event_id=<?= $ev->id ?>')" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded transition">
                        Копировать
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function copyLink(btn, path) {
    var url = window.location.origin + path;
    navigator.clipboard.writeText(url).then(function() {
        btn.textContent = 'Скопировано!';
        btn.className = btn.className.replace('bg-blue-600 hover:bg-blue-700', 'bg-green-600 hover:bg-green-700');
        setTimeout(function() {
            btn.textContent = 'Копировать';
            btn.className = btn.className.replace('bg-green-600 hover:bg-green-700', 'bg-blue-600 hover:bg-blue-700');
        }, 2000);
    });
}
</script>

<?php require '../includes/footer.php'; ?>
