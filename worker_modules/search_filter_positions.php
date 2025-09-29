<?php
header('Content-Type: application/json; charset=utf-8');

$dsn = "mysql:host=127.0.0.1;dbname=plan_U5;charset=utf8mb4";
$user = "root";
$pass = "";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $filterName = $_POST['filter_name'] ?? '';
    
    if (empty($filterName) || strlen($filterName) < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'Введите минимум 2 символа для поиска'
        ]);
        exit;
    }

    // Поиск позиций по названию фильтра
    $stmt = $pdo->prepare("
        SELECT 
            cp.order_number,
            cp.filter_label,
            cp.plan_date,
            SUM(cp.count) as plan_sum,
            SUM(cp.fact_count) as fact_sum
        FROM corrugation_plan cp
        WHERE cp.filter_label LIKE :filter_name
        GROUP BY cp.order_number, cp.filter_label, cp.plan_date
        ORDER BY cp.plan_date DESC, cp.order_number, cp.filter_label
        LIMIT 50
    ");
    
    $stmt->execute(['filter_name' => '%' . $filterName . '%']);
    $results = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'results' => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
}
?>


