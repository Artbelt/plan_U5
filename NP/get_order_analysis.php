<?php
header('Content-Type: application/json; charset=utf-8');

$order = $_GET['order'] ?? '';

if (empty($order)) {
    echo json_encode(['ok' => false, 'error' => 'Не указана заявка']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Общая информация о заявке
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(count) as total_count FROM orders WHERE order_number = ? AND (hide IS NULL OR hide != 1)");
    $stmt->execute([$order]);
    $order_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Количество бухт в раскрое
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT bale_id) as bales_count FROM cut_plans WHERE order_number = ?");
    $stmt->execute([$order]);
    $bales_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Прогресс по раскрою
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN fact_length > 0 THEN 1 ELSE 0 END) as done,
            COUNT(*) as total
        FROM cut_plans 
        WHERE order_number = ?
    ");
    $stmt->execute([$order]);
    $cut_progress = $stmt->fetch(PDO::FETCH_ASSOC);
    $cut_percent = $cut_progress['total'] > 0 ? round(($cut_progress['done'] / $cut_progress['total']) * 100) : 0;
    
    // Прогресс по гофрированию
    $stmt = $pdo->prepare("
        SELECT 
            SUM(COALESCE(fact_count, 0)) as fact,
            SUM(count) as plan
        FROM corrugation_plan 
        WHERE order_number = ?
    ");
    $stmt->execute([$order]);
    $corr_progress = $stmt->fetch(PDO::FETCH_ASSOC);
    $corr_percent = $corr_progress['plan'] > 0 ? round(($corr_progress['fact'] / $corr_progress['plan']) * 100) : 0;
    
    // Прогресс по сборке
    $stmt = $pdo->prepare("
        SELECT 
            SUM(COALESCE(fact_count, 0)) as fact,
            SUM(count) as plan
        FROM build_plan 
        WHERE order_number = ?
    ");
    $stmt->execute([$order]);
    $build_progress = $stmt->fetch(PDO::FETCH_ASSOC);
    $build_percent = $build_progress['plan'] > 0 ? round(($build_progress['fact'] / $build_progress['plan']) * 100) : 0;
    
    // Информация о датах планирования
    $stmt = $pdo->prepare("SELECT MIN(work_date) as start_date, MAX(work_date) as end_date FROM roll_plans WHERE order_number = ?");
    $stmt->execute([$order]);
    $dates = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Распределение по высотам из cut_plans с учетом количества фильтров и сложности
    // Используем подзапрос чтобы избежать дублирования при JOIN
    $stmt = $pdo->prepare("
        SELECT 
            h.height,
            h.strips_count,
            h.unique_filters,
            COALESCE(SUM(o.count), 0) as total_filters,
            COALESCE(SUM(CASE WHEN sfs.build_complexity < 600 THEN o.count ELSE 0 END), 0) as complex_filters
        FROM (
            SELECT 
                height,
                COUNT(*) as strips_count,
                COUNT(DISTINCT filter) as unique_filters,
                GROUP_CONCAT(DISTINCT filter) as filter_list
            FROM cut_plans
            WHERE order_number = ?
            GROUP BY height
        ) h
        LEFT JOIN orders o ON FIND_IN_SET(TRIM(o.filter), h.filter_list) > 0 AND o.order_number = ?
        LEFT JOIN salon_filter_structure sfs ON TRIM(o.filter) = TRIM(sfs.filter)
        GROUP BY h.height, h.strips_count, h.unique_filters
        ORDER BY h.height
    ");
    $stmt->execute([$order, $order]);
    $heights_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Анализ сложности (считаем простые >= 600, сложные < 600)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN sfs.build_complexity >= 600 THEN 1 END) as simple_count,
            COUNT(CASE WHEN sfs.build_complexity < 600 THEN 1 END) as complex_count,
            AVG(sfs.build_complexity) as avg_complexity,
            MIN(sfs.build_complexity) as min_complexity,
            MAX(sfs.build_complexity) as max_complexity
        FROM orders o
        LEFT JOIN salon_filter_structure sfs ON TRIM(o.filter) = TRIM(sfs.filter)
        WHERE o.order_number = ? AND sfs.build_complexity IS NOT NULL
    ");
    $stmt->execute([$order]);
    $complexity_analysis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'total_filters' => (int)$order_info['total_count'],
        'unique_filters' => (int)$order_info['total'],
        'bales_count' => (int)$bales_info['bales_count'],
        'progress' => [
            'cut' => $cut_percent,
            'corr' => $corr_percent,
            'build' => $build_percent
        ],
        'dates' => $dates,
        'heights' => $heights_data,
        'complexity' => $complexity_analysis
    ]);
    
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>

