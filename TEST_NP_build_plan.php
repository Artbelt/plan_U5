<?php
// NP_build_plan.php — план сборки салонных фильтров (2 бригады)

$dsn  = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root";
$pass = "";
$SHIFT_HOURS = 11.5;

/* ===================== AJAX save/load/busy ===================== */
if (isset($_GET['action']) && in_array($_GET['action'], ['save','load','busy','meta','orders','progress'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    try{
        $pdo = new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);

        // auto-migrate: build_plan (+ brigade)
        $pdo->exec("CREATE TABLE IF NOT EXISTS build_plan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) NOT NULL,
            source_date DATE NOT NULL,
            plan_date   DATE NOT NULL,
            brigade TINYINT(1) NOT NULL DEFAULT 1,
            filter TEXT NOT NULL,
            count INT NOT NULL,
            done TINYINT(1) NOT NULL DEFAULT 0,
            fact_count INT NOT NULL DEFAULT 0,
            status TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_order (order_number),
            KEY idx_plan_date (plan_date),
            KEY idx_source (source_date),
            KEY idx_brigade (brigade)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // add brigade column if missing
        $hasBrig = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='build_plan' AND COLUMN_NAME='brigade'")->fetchColumn();
        if (!$hasBrig) {
            $pdo->exec("ALTER TABLE build_plan ADD brigade TINYINT(1) NOT NULL DEFAULT 1 AFTER plan_date");
        }

        // orders.build_ready
        $hasBuildReadyCol = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='build_ready'")->fetchColumn();
        if (!$hasBuildReadyCol) {
            $pdo->exec("ALTER TABLE orders ADD build_ready TINYINT(1) NOT NULL DEFAULT 0");
        }

        /* -------- load -------- */
        if ($_GET['action']==='load') {
            $order = $_GET['order'] ?? '';
            if ($order==='') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

            $st = $pdo->prepare("SELECT source_date, plan_date, brigade, filter, count 
                                 FROM build_plan 
                                 WHERE order_number=? 
                                 ORDER BY plan_date, brigade, filter");
            $st->execute([$order]);
            $rows = $st->fetchAll();

            $plan = [];
            foreach($rows as $r){
                $d = $r['plan_date'];
                $b = (string)((int)$r['brigade'] ?: 1);
                if (!isset($plan[$d])) $plan[$d] = ['1'=>[], '2'=>[]];
                $plan[$d][$b][] = [
                    'source_date'=>$r['source_date'],
                    'filter'=>$r['filter'],
                    'count'=>(int)$r['count']
                ];
            }
            echo json_encode(['ok'=>true,'plan'=>$plan]); exit;
        }

        /* -------- save -------- */
        if ($_GET['action']==='save') {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data || !isset($data['order']) || !isset($data['plan'])) {
                http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad payload']); exit;
            }
            $order = (string)$data['order'];
            $plan  = $data['plan']; // { 'YYYY-MM-DD': { '1': [ {source_date, filter, count} ], '2': [ ... ] } }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM build_plan WHERE order_number=?")->execute([$order]);

            $ins = $pdo->prepare("INSERT INTO build_plan(order_number,source_date,plan_date,brigade,filter,count) 
                                  VALUES (?,?,?,?,?,?)");
            $rows = 0;
            foreach ($plan as $day=>$byTeam){
                if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $day)) continue;
                if (!is_array($byTeam)) continue;
                foreach (['1','2'] as $team){
                    if (empty($byTeam[$team]) || !is_array($byTeam[$team])) continue;
                    foreach ($byTeam[$team] as $it){
                        $src = $it['source_date'] ?? null;
                        $flt = $it['filter'] ?? '';
                        $cnt = (int)($it['count'] ?? 0);
                        $brig= (int)$team;
                        if (!$src || !preg_match('~^\d{4}-\d{2}-\d{2}$~', $src)) continue;
                        if ($cnt<=0 || $flt==='') continue;
                        $ins->execute([$order, $src, $day, $brig, $flt, $cnt]);
                        $rows++;
                    }
                }
            }

            $pdo->prepare("UPDATE orders SET build_ready=? WHERE order_number=?")->execute([$rows>0?1:0, $order]);
            $pdo->commit();
            echo json_encode(['ok'=>true,'rows'=>$rows]); exit;
        }

        /* -------- busy (часы + высоты других заявок) -------- */
        if ($_GET['action']==='busy') {
            $order = $_GET['order'] ?? '';
            $payload = [];
            if ($_SERVER['REQUEST_METHOD']==='POST') {
                $raw = file_get_contents('php://input');
                $payload = json_decode($raw, true) ?: [];
            }
            $days = $payload['days'] ?? ($_GET['days'] ?? []);
            if (is_string($days)) $days = explode(',', $days);
            $days = array_values(array_filter(array_unique(array_map('trim', (array)$days)), fn($d)=>preg_match('~^\d{4}-\d{2}-\d{2}$~',$d)));

            if (!$order || !$days) { echo json_encode(['ok'=>true,'data'=>[], 'heights'=>[]]); exit; }

            $ph = implode(',', array_fill(0, count($days), '?'));
            $q  = $pdo->prepare("
                SELECT bp.plan_date, bp.brigade, bp.count,
                       NULLIF(COALESCE(sfs.build_complexity,0),0) AS rate_per_shift,
                       pps.p_p_height AS paper_height
                FROM build_plan bp
                LEFT JOIN salon_filter_structure sfs ON TRIM(sfs.filter) = TRIM(bp.filter)
                LEFT JOIN paper_package_salon pps ON pps.p_p_name = sfs.paper_package
                WHERE bp.order_number <> ?
                  AND bp.plan_date IN ($ph)
            ");
            $params = array_merge([$order], $days);
            $q->execute($params);

            $outHrs = []; // [$day][1|2] => hours
            $outHei = []; // [$day][1|2] => [heights...]
            while ($r = $q->fetch()){
                $d = $r['plan_date']; $b = (int)($r['brigade'] ?: 1);
                $cnt = (int)$r['count']; $rate = (int)$r['rate_per_shift'];
                $hrs = $rate>0 ? ($cnt/$rate)*$SHIFT_HOURS : 0.0;
                if (!isset($outHrs[$d])) $outHrs[$d] = [1=>0.0,2=>0.0];
                $outHrs[$d][$b] += $hrs;

                if (!isset($outHei[$d])) $outHei[$d] = [1=>[],2=>[]];
                if ($r['paper_height'] !== null) {
                    $outHei[$d][$b][] = (float)$r['paper_height']; // dedupe на клиенте
                }
            }
            foreach ($outHrs as $d=>$bb){ $outHrs[$d][1] = round($bb[1],1); $outHrs[$d][2] = round($bb[2],1); }

            echo json_encode(['ok'=>true,'data'=>$outHrs, 'heights'=>$outHei]); exit;
        }

        /* -------- meta -------- */
        if ($_GET['action'] === 'meta') {
            $raw = file_get_contents('php://input');
            $in  = json_decode($raw, true) ?: [];
            $filters = array_values(array_filter(array_unique((array)($in['filters'] ?? []))));
            header('Content-Type: application/json; charset=utf-8');

            if (!$filters) { echo json_encode(['ok'=>true,'items'=>[]], JSON_UNESCAPED_UNICODE); exit; }

            $ph = implode(',', array_fill(0, count($filters), '?'));
            $st = $pdo->prepare("
                SELECT
                    TRIM(sfs.filter) as filter,
                    CAST(NULLIF(COALESCE(sfs.build_complexity,0),0) AS DECIMAL(10,3)) AS rate,
                    sfs.build_complexity,
                    COALESCE(
                        CAST(pps.p_p_height AS DECIMAL(10,3)),
                        CAST(cp.height AS DECIMAL(10,3))
                    ) AS height,
                    sfs.paper_package,
                    pps.p_p_height as raw_height,
                    cp.height as cut_height
                FROM salon_filter_structure sfs
                LEFT JOIN paper_package_salon pps ON pps.p_p_name = sfs.paper_package
                LEFT JOIN (
                    SELECT TRIM(filter) as filter, height 
                    FROM cut_plans 
                    WHERE height IS NOT NULL 
                    GROUP BY TRIM(filter)
                    HAVING COUNT(*) > 0
                ) cp ON TRIM(cp.filter) = TRIM(sfs.filter)
                WHERE TRIM(sfs.filter) IN ($ph)
            ");
            $st->execute($filters);
            $items = $st->fetchAll(PDO::FETCH_ASSOC);
            
            // Отладка: для фильтров, которых нет в результатах, ищем похожие
            $found_filters = array_column($items, 'filter');
            $missing = array_diff($filters, $found_filters);
            $debug_info = [];
            
            if (!empty($missing)) {
                foreach ($missing as $miss) {
                    // Ищем похожие фильтры
                    $st2 = $pdo->prepare("SELECT TRIM(filter) as filter FROM salon_filter_structure WHERE TRIM(filter) LIKE ? LIMIT 3");
                    $st2->execute(['%' . trim($miss) . '%']);
                    $similar = $st2->fetchAll(PDO::FETCH_COLUMN);
                    $debug_info[$miss] = $similar;
                }
            }
            
            echo json_encode(['ok'=>true, 'items'=>$items, 'debug_missing'=>$debug_info], JSON_UNESCAPED_UNICODE);
            exit;
        }

        /* -------- progress -------- */
        if ($_GET['action'] === 'progress') {
            $order = $_GET['order'] ?? '';
            header('Content-Type: application/json; charset=utf-8');
            if ($order === '') { echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

            $st = $pdo->prepare("
                SELECT bp.filter, SUM(bp.count) AS planned, SUM(bp.fact_count) AS fact
                FROM build_plan bp
                WHERE bp.order_number = ?
                GROUP BY bp.filter
                ORDER BY bp.filter
            ");
            $st->execute([$order]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok'=>true,'items'=>$rows], JSON_UNESCAPED_UNICODE); exit;
        }

        echo json_encode(['ok'=>false,'error'=>'unknown action']); exit;
    } catch(Throwable $e){
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
    }
    exit;
}

/* ===================== обычная страница ===================== */
$order = $_GET['order'] ?? '';
if ($order==='') { http_response_code(400); exit('Укажите ?order=...'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function fmt1($x){ return number_format((float)$x, 1, '.', ''); }
function fmt_mm($v){
    if ($v === null || $v === '') return null;
    $v = (float)$v;
    return (abs($v - round($v)) < 0.01) ? (string)(int)round($v) : rtrim(rtrim(number_format($v,1,'.',''), '0'), '.');
}

try{
    $pdo = new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);

    // источник: corrugation_plan + норма смены + высота бумаги + факт выполнения
    $src = $pdo->prepare("
        SELECT
          cp.plan_date     AS source_date,
          cp.filter_label  AS filter,
          SUM(cp.count)    AS planned,
          SUM(cp.fact_count) AS fact_count,
          NULLIF(COALESCE(sfs.build_complexity, 0), 0) AS rate_per_shift,
          pps.p_p_height   AS paper_height
        FROM corrugation_plan cp
        LEFT JOIN salon_filter_structure sfs ON TRIM(sfs.filter) = TRIM(cp.filter_label)
        LEFT JOIN paper_package_salon pps ON pps.p_p_name = sfs.paper_package
        WHERE cp.order_number = ?
        GROUP BY cp.plan_date, cp.filter_label, pps.p_p_height
        ORDER BY cp.plan_date, cp.filter_label
    ");
    $src->execute([$order]);
    $rowsSrc = $src->fetchAll();

    // уже разложено — для «Доступно»
    $bp  = $pdo->prepare("SELECT source_date, filter, SUM(count) AS assigned
                          FROM build_plan WHERE order_number=?
                          GROUP BY source_date, filter");
    $bp->execute([$order]);
    $rowsAssigned = $bp->fetchAll();
    $assignedMap = [];
    foreach($rowsAssigned as $r){
        $assignedMap[$r['source_date'].'|'.$r['filter']] = (int)$r['assigned'];
    }

    // верхние плашки (с высотой)
    $pool = []; $srcDates = [];
    foreach($rowsSrc as $r){
        $d = $r['source_date'];
        $flt = $r['filter'];
        $planned = (int)$r['planned'];
        $factCount = (int)$r['fact_count'];
        $used = (int)($assignedMap[$d.'|'.$flt] ?? 0);
        $avail = max(0, $planned - $used);
        $srcDates[$d] = true;

        $pool[$d][] = [
            'key'         => md5($d.'|'.$flt),
            'source_date' => $d,
            'filter'      => $flt,
            'available'   => $avail,
            'rate'        => $r['rate_per_shift'] ? (int)$r['rate_per_shift'] : 0,
            'height'      => isset($r['paper_height']) && $r['paper_height']!==null ? (float)$r['paper_height'] : null,
            'fact_count'  => $factCount,
            'is_corrugated' => $factCount > 0,
        ];
    }
    $srcDates = array_keys($srcDates); sort($srcDates);

    // предварительный план
    $prePlan = [];
    $pre = $pdo->prepare("SELECT plan_date, brigade, source_date, filter, count
                          FROM build_plan WHERE order_number=? ORDER BY plan_date, brigade, filter");
    $pre->execute([$order]);
    while($r=$pre->fetch()){
        $d = $r['plan_date']; $b = (string)((int)$r['brigade'] ?: 1);
        if (!isset($prePlan[$d])) $prePlan[$d] = ['1'=>[], '2'=>[]];
        $prePlan[$d][$b][] = [
            'source_date'=>$r['source_date'],
            'filter'=>$r['filter'],
            'count'=>(int)$r['count']
        ];
    }

    // какие дни показать — непрерывный диапазон
    $buildDays = [];
    $interesting = array_unique(array_merge(array_keys($prePlan), array_keys($srcDates?['x'=>1]:[]) ? [] : [] ));
    $interesting = array_unique(array_merge(array_keys($prePlan), $srcDates));
    sort($interesting);

    if ($interesting) {
        $from = new DateTime(reset($interesting));
        $to   = new DateTime(end($interesting));
        for ($d = clone $from; $d <= $to; $d->modify('+1 day')) {
            $buildDays[] = $d->format('Y-m-d');
        }
    } else {
        $start = new DateTime();
        for ($i = 0; $i < 7; $i++) {
            $buildDays[] = $start->format('Y-m-d');
            $start->modify('+1 day');
        }
    }

    /* === стартовая занятость других заявок (часы+высоты) === */
    $busyByDayBrig = [];
    $busyHeiByDay = [];
    if ($buildDays) {
        $ph = implode(',', array_fill(0, count($buildDays), '?'));
        $q  = $pdo->prepare("
            SELECT bp.plan_date, bp.brigade, bp.filter, bp.count,
                   NULLIF(COALESCE(sfs.build_complexity,0),0) AS rate_per_shift,
                   pps.p_p_height AS paper_height
            FROM build_plan bp
            LEFT JOIN salon_filter_structure sfs ON TRIM(sfs.filter) = TRIM(bp.filter)
            LEFT JOIN paper_package_salon pps ON pps.p_p_name = sfs.paper_package
            WHERE bp.order_number <> ?
              AND bp.plan_date IN ($ph)
        ");
        $params = array_merge([$order], $buildDays);
        $q->execute($params);
        while ($row = $q->fetch()) {
            $d = $row['plan_date'];
            $b = (int)($row['brigade'] ?: 1);
            $cnt = (int)$row['count'];
            $rate = (int)$row['rate_per_shift'];
            $hrs = ($rate > 0) ? ($cnt / $rate) * $SHIFT_HOURS : 0.0;

            if (!isset($busyByDayBrig[$d])) $busyByDayBrig[$d] = [1=>['cnt'=>0,'hrs'=>0.0], 2=>['cnt'=>0,'hrs'=>0.0]];
            $busyByDayBrig[$d][$b]['cnt'] += $cnt;
            $busyByDayBrig[$d][$b]['hrs'] += $hrs;

            if (!isset($busyHeiByDay[$d])) $busyHeiByDay[$d] = [1=>[],2=>[]];
            if ($row['paper_height'] !== null) {
                $busyHeiByDay[$d][$b][] = (float)$row['paper_height'];
            }
        }
    }

    $busyInit = [];
    $busyHeightsInit = [];
    foreach ($busyByDayBrig as $d => $bb) {
        $busyInit[$d] = [
            1 => round(($bb[1]['hrs'] ?? 0), 1),
            2 => round(($bb[2]['hrs'] ?? 0), 1),
        ];
    }
    foreach ($busyHeiByDay as $d => $bb) {
        $busyHeightsInit[$d] = [
            1 => array_values($bb[1]),
            2 => array_values($bb[2]),
        ];
    }

} catch(Throwable $e){
    http_response_code(500); echo 'Ошибка: '.h($e->getMessage()); exit;
}
?>
<!doctype html>
<meta charset="utf-8">
<title>План сборки (2 бригады) — заявка <?=h($order)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root{ --line:#e5e7eb; --bg:#f7f9fc; --card:#fff; --muted:#6b7280; --accent:#2563eb; --ok:#16a34a; }
    :root{ --brig1-bg:#faf0da; --brig1-bd:#F3E8A1; --brig2-bg:#EEF5FF; --brig2-bd:#CFE0FF; }
    .brig.brig1{ background:var(--brig1-bg); border-color:var(--brig1-bd); }
    .brig.brig2{ background:var(--brig2-bg); border-color:var(--brig2-bd); }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);font:13px system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111; user-select:none;}
    h2{margin:18px 10px 6px}
    .wrap{width:100vw;margin:0;padding:0 10px}

    .panel{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:10px;margin:10px 0}
    .head{display:flex;align-items:center;justify-content:space-between;margin:0 0 8px}
    .btn{background:var(--accent);color:#fff;border:1px solid var(--accent);border-radius:8px;padding:6px 10px;cursor:pointer}
    .btn.secondary{background:#eef6ff;color:#1e40af;border-color:#c7d2fe}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .muted{color:var(--muted)}
    .sub{font-size:12px;color:var(--muted)}

    .grid{display:grid;grid-template-columns:repeat(<?=count($srcDates)?:1?>,minmax(120px,1fr));gap:10px}
    .gridDays{display:grid;grid-template-columns:repeat(<?=count($buildDays)?:1?>,minmax(300px,1fr));gap:10px}

    .col{border-left:1px solid var(--line);padding-left:8px;min-height:200px}
    .col h4{margin:0 0 8px;font-weight:600}

    /* верхние плашки */
    .pill{border:1px solid #dbe3f0;background:#eef6ff;border-radius:10px;padding:8px;margin:6px 0;display:flex;flex-direction:column;gap:6px}
    .pillTop{display:flex;align-items:center;gap:10px;justify-content:space-between}
    .qty{width:72px;padding:6px;border:1px solid #c9d4ea;border-radius:8px}
    .pillName{font-weight:600}
    .pillSub{font-size:12px;color:#374151}
    .pill.disabled{opacity:.45;filter:grayscale(.15);pointer-events:none}
    .pill.corrugated{border-color:#22c55e;background:#f0fdf4;box-shadow:0 1px 3px rgba(34,197,94,0.2)}

    /* низ — две бригады */
    .brigWrap{display:grid;grid-template-columns:1fr;gap:6px}
    .brig{border:1px dashed var(--line);border-radius:8px;padding:6px}
    .brig h5{margin:0 0 6px;font-weight:700}
    .dropzone{min-height:36px}
    .rowItem{display:flex;align-items:center;justify-content:space-between;background:#dff7c7;border:1px solid #bddda2;border-radius:8px;padding:6px 8px;margin:6px 0}
    .rowLeft{display:flex;flex-direction:column}
    .rm{border:1px solid #ccc;background:#fff;border-radius:8px;padding:2px 8px;cursor:pointer}
    .mv{border:1px solid #ccc;background:#fff;border-radius:8px;padding:2px 6px;cursor:pointer}
    .mv:disabled{opacity:.5;cursor:not-allowed}
    .rowCtrls{display:flex;gap:6px;flex-wrap:wrap}

    .dayFoot{margin-top:6px;font-size:12px;color:#374151}
    .tot,.hrsB,.hrs{font-weight:700}
    .hrsHeights{color:#6b7280;font-weight:600;margin-left:4px}

    /* скроллы */
    .scrollX{ overflow-x:auto; overflow-y:hidden; -webkit-overflow-scrolling:touch; padding-bottom:4px; }
    .scrollX > .grid, .scrollX > .gridDays{ width:max-content; display:grid; }
    .scrollX::-webkit-scrollbar{height:10px}
    .scrollX::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:6px}
    .vscroll::-webkit-scrollbar{width:10px}
    .vscroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:6px}

    /* компактный вид плашек ТОЛЬКО вверху */
    #topGrid .pill{ padding:6px 8px; border-radius:8px; }
    #topGrid .pillTop{ gap:6px; }
    #topGrid .pillName{
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        max-width:130px;
        font-family:"Arial Narrow", Arial, "Nimbus Sans Narrow", system-ui, sans-serif;
        font-size:12px; line-height:1.2;
    }
    #topGrid .pillSub{ font-size:11px; }
    #topGrid .qty{ width:40px; padding:4px 6px; font-size:12px; }
    #topGrid .qty::-webkit-outer-spin-button, #topGrid .qty::-webkit-inner-spin-button{ -webkit-appearance:none; margin:0; }

    /* компактный режим — змейка */
    .snakeGrid{
        display:grid;
        grid-auto-flow:column;
        grid-template-rows:repeat(15, min-content);
        grid-auto-columns:minmax(200px, 1fr);
        gap:4px;
    }
    .snakeGrid .pill{ margin:0; padding:4px 6px; }
    .snakeGrid .dayBadge{ margin:2px 0; }
    .dayBadge{
        border:1px solid #dbe3f0; background:#f4f8ff; border-radius:8px;
        padding:6px 8px; font-weight:600; font-size:12px; color:#374151;
    }

    /* Плотный режим (низ) */
    .dense #daysGrid{ gap:8px }
    .dense #daysGrid .col{ padding-left:6px }
    .dense #daysGrid h4{ margin:0 0 6px; font-size:12px }
    .dense .brigWrap{ gap:4px }
    .dense .brig{ padding:4px; border-radius:6px }
    .dense .brig h5{ margin:0 0 4px; font-size:11px; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .dense .dayFoot{ margin-top:4px; font-size:12px }
    .dense .rowItem{ padding:4px 6px; margin:3px 0; border-radius:6px; }
    .dense .rowLeft b{ font-weight:600 }
    .dense .rowLeft .sub{ font-size:11px }
    .dense .rowCtrls .mv, .dense .rowCtrls .rm{ width:22px; height:22px; padding:0; display:flex; align-items:center; justify-content:center; }
    .dense .gridDays{ grid-template-columns:repeat(<?=count($buildDays)?:1?>, minmax(200px,1fr)); }
    .dense .totB, .dense .hrsB, .dense .hrsHeights{ font-weight:600 }
    .dense .hrsHeights{ font-size:11px }

    /* плавная смена фона при подсветке */
    #topGrid .pill, #daysGrid .rowItem{ transition: background-color .15s ease, border-color .15s ease; }
    
    /* Плавающая панель для нижней таблицы */
    .floating-panel {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 37%;
        max-width: 700px;
        height: auto;
        max-height: 85vh;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        z-index: 1000;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .floating-panel-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 6px 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: move;
        user-select: none;
    }
    
    .floating-panel-title {
        font-weight: 600;
        font-size: 12px;
    }
    
    .floating-panel-controls {
        display: flex;
        gap: 4px;
    }
    
    .floating-panel-btn {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        padding: 3px 8px;
        border-radius: 3px;
        cursor: pointer;
        font-size: 11px;
        line-height: 1;
        transition: background .15s;
    }
    
    .floating-panel-btn:hover {
        background: rgba(255,255,255,0.35);
    }
    
    .floating-panel-content {
        overflow: auto;
        flex: 1;
        padding: 6px;
    }
    
    /* Компактные стили для элементов внутри плавающей панели */
    .floating-panel .gridDays {
        gap: 6px !important;
    }
    
    .floating-panel .col {
        min-width: 180px !important;
    }
    
    .floating-panel .col h4 {
        font-size: 11px !important;
        padding: 4px 6px !important;
        margin-bottom: 4px !important;
    }
    
    .floating-panel .brigWrap {
        gap: 4px !important;
    }
    
    .floating-panel .brig {
        padding: 4px !important;
    }
    
    .floating-panel .brig h5 {
        font-size: 10px !important;
        padding: 3px 5px !important;
        margin-bottom: 3px !important;
    }
    
    .floating-panel .dropzone {
        min-height: 40px !important;
        padding: 3px !important;
    }
    
    .floating-panel .rowItem {
        padding: 3px 6px !important;
        margin-bottom: 2px !important;
        font-size: 11px !important;
    }
    
    .floating-panel .rowTop {
        margin-bottom: 2px !important;
    }
    
    .floating-panel .rowName {
        font-size: 11px !important;
    }
    
    .floating-panel .rowSub {
        font-size: 9px !important;
    }
    
    .floating-panel .rowCtrls button {
        width: 18px !important;
        height: 18px !important;
        font-size: 10px !important;
        padding: 0 !important;
    }
    
    .floating-panel .dayFoot {
        font-size: 9px !important;
        padding: 3px 5px !important;
    }
    
    .floating-panel .sub {
        font-size: 9px !important;
        padding: 4px 6px !important;
    }
</style>

<div class="wrap">
    <b>План сборки заявки <?=h($order)?></b>

    <!-- ВЕРХ: остатки после гофры -->
    <div class="panel">
        <div class="head">
            <div>
                <b>Доступно к сборке (после гофры)</b>
                <span class="sub">клик по плашке — выбрать день и бригаду</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <button class="btn secondary" id="btnSnake">Компактный режим</button>
                <!-- ПЕРЕКЛЮЧАТЕЛЬ ПОДСВЕТКИ -->
                <button class="btn secondary" id="btnHeightColors">Цвет по высоте: Вкл</button>
                <div class="muted">
                    <?php
                    $availCount=0; foreach($pool as $list){ foreach($list as $it){ $availCount+=$it['available']; } }
                    echo 'Всего доступно: <b>'.number_format($availCount,0,'.',' ').'</b> шт';
                    ?>
                </div>
            </div>
        </div>
        <div class="scrollX" id="topScroll">
            <div class="grid" id="topGrid">
                <?php foreach($srcDates as $d): ?>
                    <div class="col">
                        <h4><?=h($d)?></h4>
                        <?php if (empty($pool[$d])): ?>
                            <div class="muted">нет остатков</div>
                        <?php else: foreach ($pool[$d] as $p):
                            $htStr = $p['height'] !== null ? fmt_mm($p['height']) : null;
                            $ht = $htStr !== null ? ('  <span class="muted">['.$htStr.']</span>') : '';
                            ?>
                            <div class="pill<?= ($p['available']<=0 ? ' disabled' : '') ?><?= ($p['is_corrugated'] ? ' corrugated' : '') ?>"
                                 data-key="<?=h($p['key'])?>"
                                 data-source-date="<?=h($p['source_date'])?>"
                                 data-filter="<?=h($p['filter'])?>"
                                 data-avail="<?=$p['available']?>"
                                 data-rate="<?=$p['rate']?>"
                                 data-height="<?= $htStr !== null ? h($htStr) : '' ?>"
                                 data-fact-count="<?=$p['fact_count']?>"
                                 title="Клик — добавить в день сборки<?= $p['is_corrugated'] ? ' ✓ Сгофрировано' : '' ?>">
                                <div class="pillTop">
                                    <div>
                                        <div class="pillName">
                                            <?= $p['is_corrugated'] ? '✅ ' : '' ?><?=h($p['filter'])?><?= $ht ?>
                                        </div>
                                        <div class="pillSub">
                                            <b class="av"><?=$p['available']?></b> шт · ~<b class="time">0.0</b>ч
                                            <?= $p['is_corrugated'] ? '<br><span class="muted">✓ Сгофрировано: ' . $p['fact_count'] . ' шт</span>' : '' ?>
                                        </div>
                                    </div>
                                    <input class="qty" type="number" min="1" step="1"
                                           value="<?=max(1, (int)$p['available'])?>"
                                           max="<?=$p['available']?>"
                                           title="Количество">
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- НИЗ: дни сборки (две бригады) в плавающей панели -->
    <div class="floating-panel" id="floating-panel">
        <div class="floating-panel-header" id="panel-header">
            <div class="floating-panel-title">📋 Сетка дней сборки</div>
            <div class="floating-panel-controls">
                <button class="floating-panel-btn" id="btnDense">Плотный режим</button>
                <button class="floating-panel-btn" id="btnAddRange">+ Дни</button>
                <button class="floating-panel-btn" id="btnLoad">Загрузить</button>
                <button class="floating-panel-btn" id="btnSave">Сохранить</button>
                <button class="floating-panel-btn" onclick="minimizePanel()">−</button>
            </div>
        </div>
        <div class="floating-panel-content">
        <div class="scrollX" id="daysScroll">
            <div id="daysGrid" class="gridDays">
                <?php foreach($buildDays as $d): ?>
                    <div class="col" data-day="<?=h($d)?>">
                        <h4><?=h($d)?></h4>
                        <div class="brigWrap">
                            <div class="brig brig1">
                                <h5>Бригада 1:
                                    <span class="totB" data-totb="<?=h($d)?>|1">0</span> шт ·
                                    Время: <span class="hrsB" data-hrsb="<?=h($d)?>|1">0.0</span>
                                    <span class="hrsHeights" data-hrsh="<?=h($d)?>|1"></span>
                                </h5>
                                <div class="dropzone" data-day="<?=h($d)?>" data-team="1"></div>
                            </div>
                            <div class="brig brig2">
                                <h5>Бригада 2:
                                    <span class="totB" data-totb="<?=h($d)?>|2">0</span> шт ·
                                    Время: <span class="hrsB" data-hrsb="<?=h($d)?>|2">0.0</span>
                                    <span class="hrsHeights" data-hrsh="<?=h($d)?>|2"></span>
                                </h5>
                                <div class="dropzone" data-day="<?=h($d)?>" data-team="2"></div>
                            </div>
                        </div>
                        <div class="dayFoot">
                            Итого за день:
                            <span class="tot" data-tot-day="<?=h($d)?>">0</span> шт ·
                            Время: <span class="hrs" data-hrs-day="<?=h($d)?>">0.0</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sub" style="margin-top:8px;padding:0 10px 10px;">
            Удаляя позицию из дня/бригады, её количество возвращается в «Доступно» наверху. «Время» включает наши часы и часы других заявок на ту же дату и бригаду. Высоты других заявок отображаются в квадратных скобках.
        </div>
        </div>
    </div>
</div>

<!-- Модалка выбора дня/бригады -->
<div class="modalWrap" id="datePicker" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.35);z-index:1000">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dpTitle" style="background:#fff;border-radius:12px;border:1px solid #e5e7eb;min-width:360px;max-width:600px;max-height:75vh;display:flex;flex-direction:column;overflow:hidden">
        <div class="modalHeader" style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid #e5e7eb">
            <div class="modalTitle" id="dpTitle" style="font-weight:600">Выберите день и бригаду</div>
            <button class="modalClose" id="dpClose" title="Закрыть" style="border:1px solid #ccc;background:#f8f8f8;border-radius:8px;padding:4px 8px;cursor:pointer">×</button>
        </div>
        <div class="modalBody" style="padding:10px;overflow:auto">
            <div class="teamSwitch" style="display:flex;gap:8px;margin-bottom:8px">
                <button class="teamBtn" id="team1" style="border:1px solid #cbd5e1;background:#f8fafc;border-radius:8px;padding:6px 10px;cursor:pointer">Бригада 1</button>
                <button class="teamBtn" id="team2" style="border:1px solid #cbd5e1;background:#f8fafc;border-radius:8px;padding:6px 10px;cursor:pointer">Бригада 2</button>
                <div style="flex:1"></div>
                <label>Кол-во:
                    <input id="dpQty" type="number" min="1" step="1" value="1" style="padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px;width:100px">
                </label>
            </div>
            <div class="daysGrid" id="dpDays" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px"></div>
        </div>
    </div>
</div>

<script>
    const ORDER = <?= json_encode($order) ?>;
    const SHIFT_HOURS = <?= json_encode($SHIFT_HOURS) ?>;

    // ===== in-memory =====
    const plan   = new Map();
    const countsByTeam = new Map();
    const hoursByTeam  = new Map();

    let lastDay  = null;
    let lastTeam = '1';

    const prePlan = <?= json_encode($prePlan, JSON_UNESCAPED_UNICODE) ?>;

    // стартовые часы/высоты других заявок
    const BUSY_INIT = <?= json_encode($busyInit, JSON_UNESCAPED_UNICODE) ?>;
    const BUSY_HEIGHTS_INIT = <?= json_encode($busyHeightsInit, JSON_UNESCAPED_UNICODE) ?>;

    const busyHours = new Map();
    Object.keys(BUSY_INIT || {}).forEach(d => {
        busyHours.set(d, {'1': BUSY_INIT[d][1] || 0, '2': BUSY_INIT[d][2] || 0});
    });
    const busyHeights = new Map();
    Object.keys(BUSY_HEIGHTS_INIT || {}).forEach(d => {
        busyHeights.set(d, {'1': BUSY_HEIGHTS_INIT[d][1] || [], '2': BUSY_HEIGHTS_INIT[d][2] || []});
    });

    // базовые доступности
    document.querySelectorAll('.pill').forEach(p=>{
        if (!p.dataset.avail0) p.dataset.avail0 = p.dataset.avail || '0';
    });

    // ===== helpers =====
    function cssEscape(s){ return String(s).replace(/["\\]/g, '\\$&'); }
    function escapeHtml(s){ return (s??'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
    function fmtH(x){ return (Math.round((x||0)*10)/10).toFixed(1); }
    function fmtMM(v){
        if (v === null || v === undefined || v === '') return '';
        const n = +v; if (!isFinite(n)) return String(v);
        const i = Math.round(n);
        return (Math.abs(n - i) < 0.01) ? String(i) : String(Math.round(n*10)/10);
    }
    function uniq(arr){
        const s = new Set(), out=[];
        (arr||[]).forEach(v=>{ const k = String(v); if(!s.has(k)){ s.add(k); out.push(v); }});
        return out;
    }
    function getAllDays(){
        return [...document.querySelectorAll('#daysGrid .col[data-day]')].map(c=>c.dataset.day);
    }

    /* ===== ПОДСВЕТКА ПО ВЫСОТЕ + ПЕРЕКЛЮЧАТЕЛЬ ===== */
    const HEIGHT_COLORS_FIXED = {
        '20':{bg:'#DCFCE7',bd:'#86EFAC'}, '25':{bg:'#E0F2FE',bd:'#93C5FD'},
        '27':{bg:'#DBEAFE',bd:'#93C5FD'}, '30':{bg:'#FEF9C3',bd:'#FDE68A'},
        '32':{bg:'#F3E8FF',bd:'#D8B4FE'}, '35':{bg:'#FFE4E6',bd:'#FDA4AF'},
        '40':{bg:'#E2E8F0',bd:'#CBD5E1'},
    };
    const PALETTE = [
        ['#E0F2FE','#93C5FD'], ['#DCFCE7','#86EFAC'], ['#FCE7F3','#F9A8D4'],
        ['#FEF3C7','#FCD34D'], ['#F3E8FF','#D8B4FE'], ['#FFE4E6','#FDA4AF'],
        ['#DDD6FE','#C4B5FD'], ['#CCFBF1','#5EEAD4']
    ];
    function hashToTheme(s){ let h=0; for(let i=0;i<s.length;i++) h=(h*31 + s.charCodeAt(i))>>>0; const [bg,bd]=PALETTE[h%PALETTE.length]; return {bg,bd}; }
    function themeForHeight(raw){
        if (raw==null) return null;
        const txt = String(raw).trim(); if (!txt) return null;
        const key = txt.replace(/[^\d.]/g,'');
        return HEIGHT_COLORS_FIXED[key] || hashToTheme(txt);
    }

    // состояние переключателя (persist)
    let heightColorOn = (localStorage.getItem('heightColorOn') ?? '1') !== '0';
    const btnHC = document.getElementById('btnHeightColors');
    function setHCLabel(){ if(btnHC) btnHC.textContent = 'Цвет по высоте: ' + (heightColorOn ? 'Вкл' : 'Выкл'); }
    if (btnHC){
        setHCLabel();
        btnHC.addEventListener('click', ()=>{
            heightColorOn = !heightColorOn;
            localStorage.setItem('heightColorOn', heightColorOn ? '1' : '0');
            setHCLabel();
            applyHeightColors();
        });
    }

    function applyHeightColors(){
        const topPills = document.querySelectorAll('#topGrid .pill');
        const rows = document.querySelectorAll('#daysGrid .rowItem');

        if (!heightColorOn){
            topPills.forEach(el=>{ el.style.backgroundColor=''; el.style.borderColor=''; });
            rows.forEach(el=>{ el.style.backgroundColor=''; el.style.borderColor=''; });
            return;
        }
        topPills.forEach(el=>{
            const t = themeForHeight(el.dataset.height);
            if (t){ el.style.backgroundColor=t.bg; el.style.borderColor=t.bd; }
        });
        rows.forEach(el=>{
            const t = themeForHeight(el.dataset.height);
            if (t){ el.style.backgroundColor=t.bg; el.style.borderColor=t.bd; }
        });
    }
    /* ===== /ПОДСВЕТКА ===== */

    async function fetchBusyForDays(daysArr){
        if (!Array.isArray(daysArr) || !daysArr.length) return;
        try{
            const res  = await fetch(location.pathname+'?action=busy&order='+encodeURIComponent(ORDER),{
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({days: daysArr})
            });
            const data = await res.json();
            if (!data.ok) return;

            const map = data.data || {};
            daysArr.forEach(d=>{
                const v = map[d] || {1:0,2:0};
                busyHours.set(d, {'1': +v[1] || 0, '2': +v[2] || 0});
            });

            const hm = data.heights || {};
            daysArr.forEach(d=>{
                const hv = hm[d] || {};
                const a1 = Array.isArray(hv[1]) ? hv[1] : [];
                const a2 = Array.isArray(hv[2]) ? hv[2] : [];
                busyHeights.set(d, {'1': a1, '2': a2});
            });

            daysArr.forEach(refreshTotalsDOM);
        }catch(e){ /* молча */ }
    }

    function ensureDay(day){
        if(!plan.has(day)) plan.set(day, {'1':[], '2':[]});
        if(!countsByTeam.has(day)) countsByTeam.set(day, {'1':0,'2':0,'sum':0});
        if(!hoursByTeam.has(day))  hoursByTeam.set(day,  {'1':0,'2':0,'sum':0});

        if (!document.querySelector(`.col[data-day="${cssEscape(day)}"]`)){
            const col = document.createElement('div'); col.className='col'; col.dataset.day = day;
            col.innerHTML = `
              <h4>${escapeHtml(day)}</h4>
              <div class="brigWrap">
                <div class="brig brig1">
                  <h5>Бригада 1:
                    <span class="totB" data-totb="${escapeHtml(day)}|1">0</span> шт ·
                    Время: <span class="hrsB" data-hrsb="${escapeHtml(day)}|1">0.0</span>
                    <span class="hrsHeights" data-hrsh="${escapeHtml(day)}|1"></span>
                  </h5>
                  <div class="dropzone" data-day="${escapeHtml(day)}" data-team="1"></div>
                </div>
                <div class="brig brig2">
                  <h5>Бригада 2:
                    <span class="totB" data-totb="${escapeHtml(day)}|2">0</span> шт ·
                    Время: <span class="hrsB" data-hrsb="${escapeHtml(day)}|2">0.0</span>
                    <span class="hrsHeights" data-hrsh="${escapeHtml(day)}|2"></span>
                  </h5>
                  <div class="dropzone" data-day="${escapeHtml(day)}" data-team="2"></div>
                </div>
              </div>
              <div class="dayFoot">
                Итого за день:
                <span class="tot" data-tot-day="${escapeHtml(day)}">0</span> шт ·
                Время: <span class="hrs" data-hrs-day="${escapeHtml(day)}">0.0</span>
              </div>
            `;
            document.getElementById('daysGrid').appendChild(col);

            if (!busyHours.has(day)) busyHours.set(day, {'1':0,'2':0});
            if (!busyHeights.has(day)) busyHeights.set(day, {'1':[], '2':[]});
            fetchBusyForDays([day]);
        }
        refreshTotalsDOM(day);
    }

    function refreshTotalsDOM(day){
        const c = countsByTeam.get(day) || {'1':0,'2':0,'sum':0};
        const h = hoursByTeam.get(day)  || {'1':0,'2':0,'sum':0};

        const busy1 = (busyHours.get(day) || {})['1'] || 0;
        const busy2 = (busyHours.get(day) || {})['2'] || 0;

        const heights1 = uniq((busyHeights.get(day) || {})['1'] || []).map(fmtMM).filter(x=>x!=='');
        const heights2 = uniq((busyHeights.get(day) || {})['2'] || []).map(fmtMM).filter(x=>x!=='');

        const el1 = document.querySelector(`.totB[data-totb="${cssEscape(day)}|1"]`);
        const el2 = document.querySelector(`.totB[data-totb="${cssEscape(day)}|2"]`);
        const eh1 = document.querySelector(`.hrsB[data-hrsb="${cssEscape(day)}|1"]`);
        const eh2 = document.querySelector(`.hrsB[data-hrsb="${cssEscape(day)}|2"]`);
        const eH1 = document.querySelector(`.hrsHeights[data-hrsh="${cssEscape(day)}|1"]`);
        const eH2 = document.querySelector(`.hrsHeights[data-hrsh="${cssEscape(day)}|2"]`);
        const edC = document.querySelector(`.tot[data-tot-day="${cssEscape(day)}"]`);
        const edH = document.querySelector(`.hrs[data-hrs-day="${cssEscape(day)}"]`);

        if (el1) el1.textContent = String(c['1']||0);
        if (el2) el2.textContent = String(c['2']||0);
        if (eh1) eh1.textContent = fmtH((h['1']||0) + busy1);
        if (eh2) eh2.textContent = fmtH((h['2']||0) + busy2);
        if (eH1) eH1.textContent = heights1.length ? ` [${heights1.join(', ')}]` : '';
        if (eH2) eH2.textContent = heights2.length ? ` [${heights2.join(', ')}]` : '';
        if (edC) edC.textContent = String((c['1']||0) + (c['2']||0));
        if (edH) edH.textContent = fmtH(((h['1']||0) + busy1) + ((h['2']||0) + busy2));
    }

    function incTotals(day, team, deltaCount, deltaHours){
        const c = countsByTeam.get(day) || {'1':0,'2':0,'sum':0};
        c[team] = Math.max(0, (c[team]||0) + deltaCount);
        c.sum   = (c['1']||0) + (c['2']||0);
        countsByTeam.set(day, c);

        const h = hoursByTeam.get(day) || {'1':0,'2':0,'sum':0};
        h[team] = Math.max(0, (h[team]||0) + deltaHours);
        h.sum   = (h['1']||0) + (h['2']||0);
        hoursByTeam.set(day, h);

        refreshTotalsDOM(day);
    }

    function resetPillsToBase(){
        document.querySelectorAll('.pill').forEach(p=>{
            const base = +p.dataset.avail0 || 0;
            updateAvailForPill(p, base);
        });
    }

    function updateAvailForPill(pill, newAvail){
        const avEl = pill.querySelector('.av');
        if (avEl) avEl.textContent = String(newAvail);
        const qty = pill.querySelector('.qty');
        if (qty){ qty.max = String(newAvail); qty.value = String(newAvail>0 ? newAvail : 1); }
        pill.dataset.avail = String(newAvail);
        pill.classList.toggle('disabled', newAvail<=0);
        updatePillTime(pill);
    }

    function collectUsedFromPlan(){
        const map = new Map();
        plan.forEach(byTeam=>{
            ['1','2'].forEach(team=>{
                (byTeam[team]||[]).forEach(r=>{
                    const k = r.source_date + '|' + r.filter;
                    map.set(k, (map.get(k)||0) + (r.count||0));
                });
            });
        });
        return map;
    }

    function updatePillTime(pill){
        const rate = +pill.dataset.rate || 0;
        const qty  = +(pill.querySelector('.qty')?.value || 0);
        const hours = rate>0 ? (qty / rate) * SHIFT_HOURS : 0;
        const tEl = pill.querySelector('.time');
        if (tEl) tEl.textContent = fmtH(hours);
    }
    document.querySelectorAll('.pill').forEach(p=>{
        const q = p.querySelector('.qty');
        updatePillTime(p);
        if (q) q.addEventListener('input', ()=> updatePillTime(p));
    });

    // ===== строки внизу =====
    function addRowElement(day, team, src, flt, count, rate, height){
        ensureDay(day);
        const dz = document.querySelector(`.dropzone[data-day="${cssEscape(day)}"][data-team="${cssEscape(team)}"]`);
        if (!dz) return;

        const r = Math.max(0, +rate || 0);
        const rowHours = r>0 ? (count / r) * SHIFT_HOURS : 0;

        plan.get(day)[team].push({source_date:src, filter:flt, count:count, rate:r, height:height ?? ''});

        const row = document.createElement('div');
        row.className = 'rowItem';
        row.dataset.day = day;
        row.dataset.team = team;
        row.dataset.sourceDate = src;
        row.dataset.filter = flt;
        row.dataset.count = count;
        row.dataset.rate  = r;
        row.dataset.hours = rowHours;
        if (height) row.dataset.height = height;

        const heightBadge = height ? ` <span class="sub">[${escapeHtml(String(height))}]</span>` : '';

        row.innerHTML = `
        <div class="rowLeft">
            <div><b>${escapeHtml(flt)}</b>${heightBadge}</div>
            <div class="sub">
                <b class="cnt">${count}</b> шт ·
                <b class="h">${fmtH(rowHours)}</b>ч
            </div>
        </div>
        <div class="rowCtrls">
            <button class="mv mvL" title="Сместить на день влево">◀</button>
            <button class="mv mvR" title="Сместить на день вправо">▶</button>
            <button class="rm" title="Удалить">×</button>
        </div>
        `;
        dz.appendChild(row);

        row.querySelector('.rm').onclick  = ()=> removeRow(row);
        row.querySelector('.mvL').onclick = ()=> moveRow(row, -1);
        row.querySelector('.mvR').onclick = ()=> moveRow(row, +1);

        incTotals(day, team, count, rowHours);
        applyHeightColors();
    }

    function removeRow(row){
        const day = row.dataset.day;
        const team= row.dataset.team;
        const src = row.dataset.sourceDate;
        const flt = row.dataset.filter;
        const cnt = +row.dataset.count || 0;
        const r   = +row.dataset.rate  || 0;
        const hrs = +row.dataset.hours || 0;

        const arr = plan.get(day)?.[team] || [];
        const i = arr.findIndex(x=> x.source_date===src && x.filter===flt && x.count===cnt && (x.rate||0)===r);
        if (i>=0){ arr.splice(i,1); plan.get(day)[team] = arr; }

        const pill = document.querySelector(`.pill[data-source-date="${cssEscape(src)}"][data-filter="${cssEscape(flt)}"]`);
        if (pill){
            const av = (+pill.dataset.avail||0) + cnt;
            updateAvailForPill(pill, av);
        }

        incTotals(day, team, -cnt, -hrs);
        row.remove();
        applyHeightColors();
    }

    function moveRow(row, dir){
        const days = getAllDays();
        const curDay = row.dataset.day;
        const i = days.indexOf(curDay);
        if (i < 0) return;

        const j = i + (dir < 0 ? -1 : 1);
        if (j < 0 || j >= days.length) return;

        const newDay = days[j];
        const team   = row.dataset.team;

        const src = row.dataset.sourceDate;
        const flt = row.dataset.filter;
        const cnt = +row.dataset.count || 0;
        const r   = +row.dataset.rate  || 0;
        const hrs = +row.dataset.hours || 0;
        const height = row.dataset.height || '';

        const arr = plan.get(curDay)?.[team] || [];
        const idx = arr.findIndex(x =>
            x.source_date === src &&
            x.filter      === flt &&
            x.count       === cnt &&
            (x.rate||0)   === r
        );
        if (idx >= 0){
            arr.splice(idx,1);
            plan.get(curDay)[team] = arr;
        }

        ensureDay(newDay);
        plan.get(newDay)[team].push({source_date:src, filter:flt, count:cnt, rate:r, height});

        const dzNew = document.querySelector(`.dropzone[data-day="${cssEscape(newDay)}"][data-team="${cssEscape(team)}"]`);
        if (dzNew) dzNew.appendChild(row);

        incTotals(curDay, team, -cnt, -hrs);
        incTotals(newDay, team, +cnt, +hrs);

        row.dataset.day = newDay;
        lastDay = newDay;
        applyHeightColors();
    }

    // ===== модалка дня/бригады =====
    const dpWrap  = document.getElementById('datePicker');
    const dpDays  = document.getElementById('dpDays');
    const dpQty   = document.getElementById('dpQty');
    const dpClose = document.getElementById('dpClose');
    const team1Btn= document.getElementById('team1');
    const team2Btn= document.getElementById('team2');
    let pending = null;

    function setTeamActive(t){
        lastTeam = (t==='2'?'2':'1');
        team1Btn.classList.toggle('active', lastTeam==='1');
        team2Btn.classList.toggle('active', lastTeam==='2');
        const days = getAllDays();
        dpDays.querySelectorAll('.dayBtn').forEach(btn=>{
            const ds = btn.dataset.day;
            const c  = (countsByTeam.get(ds)||{})[lastTeam] || 0;
            const h  = (hoursByTeam.get(ds)||{})[lastTeam]  || 0;
            btn.querySelector('.daySub').textContent = `Назначено (бригада ${lastTeam}): ${c} шт · ${fmtH(h)} ч`;
        });
    }
    team1Btn.onclick = ()=> setTeamActive('1');
    team2Btn.onclick = ()=> setTeamActive('2');

    function openDatePicker(pill, qty){
        pending = {pill, qty};
        dpQty.value = String(qty);
        dpDays.innerHTML = '';

        const days = getAllDays();
        days.forEach(d=>{
            const btn = document.createElement('button');
            btn.type='button'; btn.className='dayBtn'; btn.dataset.day = d;
            btn.style.cssText='display:flex;flex-direction:column;gap:4px;padding:10px;border:1px solid #d9e2f1;border-radius:10px;background:#f4f8ff;cursor:pointer;text-align:left';
            btn.innerHTML = `<div class="dayHead" style="font-weight:600">${d}</div><div class="daySub" style="font-size:12px;color:#6b7280"></div>`;
            btn.onclick = ()=>{
                addToDay(d, lastTeam, pending.pill, +dpQty.value || 1);
                closeDatePicker();
            };
            if (d===lastDay) btn.style.outline = '2px solid #2563eb';
            dpDays.appendChild(btn);
        });
        dpWrap.style.display='flex';
        setTeamActive(lastTeam);
    }
    function closeDatePicker(){ dpWrap.style.display='none'; pending=null; }
    dpClose.onclick = closeDatePicker;
    dpWrap.addEventListener('click', e=>{ if(e.target===dpWrap) closeDatePicker(); });
    document.addEventListener('keydown', e=>{ if(e.key==='Escape' && dpWrap.style.display==='flex') closeDatePicker(); });

    // клики по верхним плашкам
    document.querySelectorAll('.pill').forEach(pill=>{
        pill.addEventListener('click', (e)=>{
            if (e.target.closest('.qty')) return;
            const avail = +pill.dataset.avail || 0;
            if (avail <= 0) return;

            const qtyEl = pill.querySelector('.qty');
            let qty = parseInt(qtyEl?.value ?? avail, 10);
            if (!Number.isFinite(qty) || qty <= 0) qty = avail;
            qty = Math.min(qty, avail);

            if (e.shiftKey && lastDay){
                addToDay(lastDay, lastTeam, pill, qty);
            } else {
                openDatePicker(pill, qty);
            }
        });
    });

    // add to day
    function addToDay(day, team, pill, qty){
        const avail = +pill.dataset.avail || 0;
        if (qty<=0 || avail<=0) return;
        const take = Math.min(qty, avail);

        const src  = pill.dataset.sourceDate;
        const flt  = pill.dataset.filter;
        const rate = parseInt(pill.dataset.rate || '0', 10) || 0;
        const height = pill.dataset.height || '';

        addRowElement(day, team, src, flt, take, rate, height);

        const rest = avail - take;
        updateAvailForPill(pill, rest);

        lastDay = day;
    }

    // пререндер сохранённого плана
    (function renderPre(){
        Object.keys(prePlan||{}).forEach(day=>{
            ensureDay(day);
            ['1','2'].forEach(team=>{
                (prePlan[day][team]||[]).forEach(it=>{
                    const pill = document.querySelector(`.pill[data-source-date="${cssEscape(it.source_date)}"][data-filter="${cssEscape(it.filter)}"]`);
                    const rate = pill ? (parseInt(pill.dataset.rate||'0',10)||0) : 0;
                    const height = pill ? (pill.dataset.height || '') : '';
                    addRowElement(day, team, it.source_date, it.filter, +it.count||0, rate, height);
                });
            });
            lastDay = day;
        });
        applyHeightColors();
    })();

    // корректировка доступности после пререндеринга
    (function applyAvailAfterPre(){
        const used = collectUsedFromPlan();
        document.querySelectorAll('.pill').forEach(p=>{
            const base = +p.dataset.avail0 || 0;
            const key  = p.dataset.sourceDate + '|' + p.dataset.filter;
            const rest = Math.max(0, base - (used.get(key)||0));
            updateAvailForPill(p, rest);
        });
    })();

    // подтянуть «другие часы/высоты»
    fetchBusyForDays(getAllDays());

    // добавление следующего дня
    const btnAddRange = document.getElementById('btnAddRange');
    if (btnAddRange) {
        btnAddRange.onclick = ()=> {
            // Находим последний день в сетке
            const allDays = getAllDays();
            console.log('Текущие дни:', allDays);
            
            let newDay;
            if (allDays.length === 0) {
                // Если нет дней, добавляем сегодня
                const today = new Date();
                newDay = today.toISOString().slice(0,10);
            } else {
                // Добавляем следующий день после последнего
                const lastDay = allDays[allDays.length - 1];
                const nextDate = new Date(lastDay + 'T00:00:00');
                nextDate.setDate(nextDate.getDate() + 1);
                newDay = nextDate.toISOString().slice(0,10);
            }
            
            console.log('Добавляем день:', newDay);
            ensureDay(newDay);
            
            // Принудительно прокручиваем к новому дню
            const newCol = document.querySelector(`.col[data-day="${cssEscape(newDay)}"]`);
            if (newCol) {
                console.log('Колонка создана:', newCol);
                newCol.scrollIntoView({ behavior: 'smooth', inline: 'end', block: 'nearest' });
            } else {
                console.error('Колонка не создана!');
            }
            
            fetchBusyForDays([newDay]);
        };
    }

    // SAVE
    document.getElementById('btnSave').addEventListener('click', async ()=>{
        const payload = {};
        plan.forEach((byTeam,day)=>{
            const t1 = (byTeam['1']||[]).map(x=>({source_date:x.source_date, filter:x.filter, count:x.count}));
            const t2 = (byTeam['2']||[]).map(x=>({source_date:x.source_date, filter:x.filter, count:x.count}));
            if (t1.length || t2.length) payload[day] = {'1':t1, '2':t2};
        });

        try{
            const res = await fetch(location.pathname+'?action=save', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({order: ORDER, plan: payload})
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error||'unknown');
            alert('План сборки сохранён.');
        }catch(e){
            alert('Не удалось сохранить: '+e.message);
        }
    });

    // LOAD
    document.getElementById('btnLoad').addEventListener('click', loadPlanFromDB);
    async function loadPlanFromDB(){
        try{
            const url = location.pathname + '?action=load&order=' + encodeURIComponent(ORDER);
            const res = await fetch(url, { headers:{'Accept':'application/json'} });
            let data;
            try{ data = await res.json(); }
            catch(e){
                const t = await res.text();
                throw new Error('Backend вернул не JSON:\n'+t.slice(0,500));
            }
            if(!data.ok) throw new Error(data.error||'Ошибка загрузки');

            // очистка
            plan.clear(); countsByTeam.clear(); hoursByTeam.clear();
            document.querySelectorAll('#daysGrid .dropzone').forEach(dz=>dz.innerHTML='');
            document.querySelectorAll('.totB').forEach(el=>el.textContent='0');
            document.querySelectorAll('.hrsB').forEach(el=>el.textContent='0.0');
            document.querySelectorAll('.hrsHeights').forEach(el=>el.textContent='');
            document.querySelectorAll('.tot').forEach(el=>el.textContent='0');
            document.querySelectorAll('.hrs').forEach(el=>el.textContent='0.0');
            resetPillsToBase();

            const days = Object.keys(data.plan||{}).sort();
            days.forEach(d=> ensureDay(d));

            const used = new Map();
            for (const day of days){
                ['1','2'].forEach(team=>{
                    (data.plan[day][team]||[]).forEach(it=>{
                        const pill = document.querySelector(`.pill[data-source-date="${cssEscape(it.source_date)}"][data-filter="${cssEscape(it.filter)}"]`);
                        const rate = pill ? (parseInt(pill.dataset.rate||'0',10)||0) : 0;
                        const height = pill ? (pill.dataset.height || '') : '';
                        addRowElement(day, team, it.source_date, it.filter, +it.count||0, rate, height);
                        const k = it.source_date + '|' + it.filter;
                        used.set(k, (used.get(k)||0) + (+it.count||0));
                    });
                });
                lastDay = day;
            }

            document.querySelectorAll('.pill').forEach(p=>{
                const base = +p.dataset.avail0 || 0;
                const key  = p.dataset.sourceDate + '|' + p.dataset.filter;
                const rest = Math.max(0, base - (used.get(key)||0));
                updateAvailForPill(p, rest);
            });

            fetchBusyForDays(days);
            applyHeightColors();

            alert('План сборки загружен.');
        }catch(e){
            alert('Не удалось загрузить: '+e.message);
        }
    }

    // Змейка (верх)
    (function(){
        const btn = document.getElementById('btnSnake');
        const topGrid   = document.getElementById('topGrid');
        if (!btn || !topGrid) return;

        const PER_COL = 15;
        let snakeOn = false;
        let originalHTML = null;

        function makeDayBadge(day){
            const d = document.createElement('div');
            d.className = 'dayBadge';
            d.textContent = day;
            d.dataset.isDayBadge = '1';
            return d;
        }

        function enableSnake(){
            if (snakeOn) return;
            snakeOn = true;
            if (originalHTML === null) originalHTML = topGrid.innerHTML;

            const cols = [...topGrid.querySelectorAll('.col')];
            const items = [];
            cols.forEach(col=>{
                const day = col.querySelector('h4')?.textContent?.trim() || '';
                const pills = [...col.querySelectorAll('.pill')];
                if (!pills.length) return;
                items.push(makeDayBadge(day));
                pills.forEach(p => items.push(p));
            });

            topGrid.innerHTML = '';
            topGrid.classList.add('snakeGrid');

            items.forEach((el, idx)=>{
                topGrid.appendChild(el);
                const row = (idx % PER_COL) + 1;
                const col = Math.floor(idx / PER_COL) + 1;
                el.style.gridRow = String(row);
                el.style.gridColumn = String(col);
                if (el.classList.contains('dayBadge')) el.style.marginBottom = '2px';
            });
            applyHeightColors();
        }

        function disableSnake(){
            if (!snakeOn) return;
            snakeOn = false;
            if (originalHTML !== null) {
                topGrid.classList.remove('snakeGrid');
                topGrid.innerHTML = originalHTML;
                originalHTML = null;

                topGrid.querySelectorAll('.pill').forEach(pill=>{
                    updatePillTime(pill);
                    pill.addEventListener('click', (e)=>{
                        if (e.target.closest('.qty')) return;
                        const avail = +pill.dataset.avail || 0;
                        if (avail <= 0) return;
                        const qtyEl = pill.querySelector('.qty');
                        let qty = parseInt(qtyEl?.value ?? avail, 10);
                        if (!Number.isFinite(qty) || qty <= 0) qty = avail;
                        qty = Math.min(qty, avail);
                        if (e.shiftKey && lastDay){
                            addToDay(lastDay, lastTeam, pill, qty);
                        } else {
                            openDatePicker(pill, qty);
                        }
                    });
                    const q = pill.querySelector('.qty');
                    if (q) q.addEventListener('input', ()=> updatePillTime(pill));
                });
                applyHeightColors();
            }
        }

        btn.addEventListener('click', ()=>{
            if (!snakeOn) {
                btn.textContent = 'Обычный режим';
                enableSnake();
            } else {
                btn.textContent = 'Компактный режим';
                disableSnake();
            }
        });
    })();

    // Плотный режим (низ)
    (function(){
        const btnDense = document.getElementById('btnDense');
        if (!btnDense) return;
        let denseOn = false;

        btnDense.addEventListener('click', ()=>{
            denseOn = !denseOn;
            document.body.classList.toggle('dense', denseOn);
            btnDense.textContent = denseOn ? 'Обычный режим' : 'Плотный режим';
        });
    })();

    // первичная подсветка
    applyHeightColors();
    
    // ===== ФУНКЦИОНАЛ ПЛАВАЮЩЕЙ ПАНЕЛИ =====
    (function() {
        let isDragging = false;
        let currentX, currentY, initialX, initialY;
        let isMinimized = false;
        
        const panel = document.getElementById('floating-panel');
        const panelHeader = document.getElementById('panel-header');
        
        if (!panel || !panelHeader) return;
        
        panelHeader.addEventListener('mousedown', dragStart);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', dragEnd);
        
        function dragStart(e) {
            if (e.target === panelHeader || e.target.classList.contains('floating-panel-title')) {
                isDragging = true;
                const rect = panel.getBoundingClientRect();
                initialX = e.clientX - rect.left;
                initialY = e.clientY - rect.top;
            }
        }
        
        function drag(e) {
            if (isDragging) {
                e.preventDefault();
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;
                panel.style.left = currentX + 'px';
                panel.style.top = currentY + 'px';
                panel.style.transform = 'none';
            }
        }
        
        function dragEnd() {
            isDragging = false;
        }
        
        window.minimizePanel = function() {
            const content = document.querySelector('.floating-panel-content');
            if (isMinimized) {
                content.style.display = 'block';
                isMinimized = false;
            } else {
                content.style.display = 'none';
                isMinimized = true;
            }
        };
    })();
</script>
