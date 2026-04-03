<?php
include 'includes/db.php';
include 'includes/header.php';
?>

<div class="bg-white max-w-2xl w-full mx-auto mt-6 p-6 rounded-lg shadow-lg text-center">
    <h3 class="text-xl font-semibold">Выберите мероприятие</h3>
    <?php
    $events = R::findAll('events');
    foreach ($events as $event) {
        if ($event->type === 'no_access') {
            echo '<a href="#" class="button bg-gray-500" disabled>' . htmlspecialchars($event->title) . '</a>';
        } else {
            echo '<a href="event.php?id=' . $event->id . '" class="button">' . htmlspecialchars($event->title) . '</a>';
        }
    }
    ?>
</div>

<?php include 'includes/footer.php'; ?>
