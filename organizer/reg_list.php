<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /organizer/');
    exit();
}

$event_id = (int)($_GET['event'] ?? 0);
$event = R::load('events', $event_id);

if (!$event->id) {
    header('Location: /organizer/events.php');
    exit();
}

// Get approved registrations for this event
$registrations = R::findAll('registration', 'event_id = ? AND status = ? ORDER BY id', [$event_id, 'approved']);

// Build flat list of participants
$rows = [];
foreach ($registrations as $reg) {
    $members = R::findAll('regmember', 'registration_id = ? ORDER BY id', [$reg->id]);
    foreach ($members as $m) {
        $rows[] = [
            'full_name'    => $m->full_name,
            'school'       => $reg->school,
            'birth_date'   => $m->birth_date,
            'team_name'    => $reg->team_name,
            'leader'       => $reg->leader_name . ', ' . $reg->leader_phone,
        ];
    }
}

// --- WORD DOWNLOAD ---
if (isset($_GET['download'])) {
    $title = $event->title;
    $html = '
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="UTF-8">
    <!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View></w:WordDocument></xml><![endif]-->
    <!--[if gte mso 9]><xml><w:Section><w:SectionPr><w:PgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:PgMar w:top="850" w:right="850" w:bottom="850" w:left="850"/></w:SectionPr></w:Section></xml><![endif]-->
    <style>
        @page WordSection1 { size: 297mm 210mm; mso-page-orientation: landscape; margin: 1.5cm; }
        div.WordSection1 { page: WordSection1; }
        body { font-family: "Times New Roman", serif; font-size: 12pt; }
        h2 { text-align: center; font-size: 14pt; margin-bottom: 4pt; }
        h3 { text-align: center; font-size: 12pt; font-weight: normal; margin-top: 0; color: #555; }
        table { border-collapse: collapse; width: 100%; margin-top: 12pt; }
        th, td { border: 1px solid #000; padding: 4pt 6pt; font-size: 11pt; vertical-align: top; }
        th { background-color: #f0f0f0; text-align: center; font-weight: bold; }
        td.center { text-align: center; }
    </style>
    </head><body><div class="WordSection1">
    <h2>Список участников</h2>
    <h3>' . htmlspecialchars($title) . '</h3>
    <table>
        <tr>
            <th style="width:30px">&#8470;</th>
            <th>Ф.И.О. участника</th>
            <th>Район/город, школа</th>
            <th style="width:90px">Дата рождения</th>
            <th>Название команды</th>
            <th>Ф.И.О. руководителя, контакты</th>
            <th style="width:70px">Подпись</th>
        </tr>';

    $n = 0;
    foreach ($rows as $r) {
        $n++;
        $html .= '<tr>
            <td class="center">' . $n . '</td>
            <td>' . htmlspecialchars($r['full_name']) . '</td>
            <td>' . htmlspecialchars($r['school']) . '</td>
            <td class="center">' . htmlspecialchars($r['birth_date']) . '</td>
            <td>' . htmlspecialchars($r['team_name']) . '</td>
            <td>' . htmlspecialchars($r['leader']) . '</td>
            <td></td>
        </tr>';
    }

    $html .= '</table></div></body></html>';

    $filename = 'participants_' . $event_id . '.doc';
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    echo $html;
    exit();
}

require '../includes/header.php';
?>

<script>document.body.classList.add('reg-list-page');</script>
<style>
    .reg-list-page .max-w-4xl { max-width: 100% !important; }
</style>
<div class="w-full mx-auto mt-6 px-4 sm:px-8">
    <nav class="text-sm text-gray-500 mb-4">
        <a href="/organizer/" class="hover:text-gray-700">Панель</a>
        <span class="mx-1">/</span>
        <a href="/organizer/event_detail.php?id=<?= $event_id ?>" class="hover:text-gray-700"><?= htmlspecialchars($event->title) ?></a>
        <span class="mx-1">/</span>
        <span class="text-gray-800 font-semibold">Лист регистраций</span>
    </nav>

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Список участников</h2>
        <div class="flex gap-2">
            <?php if (!empty($rows)): ?>
            <a href="?event=<?= $event_id ?>&download=1" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-5 rounded transition text-sm">
                Скачать Word
            </a>
            <?php endif; ?>
            <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-5 rounded transition text-sm">
                Печать
            </button>
            <a href="/organizer/event_detail.php?id=<?= $event_id ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-5 rounded transition text-sm">
                Назад
            </a>
        </div>
    </div>

    <p class="text-gray-500 text-sm mb-4"><?= htmlspecialchars($event->title) ?> &mdash; <?= count($rows) ?> участников</p>

    <?php if (empty($rows)): ?>
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-400">
            Нет одобренных заявок. Одобрите заявки в <a href="/organizer/registrations.php?event=<?= $event_id ?>" class="text-blue-500 underline">разделе регистраций</a>.
        </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
        <table class="min-w-full text-sm" id="reg-table">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="py-2 px-3 text-center w-10">&numero;</th>
                    <th class="py-2 px-3 text-left">Ф.И.О. участника</th>
                    <th class="py-2 px-3 text-left">Район/город, школа</th>
                    <th class="py-2 px-3 text-center">Дата рождения</th>
                    <th class="py-2 px-3 text-left">Название команды</th>
                    <th class="py-2 px-3 text-left">Ф.И.О. руководителя, контакты</th>
                    <th class="py-2 px-3 text-center w-20">Подпись</th>
                </tr>
            </thead>
            <tbody class="text-gray-700">
                <?php $n = 0; $prevTeam = ''; foreach ($rows as $r): $n++; ?>
                <tr class="border-b hover:bg-gray-50 <?= $r['team_name'] !== $prevTeam && $n > 1 ? 'border-t-2 border-t-gray-300' : '' ?>">
                    <td class="py-2 px-3 text-center"><?= $n ?></td>
                    <td class="py-2 px-3 font-medium"><?= htmlspecialchars($r['full_name']) ?></td>
                    <td class="py-2 px-3"><?= htmlspecialchars($r['school']) ?></td>
                    <td class="py-2 px-3 text-center"><?= htmlspecialchars($r['birth_date']) ?></td>
                    <td class="py-2 px-3 font-semibold"><?= htmlspecialchars($r['team_name']) ?></td>
                    <td class="py-2 px-3"><?= htmlspecialchars($r['leader']) ?></td>
                    <td class="py-2 px-3"></td>
                </tr>
                <?php $prevTeam = $r['team_name']; endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
@page { size: landscape; margin: 1cm; }
@media print {
    body { background: white !important; }
    .container { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
    nav, .flex.gap-2, header, .bg-blue-700, .bg-white.max-w-4xl, main > .bg-white.max-w-4xl, p.text-gray-500 { display: none !important; }
    h2 { font-size: 14pt !important; margin-bottom: 8pt; }
    table { font-size: 9pt; }
    th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<?php require '../includes/footer.php'; ?>
