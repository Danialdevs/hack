<?php
include 'includes/db.php';

$id = $_GET["id"] ?? null;
if (!$id) {
    die("Ошибка: Не указан ID сертификата.");
}

$certificate = R::findOne("certificates", "id = ?", [$id]);
if (!$certificate) {
    die("Ошибка: Сертификат не найден.");
}
$template = R::findOne("certificatetemplate", "id = ?", [$certificate->template_id]);
if (!$template) {
    die("Ошибка: Шаблон сертификата не найден.");
}

$bg = "./storage/" . $template->background;
if (!file_exists($bg)) {
    die("Ошибка: Файл фона не найден.");
}

// Загружаем изображение
$ext = strtolower(pathinfo($bg, PATHINFO_EXTENSION));
if ($ext === 'png') {
    $image = imagecreatefrompng($bg);
} else {
    $image = imagecreatefromjpeg($bg);
}
if (!$image) {
    die("Ошибка: Не удалось загрузить изображение.");
}

// Загружаем элементы
$elements = json_decode($template->elements, true);
if (!$elements) {
    die("Ошибка: Неверный формат элементов.");
}
if($certificate->type == "team_user"){
	$team_user = R::findOne("teamuser", "id = ?", [$certificate->team_user_id]);
	if (!$team_user) {
		die("Ошибка: Участник команды не найден.");
	}

	$team = R::findOne("teams", "id = ?", [$team_user->team_id]);
	if (!$team) {
		die("Ошибка: Команда не найдена.");
	}

	$mentor = R::findOne("teamuser", "team_id = ? AND role = ?", [$team->id, 'mentor']);
	if (!$mentor) {
		die("Ошибка: Ментор не найден.");
	}

	$placeholders = [
		"{full_name}" => $team_user->full_name,
		"{mentor_name}" => $mentor->full_name,
	];
}else{
	$team = R::findOne("teams", "id = ?", [$certificate->team_id]);
	if (!$team) {
		die("Ошибка: Команда не найдена.");
	}

	$mentor = R::findOne("teamuser", "team_id = ? AND role = ?", [$team->id, 'mentor']);
	if (!$mentor) {
		die("Ошибка: Ментор не найден.");
	}
	
	$placeholders = [
		"{team_name}" => $team->name,
		"{mentor_name}" => $mentor->full_name,
	];
}

$placeholders += [
    "{certificate_url}" => "https://hackathon.nurymdaniyal.kz/certificate.php?id=" . $certificate->id,
    "{option_text}" => $certificate->option_text
];

foreach ($elements as $element) {
    if (isset($element['text'])) {
        $text = str_replace(array_keys($placeholders), array_values($placeholders), $element['text']);
        $font = './storage/' . $element['font'];
        if (!file_exists($font)) {
            die("Ошибка: Файл шрифта не найден.");
        }
        
        $size = $element['size'];
        list($r, $g, $b) = sscanf($element['color'], "#%02x%02x%02x");
        $color = imagecolorallocate($image, $r, $g, $b);
        
        if (isset($element['center_x']) && isset($element['center_y'])) {
            $textBox = imagettfbbox($size, 0, $font, $text);
            $textWidth = $textBox[2] - $textBox[0];
            $textHeight = $textBox[1] - $textBox[7];
            $x = $element['center_x'] - ($textWidth / 2);
            $y = $element['center_y'] + ($textHeight / 2);
        } else {
            $x = $element['x'];
            $y = $element['y'];
        }
        
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    } elseif (isset($element['data'])) {
$qrData = str_replace(array_keys($placeholders), array_values($placeholders), $element['data']);
$qrSize = isset($element['size']) ? (int) $element['size'] : 150; // Приведение к числу, если вдруг строка или null

$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$qrSize}x{$qrSize}&data=" . urlencode($qrData);

    
    // Загружаем изображение QR-кода в строку
    $qrImageData = file_get_contents($qrUrl);
    if ($qrImageData === false) {
        die("Ошибка: Не удалось загрузить QR-код.");
    }

    // Создаем изображение из строки
    $qr = imagecreatefromstring($qrImageData);
    if (!$qr) {
        die("Ошибка: Не удалось создать изображение QR-кода.");
    }

    // Копируем QR-код на фон
    imagecopy($image, $qr, $element['x'], $element['y'], 0, 0, imagesx($qr), imagesy($qr));
    imagedestroy($qr);
}

}


// Выводим изображение
ob_clean();
header('Content-Type: image/jpeg');
header('Content-Disposition: attachment; filename="certificate.jpg"');

imagejpeg($image);
imagedestroy($image);
exit;
?>