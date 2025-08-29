<?php
header('Content-Type: application/json');
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Нет ID']);
    exit;
}

$id = (int)$_POST['id'];
$stmt = $pdo->prepare("UPDATE roll_plan SET done = 1 WHERE id = ?");
$success = $stmt->execute([$id]);

echo json_encode(['success' => $success]);
