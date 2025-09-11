<?php
/* NP_build_period.php — сводный план на период (У5/1 и У5/2, все заявки)
   Источники: plan_u5.build_plan, salon_filter_structure(build_complexity), manufactured_production
   Период по умолчанию: CURDATE() → MAX(plan_date)
   Две горизонтальные таблицы (У5/1, У5/2). В шапке дня — % загрузки смены.
   Карточки позиций показывают мини-гистограмму: факт этой карточки / план карточки.
   Факт применяется ПО КАЖДОЙ паре (order+filter) слева направо (по датам периода).
*/

$dsn  = 'mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4';
$user = 'root';
$pass = '';

$action = $_GET['action'] ?? '';

if ($action === 'load') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Максимальная дата из плана (если пусто — сегодня)
        $maxRow = $pdo->query("SELECT MAX(plan_date) AS max_date FROM build_plan")->fetch();
        $today  = (new DateTime('today'))->format('Y-m-d');
        $maxDB  = $maxRow && $maxRow['max_date'] ? $maxRow['max_date'] : $today;

        // Границы периода
        $raw   = file_get_contents('php://input');
        $p     = $raw ? json_decode($raw, true) : [];
        $start = (string)($p['start'] ?? ($_GET['start'] ?? $today));
        $end   = (string)($p['end']   ?? ($_GET['end']   ?? $maxDB));

        $d1 = DateTime::createFromFormat('Y-m-d', $start) ?: new DateTime($today);
        $d2 = DateTime::createFromFormat('Y-m-d', $end)   ?: new DateTime($maxDB);
        if ($d2 < $d1) { $tmp = clone $d1; $d1 = clone $d2; $d2 = $tmp; } // swap

        $start = $d1->format('Y-m-d');
        $end   = $d2->format('Y-m-d');

        // Массив дат (включительно)
        $dates = [];
        for ($d = clone $d1; $d <= $d2; $d->modify('+1 day')) $dates[] = $d->format('Y-m-d');

        // ПЛАН (+ норма сложности за 11.5ч по фильтру)
        $sqlPlan = "
            SELECT
                bp.plan_date,
                bp.brigade,
                TRIM(REPLACE(bp.order_number, CHAR(160), ' '))                         AS order_number,
                UPPER(TRIM(REPLACE(bp.order_number, CHAR(160), ' ')))                   AS order_canon,
                TRIM(REPLACE(bp.filter,       CHAR(160), ' '))                          AS filter,
                UPPER(TRIM(REPLACE(bp.filter,       CHAR(160), ' ')))                   AS filter_canon,
                SUM(bp.`count`)                                                         AS qty_plan,
                SUM(bp.fact_count)                                                      AS qty_fact,
                MAX(bp.done)                                                            AS done,
                MAX(COALESCE(sfs.build_complexity, 0))                                  AS complexity_115h
            FROM build_plan bp
            LEFT JOIN salon_filter_structure sfs
              ON UPPER(TRIM(REPLACE(bp.filter, CHAR(160), ' ')))
               = UPPER(TRIM(REPLACE(sfs.filter, CHAR(160), ' ')))
            WHERE bp.plan_date BETWEEN ? AND ? AND bp.brigade IN (1,2)
            GROUP BY bp.plan_date, bp.brigade,
                     TRIM(REPLACE(bp.order_number, CHAR(160), ' ')),
                     UPPER(TRIM(REPLACE(bp.order_number, CHAR(160), ' '))),
                     TRIM(REPLACE(bp.filter,       CHAR(160), ' ')),
                     UPPER(TRIM(REPLACE(bp.filter,       CHAR(160), ' ')))
            ORDER BY bp.plan_date, bp.brigade, order_number, filter
        ";
        $st = $pdo->prepare($sqlPlan);
        $st->execute([$start, $end]);
        $rows = $st->fetchAll();

        // ФАКТ по (бригада, заявка, фильтр) за период (канонизированные ключи!)
        $sqlProdByOF = "
            SELECT
                COALESCE(mp.team, 0)                                              AS brigade,
                UPPER(TRIM(REPLACE(mp.name_of_order,  CHAR(160), ' ')))           AS order_canon,
                UPPER(TRIM(REPLACE(mp.name_of_filter, CHAR(160), ' ')))           AS filter_canon,
                SUM(COALESCE(mp.count_of_filters,0))                              AS produced
            FROM manufactured_production mp
            WHERE mp.date_of_production BETWEEN ? AND ? AND mp.team IN (1,2)
            GROUP BY brigade,
                     UPPER(TRIM(REPLACE(mp.name_of_order,  CHAR(160), ' '))),
                     UPPER(TRIM(REPLACE(mp.name_of_filter, CHAR(160), ' ')))
        ";
        $ps1 = $pdo->prepare($sqlProdByOF);
        $ps1->execute([$start, $end]);
        $prodByOF = $ps1->fetchAll();

        // ФАКТ по дню/бригаде (для заголовка дня)
        $sqlProdByDay = "
            SELECT
                COALESCE(mp.team, 0)                 AS brigade,
                mp.date_of_production                AS d,
                SUM(COALESCE(mp.count_of_filters,0)) AS fact_day
            FROM manufactured_production mp
            WHERE mp.date_of_production BETWEEN ? AND ? AND mp.team IN (1,2)
            GROUP BY brigade, mp.date_of_production
        ";
        $ps2 = $pdo->prepare($sqlProdByDay);
        $ps2->execute([$start, $end]);
        $prodByDay = $ps2->fetchAll();

        echo json_encode([
            'ok'          => true,
            'start'       => $start,
            'end'         => $end,
            'maxDB'       => $maxDB,
            'dates'       => $dates,
            'items'       => $rows,        // план с нормой + канонические ключи
            'prod_by_of'  => $prodByOF,    // факт по (бригада, order_canon, filter_canon)
            'prod_by_day' => $prodByDay,   // факт по (бригада, день)
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Сводный план У5/1 и У5/2 — период</title>
<style>
    :root{
        --bg:#f6f7fb; --card:#fff; --border:#e5e7eb; --text:#111827; --muted:#6b7280;
        --accent:#2563eb; --ok:#10b981; --warn:#f59e0b; --danger:#ef4444;
        --radius:12px; --shadow:0 6px 20px rgba(0,0,0,.06);
        --colW: 200px;

        --topbarH: 0px; --toolbarH: 0px; --boardMaxH: 60vh; --headPad: 0px;
    }

    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font:13px/1.35 system-ui,-apple-system,Segoe UI,Roboto,Arial,Helvetica,sans-serif}

    /* Топбар и тулбар */
    .topbar{ position:sticky; top:0; z-index:50; backdrop-filter:saturate(140%) blur(6px); background:rgba(246,247,251,.85); border-bottom:1px solid var(--border) }
    .topbar__in{max-width:1400px;margin:0 auto;padding:12px 16px;display:flex;align-items:center;gap:12px;justify-content:space-between}
    h1{font:700 16px/1.2 system-ui;margin:0}
    .chips{display:flex;gap:8px;flex-wrap:wrap}
    .chip{background:#eef2ff;border:1px solid #e0e7ff;color:#4338ca;border-radius:999px;padding:4px 8px;font-weight:600}

    .toolbar{ position:sticky; top: var(--topbarH); z-index:45; max-width:1400px; margin:10px auto 0; padding:0 16px }
    .toolbar__in{ background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:10px 12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; box-shadow:var(--shadow) }
    label{display:flex;align-items:center;gap:6px}
    input[type=date]{padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:#fff;outline:none}
    input[type=date]:focus{border-color:var(--accent); box-shadow:0 0 0 3px rgba(37,99,235,.12)}
    .btn{padding:7px 12px;border:1px solid var(--border);border-radius:10px;background:#fff;cursor:pointer;font-weight:600}
    .btn:hover{transform:translateY(-1px); box-shadow:0 4px 16px rgba(0,0,0,.08)}
    .btn-small{padding:5px 8px; font-size:12px}
    .help{color:var(--muted);font-size:12px}

    /* Панель заявок */
    .orders{max-width:1400px;margin:10px auto 0;padding:0 16px}
    .orders__in{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:8px 10px}
    .orders__head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px}
    .orders__title{font-weight:700}
    .orders__tools{display:flex;align-items:center;gap:8px}
    .orders__tools input{padding:6px 10px;border:1px solid var(--border);border-radius:8px}
    .orders__list{
        display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr));
        gap:6px 12px; max-height:180px; overflow:auto; padding-right:4px;
    }
    .orderCheck{display:flex; align-items:center; gap:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
    .orderCheck input{transform:translateY(1px)}
    .orderTag{display:inline-block; padding:1px 6px; border-radius:999px; background:#f3f4f6; border:1px solid var(--border); font-weight:700; font-size:12px; color:#374151}

    /* Две «горизонтальные таблицы» */
    .board{max-width:1400px;margin:12px auto 0;padding:0 16px}
    .board__title{font:700 14px/1.2 system-ui;margin:0 0 6px 2px;color:#111827}
    .board__wrap{
        display:flex; gap:12px;
        overflow-x:auto; overflow-y:auto;
        max-height: var(--boardMaxH);
        border:1px solid var(--border); border-radius:var(--radius);
        background:var(--card); box-shadow:var(--shadow);
        padding-bottom:8px;
    }

    /* Колонки-дни */
    .col{ min-width:var(--colW); max-width:var(--colW); background:var(--card); border-right:1px solid var(--border); display:flex; flex-direction:column }
    .col:last-child{border-right:none}

    /* Шапка дня */
    .col__head{ position:sticky; top:0; background:var(--card); z-index:5; border-bottom:1px solid var(--border); padding:8px 10px }
    .col__date{font:700 13px/1.2 system-ui}
    .col__sum{color:var(--muted);font-size:12px; display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:2px}
    .badgeLoad{
        display:inline-block; min-width:54px; text-align:center;
        padding:2px 6px; border-radius:999px; font-weight:700; font-size:12px; border:1px solid var(--border);
        background:#f3f4f6; color:#374151;
    }
    .badge-ok{ background:rgba(16,185,129,.12); border-color:rgba(16,185,129,.35); color:#065f46 }
    .badge-warn{ background:rgba(245,158,11,.12); border-color:rgba(245,158,11,.35); color:#92400e }
    .badge-over{ background:rgba(239,68,68,.12); border-color:rgba(239,68,68,.35); color:#7f1d1d }
    .badgeLoad .unk{ margin-left:6px; font-weight:800; color:#6b7280; }

    .col__body{ padding: calc(8px + var(--headPad)) 8px 10px }

    /* Карточки позиций + мини-гистограмма */
    .item{
        border:1px solid #e5e7eb;border-radius:10px;padding:6px 8px;margin:6px 0;
        background:#fff;display:flex;justify-content:space-between;gap:8px;
        position:relative; overflow:hidden;
    }
    .item > *{ position:relative; z-index:1; }

    .item__l{min-width:0}
    .item__r{text-align:right;min-width:72px}
    .label{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .filter{color:#111827}
    .qty{font-weight:700}
    .fact{color:#6b7280;font-size:12px}
    .done{color:#10b981;font-weight:700}
    .empty{color:#9ca3af;font-style:italic;padding:4px 2px}

    /* прогресс-бар */
    .bar{
        height:8px; border-radius:999px; background:#eef2ff; overflow:hidden;
        margin-top:4px;
    }
    .bar__fill{
        height:100%; width:0;
        background:linear-gradient(90deg, rgba(16,185,129,.75), rgba(16,185,129,.75));
    }
    .bar__fill.part{ opacity:.9; }
    .bar__fill.full{ opacity:1; }

    .foot{max-width:1400px;margin:10px auto 18px;padding:0 16px;color:#6b7280;font-size:12px}
    @media print {
        @page { size: A4 landscape; margin: 8mm; }

        /* убираем всё лишнее */
        .topbar, .toolbar, .orders, .foot { display:none !important; }

        /* компактнее колонки на печати */
        :root { --colW: 160px; }

        /* контейнеры без скроллов и теней */
        .board{ margin:0; padding:0; }
        .board__wrap{
            overflow: visible !important;
            box-shadow: none !important;
            border: 0 !important;
            max-height: none !important;
        }

        /* шапка дня не липкая на бумаге */
        .col__head{ position: static !important; top:auto !important; box-shadow:none !important; }

        /* не рвать карточки и колонки посередине */
        .col, .item { break-inside: avoid; page-break-inside: avoid; }

        /* полоски прогресса печатаем жирнее, даже в ч/б */
        .bar{ background:#eee !important; }
        .bar__fill{
            background:#4b5563 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* бейджи загрузки — контурные, без пастельного фона */
        .badgeLoad{ background:#fff !important; border-color:#000 !important; color:#000 !important; }

        /* У5/2 — со следующей страницы */
        .board:nth-of-type(3){ break-before: page; } /* если хочешь — добавь класс .board--u52 и используй его */
    }
</style>

<div class="topbar">
    <div class="topbar__in">
        <h1>Сводный план по всем заявкам — У5/1 и У5/2</h1>
        <div class="chips">
            <span class="chip" id="chipRange">—</span>
            <span class="chip" id="chipDays">Дней: 0</span>
            <span class="chip" id="chipItems1">У5/1 позиций: 0</span>
            <span class="chip" id="chipItems2">У5/2 позиций: 0</span>
        </div>
    </div>
</div>

<div class="toolbar">
    <div class="toolbar__in">
        <label>С даты: <input type="date" id="start"></label>
        <label>По дату: <input type="date" id="end"></label>
        <button class="btn" id="btnShow">Показать</button>
        <button class="btn" id="btnPrint" title="Распечатать эту сводку">Печать</button>
        <button class="btn" id="btnTodayMax" title="Сбросить на сегодня → последняя дата в БД">Сегодня→Макс</button>
        <span class="help">Гистограмма = факт карточки / план карточки. Факт распределяется слева направо в пределах пары «заявка+фильтр».</span>
    </div>
</div>

<!-- ПАНЕЛЬ ЗАЯВОК -->
<div class="orders">
    <div class="orders__in">
        <div class="orders__head">
            <div class="orders__title">Заявки в периоде</div>
            <div class="orders__tools">
                <input type="text" id="ordSearch" placeholder="поиск по номеру…">
                <button class="btn btn-small" id="ordAll">Все</button>
                <button class="btn btn-small" id="ordNone">Ничего</button>
            </div>
        </div>
        <div class="orders__list" id="orderList"></div>
    </div>
</div>

<div class="board">
    <h3 class="board__title">У5/1</h3>
    <div class="board__wrap" id="wrap1"></div>
</div>

<div class="board">
    <h3 class="board__title">У5/2</h3>
    <div class="board__wrap" id="wrap2"></div>
</div>

<div class="foot" id="footInfo"></div>

<script>
    const el  = (id)=>document.getElementById(id);
    const esc = (s)=>String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;', "'":'&#039;' }[m]));
    const debounce = (fn, ms=150) => { let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };

    /* канонизация ключей на всякий случай, если понадобится в JS */
    const canon = (s)=>String(s||'').replace(/\u00A0/g,' ').replace(/\s+/g,' ').trim().toUpperCase();
    const itemKeyC = (d,oC,fC)=>`${d}|${oC}|${fC}`;

    function fmtDMY(iso){
        const d=new Date(iso);
        const dd=String(d.getDate()).padStart(2,'0');
        const mm=String(d.getMonth()+1).padStart(2,'0');
        const yyyy=d.getFullYear();
        const w=['Вс','Пн','Вт','Ср','Чт','Пт','Сб'][d.getDay()];
        return `${dd}.${mm}.${yyyy} (${w})`;
    }

    async function loadData(params){
        const r = await fetch(location.pathname+'?action=load', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(params||{}),
            cache: 'no-store'
        });
        const data = await r.json();
        if(!data.ok) throw new Error(data.error||'load failed');
        return data;
    }

    function partitionByBrigade(items){
        const b1=[], b2=[];
        for(const it of (items||[])){
            const b = parseInt(it.brigade,10)||0;
            (b===1?b1:b===2?b2:null)?.push({
                date: it.plan_date,
                order: it.order_number,   // отображаемый
                orderC: it.order_canon,   // для ключей
                filter: it.filter,        // отображаемый
                filterC: it.filter_canon, // для ключей
                qty: parseInt(it.qty_plan,10)||0,
                fact: parseInt(it.qty_fact,10)||0, // справочно
                done: (parseInt(it.done,10)||0)===1,
                cx:   parseFloat(it.complexity_115h||0) || 0 // норма за 11.5ч
            });
        }
        return {b1, b2};
    }

    /* ===== Бейдж загрузки ===== */
    function loadBadgeHTML(percent, unknown){
        const p = Math.round(percent);
        let cls = 'badge-ok';
        if (p > 120) cls = 'badge-over';
        else if (p > 100) cls = 'badge-warn';
        const unk = unknown>0 ? `<span class="unk" title="Нет нормы для ${unknown} позиций">±</span>` : '';
        return `<span class="badgeLoad ${cls}" title="Загрузка смены: ${p}%">${p}%</span>${unk}`;
    }

    /* ===== prod_by_* в Map ===== */
    function mapProdByOF(rows){
        // -> Map<brigade, Map<orderC, Map<filterC, produced>>>
        const outer = new Map();
        for(const r of (rows||[])){
            const b  = parseInt(r.brigade,10)||0;
            const oC = canon(r.order_canon);
            const fC = canon(r.filter_canon);
            const v  = parseInt(r.produced,10)||0;
            if (!outer.has(b)) outer.set(b, new Map());
            const byOrder = outer.get(b);
            if (!byOrder.has(oC)) byOrder.set(oC, new Map());
            const byFilter = byOrder.get(oC);
            byFilter.set(fC, (byFilter.get(fC)||0) + v);
        }
        return outer;
    }
    function mapProdByDay(rows){
        // -> Map<brigade, Map<date, fact>>
        const m = new Map();
        for(const r of (rows||[])){
            const b = parseInt(r.brigade,10)||0;
            const d = String(r.d||'');
            const v = parseInt(r.fact_day,10)||0;
            if (!m.has(b)) m.set(b, new Map());
            m.get(b).set(d, (m.get(b).get(d)||0) + v);
        }
        return m;
    }

    /* ===== Распределение факта по карточкам для каждой пары (заявка+фильтр) ===== */
    // Возвращает:
    //   ratios: key(date|orderC|filterC) -> 0..1
    //   allocs: key(date|orderC|filterC) -> «сколько факта пришло в эту карточку»
    function computeItemFillRatiosByFilter(dates, items, prodOFMap){
        // orderC -> filterC -> date -> item
        const byOF = new Map();
        for (const it of items){
            const oC = canon(it.orderC), fC = canon(it.filterC), d = it.date;
            if (!byOF.has(oC)) byOF.set(oC, new Map());
            const byF = byOF.get(oC);
            if (!byF.has(fC)) byF.set(fC, new Map());
            byF.get(fC).set(d, it);
        }

        const ratios = new Map();
        const allocs = new Map();

        for (const [oC, byF] of byOF.entries()){
            const byFilterProduced = prodOFMap.get(oC) || new Map();
            for (const [fC, dayMap] of byF.entries()){
                let left = byFilterProduced.get(fC) || 0;    // общий факт пары за период
                for (const d of dates){
                    const x = dayMap.get(d);
                    if (!x) continue;
                    const q = x.qty || 0;
                    let ratio = 0, put = 0;
                    if (q > 0 && left > 0){
                        put   = Math.min(left, q);
                        ratio = put / q;
                        left -= put;
                    }
                    const k = itemKeyC(d,oC,fC);
                    ratios.set(k, ratio);
                    allocs.set(k, put);
                }
            }
        }
        return { ratios, allocs };
    }

    /* ====== РЕНДЕР ТАБЛИЦЫ ДЛЯ БРИГАДЫ ====== */
    function renderBoardForBrigade(containerId, dates, items, brigade, prodByOFMap, prodByDayMap){
        // day → список позиций
        const map = new Map(); dates.forEach(d=>map.set(d, []));
        items.forEach(x=>{ if(map.has(x.date)) map.get(x.date).push(x); });

        // Итоги дня (план) и % загрузки (по complexity)
        const daySumPlan = new Map();
        const dayLoadPct = new Map();

        const hours = (brigade===2) ? 8 : 11.5;
        const factor = hours / 11.5;

        for(const d of dates){
            const arr = map.get(d);
            const s = arr.reduce((a,x)=>a+(x.qty||0),0);
            daySumPlan.set(d,s);

            let shiftUnits = 0, unknown = 0;
            for(const x of arr){
                if (x.cx > 0){
                    const norm = x.cx * factor;
                    shiftUnits += x.qty / norm;
                } else {
                    unknown++;
                }
            }
            const pct = shiftUnits * 100;
            dayLoadPct.set(d, { pct, unknown });
        }

        // Коэффициенты/выделения: на КАЖДУЮ карточку (дата×заявка×фильтр)
        const { ratios: itemRatios, allocs: itemAllocs } =
            computeItemFillRatiosByFilter(dates, items, prodByOFMap || new Map());

        const wrap = el(containerId);
        wrap.innerHTML = '';

        for(const d of dates){
            const sums = daySumPlan.get(d)||0;
            const ld   = dayLoadPct.get(d)||{pct:0,unknown:0};
            const factDay = (prodByDayMap && prodByDayMap.get(d)) || 0;

            let html = `<div class="col">`;
            html += `<div class="col__head">
               <div class="col__date">${esc(fmtDMY(d))}</div>
               <div class="col__sum">
                 <span>план: <b>${sums}</b>${factDay?` · факт: <b>${factDay}</b>`:''}</span>
                 ${loadBadgeHTML(ld.pct, ld.unknown)}
               </div>
             </div>`;
            html += `<div class="col__body">`;

            const arr = map.get(d);
            if(!arr.length){
                html += `<div class="empty">нет позиций</div>`;
            } else {
                for(const x of arr){
                    const key = itemKeyC(d, canon(x.orderC), canon(x.filterC));
                    const r   = itemRatios.get(key) || 0;      // доля 0..1
                    const got = itemAllocs.get(key) || 0;      // факт в карточке

                    html += `<div class="item" title="Заявка: #${esc(x.order)} • факт ${got} из ${x.qty} (${Math.round(r*100)}%)">
          <div class="item__l">
            <span class="label"><span class="filter">${esc(x.filter)}</span></span>
            <div class="bar" aria-label="Факт ${got} из ${x.qty}">
              <div class="bar__fill ${r>=1?'full':'part'}" style="width:${Math.min(100, r*100)}%"></div>
            </div>
            <span class="fact">${got ? ('вып: '+got+' / '+x.qty) : ''}</span>
          </div>
          <div class="item__r">
            <div class="qty">${x.qty}</div>
            <div>${x.done ? '<span class="done">готово</span>' : ''}</div>
          </div>
        </div>`;
                }
            }

            html += `</div></div>`;
            wrap.insertAdjacentHTML('beforeend', html);
        }

        return items.length;
    }

    /* ====== СИНХРОНИЗАЦИЯ СКРОЛЛА ====== */
    function syncHorizontalScroll(a, b){
        let lock = false;
        const onA = ()=>{ if(lock) return; lock = true; b.scrollLeft = a.scrollLeft; lock = false; };
        const onB = ()=>{ if(lock) return; lock = true; a.scrollLeft = b.scrollLeft; lock = false; };
        a.addEventListener('scroll', onA, {passive:true});
        b.addEventListener('scroll', onB, {passive:true});
    }

    /* ====== ВЫСОТЫ ====== */
    function recomputeHeights(){
        const tb  = document.querySelector('.topbar');
        const tl  = document.querySelector('.toolbar');
        const topbarH  = tb ? Math.ceil(tb.getBoundingClientRect().height)  : 0;
        const toolbarH = tl ? Math.ceil(tl.querySelector('.toolbar__in').getBoundingClientRect().height) : 0;
        document.documentElement.style.setProperty('--topbarH',  topbarH +'px');
        document.documentElement.style.setProperty('--toolbarH', toolbarH+'px');

        const extra = 18;
        const boardMaxH = Math.max(240, window.innerHeight - topbarH - toolbarH - extra);
        document.documentElement.style.setProperty('--boardMaxH', boardMaxH+'px');

        const head = document.querySelector('.col__head');
        const headH = head ? Math.ceil(head.getBoundingClientRect().height) : 0;
        document.documentElement.style.setProperty('--headPad', headH+'px');
    }

    /* ================= ПАНЕЛЬ ЗАЯВОК (фильтр) ================= */
    let ORDER_SET = new Set();
    let LAST = { dates:[], b1:[], b2:[], prodOF:new Map(), prodDay:new Map() };

    function buildOrderStats(items){
        const map = new Map();
        for(const it of items){
            const o = String(it.order);
            map.set(o, (map.get(o)||0) + 1);
        }
        return Array.from(map.entries())
            .map(([order,count])=>({order, count}))
            .sort((a,b)=> (a.order > b.order ? -1 : a.order < b.order ? 1 : 0));
    }
    function syncSelectionWith(orders){
        const prev = ORDER_SET;
        const next = new Set();
        for(const o of orders){ if(prev.has(o)) next.add(o); }
        if (next.size === 0){ orders.forEach(o=>next.add(o)); }
        ORDER_SET = next;
    }
    function renderOrderPanel(orderStats){
        const list = el('orderList'); list.innerHTML = '';
        const q = el('ordSearch').value.trim().toLowerCase();
        for (const {order, count} of orderStats){
            if (q && !String(order).toLowerCase().includes(q)) continue;
            const id = 'ord_' + order.replace(/[^a-zA-Z0-9_-]/g,'_');
            const checked = ORDER_SET.has(order) ? 'checked' : '';
            const item = `
      <label class="orderCheck" title="Позиции: ${count}">
        <input type="checkbox" id="${id}" data-order="${esc(order)}" ${checked}>
        <span class="orderTag">#${esc(order)}</span>
        <span class="muted" style="color:#6b7280">(${count})</span>
      </label>`;
            list.insertAdjacentHTML('beforeend', item);
        }
        list.querySelectorAll('input[type=checkbox]').forEach(cb=>{
            cb.addEventListener('change', ()=>{
                const ord = cb.dataset.order;
                if (cb.checked) ORDER_SET.add(ord); else ORDER_SET.delete(ord);
                applyOrderFilter();
            });
        });
    }

    function applyOrderFilter(){
        const useAll = (ORDER_SET.size === 0);
        const b1f = useAll ? LAST.b1 : LAST.b1.filter(x=>ORDER_SET.has(String(x.order)));
        const b2f = useAll ? LAST.b2 : LAST.b2.filter(x=>ORDER_SET.has(String(x.order)));

        const prod1 = LAST.prodOF.get(1) || new Map();
        const prod2 = LAST.prodOF.get(2) || new Map();
        const pday1 = LAST.prodDay.get(1) || new Map();
        const pday2 = LAST.prodDay.get(2) || new Map();

        const n1 = renderBoardForBrigade('wrap1', LAST.dates, b1f, 1, prod1, pday1);
        const n2 = renderBoardForBrigade('wrap2', LAST.dates, b2f, 2, prod2, pday2);

        el('chipItems1').textContent = `У5/1 позиций: ${n1}`;
        el('chipItems2').textContent = `У5/2 позиций: ${n2}`;

        recomputeHeights();
    }

    /* ================= ЗАГРУЗКА/РЕНДЕР ================= */
    async function boot(params){
        const data = await loadData(params);

        if(!el('start').value) el('start').value = data.start;
        if(!el('end').value)   el('end').value   = data.end;

        const dates = data.dates||[];
        const {b1, b2} = partitionByBrigade(data.items);

        const prodOF  = mapProdByOF(data.prod_by_of);
        const prodDay = mapProdByDay(data.prod_by_day);

        LAST = { dates, b1, b2, prodOF, prodDay };

        // Список заявок периода
        const allItems = b1.concat(b2);
        const orderStats = buildOrderStats(allItems);
        const orderListArr = orderStats.map(x=>x.order);
        syncSelectionWith(orderListArr);
        renderOrderPanel(orderStats);

        // чипы
        el('chipRange').textContent = `${data.start} → ${data.end}`;
        el('chipDays').textContent  = `Дней: ${dates.length}`;

        // отрисовать с фильтром
        applyOrderFilter();

        // синхронизируем прокрутку
        const w1 = el('wrap1'), w2 = el('wrap2');
        syncHorizontalScroll(w1, w2);
    }

    /* ===== Панель заявок: кнопки ===== */
    el('ordAll').addEventListener('click', ()=>{
        const boxes = Array.from(el('orderList').querySelectorAll('input[type=checkbox]'));
        ORDER_SET = new Set(boxes.map(b=>b.dataset.order));
        boxes.forEach(b=>b.checked = true);
        applyOrderFilter();
    });
    el('ordNone').addEventListener('click', ()=>{
        ORDER_SET.clear();
        el('orderList').querySelectorAll('input[type=checkbox]').forEach(b=>b.checked=false);
        applyOrderFilter();
    });
    el('ordSearch').addEventListener('input', debounce(()=>{
        const allItems = LAST.b1.concat(LAST.b2);
        const orderStats = buildOrderStats(allItems);
        renderOrderPanel(orderStats);
    }, 150));

    /* ===== Период ===== */
    el('btnShow').addEventListener('click', ()=>{
        const start = el('start').value;
        const end   = el('end').value;
        boot({start, end}).catch(e=>alert('Ошибка: '+e.message));
    });
    el('btnTodayMax').addEventListener('click', async ()=>{
        const d = await loadData({});
        el('start').value = d.start;
        el('end').value   = d.end;
        boot({start:d.start, end:d.end}).catch(e=>alert('Ошибка: '+e.message));
    });
    el('btnPrint').addEventListener('click', ()=>window.print());

    /* ===== Старт ===== */
    function recomputeHeights(){  // переопределяем (снизу нужен доступ)
        const tb  = document.querySelector('.topbar');
        const tl  = document.querySelector('.toolbar');
        const topbarH  = tb ? Math.ceil(tb.getBoundingClientRect().height)  : 0;
        const toolbarH = tl ? Math.ceil(tl.querySelector('.toolbar__in').getBoundingClientRect().height) : 0;
        document.documentElement.style.setProperty('--topbarH',  topbarH +'px');
        document.documentElement.style.setProperty('--toolbarH', toolbarH+'px');
        const extra = 18;
        const boardMaxH = Math.max(240, window.innerHeight - topbarH - toolbarH - extra);
        document.documentElement.style.setProperty('--boardMaxH', boardMaxH+'px');
        const head = document.querySelector('.col__head');
        const headH = head ? Math.ceil(head.getBoundingClientRect().height) : 0;
        document.documentElement.style.setProperty('--headPad', headH+'px');
    }
    window.addEventListener('load', ()=>{ recomputeHeights(); });
    window.addEventListener('resize', debounce(recomputeHeights, 120));
    boot({}).catch(e=>alert('Ошибка загрузки: '+e.message));
</script>
