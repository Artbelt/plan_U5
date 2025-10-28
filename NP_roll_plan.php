<?php
// NP_roll_plan.php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$order = $_GET['order'] ?? '';
if ($order === '') { http_response_code(400); exit('Укажите ?order=...'); }

/* авто-миграции: roll_plans + orders.plan_ready */
$pdo->exec("CREATE TABLE IF NOT EXISTS roll_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL,
    bale_id INT NOT NULL,
    work_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_bale (order_number, bale_id),
    KEY idx_date (work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$hasPlanReadyCol = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='plan_ready'")->fetchColumn();
if (!$hasPlanReadyCol) {
    $pdo->exec("ALTER TABLE orders ADD plan_ready TINYINT(1) NOT NULL DEFAULT 0");
}

/* Бухты из cut_plans с complexity */
$stmt = $pdo->prepare("SELECT cp.bale_id, cp.filter, cp.height, cp.width, sfs.build_complexity
                       FROM cut_plans cp
                       LEFT JOIN salon_filter_structure sfs ON TRIM(cp.filter) = TRIM(sfs.filter)
                       WHERE cp.order_number = ?
                       ORDER BY cp.bale_id, cp.strip_no");
$stmt->execute([$order]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bales = [];
foreach ($rows as $r) {
    $bid = (int)$r['bale_id'];
    if (!isset($bales[$bid])) $bales[$bid] = ['bale_id'=>$bid, 'strips'=>[]];
    $bales[$bid]['strips'][] = [
        'filter' => $r['filter'],
        'height' => (float)$r['height'],
        'width'  => (float)$r['width'],
        'complexity' => $r['build_complexity'] !== null ? (float)$r['build_complexity'] : null,
    ];
}

/* Уже сохранённые назначения (для первичного заполнения) */
$pre = [];
$st2 = $pdo->prepare("SELECT work_date, bale_id, done FROM roll_plans WHERE order_number=?");
$st2->execute([$order]);
while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
    $d = $r['work_date'];
    if (!isset($pre[$d])) $pre[$d] = [];
    $pre[$d][] = (int)$r['bale_id'];
}

/* Получаем информацию о порезанных бухтах (done=1) */
$doneBales = [];
$st3 = $pdo->prepare("SELECT bale_id FROM roll_plans WHERE order_number=? AND done=1");
$st3->execute([$order]);
while ($r = $st3->fetch(PDO::FETCH_ASSOC)) {
    $doneBales[] = (int)$r['bale_id'];
}

/* Получаем информацию о бухтах, которые должны быть порезаны до текущей даты, но не порезаны */
$overdueBales = [];
$st4 = $pdo->prepare("SELECT bale_id FROM roll_plans WHERE order_number=? AND work_date <= CURDATE() AND (done IS NULL OR done = 0)");
$st4->execute([$order]);
while ($r = $st4->fetch(PDO::FETCH_ASSOC)) {
    $overdueBales[] = (int)$r['bale_id'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Планирование раскроя: <?= htmlspecialchars($order) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{ --leftcol:220px; --daycol:80px; }
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:20px;background:#f7f9fc;color:#111}
        .container{max-width:1200px;margin:0 auto}
        h2{margin:0 0 6px}
        p{margin:0 0 14px;color:#555}
        form{background:#fff;padding:12px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.05);display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        label{font-size:14px;color:#333}
        input[type=date],input[type=number]{padding:6px 10px;border:1px solid #dcdfe5;border-radius:8px}
        .btn{background:#1a73e8;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:14px;cursor:pointer}
        .btn:hover{filter:brightness(.95)}
        .btn-gray{background:#6b7280}

        /* Панель висот (чіпи) */
        #heightBarWrap{margin-top:12px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.05);padding:8px 10px;}
        #heightBarTitle{font-size:12px;color:#555;margin:0 0 6px}
        #heightBar{display:flex;flex-wrap:wrap;gap:6px}
        .hchip{font-size:12px;line-height:1;border:1px solid #d1d5db;border-radius:999px;padding:6px 10px;background:#f9fafb;cursor:pointer;user-select:none;position:relative;padding-bottom:16px}
        .hchip.active{background:#e0f2fe;border-color:#38bdf8;font-weight:600}
        /* відсоток + смужка прогресу всередині чіпа */
        .hchip .hpct{font-size:10px;color:#555;margin-left:6px}
        .hchip .hbar{position:absolute;left:8px;right:8px;bottom:4px;height:4px;background:#e5e7eb;border-radius:999px;overflow:hidden}
        .hchip .hfill{height:100%;width:0;background:#60a5fa;transition:width .2s ease}

        #planArea{margin-top:12px;overflow:auto;max-height:calc(100vh - 300px);background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.05);border:1px solid #e5e7eb}

        table{border-collapse:separate;border-spacing:0;width:100%;table-layout:fixed}
        th,td{border-right:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:6px;font-size:12px;text-align:center;white-space:nowrap;height:26px;background:#fff}
        th{background:#f0f3f8;font-weight:600;position:sticky;top:0;z-index:3}
        th:first-child,td:first-child{width:var(--leftcol);min-width:var(--leftcol);max-width:var(--leftcol);text-align:left;white-space:normal}
        th:not(:first-child),td:not(:first-child){width:var(--daycol)}
        .highlight{background:#d1ecf1 !important;outline:1px solid #0bb}
        .overload{background:#f8d7da !important}
        
        /* Ячейки порезанных бухт */
        td.cell-done{background:#e8f5e9 !important;pointer-events:none;opacity:0.7}

        /* Липкий перший стовпець */
        th:first-child {
            position: sticky !important;
            left: 0 !important;
            z-index: 10 !important;
            background: #f0f3f8;
            width: var(--leftcol);
            min-width: var(--leftcol);
            max-width: var(--leftcol);
            text-align: left;
            white-space: normal;
        }
        td.left-label {
            position: sticky !important;
            left: 0 !important;
            z-index: 9 !important;
            background: #fff;
            width: var(--leftcol);
            min-width: var(--leftcol);
            max-width: var(--leftcol);
            text-align: left;
            white-space: normal;
        }

        /* ПІДСВІТКА обраних бухт (ліва клітинка) */
        td.left-label.bale-picked{background:#fff7cc !important;box-shadow:inset 4px 0 0 #f59e0b}
        
        /* Порезанные бухты (done=1) */
        td.left-label.bale-done{background:#d1f4e0 !important;box-shadow:inset 4px 0 0 #10b981}
        td.left-label.bale-done::after{content:"✓";position:absolute;right:8px;top:50%;transform:translateY(-50%);color:#10b981;font-weight:bold;font-size:16px;z-index:1}
        
        /* Бухты, которые должны быть порезаны, но еще не порезаны */
        td.left-label.bale-overdue{background:#fef3c7 !important;box-shadow:inset 4px 0 0 #f59e0b}
        td.left-label.bale-overdue::after{content:"⚠";position:absolute;right:8px;top:50%;transform:translateY(-50%);color:#f59e0b;font-weight:bold;font-size:16px;z-index:1}
        
        /* Кружочок зі складністю */
        .complexity-badge{
            position:absolute;
            top:8px;
            right:48px;
            width:32px;
            height:32px;
            border-radius:50%;
            background:transparent;
            border:1.5px solid #d1d5db;
            color:#6b7280;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:10px;
            font-weight:600;
            cursor:help;
            z-index:2;
            line-height:1;
        }

        /* тільки окремі висоти */
        .hval{padding:1px 4px;border-radius:4px;margin-right:2px;border:1px solid transparent}
        .hval.active{background:#7dd3fc;color:#052c47;font-weight:700;border-color:#0284c7;box-shadow:0 0 0 2px rgba(2,132,199,.22)}

        #totalsBar{position:sticky;bottom:0;z-index:5;display:grid;grid-auto-rows:32px;align-items:center;background:#f0f3f8;border-top:1px solid #e5e7eb}
        #totalsBar .cell{border-right:1px solid #e5e7eb;text-align:center;font-weight:600;font-size:12px;white-space:nowrap;padding:6px}
        #totalsBar .cell:first-child{text-align:left;padding-left:8px}
        #totalsBar .overload{background:#f8d7da}
        /* чіп висоти */
        .hchip{
            font-size:12px;
            line-height:1;
            border:1px solid #d1d5db;
            border-radius:999px;
            padding:6px 10px;
            background:#f9fafb;
            cursor:pointer;
            user-select:none;

            /* важливо для прогрес-бару */
            position: relative;
            display: inline-block;   /* ← дає коробку для absolute-вкладених */
            padding-bottom: 16px;    /* місце під смужку */
        }

        /* відсоток + смужка прогресу */
        .hchip .hpct{font-size:10px;color:#555;margin-left:6px}
        .hchip .hbar{
            position:absolute;
            left:8px;
            right:8px;
            bottom:4px;
            height:6px;             /* трішки вище для наочності */
            background:#e5e7eb;
            border-radius:999px;
            overflow:hidden;
        }
        .hchip .hfill{
            display:block;          /* гарантія, що займає висоту/ширину */
            height:100%;
            width:0;
            background:#60a5fa;
            transition:width .2s ease;
        }

    </style>
</head>
<body>
<div class="container">
    <h2>Планирование раскроя для заявки <?= htmlspecialchars($order) ?></h2>
    <p><b>Норматив:</b> 1 бухта = <b>40 минут</b> (= 0.67 ч)</p>

    <form onsubmit="event.preventDefault(); drawTable();">
        <label>Дата начала: <input type="date" id="startDate" required></label>
        <label>Дней: <input type="number" id="daysCount" min="1" value="10" required></label>
        <button type="submit" class="btn">Построить таблицу</button>
        <button type="button" class="btn btn-gray" onclick="loadPlanFromDB()">Загрузить из БД</button>
        <button type="button" class="btn" onclick="savePlan()">Сохранить план</button>
        <button type="button" class="btn btn-gray" onclick="selected = Object.create(null); drawTable();">Очистить всё</button>
        <button type="button" class="btn" onclick="window.location.href='NP_cut_index.php'">Вернуться</button>
    </form>

    <div id="heightBarWrap" style="display:none">
        <div id="heightBarTitle">Фільтр за висотами:</div>
        <div id="heightBar"></div>
    </div>

    <div id="planArea"></div>
</div>

<script>
    const PER_BALE_MIN = 40;
    const bales = <?= json_encode(array_values($bales), JSON_UNESCAPED_UNICODE) ?>;
    const preselected = <?= json_encode($pre, JSON_UNESCAPED_UNICODE) ?>;
    const orderNumber = <?= json_encode($order) ?>;
    const doneBales = <?= json_encode($doneBales) ?>;
    const overdueBales = <?= json_encode($overdueBales) ?>;

    // утиліта для id з висотою (14.5 -> "14_5")
    const hid = h => String(h).replace(/\./g, '_');

    // Множина обраних висот у фільтрі
    const selectedHeights = new Set();

    // Всі доступні висоти
    const allHeights = (() => {
        const s = new Set();
        bales.forEach(b => b.strips.forEach(st => s.add(Number(st.height))));
        return Array.from(s).sort((a,b)=>a-b);
    })();

    // Загальна кількість смуг по кожній висоті у всьому замовленні
    const totalStripsByHeight = (() => {
        const m = new Map();
        bales.forEach(b => b.strips.forEach(s => {
            const h = Number(s.height);
            m.set(h, (m.get(h) || 0) + 1);
        }));
        return m; // Map<height, totalCount>
    })();

    function buildHeightBar(){
        const wrap = document.getElementById('heightBarWrap');
        const bar  = document.getElementById('heightBar');
        if(!allHeights.length){ wrap.style.display='none'; return; }
        wrap.style.display='';
        bar.innerHTML='';

        // Скинути
        const reset = document.createElement('span');
        reset.className='hchip';
        reset.textContent='Скинути';
        reset.title='Очистити вибір висот';
        reset.onclick=()=>{
            selectedHeights.clear();
            bar.querySelectorAll('.hchip').forEach(c=>c.classList.remove('active'));
            updateHeightHighlights();
        };
        bar.appendChild(reset);

        // Чіпи висот з % та прогрес-баром
        allHeights.forEach(h=>{
            const id = hid(h);
            const chip = document.createElement('span');
            chip.className='hchip';
            chip.dataset.h = h;
            chip.innerHTML = `[${h}] <span class="hpct" id="hpct-${id}">0%</span>
                               <span class="hbar"><span class="hfill" id="hfill-${id}"></span></span>`;
            chip.onclick=()=>{
                const val = Number(chip.dataset.h);
                if(selectedHeights.has(val)){ selectedHeights.delete(val); chip.classList.remove('active'); }
                else{ selectedHeights.add(val); chip.classList.add('active'); }
                updateHeightHighlights();
            };
            bar.appendChild(chip);
        });
        updateHeightProgress();
    }

    let selected = Object.create(null);
    (function initSelected(){
        const pre = preselected || {};
        for (const [ds, arr] of Object.entries(pre)) {
            selected[ds] = [...new Set((arr||[]).map(n => parseInt(n,10)).filter(n => n>0))];
        }
    })();

    function fmtDateISO(d){
        return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
    }
    function dowName(d){ return ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'][d.getDay()]; }

    function updateHeightHighlights(){
        document.querySelectorAll('.hval').forEach(span=>{
            const h = Number(span.dataset.h);
            if(selectedHeights.has(h)) span.classList.add('active'); else span.classList.remove('active');
        });
    }

    function getSelectedBaleIds(){
        const set = new Set();
        Object.values(selected).forEach(arr => (arr||[]).forEach(id => set.add(id)));
        return set;
    }

    function updateLeftMarkers(){
        const chosen = getSelectedBaleIds();
        document.querySelectorAll('td.left-label').forEach(td=>{
            const bid = parseInt(td.dataset.baleId, 10);
            td.classList.toggle('bale-picked', chosen.has(bid));
        });
    }

    // Порахувати прогрес по кожній висоті і намалювати у чіпах
    function updateHeightProgress(){
        const planned = new Map(); // Map<height, count>
        Object.values(selected).forEach(arr=>{
            (arr||[]).forEach(bid=>{
                const b = bales.find(x=>x.bale_id===bid);
                if(!b) return;
                b.strips.forEach(s=>{
                    const h = Number(s.height);
                    planned.set(h, (planned.get(h)||0)+1);
                });
            });
        });

        allHeights.forEach(h=>{
            const id = hid(h);
            const total = totalStripsByHeight.get(h) || 0;
            const done  = planned.get(h) || 0;
            const pct   = total ? Math.round(done*100/total) : 0;

            const pctEl  = document.getElementById(`hpct-${id}`);
            const fillEl = document.getElementById(`hfill-${id}`);
            if (pctEl)  pctEl.textContent = `${pct}%`;
            if (fillEl) fillEl.style.width = `${pct}%`;

            const chip = document.querySelector(`.hchip[data-h="${h}"]`);
            if (chip) chip.title = `Розплановано: ${done} з ${total} (${pct}%)`;
        });
    }

    function drawTable(){
        const startVal = document.getElementById('startDate').value;
        const days = parseInt(document.getElementById('daysCount').value,10);
        if(!startVal || isNaN(days)) return;

        const start = new Date(startVal+'T00:00:00');
        const container = document.getElementById('planArea');
        container.innerHTML = '';

        if(!bales.length){
            container.innerHTML = '<div style="padding:10px;">Нет бухт по этой заявке.</div>';
            return;
        }

        const table = document.createElement('table');

        // thead
        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        headRow.innerHTML = '<th>Бухта</th>';
        for(let d=0; d<days; d++){
            const date = new Date(start); date.setDate(start.getDate()+d);
            const ds = fmtDateISO(date);
            headRow.innerHTML += `<th>${ds}<span class="dow"><br>${dowName(date)}</span></th>`;
        }
        thead.appendChild(headRow);
        table.appendChild(thead);

        // tbody
        const tbody = document.createElement('tbody');
        bales.forEach(b=>{
            const row = document.createElement('tr');

            const uniqHeights = Array.from(new Set(b.strips.map(s=>Number(s.height))).values());
            
            // Вычисляем среднюю сложность
            const complexities = b.strips
                .map(s => s.complexity)
                .filter(c => c !== null && c !== undefined && !isNaN(c));
            let avgComplexity = null;
            let displayComplexity = null;
            if (complexities.length > 0) {
                avgComplexity = complexities.reduce((sum, c) => sum + c, 0) / complexities.length;
                // Форматируем для отображения
                if (avgComplexity >= 1000) {
                    displayComplexity = (avgComplexity / 1000).toFixed(1) + 'K';
                } else if (avgComplexity % 1 === 0) {
                    displayComplexity = avgComplexity.toString();
                } else {
                    displayComplexity = avgComplexity.toFixed(1);
                }
            }
            
            const tooltip = b.strips
                .map(s => `${s.filter} [${s.height}] ${s.width}мм${s.complexity ? ' (сложность: '+s.complexity+')' : ''}`)
                .join('\n')
                + (avgComplexity ? '\n\nСредняя сложность: ' + avgComplexity.toFixed(1) : '');
            
            const left = document.createElement('td');
            left.className = 'left-label';
            left.dataset.baleId = b.bale_id;
            left.style.position = 'relative';
            
            // Проверяем статус бухты
            const isDone = doneBales.includes(b.bale_id);
            const isOverdue = overdueBales.includes(b.bale_id);
            
            if (isDone) {
                left.classList.add('bale-done');
                left.title = tooltip + '\n\n✓ Порезана';
            } else if (isOverdue) {
                left.classList.add('bale-overdue');
                left.title = tooltip + '\n\n⚠ Должна быть порезана';
            } else {
                left.title = tooltip;
            }
            
            left.innerHTML = '<strong>Бухта '+b.bale_id+'</strong><div class="bale-label">'
                + uniqHeights.map(h=>`<span class="hval" data-h="${h}">[${h}]</span>`).join(' ')
                + '</div>'
                + (displayComplexity ? `<div class="complexity-badge" title="Средняя сложность сборки: ${avgComplexity.toFixed(1)}">${displayComplexity}</div>` : '');
            row.appendChild(left);

            for(let d=0; d<days; d++){
                const date = new Date(start); date.setDate(start.getDate()+d);
                const ds = fmtDateISO(date);
                const td = document.createElement('td');
                td.dataset.date = ds;
                td.dataset.baleId = b.bale_id;

                // Если бухта порезана, делаем ячейки неактивными
                if (isDone) {
                    td.classList.add('cell-done');
                }

                if (selected[ds] && selected[ds].includes(b.bale_id)) td.classList.add('highlight');

                td.onclick = () => {
                    const bid = b.bale_id;
                    // зняти підсвітку з усіх клітинок цієї бухти
                    document.querySelectorAll(`td[data-bale-id="${bid}"]`).forEach(c => c.classList.remove('highlight'));
                    // прибрати зі всіх днів
                    Object.keys(selected).forEach(k=>{
                        const arr = selected[k];
                        if (!arr) return;
                        const i = arr.indexOf(bid);
                        if (i>=0) { arr.splice(i,1); if(!arr.length) delete selected[k]; }
                    });
                    // додати на поточний день
                    if (!selected[ds]) selected[ds] = [];
                    selected[ds].push(bid);
                    td.classList.add('highlight');

                    updateTotals();
                    updateLeftMarkers();
                    updateHeightProgress();   // ← оновити прогрес у чіпах
                };

                row.appendChild(td);
            }

            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        container.appendChild(table);

        // Липкий підсумок годин
        const totalsBar = document.createElement('div');
        totalsBar.id = 'totalsBar';
        totalsBar.style.gridTemplateColumns = `var(--leftcol) ${Array(days).fill('var(--daycol)').join(' ')}`;

        const first = document.createElement('div');
        first.className = 'cell';
        first.innerHTML = '<b>Завантаження (год)</b>';
        totalsBar.appendChild(first);

        for(let d=0; d<days; d++){
            const date = new Date(start); date.setDate(start.getDate()+d);
            const ds = fmtDateISO(date);
            const c = document.createElement('div');
            c.className = 'cell';
            c.id = 'load-'+ds;
            totalsBar.appendChild(c);
        }
        container.appendChild(totalsBar);

        updateTotals();
        updateHeightHighlights();
        updateLeftMarkers();
        updateHeightProgress();  // ← після побудови таблиці
    }

    function updateTotals(){
        document.querySelectorAll('[id^=load-]').forEach(el=>{ el.textContent=''; el.classList.remove('overload'); });
        Object.keys(selected).forEach(ds=>{
            const cnt = (selected[ds]||[]).length;
            const hours = cnt * (PER_BALE_MIN/60);
            const el = document.getElementById('load-'+ds);
            if (el){
                el.textContent = hours.toFixed(2);
                if (hours > 7) el.classList.add('overload');
            }
        });
    }

    function savePlan(){
        // 1) беремо з пам’яті
        let planObj = (selected && !Array.isArray(selected)) ? selected : Object.create(null);

        // 2) якщо пусто — зберемо з DOM (на всякий випадок)
        if (Object.keys(planObj).length === 0) {
            planObj = (function collectPlanFromDOM(){
                const map = Object.create(null);
                document.querySelectorAll('td[data-bale-id].highlight').forEach(td=>{
                    const ds  = td.dataset.date;
                    const bid = parseInt(td.dataset.baleId,10);
                    if (!map[ds]) map[ds] = [];
                    if (!map[ds].includes(bid)) map[ds].push(bid);
                });
                return map;
            })();
        }

        // 3) нормалізація
        const normalized = {};
        for (const [ds, arr] of Object.entries(planObj)) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(ds)) continue;
            if (!Array.isArray(arr)) continue;
            const uniq = [...new Set(arr.map(x => parseInt(x,10)).filter(x => x>0))];
            if (uniq.length) normalized[ds] = uniq;
        }
        if (Object.keys(normalized).length === 0) {
            alert('Нечего сохранять: ни одна бухта не назначена на даты.');
            return;
        }

        const payload = { order: orderNumber, plan: normalized };
        fetch('NP/save_roll_plan.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        })
            .then(async res => {
                const txt = await res.text();
                if (!res.ok) throw new Error(txt || ('HTTP '+res.status));
                if (txt.trim() !== 'ok') throw new Error(txt);
                alert('План сохранён в roll_plans.');
            })
            .catch(e => alert('Ошибка сохранения: ' + e.message));
    }

    async function loadPlanFromDB(){
        try{
            const res = await fetch('NP/get_roll_plan.php?order='+encodeURIComponent(orderNumber), {
                headers:{'Accept':'application/json'}
            });
            const data = await res.json();
            if(!data.ok) throw new Error(data.error || 'Ошибка загрузки');
            if(!data.exists){ alert('Сохранённый план не найден.'); return; }

            // Приведення до об’єкта selected {ds: [baleId,...]}
            const incoming = data.plan || {};
            selected = Object.create(null);
            for (const [ds, arr] of Object.entries(incoming)) {
                selected[ds] = [...new Set((arr||[]).map(n => parseInt(n,10)).filter(n => n>0))];
            }

            // Підігнати діапазон дат під завантажений план
            const keys = Object.keys(selected).sort();
            if(keys.length){
                const minD = keys[0], maxD = keys[keys.length-1];
                document.getElementById('startDate').value = minD;
                const d1=new Date(minD+'T00:00:00'), d2=new Date(maxD+'T00:00:00');
                document.getElementById('daysCount').value = String(Math.max(1, Math.round((d2-d1)/(24*3600*1000))+1));
            }

            // Перемалювати таблицю та оновити індикатори
            drawTable();
            updateHeightHighlights();
            updateLeftMarkers();
            updateHeightProgress();
        }catch(e){
            alert('Не удалось загрузить: '+e.message);
            console.error(e);
        }
    }

    (function(){
        const inp = document.getElementById('startDate');
        const ds = new Date().toISOString().slice(0,10);
        if(!inp.value) inp.value = ds;
        buildHeightBar();
        drawTable();
    })();
</script>
</body>
</html>
