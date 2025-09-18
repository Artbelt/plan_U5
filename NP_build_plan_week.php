<?php
// NP_build_plan_week.php — недельный календарь (2 бригады) с фиксированной высотой 13ч
// Требуются эндпоинты в NP_build_plan.php: action=load, save, busy, meta

$dsn  = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root";
$pass = "";
$SHIFT_HOURS = 11.5; // фактическая смена для расчётов

$order = $_GET['order'] ?? '';
if ($order==='') { http_response_code(400); exit('Укажите ?order=...'); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
?>
<!doctype html>
<html lang="ru">
<meta charset="utf-8">
<title>План (неделя) — заявка <?=h($order)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root{
        --line:#e5e7eb; --grid:#eef2f7; --muted:#667085; --accent:#2563eb;
        --brig1:#fff7db; --brig2:#eef5ff; --event:#fffbeb; --event-bd:#e6d8a3;
        --bg:#f7f9fc;
    }
    *{box-sizing:border-box}
    body{margin:0;font:13px system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a;background:var(--bg)}
    header{display:flex;gap:8px;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid var(--line);background:#fff;position:sticky;top:0;z-index:10}
    .controls{display:flex;gap:8px;align-items:center}
    .btn{border:1px solid #cbd5e1;background:#fff;border-radius:8px;padding:6px 10px;cursor:pointer}
    .btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
    .muted{color:var(--muted)}
    .week-wrap{padding:10px}
    .week{display:grid;grid-template-columns: 60px repeat(7, 1fr); gap:6px; height: calc(100vh - 76px);}
    .hours{background:#fff;border:1px solid var(--line);border-radius:10px;position:relative}
    .hours .h{position:absolute;left:6px;transform:translateY(-50%);font-size:11px;color:#94a3b8}
    .day{
        display:grid;
        grid-template-rows:auto 1fr;  /* шапка сама по высоте */
        gap:6px;
    }
    /* day-top — пусть переносится на 2 строку при нехватке места */
    .day-top{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        flex-wrap:wrap;               /* ← разрешили перенос */
    }/* дата не сжимается и не переносится */
    .day-date{
        font-weight:600;
        white-space:nowrap;
        flex:0 0 auto;                /* ← не shrink */
    }

    /* чипам можно сжиматься/расти и переноситься */
    .day-chips{
        display:flex;
        flex-wrap:wrap;
        gap:6px;
        justify-content:flex-end;
        flex:1 1 auto;                /* ← можно shrink/grow */
    }
    .day-head{background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px 8px;display:flex;align-items:center;justify-content:space-between}
    .brig-wrap{background:#fff;border:1px solid var(--line);border-radius:10px;display:grid;grid-template-rows:1fr 1fr;gap:4px;overflow:hidden;position:relative}
    .lane{position:relative; overflow:hidden; border-top:1px dashed #f0f2f7}
    .lane:first-child{border-top:none}
    .lane.b1{background:linear-gradient(0deg, var(--brig1), var(--brig1))}
    .lane.b2{background:linear-gradient(0deg, var(--brig2), var(--brig2))}
    .lane::before{
        content:""; position:absolute; inset:0; pointer-events:none;
        background:repeating-linear-gradient(to bottom, transparent 0, transparent 39px, var(--grid) 40px);
    }
    .event{position:absolute; left:6px; right:6px; border:1px solid var(--event-bd); background:var(--event);
        border-radius:10px; padding:6px 8px; cursor:grab; box-shadow:0 1px 0 rgba(0,0,0,.04)}
    .event:active{cursor:grabbing}
    .event h4{margin:0 0 4px; font-size:12.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
    .event .sub{font-size:11px;color:#475569;display:flex;gap:8px;flex-wrap:nowrap; white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
    .badge{font-size:11px; padding:1px 6px; border:1px solid #d1d5db; border-radius:999px; background:#fff}
    .legend{display:flex; gap:8px; align-items:center}
    .legend .dot{width:10px;height:10px;border-radius:50%}
    .dot.b1{background:var(--brig1); border:1px solid #e6d8a3}
    .dot.b2{background:var(--brig2); border:1px solid #cfe0ff}
    .totals{font-size:12px}
    .over{outline:2px solid #ef4444; outline-offset:-2px}
    /* busy/shift маркеры внутри дорожек */
    .lane .busyBar{
        position:absolute; left:0; right:0; top:0;
        background:rgba(148,163,184,.25);   /* slate-400 ~ 25% */
        border-bottom:1px solid rgba(148,163,184,.5);
        z-index:0;
    }
    .lane .shiftLine{
        position:absolute; left:0; right:0; height:0;
        border-top:2px dashed #94a3b8;      /* slate-400 */
        z-index:1;
    }
    .event{ z-index:2; }                   /* события поверх маркеров */
    /* компактные карточки */
    .event.compact{ padding:4px 6px; }
    .event.compact h4{ margin:0 0 2px; font-size:12px; }
    .event.tiny{ padding:2px 6px; }
    .event.tiny h4{ margin:0; font-size:12px; }
    .event.tiny .sub{ display:none; }        /* у «крошек» прячем подзаголовок */
    /* новая компоновка шапки дня */
    .day-head{
        background:#fff;border:1px solid var(--line);border-radius:10px;
        padding:6px 8px; display:flex; flex-direction:column; gap:4px;
    }
    .day-top{ display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .day-date{ font-weight:600; white-space:nowrap; }

    /* чипы с метриками */
    .day-chips{ display:flex; flex-wrap:wrap; gap:6px; justify-content:flex-end; }
    .chip{
        display:inline-flex; align-items:center; gap:6px; padding:2px 8px;
        border:1px solid #dbe3f0; background:#f8fafc; border-radius:9999px;
        font-size:12px; white-space:nowrap;
    }
    .chip .dot{ width:8px; height:8px; border-radius:50%; border:1px solid #94a3b8 }
    .chip.b1{ background:var(--brig1); border-color:#e6d8a3 }
    .chip.b2{ background:var(--brig2); border-color:#cfe0ff }



</style>

<header>
    <div class="controls">
        <button class="btn" id="prevWeek">‹</button>
        <div id="weekTitle" style="font-weight:600"></div>
        <button class="btn" id="nextWeek">›</button>
        <button class="btn" id="todayBtn">Сегодня</button>
    </div>
    <div class="legend">
        <span class="dot b1"></span> Бр-1
        <span class="dot b2" style="margin-left:8px"></span> Бр-2
        <span class="muted" style="margin-left:12px">Сетка по 0.5 ч (40px = 1ч)</span>
    </div>
    <div class="controls">
        <button class="btn" id="loadBtn">Загрузить</button>
        <button class="btn primary" id="saveBtn" disabled>Сохранить</button>
    </div>
</header>

<div class="week-wrap">
    <div class="week" id="weekGrid">
        <div class="hours" id="hourCol"></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const API = 'NP_build_plan.php';
        if (!window.CSS || typeof CSS.escape !== 'function') {
            window.CSS = window.CSS || {}; CSS.escape = (s)=> String(s).replace(/[^a-zA-Z0-9_\-]/g, m => '\\' + m);
        }

        // === ключевые константы
        const ORDER = <?= json_encode($order) ?>;
        const SHIFT_H = <?= json_encode($SHIFT_HOURS) ?>; // расчёты (занятость/перегруз)
        const VIEW_H  = 13;                               // высота дорожки и шкалы (без скролла)
        const PX_PER_HOUR = 40;
        const GRID_STEP_H = 0.5;
        const FALLBACK_SLOT_H = 0.5;                      // если нет нормы (фиксируем!)
        const MIN_SLOT_H = 0.25;
        const COLS = 7;
        const COMPACT_H = 1.0;  // ≤1.0 ч — компакт: дату скрыть
        const TINY_H    = 0.7;  // ≤0.7 ч — очень компактно: оставить только заголовок
        const TEAM_CAP = { '1': SHIFT_H, '2': 8 };  // вместимость дорожки: бр-1 = 11.5ч, бр-2 = 8ч
        const cap = (team) => +(TEAM_CAP[team] ?? SHIFT_H);



        // === состояние
        let weekStart = startOfWeek(new Date());
        // row: {source_date, filter, count, rate, height, baseH, _fallback}
        const plan = new Map();          // Map(day -> { '1':[], '2':[] })
        const busyHours = new Map();     // Map(day -> {'1':hrs,'2':hrs})

        // === DOM
        const weekGrid = document.getElementById('weekGrid');
        const hourCol  = document.getElementById('hourCol');

        // === helpers
        function startOfWeek(d){ const nd=new Date(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate())); let day=nd.getUTCDay(); if(day===0) day=7; nd.setUTCDate(nd.getUTCDate()-(day-1)); return nd; }
        function fmtDate(d){ return new Date(d).toISOString().slice(0,10); }
        function addDays(d,n){ const x=new Date(d); x.setUTCDate(x.getUTCDate()+n); return x; }
        function fmt1(x){ return (Math.round((x||0)*10)/10).toFixed(1); }
        function escapeHtml(s){ return (s??'').replace(/[&<>"']/g,c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
        function ensureDay(day){ if(!plan.has(day)) plan.set(day, {'1':[], '2':[]}); }

        // === шкала часов до VIEW_H
        buildHourColumn();
        function buildHourColumn(){
            const px = Math.ceil(VIEW_H/GRID_STEP_H)*GRID_STEP_H * PX_PER_HOUR;
            hourCol.style.height = (px+12)+'px';
            hourCol.innerHTML = '';
            for(let i=0;i<=VIEW_H;i++){
                const lab = document.createElement('div');
                lab.className='h'; lab.style.top = (i*PX_PER_HOUR)+'px'; lab.textContent = i+'ч';
                hourCol.appendChild(lab);
            }
        }

        // === рендер
        renderWeek(false);
        document.getElementById('prevWeek').onclick = ()=>{ weekStart = addDays(weekStart,-7); renderWeek(false); };
        document.getElementById('nextWeek').onclick = ()=>{ weekStart = addDays(weekStart,+7); renderWeek(false); };
        document.getElementById('todayBtn').onclick = ()=>{ weekStart = startOfWeek(new Date()); renderWeek(false); };
        document.getElementById('loadBtn').onclick  = loadPlan;
        document.getElementById('saveBtn').onclick  = savePlan;

        function renderWeek(skipBusy){
            const d0 = fmtDate(weekStart), d6 = fmtDate(addDays(weekStart,6));
            document.getElementById('weekTitle').textContent = d0+' — '+d6;

            // очистка
            [...weekGrid.querySelectorAll('.day')].forEach(n=>n.remove());

            // 7 колонок
            for(let i=0;i<COLS;i++){
                const day = fmtDate(addDays(weekStart,i));
                const col = document.createElement('div'); col.className='day'; col.dataset.day = day;

                const head = document.createElement('div'); head.className='day-head';
                head.innerHTML = `
                          <div class="day-top">
                            <div class="day-date">${day}</div>
                            <div class="day-chips">
                              <span class="chip b1">
                                <span class="dot"></span>
                                <span><span class="t1" data-t1="${day}">0</span> шт · <span class="h1" data-h1="${day}">0.0</span> ч</span>
                              </span>
                              <span class="chip b2">
                                <span class="dot"></span>
                                <span><span class="t2" data-t2="${day}">0</span> шт · <span class="h2" data-h2="${day}">0.0</span> ч</span>
                              </span>
                            </div>
                          </div>
                        `;
                col.appendChild(head);

                const wrap = document.createElement('div'); wrap.className='brig-wrap'; wrap.dataset.day = day;
                const lane1 = document.createElement('div'); lane1.className='lane b1'; lane1.dataset.day=day; lane1.dataset.team='1';
                const lane2 = document.createElement('div'); lane2.className='lane b2'; lane2.dataset.day=day; lane2.dataset.team='2';

                const heightPx = VIEW_H * PX_PER_HOUR; // фиксированная высота 13ч
                lane1.style.height = heightPx+'px'; lane2.style.height = heightPx+'px';
                // маркеры: busy-заливка и пунктир окончания реальной смены
                addMarkers(lane1);
                addMarkers(lane2);


                // DnD
                [lane1,lane2].forEach(l=>{
                    l.addEventListener('dragover', e=>e.preventDefault());
                    l.addEventListener('drop', e=>{
                        e.preventDefault();
                        const dragging = document.querySelector('.event.dragging');
                        if (!dragging) return;
                        const row = dragging._row; const srcDay=dragging._day; const srcTeam=dragging._team;
                        const dstDay = l.dataset.day; const dstTeam = l.dataset.team;
                        if (srcDay===dstDay && srcTeam===dstTeam) return;
                        const arr = plan.get(srcDay)[srcTeam]; const idx = arr.indexOf(row);
                        if (idx>=0) arr.splice(idx,1);
                        ensureDay(dstDay); plan.get(dstDay)[dstTeam].push(row);
                        renderWeek(false);
                    });
                });

                wrap.appendChild(lane1); wrap.appendChild(lane2);
                col.appendChild(wrap);
                weekGrid.appendChild(col);
            }

            // расчёт и отрисовка по дорожкам
            for(let i=0;i<COLS;i++){
                const day = fmtDate(addDays(weekStart,i));
                ['1','2'].forEach(team=>{
                    const layout = computeLaneLayout(day, team);
                    let topH = 0;
                    layout.forEach(r=>{
                        paintEvent(day, team, r, r._effH, topH);
                        topH += r._effH;
                    });
                });
            }

            if (!skipBusy){
                const days = [...Array(7)].map((_,i)=>fmtDate(addDays(weekStart,i)));
                fetchBusy(days).then(()=> renderWeek(true)); // второй рендер с актуальной занятостью
            }
            applyBusyMarkersForWeek();
            refreshTotals();
        }
        function addMarkers(lane){
            const busy = document.createElement('div');
            busy.className = 'busyBar';
            busy.style.height = '0px';
            lane.appendChild(busy);

            const sline = document.createElement('div');
            sline.className = 'shiftLine';
            sline.style.top = (Math.min(VIEW_H, SHIFT_H) * PX_PER_HOUR) + 'px';
            lane.appendChild(sline);
        }

        function applyBusyMarkersForWeek(){
            for(let i=0;i<7;i++){
                const day = fmtDate(addDays(weekStart,i));
                ['1','2'].forEach(team=>{
                    const lane = document.querySelector(`.lane[data-day="${CSS.escape(day)}"][data-team="${team}"]`);
                    if (!lane) return;
                    const busy = (busyHours.get(day) || {})[team] || 0;
                    const busyPx = Math.min(VIEW_H, busy) * PX_PER_HOUR;
                    const bar = lane.querySelector('.busyBar');
                    if (bar) bar.style.height = busyPx + 'px';
                    const sline = lane.querySelector('.shiftLine');
                    if (sline) sline.style.top = (Math.min(VIEW_H, cap(team)) * PX_PER_HOUR) + 'px';
                });
            }
        }


        // === укладка: используем фиксированные baseH карточек; неизвестные нормы НЕ растягиваем
        function computeLaneLayout(day, team){
            const rows = (plan.get(day)?.[team]||[]).slice();
            const busy = ((busyHours.get(day)||{})[team]||0);
            const avail = Math.max(0, cap(team) - busy);     // в смене доступно

            // базовые часы карточек (фиксированы при загрузке)
            const sumBase = rows.reduce((s,r)=> s + Math.max(MIN_SLOT_H, (r.baseH||FALLBACK_SLOT_H)), 0);

            // масштаб только если не влезли
            const scale = (sumBase>avail && sumBase>0) ? (avail/sumBase) : 1;

            rows.forEach(r=>{
                const b = Math.max(MIN_SLOT_H, (r.baseH||FALLBACK_SLOT_H));
                r._effH = Math.max(MIN_SLOT_H, b * scale);
            });

            // большие сверху
            rows.sort((a,b)=> (b._effH - a._effH) || String(a.filter).localeCompare(String(b.filter)));
            return rows;
        }

        function paintEvent(day, team, row, effH, topH){
            const lane = weekGrid.querySelector(`.lane[data-day="${CSS.escape(day)}"][data-team="${team}"]`);
            if(!lane) return;

            const topPx    = Math.round(topH * PX_PER_HOUR);
            const heightPx = Math.max(18, Math.round(effH * PX_PER_HOUR));

            // определяем режимы компактности по фактическим часам
            const isTiny    = effH <= TINY_H;
            const isCompact = !isTiny && effH <= COMPACT_H;

            const ev = document.createElement('div');
            ev.className='event';
            if (isCompact) ev.classList.add('compact');
            if (isTiny)    ev.classList.add('tiny');

            ev.style.top = topPx+'px';
            ev.style.height = heightPx+'px';
            ev.draggable = true;

            // показываем дату только если карточка не компактная
            const showDate = !(isTiny || isCompact);
            const dateHtml = showDate ? `<span class="muted">${escapeHtml(row.source_date)}</span>` : '';

            // подсказка всегда полная
            ev.title = `${row.filter}${row.height!=null?` [${row.height} мм]`:''}
            ${row.count} шт • ~ ${fmt1(effH)} ч${row._fallback?'*':''}
            ${row.source_date}`;

            ev.innerHTML = `
                            <h4>${escapeHtml(row.filter)} ${row.height!=null?`<span class="badge">${escapeHtml(String(row.height))} мм</span>`:''}</h4>
                            <div class="sub">
                              <span>${row.count} шт</span>
                              <span>~ ${fmt1(effH)} ч${row._fallback?'*':''}</span>
                              ${dateHtml}
                            </div>
                          `;

            ev._row=row; ev._day=day; ev._team=team;
            ev.addEventListener('dragstart', ()=>ev.classList.add('dragging'));
            ev.addEventListener('dragend',   ()=>ev.classList.remove('dragging'));
            lane.appendChild(ev);
        }


        function refreshTotals(){
            for (let i = 0; i < COLS; i++){
                const day = fmtDate(addDays(weekStart, i));
                const by  = plan.get(day) || {'1':[], '2':[]};

                const effSum = (team) => computeLaneLayout(day, team)
                    .reduce((s, r) => s + (r._effH || 0), 0);

                const t1 = by['1'].reduce((s,r)=> s + (r.count||0), 0);
                const t2 = by['2'].reduce((s,r)=> s + (r.count||0), 0);

                const myH1 = effSum('1');
                const myH2 = effSum('2');
                const busy1 = (busyHours.get(day)||{})['1'] || 0;
                const busy2 = (busyHours.get(day)||{})['2'] || 0;

                const h1 = myH1 + busy1;
                const h2 = myH2 + busy2;

                const col = weekGrid.querySelector(`.day[data-day="${CSS.escape(day)}"]`);
                if (!col) continue;

                col.querySelector(`.t1[data-t1="${CSS.escape(day)}"]`).textContent = String(t1);
                col.querySelector(`.t2[data-t2="${CSS.escape(day)}"]`).textContent = String(t2);
                col.querySelector(`.h1[data-h1="${CSS.escape(day)}"]`).textContent = fmt1(h1);
                col.querySelector(`.h2[data-h2="${CSS.escape(day)}"]`).textContent = fmt1(h2);

                const lane1 = col.querySelector(`.lane[data-team="1"]`);
                const lane2 = col.querySelector(`.lane[data-team="2"]`);

                const cap1 = cap('1');   // 11.5ч
                const cap2 = cap('2');   // 8ч

                lane1.classList.toggle('over', h1 > cap1 + 0.01);
                lane2.classList.toggle('over', h2 > cap2 + 0.01);
            }
        }


        // === I/O
        async function loadPlan(){
            try{
                const res = await fetch(`${API}?action=load&order=`+encodeURIComponent(ORDER), {headers:{'Accept':'application/json'}});
                const data = await res.json();
                if (!data.ok) throw new Error(data.error||'load failed');

                plan.clear();

                const need = [];
                Object.keys(data.plan||{}).forEach(day=>{
                    ['1','2'].forEach(team=>{
                        (data.plan[day][team]||[]).forEach(it=>{
                            need.push({day,team, source_date: it.source_date, filter: it.filter, count: +it.count||0});
                        });
                    });
                });

                let metaMap = new Map();
                try{
                    const uniq = Array.from(new Set(need.map(x=>x.filter)));
                    const resMeta = await fetch(`${API}?action=meta`, {
                        method:'POST', headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({filters: uniq})
                    });
                    const dataMeta = await resMeta.json();
                    (dataMeta.items||[]).forEach(it=> metaMap.set(it.filter, {rate:+it.rate||0, height: it.height==null?null:+it.height}));
                }catch(_){ metaMap = new Map(); }

                need.forEach(x=>{
                    const m = metaMap.get(x.filter) || {rate:0, height:null};
                    const rate = +m.rate || 0;
                    const rawH = rate>0 ? (x.count / rate) * SHIFT_H : 0;
                    const baseH = rawH>0 ? rawH : FALLBACK_SLOT_H; // зафиксировали!
                    ensureDay(x.day);
                    plan.get(x.day)[x.team].push({
                        source_date: x.source_date, filter: x.filter, count: x.count,
                        rate: rate, height: (m.height==null?null:+m.height),
                        baseH: baseH, _fallback: (rawH<=0)
                    });
                });

                renderWeek(false);
                alert('Загружено.');
            }catch(e){
                alert('Ошибка загрузки: '+e.message);
            }
        }

        async function savePlan(){
            const payload = {};
            plan.forEach((byTeam,day)=>{
                const t1 = (byTeam['1']||[]).map(x=>({source_date:x.source_date, filter:x.filter, count:x.count}));
                const t2 = (byTeam['2']||[]).map(x=>({source_date:x.source_date, filter:x.filter, count:x.count}));
                if (t1.length || t2.length) payload[day] = {'1':t1, '2':t2};
            });
            try{
                const res = await fetch(`${API}?action=save`, {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({order: ORDER, plan: payload})
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error||'save failed');
                alert('Сохранено.');
            }catch(e){
                alert('Ошибка сохранения: '+e.message);
            }
        }

        async function fetchBusy(days){
            try{
                const res = await fetch(`${API}?action=busy&order=`+encodeURIComponent(ORDER), {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({days})
                });
                const data = await res.json();
                if (!data.ok) return;
                busyHours.clear();
                (days||[]).forEach(d=>{
                    const v = (data.data||{})[d] || {1:0,2:0};
                    busyHours.set(d, {'1': +v[1]||0, '2': +v[2]||0});
                });
            }catch(e){ /* ignore */ }
        }
    });
</script>
</html>
