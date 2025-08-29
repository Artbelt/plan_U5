<?php
// NP_build_tasks.php — лист заданий бригадам по заявке

$dsn  = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root";
$pass = "";
const SHIFT_HOURS = 11.5;

$order = $_GET['order'] ?? '';
if ($order==='') { http_response_code(400); exit('Укажите ?order=...'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function dow($iso){
    $w = (int)date('w', strtotime($iso));
    return ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'][$w] ?? '';
}

try{
    $pdo = new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);

    // Собираем план по дням и бригадам, подтягиваем норму (build_complexity = шт/смену)
    $sql = "
        SELECT
            bp.plan_date,
            bp.brigade,
            bp.source_date,
            bp.filter,
            SUM(bp.count) AS qty,
            NULLIF(COALESCE(sfs.build_complexity,0),0) AS rate_per_shift
        FROM build_plan bp
        LEFT JOIN salon_filter_structure sfs ON sfs.filter = bp.filter
        WHERE bp.order_number = ?
        GROUP BY bp.plan_date, bp.brigade, bp.source_date, bp.filter
        ORDER BY bp.plan_date, bp.brigade, bp.source_date, bp.filter
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$order]);
    $rows = $st->fetchAll();

    if (!$rows){
        echo "<!doctype html><meta charset='utf-8'><body style='font:14px system-ui'>
              <p>По заявке <b>".h($order)."</b> нет заданий в build_plan.</p>
              <p><a href='NP_build_plan.php?order=".urlencode($order)."'>Перейти к плану сборки</a></p></body>";
        exit;
    }

    // Группируем: $byDay[YYYY-MM-DD]['1'][] и ['2'][]
    $byDay = [];
    foreach ($rows as $r){
        $d = $r['plan_date'];
        $b = (string)((int)$r['brigade'] ?: 1);
        if (!isset($byDay[$d])) $byDay[$d] = ['1'=>[], '2'=>[]];

        $rate = (int)$r['rate_per_shift'];
        $qty  = (int)$r['qty'];
        $hrs  = $rate>0 ? ($qty / $rate) * SHIFT_HOURS : 0.0;

        $byDay[$d][$b][] = [
            'source_date' => $r['source_date'],
            'filter'      => $r['filter'],
            'qty'         => $qty,
            'rate'        => $rate,                 // шт/смену
            'hours'       => $hrs
        ];
    }
    ksort($byDay);

} catch(Throwable $e){
    http_response_code(500); echo 'Ошибка: '.h($e->getMessage()); exit;
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Задание бригадам — заявка <?=h($order)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root{
        --line:#e5e7eb; --muted:#6b7280; --ink:#111; --bg:#f7f9fc; --card:#fff;
        --team1:#fff8d6; /* бледно-жёлтый фон для бригады 1 */
        --team2:#eaf2ff; /* бледно-синий фон для бригады 2 */
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);font:14px system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--ink)}
    .wrap{max-width:1200px;margin:0 auto;padding:16px}

    .topbar{display:flex;align-items:center;gap:10px;margin-bottom:12px}
    .print{border:1px solid #cbd5e1;background:#2563eb;color:#fff;border-radius:8px;padding:8px 12px;cursor:pointer}
    .muted{color:var(--muted);font-size:12px}

    .day-card{background:var(--card);border:1px solid var(--line);border-radius:12px;margin:10px 0;overflow:hidden}
    .day-hdr{display:flex;justify-content:space-between;align-items:baseline;padding:10px 12px;border-bottom:1px solid var(--line)}
    .day-date{font-weight:700}
    .day-sum{font-size:12px;color:#374151}

    .twocol{display:grid;grid-template-columns:1fr 1fr;gap:0;border-top:0}
    .team{padding:10px;border-right:1px solid var(--line)}
    .team:last-child{border-right:0}

    .team-1{background:var(--team1)}
    .team-2{background:var(--team2)}

    .team h4{margin:0 0 8px}
    table.tbl{width:100%;border-collapse:collapse;font-size:13px;background:#fff}
    .team-1 table.tbl{background:var(--team1)}
    .team-2 table.tbl{background:var(--team2)}
    .tbl th,.tbl td{border:1px solid #dfe3ea;padding:6px 8px;text-align:left}
    .tbl th{background:#f3f4f6;font-weight:600}
    .tot{font-weight:700}

    @media print{
        .topbar{display:none}
        body{background:#fff}
        .wrap{max-width:none;padding:0}
        .day-card{break-inside:avoid-page}
    }
</style>

<div class="wrap">
    <div class="topbar">
        <h2 style="margin:0">Задание бригадам — заявка <?=h($order)?></h2>
        <button class="print" onclick="window.print()">Печать</button>
        <div class="muted">Норма берётся из <code>salon_filter_structure.build_complexity</code> (шт/смену, смена <?=SHIFT_HOURS?> ч).</div>
    </div>

    <?php foreach ($byDay as $day=>$teams):
        // Подсчёт итогов по дню
        $sum1c=$sum2c=$sum1h=$sum2h=0.0;
        foreach ($teams['1'] as $r){ $sum1c += $r['qty']; $sum1h += $r['hours']; }
        foreach ($teams['2'] as $r){ $sum2c += $r['qty']; $sum2h += $r['hours']; }
        $sumDayC = $sum1c + $sum2c; $sumDayH = $sum1h + $sum2h;
        ?>
        <section class="day-card">
            <div class="day-hdr">
                <div class="day-date"><?=h($day)?> <span class="muted">(<?=dow($day)?>)</span></div>
                <div class="day-sum">Итого за день: <b><?=number_format($sumDayC,0,'.',' ')?></b> шт · <b><?=number_format($sumDayH,1,'.',' ')?></b> ч</div>
            </div>

            <div class="twocol">
                <!-- Бригада 1 -->
                <div class="team team-1">
                    <h4>Бригада 1 — итого: <span class="tot"><?=number_format($sum1c,0,'.',' ')?></span> шт · <span class="tot"><?=number_format($sum1h,1,'.',' ')?></span> ч</h4>
                    <?php if (empty($teams['1'])): ?>
                        <div class="muted">Нет заданий</div>
                    <?php else: ?>
                        <table class="tbl">
                            <thead>
                            <tr>
                                <th style="width:110px">Источник</th>
                                <th>Фильтр</th>
                                <th style="width:80px">Кол-во</th>
                                <th style="width:110px">Норма (шт/смену)</th>
                                <th style="width:80px">Часы</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($teams['1'] as $r): ?>
                                <tr>
                                    <td><?=h($r['source_date'])?></td>
                                    <td><?=h($r['filter'])?></td>
                                    <td><?=number_format($r['qty'],0,'.',' ')?></td>
                                    <td><?= $r['rate'] ? number_format($r['rate'],0,'.',' ') : '—' ?></td>
                                    <td><?=number_format($r['hours'],1,'.',' ')?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Бригада 2 -->
                <div class="team team-2">
                    <h4>Бригада 2 — итого: <span class="tot"><?=number_format($sum2c,0,'.',' ')?></span> шт · <span class="tot"><?=number_format($sum2h,1,'.',' ')?></span> ч</h4>
                    <?php if (empty($teams['2'])): ?>
                        <div class="muted">Нет заданий</div>
                    <?php else: ?>
                        <table class="tbl">
                            <thead>
                            <tr>
                                <th style="width:110px">Источник</th>
                                <th>Фильтр</th>
                                <th style="width:80px">Кол-во</th>
                                <th style="width:110px">Норма (шт/смену)</th>
                                <th style="width:80px">Часы</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($teams['2'] as $r): ?>
                                <tr>
                                    <td><?=h($r['source_date'])?></td>
                                    <td><?=h($r['filter'])?></td>
                                    <td><?=number_format($r['qty'],0,'.',' ')?></td>
                                    <td><?= $r['rate'] ? number_format($r['rate'],0,'.',' ') : '—' ?></td>
                                    <td><?=number_format($r['hours'],1,'.',' ')?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
</div>
