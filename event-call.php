<?php
include 'includes/db.php';
include 'includes/header.php';

$id = $_GET['id'] ?? 0;
$event = R::load('events', $id);
$roomName = $event->id . "_" . $event->slug;
$teams = R::findAll('teams', 'event_id = ?', [$id]);
?>

<div class="bg-white w-2/5 mx-auto mt-6 p-6 rounded-lg shadow-lg text-center">
    <h3 class="text-xl font-semibold"> <?=$event->title?></h3>

    <div class="flex flex-col items-center">
        <select id="team-select" class="mt-2 p-3 border rounded-lg w-3/4 text-gray-700 bg-gray-100">
            <?php foreach ($teams as $team) : ?>
                <option value="<?= htmlspecialchars($team->name) ?>"> <?= htmlspecialchars($team->name) ?> </option>
            <?php endforeach; ?>
        </select>

        <input type="text" id="username" placeholder="Введите ФИО" class="mt-4 p-3 border rounded-lg w-3/4 text-gray-700 bg-gray-100">

        <button onclick="startMeeting()" class="mt-4 bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg w-3/4 font-semibold transition">
            Присоединиться к звонку
        </button>
    </div>
</div>
<script>
    const roomName = "<?= $roomName ?>"; // Теперь roomName доступен в JS

    function startMeeting() {
        const name = encodeURIComponent(document.getElementById("username").value.trim());
        const team = encodeURIComponent(document.getElementById("team-select").value);

        if (!name) {
            alert("Введите ФИО перед входом!");
            return;
        }

        // Формируем ссылку на Jitsi
        const meetingUrl = `https://meet.jit.si/${roomName}#config.disableProfile=true&config.defaultLanguage=ru&userInfo.displayName="${name} - ${team}"`;

        // Открываем ссылку в новой вкладке
        window.open(meetingUrl, "_blank");
    }
</script>

<?php include 'includes/footer.php'; ?>
