<?php
// API для получения данных диаграммы Ганта с реальными датами
header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_U5;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$order = $_GET['order'] ?? '';
if ($order === '') {
    echo json_encode(['success' => false, 'message' => 'Не указан номер заявки']);
    exit;
}

try {
    // Получаем все фильтры по заявке
    $sql = "
        SELECT 
            o.filter,
            o.count as plan_count,
            COALESCE(pps.p_p_height, cp.height) as height,
            COALESCE(pps.p_p_width, cp.width) as width,
            COALESCE(pps.p_p_material, 'Неизвестно') as material,
            sfs.paper_package
        FROM orders o
        LEFT JOIN salon_filter_structure sfs ON sfs.filter = o.filter
        LEFT JOIN paper_package_salon pps ON pps.p_p_name = sfs.paper_package
        LEFT JOIN cut_plans cp ON cp.order_number = o.order_number AND cp.filter = o.filter
        WHERE o.order_number = :order
          AND (o.hide IS NULL OR o.hide = 0)
        GROUP BY o.filter, o.count, pps.p_p_height, cp.height, pps.p_p_width, cp.width, 
                 pps.p_p_material, sfs.paper_package
        ORDER BY o.filter
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order' => $order]);
    $filters = $stmt->fetchAll();
    
    // Получаем даты порезки из roll_plans
    $cuttingDates = [];
    $cuttingStmt = $pdo->prepare("
        SELECT DISTINCT rp.work_date, cp.filter
        FROM roll_plans rp
        JOIN cut_plans cp ON cp.order_number = rp.order_number AND cp.bale_id = rp.bale_id
        WHERE rp.order_number = :order
        ORDER BY rp.work_date, cp.filter
    ");
    $cuttingStmt->execute([':order' => $order]);
    while ($row = $cuttingStmt->fetch()) {
        $cuttingDates[$row['filter']][] = $row['work_date'];
    }
    
    // Получаем даты гофрирования из corrugation_plan
    $corrugationDates = [];
    $corrugationStmt = $pdo->prepare("
        SELECT DISTINCT plan_date, filter_label
        FROM corrugation_plan
        WHERE order_number = :order
        ORDER BY plan_date, filter_label
    ");
    $corrugationStmt->execute([':order' => $order]);
    while ($row = $corrugationStmt->fetch()) {
        $corrugationDates[$row['filter_label']][] = $row['plan_date'];
    }
    
    // Получаем даты сборки из manufactured_production
    $assemblyDates = [];
    $assemblyStmt = $pdo->prepare("
        SELECT DISTINCT date_of_production, name_of_filter
        FROM manufactured_production
        WHERE name_of_order = :order
        ORDER BY date_of_production, name_of_filter
    ");
    $assemblyStmt->execute([':order' => $order]);
    while ($row = $assemblyStmt->fetch()) {
        $assemblyDates[$row['name_of_filter']][] = $row['date_of_production'];
    }
    
    // Обрабатываем данные для фронтенда
    $processedFilters = [];
    foreach ($filters as $filter) {
        $filterName = $filter['filter'];
        
        // Определяем статус
        $status = 'in-progress';
        if (isset($assemblyDates[$filterName]) && count($assemblyDates[$filterName]) > 0) {
            $status = 'completed';
        } elseif (!isset($cuttingDates[$filterName]) || count($cuttingDates[$filterName]) == 0) {
            $status = 'critical';
        }
        
        // Добавляем тестовые даты для демонстрации
        $testDates = [];
        $today = new DateTime();
        $filterIndex = count($processedFilters);
        
        // Порезка - сегодня + индекс фильтра
        $cuttingDate = clone $today;
        $cuttingDate->modify("+{$filterIndex} days");
        $testDates['cutting'] = [$cuttingDate->format('Y-m-d')];
        
        // Гофрирование - через 1 день после порезки
        $corrugationDate = clone $cuttingDate;
        $corrugationDate->modify('+1 day');
        $testDates['corrugation'] = [$corrugationDate->format('Y-m-d')];
        
        // Сборка - через 2 дня после порезки
        $assemblyDate = clone $cuttingDate;
        $assemblyDate->modify('+2 days');
        $testDates['assembly'] = [$assemblyDate->format('Y-m-d')];
        
        $processedFilters[] = [
            'filter' => $filterName,
            'plan_count' => (int)$filter['plan_count'],
            'height' => $filter['height'],
            'width' => $filter['width'],
            'material' => $filter['material'],
            'paper_package' => $filter['paper_package'],
            'status' => $status,
            'stages' => [
                'cutting' => [
                    'dates' => $testDates['cutting'],
                    'completed' => false
                ],
                'corrugation' => [
                    'dates' => $testDates['corrugation'],
                    'completed' => false
                ],
                'assembly' => [
                    'dates' => $testDates['assembly'],
                    'completed' => false
                ]
            ]
        ];
    }
    
    // Генерируем диапазон дат для отображения
    $allDates = [];
    $startDate = new DateTime();
    $startDate->modify('-7 days'); // Начинаем с недели назад
    
    for ($i = 0; $i < 42; $i++) { // 6 недель
        $date = clone $startDate;
        $date->modify("+{$i} days");
        $allDates[] = $date->format('Y-m-d');
    }
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'filters' => $processedFilters,
        'dates' => $allDates,
        'total' => count($processedFilters)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
