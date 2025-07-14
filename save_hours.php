<?php
// save_hours.php

// Подключение к БД
$host = 'localhost';
$db = 'plan_u5';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo "Ошибка подключения к БД: " . $e->getMessage();
    exit;
}

// Обработка формы
$inserted = 0;

if (isset($_POST['hours']) && is_array($_POST['hours'])) {
    foreach ($_POST['hours'] as $key => $value) {
        // Пропустить пустые значения
        if (trim($value) === '') continue;

        // Разделить ключ (пример: AF5056_24-28-25)
        $parts = explode('_', $key, 2);
        if (count($parts) !== 2) continue;

        $filter = $parts[0];
        $order_number = $parts[1];
        $hours = (float)$value;

        // Проверка валидности
        if ($hours <= 0) continue;

        // Запись в БД
        $stmt = $pdo->prepare("REPLACE INTO hourly_work_log (filter, order_number, hours) VALUES (?, ?, ?)");
        if ($stmt->execute([$filter, $order_number, $hours])) {
            $inserted++;
        } else {
            $error = $stmt->errorInfo();
            echo "Ошибка вставки: " . $error[2] . "<br>";
        }
    }
}

echo "Сохранено строк: $inserted";
