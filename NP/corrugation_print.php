<?php
/* corrugation_print.php — компактный печатный план гофрирования (неделя/месяц/период, 2 колонки)
   Таблица: corrugation_plan(id, order_number, plan_date(Y-m-d), filter_label, count, fact_count)
*/
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);

$mode  = $_GET['mode'] ?? 'week'; // week | month | range
$today = new DateTimeImmutable(date('Y-m-d'));

if ($mode === 'month') {
    $ym = $_GET['month'] ?? $today->format('Y-m');
    try { $start = new DateTimeImmutable($ym.'-01'); } catch(Exception $e){ $start = new DateTimeImmutable($today->format('Y-m-01')); }
    $end   = $start->modify('last day of this month');
    $title = 'План гофрирования — месяц: '.$start->format('m.Y');
    $prevParam = $start->modify('-1 month')->format('Y-m');
    $nextParam = $start->modify('+1 month')->format('Y-m');
} elseif ($mode === 'range') {
    $sIn = $_GET['start'] ?? $today->format('Y-m-d');
    $eIn = $_GET['end']   ?? $today->modify('+6 days')->format('Y-m-d');
    try { $sObj = new DateTimeImmutable($sIn); } catch(Exception $e){ $sObj = $today; }
    try { $eObj = new DateTimeImmutable($eIn); } catch(Exception $e){ $eObj = $today->modify('+6 days'); }
    if ($eObj < $sObj) { [$sObj, $eObj] = [$eObj, $sObj]; }
    $maxDays = 120;
    $len = $sObj->diff($eObj)->days + 1;
    if ($len > $maxDays) { $eObj = $sObj->modify('+'.($maxDays-1).' days'); $len = $maxDays; }
    $start = $sObj; $end = $eObj;
    $title = 'План гофрирования — период: '.$start->format('d.m.Y').'–'.$end->format('d.m.Y');
    $prevStart = $start->modify('-'.$len.' days')->format('Y-m-d');
    $prevEnd   = $end->modify('-'.$len.' days')->format('Y-m-d');
    $nextStart = $start->modify('+'.$len.' days')->format('Y-m-d');
    $nextEnd   = $end->modify('+'.$len.' days')->format('Y-m-d');
} else { // week
    $refDateStr = $_GET['date'] ?? $today->format('Y-m-d');
    try { $ref = new DateTimeImmutable($refDateStr); } catch(Exception $e){ $ref = $today; }
    $dow   = (int)$ref->format('N'); // 1..7 (Пн..Вс)
    $start = $ref->modify('-'.($dow-1).' days');
    $end   = $start->modify('+6 days');
    $title = 'План гофрирования — неделя: '.$start->format('d.m.Y').'–'.$end->format('d.m.Y');
    $prevParam = $start->modify('-7 days')->format('Y-m-d');
    $nextParam = $start->modify('+7 days')->format('Y-m-d');
}

