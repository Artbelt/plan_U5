<?php
// NP_view_corrugation_plan.php — просмотр/печать плана гофрирования по дням
// GET: ?order=XXXX

$dsn = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root"; $pass = "";

$order = $_GET['order'] ?? '';
if ($order === '') { http_response_code(400); exit('Укажите ?order=...'); }

try{
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);

    // Достаём план по заявке
    $st = $pdo->prepare("
        SELECT id, plan_date, filter_label, `count`, fact_count
        FROM corrugation_plan
        WHERE order_number = ?
        ORDER BY plan_date, filter_label
    ");
    $st->execute([$order]);
    $rows = $st->fetchAll();

    // Группировка по дате
    $byDate = [];
    foreach($rows as $r){
        $d = $r['plan_date'] ?: '';
        if (!isset($byDate[$d])) $byDate[$d] = [];
        $byDate[$d][] = $r;
    }
    ksort($byDate);

    // агрегаты
    $grandPlan = 0; $grandFact = 0;

    // диапазон дат (мин/макс) — пригодится для заголовка
    $dates = array_keys($byDate);
    $minDate = $dates ? min($dates) : null;
    $maxDate = $dates ? max($dates) : null;

    function ruDow($isoDate){
        if (!$isoDate) return '';
        $ts = strtotime($isoDate);
        $d = (int)date('w',$ts); // 0..6
        $names = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'];
        return $names[$d] ?? '';
    }

} catch(Throwable $e){
    http_response_code(500);
    echo "Ошибка БД: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Просмотр гофроплана — заявка <?=htmlspecialchars($order)?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --bg:#f6f7fb; --card:#fff; --text:#111827; --muted:#6b7280;
            --line:#e5e7eb; --ok:#16a34a; --warn:#ef4444; --accent:#2563eb;
        }
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);margin:16px;color:var(--text)}
        .wrap{max-width:1100px;margin:0 auto}
        h2{margin:0 0 6px}
        .sub{color:var(--muted); margin-bottom:10px}

        .toolbar{display:flex;gap:8px;align-items:center;margin:8px 0 14px}
        .btn{padding:8px 12px;border-radius:8px;background:var(--accent);color:#fff;border:1px solid var(--accent);cursor:pointer;text-decoration:none}
        .btn:hover{filter:brightness(.95)}
        .btn-ghost{padding:8px 12px;border-radius:8px;background:#eef2ff;color:#374151;border:1px solid #c7d2fe;text-decoration:none}
        .totals{margin:8px 0 18px;padding:10px;border:1px solid var(--line);border-radius:10px;background:#fff;display:flex;gap:18px;flex-wrap:wrap}

        .day-card{background:#fff;border:1px solid var(--line);border-radius:12px;margin:12px 0;overflow:hidden}
        .day-head{display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#f3f4f6;border-bottom:1px solid var(--line)}
        .day-title{font-weight:600}
        .day-sub{font-size:12px;color:var(--muted)}
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid var(--line);padding:8px 10px;text-align:left;font-size:14px}
        th{background:#fafafa}
        td.num, th.num{text-align:right}

        .done{color:var(--ok); font-weight:600}
        .warn{color:var(--warn); font-weight:600}

        @media print{
            @page{ size: A4 portrait; margin: 10mm; }
            body{background:#fff;margin:0}
            .toolbar{display:none}
            .wrap{max-width:none}
            .day-card{page-break-inside:avoid}
        }
    </style>
</head>
<body>
<div class="wrap">
    <h2>Просмотр гофроплана — заявка <?=htmlspecialchars($order)?></h2>
    <div class="sub">
        <?= $minDate && $maxDate
            ? 'Диапазон: <b>'.htmlspecialchars($minDate).'</b> — <b>'.htmlspecialchars($maxDate).'</b>'
            : 'Даты не заданы' ?>
    </div>

    <div class="toolbar">
        <a class="btn" href="#" onclick="window.print();return false;">Печать</a>
        <a class="btn-ghost" href="NP_cut_index.php">Назад к этапам</a>
    </div>

    <?php if (empty($byDate)): ?>
        <div class="day-card">
            <div class="day-head">
                <div class="day-title">Нет данных по плану</div>
            </div>
            <div style="padding:12px">Для этой заявки нет записей в <code>corrugation_plan</code>.</div>
        </div>
    <?php else: ?>

        <?php foreach($byDate as $date => $items): ?>
            <?php
            $sumPlan = 0; $sumFact = 0;
            foreach($items as $it){ $sumPlan += (int)$it['count']; $sumFact += (int)$it['fact_count']; }
            $grandPlan += $sumPlan; $grandFact += $sumFact;
            $remain = max(0, $sumPlan - $sumFact);
            ?>
            <div class="day-card">
                <div class="day-head">
                    <div class="day-title"><?=htmlspecialchars($date)?> <span class="day-sub">/ <?=ruDow($date)?></span></div>
                    <div class="day-sub">
                        План: <b><?=number_format($sumPlan,0,'.',' ')?></b> |
                        Факт: <b><?=number_format($sumFact,0,'.',' ')?></b> |
                        Осталось: <b><?=number_format($remain,0,'.',' ')?></b>
                    </div>
                </div>
                <table>
                    <tr>
                        <th style="width:40px">№</th>
                        <th>Фильтр</th>
                        <th class="num" style="width:110px">План, шт</th>
                        <th class="num" style="width:110px">Факт, шт</th>
                        <th style="width:140px">Статус</th>
                    </tr>
                    <?php foreach($items as $i=>$it): ?>
                        <?php
                        $pl = (int)$it['count'];
                        $fc = (int)$it['fact_count'];
                        $done = ($fc >= $pl && $pl > 0);
                        $statusTxt = $done ? 'Выполнено' : (($fc>0 && $fc<$pl) ? 'В работе' : 'Запланировано');
                        ?>
                        <tr>
                            <td><?=($i+1)?></td>
                            <td><?=htmlspecialchars($it['filter'] ?? '')?></td>
                            <td class="num"><?=number_format($pl,0,'.',' ')?></td>
                            <td class="num"><?=number_format($fc,0,'.',' ')?></td>
                            <td>
                                <?php if ($done): ?>
                                    <span class="done">✅ <?=$statusTxt?></span>
                                <?php elseif ($fc>0 && $fc<$pl): ?>
                                    <span class="warn">⏳ <?=$statusTxt?></span>
                                <?php else: ?>
                                    <span><?=$statusTxt?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th colspan="2" class="num">ИТОГО за день:</th>
                        <th class="num"><?=number_format($sumPlan,0,'.',' ')?></th>
                        <th class="num"><?=number_format($sumFact,0,'.',' ')?></th>
                        <th><?= $remain>0 ? ('Осталось: '.number_format($remain,0,'.',' ')) : 'Готово' ?></th>
                    </tr>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="totals">
            <div><b>Итого по заявке:</b></div>
            <div>План, шт: <b><?=number_format($grandPlan,0,'.',' ')?></b></div>
            <div>Факт, шт: <b><?=number_format($grandFact,0,'.',' ')?></b></div>
            <div>Осталось, шт: <b><?=number_format(max(0, $grandPlan-$grandFact),0,'.',' ')?></b></div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
