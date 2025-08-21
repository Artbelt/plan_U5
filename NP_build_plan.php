<?php
// NP_build_plan.php — план сборки по заказу (верх — остатки после гофры, низ — сетка дней сборки)

$dsn = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root"; $pass = "";

// ============ AJAX save/load ============
if (isset($_GET['action']) && in_array($_GET['action'], ['save','load'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    try{
        $pdo = new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);

        // авто-миграция build_plan
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

        // orders.build_ready
        $hasBuildReadyCol = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='build_ready'")->fetchColumn();
        if (!$hasBuildReadyCol) {
            $pdo->exec("ALTER TABLE orders ADD build_ready TINYINT(1) NOT NULL DEFAULT 0");
        }

        if ($_GET['action']==='load') {
            $order = $_GET['order'] ?? '';
            if ($order==='') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

            $st = $pdo->prepare("SELECT source_date, plan_date, filter, count FROM build_plan WHERE order_number=? ORDER BY plan_date, filter");
            $st->execute([$order]);
            $rows = $st->fetchAll();

            $plan = [];
            foreach($rows as $r){
                $d = $r['plan_date'];
                $plan[$d][] = [
                    'source_date'=>$r['source_date'],
                    'filter'=>$r['filter'],
                    'count'=>(int)$r['count']
                ];
            }
            echo json_encode(['ok'=>true,'plan'=>$plan]); exit;
        }

        if ($_GET['action']==='save') {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!$data || !isset($data['order']) || !isset($data['plan'])) {
                http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad payload']); exit;
            }
            $order = (string)$data['order'];
            $plan  = $data['plan']; // { 'YYYY-MM-DD': [ {source_date, filter, count}, ... ] }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM build_plan WHERE order_number=?")->execute([$order]);

            $ins = $pdo->prepare("INSERT INTO build_plan(order_number,source_date,plan_date,filter,count) VALUES (?,?,?,?,?)");
            $rows = 0;
            foreach ($plan as $day=>$items){
                if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $day)) continue;
                if (!is_array($items)) continue;
                foreach ($items as $it){
                    $src = $it['source_date'] ?? null;
                    $flt = $it['filter'] ?? '';
                    $cnt = (int)($it['count'] ?? 0);
                    if (!$src || !preg_match('~^\d{4}-\d{2}-\d{2}$~', $src)) continue;
                    if ($cnt<=0 || $flt==='') continue;
                    $ins->execute([$order, $src, $day, $flt, $cnt]);
                    $rows++;
                }
            }

            // orders.build_ready
            $pdo->prepare("UPDATE orders SET build_ready=? WHERE order_number=?")->execute([$rows>0?1:0, $order]);

            $pdo->commit();
            echo json_encode(['ok'=>true, 'rows'=>$rows]); exit;
        }

    } catch(Throwable $e){
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
    }
    exit;
}

// ============ обычная страница ============
$order = $_GET['order'] ?? '';
if ($order==='') { http_response_code(400); exit('Укажите ?order=...'); }

