<?php
// view_production_plan_light.php — лёгкая печатная версия плана сборки (только план, с агрегацией одинаковых позиций)

$pdo = new PDO(
    "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$order = $_GET['order'] ?? '';
$autoPrint = isset($_GET['print']);

if ($order === '') {
    die('Не указан номер заявки (?order=...).');
}

// Грузим строки плана сборки
$stmt = $pdo->prepare("
    SELECT plan_date,
           filter,
           TRIM(SUBSTRING_INDEX(filter,' [',1)) AS base_filter,
           `count`
    FROM build_plan
    WHERE order_number = ?
    ORDER BY plan_date, base_filter
");
$stmt->execute([$order]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
  Группируем по датам и АГРЕГИРУЕМ одинаковые позиции:
  — base_filter «чистим» от спец. символов (как в полной версии)
  — по ключу base_filter внутри даты суммируем count
*/
$byDate = []; // [date] => [ ['base'=>..., 'label'=>..., 'count'=>int], ... ]

foreach ($rows as $r) {
    $d = $r['plan_date'];
    if (!isset($byDate[$d])) $byDate[$d] = [];

    // очистка названия
    $base = preg_replace('/[●◩⏃]/u', '', $r['base_filter']);
    $base = trim($base);

    // агрегируем
    if (!isset($byDate[$d][$base])) {
        $byDate[$d][$base] = [
            'base'  => $base,
            'label' => $r['filter'], // для title; не критично какой, берём первый попавшийся
            'count' => 0
        ];
    }
    $byDate[$d][$base]['count'] += (int)$r['count'];
}

/* Преобразуем ассоц. массивы в списки и отсортируем по имени фильтра (натуральная сортировка). */
foreach ($byDate as $d => $map) {
    usort($map, function($a, $b){
        return strnatcasecmp($a['base'], $b['base']);
    });
    $byDate[$d] = $map;
}
ksort($byDate);

// утилиты
function dayTotal(array $arr){ $s=0; foreach($arr as $x){ $s += (int)$x['count']; } return $s; }
function ruDow(string $ymd){
    $ts = strtotime($ymd);
    $dows = ['вс','пн','вт','ср','чт','пт','сб'];
    return $dows[(int)date('w',$ts)];
}
$grand = array_sum(array_map('dayTotal', $byDate));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>План сборки (лайт) — заявка <?= htmlspecialchars($order) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{ --bg:#f6f7fb; --card:#fff; --line:#e5e7eb; --muted:#6b7280; --ink:#111827; }
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);margin:0;padding:10px;color:var(--ink);font-size:12px}
        h1{margin:4px 0 10px;text-align:center;font-size:16px}
        .panel{max-width:1400px;margin:0 auto 8px;display:flex;gap:8px;justify-content:center;align-items:center}
        .btn{padding:6px 10px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer;font-size:12px}
        .muted{color:var(--muted)}
        .wrap{max-width:1400px;margin:0 auto}
        .grid{
            display:grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap:8px;
            align-items:start;
        }
        .card{
            background:#fff;border:1px solid var(--line);border-radius:8px;padding:6px;
        }
        .header{
            display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;
            font-variant-numeric:tabular-nums;
        }
        .dow{font-size:11px;color:var(--muted);margin-left:6px}

        table{width:100%;border-collapse:collapse;font-size:11px;table-layout:fixed}
        colgroup col:first-child{width:70%}
        colgroup col:last-child{width:30%}
        th,td{border:1px solid var(--line);padding:3px 5px;text-align:center;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        th{background:#f3f4f6;font-weight:600}
        td.left{text-align:left}
        tbody tr:nth-child(even){background:#fbfbfb}

        @media print{
            @page { size: landscape; margin: 8mm; }
            body{background:#fff;padding:0}
            .panel{display:none}
            .grid{gap:6px;grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));}
            .card{break-inside:avoid}
            th,td{ -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
    <?php if ($autoPrint): ?>
        <script>window.addEventListener('load',()=>window.print());</script>
    <?php endif; ?>
</head>
<body>

<h1>План сборки (лайт) — заявка № <?= htmlspecialchars($order) ?></h1>

<div class="panel">
    <button class="btn" onclick="window.print()">Печать</button>
    <span class="muted">
    Дней: <?= count($byDate) ?> • Всего по плану: <?= (int)$grand ?> шт.
  </span>
</div>

<div class="wrap">
    <?php if (!$byDate): ?>
        <div class="card"><em class="muted">Нет строк плана сборки для этой заявки.</em></div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($byDate as $d => $items): ?>
                <div class="card">
                    <div class="header">
                        <strong><?= htmlspecialchars($d) ?><span class="dow"> / <?= ruDow($d) ?></span></strong>
                        <span class="muted">Итого: <b><?= (int)dayTotal($items) ?></b> шт.</span>
                    </div>
                    <table>
                        <colgroup><col><col></colgroup>
                        <thead>
                        <tr>
                            <th class="left">Фильтр</th>
                            <th>Кол-во</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td class="left" title="<?= htmlspecialchars($it['label']) ?>"><?= htmlspecialchars($it['base']) ?></td>
                                <td><?= (int)$it['count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
