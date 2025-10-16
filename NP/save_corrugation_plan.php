<?php
// NP/save_corrugation_plan.php
header('Content-Type: application/json; charset=utf-8');
// Не даём ворнингам/нотисам ломать JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

try{
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4","root","",[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);

    // Создать таблицу, если её нет (из вашего дампа)
    $pdo->exec("CREATE TABLE IF NOT EXISTS corrugation_plan (
      id INT(11) NOT NULL AUTO_INCREMENT,
      order_number VARCHAR(50) DEFAULT NULL,
      plan_date DATE DEFAULT NULL,
      filter TEXT DEFAULT NULL,
      count INT(11) DEFAULT NULL,
      fact_count INT(11) NOT NULL DEFAULT 0,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Утилита: проверить, есть ли столбец
    $colExists = function(string $col){
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='corrugation_plan' AND COLUMN_NAME=?");
        $st->execute([$col]); return (bool)$st->fetchColumn();
    };
} catch (Throwable $e) {
    // из-за замыкания проще без $colExists в виде closure — перепишем чуть ниже
}
try{
    // Проверка столбцов безопаснее без замыкания:
    $hasCol = function(PDO $pdo, string $col): bool {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='corrugation_plan' AND COLUMN_NAME=?");
        $st->execute([$col]); return (bool)$st->fetchColumn();
    };

    // Определяем реальное поле для названия фильтра: filter или filter_label
    $filterCol = null;
    if ($hasCol($pdo,'filter'))        $filterCol = 'filter';
    elseif ($hasCol($pdo,'filter_label')) $filterCol = 'filter_label';
    else {
        // если нет ни того, ни другого — добавим filter
        $pdo->exec("ALTER TABLE corrugation_plan ADD `filter` TEXT NULL");
        $filterCol = 'filter';
    }

    // Добавим bale_id / strip_no при отсутствии
    if (!$hasCol($pdo, 'bale_id'))  $pdo->exec("ALTER TABLE corrugation_plan ADD bale_id INT NULL");
    if (!$hasCol($pdo, 'strip_no')) $pdo->exec("ALTER TABLE corrugation_plan ADD strip_no INT NULL");

    // Уникальный индекс (если его ещё нет)
    $st = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='corrugation_plan' AND INDEX_NAME='cp_uq_order_bale_strip'");
    if (!$st->fetchColumn()) {
        // NULL в unique индексе допустим и не конфликтует
        $pdo->exec("CREATE UNIQUE INDEX cp_uq_order_bale_strip ON corrugation_plan(order_number, bale_id, strip_no)");
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        // ===== ЗАГРУЗКА =====
        $order = $_GET['order'] ?? '';
        if ($order === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

        $q = $pdo->prepare("SELECT plan_date, bale_id, strip_no, {$filterCol} AS filter, count
                            FROM corrugation_plan
                            WHERE order_number=?
                            ORDER BY plan_date, bale_id, strip_no");
        $q->execute([$order]);
        $rows = $q->fetchAll();

        $days = [];
        $items = [];
        foreach($rows as $r){
            $ds = $r['plan_date'];
            if ($ds) $days[$ds] = true;
            $items[] = [
                'date'     => $ds,
                'bale_id'  => (int)$r['bale_id'],
                'strip_no' => (int)$r['strip_no'],
                'filter'   => (string)$r['filter'],
                'count'    => (int)$r['count'],
            ];
        }
        echo json_encode(['ok'=>true, 'days'=>array_keys($days), 'items'=>$items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ===== СОХРАНЕНИЕ (POST JSON) =====
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data || !isset($data['order']) || !isset($data['items']) || !is_array($data['items'])) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad payload']); exit;
    }

    $order = (string)$data['order'];
    $items = $data['items'];

    $pdo->beginTransaction();

    // Сохраняем информацию о fact_count для существующих записей
    $existingFacts = [];
    $stmt = $pdo->prepare("SELECT bale_id, strip_no, fact_count FROM corrugation_plan WHERE order_number=?");
    $stmt->execute([$order]);
    while ($row = $stmt->fetch()) {
        $key = $row['bale_id'] . ':' . $row['strip_no'];
        if ($row['fact_count'] > 0) {
            $existingFacts[$key] = (int)$row['fact_count'];
        }
    }

    // Полное пересохранение плана по заявке
    $del = $pdo->prepare("DELETE FROM corrugation_plan WHERE order_number=?");
    $del->execute([$order]);

    $sql = "INSERT INTO corrugation_plan
        (order_number, plan_date, {$filterCol}, count, bale_id, strip_no, fact_count)
        VALUES (?,?,?,?,?,?,?)";
    $ins = $pdo->prepare($sql);

    foreach ($items as $it){
        $ds = $it['date'] ?? null;
        $bid = isset($it['bale_id'])  ? (int)$it['bale_id']  : null;
        $sn  = isset($it['strip_no']) ? (int)$it['strip_no'] : null;
        $flt = (string)($it['filter'] ?? '');
        $cnt = (int)($it['count'] ?? 0);

        if (!$ds || !$bid || !$sn) continue;
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $ds)) continue;

        // Восстанавливаем fact_count если он был
        $key = $bid . ':' . $sn;
        $factCount = isset($existingFacts[$key]) ? $existingFacts[$key] : 0;

        $ins->execute([$order, $ds, $flt, $cnt, $bid, $sn, $factCount]);
    }

    // Обновляем статус готовности плана гофрирования
    $stmt = $pdo->prepare("UPDATE orders SET corr_ready = 1 WHERE order_number = ?");
    $stmt->execute([$order]);

    $pdo->commit();
    echo json_encode(['ok'=>true]);
}catch(Throwable $e){
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
