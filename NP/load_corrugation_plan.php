<?php
// NP/load_corrugation_plan.php
header('Content-Type: application/json; charset=utf-8');

try{
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $order = $_GET['order'] ?? '';
    if ($order==='') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

    $pdo->exec("CREATE TABLE IF NOT EXISTS corrugation_plan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(50) NOT NULL,
        bale_id INT NOT NULL,
        work_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_order_bale (order_number, bale_id),
        KEY idx_date (work_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $plan = [];
    $st = $pdo->prepare("SELECT work_date, bale_id FROM corrugation_plan WHERE order_number=?");
    $st->execute([$order]);
    while ($r = $st->fetch()) {
        $d = $r['work_date'];
        if (!isset($plan[$d])) $plan[$d] = [];
        $plan[$d][] = (int)$r['bale_id'];
    }

    echo json_encode(['ok'=>true,'plan'=>$plan]);
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
