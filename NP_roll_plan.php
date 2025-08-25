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
        body{
            font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
            padding:20px;background:#f7f9fc;color:#111
        }
        .container{max-width:1200px;margin:0 auto}
        h2{margin:0 0 6px}
        p{margin:0 0 14px;color:#555}
        form{
            background:#fff;padding:12px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.05);
            display:flex;gap:10px;flex-wrap:wrap;align-items:center
        }
        label{font-size:14px;color:#333}
        input[type=date],input[type=number]{padding:6px 10px;border:1px solid #dcdfe5;border-radius:8px}
        .btn{background:#1a73e8;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:14px;cursor:pointer}
        .btn:hover{filter:brightness(.95)}
        .btn-gray{background:#6b7280}
        #planArea{margin-top:18px;overflow-x:auto}

        table{
            border-collapse:collapse;width:100%;background:#fff;border-radius:10px;overflow:hidden;
            box-shadow:0 1px 6px rgba(0,0,0,.05);table-layout:fixed
        }
        th,td{
            border:1px solid #e5e7eb;padding:6px;font-size:12px;text-align:center;white-space:nowrap;height:26px
        }
        th{background:#f0f3f8;font-weight:600}
        th:first-child,td:first-child{
            width:var(--leftcol);min-width:var(--leftcol);max-width:var(--leftcol);
            text-align:left;white-space:normal
        }
        th:not(:first-child),td:not(:first-child){width:var(--daycol);min-width:var(--daycol);max-width:var(--daycol)}
        .bale-label{font-size:10px;color:#666;margin-top:4px;line-height:1.2}
        .dow{display:block;font-size:10px;color:#777;margin-top:2px}
        .highlight{background:#d1ecf1 !important;border:1px solid #0bb !important}
        .overload{background:#f8d7da !important}
        .footer{display:flex;gap:10px;align-items:center;margin-top:10px}
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

        <!-- Кнопки управления -->
        <button type="button" class="btn btn-gray" onclick="loadPlanFromDB()">Загрузить из БД</button>
        <button type="button" class="btn" onclick="savePlan()">Сохранить план</button>
        <button type="button" class="btn btn-gray" onclick="selected = Object.create(null); drawTable();">Очистить всё</button>
        <button type="button" class="btn btn" onclick="window.location.href='NP_cut_index.php'">Вернуться</button>

    </form>

    <div id="planArea"></div>
</div>

<script>
    const PER_BALE_MIN = 40; // минут на бухту
    const bales = <?= json_encode(array_values($bales), JSON_UNESCAPED_UNICODE) ?>;
    const preselected = <?= json_encode($pre, JSON_UNESCAPED_UNICODE) ?>;
    const orderNumber = <?= json_encode($order) ?>;

    let selected = Object.create(null);
    (function initSelected(){
        const pre = <?= json_encode($pre, JSON_UNESCAPED_UNICODE) ?> || {};
        for (const [ds, arr] of Object.entries(pre)) {
            selected[ds] = [...new Set((arr||[]).map(n => parseInt(n,10)).filter(n => n>0))];
        }
    })();

    function collectPlanFromDOM(){
        const map = Object.create(null);
        document.querySelectorAll('td[data-bale-id].highlight').forEach(td=>{
            const ds  = td.dataset.date;
            const bid = parseInt(td.dataset.baleId,10);
            if (!map[ds]) map[ds] = [];
            if (!map[ds].includes(bid)) map[ds].push(bid);
        });
        return map;
    }


    function fmtDateISO(d){
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const day = String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${day}`;
    }
    function dowName(d){ return ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'][d.getDay()]; }

    function drawTable(){
        const startVal = document.getElementById('startDate').value;
        const days = parseInt(document.getElementById('daysCount').value, 10);
        if(!startVal || isNaN(days)) return;
        const start = new Date(startVal+'T00:00:00');

        const container = document.getElementById('planArea');
        container.innerHTML = '';

        if(!bales.length){
            container.innerHTML = '<div style="padding:10px;background:#fff;border:1px solid #e5e7eb;border-radius:10px">Нет бухт по этой заявке.</div>';
            return;
        }

        const table = document.createElement('table');

        // head
        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        headRow.innerHTML = '<th>Бухта</th>';
        for(let d=0; d<days; d++){
            const date = new Date(start); date.setDate(start.getDate()+d);
            const ds = fmtDateISO(date);
            headRow.innerHTML += `<th>${ds}<span class="dow">${dowName(date)}</span></th>`;
        }
        thead.appendChild(headRow);
        table.appendChild(thead);

        // body
        const tbody = document.createElement('tbody');

        bales.forEach(b => {
            const row = document.createElement('tr');

            const heights = [...new Set(b.strips.map(s => s.height))].map(h => `[${h}]`).join(' ');
            const tooltip = b.strips.map(s => `${s.filter} [${s.height}] ${s.width}мм`).join('\n');
            const left = document.createElement('td');
            left.innerHTML = `<strong>Бухта ${b.bale_id}</strong><div class="bale-label">${heights}</div>`;
            left.title = tooltip;
            row.appendChild(left);

            for(let d=0; d<days; d++){
                const date = new Date(start); date.setDate(start.getDate()+d);
                const ds = fmtDateISO(date);
                const td = document.createElement('td');
                td.dataset.date = ds;
                td.dataset.baleId = String(b.bale_id);

                if (selected[ds] && selected[ds].includes(b.bale_id)) td.classList.add('highlight');

                td.onclick = () => {
                    const bid = b.bale_id;
                    // снять подсветку со всех ячеек этой бухты
                    document.querySelectorAll(`td[data-bale-id="${bid}"]`).forEach(c => c.classList.remove('highlight'));
                    // убрать бухту из всех дней
                    Object.keys(selected).forEach(k=>{
                        const arr = selected[k];
                        if (!arr) return;
                        const i = arr.indexOf(bid);
                        if (i>=0) { arr.splice(i,1); if(!arr.length) delete selected[k]; }
                    });
                    // назначить в текущий день
                    if (!selected[ds]) selected[ds] = [];
                    selected[ds].push(bid);
                    td.classList.add('highlight');
                    updateTotals();
                };

                row.appendChild(td);
            }

            tbody.appendChild(row);
        });

        // строка "Загрузка (ч)"
        const totalRow = document.createElement('tr');
        totalRow.innerHTML = '<td><b>Загрузка (ч)</b></td>';
        for(let d=0; d<days; d++){
            const date = new Date(start); date.setDate(start.getDate()+d);
            const ds = fmtDateISO(date);
            const td = document.createElement('td');
            td.id = 'load-'+ds;
            totalRow.appendChild(td);
        }
        tbody.appendChild(totalRow);

        table.appendChild(tbody);
        container.appendChild(table);

        updateTotals();
    }

    function updateTotals(){
        document.querySelectorAll('[id^=load-]').forEach(td => { td.textContent=''; td.className=''; });
        Object.keys(selected).forEach(ds=>{
            const cnt = (selected[ds]||[]).length;
            const hours = cnt * (PER_BALE_MIN/60);
            const td = document.getElementById('load-'+ds);
            if (td){ td.textContent = hours.toFixed(2); td.className = hours > 7 ? 'overload' : ''; }
        });
    }

    function savePlan(){
        // 1) берём то, что в памяти
        let planObj = (selected && !Array.isArray(selected)) ? selected : Object.create(null);

        // 2) если пусто — соберём из DOM
        if (Object.keys(planObj).length === 0) {
            planObj = collectPlanFromDOM();
        }

        // 3) нормализуем {date: [uniq,baleIds]}
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
        console.log('save payload:', payload);

        fetch('NP/save_roll_plan.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        })
            .then(async res => {
                const txt = await res.text();
                console.log('save response:', res.status, txt);
                if (!res.ok) throw new Error(txt || ('HTTP '+res.status));
                if (txt.trim() !== 'ok') throw new Error(txt);
                alert('План сохранён в roll_plans.');
                // location.href = 'NP_cut_index.php'; // верни, если снова нужен автопереход
            })
            .catch(e => alert('Ошибка сохранения: ' + e.message));
    }


    async function loadPlanFromDB(){
        try{
            const res = await fetch('NP/get_roll_plan.php?order='+encodeURIComponent(orderNumber), {headers:{'Accept':'application/json'}});
            const data = await res.json();
            if(!data.ok) throw new Error(data.error || 'Ошибка загрузки');
            if(!data.exists){ alert('Сохранённый план не найден.'); return; }

            // приведение к объекту
            const incoming = data.plan || {};
            selected = Object.create(null);
            for (const [ds, arr] of Object.entries(incoming)) {
                selected[ds] = [...new Set((arr||[]).map(n => parseInt(n,10)).filter(n => n>0))];
            }

            // ... дальше твоя логика установки дат и перерисовки
            const keys = Object.keys(selected).sort();
            if(keys.length){
                const minD = keys[0], maxD = keys[keys.length-1];
                document.getElementById('startDate').value = minD;
                const d1=new Date(minD+'T00:00:00'), d2=new Date(maxD+'T00:00:00');
                document.getElementById('daysCount').value = String(Math.max(1, Math.round((d2-d1)/(24*3600*1000))+1));
            }
            drawTable();
        }catch(e){ alert('Не удалось загрузить: '+e.message); }
    }


    // init
    (function(){
        const inp = document.getElementById('startDate');
        const ds = new Date().toISOString().slice(0,10);
        if(!inp.value) inp.value = ds;
        drawTable();
    })();
</script>
</body>
</html>
