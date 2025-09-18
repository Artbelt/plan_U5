<?php
// NP_corrugation_plan.php — верх: ПОЛОСЫ из бухт (с расчётом количества фильтров), низ: план на гофру с диапазоном дней + сохранение/загрузка
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);

$order = $_GET['order'] ?? '';
if ($order==='') { http_response_code(400); exit('Укажите ?order=...'); }

/*
 * Верхняя таблица = полосы, полученные при раскрое (по датам раскроя).
 */
$sql = "
SELECT
  rp.work_date,
  rp.bale_id,
  cps.strip_no,
  cps.filter,
  cps.height,
  cps.width,
  cps.fact_length,
  pps.p_p_pleats_count AS pleats
FROM roll_plans rp
JOIN cut_plans cps
  ON cps.order_number = rp.order_number
 AND cps.bale_id      = rp.bale_id
JOIN salon_filter_structure sfs
  ON sfs.filter = cps.filter
JOIN paper_package_salon pps
  ON pps.p_p_name = sfs.paper_package
WHERE rp.order_number = ?
ORDER BY rp.work_date, rp.bale_id, cps.strip_no
";
$st = $pdo->prepare($sql);
$st->execute([$order]);
$rows = $st->fetchAll();

function trim_num($x, $dec=1){
    $s = number_format((float)$x, $dec, '.', '');
    return rtrim(rtrim($s, '0'), '.');
}

