<?php
// NP/get_roll_plan.php
header('Content-Type: application/json; charset=utf-8');

try{
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $order = $_GET['order'] ?? '';
    if ($order === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

    // Определяем реальное имя таблицы (roll_plans или roll_plan)
    $tbl = null;
    $q = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('roll_plans','roll_plan')");
    $q->execute();
    $row = $q->fetch();
    if ($row) { $tbl = $row['TABLE_NAME']; }
    if (!$tbl) {
        // если нет ни одной — создадим roll_plans (как в странице)
        $tbl = 'roll_plans';
        $pdo->exec("CREATE TABLE IF NOT EXISTS roll_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) NOT NULL,
            bale_id INT NOT NULL,
            work_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_order_bale (order_number, bale_id),
            KEY idx_date (work_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $st = $pdo->prepare("SELECT work_date, bale_id FROM `$tbl` WHERE order_number=?");
    $st->execute([$order]);
    $rows = $st->fetchAll();

    if (!$rows) { echo json_encode(['ok'=>true,'exists'=>false,'plan'=>[]]); exit; }

    $plan = [];
    foreach ($rows as $r){
        $d = $r['work_date'];
        if (!isset($plan[$d])) $plan[$d] = [];
        $plan[$d][] = (int)$r['bale_id'];
    }

    echo json_encode(['ok'=>true,'exists'=>true,'plan'=>$plan]);
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
