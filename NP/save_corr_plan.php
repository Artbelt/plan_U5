<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $order = $_POST['order'] ?? '';
    if (!$order) {
        http_response_code(400);
        echo "NO ORDER";
        exit;
    }

    $stmt = $pdo->prepare("UPDATE orders SET corr_ready = 1 WHERE order_number = ?");
    $stmt->execute([$order]);

    header("Location: NP_cut_index.php");
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
}