try{
    $pdo = new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);

    // источник: corrugation_plan (что пришло с гофры)
    $src = $pdo->prepare("SELECT plan_date AS source_date, filter_label, SUM(`count`) AS planned
                          FROM corrugation_plan
                          WHERE order_number=?
                          GROUP BY plan_date, filter_label
                          ORDER BY plan_date, filter_label");
    $src->execute([$order]);
    $rowsSrc = $src->fetchAll();

    // уже разложено в сборку
    $bp  = $pdo->prepare("SELECT source_date, filter, SUM(count) AS assigned
                          FROM build_plan WHERE order_number=?
                          GROUP BY source_date, filter");
    $bp->execute([$order]);
    $rowsAssigned = $bp->fetchAll();
    $assignedMap = [];
    foreach($rowsAssigned as $r){
        $assignedMap[$r['source_date'].'|'.$r['filter']] = (int)$r['assigned'];
    }

    // пул верхних «плашек»: по датам гофры
    $pool = [];   // $pool[date] = [ {key, source_date, filter, available} ]
    $srcDates = [];
    foreach($rowsSrc as $r){
        $d = $r['source_date'];
        $flt = $r['filter_label'];
        $planned = (int)$r['planned'];
        $used = (int)($assignedMap[$d.'|'.$flt] ?? 0);
        $avail = max(0, $planned - $used);
        $srcDates[$d] = true;
        // стало (всегда добавляем, даже если 0):
            $pool[$d][] = [
                'key' => md5($d.'|'.$flt),
                'source_date'=>$d,
                'filter'=>$flt,
                'available'=>$avail  // может быть 0
            ];
    }
    $srcDates = array_keys($srcDates); sort($srcDates);

    // предварительный план (что уже сохранено)
    $prePlan = [];
    $pre = $pdo->prepare("SELECT plan_date, source_date, filter, count
                          FROM build_plan WHERE order_number=? ORDER BY plan_date, filter");
    $pre->execute([$order]);
    while($r=$pre->fetch()){
        $prePlan[$r['plan_date']][] = [
            'source_date'=>$r['source_date'],
            'filter'=>$r['filter'],
            'count'=>(int)$r['count']
        ];
    }

    // какие дни показать внизу изначально
    $buildDays = array_keys($prePlan);
    sort($buildDays);
    if (!$buildDays) { $buildDays = $srcDates ?: []; }
    if (!$buildDays) {
        // если вообще ничего — возьмём семь дней от сегодня
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
<title>План сборки — заявка <?=h($order)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root{ --line:#e5e7eb; --bg:#f7f9fc; --card:#fff; --muted:#6b7280; --accent:#2563eb; --ok:#16a34a; }
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

    /* сетка на весь экран */
    .grid{display:grid;grid-template-columns:repeat(<?=count($srcDates)?:1?>,minmax(260px,1fr));gap:10px}
    .gridDays{display:grid;grid-template-columns:repeat(<?=count($buildDays)?:1?>,minmax(220px,1fr));gap:10px}

    .col{border-left:1px solid var(--line);padding-left:8px;min-height:200px}
    .col h4{margin:0 0 8px;font-weight:600}

    /* верхние плашки */
    .pill{border:1px solid #dbe3f0;background:#eef6ff;border-radius:10px;padding:8px;margin:6px 0;display:flex;flex-direction:column;gap:6px}
    .pillTop{display:flex;align-items:center;gap:10px;justify-content:space-between}
    .pillCtrls{display:none}
    .pillBtn{display:none}
    .qty{width:90px;padding:6px;border:1px solid #c9d4ea;border-radius:8px}

    .pillName{font-weight:600}
    .pillSub{font-size:12px;color:#374151}

    .qty{width:72px;padding:6px;border:1px solid #c9d4ea;border-radius:8px}

    .pillBtn:hover{filter:brightness(.97)}
    .pill.disabled{opacity:.45;filter:grayscale(.15);pointer-events:none}

    /* низ — дни сборки */
    .dropzone{min-height:48px;border:1px dashed var(--line);border-radius:8px;padding:6px}
    .rowItem{display:flex;align-items:center;justify-content:space-between;background:#dff7c7;border:1px solid #bddda2;border-radius:8px;padding:6px 8px;margin:6px 0}
    .rowLeft{display:flex;flex-direction:column}
    .rm{border:1px solid #ccc;background:#fff;border-radius:8px;padding:2px 8px;cursor:pointer}
    .dayFoot{margin-top:6px;font-size:12px;color:#374151}
    .tot{font-weight:700}

    /* модалка выбора дня */
    .modalWrap{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.35);z-index:1000}
    .modal{background:#fff;border-radius:12px;border:1px solid var(--line);min-width:340px;max-width:560px;max-height:75vh;display:flex;flex-direction:column;overflow:hidden}
    .modalHeader{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid var(--line)}
    .modalTitle{font-weight:600}
    .modalClose{border:1px solid #ccc;background:#f8f8f8;border-radius:8px;padding:4px 8px;cursor:pointer}
    .modalBody{padding:10px;overflow:auto}
    .daysGrid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
    .dayBtn{display:flex;flex-direction:column;gap:4px;padding:10px;border:1px solid #d9e2f1;border-radius:10px;background:#f4f8ff;cursor:pointer;text-align:left}
    .dayBtn:hover{background:#ecf4ff}
    .dayHead{font-weight:600}
    .daySub{font-size:12px;color:#6b7280}

    /* добавление дней снизу */
    .rangeBar{display:flex;gap:8px;align-items:center;margin:8px 0 0}
    .rangeBar input{padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px}

    @media (max-width:560px){ .daysGrid{grid-template-columns:1fr;} .modal{min-width:300px;max-width:92vw;} }
</style>

<div class="wrap">
    <h2>План сборки — заявка <?=h($order)?></h2>

    <!-- ВЕРХ: остатки после гофры (по датам гофры) -->
    <div class="panel">
        <div class="head">
            <div><b>Доступно к сборке (после гофры)</b> <span class="sub">выберите количество и отправьте в день сборки</span></div>
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
                             title="Клик — добавить в день сборки">
                            <div class="pillTop">
                                <div>
                                    <div class="pillName"><?=h($p['filter'])?></div>
                                    <div class="pillSub">Доступно: <b class="av"><?=$p['available']?></b> шт</div>
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

    <!-- НИЗ: дни сборки -->
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
                    <div class="dropzone"></div>
                    <div class="dayFoot">Итого за день: <span class="tot" data-tot="<?=h($d)?>">0</span> шт</div>
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
            Удаляя позицию из дня сборки, её количество возвращается в «Доступно» наверху.
        </div>
    </div>
</div>

<!-- Модальное окно выбора дня -->
<div class="modalWrap" id="datePicker">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dpTitle">
        <div class="modalHeader">
            <div class="modalTitle" id="dpTitle">Выберите день сборки</div>
            <button class="modalClose" id="dpClose" title="Закрыть">×</button>
        </div>
        <div class="modalBody">
            <div style="margin-bottom:8px">
                <label>Количество: <input id="dpQty" type="number" min="1" step="1" value="1" style="padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px;width:100px"></label>
            </div>
            <div class="daysGrid" id="dpDays"></div>
        </div>
    </div>
</div>

<script>
    const ORDER = <?= json_encode($order) ?>;

    // ===== in-memory =====
    const plan = new Map();             // day -> array of {source_date, filter, count}
    const totals = new Map();           // day -> sum(count)
    let lastDay = null;

    // preload from PHP ($prePlan)
    const prePlan = <?= json_encode($prePlan, JSON_UNESCAPED_UNICODE) ?>;
    for (const day in prePlan){
        plan.set(day, prePlan[day].map(x => ({...x})));
    }
    recalcTotalsAll();

    // сохранить базовые доступности для «сброса»
    document.querySelectorAll('.pill').forEach(p=>{
        if (!p.dataset.avail0) p.dataset.avail0 = p.dataset.avail || '0';
    });

    // ===== helpers =====
    function getAllDays(){
        return [...document.querySelectorAll('#daysGrid .col[data-day]')].map(c=>c.dataset.day);
    }
    function ensureDay(day){
        if(!plan.has(day)) plan.set(day, []);
        // если колонки нет в DOM — создадим
        if (!document.querySelector(`.col[data-day="${cssEscape(day)}"]`)){
            const col = document.createElement('div');
            col.className = 'col'; col.dataset.day = day;
            col.innerHTML = `
                <h4>${escapeHtml(day)}</h4>
                <div class="dropzone"></div>
                <div class="dayFoot">Итого за день: <span class="tot" data-tot="${escapeHtml(day)}">0</span> шт</div>
            `;
            document.getElementById('daysGrid').appendChild(col);
        }
        if (!totals.has(day)) totals.set(day, 0);
        // обновим футер
        const t = document.querySelector(`.tot[data-tot="${cssEscape(day)}"]`);
        if (t) t.textContent = String(totals.get(day)||0);
    }
    function incTotal(day, delta){
        const t = (totals.get(day)||0) + delta;
        totals.set(day, Math.max(0, t));
        const el = document.querySelector(`.tot[data-tot="${cssEscape(day)}"]`);
        if (el) el.textContent = String(totals.get(day) || 0);
    }
    function recalcTotalsAll(){
        totals.clear();
        getAllDays().forEach(d=> totals.set(d,0));
        plan.forEach((arr,day)=>{
            const s = arr.reduce((a,x)=>a+(x.count||0),0);
            totals.set(day, s);
        });
        totals.forEach((v,day)=>{
            const el = document.querySelector(`.tot[data-tot="${cssEscape(day)}"]`);
            if (el) el.textContent = String(v||0);
        });
    }
    function updateAvailForPill(pill, newAvail){
        const avEl = pill.querySelector('.av');
        if (avEl) avEl.textContent = String(newAvail);

        const qty = pill.querySelector('.qty');
        if (qty){
            qty.max = String(newAvail);
            qty.value = String(newAvail > 0 ? newAvail : 1);
        }
        pill.dataset.avail = String(newAvail);
        pill.classList.toggle('disabled', newAvail<=0);
    }
    function resetPillsToBase(){
        document.querySelectorAll('.pill').forEach(p=>{
            const base = +p.dataset.avail0 || 0;
            updateAvailForPill(p, base);
        });
    }

    // --- клики по плашке (добавление) ---
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
                addToDay(lastDay, pill, qty);
            } else {
                openDatePicker(pill, qty);
            }
        });
    });

    // ===== рендер сохранённого плана (с сервера при загрузке страницы) =====
    (function renderPre(){
        for (const day in prePlan){
            ensureDay(day);
            const dz = document.querySelector(`.col[data-day="${cssEscape(day)}"] .dropzone`);
            if (!dz) continue;
            for (const it of prePlan[day]){
                addRowElement(dz, day, it.source_date, it.filter, it.count);
            }
        }
    })();

    // при инициализации надо уменьшить доступность в верхних плашках
    (function applyAvailAfterPre(){
        resetPillsToBase();
        const used = collectUsedFromPlan();
        document.querySelectorAll('.pill').forEach(p=>{
            const key = p.dataset.sourceDate + '|' + p.dataset.filter;
            const base = +p.dataset.avail0 || 0;
            const rest = Math.max(0, base - (used.get(key)||0));
            updateAvailForPill(p, rest);
        });
    })();

    function collectUsedFromPlan(){
        const map = new Map(); // key = src|filter -> used
        plan.forEach(arr=>{
            arr.forEach(r=>{
                const k = r.source_date + '|' + r.filter;
                map.set(k, (map.get(k)||0) + (r.count||0));
            });
        });
        return map;
    }

    // ===== создание строки в дне =====
    function addRowElement(dz, day, src, flt, count){
        // в память
        ensureDay(day);
        plan.get(day).push({source_date:src, filter:flt, count:count});

        // в DOM
        const row = document.createElement('div');
        row.className = 'rowItem';
        row.dataset.day = day;
        row.dataset.sourceDate = src;
        row.dataset.filter = flt;
        row.dataset.count = count;
        row.innerHTML = `
            <div class="rowLeft">
                <div><b>${escapeHtml(flt)}</b></div>
                <div class="sub">Источник: ${escapeHtml(src)} · Кол-во: <b class="cnt">${count}</b> шт</div>
            </div>
            <button class="rm">×</button>
        `;
        dz.appendChild(row);
        row.querySelector('.rm').onclick = ()=> removeRow(row);

        incTotal(day, count);
    }

    // ===== добавление в день =====
    function addToDay(day, pill, qty){
        const avail = +pill.dataset.avail || 0;
        if (qty<=0 || avail<=0){ return; }
        const take = Math.min(qty, avail);

        const src = pill.dataset.sourceDate;
        const flt = pill.dataset.filter;

        const dz = document.querySelector(`.col[data-day="${cssEscape(day)}"] .dropzone`);
        ensureDay(day);
        addRowElement(dz, day, src, flt, take);

        const rest = avail - take;
        updateAvailForPill(pill, rest);

        lastDay = day;
    }

    function removeRow(row){
        const day = row.dataset.day;
        const src = row.dataset.sourceDate;
        const flt = row.dataset.filter;
        const cnt = +row.dataset.count || 0;

        // удалить из plan (по одному совпадению)
        const arr = plan.get(day) || [];
        const i = arr.findIndex(x=> x.source_date===src && x.filter===flt && x.count===cnt);
        if (i>=0){ arr.splice(i,1); plan.set(day, arr); }

        // вернуть в доступность
        const selector = `.pill[data-source-date="${cssEscape(src)}"][data-filter="${cssEscape(flt)}"]`;
        const pill = document.querySelector(selector);
        if (pill){
            const av = (+pill.dataset.avail||0) + cnt;
            updateAvailForPill(pill, av);
        }

        incTotal(day, -cnt);
        row.remove();
    }

    // ===== модалка выбора дня =====
    const dpWrap  = document.getElementById('datePicker');
    const dpDays  = document.getElementById('dpDays');
    const dpQty   = document.getElementById('dpQty');
    const dpClose = document.getElementById('dpClose');
    let pending = null;

    function openDatePicker(pill, qty){
        pending = {pill, qty};
        dpQty.value = String(qty);
        dpDays.innerHTML = '';
        const days = getAllDays();
        days.forEach(d=>{
            const s = totals.get(d) || 0;
            const btn = document.createElement('button');
            btn.type='button'; btn.className='dayBtn';
            btn.innerHTML = `<div class="dayHead">${d}</div><div class="daySub">Уже назначено: ${s} шт</div>`;
            if (d===lastDay) btn.style.outline = '2px solid #2563eb';
            btn.onclick = ()=>{ addToDay(d, pending.pill, +dpQty.value || 1); closeDatePicker(); };
            dpDays.appendChild(btn);
        });
        dpWrap.style.display='flex';
    }
    function closeDatePicker(){ dpWrap.style.display='none'; pending=null; }
    dpClose.onclick = closeDatePicker;
    dpWrap.addEventListener('click', e=>{ if(e.target===dpWrap) closeDatePicker(); });
    document.addEventListener('keydown', e=>{ if(e.key==='Escape' && dpWrap.style.display==='flex') closeDatePicker(); });

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
        plan.forEach((arr,day)=>{
            if (!arr || !arr.length) return;
            payload[day] = arr.map(x=>({source_date:x.source_date, filter:x.filter, count:x.count}));
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

            // 1) очистить низ и память
            plan.clear();
            totals.clear();
            document.querySelectorAll('#daysGrid .dropzone').forEach(dz=>dz.innerHTML='');
            document.querySelectorAll('#daysGrid .tot').forEach(el=>el.textContent='0');

            // 2) сбросить верхние плашки к базовой доступности
            resetPillsToBase();

            // 3) убедиться, что есть колонки для всех дней из ответа
            const days = Object.keys(data.plan||{});
            days.sort();
            days.forEach(d=> ensureDay(d));

            // 4) заполнить дни и посчитать использование
            const used = new Map(); // key = src|filter -> used
            for (const day of days){
                const dz = document.querySelector(`.col[data-day="${cssEscape(day)}"] .dropzone`);
                const items = data.plan[day]||[];
                for (const it of items){
                    addRowElement(dz, day, it.source_date, it.filter, +it.count||0);
                    const k = it.source_date + '|' + it.filter;
                    used.set(k, (used.get(k)||0) + (+it.count||0));
                }
                lastDay = day; // последний из загруженных — для Shift-добавления
            }

            // 5) уменьшить доступности наверху
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

    // ===== utils =====
    function escapeHtml(s){ return (s??'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
    function cssEscape(s){ return String(s).replace(/["\\]/g, '\\$&'); }

    // Пересчёт тоталов после начального рендера
    recalcTotalsAll();
</script>
