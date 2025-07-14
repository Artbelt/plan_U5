<?php
require_once('C:/xampp/htdocs/plan_U5/settings.php') ;

// Подключение к базе данных

global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;

$host = $mysql_host;
$db = $mysql_database;
$user = $mysql_user;
$pass = $mysql_user_pass;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Получение данных из формы (POST-запрос)
    $title = $_POST['title'] ?? null;
    $content = $_POST['content'] ?? null;
    $expires_at = $_POST['expires_at'] ?? null;

    // Проверяем, что все поля заполнены
    if ($title && $content && $expires_at) {
        // Подготовка SQL-запроса для вставки данных
        $stmt = $pdo->prepare("
            INSERT INTO ads (title, content, expires_at) 
            VALUES (:title, :content, :expires_at)
        ");

        // Выполняем запрос с параметрами
        $stmt->execute([
            ':title' => $title,
            ':content' => $content,
            ':expires_at' => $expires_at
        ]);

        echo "Объявление успешно добавлено!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
    } else {
        echo "Заполните все поля!";
    }
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}
?>