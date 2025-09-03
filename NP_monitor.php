<?php
$date = $_GET['date'] ?? date('Y-m-d');
$hideDone = isset($_GET['hideDone']) && $_GET['hideDone']=='1' ? '1' : '0';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Мониторинг — сегодня</title>
    <style>
        body{font-family:sans-serif;background:#f5f5f5;margin:0;padding:10px}
        h2{margin:8px 0 12px;text-align:center}
        .controls{display:flex;gap:8px;justify-content:center;align-items:center;flex-wrap:wrap;margin-bottom:12px}
        .controls input[type="date"]{padding:6px 8px;font-size:14px}
        .controls label{display:flex;gap:6px;align-items:center;font-size:14px}
        .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;max-width:1000px;margin:0 auto 12px}
        .card{background:#fff;border-radius:8px;padding:10px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
        .card h4{margin:0 0 8px;font-size:14px;color:#555}
        .card .big{font-size:20px;font-weight:bold}
        .card .sub{color:#666;font-size:12px}
        .section{max-width:1000px;margin:0 auto 14px;background:#fff;border-radius:8px;padding:10px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
        .section h3{margin:0 0 8px}
        table{border-collapse:collapse;width:100%;font-size:14px}
        th,td{border:1px solid #ddd;padding:6px 8px;text-align:center}
        thead th{background:#f0f0f0}
        tbody tr:nth-child(even){background:#fafafa}
        .ok{color:#0a7d38;font-weight:bold}
        .warn{color:#d97706;font-weight:bold}
        .bad{color:#b91c1c;font-weight:bold}
        @media(max-width:700px){
            .kpis{grid-template-columns:repeat(2,1fr)}
            table{font-size:13px}
            th,td{padding:4px}
            .controls{gap:6px}
        }
    </style>
    <script>
        let timer=null;
        function pct(done, plan){
            if(plan<=0) return 0;
            return Math.round((done/plan)*100);
        }
        function cls(p){
            if(p>=100) return 'ok';
            if(p>=50) return 'warn';
            return 'bad';
        }
        function loadData(){
            const date = document.getElementById('date').value;
            const hideDone = document.getElementById('hideDone').checked ? '1':'0';
            fetch(`NP_monitor_data.php?date=${encodeURIComponent(date)}&hideDone=${hideDone}`)
                .then(r=>r.json())
                .then(d=>{
                    // KPIs
                    const cP=d.cut.kpi.plan, cD=d.cut.kpi.done;
                    const gP=d.corr.kpi.plan, gF=d.corr.kpi.fact;
                    const cPct=pct(cD,cP), gPct=pct(gF,gP);

                    document.getElementById('kpi-cut-main').textContent=`${cD}/${cP}`;
                    document.getElementById('kpi-cut-sub').textContent=`${cPct}%`;
                    document.getElementById('kpi-cut-sub').className='sub '+cls(cPct);

                    document.getElementById('kpi-corr-main').textContent=`${gF}/${gP}`;
                    document.getElementById('kpi-corr-sub').textContent=`${gPct}%`;
                    document.getElementById('kpi-corr-sub').className='sub '+cls(gPct);

                    // таблицы
                    const cutT = document.getElementById('cut-body');
                    cutT.innerHTML='';
                    d.cut.byOrder.forEach(r=>{
                        const tr=document.createElement('tr');
                        tr.innerHTML = `<td>${r.order}</td><td>${r.plan}</td><td>${r.done}</td><td>${r.left}</td>`;
                        cutT.appendChild(tr);
                    });

                    const corrT = document.getElementById('corr-body');
                    corrT.innerHTML='';
                    d.corr.byOrder.forEach(r=>{
                        const tr=document.createElement('tr');
                        tr.innerHTML = `<td>${r.order}</td><td>${r.plan}</td><td>${r.fact}</td><td>${r.left}</td>`;
                        corrT.appendChild(tr);
                    });
                })
                .catch(e=>console.error(e));
        }
        function startPolling(){
            if(timer) clearInterval(timer);
            timer=setInterval(loadData, 30000); // 30 сек
        }
        window.addEventListener('DOMContentLoaded', ()=>{
            loadData();
            startPolling();
        });
    </script>
</head>
<body>
<h2>Мониторинг — текущий день</h2>
<div class="controls">
    <form method="get" onsubmit="event.preventDefault(); loadData();">
        Дата:
        <input type="date" id="date" value="<?= htmlspecialchars($date) ?>">
        <label><input type="checkbox" id="hideDone" <?= $hideDone==='1'?'checked':'' ?> onchange="loadData()"> Скрыть выполненные</label>
        <button onclick="loadData()">Обновить</button>
    </form>
</div>

<div class="kpis">
    <div class="card">
        <h4>Порезка (бухты)</h4>
        <div class="big" id="kpi-cut-main">–/–</div>
        <div class="sub" id="kpi-cut-sub">–%</div>
    </div>
    <div class="card">
        <h4>Гофрирование (шт)</h4>
        <div class="big" id="kpi-corr-main">–/–</div>
        <div class="sub" id="kpi-corr-sub">–%</div>
    </div>
    <div class="card">
        <h4>Сборка</h4>
        <div class="big">скоро</div>
        <div class="sub">пока скрыто</div>
    </div>
    <div class="card">
        <h4>Упаковка</h4>
        <div class="big">скоро</div>
        <div class="sub">пока скрыто</div>
    </div>
</div>

<div class="section">
    <h3>Порезка — по заявкам (сегодня)</h3>
    <table>
        <thead><tr><th>Заявка</th><th>План (бухт)</th><th>Готово</th><th>Осталось</th></tr></thead>
        <tbody id="cut-body"></tbody>
    </table>
</div>

<div class="section">
    <h3>Гофрирование — по заявкам (сегодня)</h3>
    <table>
        <thead><tr><th>Заявка</th><th>План (шт)</th><th>Факт (шт)</th><th>Осталось</th></tr></thead>
        <tbody id="corr-body"></tbody>
    </table>
</div>
</body>
</html>
