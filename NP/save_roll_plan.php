<?php
// NP/save_roll_plan.php — сохраняет план раскроя в roll_plans
// Ожидает JSON: { order: "20-23-25", plan: { "YYYY-MM-DD": [1,2,3], ... } }
// Возвращает: текст "ok" либо "error: ..."

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Таблица назначений (множественное число!)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roll_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) NOT NULL,
            bale_id INT NOT NULL,
            work_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_order_bale (order_number, bale_id),
            KEY idx_date (work_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Столбец orders.plan_ready — на всякий случай
    $hasPlanReady = $pdo->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='plan_ready'
    ")->fetchColumn();
    if (!$hasPlanReady) {
        $pdo->exec("ALTER TABLE orders ADD plan_ready TINYINT(1) NOT NULL DEFAULT 0");
    }

    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) { http_response_code(400); exit("error: empty body"); }

    $data = json_decode($raw, true);
    if (!is_array($data)) { http_response_code(400); exit("error: bad json"); }

    if (!isset($data['order']) || !isset($data['plan']) || !is_array($data['plan'])) {
        http_response_code(400); exit("error: bad payload");
    }

    $order = trim((string)$data['order']);
    if ($order === '') { http_response_code(400); exit("error: empty order"); }

    $plan = $data['plan'];

    // Нормализуем: для каждой бухты оставляем ОДНУ дату (последняя побеждает)
    // Это защищает от дублей и падения на UNIQUE (order_number, bale_id)
    $assign = []; // bale_id => work_date
    foreach ($plan as $date => $ids) {
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $date)) continue;
        if (!is_array($ids)) continue;
        foreach ($ids as $bid) {
            $bid = (int)$bid;
            if ($bid <= 0) continue;
            $assign[$bid] = $date;  // последняя запись перезапишет прежнюю
        }
    }

    $pdo->beginTransaction();

    // Сохраняем информацию о done для существующих бухт
    $existingDone = [];
    $stmt = $pdo->prepare("SELECT bale_id, done FROM roll_plans WHERE order_number=?");
    $stmt->execute([$order]);
    while ($row = $stmt->fetch()) {
        if (!empty($row['done'])) {
            $existingDone[(int)$row['bale_id']] = (int)$row['done'];
        }
    }

    // Удаляем прежние назначения этой заявки
    $pdo->prepare("DELETE FROM roll_plans WHERE order_number=?")->execute([$order]);

    // Вставляем новые с сохранением done
    $ins = $pdo->prepare("INSERT INTO roll_plans (order_number, bale_id, work_date, done) VALUES (?,?,?,?)");
    $rows = 0;
    foreach ($assign as $bid => $date) {
        $doneValue = isset($existingDone[$bid]) ? $existingDone[$bid] : 0;
        $ins->execute([$order, $bid, $date, $doneValue]);
        $rows++;
    }

    // Флаг готовности
    $pdo->prepare("UPDATE orders SET plan_ready=? WHERE order_number=?")->execute([$rows > 0 ? 1 : 0, $order]);

    $pdo->commit();
    echo "ok";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    // вернём текст ошибки — фронт его покажет
    http_response_code(500);
    echo "error: " . $e->getMessage();
}
