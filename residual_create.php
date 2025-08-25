<?php
// residual_create.php — создать/пополнить заявку «Остатки» (OSTATKI-YYYY-MM) позициями AF из каталога
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');
ob_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }

    // === DB connect (подставь свои доступы при необходимости)
    $dsn  = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
    $user = "root";
    $pass = "";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // === Вход
    $workshop = trim($_POST['workshop'] ?? '');
    if ($workshop === '') throw new Exception('Не указан цех');

    // Номер «Остатков» на текущий месяц
    $order_number = 'OSTATKI-' . date('Y-m');

    // Вставляем позиции из каталога, где filter LIKE '%AF%', но только те, которых еще нет в этой заявке
    $sql = "
        INSERT INTO orders (
            order_number, workshop, filter, count,
            marking, personal_packaging, personal_label,
            group_packaging, packaging_rate, group_label,
            remark, hide, cut_ready, cut_confirmed, plan_ready, corr_ready, build_ready
        )
        SELECT
            :ord, :wrk, s.filter, 0,
            '', '', '',
            '', 0, '',
            'Остатки: автосоздание из каталога AF', 0, 0, 0, 0, 0, 0
        FROM salon_filter_structure s
        WHERE s.filter LIKE '%AF%'
          AND NOT EXISTS (
              SELECT 1
              FROM orders o
              WHERE o.order_number = :ord2
                AND o.filter = s.filter
          )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ord'  => $order_number,
        ':wrk'  => $workshop,
        ':ord2' => $order_number,
    ]);

    $inserted = $stmt->rowCount(); // сколько позиций добавили сейчас

    ob_clean();
    echo json_encode([
        'ok' => true,
        'order_number' => $order_number,
        'workshop' => $workshop,
        'added' => $inserted,               // новые позиции
        'message' => $inserted > 0
            ? "Добавлено позиций: $inserted"
            : "Все позиции AF уже есть в заявке"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    $buf = trim(ob_get_contents() ?: '');
    ob_clean();
    echo json_encode([
        'ok'=>false,
        'error'=>$e->getMessage(),
        'debug_extra'=>$buf?:null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
