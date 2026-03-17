<?php
include 'includes/db.php';
include 'includes/header.php';

$code = trim($_GET['code'] ?? '');
$certificate = null;
$searched = false;

if ($code !== '') {
    $searched = true;
    $certificate = R::findOne('certificates', 'code = ?', [$code]);
}
?>

<div class="container mx-auto mt-10 p-5 max-w-2xl">
    <h2 class="text-3xl font-bold text-gray-800 text-center mb-6">Проверка документа</h2>

    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <form method="GET" class="flex gap-2">
            <input type="text" name="code" value="<?= htmlspecialchars($code) ?>" placeholder="Введите код (например CERT-A1B2C3)" class="flex-1 p-3 border rounded-lg text-gray-700 bg-gray-100" required>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">Проверить</button>
        </form>
    </div>

    <?php if ($searched && $certificate): ?>
        <?php
        $typeLabel = $certificate->type === 'team_user' ? 'Сертификат' : 'Диплом';
        $typeBadge = $certificate->type === 'team_user' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800';

        $name = '';
        $role = '';
        $mentorName = '';
        $eventTitle = '';

        if ($certificate->type === 'team_user') {
            $teamUser = R::findOne('teamuser', 'id = ?', [$certificate->team_user_id]);
            if ($teamUser) {
                $name = $teamUser->full_name;
                $role = $teamUser->role === 'mentor' ? 'Ментор' : 'Участник';
                $team = R::findOne('teams', 'id = ?', [$teamUser->team_id]);
                if ($team) {
                    $event = R::findOne('events', 'id = ?', [$team->event_id]);
                    $eventTitle = $event ? $event->title : '';
                    $mentor = R::findOne('teamuser', 'team_id = ? AND role = ?', [$team->id, 'mentor']);
                    $mentorName = $mentor ? $mentor->full_name : '';
                }
            }
        } else {
            $team = R::findOne('teams', 'id = ?', [$certificate->team_id]);
            if ($team) {
                $name = $team->name;
                $event = R::findOne('events', 'id = ?', [$team->event_id]);
                $eventTitle = $event ? $event->title : '';
                $mentor = R::findOne('teamuser', 'team_id = ? AND role = ?', [$team->id, 'mentor']);
                $mentorName = $mentor ? $mentor->full_name : '';
            }
        }
        ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex items-center gap-3 mb-4">
                <span class="px-3 py-1 rounded text-sm font-bold <?= $typeBadge ?>"><?= $typeLabel ?></span>
                <span class="text-gray-400 text-sm">Код: <?= htmlspecialchars($certificate->code) ?></span>
            </div>

            <div class="space-y-3">
                <div>
                    <span class="text-gray-500 text-sm"><?= $certificate->type === 'team_user' ? 'ФИО участника' : 'Команда' ?>:</span>
                    <p class="font-bold text-lg text-gray-800"><?= htmlspecialchars($name) ?></p>
                </div>

                <?php if ($certificate->type === 'team_user' && $role): ?>
                <div>
                    <span class="text-gray-500 text-sm">Роль:</span>
                    <p class="font-semibold text-gray-700"><?= htmlspecialchars($role) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($eventTitle): ?>
                <div>
                    <span class="text-gray-500 text-sm">Мероприятие:</span>
                    <p class="font-semibold text-gray-700"><?= htmlspecialchars($eventTitle) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($mentorName): ?>
                <div>
                    <span class="text-gray-500 text-sm">Руководитель:</span>
                    <p class="font-semibold text-gray-700"><?= htmlspecialchars($mentorName) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($certificate->option_text): ?>
                <div>
                    <span class="text-gray-500 text-sm">Примечание:</span>
                    <p class="font-semibold text-gray-700"><?= htmlspecialchars($certificate->option_text) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-6 pt-4 border-t">
                <a href="/certificate.php?id=<?= $certificate->id ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition inline-block">Скачать</a>
            </div>
        </div>

    <?php elseif ($searched): ?>
        <div class="bg-red-50 text-red-700 p-6 rounded-lg shadow-md text-center">
            <p class="text-lg font-semibold">Документ не найден</p>
            <p class="text-sm mt-2">Проверьте правильность введённого кода</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
