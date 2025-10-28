<?php
header('Content-Type: application/json; charset=utf-8');

try {
    // Подключение к БД
    $mysqli = new mysqli('127.0.0.1', 'root', '', 'plan_u5');
    
    if ($mysqli->connect_errno) {
        throw new Exception('Ошибка подключения к БД: ' . $mysqli->connect_error);
    }
    
    $mysqli->set_charset('utf8mb4');
    
    // Получение данных
    $gb_name = $_POST['gb_name'] ?? '';
    $gb_length = $_POST['gb_length'] ?? '';
    $gb_width = $_POST['gb_width'] ?? '';
    $gb_heght = $_POST['gb_heght'] ?? '';
    $gb_supplier = $_POST['gb_supplier'] ?? 'УУ';
    
    // Валидация
    if (empty($gb_name) || empty($gb_length) || empty($gb_width) || empty($gb_heght)) {
        throw new Exception('Не все обязательные поля заполнены');
    }
    
    // Проверка на числа
    if (!is_numeric($gb_length) || !is_numeric($gb_width) || !is_numeric($gb_heght)) {
        throw new Exception('Длина, ширина и высота должны быть числами');
    }
    
    // Проверка, не существует ли уже ящик с таким номером
    $stmt = $mysqli->prepare("SELECT gb_name FROM g_box WHERE gb_name = ?");
    $stmt->bind_param('s', $gb_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('Ящик с номером "' . htmlspecialchars($gb_name) . '" уже существует');
    }
    $stmt->close();
    
    // Вставка нового ящика
    $stmt = $mysqli->prepare("
        INSERT INTO g_box (gb_name, gb_length, gb_width, gb_heght, gb_supplier) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('sddds', $gb_name, $gb_length, $gb_width, $gb_heght, $gb_supplier);
    
    if (!$stmt->execute()) {
        throw new Exception('Ошибка при сохранении: ' . $stmt->error);
    }
    
    $stmt->close();
    $mysqli->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ящик успешно добавлен',
        'gb_name' => $gb_name
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}


