<?php
require_once('tools/tools.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $b_name = $_POST['b_name'] ?? '';
    $b_length = floatval(str_replace(',', '.', $_POST['b_length'] ?? 0));
    $b_width = floatval(str_replace(',', '.', $_POST['b_width'] ?? 0));
    $b_heght = floatval(str_replace(',', '.', $_POST['b_heght'] ?? 0));
    $b_supplier = $_POST['b_supplier'] ?? '';
    
    // Валидация
    if (empty($b_name) || $b_length <= 0 || $b_width <= 0 || $b_heght <= 0) {
        echo json_encode(['success' => false, 'error' => 'Не заполнены обязательные поля']);
        exit;
    }
    
    // Подключение к БД
    $mysqli = new mysqli($host, $username, $password, $dbname_U5);
    $mysqli->set_charset("utf8mb4");
    
    if ($mysqli->connect_error) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подключения к БД']);
        exit;
    }
    
    // Вставка в таблицу box
    $stmt = $mysqli->prepare("INSERT INTO box (b_name, b_length, b_width, b_heght, b_supplier) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Ошибка подготовки запроса: ' . $mysqli->error]);
        $mysqli->close();
        exit;
    }
    
    $stmt->bind_param("sddds", $b_name, $b_length, $b_width, $b_heght, $b_supplier);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $mysqli->error]);
    }
    
    $stmt->close();
    $mysqli->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса']);
}
?>