$dates = [];
$pool  = [];
foreach($rows as $r){
    $d = $r['work_date'];
    $dates[$d]=true;

    $H = (float)$r['height'];
    $W = (float)$r['width'];
    $Z = (int)$r['pleats'];
    $L = $r['fact_length'] !== null ? (int)round((float)$r['fact_length']) : null; // м

    // длина одного фильтра (м)
    $L_one = ($H * 2 * max(0,$Z)) / 1000.0;
    $cnt   = ($L !== null && $L_one > 0) ? (int)floor($L / $L_one) : 0;

    // видимая часть: имя + [h..] + [N шт]
    $label_visible = sprintf('%s [h%s] [%d шт]', $r['filter'], trim_num($H, 1), $cnt);

    // tooltip (скрытые поля): [z..][w..][L..]
    $tooltip = sprintf('[z%d] [w%s]%s', $Z, trim_num($W, 1), $L !== null ? (' [L'.(int)$L.']') : '');

    $pool[$d][] = [
        'key'      => $r['bale_id'].':'.$r['strip_no'],
        'bale_id'  => (int)$r['bale_id'],
        'strip_no' => (int)$r['strip_no'],
        'filter'   => (string)$r['filter'], // чистое имя (для БД)
        'label'    => $label_visible,
        'tip'      => $tooltip,
        'packs'    => $cnt,
    ];
}
$dates = array_values(array_keys($dates));
sort($dates);
?>
<!doctype html>
<meta charset="utf-8">
<title>Гофроплан (полосы): <?=htmlspecialchars($order)?></title>
<style>
    :root{ --line:#e5e7eb; --bg:#f7f9fc; --card:#fff; --muted:#6b7280; --accent:#2563eb; }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);font:13px system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111}
    h2{margin:18px 10px 8px}
    .wrap{width:100vw;margin:0;padding:0 10px}
    .panel{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:10px;margin:10px 0}
    .head{display:flex;align-items:center;justify-content:space-between;margin:2px 0 10px;gap:8px;flex-wrap:wrap}
    .btn{background:var(--accent);color:#fff;border:1px solid var(--accent);border-radius:8px;padding:6px 10px;cursor:pointer}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .muted{color:var(--muted)}
    .sub{font-size:12px;color:var(--muted)}

    .gridTop{display:grid;grid-template-columns:repeat(<?=count($dates)?:1?>,minmax(220px,1fr));gap:10px}
    .gridBot{display:grid;gap:10px}
    .col{border-left:1px solid var(--line);padding-left:8px;min-height:200px}
    .col h4{margin:0 0 8px;font-weight:600}

    .pill{display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid #dbe3f0;background:#eef6ff;border-radius:8px;padding:6px 8px;margin:4px 0;cursor:pointer}
    .pill:hover{background:#e6f1ff}
    .pill-disabled{opacity:.45;filter:grayscale(.15);pointer-events:none}

    .dropzone{min-height:42px;border:1px dashed var(--line);border-radius:6px;padding:6px}
    .rowItem{display:flex;align-items:center;justify-content:space-between;background:#dff7c7;border:1px solid #bddda2;border-radius:8px;padding:6px 8px;margin:4px 0}
    .rowItem .rm{border:none;background:#fff;border:1px solid #ccc;border-radius:6px;padding:2px 8px;cursor:pointer}
    .dayTotal{margin-top:6px;font-size:12px}
    .rowItem b.qty{margin-left:8px}
    /* керування всередині картки низу */
    .rowItem .controls{display:flex;align-items:center;gap:6px}
    .rowItem .mv{
        min-width: 24px;        /* трохи вужчі за попередні */
        padding: 0 6px;
        font-size: 16px;        /* щоб символ був чіткий */
        line-height: 1;
        text-align: center;
    }

    .rowItem .mv:hover{background:#f1f5f9}
    .rowItem .mv:disabled{opacity:.4;cursor:not-allowed}

    .tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .tools label{font-size:12px;color:#333}
    .tools input[type=date], .tools input[type=number]{padding:4px 8px;border:1px solid #dcdfe5;border-radius:8px}
    /* запрет выделения текста по всей странице */
        html, body, .wrap, .panel, .grid, .col, .pill, .rowItem, button {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        /* но внутри полей ввода и редактируемых областей разрешаем */
        input, textarea, [contenteditable], .allow-select {
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
            user-select: text;
        }

    .modalWrap{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.35);z-index:1000}
    .modal{background:#fff;border-radius:12px;border:1px solid var(--line);min-width:320px;max-width:520px;max-height:70vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.2)}
    .modalHeader{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid var(--line)}
    .modalTitle{font-weight:600}
    .modalClose{border:1px solid #ccc;background:#f8f8f8;border-radius:8px;padding:4px 8px;cursor:pointer}
    .modalBody{padding:10px;overflow:auto}
    .daysGrid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
    .dayBtn{display:flex;flex-direction:column;gap:4px;padding:10px;border:1px solid #d9e2f1;border-radius:10px;background:#f4f8ff;cursor:pointer;text-align:left}
    .dayBtn:hover{background:#ecf4ff}
    .dayHead{font-weight:600}
    .daySub{font-size:12px;color:#6b7280}
    .dayBtn:disabled{
        opacity:.5;
        cursor:not-allowed;
    }
    .topCol h4{display:flex;align-items:center;justify-content:space-between}
    .topShift{
        border:1px solid #cbd5e1;
        background:#fff;
        border-radius:6px;
        padding:2px 8px;
        cursor:pointer;
        font-size:14px; line-height:1;
    }
    .topShift:hover{background:#f1f5f9}
    .topCol h4{display:flex;align-items:center;justify-content:space-between}
    .topCascade{
        border:1px solid #cbd5e1;
        background:#fff;
        border-radius:6px;
        padding:2px 8px;
        cursor:pointer;
        font-size:14px; line-height:1;
    }
    .topCascade:hover{background:#f1f5f9}


    @media (max-width:560px){ .daysGrid{grid-template-columns:1fr;} .modal{min-width:280px;max-width:90vw;} }
</style>

<div class="wrap">
    <h2>Планирование гофрирования (полосы) — заявка <?=htmlspecialchars($order)?></h2>

    <div class="panel" id="topPanel">
        <div class="head">
            <div><b>Доступные полосы из раскроя</b> <span class="sub">клик по плашке — выбрать дату внизу (Shift+клик — в последний выбранный день)</span></div>
            <div class="muted">
                <?php $cnt=0; foreach($pool as $list) $cnt+=count($list); echo $cnt; ?> полос
            </div>
        </div>
        <div class="gridTop">
            <?php foreach($dates as $d): ?>
                <div class="col topCol" data-day="<?=$d?>">
                    <h4>
                        <span><?=$d?></span>
                        <button class="topCascade" data-day="<?=$d?>" title="Зсунути всі дні від цього на +N">&rsaquo;</button>
                    </h4>

                    <?php if(empty($pool[$d])): ?>
                        <div class="muted">нет</div>
                    <?php else: foreach($pool[$d] as $p): ?>
                        <div class="pill"
                             title="<?= htmlspecialchars('Бухта #'.$p['bale_id'].' · Полоса №'.$p['strip_no'].' · '.($p['tip'] ?? '')) ?>"
                             data-key="<?= htmlspecialchars($p['key']) ?>"
                             data-cut-date="<?= $d ?>"
                             data-bale-id="<?= $p['bale_id'] ?>"
                             data-strip-no="<?= $p['strip_no'] ?>"
                             data-filter-name="<?= htmlspecialchars($p['filter']) ?>"
                             data-packs="<?= (int)$p['packs'] ?>">
                            <span><?= htmlspecialchars($p['label'] ?? '') ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            <?php endforeach; ?>
        </div>


    </div>

    <div class="panel" id="planPanel">
        <div class="head">
            <b>План гофрирования</b>
            <div class="tools">
                <button class="btn" id="btnLoad">Загрузить план</button>
                <label>Начало: <input type="date" id="rngStart"></label>
                <label>Дней: <input type="number" id="rngDays" value="7" min="1"></label>
                <button class="btn" id="btnBuildDays">Построить дни</button>
                <label> День+: </label>
                <button class="btn" id="btnAddDay" title="Добавить этот день внизу">+</button>
            </div>
            <button class="btn" id="btnSave" disabled>Сохранить план</button>
            <button type="button" class="btn btn" onclick="window.location.href='NP_cut_index.php'">Вернуться</button>
        </div>

        <div class="gridBot" id="planGrid"></div>

        <div class="sub" style="margin-top:8px">
            Одна и та же полоса может быть добавлена только один раз.
            Удалите её внизу, чтобы вернуть плашку вверху в активное состояние.
        </div>
    </div>
</div>

<div class="modalWrap" id="datePicker">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dpTitle">
        <div class="modalHeader">
            <div class="modalTitle" id="dpTitle">Выберите дату для добавления</div>
            <button class="modalClose" id="dpClose" title="Закрыть">×</button>
        </div>
        <div class="modalBody">
            <div class="daysGrid" id="dpDays"></div>
        </div>
    </div>
</div>

<script>
    const orderNumber = <?= json_encode($order) ?>;

    const plan = new Map();          // Map<date, Set<key>>
    const assigned = new Set();      // Set<key>
    const planGrid = document.getElementById('planGrid');
    const saveBtn  = document.getElementById('btnSave');
    const loadBtn  = document.getElementById('btnLoad');

    // Локальний ISO без UTC-зсуву
    const iso = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    const parseISO = s => { const [y,m,da] = s.split('-').map(Number); return new Date(y, m-1, da); };
    const topGrid = document.querySelector('#topPanel .gridTop');
    const nextISO = ds => { const d = parseISO(ds); d.setDate(d.getDate()+1); return iso(d); };

    function topEnsureDayCol(ds){
        let col = topGrid.querySelector(`.topCol[data-day="${ds}"]`);
        if (col) return col;

        col = document.createElement('div');
        col.className = 'col topCol';
        col.dataset.day = ds;
        col.innerHTML = `
    <h4><span>${ds}</span>
      <button class="topShift" data-day="${ds}" title="Зсунути доступність на +1 день">&rsaquo;</button>
    </h4>
    <div class="muted">нет</div>
  `;
        topGrid.appendChild(col);
        // оновити кількість колонок
        topGrid.style.gridTemplateColumns = `repeat(${topGrid.querySelectorAll('.topCol').length}, minmax(220px,1fr))`;
        // прив’язати клік
        col.querySelector('.topShift').onclick = () => shiftTopDay(ds, 1);
        return col;
    }

    function topSetEmptyState(col){
        const hasPill = !!col.querySelector('.pill');
        const ph = col.querySelector('.muted');
        if (!hasPill && !ph){
            const m = document.createElement('div'); m.className='muted'; m.textContent='нет'; col.appendChild(m);
        } else if (hasPill && ph){ ph.remove(); }
    }

    function shiftTopDay(ds, delta=1){
        if (delta<=0) return;
        const from = topGrid.querySelector(`.topCol[data-day="${ds}"]`);
        if (!from) return;

        const pills = [...from.querySelectorAll('.pill')];
        if (!pills.length) return;

        // цільовий день (посунемо на delta)
        let target = ds;
        for (let i=0;i<delta;i++){ target = nextISO(target); topEnsureDayCol(target); }
        const to = topGrid.querySelector(`.topCol[data-day="${target}"]`);

        // переносимо всі плашки, оновлюємо cutDate
        pills.forEach(p=>{
            p.dataset.cutDate = target;
            cutDateByKey.set(p.dataset.key, target);
            to.appendChild(p);
        });

        topSetEmptyState(from);
        topSetEmptyState(to);
    }

    // підвісити обробники на початкові кнопки верхньої сітки
    document.querySelectorAll('.topShift').forEach(btn=>{
        btn.onclick = () => shiftTopDay(btn.dataset.day, 1);
    });


    const cutDateByKey = new Map(); // key => 'YYYY-MM-DD'

    let lastPickedDay = null;

    const initialDays = <?= json_encode($dates, JSON_UNESCAPED_UNICODE) ?>;

    function ensureDay(ds){ if(!plan.has(ds)) plan.set(ds, new Set()); }
    function refreshSaveState(){
        let has=false; plan.forEach(set=>{ if(set.size) has=true; });
        saveBtn.disabled = !has;
    }
    function setPillDisabledByKey(key, disabled){
        document.querySelectorAll(`.pill[data-key="${key}"]`).forEach(el=>{
            el.classList.toggle('pill-disabled', !!disabled);
        });
    }
    function getAllDays(){
        return [...planGrid.querySelectorAll('.col[data-day]')].map(c=>c.dataset.day);
    }
    function dayCount(ds){ return plan.has(ds) ? plan.get(ds).size : 0; }


    function dayPacks(ds){
        const col = getPlanCol(ds);
        if (!col) return 0;
        let sum = 0;
        col.querySelectorAll('.dropzone .rowItem').forEach(r=>{
            const pk = parseInt(r.dataset.packs||'0',10);
            if (!isNaN(pk)) sum += pk;
        });
        return sum;
    }


    function updateMoveButtons(row){
        const days = getAllDays();
        const idx  = days.indexOf(row.dataset.day);
        const leftBtn  = row.querySelector('.mv-left');
        const rightBtn = row.querySelector('.mv-right');
        if(leftBtn)  leftBtn.disabled  = (idx <= 0);
        if(rightBtn) rightBtn.disabled = (idx >= days.length - 1);
    }

    function moveRow(row, dir){
        const days = getAllDays();
        const cur  = row.dataset.day;
        const idx  = days.indexOf(cur);
        const next = idx + dir;
        if (next < 0 || next >= days.length) return;

        const newDay  = days[next];
        const key     = row.dataset.key;
        const cutDate = row.dataset.cutDate || cutDateByKey.get(key) || '';  // ← додано

        if (cutDate && newDay < cutDate) {
            alert(`Нельзя переносить раньше раскроя: ${cutDate}`);
            return;
        }

        ensureDay(newDay);
        const newSet = plan.get(newDay);
        if (newSet.has(key)) { alert('У цьому дні вже є ця полоса.'); return; }

        const oldSet = plan.get(cur);
        if (oldSet) oldSet.delete(key);
        newSet.add(key);

        const dzNew = planGrid.querySelector(`.col[data-day="${newDay}"] .dropzone`);
        if (!dzNew) return;
        dzNew.appendChild(row);
        row.dataset.day = newDay;

        recalcDayTotal(cur);
        recalcDayTotal(newDay);
        updateMoveButtons(row);
        lastPickedDay = newDay;
    }



    /* фабрика створення картки рядка з кнопками ⟵ ⟶ */
    function createRow({key,targetDay,packs,filter,labelTxt,cutDate}){
        const row = document.createElement('div');
        row.className = 'rowItem';
        row.dataset.key      = key;
        row.dataset.day      = targetDay;
        row.dataset.packs    = String(packs);
        row.dataset.filter   = filter;
        row.dataset.cutDate  = cutDate || cutDateByKey.get(key) || '';  // ← зберегли

        row.innerHTML = `
    <div>
      <b>${labelTxt}</b>
      <b class="qty">· ${packs} шт</b>
    </div>
    <div class="controls">
      <button class="mv mv-left"  title="Перенести на попередній день" aria-label="Вліво">&lsaquo;</button>
      <button class="mv mv-right" title="Перенести на наступний день"   aria-label="Вправо">&rsaquo;</button>
      <button class="rm"          title="Убрать" aria-label="Видалити">×</button>
    </div>
  `;

        row.querySelector('.rm').onclick = ()=>{
            const set = plan.get(row.dataset.day);
            if(set) set.delete(key);
            row.remove();
            assigned.delete(key);
            setPillDisabledByKey(key,false);
            refreshSaveState();
            recalcDayTotal(row.dataset.day);
        };

        row.querySelector('.mv-left').onclick  = ()=>moveRow(row,-1);
        row.querySelector('.mv-right').onclick = ()=>moveRow(row, 1);

        updateMoveButtons(row);
        return row;
    }



    function renderPlanGrid(days){
        plan.clear(); assigned.clear();
        document.querySelectorAll('.pill').forEach(p=>p.classList.remove('pill-disabled'));
        lastPickedDay = null;

        planGrid.innerHTML = '';
        const frag = document.createDocumentFragment();
        days.forEach(ds=>{
            ensureDay(ds);
            const col = document.createElement('div');
            col.className = 'col';
            col.dataset.day = ds;
            col.innerHTML = `
                <h4>${ds}</h4>
                <div class="dropzone"></div>
                <div class="dayTotal muted">Итого: <b class="n">0</b> шт</div>
            `;
            frag.appendChild(col);
        });
        planGrid.appendChild(frag);
        planGrid.style.gridTemplateColumns = `repeat(${Math.max(1, days.length)}, minmax(220px, 1fr))`;
        refreshSaveState();
    }
    function getPlanCol(ds){
        return planGrid.querySelector(`.col[data-day="${ds}"]`);
    }
    function recalcDayTotal(ds){
        const col = getPlanCol(ds);
        if (!col) return;
        let sum = 0;
        col.querySelectorAll('.dropzone .rowItem').forEach(r=>{
            const pk = parseInt(r.dataset.packs||'0',10);
            if (!isNaN(pk)) sum += pk;
        });
        const out = col.querySelector('.dayTotal .n');
        if (out) out.textContent = String(sum);
    }

    function addToPlan(targetDay, pillEl){
        const key      = pillEl.dataset.key;
        const packs    = parseInt(pillEl.dataset.packs||'0',10);
        const filter   = pillEl.dataset.filterName || '';
        const labelTxt = pillEl.querySelector('span')?.textContent || pillEl.textContent;
        const cutDate  = pillEl.dataset.cutDate || cutDateByKey.get(key) || '';

        // ЗАБОРОНА: не раніше розкрою
        if (cutDate && targetDay < cutDate) {
            alert(`Нельзя назначать раньше раскроя: ${cutDate}`);
            return;
        }


        ensureDay(targetDay);
        const set = plan.get(targetDay);
        if (set.has(key)) return;

        const dz  = planGrid.querySelector(`.col[data-day="${targetDay}"] .dropzone`);
        if(!dz){ alert('Такого дня нет в нижней таблице. Добавьте день внизу.'); return; }

        const row = createRow({
            key,
            targetDay,
            packs,
            filter,
            labelTxt
        });
        dz.appendChild(row);


        set.add(key);
        assigned.add(key);
        setPillDisabledByKey(key,true);
        refreshSaveState();
        lastPickedDay = targetDay;
        recalcDayTotal(targetDay);
    }

    // Модалка выбора даты
    const dpWrap = document.getElementById('datePicker');
    const dpDays = document.getElementById('dpDays');
    const dpClose= document.getElementById('dpClose');
    let pendingPill = null;

    function openDatePicker(pillEl){
        pendingPill = pillEl;
        dpDays.innerHTML = '';
        const days = getAllDays();
        if (!days.length){ alert('Нет дат в нижней таблице. Сначала добавьте дни.'); return; }

        const cutDate = pillEl.dataset.cutDate; // 'YYYY-MM-DD'

        days.forEach(ds=>{
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dayBtn';

            const lines = dayCount(ds);
            const packs = dayPacks(ds);

            btn.innerHTML = `
      <div class="dayHead">${ds}</div>
      <div class="daySub">Назначено полос: ${lines}</div>
      <div class="daySub">Гофропакетів: ${packs} шт</div>
    `;

            if (cutDate && ds < cutDate) {
                btn.disabled = true;        // раніше розкрою — забороняємо
            } else {
                btn.onclick = ()=>{ addToPlan(ds, pendingPill); closeDatePicker(); };
            }

            if (ds === lastPickedDay) btn.style.outline = '2px solid #2563eb';
            dpDays.appendChild(btn);
        });

        dpWrap.style.display = 'flex';
        setTimeout(()=>{ const first = dpDays.querySelector('.dayBtn:not(:disabled)'); if(first) first.focus(); },0);
    }





    function closeDatePicker(){ dpWrap.style.display = 'none'; pendingPill = null; }
    dpClose.addEventListener('click', closeDatePicker);
    dpWrap.addEventListener('click', (e)=>{ if(e.target===dpWrap) closeDatePicker(); });
    document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && dpWrap.style.display==='flex') closeDatePicker(); });

    document.querySelectorAll('.pill').forEach(p=>{
        cutDateByKey.set(p.dataset.key, p.dataset.cutDate);
        p.addEventListener('click', (e)=>{
            if (e.shiftKey && lastPickedDay){ addToPlan(lastPickedDay, p); return; }
            openDatePicker(p);
        });
    });

    // Кнопки дней
    const btnBuildDays = document.getElementById('btnBuildDays');
    const rngStart     = document.getElementById('rngStart');
    const rngDays      = document.getElementById('rngDays');
    const addOneDayInp = document.getElementById('addOneDay');
    const btnAddDay    = document.getElementById('btnAddDay');

    (function initDates(){
        const today = new Date(); const ds = today.toISOString().slice(0,10);
        rngStart.value = ds;
        renderPlanGrid(initialDays.length ? initialDays : [ds]);
    })();

    btnBuildDays.addEventListener('click', ()=>{
        const start = rngStart.value;
        const n = parseInt(rngDays.value||'0',10);
        if(!start || isNaN(n) || n<=0){ alert('Укажите корректный диапазон дат.'); return; }
        const out = [];
        const d0 = parseISO(start);
        for(let i=0;i<n;i++){ const d=new Date(d0); d.setDate(d0.getDate()+i); out.push(iso(d)); }
        renderPlanGrid(out);
    });

    // Добавление одного дня
    btnAddDay.addEventListener('click', ()=>{
        // 1) Визначаємо, який день додати
        const daysNow = getAllDays();
        let newDs;
        if (daysNow.length) {
            const last = daysNow[daysNow.length - 1];
            const nd = parseISO(last); nd.setDate(nd.getDate() + 1);
            newDs = iso(nd);
        } else {
            // якщо таблиця порожня — стартуємо з rngStart або сьогодні
            const base = (rngStart.value || iso(new Date()));
            newDs = base;
        }

        // 3) Додаємо колонку дня в кінець
        ensureDay(newDs);
        const col = document.createElement('div');
        col.className = 'col';
        col.dataset.day = newDs;
        col.innerHTML = `
    <h4>${newDs}</h4>
    <div class="dropzone"></div>
    <div class="dayTotal muted">Итого: <b class="n">0</b> шт</div>
  `;
        planGrid.appendChild(col);

        // 4) Оновлюємо ширину гріда
        const total = daysNow.length + 1;
        planGrid.style.gridTemplateColumns = `repeat(${Math.max(1, total)}, minmax(220px, 1fr))`;
    });


    // Сохранение
    function buildPayload(){
        const items = [];
        document.querySelectorAll('.dropzone .rowItem').forEach(row=>{
            const key    = row.dataset.key || '';
            const packs  = parseInt(row.dataset.packs||'0',10);
            const filter = row.dataset.filter || '';
            const day    = row.dataset.day || '';
            if(!key || !day) return;
            const [bale_id, strip_no] = key.split(':').map(x=>parseInt(x,10));
            if(!bale_id || !strip_no) return;
            items.push({ date: day, bale_id, strip_no, filter, count: packs });
        });
        return { order: orderNumber, items };
    }

    saveBtn.addEventListener('click', async ()=>{
        try{
            const payload = buildPayload();
            const res = await fetch('NP/save_corrugation_plan.php', { // <-- путь, если файл лежит в папке NP
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify(payload)
            });
            let data;
            try { data = await res.json(); }
            catch { const t = await res.text(); throw new Error('Backend не JSON:\n'+t.slice(0,500)); }
            if(!data.ok) throw new Error(data.error||'Ошибка сохранения');
            alert('План сохранён.');
        }catch(e){ alert('Не удалось сохранить: '+e.message); }
    });


    // Загрузка
    // Загрузка
    loadBtn.addEventListener('click', async ()=>{
        const uniqSortedDates = arr => Array.from(new Set(arr.filter(Boolean))).sort();

        try{
            const res = await fetch('NP/save_corrugation_plan.php?order='+encodeURIComponent(orderNumber));
            let data;
            try { data = await res.json(); }
            catch { const t = await res.text(); throw new Error('Backend не JSON:\n'+t.slice(0,500)); }
            if(!data.ok) throw new Error(data.error||'Ошибка загрузки');

            // 1) Зібрати всі дати з бекенда: з data.days і з самих items
            const itemDays = uniqSortedDates((data.items||[]).map(it=>it.date));
            const apiDays  = uniqSortedDates([...(data.days||[]), ...itemDays]);

            // 2) Якщо бекенд нічого не дав — fallback на initialDays
            const days = apiDays.length ? apiDays : (initialDays.length ? initialDays : []);
            renderPlanGrid(days);

            // 3) Розкласти елементи по днях
// 3) Розкласти елементи по днях
            (data.items||[]).forEach(it=>{
                const key  = String(it.bale_id)+':'+String(it.strip_no);
                const pill = document.querySelector(`.pill[data-key="${key}"]`);

                if (pill) {
                    addToPlan(it.date, pill);
                } else {
                    ensureDay(it.date);
                    const dz = document.querySelector(`.col[data-day="${it.date}"] .dropzone`);
                    if (!dz) return;

                    const label   = (it.filter||'Без имени') + ' ['+(it.count||0)+' шт]';
                    const cutDate = cutDateByKey.get(key) || '';  // ← взяли з мапи

                    const row = createRow({
                        key,
                        targetDay: it.date,
                        packs: (it.count||0),
                        filter: (it.filter||''),
                        labelTxt: label,
                        cutDate                          // ← передали явно
                    });
                    dz.appendChild(row);

                    const set = plan.get(it.date); set.add(key);
                    assigned.add(key);
                    setPillDisabledByKey(key,true);
                }
            });


            // 4) Підрахувати підсумки по кожному дню та розблокувати “Сохранить”
            getAllDays().forEach(ds=>recalcDayTotal(ds));
            refreshSaveState();
            alert('План загружен.');
        }catch(e){
            alert('Не удалось загрузить: '+e.message);
        }
    });



    // Инициализация
    (function init(){
        const today = new Date(); const ds = iso(today);
        document.getElementById('rngStart').value = ds;
        renderPlanGrid(initialDays.length ? initialDays : [ds]);
    })();

    function cascadeShiftFrom(ds){
        const s = prompt(`На скільки днів зсунути всі дні ВІД ${ds} (включно)?\nДодатне число — вперед, від’ємне — назад.`, '1');
        if (s === null) return;
        const delta = parseInt(s, 10);
        if (!Number.isFinite(delta) || delta === 0) { alert('Нічого не змінено'); return; }

        fetch('NP/shift_roll_plan_days.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ order: orderNumber, start_date: ds, delta })
        })
            .then(async r => {
                let j; try { j = await r.json(); }
                catch { throw new Error('Backend не JSON'); }
                if (!j.ok) throw new Error(j.error || 'Помилка');
                alert(`Оновлено записів: ${j.affected}. Перезавантажую сторінку...`);
                location.reload();
            })
            .catch(e => alert('Не вдалося зсунути: ' + e.message));
    }

    // прив’язка до кнопок у верхній таблиці
    document.querySelectorAll('.topCascade').forEach(btn=>{
        btn.onclick = ()=> cascadeShiftFrom(btn.dataset.day);
    });

</script>
