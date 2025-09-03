<?php
header('Content-Type: application/json; charset=utf-8');
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
]);
$date = $_GET['date'] ?? date('Y-m-d');
$hideDone = isset($_GET['hideDone']) && $_GET['hideDone']=='1';

// --- Порезка (roll_plan) ---
$cutTotals = $pdo->prepare("
  SELECT 
    COUNT(*) AS plan_total,
    SUM(done=1) AS done_total
  FROM roll_plan
  WHERE plan_date = ?
");
$cutTotals->execute([$date]);
$cutKpi = $cutTotals->fetch(PDO::FETCH_ASSOC) ?: ['plan_total'=>0,'done_total'=>0];

// по заявкам: сколько бухт всего и сколько готово
$cutByOrder = $pdo->prepare("
  SELECT order_number,
         COUNT(*) AS plan_bales,
         SUM(done=1) AS done_bales
  FROM roll_plan
  WHERE plan_date = ?
  GROUP BY order_number
  ORDER BY order_number
");
$cutByOrder->execute([$date]);
$cutRows = $cutByOrder->fetchAll(PDO::FETCH_ASSOC);
if ($hideDone) {
    $cutRows = array_values(array_filter($cutRows, fn($r)=> (int)$r['plan_bales'] > (int)$r['done_bales']));
}

// --- Гофрирование (corrugation_plan) ---
// Предполагаем: добавили fact_count (INT) и status (TINYINT) ранее
$corrTotals = $pdo->prepare("
  SELECT 
    COALESCE(SUM(`count`),0) AS plan_total,
    COALESCE(SUM(fact_count),0) AS fact_total
  FROM corrugation_plan
  WHERE plan_date = ?
");
$corrTotals->execute([$date]);
$corrKpi = $corrTotals->fetch(PDO::FETCH_ASSOC) ?: ['plan_total'=>0,'fact_total'=>0];

// по заявкам
$corrByOrder = $pdo->prepare("
  SELECT order_number,
         COALESCE(SUM(`count`),0) AS plan_count,
         COALESCE(SUM(fact_count),0) AS fact_count
  FROM corrugation_plan
  WHERE plan_date = ?
  GROUP BY order_number
  ORDER BY order_number
");
$corrByOrder->execute([$date]);
$corrRows = $corrByOrder->fetchAll(PDO::FETCH_ASSOC);
if ($hideDone) {
    $corrRows = array_values(array_filter($corrRows, fn($r)=> (int)$r['plan_count'] > (int)$r['fact_count']));
}

echo json_encode([
    'date' => $date,
    'cut'  => [
        'kpi'  => [
            'plan' => (int)$cutKpi['plan_total'],
            'done' => (int)$cutKpi['done_total'],
        ],
        'byOrder' => array_map(function($r){
            $plan=(int)$r['plan_bales']; $done=(int)$r['done_bales'];
            return [
                'order' => $r['order_number'],
                'plan' => $plan,
                'done' => $done,
                'left' => max(0,$plan-$done),
            ];
        }, $cutRows),
    ],
    'corr' => [
        'kpi'  => [
            'plan' => (int)$corrKpi['plan_total'],
            'fact' => (int)$corrKpi['fact_total'],
        ],
        'byOrder' => array_map(function($r){
            $plan=(int)$r['plan_count']; $fact=(int)$r['fact_count'];
            return [
                'order' => $r['order_number'],
                'plan'  => $plan,
                'fact'  => $fact,
                'left'  => max(0,$plan-$fact),
            ];
        }, $corrRows),
    ],
], JSON_UNESCAPED_UNICODE);
