<?php
require __DIR__ . '/includes/db.php';

R::nuke(); // Clear all tables

// Create admin user
$admin = R::dispense('users');
$admin->name = 'Администратор';
$admin->password_hash = password_hash('admin', PASSWORD_DEFAULT);
$admin->role = 'admin';
$admin->event_id = 0;
R::store($admin);

// Create events
$event1 = R::dispense('events');
$event1->title = 'Хакатон "Цифровой Казахстан"';
$event1->slug = 'digital-kz';
$event1->status = 'active';
$event1->show_leaderboard = 1;
$event1->task_start = '2026-03-20T10:00';
$event1->task = '';
$event1->type = '';
R::store($event1);

$event2 = R::dispense('events');
$event2->title = 'Хакатон "AI Challenge 2026"';
$event2->slug = 'ai-challenge-2026';
$event2->status = 'active';
$event2->show_leaderboard = 1;
$event2->task_start = '2026-04-15T09:00';
$event2->task = '';
$event2->type = '';
R::store($event2);

// Create criteria for event 1
$criteriaNames = ['Инновационность', 'Техническая реализация', 'Дизайн и UX', 'Презентация', 'Практическая ценность'];
$criteriaList = [];
foreach ($criteriaNames as $name) {
    $c = R::dispense('criteria');
    $c->name = $name;
    $c->event_id = $event1->id;
    R::store($c);
    $criteriaList[] = $c;
}

// Create criteria for event 2
$criteriaNames2 = ['AI модель', 'Качество данных', 'Масштабируемость', 'Презентация'];
foreach ($criteriaNames2 as $name) {
    $c = R::dispense('criteria');
    $c->name = $name;
    $c->event_id = $event2->id;
    R::store($c);
}

// Create teams for event 1
$teamNames = ['AlphaCode', 'ByteForce', 'CodeCraft', 'DataDriven', 'EcoTech'];
$teams = [];
foreach ($teamNames as $name) {
    $t = R::dispense('teams');
    $t->name = $name;
    $t->event_id = $event1->id;
    R::store($t);
    $teams[] = $t;
}

// Create teams for event 2
$teamNames2 = ['NeuralNet', 'DeepMind KZ', 'TensorFlow Team'];
foreach ($teamNames2 as $name) {
    $t = R::dispense('teams');
    $t->name = $name;
    $t->event_id = $event2->id;
    R::store($t);
}

// Create team members
$members = [
    ['Асанов Данияр', 'mentor'], ['Болатов Нурсултан', 'participant'], ['Сериков Адиль', 'participant'],
    ['Касымова Айгерим', 'mentor'], ['Тулепов Ермек', 'participant'], ['Жумабекова Дана', 'participant'],
    ['Нурланов Тимур', 'mentor'], ['Базарбаев Алмас', 'participant'], ['Искакова Мадина', 'participant'],
    ['Кенесов Бауржан', 'mentor'], ['Оразбек Арман', 'participant'], ['Токтарова Гульнара', 'participant'],
    ['Муратов Ринат', 'mentor'], ['Сатыбалдиев Олжас', 'participant'], ['Аманова Жанна', 'participant'],
];
for ($i = 0; $i < count($teams); $i++) {
    for ($j = 0; $j < 3; $j++) {
        $m = R::dispense('teamuser');
        $m->team_id = $teams[$i]->id;
        $m->full_name = $members[$i * 3 + $j][0];
        $m->role = $members[$i * 3 + $j][1];
        $m->event_id = $event1->id;
        R::store($m);
    }
}

// Create jury members
$juryNames = ['Сагинбаев Марат', 'Ковалева Елена', 'Ахметов Рустам'];
foreach ($juryNames as $idx => $name) {
    $j = R::dispense('users');
    $j->name = $name;
    $j->password_hash = password_hash('jury' . ($idx + 1), PASSWORD_DEFAULT);
    $j->role = 'jury';
    $j->event_id = $event1->id;
    $juryId = R::store($j);

    // Assign to event via juryevent table
    $je = R::dispense('juryevent');
    $je->user_id = $juryId;
    $je->event_id = $event1->id;
    R::store($je);
}

// Add some scores
$juryUsers = R::findAll('users', 'role = ?', ['jury']);
foreach ($juryUsers as $jury) {
    foreach ($teams as $team) {
        foreach ($criteriaList as $criterion) {
            $s = R::dispense('scores');
            $s->team_id = $team->id;
            $s->criteria_id = $criterion->id;
            $s->jury_id = $jury->id;
            $s->score = rand(5, 10);
            R::store($s);
        }
    }
}

echo "Seed complete!\n";
echo "Admin: ID=1, password=admin\n";
echo "Jury: Сагинбаев Марат (jury1), Ковалева Елена (jury2), Ахметов Рустам (jury3)\n";
