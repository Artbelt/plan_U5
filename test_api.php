<?php
// Простой тест API
header('Content-Type: application/json; charset=utf-8');

try {
    // Подключение к базе данных
    $mysqli = new mysqli("localhost", "root", "", "plan_U5");

    if ($mysqli->connect_error) {
        throw new Exception('Ошибка подключения к базе данных: ' . $mysqli->connect_error);
    }

    // Проверяем таблицу manufactured_production
    $query = "SELECT COUNT(*) as count FROM manufactured_production";
    $result = $mysqli->query($query);
    
    if (!$result) {
        throw new Exception('Ошибка запроса: ' . $mysqli->error);
    }
    
    $row = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'message' => 'API работает',
        'table_count' => $row['count'],
        'connection' => 'OK'
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>


