<?php
$dsn='mysql:host=127.0.0.1;dbname=plan_U5;charset=utf8mb4'; $user='root'; $pass='';
$orderNumber=$_GET['order_number']??''; if($orderNumber===''){http_response_code(400);exit('Укажите ?order_number=...');}
try{
    $pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

    $sql="
    SELECT
      o.order_number,
      o.filter,
      pps.p_p_material      AS material,
      pps.p_p_width         AS strip_width_mm,
      pps.p_p_height        AS pleat_height_mm,
      SUM(o.count)          AS strips_qty,
      ROUND(((pps.p_p_height*2*pps.p_p_pleats_count)*SUM(o.count))/1000, 3) AS total_length_m
    FROM orders o
    JOIN salon_filter_structure sfs ON sfs.filter=o.filter
    JOIN paper_package_salon pps ON pps.p_p_name=sfs.paper_package
    WHERE o.order_number=:order_number
    GROUP BY o.order_number,o.filter,pps.p_p_material,pps.p_p_width,pps.p_p_height,pps.p_p_pleats_count
    ORDER BY total_length_m DESC";
    $st=$pdo->prepare($sql); $st->execute([':order_number'=>$orderNumber]); $rows=$st->fetchAll();

    $rowsSimple=[]; $rowsCarbon=[];
    $totalMeters=0.0;
    foreach($rows as $r){
        $totalMeters += (float)$r['total_length_m'];
        if (strtoupper((string)$r['material'])==='CARBON') $rowsCarbon[]=$r; else $rowsSimple[]=$r;
    }
}catch(Throwable $e){http_response_code(500);echo 'Ошибка: '.$e->getMessage(); exit;}
?>
<!doctype html><meta charset="utf-8">
<title>Раскрой #<?=htmlspecialchars($orderNumber)?></title>
<style>
    :root{ --gap:12px; --left:600px; --mid:600px; --right:600px; }
    *{box-sizing:border-box}
    body{font:12px/1.25 Arial;margin:10px;height:100vh;overflow:hidden}
    h2{margin:0 0 8px;font:600 16px/1.2 Arial}
    .wrap{display:grid;grid-template-columns:var(--left) var(--mid) var(--right);gap:var(--gap);height:calc(100vh - 38px)}

    .left{overflow-y:auto;overflow-x:hidden;border:1px solid #ddd;border-radius:8px;padding:8px;font-size:11px}
    .section{margin-bottom:10px}
    .section h3{margin:0 0 6px;font:600 13px/1.2 Arial}

    .panel{border:1px solid #ccc;border-radius:8px;padding:10px;height:100%;overflow:auto}
    .panel h3{margin:0 0 6px;font:600 14px/1.2 Arial}
    .meta{margin:6px 0 8px}
    .meta span{display:inline-block;margin-right:10px}
    #currentBalePanel{max-height:300px;overflow-y:auto}

    table{border-collapse:collapse;table-layout:fixed}
    th,td{border:1px solid #ccc;padding:4px 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    th{background:#f6f6f6}
    .right{text-align:right}

    /* колонки в левой таблице */
    .col-filter{width:160px}.col-w{width:55px}.col-h{width:45px}.col-q{width:48px}
    .col-sum{width:70px}.col-cut{width:80px}.col-rest{width:80px}

    /* подсветки по остаткам */
    .posTable tr.sel td{background: #ffd0d0 !important}
    .posTable tr:hover td{background:#f3f8ff}
    .posTable tr.rest-colored td{background:var(--rest-bg)}

    /* подсветка кандидатов по ширине (оверлей поверх строки) */
    .posTable tr.width-cand td{ position:relative; }
    .posTable tr.width-cand td::after{
        content:""; position:absolute; inset:0;
        background: var(--wbg, transparent);
        pointer-events:none;
    }

    .btn{border:1px solid #aaa;padding:5px 9px;border-radius:6px;background:#fafafa;cursor:pointer}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .ctrls{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0}

    /* таблицы бухты */
    .baleTbl{width:100%;table-layout:fixed;margin-top:6px}
    .bcol-act{width:36px}.bcol-pos{width:200px}.bcol-w{width:80px}.bcol-h{width:70px}.bcol-l{width:120px}
    .lenInput{width:100%;padding:3px 6px;border:1px solid #bbb;border-radius:6px}
    .delBtn{border:1px solid #d66;background:#fee;border-radius:6px;padding:2px 8px;cursor:pointer}
    .delBtn:hover{background:#fdd}

    .balesList .card{border:1px dashed #bbb;border-radius:8px;padding:8px;margin-bottom:8px}

    /* контекстное меню */
    .menu{position:fixed;z-index:50;display:none;background:#fff;border:1px solid #ccc;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.15);padding:8px}
    .menu .row{display:flex;align-items:center;gap:6px;margin:4px 0}
    .menu .mi{border:1px solid #aaa;padding:5px 9px;border-radius:6px;background:#fafafa;cursor:pointer;white-space:nowrap}
    .menu input{width:50px;padding:4px 6px;border:1px solid #bbb;border-radius:6px}
    /* шире кнопка «Добавить авто» */
    #mi_auto{min-width:180px; text-align:center;}
    #mi_addN {
        width: 125px; /* Установите ширину для кнопки "Добавить N" */
    }
</style>

<h2>Раскрой по заявке #<?=htmlspecialchars($orderNumber)?></h2>
<div class="wrap">
    <!-- ЛЕВАЯ: Simple -->
    <div class="left">
        <div class="section">
            <h3>Простой материал</h3>
            <table id="tblSimple" class="posTable">
                <colgroup>
                    <col class="col-filter"><col class="col-w"><col class="col-h"><col class="col-q">
                    <col class="col-sum"><col class="col-cut"><col class="col-rest">
                </colgroup>
                <tr>
                    <th>Фильтр</th><th>Ширина, мм</th><th>H, мм</th><th>Шт</th>
                    <th class="right">Σ, м</th><th class="right">в раскрое, м</th><th class="right">остаток, м</th>
                </tr>
                <?php foreach($rowsSimple as $i=>$r): ?>
                    <?php $tm=(float)$r['total_length_m']; $qty=(int)$r['strips_qty']; ?>
                    <tr data-i="s<?=$i?>"
                        data-mat="Simple"
                        data-filter="<?=htmlspecialchars($r['filter'])?>"
                        data-w="<?=$r['strip_width_mm']?>"
                        data-h="<?=$r['pleat_height_mm']?>"
                        data-q="<?=$qty?>"
                        data-q0="<?=$qty?>"
                        data-tm="<?=$tm?>"
                        data-cutm="0">
                        <td><?=htmlspecialchars($r['filter'])?></td>
                        <td><?=$r['strip_width_mm']?></td>
                        <td><?=$r['pleat_height_mm']?></td>
                        <td class="q"><?=$qty?></td>
                        <td class="right tm"><?=number_format($tm,3,'.',' ')?></td>
                        <td class="right cutm">0.000</td>
                        <td class="right restm"><?=number_format($tm,3,'.',' ')?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- ЛЕВАЯ: Carbon -->
        <div class="section">
            <h3>Carbon</h3>
            <table id="tblCarbon" class="posTable">
                <colgroup>
                    <col class="col-filter"><col class="col-w"><col class="col-h"><col class="col-q">
                    <col class="col-sum"><col class="col-cut"><col class="col-rest">
                </colgroup>
                <tr>
                    <th>Фильтр</th><th>Ширина, мм</th><th>H, мм</th><th>Шт</th>
                    <th class="right">Σ, м</th><th class="right">в раскрое, м</th><th class="right">остаток, м</th>
                </tr>
                <?php foreach($rowsCarbon as $i=>$r): ?>
                    <?php $tm=(float)$r['total_length_m']; $qty=(int)$r['strips_qty']; ?>
                    <tr data-i="c<?=$i?>"
                        data-mat="Carbon"
                        data-filter="<?=htmlspecialchars($r['filter'])?>"
                        data-w="<?=$r['strip_width_mm']?>"
                        data-h="<?=$r['pleat_height_mm']?>"
                        data-q="<?=$qty?>"
                        data-q0="<?=$qty?>"
                        data-tm="<?=$tm?>"
                        data-cutm="0">
                        <td><?=htmlspecialchars($r['filter'])?></td>
                        <td><?=$r['strip_width_mm']?></td>
                        <td><?=$r['pleat_height_mm']?></td>
                        <td class="q"><?=$qty?></td>
                        <td class="right tm"><?=number_format($tm,3,'.',' ')?></td>
                        <td class="right cutm">0.000</td>
                        <td class="right restm"><?=number_format($tm,3,'.',' ')?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <p class="quiet">ИТОГО материала: <b><?=number_format($totalMeters,3,'.',' ')?> м</b></p>
    </div>

    <!-- ЦЕНТР -->
    <div class="panel" id="currentBalePanel">
        <h3>Текущая бухта</h3>
        <div class="meta">
            <span>Выбрано: <b id="selName" class="quiet">—</b></span>
            <span>Материал: <b id="selMat" class="quiet">—</b></span>
            <span>Ширина бухты: <b id="bw">0.0</b> / 1000.0 мм</span>
            <span>Остаток: <b id="rest">1000.0</b> мм</span>
            <span>Материал бухты: <b id="baleMat" class="quiet">—</b></span>
        </div>
        <div class="ctrls">
            <!-- Поле N и «Добавить пустую» удалены -->
            <button class="btn" id="btnSave" disabled>Сохранить бухту</button>
            <button class="btn" id="btnClear" disabled>Очистить</button>
        </div>
        <div id="baleList" class="quiet">Пусто</div>
    </div>

    <!-- ПРАВО -->
    <div class="panel">
        <h3>Собранные бухты</h3>
        <div id="bales" class="balesList quiet">Пока нет</div>
    </div>
</div>

<!-- ВСПЛЫВАЮЩЕЕ МЕНЮ -->
<div class="menu" id="ctxMenu">
    <div class="row"><button class="mi" id="mi_auto">Добавить авто</button></div>
    <div class="row">
        <button class="mi" id="mi_addN">Добавить N</button>
        <input id="mi_n" type="number" min="1" step="1" value="1" placeholder="N" >

    </div>
</div>

<script>
    const BALE_WIDTH=1000.0, eps=1e-9;
    let curRow=null, baleStrips=[], baleWidth=0.0, baleMaterial=null, bales=[];
    let lastN = 1; // дефолт для меню
    const el=(id)=>document.getElementById(id);
    const fmt1=(x)=>(Math.round(x*10)/10).toFixed(1);
    const fmt3=(x)=>(Math.round(x*1000)/1000).toFixed(3);
    const round3=(x)=>Math.round(x*1000)/1000;

    /* === утилиты левой таблицы === */
    function restMetersOf(tr){
        const tm = parseFloat(tr.dataset.tm || '0');
        const cut= parseFloat(tr.dataset.cutm || '0');
        return Math.max(0, round3(tm - cut));
    }

    function applyRestColor(tr){
        const rest = restMetersOf(tr);
        if (rest <= 0 + eps) {
            tr.classList.add('rest-colored');
            tr.style.setProperty('--rest-bg','rgb(200,247,197)');
        } else if (rest <= 20) {
            const g=[200,247,197], y=[255,243,176];
            const t = rest/20;
            const r = Math.round(g[0] + (y[0]-g[0])*t);
            const gg= Math.round(g[1] + (y[1]-g[1])*t);
            const b = Math.round(g[2] + (y[2]-g[2])*t);
            tr.classList.add('rest-colored');
            tr.style.setProperty('--rest-bg',`rgb(${r},${gg},${b})`);
        } else {
            tr.classList.remove('rest-colored');
            tr.style.removeProperty('--rest-bg');
        }
    }

    /* подсветка кандидатов по ширине в окне [free-30 .. free] */
    function highlightWidthMatches(){
        const RANGE = 30;
        const free = Math.max(0, BALE_WIDTH - baleWidth);
        const mat  = baleMaterial;

        document.querySelectorAll('.posTable tr[data-i]').forEach(tr=>{
            tr.classList.remove('width-cand');
            tr.style.removeProperty('--wbg');

            if(!baleStrips.length || !mat) return;
            if(tr.dataset.mat !== mat) return;

            const w = parseFloat(tr.dataset.w);
            const delta = free - w;

            if (delta < -eps || delta > RANGE + eps) return;

            const t = Math.max(0, Math.min(1, 1 - (delta / RANGE)));
            const light=[210,235,255], dark=[0,102,204];
            const r=Math.round(light[0] + (dark[0]-light[0])*t);
            const g=Math.round(light[1] + (dark[1]-light[1])*t);
            const b=Math.round(light[2] + (dark[2]-light[2])*t);

            tr.classList.add('width-cand');
            tr.style.setProperty('--wbg', `rgba(${r},${g},${b},0.40)`);
        });
    }

    /* выбор строки */
    function setSelection(tr){
        document.querySelectorAll('.posTable tr').forEach(r=>r.classList.remove('sel'));
        curRow=tr||null;
        if(!tr){
            el('selName').textContent='—'; el('selMat').textContent='—';
            el('selName').classList.add('quiet'); el('selMat').classList.add('quiet');
            toggleCtrls(); return;
        }
        tr.classList.add('sel');
        el('selName').textContent = tr.dataset.filter + ` | ${tr.dataset.w}×${tr.dataset.h}`;
        el('selMat').textContent  = tr.dataset.mat || '—';
        el('selName').classList.remove('quiet'); el('selMat').classList.remove('quiet');
        toggleCtrls();
    }
    document.querySelectorAll('.posTable').forEach(tbl=>{
        tbl.addEventListener('click',e=>{
            const tr=e.target.closest('tr[data-i]'); if(!tr) return;
            setSelection(tr); openMenu(e.clientX, e.clientY);
        });
        tbl.addEventListener('contextmenu',e=>{
            const tr=e.target.closest('tr[data-i]'); if(!tr) return;
            e.preventDefault(); setSelection(tr); openMenu(e.clientX, e.clientY);
        });
        tbl.querySelectorAll('tr[data-i]').forEach(applyRestColor);
    });

    /* центральные кнопки */
    function toggleCtrls(){
        const hasBale=baleStrips.length>0;
        if(el('btnSave'))  el('btnSave').disabled = !hasBale;
        if(el('btnClear')) el('btnClear').disabled= !hasBale;
    }
    if(el('btnSave'))  el('btnSave').addEventListener('click', saveBale);
    if(el('btnClear')) el('btnClear').addEventListener('click', clearBale);

    /* обновление метрик (по Δметров) */
    function updateRowMeters(tr, deltaMeters){
        const total = parseFloat(tr.dataset.tm);
        const cutPrev = parseFloat(tr.dataset.cutm||'0');
        let cutNow = round3(cutPrev + deltaMeters);
        if (cutNow < 0) cutNow = 0;
        if (cutNow > total) cutNow = total;
        const effDelta = round3(cutNow - cutPrev);
        tr.dataset.cutm = cutNow;
        const rest  = Math.max(0, round3(total - cutNow));
        tr.querySelector('.cutm').textContent = fmt3(cutNow);
        tr.querySelector('.restm').textContent = fmt3(rest);
        applyRestColor(tr);
        return effDelta;
    }

    /* интерфейс бухты */
    function updBaleUI(){
        el('bw').textContent=fmt1(baleWidth);
        el('rest').textContent=fmt1(Math.max(0,BALE_WIDTH-baleWidth));
        el('baleMat').textContent=baleMaterial?baleMaterial:'—';
        if(!baleMaterial) el('baleMat').classList.add('quiet'); else el('baleMat').classList.remove('quiet');

        const box=el('baleList');
        if(!baleStrips.length){box.textContent='Пусто';box.classList.add('quiet');toggleCtrls();highlightWidthMatches();return;}
        box.classList.remove('quiet');

        let html='<table class="baleTbl"><colgroup><col class="bcol-act"><col class="bcol-pos"><col class="bcol-w"><col class="bcol-h"><col class="bcol-l"></colgroup>';
        html+='<tr><th></th><th>Позиция</th><th>Ширина</th><th>H</th><th>Длина</th></tr>';
        html+=baleStrips.map((s,idx)=>`
      <tr>
        <td><button class="delBtn" title="Убрать полосу" data-idx="${idx}">×</button></td>
        <td>${s.filter}</td>
        <td>${fmt1(s.w)} мм</td>
        <td>${s.h} мм</td>
        <td>
          <input type="number" class="lenInput" data-idx="${idx}" min="0" step="10" value="${fmt3(s.len)}">
        </td>
      </tr>`).join('');
        html+='</table>';
        box.innerHTML=html;

        box.querySelectorAll('.lenInput').forEach(inp=>{
            inp.addEventListener('change', e=>{
                let L = round3(parseFloat(e.target.value||'0')); if(isNaN(L)||L<0) L=0;
                unifyBaleLength(L);
            });
        });
        box.querySelectorAll('.delBtn').forEach(btn=>{
            btn.addEventListener('click', e=>{
                const i = parseInt(e.currentTarget.dataset.idx,10);
                removeStrip(i);
            });
        });

        toggleCtrls();
        highlightWidthMatches();
    }

    // Сделать все полосы одинаковой длины L
    function unifyBaleLength(Lnew){
        if(!baleStrips.length) return;
        const groups=[]; const map=new Map();
        for(let i=0;i<baleStrips.length;i++){
            const s=baleStrips[i];
            const key = s.rowEl ? s.rowEl.dataset.i : `nr:${s.filter}:${s.w}:${s.h}`;
            let g=map.get(key);
            if(!g){ g={rowEl:s.rowEl, idxs:[], sum:0, count:0, avg:0, rest:Infinity}; map.set(key,g); groups.push(g); }
            g.idxs.push(i); g.sum=round3(g.sum + s.len); g.count++;
        }
        groups.forEach(g=>{
            g.avg = g.count ? round3(g.sum/g.count) : 0;
            g.rest = g.rowEl ? restMetersOf(g.rowEl) : Infinity;
        });

        let Lmax = Infinity;
        for(const g of groups){
            const addPerStrip = (g.rest===Infinity) ? Infinity : g.rest / g.count;
            const candidate = (g.avg) + addPerStrip;
            if(candidate < Lmax) Lmax = candidate;
        }
        if(Lnew > Lmax){
            Lnew = round3(Lmax);
            alert('Недостаточно остатка по одной из позиций. Длина бухты увеличена до максимально возможной.');
        }

        for(const g of groups){
            const desiredSum = round3(Lnew * g.count);
            const delta = round3(desiredSum - g.sum);
            if(g.rowEl) updateRowMeters(g.rowEl, delta);
        }
        baleStrips.forEach(s=>{ s.len=Lnew; });

        updBaleUI();
    }

    function ensureMaterial(mat){
        if(!baleMaterial){ baleMaterial=mat; return true; }
        if(baleMaterial===mat) return true;
        alert('Материал текущей бухты: '+baleMaterial+'. Нельзя смешивать с: '+mat+'. Очистите или сохраните бухту.');
        return false;
    }

    /* Удаление одной полосы */
    function removeStrip(idx){
        const s = baleStrips[idx];
        if(!s) return;
        if(s.rowEl) updateRowMeters(s.rowEl, -round3(s.len));
        baleWidth = Math.max(0, round3(baleWidth - s.w));
        baleStrips.splice(idx,1);
        if(!baleStrips.length) baleMaterial = null;
        updBaleUI();
    }

    /* Добавление: переносим весь «остаток, м» выбранной строки, длина каждой из X полос = остаток/X */
    function addStrips(take){
        if(!curRow) return;
        let w   = parseFloat(curRow.dataset.w),
            h   = parseFloat(curRow.dataset.h),
            mat = curRow.dataset.mat || 'Simple';

        if(take<=0) return;
        if(!ensureMaterial(mat)) return;

        const restMeters = restMetersOf(curRow);
        if(restMeters<=0){ alert('По этой позиции метров не осталось.'); return; }

        const perLen = round3(restMeters / take);

        const free=Math.max(0, BALE_WIDTH-baleWidth);
        const needW=w*take;
        if(needW > free+eps){ alert('Не помещается по ширине.'); return; }

        for(let i=0;i<take;i++)
            baleStrips.push({filter:curRow.dataset.filter,w:w,h:h,len:perLen,mat:mat,rowEl:curRow});

        baleWidth += needW;

        updateRowMeters(curRow, restMeters);
        updBaleUI();
    }

    function addAuto(){
        if(!curRow) return;
        const w = parseFloat(curRow.dataset.w);
        const free = Math.max(0, BALE_WIDTH - baleWidth);
        const take = Math.floor((free + eps) / w);
        if(take<=0){ alert('Не помещается по ширине.'); return; }
        addStrips(take);
    }

    function addN(n){
        n = parseInt(n||'0',10);
        if(!n || n<=0){ alert('Укажите N ≥ 1'); return; }
        if(!curRow) return;
        const w = parseFloat(curRow.dataset.w);
        const free = Math.max(0, BALE_WIDTH - baleWidth);
        const maxFit = Math.floor((free + eps) / w);
        const take = Math.min(n, maxFit);
        if(take<=0){ alert('Не помещается по ширине.'); return; }
        addStrips(take);
        lastN = n;
    }

    // Очистка: вернуть все метры по группам
    function clearBale(){
        if(!baleStrips.length){ updBaleUI(); return; }
        const sums = new Map();
        for(const s of baleStrips){
            if(!s.rowEl) continue;
            sums.set(s.rowEl, round3((sums.get(s.rowEl)||0) + s.len));
        }
        for (const [tr, sum] of sums.entries()){
            updateRowMeters(tr, -sum);
        }
        baleStrips=[]; baleWidth=0; baleMaterial=null;
        updBaleUI();
    }

    function saveBale(){
        if(!baleStrips.length) return;
        let totalLen=baleStrips.reduce((s,x)=>s+x.len,0);
        bales.push({w:baleWidth, len:round3(totalLen), mat:baleMaterial, strips:[...baleStrips]});
        renderBales(); baleStrips=[]; baleWidth=0; baleMaterial=null; updBaleUI();
    }

    function renderBales(){
        const box=el('bales');
        if(!bales.length){box.textContent='Пока нет'; box.classList.add('quiet'); return;}
        box.classList.remove('quiet');
        const byMat={}; for(const b of bales){ (byMat[b.mat]??=[]).push(b); }
        let html='';
        for(const mat of Object.keys(byMat)){
            html+=`<div style="margin:4px 0 6px"><b>${mat}</b></div>`;
            html+=byMat[mat].map((b,i)=>{
                let rows=b.strips.map(s=>`<tr><td>${s.filter}</td><td>${fmt1(s.w)} мм</td><td>${s.h} мм</td><td>${fmt3(s.len)} м</td></tr>`).join('');
                return `<div class="card">
          <div style="margin-bottom:6px"><b>Бухта</b> · Материал: <b>${b.mat}</b> · Ширина: <b>${fmt1(b.w)}</b> мм · Σ длина: <b>${fmt3(b.len)}</b> м</div>
          <table class="baleTbl"><colgroup><col class="bcol-pos"><col class="bcol-w"><col class="bcol-h"><col class="bcol-l"></colgroup>
            <tr><th>Позиция</th><th>Ширина</th><th>H</th><th>Длина</th></tr>${rows}
          </table>
        </div>`;
            }).join('');
        }
        box.innerHTML=html;
    }

    /* контекстное меню */
    const menu=el('ctxMenu');
    function openMenu(cx,cy){
        menu.style.display='block';
        const pad=6;
        let x=Math.min(window.innerWidth - menu.offsetWidth - pad, Math.max(pad, cx+pad));
        let y=Math.min(window.innerHeight- menu.offsetHeight- pad, Math.max(pad, cy+pad));
        menu.style.left=x+'px'; menu.style.top=y+'px';
        el('mi_n').value = String(lastN || 1);
    }
    function closeMenu(){ menu.style.display='none'; }
    document.addEventListener('click',e=>{
        if(!e.target.closest('#ctxMenu') && !e.target.closest('.posTable')) closeMenu();
    });
    el('mi_auto').addEventListener('click',()=>{ addAuto(); closeMenu(); });
    el('mi_addN').addEventListener('click',()=>{ const n=parseInt(el('mi_n').value||'1',10); addN(n); closeMenu(); });

    /* init */
    updBaleUI(); setSelection(null); highlightWidthMatches();
</script>
