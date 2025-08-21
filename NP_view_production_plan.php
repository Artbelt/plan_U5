<?php
// NP_view_production_plan.php — просмотр плана сборки (production) по заявке

$dsn  = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root";
$pass = "";

// --- утилита экранирования ---
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

// --- CSV экспорт ---
if (isset($_GET['action']) && $_GET['action']==='csv') {
    $order = $_GET['order'] ?? '';
    if ($order==='') { http_response_code(400); exit('no order'); }

    try {
        $pdo = new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);
        // авто-миграция таблицы на всякий случай
        $pdo->exec("CREATE TABLE IF NOT EXISTS build_plan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) NOT NULL,
            source_date DATE NOT NULL,
            plan_date   DATE NOT NULL,
            filter TEXT NOT NULL,
            count INT NOT NULL,
            done TINYINT(1) NOT NULL DEFAULT 0,
            fact_count INT NOT NULL DEFAULT 0,
            status TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_order (order_number),
            KEY idx_plan_date (plan_date),
            KEY idx_source (source_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $st = $pdo->prepare("SELECT plan_date, source_date, filter, count, fact_count, done, status
                             FROM build_plan
                             WHERE order_number=?
                             ORDER BY plan_date, filter");
        $st->execute([$order]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="build_plan_'.$order.'.csv"');

        $out = fopen('php://output', 'w');
        // BOM для Excel под Windows
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['order_number','plan_date','source_date','filter','planned_count','fact_count','done','status'], ';');
        while($r=$st->fetch()){
            fputcsv($out, [
                $order,
                $r['plan_date'],
                $r['source_date'],
                $r['filter'],
                (int)$r['count'],
                (int)$r['fact_count'],
                (int)$r['done'],
                (int)$r['status']
            ], ';');
        }
        fclose($out);
        exit;
    } catch(Throwable $e){
        http_response_code(500);
        echo 'CSV error: '.h($e->getMessage());
        exit;
    }
}

// --- обычный режим просмотра ---
$order = $_GET['order'] ?? '';
if ($order==='') { http_response_code(400); exit('Укажите ?order=...'); }

try{
    $pdo = new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);

    // авто-миграция build_plan (если открыли View раньше, чем создавалась)
    $pdo->exec("CREATE TABLE IF NOT EXISTS build_plan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(50) NOT NULL,
        source_date DATE NOT NULL,
        plan_date   DATE NOT NULL,
        filter TEXT NOT NULL,
        count INT NOT NULL,
        done TINYINT(1) NOT NULL DEFAULT 0,
        fact_count INT NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_order (order_number),
        KEY idx_plan_date (plan_date),
        KEY idx_source (source_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // данные по дням
    $sql = "SELECT plan_date, filter,
                   SUM(count) AS planned,
                   SUM(fact_count) AS fact,
                   MAX(done) AS done,
                   MAX(status) AS status
            FROM build_plan
            WHERE order_number=?
            GROUP BY plan_date, filter
            ORDER BY plan_date, filter";
    $st  = $pdo->prepare($sql);
    $st->execute([$order]);
    $rows = $st->fetchAll();

    $byDay = [];            // $byDay[date] = [ ['filter'=>..., 'planned'=>.., 'fact'=>.., 'done'=>.., 'status'=>..], ... ]
    $days  = [];
    $totalsDay = [];        // план/факт по дню
    $grandPlanned = 0;
    $grandFact    = 0;

    foreach($rows as $r){
        $d = $r['plan_date'];
        $days[$d]=true;
        $byDay[$d][] = [
            'filter'  => $r['filter'],
            'planned' => (int)$r['planned'],
            'fact'    => (int)$r['fact'],
            'done'    => (int)$r['done'],
            'status'  => (int)$r['status']
        ];
        $totalsDay[$d]['plan'] = ($totalsDay[$d]['plan'] ?? 0) + (int)$r['planned'];
        $totalsDay[$d]['fact'] = ($totalsDay[$d]['fact'] ?? 0) + (int)$r['fact'];
        $grandPlanned += (int)$r['planned'];
        $grandFact    += (int)$r['fact'];
    }
    $days = array_keys($days);
    sort($days);

    // свод по фильтрам за весь период
    $sql2 = "SELECT filter, SUM(count) AS planned, SUM(fact_count) AS fact
             FROM build_plan
             WHERE order_number=?
             GROUP BY filter
             ORDER BY filter";
    $st2 = $pdo->prepare($sql2);
    $st2->execute([$order]);
    $byFilter = $st2->fetchAll();

} catch(Throwable $e){
    http_response_code(500);
    echo 'Ошибка: '.h($e->getMessage());
    exit;
}
?>
<!doctype html>
<meta charset="utf-8">
<title>План производства (сборка) — заявка <?=h($order)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root{
        --bg:#f7f9fc; --card:#fff; --text:#111; --muted:#6b7280; --line:#e5e7eb;
        --accent:#2563eb; --ok:#16a34a; --warn:#ef4444; --chip:#eef2ff;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);font:13px system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--text)}
    h2{margin:18px 10px 6px}
    .wrap{width:100vw; padding:0 10px}

    .toolbar{display:flex;gap:8px;align-items:center;margin:6px 0 10px}
    .btn{background:var(--accent);color:#fff;border:1px solid var(--accent);border-radius:8px;padding:7px 12px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
    .btn.secondary{background:#eef6ff;color:#1e40af;border-color:#c7d2fe}
    .btn:hover{filter:brightness(.97)}

    .panel{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:10px;margin:10px 0}
    .head{display:flex;align-items:center;justify-content:space-between;margin:0 0 8px}
    .muted{color:var(--muted)}

    .gridDays{display:grid;grid-template-columns:repeat(<?= count($days) ?: 1 ?>, minmax(220px,1fr));gap:10px}
    .col{border-left:1px solid var(--line);padding-left:8px;min-height:120px}
    .col h4{margin:0 0 8px;font-weight:600}

    table{border-collapse:collapse;width:100%;table-layout:fixed}
    th,td{border:1px solid var(--line);padding:6px 8px;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    th{background:#f3f4f6}
    .right{text-align:right}

    .chip{display:inline-block;background:var(--chip);border:1px solid #dbeafe;border-radius:999px;padding:2px 8px;font-size:12px}
    .done{color:var(--ok);font-weight:700}
    .warn{color:var(--warn);font-weight:700}

    .sum{margin-top:6px;font-size:12px}
    .sum b{font-size:13px}

    @media print{
        body{background:#fff}
        .toolbar{display:none}
        .panel{page-break-inside:avoid}
        .btn{display:none}
    }
</style>

<div class="wrap">
    <h2>План производства (сборка) — заявка <?=h($order)?></h2>

    <div class="toolbar">
        <button class="btn" onclick="window.print()">Распечатать</button>
        <a class="btn secondary" href="?action=csv&order=<?=urlencode($order)?>">Экспорт в CSV</a>
        <span class="muted" style="margin-left:auto">Всего по заявке: план <b><?=number_format($grandPlanned,0,'.',' ')?></b> шт<?= $grandFact>0 ? ', факт <b>'.number_format($grandFact,0,'.',' ').'</b> шт' : '' ?></span>
    </div>

    <!-- Свод по фильтрам -->
    <div class="panel">
        <div class="head">
            <b>Свод по фильтрам (вся заявка)</b>
            <span class="muted">План / Факт</span>
        </div>
        <?php if (!$byFilter): ?>
            <div class="muted">Нет данных плана сборки по этой заявке.</div>
        <?php else: ?>
            <table>
                <colgroup>
                    <col style="width:60%"><col style="width:20%"><col style="width:20%">
                </colgroup>
                <tr><th>Фильтр</th><th class="right">План, шт</th><th class="right">Факт, шт</th></tr>
                <?php foreach($byFilter as $r): ?>
                    <tr>
                        <td><?=h($r['filter'])?></td>
                        <td class="right"><?=number_format((int)$r['planned'],0,'.',' ')?></td>
                        <td class="right"><?=number_format((int)$r['fact'],0,'.',' ')?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th class="right">ИТОГО:</th>
                    <th class="right"><?=number_format($grandPlanned,0,'.',' ')?></th>
                    <th class="right"><?=number_format($grandFact,0,'.',' ')?></th>
                </tr>
            </table>
        <?php endif; ?>
    </div>

    <!-- По дням -->
    <div class="panel">
        <div class="head">
            <b>Разбивка по дням сборки</b>
            <span class="muted">Колонки — даты, строки — изделия</span>
        </div>

        <?php if (!$days): ?>
            <div class="muted">Нет назначений по дням.</div>
        <?php else: ?>
            <div class="gridDays">
                <?php foreach($days as $d): ?>
                    <div class="col">
                        <h4><?=h($d)?></h4>
                        <?php if (empty($byDay[$d])): ?>
                            <div class="muted">Нет позиций</div>
                        <?php else: ?>
                            <table>
                                <colgroup>
                                    <col style="width:70%"><col style="width:15%"><col style="width:15%">
                                </colgroup>
                                <tr>
                                    <th>Фильтр</th>
                                    <th class="right">План</th>
                                    <th class="right">Факт</th>
                                </tr>
                                <?php foreach($byDay[$d] as $it): ?>
                                    <tr>
                                        <td>
                                            <?=h($it['filter'])?>
                                            <?php if ((int)$it['done']===1): ?>
                                                <span class="chip" title="Отмечено как выполнено">готово</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="right"><?=number_format((int)$it['planned'],0,'.',' ')?></td>
                                        <td class="right"><?=number_format((int)$it['fact'],0,'.',' ')?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <th class="right">ИТОГО:</th>
                                    <th class="right"><?=number_format((int)($totalsDay[$d]['plan']??0),0,'.',' ')?></th>
                                    <th class="right"><?=number_format((int)($totalsDay[$d]['fact']??0),0,'.',' ')?></th>
                                </tr>
                            </table>
                        <?php endif; ?>

                        <div class="sum">
                            <?php
                            $p = (int)($totalsDay[$d]['plan']??0);
                            $f = (int)($totalsDay[$d]['fact']??0);
                            if ($p>0){
                                $pct = floor(($f/$p)*100);
                                echo 'Выполнение: <b>'.$pct.'%</b>';
                                if ($pct>=100) echo ' <span class="done">✓</span>';
                                elseif ($f>0 && $pct<100) echo ' <span class="warn">в процессе</span>';
                            } else {
                                echo '<span class="muted">В этот день нет плана</span>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
