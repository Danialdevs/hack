<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require '../includes/db.php';
require '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /organizer/');
    exit();
}

$success = '';
$error = '';

// --- SAVE TEMPLATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $eventId = (int)$_POST['event_id'];
    $docType = $_POST['doc_type'];

    $template = R::findOne('certificatetemplate', 'event_id = ? AND doc_type = ?', [$eventId, $docType]);
    if (!$template) {
        $template = R::dispense('certificatetemplate');
        $template->event_id = $eventId;
        $template->doc_type = $docType;
    }

    if (isset($_FILES['background']) && $_FILES['background']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../storage/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $extension = strtolower(pathinfo($_FILES['background']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $error = 'Допустимые форматы фона: JPG, PNG';
        } else {
            $bgFilename = uniqid('bg_') . '.' . $extension;
            if (move_uploaded_file($_FILES['background']['tmp_name'], $uploadDir . $bgFilename)) {
                $template->background = $bgFilename;
            } else {
                $error = 'Ошибка при загрузке файла фона.';
            }
        }
    }

    if (!$error) {
        $elements = json_decode($_POST['elements_json'] ?? '[]', true);
        if (!is_array($elements)) $elements = [];
        $template->elements = json_encode($elements, JSON_UNESCAPED_UNICODE);
        R::store($template);
        $success = 'Шаблон сохранён.';
    }
}

// --- GENERATE CERTIFICATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_certificates'])) {
    $eventId = (int)$_POST['event_id'];
    $optionText = trim($_POST['option_text'] ?? '');
    $template = R::findOne('certificatetemplate', 'event_id = ? AND doc_type = ?', [$eventId, 'certificate']);

    if (!$template) {
        $error = 'Сначала создайте шаблон сертификата для этого мероприятия.';
    } else {
        $teamUsers = R::findAll('teamuser', 'event_id = ?', [$eventId]);
        $created = 0;
        $updated = 0;
        foreach ($teamUsers as $tu) {
            $exists = R::findOne('certificates', 'team_user_id = ? AND type = ?', [$tu->id, 'team_user']);
            if ($exists) {
                $exists->template_id = $template->id;
                $exists->option_text = $optionText;
                R::store($exists);
                $updated++;
            } else {
                $cert = R::dispense('certificates');
                $cert->template_id = $template->id;
                $cert->team_id = $tu->team_id;
                $cert->team_user_id = $tu->id;
                $cert->type = 'team_user';
                $cert->option_text = $optionText;
                $cert->code = 'CERT-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
                R::store($cert);
                $created++;
            }
        }
        $success = "Сгенерировано сертификатов: $created, обновлено: $updated";
    }
}

// --- GENERATE DIPLOMAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_diplomas'])) {
    $eventId = (int)$_POST['event_id'];
    $optionText = trim($_POST['option_text_diploma'] ?? '');
    $template = R::findOne('certificatetemplate', 'event_id = ? AND doc_type = ?', [$eventId, 'diploma']);

    if (!$template) {
        $error = 'Сначала создайте шаблон диплома для этого мероприятия.';
    } else {
        $teams = R::findAll('teams', 'event_id = ?', [$eventId]);
        $created = 0;
        $updated = 0;
        foreach ($teams as $t) {
            $exists = R::findOne('certificates', 'team_id = ? AND type = ?', [$t->id, 'team']);
            if ($exists) {
                $exists->template_id = $template->id;
                $exists->option_text = $optionText;
                R::store($exists);
                $updated++;
            } else {
                $cert = R::dispense('certificates');
                $cert->template_id = $template->id;
                $cert->team_id = $t->id;
                $cert->team_user_id = 0;
                $cert->type = 'team';
                $cert->option_text = $optionText;
                $cert->code = 'DIPL-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
                R::store($cert);
                $created++;
            }
        }
        $success = "Сгенерировано дипломов: $created, обновлено: $updated";
    }
}

// --- DELETE CERTIFICATE ---
if (isset($_GET['delete_cert'])) {
    $certId = (int)$_GET['delete_cert'];
    $cert = R::load('certificates', $certId);
    if ($cert->id) {
        R::trash($cert);
        $success = 'Документ удалён.';
    }
}

// --- FILTER ---
$filterEvent = $_GET['event'] ?? '';
$events = R::findAll('events');

$templateCert = null;
$templateDiploma = null;
if ($filterEvent) {
    $templateCert = R::findOne('certificatetemplate', 'event_id = ? AND doc_type = ?', [$filterEvent, 'certificate']);
    $templateDiploma = R::findOne('certificatetemplate', 'event_id = ? AND doc_type = ?', [$filterEvent, 'diploma']);
}

