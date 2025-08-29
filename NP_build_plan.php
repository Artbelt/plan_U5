<?php
// NP_build_plan.php — план сборки (две бригады) + расчёт времени (часы) по норме смены

$dsn  = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root";
$pass = "";

/* ===================== AJAX save/load ===================== */
if (isset($_GET['action']) && in_array($_GET['action'], ['save','load'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    try{
        $pdo = new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);

        // auto-migrate: build_plan + brigade
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

        // add brigade if missing
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

    } catch(Throwable $e){
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
    }
    exit;
}

/* ===================== обычная страница ===================== */
$order = $_GET['order'] ?? '';
if ($order==='') { http_response_code(400); exit('Укажите ?order=...'); }

try{
    $pdo = new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);

    // источник: corrugation_plan + норма смены (build_complexity как "шт/смену")
    $src = $pdo->prepare("
        SELECT
          cp.plan_date     AS source_date,
          cp.filter_label  AS filter,
          SUM(cp.count)    AS planned,
          NULLIF(COALESCE(sfs.build_complexity, 0), 0) AS rate_per_shift
        FROM corrugation_plan cp
        LEFT JOIN salon_filter_structure sfs
          ON sfs.filter = cp.filter_label
        WHERE cp.order_number = ?
        GROUP BY cp.plan_date, cp.filter_label
        ORDER BY cp.plan_date, cp.filter_label
    ");
    $src->execute([$order]);
    $rowsSrc = $src->fetchAll();

    // уже разложено (сумма по бригадам)
    $bp  = $pdo->prepare("SELECT source_date, filter, SUM(count) AS assigned
                          FROM build_plan WHERE order_number=?
                          GROUP BY source_date, filter");
    $bp->execute([$order]);
    $rowsAssigned = $bp->fetchAll();
    $assignedMap = [];
    foreach($rowsAssigned as $r){
        $assignedMap[$r['source_date'].'|'.$r['filter']] = (int)$r['assigned'];
    }

    // верхние плашки
    $pool = []; $srcDates = [];
    foreach($rowsSrc as $r){
        $d = $r['source_date'];
        $flt = $r['filter'];
        $planned = (int)$r['planned'];
        $used = (int)($assignedMap[$d.'|'.$flt] ?? 0);
        $avail = max(0, $planned - $used);
        $srcDates[$d] = true;

        $pool[$d][] = [
            'key'         => md5($d.'|'.$flt),
            'source_date' => $d,
            'filter'      => $flt,
            'available'   => $avail,
            'rate'        => $r['rate_per_shift'] ? (int)$r['rate_per_shift'] : 0, // шт/смену (11.5 ч)
        ];
    }
    $srcDates = array_keys($srcDates); sort($srcDates);

    // предварительный план
    $prePlan = []; // $prePlan[day]['1'][] , $prePlan[day]['2'][]
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

    // какие дни показать
    $buildDays = array_keys($prePlan);
    sort($buildDays);
    if (!$buildDays) { $buildDays = $srcDates ?: []; }
    if (!$buildDays) {
        $start = new DateTime();
        for($i=0;$i<7;$i++){ $buildDays[] = $start->format('Y-m-d'); $start->modify('+1 day'); }
    }

} catch(Throwable $e){
    http_response_code(500); echo 'Ошибка: '.htmlspecialchars($e->getMessage()); exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
?>
<!doctype html>
<meta charset="utf-8">
<title>План сборки (2 бригады) — заявка <?=h($order)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root{ --line:#e5e7eb; --bg:#f7f9fc; --card:#fff; --muted:#6b7280; --accent:#2563eb; --ok:#16a34a; }
    :root{
        /* ... твои переменные ... */
        --brig1-bg: #faf0da; /* бледно-жёлтый */
        --brig1-bd:#F3E8A1;
        --brig2-bg:#EEF5FF; /* бледно-синий */
        --brig2-bd:#CFE0FF;
    }
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

    .grid{display:grid;grid-template-columns:repeat(<?=count($srcDates)?:1?>,minmax(260px,1fr));gap:10px}
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
    .rowCtrls{display:flex;align-items:center;gap:6px}

    .dayFoot{margin-top:6px;font-size:12px;color:#374151}
    .tot,.hrsB,.hrs{font-weight:700}

    .modalWrap{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.35);z-index:1000}
    .modal{background:#fff;border-radius:12px;border:1px solid var(--line);min-width:360px;max-width:600px;max-height:75vh;display:flex;flex-direction:column;overflow:hidden}
    .modalHeader{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid var(--line)}
    .modalTitle{font-weight:600}
    .modalClose{border:1px solid #ccc;background:#f8f8f8;border-radius:8px;padding:4px 8px;cursor:pointer}
    .modalBody{padding:10px;overflow:auto}
    .daysGrid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
    .dayBtn{display:flex;flex-direction:column;gap:4px;padding:10px;border:1px solid #d9e2f1;border-radius:10px;background:#f4f8ff;cursor:pointer;text-align:left}
    .dayBtn:hover{background:#ecf4ff}
    .dayHead{font-weight:600}
    .daySub{font-size:12px;color:#6b7280}
    .teamSwitch{display:flex;gap:8px;margin-bottom:8px}
    .teamBtn{border:1px solid #cbd5e1;background:#f8fafc;border-radius:8px;padding:6px 10px;cursor:pointer}
    .teamBtn.active{outline:2px solid #2563eb}

    .rangeBar{display:flex;gap:8px;align-items:center;margin:8px 0 0}
    .rangeBar input{padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px}

    @media (max-width:560px){ .daysGrid{grid-template-columns:1fr;} .modal{min-width:300px;max-width:92vw;} }
</style>

<div class="wrap">
    <h2>План сборки (две бригады) — заявка <?=h($order)?></h2>

    <!-- ВЕРХ: остатки после гофры -->
    <div class="panel">
        <div class="head">
            <div><b>Доступно к сборке (после гофры)</b> <span class="sub">клик по плашке — выбрать день и бригаду</span></div>
            <div class="muted">
                <?php
                $availCount=0; foreach($pool as $list){ foreach($list as $it){ $availCount+=$it['available']; } }
                echo 'Всего доступно: <b>'.number_format($availCount,0,'.',' ').'</b> шт';
                ?>
            </div>
        </div>
        <div class="grid" id="topGrid">
            <?php foreach($srcDates as $d): ?>
                <div class="col">
                    <h4><?=h($d)?></h4>
                    <?php if (empty($pool[$d])): ?>
                        <div class="muted">нет остатков</div>
                    <?php else: foreach ($pool[$d] as $p): ?>
                        <div class="pill<?= ($p['available']<=0 ? ' disabled' : '') ?>"
                             data-key="<?=h($p['key'])?>"
                             data-source-date="<?=h($p['source_date'])?>"
                             data-filter="<?=h($p['filter'])?>"
                             data-avail="<?=$p['available']?>"
                             data-rate="<?=$p['rate']?>"
                             title="Клик — добавить в день сборки">
                            <div class="pillTop">
                                <div>
                                    <div class="pillName"><?=h($p['filter'])?></div>
                                    <div class="pillSub">
                                        Доступно: <b class="av"><?=$p['available']?></b> шт ·
                                        Время: ~<b class="time">0.0</b> ч
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

    <!-- НИЗ: дни сборки (две бригады) -->
    <div class="panel">
        <div class="head">
            <b>Сетка дней сборки</b>
            <div style="display:flex;gap:8px;align-items:center">
                <button class="btn secondary" id="btnAddRange">Добавить дни</button>
                <button class="btn secondary" id="btnLoad">Загрузить план</button>
                <button class="btn" id="btnSave">Сохранить план</button>
            </div>
        </div>

        <div id="daysGrid" class="gridDays">
            <?php foreach($buildDays as $d): ?>
                <div class="col" data-day="<?=h($d)?>">
                    <h4><?=h($d)?></h4>
                    <div class="brigWrap">
                        <div class="brig brig1">
                            <h5>Бригада 1:
                                <span class="totB" data-totb="<?=h($d)?>|1">0</span> шт ·
                                Время: <span class="hrsB" data-hrsb="<?=h($d)?>|1">0.0</span> ч
                            </h5>
                            <div class="dropzone" data-day="<?=h($d)?>" data-team="1"></div>
                        </div>
                        <div class="brig brig2">
                            <h5>Бригада 2:
                                <span class="totB" data-totb="<?=h($d)?>|2">0</span> шт ·
                                Время: <span class="hrsB" data-hrsb="<?=h($d)?>|2">0.0</span> ч
                            </h5>
                            <div class="dropzone" data-day="<?=h($d)?>" data-team="2"></div>
                        </div>
                    </div>
                    <div class="dayFoot">
                        Итого за день:
                        <span class="tot" data-tot-day="<?=h($d)?>">0</span> шт ·
                        Время: <span class="hrs" data-hrs-day="<?=h($d)?>">0.0</span> ч
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="rangeBar" id="rangeBar" style="display:none">
            <label>Старт: <input type="date" id="rangeStart"></label>
            <label>Дней: <input type="number" id="rangeDays" min="1" value="5"></label>
            <button class="btn secondary" id="btnDoRange">Добавить</button>
            <button class="btn" id="btnHideRange">Готово</button>
        </div>

        <div class="sub" style="margin-top:8px">
            Удаляя позицию из дня/бригады, её количество возвращается в «Доступно» наверху.
        </div>
    </div>
</div>

<!-- Модалка выбора дня/бригады -->
<div class="modalWrap" id="datePicker">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dpTitle">
        <div class="modalHeader">
            <div class="modalTitle" id="dpTitle">Выберите день и бригаду</div>
            <button class="modalClose" id="dpClose" title="Закрыть">×</button>
        </div>
        <div class="modalBody">
            <div class="teamSwitch">
                <button class="teamBtn" id="team1">Бригада 1</button>
                <button class="teamBtn" id="team2">Бригада 2</button>
                <div style="flex:1"></div>
                <label>Кол-во: <input id="dpQty" type="number" min="1" step="1" value="1" style="padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px;width:100px"></label>
            </div>
            <div class="daysGrid" id="dpDays"></div>
        </div>
    </div>
</div>

<script>
    const ORDER = <?= json_encode($order) ?>;
    const SHIFT_HOURS = 11.5; // длительность смены, ч

    // ===== in-memory =====
    // plan.get(day) => { '1': [ {source_date, filter, count, rate} ], '2': [...] }
    const plan   = new Map();
    // агрегаты по бригадам и дню (шт и часы)
    const countsByTeam = new Map(); // {'1':cnt,'2':cnt,'sum':cnt}
    const hoursByTeam  = new Map(); // {'1':hrs,'2':hrs,'sum':hrs}

    let lastDay  = null;
    let lastTeam = '1';

    const prePlan = <?= json_encode($prePlan, JSON_UNESCAPED_UNICODE) ?>;

    // сохранить базовые доступности
    document.querySelectorAll('.pill').forEach(p=>{
        if (!p.dataset.avail0) p.dataset.avail0 = p.dataset.avail || '0';
    });

    // ===== helpers =====
    function cssEscape(s){ return String(s).replace(/["\\]/g, '\\$&'); }
    function escapeHtml(s){ return (s??'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
    function fmtH(x){ return (Math.round((x||0)*10)/10).toFixed(1); }

    function ensureDay(day){
        if(!plan.has(day)) plan.set(day, {'1':[], '2':[]});
        if(!countsByTeam.has(day)) countsByTeam.set(day, {'1':0,'2':0,'sum':0});
        if(!hoursByTeam.has(day))  hoursByTeam.set(day,  {'1':0,'2':0,'sum':0});

        // создать колонки в DOM при необходимости
        if (!document.querySelector(`.col[data-day="${cssEscape(day)}"]`)){
            const col = document.createElement('div'); col.className='col'; col.dataset.day = day;
            col.innerHTML = `
              <h4>${escapeHtml(day)}</h4>
              <div class="brigWrap">
                <div class="brig brig1">
                  <h5>Бригада 1:
                    <span class="totB" data-totb="${escapeHtml(day)}|1">0</span> шт ·
                    Время: <span class="hrsB" data-hrsb="${escapeHtml(day)}|1">0.0</span> ч
                  </h5>
                  <div class="dropzone" data-day="${escapeHtml(day)}" data-team="1"></div>
                </div>
                <div class="brig brig2">
                  <h5>Бригада 2:
                    <span class="totB" data-totb="${escapeHtml(day)}|2">0</span> шт ·
                    Время: <span class="hrsB" data-hrsb="${escapeHtml(day)}|2">0.0</span> ч
                  </h5>
                  <div class="dropzone" data-day="${escapeHtml(day)}" data-team="2"></div>
                </div>
              </div>
              <div class="dayFoot">
                Итого за день:
                <span class="tot" data-tot-day="${escapeHtml(day)}">0</span> шт ·
                Время: <span class="hrs" data-hrs-day="${escapeHtml(day)}">0.0</span> ч
              </div>
            `;
            document.getElementById('daysGrid').appendChild(col);
        }
        refreshTotalsDOM(day);
    }

    function refreshTotalsDOM(day){
        const c = countsByTeam.get(day) || {'1':0,'2':0,'sum':0};
        const h = hoursByTeam.get(day)  || {'1':0,'2':0,'sum':0};

        const el1 = document.querySelector(`.totB[data-totb="${cssEscape(day)}|1"]`);
        const el2 = document.querySelector(`.totB[data-totb="${cssEscape(day)}|2"]`);
        const eh1 = document.querySelector(`.hrsB[data-hrsb="${cssEscape(day)}|1"]`);
        const eh2 = document.querySelector(`.hrsB[data-hrsb="${cssEscape(day)}|2"]`);
        const edC = document.querySelector(`.tot[data-tot-day="${cssEscape(day)}"]`);
        const edH = document.querySelector(`.hrs[data-hrs-day="${cssEscape(day)}"]`);

        if (el1) el1.textContent = String(c['1']||0);
        if (el2) el2.textContent = String(c['2']||0);
        if (eh1) eh1.textContent = fmtH(h['1']||0);
        if (eh2) eh2.textContent = fmtH(h['2']||0);
        if (edC) edC.textContent = String((c['1']||0)+(c['2']||0));
        if (edH) edH.textContent = fmtH((h['1']||0)+(h['2']||0));
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
        const map = new Map(); // key = src|filter -> used (по штукам)
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

    // ===== верхние плашки: пересчёт времени
    function updatePillTime(pill){
        const rate = +pill.dataset.rate || 0;          // шт/смену
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
    function addRowElement(day, team, src, flt, count, rate){
        ensureDay(day);
        const dz = document.querySelector(`.dropzone[data-day="${cssEscape(day)}"][data-team="${cssEscape(team)}"]`);
        if (!dz) return;

        const r = Math.max(0, +rate || 0);            // шт/смену
        const rowHours = r>0 ? (count / r) * SHIFT_HOURS : 0;

        plan.get(day)[team].push({source_date:src, filter:flt, count:count, rate:r});

        const row = document.createElement('div');
        row.className = 'rowItem';
        row.dataset.day = day;
        row.dataset.team = team;
        row.dataset.sourceDate = src;
        row.dataset.filter = flt;
        row.dataset.count = count;
        row.dataset.rate  = r;
        row.dataset.hours = rowHours;
        row.innerHTML = `
        <div class="rowLeft">
            <div><b>${escapeHtml(flt)}</b></div>
            <div class="sub">
                Кол-во: <b class="cnt">${count}</b> шт ·
                Время: <b class="h">${fmtH(rowHours)}</b> ч
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
    }

    function removeRow(row){
        const day = row.dataset.day;
        const team= row.dataset.team;
        const src = row.dataset.sourceDate;
        const flt = row.dataset.filter;
        const cnt = +row.dataset.count || 0;
        const r   = +row.dataset.rate  || 0;
        const hrs = +row.dataset.hours || 0;

        // память
        const arr = plan.get(day)?.[team] || [];
        const i = arr.findIndex(x=> x.source_date===src && x.filter===flt && x.count===cnt && (x.rate||0)===r);
        if (i>=0){ arr.splice(i,1); plan.get(day)[team] = arr; }

        // вернуть доступность наверху
        const sel = `.pill[data-source-date="${cssEscape(src)}"][data-filter="${cssEscape(flt)}"]`;
        const pill = document.querySelector(sel);
        if (pill){
            const av = (+pill.dataset.avail||0) + cnt;
            updateAvailForPill(pill, av);
        }

        incTotals(day, team, -cnt, -hrs);
        row.remove();
    }

    function moveRow(row, dir){
        // dir = -1 (влево) или +1 (вправо)
        const days = getAllDays();
        const curDay = row.dataset.day;
        const i = days.indexOf(curDay);
        if (i < 0) return;

        const j = i + (dir < 0 ? -1 : 1);
        if (j < 0 || j >= days.length) return; // крайние колонки — не куда двигать

        const newDay = days[j];
        const team   = row.dataset.team;

        // данные строки
        const src = row.dataset.sourceDate;
        const flt = row.dataset.filter;
        const cnt = +row.dataset.count || 0;
        const r   = +row.dataset.rate  || 0;
        const hrs = +row.dataset.hours || 0;

        // убрать из плана текущего дня
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

        // добавить в новый день (создать, если нет)
        ensureDay(newDay);
        plan.get(newDay)[team].push({source_date:src, filter:flt, count:cnt, rate:r});

        // переставить DOM
        const dzNew = document.querySelector(`.dropzone[data-day="${cssEscape(newDay)}"][data-team="${cssEscape(team)}"]`);
        if (dzNew) dzNew.appendChild(row);

        // обновить итоговые показатели
        incTotals(curDay, team, -cnt, -hrs);
        incTotals(newDay, team, +cnt, +hrs);

        // обновить метку дня у строки и "последний день" для шифт-клика
        row.dataset.day = newDay;
        lastDay = newDay;
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
        // подписи «уже назначено» по выбранной бригаде (шт + ч)
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

    function getAllDays(){
        return [...document.querySelectorAll('#daysGrid .col[data-day]')].map(c=>c.dataset.day);
    }

    function openDatePicker(pill, qty){
        pending = {pill, qty};
        dpQty.value = String(qty);
        dpDays.innerHTML = '';

        const days = getAllDays();
        days.forEach(d=>{
            const btn = document.createElement('button');
            btn.type='button'; btn.className='dayBtn'; btn.dataset.day = d;
            btn.innerHTML = `<div class="dayHead">${d}</div><div class="daySub"></div>`;
            btn.onclick = ()=>{ addToDay(d, lastTeam, pending.pill, +dpQty.value || 1); closeDatePicker(); };
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

    // ===== клики по верхним плашкам =====
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

    // ===== add to day =====
    function addToDay(day, team, pill, qty){
        const avail = +pill.dataset.avail || 0;
        if (qty<=0 || avail<=0) return;
        const take = Math.min(qty, avail);

        const src  = pill.dataset.sourceDate;
        const flt  = pill.dataset.filter;
        const rate = parseInt(pill.dataset.rate || '0', 10) || 0; // шт/смену

        addRowElement(day, team, src, flt, take, rate);

        const rest = avail - take;
        updateAvailForPill(pill, rest);

        lastDay = day;
    }

    // ===== pre-render saved plan from PHP =====
    (function renderPre(){
        Object.keys(prePlan||{}).forEach(day=>{
            ensureDay(day);
            ['1','2'].forEach(team=>{
                (prePlan[day][team]||[]).forEach(it=>{
                    // норму берём из плашки; если нет — 0 (посчитается как 0.0 ч)
                    const pill = document.querySelector(`.pill[data-source-date="${cssEscape(it.source_date)}"][data-filter="${cssEscape(it.filter)}"]`);
                    const rate = pill ? (parseInt(pill.dataset.rate||'0',10)||0) : 0;
                    addRowElement(day, team, it.source_date, it.filter, +it.count||0, rate);
                });
            });
            lastDay = day;
        });
    })();

    // при инициализации — скорректировать доступность
    (function applyAvailAfterPre(){
        const used = collectUsedFromPlan();
        document.querySelectorAll('.pill').forEach(p=>{
            const base = +p.dataset.avail0 || 0;
            const key  = p.dataset.sourceDate + '|' + p.dataset.filter;
            const rest = Math.max(0, base - (used.get(key)||0));
            updateAvailForPill(p, rest);
        });
    })();

    // ===== добавление диапазона дней =====
    const btnAddRange = document.getElementById('btnAddRange');
    const rangeBar    = document.getElementById('rangeBar');
    const btnDoRange  = document.getElementById('btnDoRange');
    const btnHideRange= document.getElementById('btnHideRange');
    btnAddRange.onclick = ()=>{ rangeBar.style.display='flex'; initRangeStart(); };
    btnHideRange.onclick = ()=>{ rangeBar.style.display='none'; };

    function initRangeStart(){
        const inp = document.getElementById('rangeStart');
        const today = new Date();
        inp.value = today.toISOString().slice(0,10);
    }
    btnDoRange.onclick = ()=>{
        const start = document.getElementById('rangeStart').value;
        const n = Math.max(1, parseInt(document.getElementById('rangeDays').value||'1',10));
        if (!start) return;
        const base = new Date(start+'T00:00:00');
        for(let i=0;i<n;i++){
            const d = new Date(base); d.setDate(base.getDate()+i);
            const ds = d.toISOString().slice(0,10);
            ensureDay(ds);
        }
    };

    // ===== SAVE =====
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

    // ===== LOAD =====
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
                        addRowElement(day, team, it.source_date, it.filter, +it.count||0, rate);
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

            alert('План сборки загружен.');
        }catch(e){
            alert('Не удалось загрузить: '+e.message);
        }
    }
</script>
