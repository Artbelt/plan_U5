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

/* Бухты из cut_plans */
$stmt = $pdo->prepare("SELECT bale_id, filter, height, width
                       FROM cut_plans
                       WHERE order_number = ?
                       ORDER BY bale_id, strip_no");
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
    ];
}

/* Уже сохранённые назначения (для первичного заполнения) */
$pre = [];
$st2 = $pdo->prepare("SELECT work_date, bale_id FROM roll_plans WHERE order_number=?");
$st2->execute([$order]);
while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
    $d = $r['work_date'];
    if (!isset($pre[$d])) $pre[$d] = [];
    $pre[$d][] = (int)$r['bale_id'];
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
        .hchip{font-size:12px;line-height:1;border:1px solid #d1d5db;border-radius:999px;padding:6px 10px;background:#f9fafb;cursor:pointer;user-select:none}
        .hchip.active{background:#e0f2fe;border-color:#38bdf8;font-weight:600}

        #planArea{margin-top:12px;overflow:auto;max-height:calc(100vh - 300px);background:#fff;border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.05);border:1px solid #e5e7eb}

        table{border-collapse:separate;border-spacing:0;width:100%;table-layout:fixed}
        th,td{border-right:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:6px;font-size:12px;text-align:center;white-space:nowrap;height:26px;background:#fff}
        th{background:#f0f3f8;font-weight:600;position:sticky;top:0;z-index:3}
        th:first-child,td:first-child{width:var(--leftcol);min-width:var(--leftcol);max-width:var(--leftcol);text-align:left;white-space:normal}
        th:not(:first-child),td:not(:first-child){width:var(--daycol)}
        .highlight{background:#d1ecf1 !important;outline:1px solid #0bb}
        .overload{background:#f8d7da !important}
        /* Стало: */
        th:first-child {
            position: sticky;
            left: 0;
            z-index: 5;                 /* вище за ліві td, щоб кутова комірка була над усім */
            background: #f0f3f8;        /* фон хедера */
            width: var(--leftcol);
            min-width: var(--leftcol);
            max-width: var(--leftcol);
            text-align: left;
            white-space: normal;
        }

        /* тільки для лівої клітинки-лейблу бухти */
        td.left-label {
            position: sticky;
            left: 0;
            z-index: 4;
            background: #fff;           /* дефолтний фон */
            width: var(--leftcol);
            min-width: var(--leftcol);
            max-width: var(--leftcol);
            text-align: left;
            white-space: normal;
        }

        /* ПІДСВІТКА обраних бухт тепер точно перекриє фон */
        td.left-label.bale-picked {
            background: #fff7cc !important;
            box-shadow: inset 4px 0 0 #f59e0b;
        }

        .bale-picked{background:#fff7cc;box-shadow:inset 4px 0 0 #f59e0b}

        /* тільки окремі висоти */
        .hval{
            padding:1px 4px;
            border-radius:4px;
            margin-right:2px;
            border:1px solid transparent;          /* щоб при активі не зсувався текст */
        }
        .hval.active{
            background:#7dd3fc;                    /* яскравіше блакитне */
            color:#052c47;
            font-weight:700;
            border-color:#0284c7;                  /* контрастна рамка */
            box-shadow:0 0 0 2px rgba(2,132,199,.22); /* м’яке «світіння» */
        }

        #totalsBar{position:sticky;bottom:0;z-index:5;display:grid;grid-auto-rows:32px;align-items:center;background:#f0f3f8;border-top:1px solid #e5e7eb}
        #totalsBar .cell{border-right:1px solid #e5e7eb;text-align:center;font-weight:600;font-size:12px;white-space:nowrap;padding:6px}
        #totalsBar .cell:first-child{text-align:left;padding-left:8px}
        #totalsBar .overload{background:#f8d7da}
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
    const PER_BALE_MIN=40;
    const bales=<?=json_encode(array_values($bales),JSON_UNESCAPED_UNICODE)?>;
    const preselected=<?=json_encode($pre,JSON_UNESCAPED_UNICODE)?>;
    const orderNumber=<?=json_encode($order)?>;

    const selectedHeights=new Set();
    const allHeights=(()=>{const s=new Set();bales.forEach(b=>b.strips.forEach(st=>s.add(Number(st.height))));return Array.from(s).sort((a,b)=>a-b);})();
    function buildHeightBar(){
        const wrap=document.getElementById('heightBarWrap');const bar=document.getElementById('heightBar');
        if(!allHeights.length){wrap.style.display='none';return;}
        wrap.style.display='';bar.innerHTML='';
        const reset=document.createElement('span');reset.className='hchip';reset.textContent='Скинути';reset.onclick=()=>{selectedHeights.clear();bar.querySelectorAll('.hchip').forEach(c=>c.classList.remove('active'));updateHeightHighlights();};bar.appendChild(reset);
        allHeights.forEach(h=>{const chip=document.createElement('span');chip.className='hchip';chip.textContent='['+h+']';chip.dataset.h=h;chip.onclick=()=>{const val=Number(chip.dataset.h);if(selectedHeights.has(val)){selectedHeights.delete(val);chip.classList.remove('active');}else{selectedHeights.add(val);chip.classList.add('active');}updateHeightHighlights();};bar.appendChild(chip);});
    }

    let selected=Object.create(null);
    (function initSelected(){const pre=preselected||{};for(const [ds,arr] of Object.entries(pre)){selected[ds]=[...new Set((arr||[]).map(n=>parseInt(n,10)).filter(n=>n>0))];}})();

    function fmtDateISO(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
    function dowName(d){return['Вс','Пн','Вт','Ср','Чт','Пт','Сб'][d.getDay()];}

    function updateHeightHighlights(){
        document.querySelectorAll('.hval').forEach(span=>{
            const h=Number(span.dataset.h);
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


    function drawTable(){
        const startVal=document.getElementById('startDate').value;
        const days=parseInt(document.getElementById('daysCount').value,10);
        if(!startVal||isNaN(days))return;
        const start=new Date(startVal+'T00:00:00');
        const container=document.getElementById('planArea');container.innerHTML='';
        if(!bales.length){container.innerHTML='<div style="padding:10px;">Нет бухт по этой заявке.</div>';return;}
        const table=document.createElement('table');
        const thead=document.createElement('thead');
        const headRow=document.createElement('tr');
        headRow.innerHTML='<th>Бухта</th>';
        for(let d=0;d<days;d++){const date=new Date(start);date.setDate(start.getDate()+d);const ds=fmtDateISO(date);headRow.innerHTML+=`<th>${ds}<span class="dow"><br>${dowName(date)}</span></th>`;}
        thead.appendChild(headRow);table.appendChild(thead);
        const tbody=document.createElement('tbody');
        bales.forEach(b=>{
            const row=document.createElement('tr');
            const uniqHeights=Array.from(new Set(b.strips.map(s=>Number(s.height))).values());
            const left=document.createElement('td');left.className='left-label';left.dataset.baleId=b.bale_id;
            left.innerHTML='<strong>Бухта '+b.bale_id+'</strong><div class="bale-label">'+uniqHeights.map(h=>`<span class="hval" data-h="${h}">[${h}]</span>`).join(' ')+'</div>';
            row.appendChild(left);
            for(let d=0;d<days;d++){const date=new Date(start);date.setDate(start.getDate()+d);const ds=fmtDateISO(date);const td=document.createElement('td');td.dataset.date=ds;td.dataset.baleId=b.bale_id;
                if(selected[ds]&&selected[ds].includes(b.bale_id))td.classList.add('highlight');
                td.onclick=()=>{const bid=b.bale_id;document.querySelectorAll(`td[data-bale-id="${bid}"]`).forEach(c=>c.classList.remove('highlight'));Object.keys(selected).forEach(k=>{const arr=selected[k];if(!arr)return;const i=arr.indexOf(bid);if(i>=0){arr.splice(i,1);if(!arr.length)delete selected[k];}});if(!selected[ds])selected[ds]=[];selected[ds].push(bid);td.classList.add('highlight');updateTotals();updateLeftMarkers();};
                row.appendChild(td);}
            tbody.appendChild(row);
        });
        table.appendChild(tbody);container.appendChild(table);
        const totalsBar=document.createElement('div');totalsBar.id='totalsBar';totalsBar.style.gridTemplateColumns=`var(--leftcol) ${Array(days).fill('var(--daycol)').join(' ')}`;
        const first=document.createElement('div');first.className='cell';first.innerHTML='<b>Завантаження (год)</b>';totalsBar.appendChild(first);
        for(let d=0;d<days;d++){const date=new Date(start);date.setDate(start.getDate()+d);const ds=fmtDateISO(date);const c=document.createElement('div');c.className='cell';c.id='load-'+ds;totalsBar.appendChild(c);}
        container.appendChild(totalsBar);
        updateTotals();updateHeightHighlights();updateLeftMarkers();
    }

    function updateTotals(){
        document.querySelectorAll('[id^=load-]').forEach(el=>{el.textContent='';el.classList.remove('overload');});
        Object.keys(selected).forEach(ds=>{const cnt=(selected[ds]||[]).length;const hours=cnt*(PER_BALE_MIN/60);const el=document.getElementById('load-'+ds);if(el){el.textContent=hours.toFixed(2);if(hours>7)el.classList.add('overload');}});
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

            // Перемалювати таблицю та оновити підсвітку висот
            drawTable();
            updateHeightHighlights();
            updateLeftMarkers();
        }catch(e){
            alert('Не удалось загрузить: '+e.message);
            console.error(e);
        }
    }


    (function(){const inp=document.getElementById('startDate');const ds=new Date().toISOString().slice(0,10);if(!inp.value)inp.value=ds;buildHeightBar();drawTable();})();
</script>
</body>
</html>
