<?php
// NP/save_roll_plan.php
header('Content-Type: text/plain; charset=utf-8');

try{
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 1) Выбираем рабочую таблицу: предпочитаем roll_plans, иначе roll_plan. Если нет ни одной — создаём roll_plans.
    $tbl = null;
    $stmt = $pdo->query("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('roll_plans','roll_plan')
        ORDER BY FIELD(TABLE_NAME,'roll_plans','roll_plan')  -- roll_plans приоритетнее
        LIMIT 1
    ");
    $row = $stmt->fetch();
    if ($row) {
        $tbl = $row['TABLE_NAME'];
    } else {
        $tbl = 'roll_plans';
        $pdo->exec("CREATE TABLE IF NOT EXISTS `roll_plans` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) NOT NULL,
            bale_id INT NOT NULL,
            work_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_order_bale (order_number, bale_id),
            KEY idx_date (work_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // 2) Получаем данные
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data || !isset($data['order']) || !isset($data['plan'])) {
        http_response_code(400); exit("bad payload");
    }
    $order = (string)$data['order'];
    $plan  = is_array($data['plan']) ? $data['plan'] : [];

    $pdo->beginTransaction();

    // 3) Чистим старый план этой заявки
    $del = $pdo->prepare("DELETE FROM `$tbl` WHERE order_number=?");
    $del->execute([$order]);

    // 4) Вставляем новый план
    $ins = $pdo->prepare("INSERT INTO `$tbl` (order_number, bale_id, work_date) VALUES (?,?,?)");
    $rows = 0;
    foreach ($plan as $date => $baleIds) {
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $date)) continue;   // защита формата даты
        if (!is_array($baleIds)) continue;
        foreach ($baleIds as $bid) {
            $bid = (int)$bid;
            if ($bid <= 0) continue;
            $ins->execute([$order, $bid, $date]);
            $rows++;
        }
    }

    // 5) Обновляем orders.plan_ready, НО только если таблица orders реально есть
    $hasOrders = (bool)$pdo->query("SELECT COUNT(*) c FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='orders'")->fetchColumn();

    if ($hasOrders) {
        // Добавим колонку plan_ready при необходимости
        $hasPlanReadyCol = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='plan_ready'")->fetchColumn();
        if (!$hasPlanReadyCol) {
            $pdo->exec("ALTER TABLE orders ADD plan_ready TINYINT(1) NOT NULL DEFAULT 0");
        }

        $upd = $pdo->prepare("UPDATE orders SET plan_ready=? WHERE order_number=?");
        $upd->execute([$rows > 0 ? 1 : 0, $order]);
    }
    // Если таблицы orders нет — просто пропускаем этот шаг (не ломаем сохранение)

    $pdo->commit();
    echo "ok";
}
catch (PDOException $e){
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    // более развёрнутый ответ, поможет понять первопричину
    echo "error: [SQLSTATE {$e->getCode()}] ".$e->getMessage();
}
catch (Throwable $e){
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo "error: ".$e->getMessage();
}
