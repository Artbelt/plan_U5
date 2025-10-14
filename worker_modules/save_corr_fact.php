<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!isset($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Нет ID']); exit;
    }
    $id = (int)$_POST['id'];

    if (isset($_POST['fact'])) {
        $fact = max(0, (int)$_POST['fact']);
        $stmt = $pdo->prepare("UPDATE corrugation_plan SET fact_count = ? WHERE id = ?");
        $stmt->execute([$fact, $id]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
