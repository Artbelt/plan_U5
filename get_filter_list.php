<?php
require_once('tools/tools.php');

header('Content-Type: application/json; charset=utf-8');

try {
    $query = $_POST['query'] ?? '';
    
    if (empty($query) || strlen($query) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'Запрос слишком короткий'
        ]);
        exit;
    }

    // Подключение к базе данных
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Поиск фильтров по имени
    $stmt = $pdo->prepare("
        SELECT DISTINCT filter 
        FROM salon_filter_structure 
        WHERE filter LIKE :query 
        ORDER BY filter 
        LIMIT 10
    ");
    
    $searchQuery = '%' . $query . '%';
    $stmt->execute(['query' => $searchQuery]);
    
    $filters = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'filters' => $filters
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
}
?>




