<?php
// Долгоживущая сессия (30 дней)
ini_set('session.gc_maxlifetime', 2592000);
session_set_cookie_params(2592000);
session_start();

include_once __DIR__ . '/db.php';

// Если сессия пустая — пробуем восстановить из remember-токена
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $user = R::findOne('users', 'remember_token = ?', [$token]);
    if ($user && $user->id) {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['user_event_id'] = $user->event_id ?? 0;
    } else {
        // Токен невалидный — удаляем куку
        setcookie('remember_token', '', time() - 3600, '/');
    }
}
