<?php
// API для получения всех фильтров по заявке с прогрессом по этапам
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
    // Получаем все фильтры по заявке с метаданными
    $sql = "
        SELECT 
            o.filter,
            o.count as plan_count,
            COALESCE(pps.p_p_height, cp.height) as height,
            COALESCE(pps.p_p_width, cp.width) as width,
            COALESCE(pps.p_p_material, 'Неизвестно') as material,
            sfs.paper_package,
            -- Прогресс порезки
            COALESCE(SUM(CASE WHEN rp.done = 1 THEN 1 ELSE 0 END), 0) as cut_done_bales,
            COALESCE(COUNT(DISTINCT rp.bale_id), 0) as cut_total_bales,
            -- Прогресс гофрирования
            COALESCE(SUM(corr.fact_count), 0) as corr_fact_count,
            COALESCE(SUM(corr.count), 0) as corr_plan_count,
            -- Прогресс сборки
            COALESCE(SUM(mp.count_of_filters), 0) as build_fact_count,
            o.count as build_plan_count,
            -- Даты
            MIN(corr.plan_date) as plan_date,
            MAX(corr.plan_date) as end_date
        FROM orders o
        LEFT JOIN salon_filter_structure sfs ON sfs.filter = o.filter
        LEFT JOIN paper_package_salon pps ON pps.p_p_name = sfs.paper_package
        LEFT JOIN cut_plans cp ON cp.order_number = o.order_number AND cp.filter = o.filter
        LEFT JOIN roll_plans rp ON rp.order_number = o.order_number AND rp.bale_id = cp.bale_id
        LEFT JOIN corrugation_plan corr ON corr.order_number = o.order_number AND corr.filter_label = o.filter
        LEFT JOIN manufactured_production mp ON mp.name_of_order = o.order_number AND mp.name_of_filter = o.filter
        WHERE o.order_number = :order
          AND (o.hide IS NULL OR o.hide = 0)
        GROUP BY o.filter, o.count, pps.p_p_height, cp.height, pps.p_p_width, cp.width, 
                 pps.p_p_material, sfs.paper_package
        ORDER BY o.filter
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order' => $order]);
    $filters = $stmt->fetchAll();
    
    // Обрабатываем данные для фронтенда
    $processedFilters = [];
    foreach ($filters as $filter) {
        $planCount = (int)$filter['plan_count'];
        
        // Прогресс порезки
        $cutTotal = (int)$filter['cut_total_bales'];
        $cutDone = (int)$filter['cut_done_bales'];
        $cutProgress = $cutTotal > 0 ? round(($cutDone / $cutTotal) * 100) : 0;
        
        // Прогресс гофрирования
        $corrPlan = (int)$filter['corr_plan_count'];
        $corrFact = (int)$filter['corr_fact_count'];
        $corrProgress = $corrPlan > 0 ? round(($corrFact / $corrPlan) * 100) : 0;
        
        // Прогресс сборки
        $buildPlan = $planCount;
        $buildFact = (int)$filter['build_fact_count'];
        $buildProgress = $buildPlan > 0 ? round(($buildFact / $buildPlan) * 100) : 0;
        
        // Определяем общий статус
        $status = 'in-progress';
        if ($buildProgress >= 100) {
            $status = 'completed';
        } elseif ($cutProgress < 50 && $corrProgress < 50 && $buildProgress < 50) {
            $status = 'critical';
        }
        
        // Определяем текущий этап
        $currentStage = 'cutting';
        if ($cutProgress >= 100 && $corrProgress < 100) {
            $currentStage = 'corrugation';
        } elseif ($cutProgress >= 100 && $corrProgress >= 100 && $buildProgress < 100) {
            $currentStage = 'assembly';
        } elseif ($buildProgress >= 100) {
            $currentStage = 'completed';
        }
        
        // Определяем приоритет
        $priority = 'low';
        if ($status === 'critical' || $cutProgress < 30) {
            $priority = 'high';
        } elseif ($cutProgress < 70 || $corrProgress < 50) {
            $priority = 'medium';
        }
        
        $processedFilters[] = [
            'filter' => $filter['filter'],
            'plan_count' => $planCount,
            'height' => $filter['height'],
            'width' => $filter['width'],
            'material' => $filter['material'],
            'paper_package' => $filter['paper_package'],
            'status' => $status,
            'current_stage' => $currentStage,
            'priority' => $priority,
            'progress' => [
                'cutting' => [
                    'percent' => $cutProgress,
                    'done' => $cutDone,
                    'total' => $cutTotal,
                    'label' => $cutTotal > 0 ? "{$cutDone}/{$cutTotal} бухт" : "0/0 бухт"
                ],
                'corrugation' => [
                    'percent' => $corrProgress,
                    'done' => $corrFact,
                    'total' => $corrPlan,
                    'label' => $corrPlan > 0 ? "{$corrFact}/{$corrPlan} шт" : "0/0 шт"
                ],
                'assembly' => [
                    'percent' => $buildProgress,
                    'done' => $buildFact,
                    'total' => $buildPlan,
                    'label' => "{$buildFact}/{$buildPlan} шт"
                ]
            ],
            'dates' => [
                'plan' => $filter['plan_date'],
                'end' => $filter['end_date']
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'filters' => $processedFilters,
        'total' => count($processedFilters)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
