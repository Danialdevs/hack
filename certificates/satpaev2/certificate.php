<?php
include '../../includes/db.php';

$team_user = R::findOne("team_users", "id = ?", [$_GET["id"]]);

if ($team_user) {
    $team = R::findOne("teams", "id = ?", [$team_user->team_id]);
    $mentor = R::findOne("team_users", "team_id = ? AND role = ?", [$team_user->team_id, 'mentor']);
}



$bg = './cert.jpg';

$image = imagecreatefromjpeg($bg);

if (!$image) {
    die('Ошибка: не удалось загрузить изображение.');
}

$font = './Montserrat-SemiBold.ttf';

if (!file_exists($font)) {
    die('Ошибка: Файл шрифта не найден.');
}

$color1 = imagecolorallocate($image, 88, 158, 208); // #589ed0
$color2 = imagecolorallocate($image, 11, 59, 125);  // #0b3b7d



// Функция для центрирования текста относительно заданной точки
function centerTextAtPoint($image, $text, $font, $fontSize, $centerX, $centerY, $color) {
    $textBox = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = $textBox[2] - $textBox[0];  // Вычисляем ширину текста
    $textHeight = $textBox[1] - $textBox[7]; // Высота текста
    $x = $centerX - ($textWidth / 2); // Корректируем X, чтобы центр текста совпал с точкой
    $y = $centerY + ($textHeight / 2); // Корректируем Y (важно для вертикального выравнивания)
    imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
}

// Рисуем текст по центру указанной точки
centerTextAtPoint($image, $team_user->full_name, $font, 120, 3865, 2641, $color1);
// Текст и его координаты
$text = $mentor->full_name;
$x = 3310;
$y = 3150;
$fontSize = 80; // Размер шрифта

imagettftext($image, $fontSize, 0, $x, $y, $color2, $font, $text);

ob_clean();
header('Content-Type: image/jpeg');
header('Content-Disposition: attachment; filename="certificate.jpg"');
imagejpeg($image);
imagedestroy($image);
exit;

?>
