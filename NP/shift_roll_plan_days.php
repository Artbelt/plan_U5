<?php
// NP/shift_roll_plan_days.php
// Зсуває всі work_date у roll_plans для order_number, починаючи з start_date, на delta РОБОЧИХ днів (пн–пт)

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Kyiv');

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $raw = file_get_contents('php://input');
    $in  = json_decode($raw, true);
    if (!is_array($in)) {
        throw new Exception('Bad JSON');
    }

    $order = trim($in['order'] ?? '');
    $start = trim($in['start_date'] ?? '');
    $delta = (int)($in['delta'] ?? 0);

    if ($order === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        throw new Exception('order/start_date required');
    }
    if ($delta === 0) {
        echo json_encode(['ok' => true, 'affected' => 0]); exit;
    }

    // Хелпер: додати/відняти РОБОЧІ дні (skip сб/нд)
    $addBusinessDays = function (string $dateISO, int $bdays): string {
        $d = new DateTime($dateISO);
        if ($bdays > 0) {
            for ($i = 0; $i < $bdays; ) {
                $d->modify('+1 day');
                $n = (int)$d->format('N'); // 1..7 (1=Mon, 7=Sun)
                if ($n <= 5) { $i++; }
            }
        } elseif ($bdays < 0) {
            for ($i = 0; $i > $bdays; ) {
                $d->modify('-1 day');
                $n = (int)$d->format('N');
                if ($n <= 5) { $i--; }
            }
        }
        return $d->format('Y-m-d');
    };

    // Вибираємо всі записи, які підлягають зсуву
    $sel = $pdo->prepare("
        SELECT id, work_date
        FROM roll_plans
        WHERE order_number = ? AND work_date >= ?
        ORDER BY work_date ASC, id ASC
    ");
    $sel->execute([$order, $start]);
    $rows = $sel->fetchAll();

    if (!$rows) {
        echo json_encode(['ok' => true, 'affected' => 0, 'note' => 'nothing to shift']); exit;
    }

    $upd = $pdo->prepare("UPDATE roll_plans SET work_date = ? WHERE id = ?");

    $pdo->beginTransaction();
    $affected = 0;

    foreach ($rows as $r) {
        $newDate = $addBusinessDays($r['work_date'], $delta);
        if ($newDate !== $r['work_date']) {
            $upd->execute([$newDate, $r['id']]);
            $affected += $upd->rowCount();
        }
    }

    $pdo->commit();

    echo json_encode([
        'ok'       => true,
        'affected' => $affected,
        'order'    => $order,
        'start'    => $start,
        'delta'    => $delta,
        'mode'     => 'business-days'
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