/* Агрегация по дню + заявке + фильтру */
$qs = $pdo->prepare("
    SELECT plan_date, order_number, filter_label,
           SUM(`count`)    AS plan_sum,
           SUM(fact_count) AS fact_sum
    FROM corrugation_plan
    WHERE plan_date BETWEEN ? AND ?
    GROUP BY plan_date, order_number, filter_label
    ORDER BY plan_date, order_number, filter_label
");
$qs->execute([$start->format('Y-m-d'), $end->format('Y-m-d')]);
$rows = $qs->fetchAll();

/* Регистр всех дней в периоде */
$days = [];
for ($d=$start; $d <= $end; $d=$d->modify('+1 day')) {
    $k = $d->format('Y-m-d');
    $days[$k] = ['date'=>$d, 'items'=>[], 'total_plan'=>0];
}
foreach ($rows as $r) {
    $k = $r['plan_date'];
    if (!isset($days[$k])) continue;
    $days[$k]['items'][] = $r;
    $days[$k]['total_plan'] += (int)$r['plan_sum'];
}

/* Хелпер для ссылок */
function linkMode(string $mode, array $params): string {
    $q = http_build_query(array_merge(['mode'=>$mode], $params));
    return '?'.$q;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        :root{
            --ink:#111827; --muted:#6b7280; --line:#d1d5db; --bg:#f8fafc;
            --fs-body: 12px;  /* базовый кегль */
            --fs-h:    13px;  /* заголовок дня */
            --pad:     6px;   /* внутренние отступы */
            --gap:     8px;   /* промежуток между плитками */
            --bdr:     8px;   /* скругления */
        }
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:var(--ink);background:var(--bg);margin:0;padding:10px;font-size:var(--fs-body)}
        h1{font-size:14px;margin:0 0 10px;text-align:center}
        .toolbar{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;align-items:center;margin:0 auto 8px;max-width:1100px}
        .seg{display:inline-flex;border:1px solid var(--line);border-radius:10px;overflow:hidden}
        .seg a{padding:6px 10px;text-decoration:none;color:var(--ink);background:#fff;border-right:1px solid var(--line);font-size:12px}
        .seg a:last-child{border-right:none}
        .seg a.active{background:#eef2ff;font-weight:600}
        .ctrl, .ctrl input, .ctrl a, .ctrl button{
            border:1px solid var(--line);background:#fff;padding:6px 10px;border-radius:10px;text-decoration:none;color:var(--ink);font-size:12px
        }
        .ctrl input[type="date"], .ctrl input[type="month"]{padding:5px 8px}
        .ctrl button{cursor:pointer}

        /* --- Компактные «плитки» по 2 в ряд --- */
        .grid{
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr)); /* 2 колонки */
            gap: var(--gap);
            align-items: start;
        }
        .day{
            background:#fff;border:1px solid var(--line);border-radius:var(--bdr);
            padding: var(--pad);
            page-break-inside: avoid; /* держать день целиком */
        }
        .day h2{
            font-size: var(--fs-h);
            margin: 0 0 4px;
            display:flex;justify-content:space-between;align-items:baseline;color:var(--muted)
        }
        .day .date{font-weight:700;color:var(--ink)}
        .day .dow{font-size:11px}

        table{width:100%;border-collapse:collapse;font-size:11px}
        th,td{border:1px solid var(--line);padding:4px 6px;text-align:center}
        thead th{background:#f3f4f6}
        tfoot td{font-weight:700;background:#f9fafb}

        /* печать — максимально уплотняем, ландшафт, минимальные поля */
        @media print{
            @page{ margin: 6mm; }   /* без size! */
        }

    </style>
    <script>
        function setParamAndReload(obj){
            const url = new URL(window.location.href);
            Object.entries(obj).forEach(([k,v])=>{
                if (v===null) url.searchParams.delete(k);
                else url.searchParams.set(k, v);
            });
            window.location.href = url.toString();
        }
        function onDateChangeWeek(e){ setParamAndReload({mode:'week', date:e.target.value}); }
        function onMonthChange(e){ setParamAndReload({mode:'month', month:e.target.value}); }
        function onRangeChange(){
            const s = document.getElementById('range-start').value;
            const e = document.getElementById('range-end').value;
            if (!s || !e) return;
            if (new Date(s) > new Date(e)) {
                setParamAndReload({mode:'range', start:e, end:s});
            } else {
                setParamAndReload({mode:'range', start:s, end:e});
            }
        }
    </script>
</head>
<body>

<h1><?= htmlspecialchars($title) ?></h1>

<div class="toolbar">
    <div class="seg">
        <a href="<?= htmlspecialchars(linkMode('week',  ['date'=> ($mode==='week' ? $start->format('Y-m-d') : $today->format('Y-m-d')) ])) ?>"  class="<?= $mode==='week'  ? 'active' : '' ?>">Неделя</a>
        <a href="<?= htmlspecialchars(linkMode('month', ['month'=> ($mode==='month'? $start->format('Y-m')   : $today->format('Y-m')) ])) ?>"   class="<?= $mode==='month' ? 'active' : '' ?>">Месяц</a>
        <a href="<?= htmlspecialchars(linkMode('range', ['start'=> ($mode==='range'? $start->format('Y-m-d') : $today->format('Y-m-d')),
            'end'  => ($mode==='range'? $end->format('Y-m-d')   : $today->modify('+6 days')->format('Y-m-d')) ])) ?>"
           class="<?= $mode==='range' ? 'active' : '' ?>">Период</a>
    </div>

    <?php if ($mode==='month'): ?>
        <a class="ctrl" href="<?= htmlspecialchars(linkMode('month', ['month'=>$prevParam])) ?>">⬅ Пред. месяц</a>
        <div class="ctrl"><input type="month" value="<?= htmlspecialchars($start->format('Y-m')) ?>" onchange="onMonthChange(event)"/></div>
        <a class="ctrl" href="<?= htmlspecialchars(linkMode('month', ['month'=>$nextParam])) ?>">След. месяц ➡</a>
        <a class="ctrl" href="<?= htmlspecialchars(linkMode('month', ['month'=> (new DateTimeImmutable('first day of this month'))->format('Y-m')])) ?>">Текущий месяц</a>
    <?php elseif ($mode==='range'): ?>
        <a class="ctrl" href="<?= htmlspecialchars(linkMode('range', ['start'=>$prevStart, 'end'=>$prevEnd])) ?>">⬅ Сдвинуть назад</a>
        <div class="ctrl">
            С&nbsp;<input id="range-start" type="date" value="<?= htmlspecialchars($start->format('Y-m-d')) ?>" onchange="onRangeChange()" />
            &nbsp;по&nbsp;<input id="range-end" type="date" value="<?= htmlspecialchars($end->format('Y-m-d')) ?>" onchange="onRangeChange()" />
        </div>
        <a class="ctrl" href="<?= htmlspecialchars(linkMode('range', ['start'=>$nextStart, 'end'=>$nextEnd])) ?>">Сдвинуть вперёд ➡</a>
        <a class="ctrl" href="<?= htmlspecialchars(linkMode('range', ['start'=>$today->format('Y-m-d'), 'end'=>$today->modify('+6 days')->format('Y-m-d')])) ?>">Неделя с сегодня</a>
    <?php else: ?>
        <a class="ctrl" href="<?= htmlspecialchars(linkMode('week', ['date'=>$prevParam])) ?>">⬅ Пред. неделя</a>
        <div class="ctrl"><input type="date" value="<?= htmlspecialchars($start->format('Y-m-d')) ?>" onchange="onDateChangeWeek(event)"/></div>
        <a class="ctrl" href="<?= htmlspecialchars(linkMode('week', ['date'=>$nextParam])) ?>">След. неделя ➡</a>
        <a class="ctrl" href="<?= htmlspecialchars(linkMode('week', ['date'=>$today->format('Y-m-d')])) ?>">Текущая неделя</a>
    <?php endif; ?>

    <button class="ctrl" onclick="window.print()">🖨 Печать</button>
</div>

<div class="grid">
    <?php
    $weekDays = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
    foreach ($days as $k => $dinfo):
        $dateObj = $dinfo['date'];
        $items   = $dinfo['items'];
        $dow     = $weekDays[(int)$dateObj->format('N')-1];
        $totalPlan = (int)$dinfo['total_plan'];
        ?>
        <section class="day">
            <h2>
                <span class="date"><?= $dateObj->format('d.m.Y') ?></span>
                <span class="dow"><?= $dow ?></span>
            </h2>
            <table>
                <thead>
                <tr>
                    <th style="width:16%">Заявка</th>
                    <th>Фильтр</th>
                    <th style="width:12%">План</th>
                    <th style="width:12%">Факт</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($items): ?>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['order_number']) ?></td>
                            <td style="text-align:left"><?= htmlspecialchars($it['filter_label']) ?></td>
                            <td><?= (int)$it['plan_sum'] ?></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;color:#6b7280">Нет заданий</td></tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right">Итого:</td>
                    <td><?= $totalPlan ?></td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </section>
    <?php endforeach; ?>
</div>

</body>
</html>