if ($filterEvent) {
    $certificates = R::getAll('
        SELECT c.* FROM certificates c
        LEFT JOIN teams t ON c.team_id = t.id
        WHERE t.event_id = ?
        ORDER BY c.id DESC
    ', [$filterEvent]);
} else {
    $certificates = R::getAll('SELECT * FROM certificates ORDER BY id DESC');
}

// Prepare template data as JSON for JS editor
$defaultCert = [
    ['text' => '{full_name}', 'font' => 'satpaev2/Montserrat-SemiBold.ttf', 'size' => 48, 'color' => '#000000', 'center_x' => 800, 'center_y' => 500],
    ['text' => '{mentor_name}', 'font' => 'satpaev2/Montserrat-SemiBold.ttf', 'size' => 32, 'color' => '#333333', 'center_x' => 800, 'center_y' => 600],
    ['text' => '{option_text}', 'font' => 'satpaev2/Montserrat-SemiBold.ttf', 'size' => 28, 'color' => '#333333', 'center_x' => 800, 'center_y' => 700],
];
$defaultDipl = [
    ['text' => '{team_name}', 'font' => 'satpaev2/Montserrat-SemiBold.ttf', 'size' => 48, 'color' => '#000000', 'center_x' => 800, 'center_y' => 500],
    ['text' => '{mentor_name}', 'font' => 'satpaev2/Montserrat-SemiBold.ttf', 'size' => 32, 'color' => '#333333', 'center_x' => 800, 'center_y' => 600],
    ['text' => '{option_text}', 'font' => 'satpaev2/Montserrat-SemiBold.ttf', 'size' => 28, 'color' => '#333333', 'center_x' => 800, 'center_y' => 700],
];

$certElementsJson = $templateCert && $templateCert->elements ? $templateCert->elements : json_encode($defaultCert);
$diplElementsJson = $templateDiploma && $templateDiploma->elements ? $templateDiploma->elements : json_encode($defaultDipl);
$certBg = $templateCert && $templateCert->background ? $templateCert->background : '';
$diplBg = $templateDiploma && $templateDiploma->background ? $templateDiploma->background : '';

$fonts = ['satpaev2/Montserrat-SemiBold.ttf'];
$fontDir = '../storage/';
if (is_dir($fontDir)) {
    foreach (glob($fontDir . '{,*/,*/*/}*.ttf', GLOB_BRACE) as $f) {
        $rel = str_replace('../storage/', '', $f);
        if (!in_array($rel, $fonts)) $fonts[] = $rel;
    }
}
?>

<style>
    .editor-canvas-wrap {
        position: relative;
        display: inline-block;
        background: repeating-conic-gradient(#e5e7eb 0% 25%, white 0% 50%) 50% / 20px 20px;
        border: 2px solid #d1d5db;
        border-radius: 8px;
        overflow: hidden;
        cursor: crosshair;
    }
    .editor-canvas-wrap img.bg-img {
        display: block;
        max-width: 100%;
        height: auto;
        pointer-events: none;
        user-select: none;
    }
    .canvas-element {
        position: absolute;
        cursor: move;
        user-select: none;
        white-space: nowrap;
        border: 2px solid transparent;
        padding: 2px 6px;
        border-radius: 4px;
        transition: border-color 0.15s, box-shadow 0.15s;
        line-height: 1.2;
    }
    .canvas-element:hover {
        border-color: rgba(59, 130, 246, 0.5);
    }
    .canvas-element.selected {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
        z-index: 10;
    }
    .canvas-element.dragging {
        opacity: 0.85;
        z-index: 20;
    }
    .qr-placeholder {
        position: absolute;
        cursor: move;
        border: 2px dashed #8b5cf6;
        background: rgba(139, 92, 246, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #7c3aed;
        font-weight: 600;
        border-radius: 4px;
        user-select: none;
    }
    .qr-placeholder:hover, .qr-placeholder.selected {
        border-color: #7c3aed;
        background: rgba(139, 92, 246, 0.15);
        box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3);
    }
    .el-item {
        border: 2px solid transparent;
        transition: border-color 0.15s, background 0.15s;
    }
    .el-item.active {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .el-item .el-drag-handle {
        cursor: grab;
        color: #9ca3af;
    }
    .el-item .el-drag-handle:hover {
        color: #6b7280;
    }
    .tab-btn {
        padding: 8px 20px;
        font-weight: 600;
        border-radius: 8px 8px 0 0;
        border: 2px solid #e5e7eb;
        border-bottom: none;
        background: #f3f4f6;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.15s;
    }
    .tab-btn.active {
        background: white;
        color: #1f2937;
        border-color: #d1d5db;
        position: relative;
    }
    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: white;
    }
    .no-bg-placeholder {
        width: 100%;
        min-height: 400px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        gap: 12px;
    }
    .coord-badge {
        font-size: 10px;
        background: rgba(0,0,0,0.6);
        color: white;
        padding: 1px 6px;
        border-radius: 4px;
        position: absolute;
        bottom: -18px;
        left: 50%;
        transform: translateX(-50%);
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.15s;
    }
    .canvas-element:hover .coord-badge,
    .canvas-element.selected .coord-badge,
    .canvas-element.dragging .coord-badge,
    .qr-placeholder:hover .coord-badge,
    .qr-placeholder.selected .coord-badge {
        opacity: 1;
    }
</style>

<div class="container mx-auto mt-10 p-5" style="max-width:1400px">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Сертификаты и дипломы</h2>
        <a href="/organizer/" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Назад</a>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-6 flex items-center gap-4 flex-wrap">
        <span class="font-bold text-gray-700">Мероприятие:</span>
        <a href="certificates.php" class="px-3 py-1 rounded text-sm font-semibold <?= !$filterEvent ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition">Все</a>
        <?php foreach ($events as $ev): ?>
            <a href="certificates.php?event=<?= $ev->id ?>" class="px-3 py-1 rounded text-sm font-semibold <?= $filterEvent == $ev->id ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> transition">
                <?= htmlspecialchars($ev->title) ?>
            </a>
        <?php endforeach; ?>
        <span class="ml-auto text-gray-400 text-sm">Документов: <?= count($certificates) ?></span>
    </div>

    <?php if ($filterEvent): ?>

    <!-- Tabs -->
    <div class="flex gap-1 mb-0">
        <button class="tab-btn active" onclick="switchTab('certificate')" id="tab-certificate">Сертификат</button>
        <button class="tab-btn" onclick="switchTab('diploma')" id="tab-diploma">Диплом</button>
    </div>

    <!-- Editor -->
    <div class="bg-white rounded-lg rounded-tl-none shadow-md border-2 border-gray-300 mb-8">

        <!-- Certificate editor -->
        <div id="editor-certificate" class="editor-pane">
            <div class="flex flex-col xl:flex-row gap-0">
                <!-- Canvas -->
                <div class="flex-1 p-5 border-b xl:border-b-0 xl:border-r border-gray-200">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-bold text-gray-600">Предпросмотр</span>
                        <span class="text-xs text-gray-400" id="cert-canvas-size"></span>
                    </div>
                    <div class="editor-canvas-wrap" id="cert-canvas">
                        <?php if ($certBg): ?>
                            <img src="/storage/<?= htmlspecialchars($certBg) ?>" class="bg-img" id="cert-bg-img" onload="editorInit('certificate')">
                        <?php else: ?>
                            <div class="no-bg-placeholder" id="cert-no-bg">
                                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg>
                                <span>Загрузите фон для сертификата</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Background upload -->
                    <form method="POST" enctype="multipart/form-data" class="mt-3 flex gap-2 items-end" id="cert-bg-form">
                        <input type="hidden" name="save_template" value="1">
                        <input type="hidden" name="event_id" value="<?= $filterEvent ?>">
                        <input type="hidden" name="doc_type" value="certificate">
                        <input type="hidden" name="elements_json" id="cert-bg-elements-json">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Фон (JPG/PNG)</label>
                            <input type="file" name="background" accept=".jpg,.jpeg,.png" class="w-full text-sm p-1.5 border rounded" onchange="this.form.querySelector('#cert-bg-elements-json').value=getElementsJSON('certificate');this.form.submit()">
                        </div>
                    </form>
                </div>

                <!-- Properties panel -->
                <div class="w-full xl:w-[380px] flex-shrink-0 p-5 flex flex-col" style="max-height:700px">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-bold text-gray-600">Элементы</span>
                        <div class="flex gap-1">
                            <button onclick="addTextElement('certificate')" class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded transition">+ Текст</button>
                            <button onclick="toggleQR('certificate')" class="text-xs bg-purple-600 hover:bg-purple-700 text-white px-2.5 py-1 rounded transition" id="cert-qr-toggle">+ QR</button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto space-y-2 mb-3 pr-1" id="cert-elements-list">
                        <!-- filled by JS -->
                    </div>

                    <!-- Save -->
                    <form method="POST" enctype="multipart/form-data" id="cert-save-form">
                        <input type="hidden" name="save_template" value="1">
                        <input type="hidden" name="event_id" value="<?= $filterEvent ?>">
                        <input type="hidden" name="doc_type" value="certificate">
                        <input type="hidden" name="elements_json" id="cert-elements-json">
                        <button type="submit" onclick="document.getElementById('cert-elements-json').value=getElementsJSON('certificate')" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded transition text-sm">
                            Сохранить шаблон
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Diploma editor -->
        <div id="editor-diploma" class="editor-pane" style="display:none">
            <div class="flex flex-col xl:flex-row gap-0">
                <div class="flex-1 p-5 border-b xl:border-b-0 xl:border-r border-gray-200">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-bold text-gray-600">Предпросмотр</span>
                        <span class="text-xs text-gray-400" id="dipl-canvas-size"></span>
                    </div>
                    <div class="editor-canvas-wrap" id="dipl-canvas">
                        <?php if ($diplBg): ?>
                            <img src="/storage/<?= htmlspecialchars($diplBg) ?>" class="bg-img" id="dipl-bg-img" onload="editorInit('diploma')">
                        <?php else: ?>
                            <div class="no-bg-placeholder" id="dipl-no-bg">
                                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg>
                                <span>Загрузите фон для диплома</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="mt-3 flex gap-2 items-end" id="dipl-bg-form">
                        <input type="hidden" name="save_template" value="1">
                        <input type="hidden" name="event_id" value="<?= $filterEvent ?>">
                        <input type="hidden" name="doc_type" value="diploma">
                        <input type="hidden" name="elements_json" id="dipl-bg-elements-json">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Фон (JPG/PNG)</label>
                            <input type="file" name="background" accept=".jpg,.jpeg,.png" class="w-full text-sm p-1.5 border rounded" onchange="this.form.querySelector('#dipl-bg-elements-json').value=getElementsJSON('diploma');this.form.submit()">
                        </div>
                    </form>
                </div>

                <div class="w-full xl:w-[380px] flex-shrink-0 p-5 flex flex-col" style="max-height:700px">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-bold text-gray-600">Элементы</span>
                        <div class="flex gap-1">
                            <button onclick="addTextElement('diploma')" class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-2.5 py-1 rounded transition">+ Текст</button>
                            <button onclick="toggleQR('diploma')" class="text-xs bg-purple-600 hover:bg-purple-700 text-white px-2.5 py-1 rounded transition" id="dipl-qr-toggle">+ QR</button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto space-y-2 mb-3 pr-1" id="dipl-elements-list"></div>

                    <form method="POST" enctype="multipart/form-data" id="dipl-save-form">
                        <input type="hidden" name="save_template" value="1">
                        <input type="hidden" name="event_id" value="<?= $filterEvent ?>">
                        <input type="hidden" name="doc_type" value="diploma">
                        <input type="hidden" name="elements_json" id="dipl-elements-json">
                        <button type="submit" onclick="document.getElementById('dipl-elements-json').value=getElementsJSON('diploma')" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded transition text-sm">
                            Сохранить шаблон
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Generation -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold mb-4">Генерация сертификатов</h3>
            <form method="POST">
                <input type="hidden" name="generate_certificates" value="1">
                <input type="hidden" name="event_id" value="<?= $filterEvent ?>">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Текст (option_text)</label>
                    <input type="text" name="option_text" placeholder="за участие в хакатоне XYZ" class="w-full p-2 border rounded text-sm">
                </div>
                <button type="submit" onclick="return confirm('Сгенерировать сертификаты для всех участников?')" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition">
                    Сгенерировать сертификаты
                </button>
            </form>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold mb-4">Генерация дипломов</h3>
            <form method="POST">
                <input type="hidden" name="generate_diplomas" value="1">
                <input type="hidden" name="event_id" value="<?= $filterEvent ?>">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Текст (option_text)</label>
                    <input type="text" name="option_text_diploma" placeholder="за участие в хакатоне XYZ" class="w-full p-2 border rounded text-sm">
                </div>
                <button type="submit" onclick="return confirm('Сгенерировать дипломы для всех команд?')" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded transition">
                    Сгенерировать дипломы
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Certificates Table -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold mb-4">Список документов</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="py-2 px-3 text-left">ID</th>
                        <th class="py-2 px-3 text-left">Код</th>
                        <th class="py-2 px-3 text-left">Тип</th>
                        <th class="py-2 px-3 text-left">ФИО / Команда</th>
                        <th class="py-2 px-3 text-center">Действия</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if (empty($certificates)): ?>
                        <tr><td colspan="5" class="py-6 text-center text-gray-400">Нет документов</td></tr>
                    <?php else: ?>
                        <?php foreach ($certificates as $c): ?>
                            <?php
                            $certName = '';
                            if ($c['type'] === 'team_user') {
                                $tu = R::load('teamuser', (int)$c['team_user_id']);
                                $certName = $tu->id ? $tu->full_name : '—';
                            } else {
                                $tm = R::load('teams', (int)$c['team_id']);
                                $certName = $tm->id ? $tm->name : '—';
                            }
                            $typeBadge = $c['type'] === 'team_user'
                                ? '<span class="px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800">Сертификат</span>'
                                : '<span class="px-2 py-0.5 rounded text-xs font-bold bg-purple-100 text-purple-800">Диплом</span>';
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-3"><?= $c['id'] ?></td>
                                <td class="py-2 px-3 font-mono text-sm"><?= htmlspecialchars($c['code'] ?? '') ?></td>
                                <td class="py-2 px-3"><?= $typeBadge ?></td>
                                <td class="py-2 px-3 font-semibold"><?= htmlspecialchars($certName) ?></td>
                                <td class="py-2 px-3 text-center whitespace-nowrap">
                                    <a href="/certificate.php?id=<?= $c['id'] ?>" class="text-blue-500 hover:text-blue-700 font-bold mr-2">Скачать</a>
                                    <a href="?<?= $filterEvent ? 'event=' . $filterEvent . '&' : '' ?>delete_cert=<?= $c['id'] ?>" onclick="return confirm('Удалить этот документ?')" class="text-red-500 hover:text-red-700 font-bold">Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Verification Link -->
    <div class="bg-white rounded-lg shadow-md p-5 mt-8">
        <h3 class="font-bold text-gray-800 mb-3">Страница проверки</h3>
        <div class="flex items-center gap-3 bg-gray-50 rounded px-4 py-2">
            <code class="text-blue-600 text-sm bg-blue-50 px-2 py-1 rounded flex-1">/verify.php?code=КОД</code>
            <button onclick="copyVerifyLink(this)" class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded transition">Копировать</button>
        </div>
    </div>
</div>

<script>
// ---- State ----
var editors = {
    certificate: { elements: [], scale: 1, naturalW: 0, naturalH: 0, selectedIdx: -1, hasQR: false },
    diploma:     { elements: [], scale: 1, naturalW: 0, naturalH: 0, selectedIdx: -1, hasQR: false }
};

var fontsAvailable = <?= json_encode($fonts) ?>;

// ---- Init from PHP data ----
(function() {
    var certData = <?= $certElementsJson ?>;
    var diplData = <?= $diplElementsJson ?>;

    function splitElements(data) {
        var texts = [], qr = null;
        (data || []).forEach(function(el) {
            if (el.data) qr = el;
            else texts.push(el);
        });
        return { texts: texts, qr: qr };
    }

    var c = splitElements(certData);
    editors.certificate.elements = c.texts;
    if (c.qr) { editors.certificate.hasQR = true; editors.certificate.qr = c.qr; }
    else { editors.certificate.qr = { data: '{certificate_url}', size: 150, x: 100, y: 100 }; }

    var d = splitElements(diplData);
    editors.diploma.elements = d.texts;
    if (d.qr) { editors.diploma.hasQR = true; editors.diploma.qr = d.qr; }
    else { editors.diploma.qr = { data: '{certificate_url}', size: 150, x: 100, y: 100 }; }
})();

// ---- Tab switching ----
function switchTab(type) {
    document.getElementById('editor-certificate').style.display = type === 'certificate' ? '' : 'none';
    document.getElementById('editor-diploma').style.display = type === 'diploma' ? '' : 'none';
    document.getElementById('tab-certificate').className = 'tab-btn' + (type === 'certificate' ? ' active' : '');
    document.getElementById('tab-diploma').className = 'tab-btn' + (type === 'diploma' ? ' active' : '');
}

// ---- Editor init (called when bg image loads) ----
function editorInit(type) {
    var img = document.getElementById(type === 'certificate' ? 'cert-bg-img' : 'dipl-bg-img');
    if (!img) return;
    var ed = editors[type];
    ed.naturalW = img.naturalWidth;
    ed.naturalH = img.naturalHeight;
    ed.scale = img.clientWidth / img.naturalWidth;

    var sizeLabel = document.getElementById(type === 'certificate' ? 'cert-canvas-size' : 'dipl-canvas-size');
    if (sizeLabel) sizeLabel.textContent = ed.naturalW + ' x ' + ed.naturalH + ' px (масштаб ' + Math.round(ed.scale * 100) + '%)';

    renderCanvas(type);
    renderElementsList(type);
    updateQRToggle(type);
}

// ---- Render canvas overlays ----
function renderCanvas(type) {
    var prefix = type === 'certificate' ? 'cert' : 'dipl';
    var canvas = document.getElementById(prefix + '-canvas');
    var ed = editors[type];

    // Remove old overlays
    canvas.querySelectorAll('.canvas-element, .qr-placeholder').forEach(function(el) { el.remove(); });

    if (!ed.naturalW) return;

    // Text elements
    ed.elements.forEach(function(el, idx) {
        var div = document.createElement('div');
        div.className = 'canvas-element' + (idx === ed.selectedIdx ? ' selected' : '');
        div.dataset.idx = idx;
        div.dataset.type = type;

        var displayText = el.text.replace(/\{(\w+)\}/g, function(m, k) {
            var samples = { full_name: 'Иванов Иван', team_name: 'Команда Альфа', mentor_name: 'Петров П.П.', option_text: 'за участие', certificate_url: 'URL' };
            return samples[k] || m;
        });

        div.textContent = displayText;
        div.style.fontSize = (el.size * ed.scale) + 'px';
        div.style.color = el.color;
        div.style.fontWeight = '600';
        div.style.fontFamily = 'Montserrat, sans-serif';

        // Position: center_x/center_y in real coords -> left/top in scaled
        var scaledX = el.center_x * ed.scale;
        var scaledY = el.center_y * ed.scale;
        div.style.left = scaledX + 'px';
        div.style.top = scaledY + 'px';
        div.style.transform = 'translate(-50%, -50%)';

        // Coord badge
        var badge = document.createElement('span');
        badge.className = 'coord-badge';
        badge.textContent = el.center_x + ', ' + el.center_y;
        div.appendChild(badge);

        div.addEventListener('mousedown', function(e) { startDrag(e, type, idx, 'text'); });
        div.addEventListener('click', function(e) { e.stopPropagation(); selectElement(type, idx); });

        canvas.appendChild(div);
    });

    // QR
    if (ed.hasQR) {
        var qr = ed.qr;
        var qDiv = document.createElement('div');
        qDiv.className = 'qr-placeholder' + (ed.selectedIdx === -2 ? ' selected' : '');
        qDiv.dataset.type = type;
        var qs = qr.size * ed.scale;
        qDiv.style.width = qs + 'px';
        qDiv.style.height = qs + 'px';
        qDiv.style.left = (qr.x * ed.scale) + 'px';
        qDiv.style.top = (qr.y * ed.scale) + 'px';
        qDiv.innerHTML = 'QR<span class="coord-badge">' + qr.x + ', ' + qr.y + ' (' + qr.size + 'px)</span>';

        qDiv.addEventListener('mousedown', function(e) { startDrag(e, type, -2, 'qr'); });
        qDiv.addEventListener('click', function(e) { e.stopPropagation(); selectElement(type, -2); });

        canvas.appendChild(qDiv);
    }
}

// ---- Render elements list panel ----
function renderElementsList(type) {
    var prefix = type === 'certificate' ? 'cert' : 'dipl';
    var list = document.getElementById(prefix + '-elements-list');
    var ed = editors[type];
    var html = '';

    ed.elements.forEach(function(el, idx) {
        var isActive = idx === ed.selectedIdx;
        html += '<div class="el-item p-3 rounded-lg border bg-gray-50 ' + (isActive ? 'active' : '') + '" data-idx="' + idx + '" onclick="selectElement(\'' + type + '\',' + idx + ')">';
        html += '<div class="flex items-center gap-2 mb-2">';
        html += '<span class="el-drag-handle text-lg">&#9776;</span>';
        html += '<input type="text" value="' + escHtml(el.text) + '" class="flex-1 p-1.5 border rounded text-sm font-mono bg-white" onchange="updateEl(\'' + type + '\',' + idx + ',\'text\',this.value)" onclick="event.stopPropagation()">';
        html += '<button onclick="event.stopPropagation();removeElement(\'' + type + '\',' + idx + ')" class="text-red-400 hover:text-red-600 text-lg font-bold leading-none" title="Удалить">&times;</button>';
        html += '</div>';

        html += '<div class="grid grid-cols-4 gap-1.5">';
        html += '<div><label class="text-[10px] text-gray-400 block">Размер</label><input type="number" value="' + el.size + '" class="w-full p-1 border rounded text-xs" onchange="updateEl(\'' + type + '\',' + idx + ',\'size\',+this.value)" onclick="event.stopPropagation()"></div>';
        html += '<div><label class="text-[10px] text-gray-400 block">Цвет</label><input type="color" value="' + (el.color || '#000000') + '" class="w-full p-0.5 border rounded h-[26px]" onchange="updateEl(\'' + type + '\',' + idx + ',\'color\',this.value)" onclick="event.stopPropagation()"></div>';
        html += '<div><label class="text-[10px] text-gray-400 block">X</label><input type="number" value="' + el.center_x + '" class="w-full p-1 border rounded text-xs" onchange="updateEl(\'' + type + '\',' + idx + ',\'center_x\',+this.value)" onclick="event.stopPropagation()"></div>';
        html += '<div><label class="text-[10px] text-gray-400 block">Y</label><input type="number" value="' + el.center_y + '" class="w-full p-1 border rounded text-xs" onchange="updateEl(\'' + type + '\',' + idx + ',\'center_y\',+this.value)" onclick="event.stopPropagation()"></div>';
        html += '</div>';

        html += '<div class="mt-1.5"><label class="text-[10px] text-gray-400 block">Шрифт</label><select class="w-full p-1 border rounded text-xs" onchange="updateEl(\'' + type + '\',' + idx + ',\'font\',this.value)" onclick="event.stopPropagation()">';
        fontsAvailable.forEach(function(f) {
            html += '<option value="' + escHtml(f) + '"' + (el.font === f ? ' selected' : '') + '>' + escHtml(f.split('/').pop()) + '</option>';
        });
        html += '</select></div>';

        html += '</div>';
    });

    // QR section
    if (ed.hasQR) {
        var qr = ed.qr;
        var isQRActive = ed.selectedIdx === -2;
        html += '<div class="el-item p-3 rounded-lg border bg-purple-50 border-purple-200 ' + (isQRActive ? 'active' : '') + '" onclick="selectElement(\'' + type + '\',-2)">';
        html += '<div class="flex items-center gap-2 mb-2"><span class="text-purple-600 font-bold text-sm">QR-код</span>';
        html += '<button onclick="event.stopPropagation();toggleQR(\'' + type + '\')" class="ml-auto text-red-400 hover:text-red-600 text-lg font-bold leading-none" title="Удалить">&times;</button></div>';
        html += '<div class="grid grid-cols-3 gap-1.5">';
        html += '<div><label class="text-[10px] text-gray-400 block">Размер</label><input type="number" value="' + qr.size + '" class="w-full p-1 border rounded text-xs" onchange="updateQR(\'' + type + '\',\'size\',+this.value)" onclick="event.stopPropagation()"></div>';
        html += '<div><label class="text-[10px] text-gray-400 block">X</label><input type="number" value="' + qr.x + '" class="w-full p-1 border rounded text-xs" onchange="updateQR(\'' + type + '\',\'x\',+this.value)" onclick="event.stopPropagation()"></div>';
        html += '<div><label class="text-[10px] text-gray-400 block">Y</label><input type="number" value="' + qr.y + '" class="w-full p-1 border rounded text-xs" onchange="updateQR(\'' + type + '\',\'y\',+this.value)" onclick="event.stopPropagation()"></div>';
        html += '</div></div>';
    }

    if (!ed.elements.length && !ed.hasQR) {
        html = '<div class="text-center text-gray-400 py-8 text-sm">Нет элементов. Нажмите "+ Текст" чтобы добавить.</div>';
    }

    list.innerHTML = html;
}

// ---- Selection ----
function selectElement(type, idx) {
    editors[type].selectedIdx = idx;
    renderCanvas(type);
    renderElementsList(type);
}

// ---- Update element property ----
function updateEl(type, idx, prop, val) {
    editors[type].elements[idx][prop] = val;
    renderCanvas(type);
    renderElementsList(type);
}

function updateQR(type, prop, val) {
    editors[type].qr[prop] = val;
    renderCanvas(type);
    renderElementsList(type);
}

// ---- Add / Remove ----
function addTextElement(type) {
    var ed = editors[type];
    var cx = ed.naturalW ? Math.round(ed.naturalW / 2) : 800;
    var cy = ed.naturalH ? Math.round(ed.naturalH / 2) : 500;
    ed.elements.push({
        text: '{placeholder}',
        font: fontsAvailable[0],
        size: 32,
        color: '#000000',
        center_x: cx,
        center_y: cy
    });
    ed.selectedIdx = ed.elements.length - 1;
    renderCanvas(type);
    renderElementsList(type);
}

function removeElement(type, idx) {
    editors[type].elements.splice(idx, 1);
    editors[type].selectedIdx = -1;
    renderCanvas(type);
    renderElementsList(type);
}

function toggleQR(type) {
    var ed = editors[type];
    ed.hasQR = !ed.hasQR;
    if (ed.hasQR && !ed.qr) {
        ed.qr = { data: '{certificate_url}', size: 150, x: 100, y: 100 };
    }
    if (!ed.hasQR && ed.selectedIdx === -2) ed.selectedIdx = -1;
    updateQRToggle(type);
    renderCanvas(type);
    renderElementsList(type);
}

function updateQRToggle(type) {
    var btn = document.getElementById((type === 'certificate' ? 'cert' : 'dipl') + '-qr-toggle');
    if (!btn) return;
    var ed = editors[type];
    btn.textContent = ed.hasQR ? '- QR' : '+ QR';
    btn.className = btn.className.replace(/bg-\w+-600 hover:bg-\w+-700/g, '');
    btn.className += ed.hasQR ? ' bg-red-500 hover:bg-red-600' : ' bg-purple-600 hover:bg-purple-700';
}

// ---- Drag & Drop ----
var dragState = null;

function startDrag(e, type, idx, kind) {
    e.preventDefault();
    e.stopPropagation();
    var ed = editors[type];
    var el = kind === 'qr' ? ed.qr : ed.elements[idx];
    selectElement(type, idx);

    var canvas = document.getElementById((type === 'certificate' ? 'cert' : 'dipl') + '-canvas');
    var rect = canvas.getBoundingClientRect();

    dragState = {
        type: type,
        idx: idx,
        kind: kind,
        startMouseX: e.clientX,
        startMouseY: e.clientY,
        startX: kind === 'qr' ? el.x : el.center_x,
        startY: kind === 'qr' ? el.y : el.center_y,
        scale: ed.scale,
        target: e.target.closest('.canvas-element, .qr-placeholder')
    };

    if (dragState.target) dragState.target.classList.add('dragging');

    document.addEventListener('mousemove', onDrag);
    document.addEventListener('mouseup', endDrag);
}

function onDrag(e) {
    if (!dragState) return;
    var dx = (e.clientX - dragState.startMouseX) / dragState.scale;
    var dy = (e.clientY - dragState.startMouseY) / dragState.scale;
    var newX = Math.round(dragState.startX + dx);
    var newY = Math.round(dragState.startY + dy);
    var ed = editors[dragState.type];

    // Clamp
    newX = Math.max(0, Math.min(ed.naturalW, newX));
    newY = Math.max(0, Math.min(ed.naturalH, newY));

    if (dragState.kind === 'qr') {
        ed.qr.x = newX;
        ed.qr.y = newY;
    } else {
        ed.elements[dragState.idx].center_x = newX;
        ed.elements[dragState.idx].center_y = newY;
    }

    renderCanvas(dragState.type);
}

function endDrag(e) {
    if (dragState && dragState.target) dragState.target.classList.remove('dragging');
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', endDrag);
    if (dragState) {
        renderElementsList(dragState.type);
    }
    dragState = null;
}

// ---- Build JSON for form submit ----
function getElementsJSON(type) {
    var ed = editors[type];
    var result = [];
    ed.elements.forEach(function(el) {
        result.push({
            text: el.text,
            font: el.font,
            size: el.size,
            color: el.color,
            center_x: el.center_x,
            center_y: el.center_y
        });
    });
    if (ed.hasQR) {
        result.push({
            data: '{certificate_url}',
            size: ed.qr.size,
            x: ed.qr.x,
            y: ed.qr.y
        });
    }
    return JSON.stringify(result);
}

// ---- Helpers ----
function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function copyVerifyLink(btn) {
    var url = window.location.origin + '/verify.php';
    navigator.clipboard.writeText(url).then(function() {
        btn.textContent = 'Скопировано!';
        btn.className = btn.className.replace('bg-blue-600 hover:bg-blue-700', 'bg-green-600 hover:bg-green-700');
        setTimeout(function() {
            btn.textContent = 'Копировать';
            btn.className = btn.className.replace('bg-green-600 hover:bg-green-700', 'bg-blue-600 hover:bg-blue-700');
        }, 2000);
    });
}

// ---- Init on page load ----
window.addEventListener('load', function() {
    editorInit('certificate');
    editorInit('diploma');
});

// ---- Resize handling ----
window.addEventListener('resize', function() {
    editorInit('certificate');
    editorInit('diploma');
});

// ---- Click on canvas background to deselect ----
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('editor-canvas-wrap') || e.target.classList.contains('bg-img')) {
        ['certificate', 'diploma'].forEach(function(type) {
            if (editors[type].selectedIdx !== -1) {
                editors[type].selectedIdx = -1;
                renderCanvas(type);
                renderElementsList(type);
            }
        });
    }
});
</script>

<?php require '../includes/footer.php'; ?>
