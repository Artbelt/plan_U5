<?php
session_start();
require_once('settings.php');
require_once('tools/tools.php');

// быстрая функция флеша
function flash_exit(string $msg, string $redirect = 'index.php') {
    $_SESSION['flash_error'] = $msg;
    header("Location: $redirect");
    exit;
}

// Проверка метода
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_exit('Неверный метод запроса.');
}

// CSRF
if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    flash_exit('Сессия устарела. Обновите страницу и попробуйте снова.');
}

// Ввод
$user     = trim($_POST['user_name'] ?? '');
$password = trim($_POST['user_pass'] ?? '');
$workshop = trim($_POST['workshop'] ?? '');

if ($user === '' || $password === '' || $workshop === '') {
    flash_exit('Заполните все поля формы.');
}

// Подключение БД
$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
if ($mysqli->connect_errno) {
    flash_exit('Ошибка подключения к базе данных.');
}

// Пользователь
$stmt = $mysqli->prepare("SELECT * FROM users WHERE user = ?");
$stmt->bind_param("s", $user);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    flash_exit('Нет такого пользователя.');
}
$u = $res->fetch_assoc();

// Пароль
// В идеале: password_hash / password_verify. Пока — сравнение как в текущей БД:
if ($password !== (string)$u['pass']) {
    flash_exit('Неверный пароль.');
}

// Доступ к подразделению
if (!isset($u[$workshop]) || (int)$u[$workshop] <= 0) {
    flash_exit('Доступ к выбранному подразделению закрыт.');
}

// Всё ок — сохраняем сессию
$_SESSION['user'] = $user;
$_SESSION['workshop'] = $workshop;

// можно убрать одноразовый csrf, чтобы на главной с новой формой выдался новый
unset($_SESSION['csrf']);

// переходим на вашу главную страницу (та, что в вопросе)
header('Location: main.php'); // при необходимости замените на нужный файл (раньше у вас был enter.php + вывод)
exit;
