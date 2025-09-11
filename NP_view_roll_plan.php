<?php
// NP_view_roll_plan.php — компактный просмотр плана раскроя рулонов (3 колонки)
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
]);

$order = $_GET['order'] ?? '';
$autoPrint = isset($_GET['print']) ? true : false;
if ($order==='') die('Не указан параметр order.');

$sql = "
SELECT
  r.id, r.bale_id, r.work_date, r.done,
  c.filter, TRIM(SUBSTRING_INDEX(c.filter,' [',1)) AS base_filter,
  c.width, c.height, c.length
FROM roll_plans r
JOIN cut_plans c
  ON c.order_number = r.order_number
 AND c.bale_id = r.bale_id
WHERE r.order_number = :ord
ORDER BY r.work_date, CAST(r.bale_id AS UNSIGNED), base_filter
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':ord'=>$order]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bales = [];
foreach ($rows as $r) {
    $id = $r['id'];
    if (!isset($bales[$id])) {
        $bales[$id] = [
            'id' => $id,
            'bale_id' => $r['bale_id'],
            'plan_date' => $r['work_date'],
            'done' => (int)$r['done'],
            'filters' => [],
            'total_width' => 0.0,
            'length' => (int)$r['length'],
        ];
    }
    $bales[$id]['filters'][] = [
        'base'   => $r['base_filter'],
        'width'  => (float)$r['width'],
        'height' => (float)$r['height'],
        'length' => (int)$r['length'],
    ];
    $bales[$id]['total_width'] += (float)$r['width'];
}
uksort($bales, fn($a,$b)=>($bales[$a]['plan_date'] <=> $bales[$b]['plan_date']) ?: ($bales[$a]['bale_id'] <=> $bales[$b]['bale_id']));
$total_bales = count($bales);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>План раскроя рулона — № <?= htmlspecialchars($order) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{ --bg:#f6f7fb; --card:#fff; --line:#e5e7eb; --muted:#6b7280; --ok:#16a34a; --ink:#111827; }
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);margin:0;padding:8px;color:var(--ink);font-size:12px}
        h1{margin:4px 0 10px;text-align:center;font-size:16px}
        .panel{max-width:1300px;margin:0 auto 8px;display:flex;gap:8px;justify-content:center;align-items:center}
        .btn{padding:6px 10px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer;font-size:12px}
        .muted{color:var(--muted)}
        .grid{max-width:1300px;margin:0 auto;display:grid;grid-template-columns:repeat(3, 1fr);gap:8px}
        .card{background:#fff;border:1px solid var(--line);border-radius:8px;padding:6px}
        .hdr{display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:space-between;margin-bottom:4px}
        .hdr-left{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .tag{font-size:11px;padding:1px 6px;border-radius:999px;border:1px solid var(--line);background:#fff;white-space:nowrap}
        .ok{color:var(--ok);border-color:#c9f2d9;background:#f1f9f4}
        .mono{font-variant-numeric:tabular-nums;white-space:nowrap}
        table{width:100%;border-collapse:collapse;font-size:11px}
        th,td{border:1px solid var(--line);padding:3px 5px;text-align:center;vertical-align:middle}
        th{background:#f6f7fb;font-weight:600}
        td.left{text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        tbody tr:nth-child(even){background:#fbfbfb}
        @media(max-width:1100px){ .grid{grid-template-columns:repeat(2, 1fr)} }
        @media(max-width:700px){  .grid{grid-template-columns:1fr} }
        @media print{
            @page { size: landscape; margin: 8mm; }
            body{background:#fff;padding:0}
            .panel{display:none}
            .grid{gap:6px;grid-template-columns:repeat(3, 1fr)}
            .card{break-inside:avoid}
            th,td{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
    <?php if ($autoPrint): ?>
        <script>window.addEventListener('load',()=>window.print());</script>
    <?php endif; ?>
</head>
<body>

<h1>План раскроя рулона — заявка № <?= htmlspecialchars($order) ?></h1>

<div class="panel">
    <button class="btn" onclick="window.print()">Печать</button>
    <span class="muted">Бухт: <?= (int)$total_bales ?></span>
</div>

<div class="grid">
    <?php if (!$bales): ?>
        <div class="card"><em class="muted">Нет данных по раскрою рулонов для этой заявки.</em></div>
    <?php else: foreach ($bales as $b):
        $leftover = max(0, 1200 - $b['total_width']); // считаем, но НЕ выводим сумму ширин
        ?>
        <div class="card">
            <div class="hdr">
                <div class="hdr-left">
                    <strong class="mono"><?= htmlspecialchars($b['plan_date']) ?></strong>
                    <span>Бухта <strong><?= htmlspecialchars($b['bale_id']) ?></strong></span>
                    <!-- Убрали «∑ ширин ...», оставили только остаток и длину -->
                    <span class="muted">остаток: <strong class="mono"><?= (float)$leftover ?> мм</strong></span>
                    <span class="muted">длина: <strong class="mono"><?= (int)$b['length'] ?> м</strong></span>
                </div>
                <div class="tag <?= $b['done']?'ok':'' ?>"><?= $b['done']?'Готово':'Запланировано' ?></div>
            </div>

            <table>
                <thead>
                <tr>
                    <th class="left">Фильтр</th>
                    <th>Шир, мм</th>
                    <th>Выс, мм</th>
                    <th>Длина, м</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($b['filters'] as $f): ?>
                    <tr>
                        <td class="left" title="<?= htmlspecialchars($f['base']) ?>"><?= htmlspecialchars($f['base']) ?></td>
                        <td class="mono"><?= (float)$f['width'] ?></td>
                        <td class="mono"><?= (float)$f['height'] ?></td>
                        <td class="mono"><?= (int)$f['length'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; endif; ?>
</div>

</body>
</html>
