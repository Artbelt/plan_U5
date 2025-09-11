<?php
/***** AJAX: сохранить/загрузить раскрой в ОДНУ таблицу cut_plan_salon
Требуется столбец fact_length_m (DECIMAL(10,3) NULL) *****/
if (isset($_GET['action']) && in_array($_GET['action'], ['save_plan','load_plan'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $dsn='mysql:host=127.0.0.1;dbname=plan_U5;charset=utf8mb4'; $user='root'; $pass='';
    try {
        $pdo=new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);

        if ($_GET['action']==='save_plan') {
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!$payload || !isset($payload['order_number']) || !isset($payload['bales'])) {
                http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad payload']); exit;
            }
            $order  = (string)$payload['order_number'];
            $format = (int)($payload['format'] ?? 1000);
            $bales  = $payload['bales'];

            $pdo->beginTransaction();

            // авто-миграция полей статусов
            $hasCutReady = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='cut_ready'")->fetchColumn();
            if (!$hasCutReady) {
                $pdo->exec("ALTER TABLE orders ADD cut_ready TINYINT(1) NOT NULL DEFAULT 0");
            }
            $hasCutConfirmed = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='cut_confirmed'")->fetchColumn();
            if (!$hasCutConfirmed) {
                $pdo->exec("ALTER TABLE orders ADD cut_confirmed TINYINT(1) NOT NULL DEFAULT 0");
            }

            // очистка старого раскроя
            $pdo->prepare("DELETE FROM cut_plans WHERE order_number=?")->execute([$order]);

            // вставки нового раскроя
            $ins = $pdo->prepare("
      INSERT INTO cut_plans
        (order_number,bale_id,strip_no,material,filter,width,height,length,format,source,fact_length)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");

            $rowsInserted = 0;
            foreach ($bales as $i=>$b) {
                $baleNo = $i+1;
                $mat    = (string)$b['mat'];
                $fact   = isset($b['fact']) ? (float)$b['fact'] : null;
                $j=1;
                foreach ($b['strips'] as $s) {
                    $ins->execute([
                        $order,
                        $baleNo,
                        $j++,
                        $mat,
                        (string)$s['filter'],
                        (float)$s['w'],
                        (float)$s['h'],
                        (float)$s['len'],
                        $format,
                        !empty($s['rowEl']) ? 'order' : 'assort',
                        $fact
                    ]);
                    $rowsInserted++;
                }
            }

            // статусы заявки: раскрой готов/не готов, подтверждение сбрасываем
            $stUpd = $pdo->prepare("UPDATE orders SET cut_ready=?, cut_confirmed=? WHERE order_number=?");
            $stUpd->execute([$rowsInserted > 0 ? 1 : 0, 0, $order]);

            $pdo->commit();
            echo json_encode(['ok'=>true, 'rows'=>$rowsInserted]); exit;
        }


        if ($_GET['action']==='load_plan') {
            $order = $_GET['order_number'] ?? '';
            if ($order==='') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

            $st=$pdo->prepare("
              SELECT order_number,bale_id,strip_no,material,filter,width,height,length,format,source,fact_length
              FROM cut_plans
              WHERE order_number=?
              ORDER BY bale_id, strip_no
            ");
            $st->execute([$order]);
            $rows = $st->fetchAll();
            if (!$rows) { echo json_encode(['ok'=>true,'exists'=>false,'bales'=>[],'format_mm'=>1000]); exit; }

            $format=(int)$rows[0]['format'];
            $bales=[]; $curNo=null; $cur=null; $curFact=null;
            foreach ($rows as $r) {
                if ($curNo !== (int)$r['bale_id']) {
                    if ($cur) { $cur['fact']=$curFact; $bales[]=$cur; }
                    $curNo=(int)$r['bale_id'];
                    $cur=['w'=>0.0,'len'=>0.0,'mat'=>$r['material'],'strips'=>[]];
                    $curFact = null;
                }
                $cur['w']   += (float)$r['width'];
                $cur['len'] += (float)$r['length'];
                if ($r['fact_length'] !== null) $curFact = (float)$r['fact_length'];
                $cur['strips'][]=[
                    'filter'=>$r['filter'],
                    'w'=>(float)$r['width'],
                    'h'=>(float)$r['height'],
                    'len'=>(float)$r['length'],
                    'rowEl'=>null
                ];
            }
            if ($cur) { $cur['fact']=$curFact; $bales[]=$cur; }

            echo json_encode(['ok'=>true,'exists'=>true,'bales'=>$bales,'format_mm'=>$format]); exit;
        }
    } catch(Throwable $e){
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
    }
    exit;
}
?>
<?php
$dsn='mysql:host=127.0.0.1;dbname=plan_U5;charset=utf8mb4'; $user='root'; $pass='';
$orderNumber=$_GET['order_number']??''; if($orderNumber===''){http_response_code(400);exit('Укажите ?order_number=...');}
try{
    $pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

    // позиции ЗАЯВКИ
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

    // АССОРТИМЕНТ
    $sqlAssort="
    SELECT
      sfs.filter,
      pps.p_p_material  AS material,
      pps.p_p_width     AS strip_width_mm,
      pps.p_p_height    AS pleat_height_mm
    FROM salon_filter_structure sfs
    JOIN paper_package_salon pps ON pps.p_p_name = sfs.paper_package
    GROUP BY sfs.filter, pps.p_p_material, pps.p_p_width, pps.p_p_height
    ORDER BY pps.p_p_material, sfs.filter";
    $assort = $pdo->query($sqlAssort)->fetchAll();

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

    .panel{border:1px solid #ccc;border-radius:8px;padding:10px;height:100%;overflow:auto;background:#fff}
    .panel h3{margin:0 0 6px;font:600 14px/1.2 Arial}
    .meta{margin:6px 0 8px}
    .meta span{display:inline-block;margin-right:10px}

    #currentBalePanel{max-height:600px;overflow-y:auto}

    table{border-collapse:collapse;table-layout:fixed;width:100%}
    th,td{border:1px solid #ccc;padding:4px 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    th{background:#f6f6f6}
    .right{text-align:right}

    /* === МЕТКИ (новое) === */
    .col-mark{width:26px}
    .markCell{ text-align:center; cursor:pointer; }
    .dot{
        width:12px; height:12px;
        display:inline-block; border-radius:999px;
        border:0;
        box-shadow: inset 0 0 0 0.5px #111; /* «пол-пикселя» */
        background:transparent;
    }
    .markCell.on .dot{ background:#111; }
    /* дополнительно можно подсветить всю строку слегка серым, если нужно */
    .posTable tr.marked td{ background-image: linear-gradient(to right, rgba(0,0,0,.06), rgba(0,0,0,0)); }
    .posTable tr.marked .dot{ background:#ef4444; }
    .col-filter{width:180px}.col-w{width:60px}.col-h{width:50px}
    .col-sum{width:80px}.col-cut{width:90px}.col-rest{width:90px}

    .posTable tr.sel td{background: #ffd0d0 !important}
    .posTable tr:hover td{background:#f3f8ff}
    .posTable tr.rest-colored td{background:var(--rest-bg)}

    .posTable tr.width-cand td{ position:relative; }
    .posTable tr.width-cand td::after{
        content:""; position:absolute; inset:0; background: var(--wbg, transparent); pointer-events:none;
    }

    .btn{border:1px solid #aaa;padding:5px 9px;border-radius:6px;background:#fafafa;cursor:pointer}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .ctrls{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:6px 0}
    .factControl{display:flex;align-items:center;gap:6px}
    .factControl input{width:90px;padding:4px 6px;border:1px solid #bbb;border-radius:6px}

    .baleTbl{table-layout:fixed;margin-top:6px}
    .bcol-act{width:36px}.bcol-pos{width:160px}.bcol-w{width:80px}.bcol-h{width:70px}.bcol-l{width:140px}
    .lenInput{width:100%;padding:3px 6px;border:1px solid #bbb;border-radius:6px}
    .delBtn{border:1px solid #d66;background:#fee;border-radius:6px;padding:2px 8px;cursor:pointer}
    .delBtn:hover{background:#fdd}

    .lenWrap{display:flex;align-items:center;gap:6px}
    .eqBtn{border:1px solid #aaa;background:#f6f6f6;border-radius:6px;padding:2px 8px;cursor:pointer;line-height:1;font-weight:600}
    .eqBtn:hover{background:#eee}

    .balesList .card{border:1px dashed #bbb;border-radius:8px;padding:8px;margin-bottom:8px;background:#fff}
    .cardHead{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;gap:8px}
    .delBaleBtn{border:1px solid #d66;background:#fee;border-radius:6px;padding:3px 10px;cursor:pointer}
    .delBaleBtn:hover{background:#fdd}
    .panelHead{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}

    .menu{position:fixed;z-index:50;display:none;background:#fff;border:1px solid #ccc;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.15);padding:8px}
    .menu .row{display:flex;align-items:center;gap:6px;margin:4px 0}
    .menu .mi{border:1px solid #aaa;padding:5px 9px;border-radius:6px;background:#fafafa;cursor:pointer;white-space:nowrap}
    .menu input{width:35px;padding:4px 6px;border:1px solid #bbb;border-radius:6px}
    #mi_auto{min-width:180px; text-align:center;}

    #assortPanel{max-height:340px;overflow:auto}
    .acol-mat{width:80px}.acol-filter{width:180px}.acol-w{width:70px}.acol-h{width:60px}.acol-act{width:120px}
    .assortBtn{border:1px solid #aaa;padding:3px 8px;border-radius:6px;background:#fafafa;cursor:pointer;margin-right:4px}

    @media print {
        @page { size: A4 portrait; margin: 10mm; }

        /* страница должна тянуться на несколько листов */
        html, body { height:auto !important; overflow:visible !important; }
        body { margin:0 !important; }

        /* показываем только правую панель со списком бухт */
        h2, .left, .mid { display:none !important; }
        .wrap { display:block !important; height:auto !important; }

        #balesPanel {
            display:block !important;
            position:static !important;
            width:auto !important;
            border:none !important;
            box-shadow:none !important;
            background:#fff !important;
        }

        /* карточки уверенно разбиваются по страницам */
        #balesPanel .balesList { column-count: 2; column-gap: 8mm; }  /* хочешь в одну колонку — поставь 1 */
        #balesPanel .balesList > div:not(.card) { break-inside: avoid; margin: 0 0 3mm; padding: 0; }

        #balesPanel .card {
            break-inside: avoid; page-break-inside: avoid;
            margin: 0 0 6mm 0; border: 1px solid #000; box-shadow: none;
            background: #fff;
        }
        #balesPanel .cardHead { margin-bottom: 2mm; }
        #balesPanel .baleTbl th, #balesPanel .baleTbl td { padding: 2mm 2mm; }
        #balesPanel .baleTbl th { background: #fff; }

        /* убираем интерактив */
        .btn, .delBaleBtn, .delBtn, .lenInput, .menu, .eqBtn { display: none !important; }

        /* чтобы цвета/границы не «серали» в печати (хром/edge) */
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        /* Узкие колонки в печати */
        #balesPanel .baleTbl{
            table-layout: fixed;      /* жёсткие ширины колонок */
            width:100%;
            font-size: 10px;          /* компактнее текст */
        }
        #balesPanel .baleTbl th,
        #balesPanel .baleTbl td{
            padding: 1mm 1mm;         /* меньше паддинги */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        /* конкретные ширины колонок (на одну карточку ~ 91мм) */
        #balesPanel .baleTbl col.bcol-pos { width: 32mm; }  /* Позиция */
        #balesPanel .baleTbl col.bcol-w   { width: 18mm; }  /* Ширина */
        #balesPanel .baleTbl col.bcol-h   { width: 12mm; }  /* H */
        #balesPanel .baleTbl col.bcol-l   { width: 14mm; }  /* Длина */

        /* чуть компактнее заголовки таблицы */
        #balesPanel .baleTbl th{ font-weight:700; }

        /* если всё равно тесно — уменьши зазор между колонками листа */
        #balesPanel .balesList{ column-gap: 6mm; } /* было 8mm */

    }

</style>

<h2>Раскрой по заявке #<?=htmlspecialchars($orderNumber)?></h2>
<div class="wrap">
    <!-- ЛЕВАЯ: Simple + Carbon -->
    <div class="left">
        <div class="section">
            <h3>Простой материал</h3>
            <table id="tblSimple" class="posTable">
                <colgroup>
                    <col class="col-mark">
                    <col class="col-filter"><col class="col-w"><col class="col-h">
                    <col class="col-sum"><col class="col-cut"><col class="col-rest">
                </colgroup>
                <tr>
                    <th title="Метка">М</th>
                    <th>Фильтр</th><th>Ширина, мм</th><th>H, мм</th>
                    <th class="right">Σ, м</th><th class="right">в раскрое, м</th><th class="right">остаток, м</th>
                </tr>
                <?php foreach($rowsSimple as $i=>$r): ?>
                    <?php $tm=(float)$r['total_length_m']; ?>
                    <tr data-i="s<?=$i?>"
                        data-mat="Simple"
                        data-filter="<?=htmlspecialchars($r['filter'])?>"
                        data-w="<?=$r['strip_width_mm']?>"
                        data-h="<?=$r['pleat_height_mm']?>"
                        data-tm="<?=$tm?>"
                        data-cutm="0">
                        <td class="markCell"><span class="dot" aria-hidden="true"></span></td>
                        <td><?=htmlspecialchars($r['filter'])?></td>
                        <td><?=$r['strip_width_mm']?></td>
                        <td><?=$r['pleat_height_mm']?></td>
                        <td class="right tm"><?=number_format($tm,3,'.',' ')?></td>
                        <td class="right cutm">0.000</td>
                        <td class="right restm"><?=number_format($tm,3,'.',' ')?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h3>Carbon</h3>
            <table id="tblCarbon" class="posTable">
                <colgroup>
                    <col class="col-mark">
                    <col class="col-filter"><col class="col-w"><col class="col-h">
                    <col class="col-sum"><col class="col-cut"><col class="col-rest">
                </colgroup>
                <tr>
                    <th title="Метка">М</th>
                    <th>Фильтр</th><th>Ширина, мм</th><th>H, мм</th>
                    <th class="right">Σ, м</th><th class="right">в раскрое, м</th><th class="right">остаток, м</th>
                </tr>
                <?php foreach($rowsCarbon as $i=>$r): ?>
                    <?php $tm=(float)$r['total_length_m']; ?>
                    <tr data-i="c<?=$i?>"
                        data-mat="Carbon"
                        data-filter="<?=htmlspecialchars($r['filter'])?>"
                        data-w="<?=$r['strip_width_mm']?>"
                        data-h="<?=$r['pleat_height_mm']?>"
                        data-tm="<?=$tm?>"
                        data-cutm="0">
                        <td class="markCell"><span class="dot" aria-hidden="true"></span></td>
                        <td><?=htmlspecialchars($r['filter'])?></td>
                        <td><?=$r['strip_width_mm']?></td>
                        <td><?=$r['pleat_height_mm']?></td>
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
    <div class="mid" style="display:flex;flex-direction:column;gap:12px;overflow:auto">
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
                <button class="btn" id="btnSave" disabled>Сохранить бухту</button>
                <button class="btn" id="btnClear" disabled>Очистить</button>
                <span class="factControl">Длина реза (факт):
                    <input id="factLenInput" type="number" min="0" step="10" value="0"> м
                </span>
            </div>
            <div id="baleList" class="quiet">Пусто</div>
        </div>

        <div class="panel" id="assortPanel">
            <h3>Ассортимент (добавить не из заявки)</h3>
            <table id="assortTable">
                <colgroup>
                    <col class="acol-mat"><col class="acol-filter"><col class="acol-w"><col class="acol-h"><col class="acol-act">
                </colgroup>
                <tr><th>Материал</th><th>Фильтр</th><th>Ширина</th><th>H</th><th>Действия</th></tr>
                <?php foreach($assort as $i=>$a): ?>
                    <tr data-mat="<?=htmlspecialchars($a['material'])?>"
                        data-filter="<?=htmlspecialchars($a['filter'])?>"
                        data-w="<?=$a['strip_width_mm']?>"
                        data-h="<?=$a['pleat_height_mm']?>">
                        <td><?=htmlspecialchars($a['material'])?></td>
                        <td><?=htmlspecialchars($a['filter'])?></td>
                        <td><?=$a['strip_width_mm']?> мм</td>
                        <td><?=$a['pleat_height_mm']?> мм</td>
                        <td>
                            <button class="assortBtn btnAdd1">+1</button>
                            <button class="assortBtn btnAuto">Авто</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <!-- ПРАВО -->
    <div class="panel" id="balesPanel">
        <div class="panelHead">
            <h3 style="margin:0">Собранные бухты</h3>
            <div style="display:flex;gap:6px;align-items:center">
                <button class="btn" id="btnLoadPlan">Загрузить</button>
                <button class="btn" id="btnSavePlan">Сохранить</button>
                <button class="btn" id="btnPrint">Распечатать</button>
            </div>
        </div>
        <div id="bales" class="balesList quiet">Пока нет</div>
    </div>
</div>

<!-- ВСПЛЫВАЮЩЕЕ МЕНЮ -->
<div class="menu" id="ctxMenu">
    <div class="row"><button class="mi" id="mi_auto">Добавить авто</button></div>
    <div class="row">
        <button class="mi" id="mi_addN">Добавить N</button>
        <input id="mi_n" type="number" min="1" step="1" value="1" placeholder="N">
    </div>
</div>

<script>
    const BALE_WIDTH=1000.0, eps=1e-9;
    const ORDER_NUMBER = "<?=htmlspecialchars($orderNumber, ENT_QUOTES)?>";

    let curRow=null, baleStrips=[], baleWidth=0.0, baleMaterial=null, bales=[];
    // фактическая длина текущей бухты
    let baleFactLen = 0;     // м
    let baleFactManual = false; // true, если пользователь менял поле вручную

    let lastN = 1;
    const el=(id)=>document.getElementById(id);
    const fmt1=(x)=>(Math.round(x*10)/10).toFixed(1);
    const fmt3=(x)=>(Math.round(x*1000)/1000).toFixed(3);
    const fmt0=(x)=>String(Math.round(x));
    const round3=(x)=>Math.round(x*1000)/1000;

    /* ====== МЕТКИ (новое) ====== */
    const MARKS_KEY = 'cut_marks:'+ORDER_NUMBER;
    let MARKS = {};
    const rowKey = (tr)=>[(tr.dataset.mat||''),(tr.dataset.filter||''),tr.dataset.w,tr.dataset.h].join('|');
    function loadMarks(){ try{ MARKS = JSON.parse(localStorage.getItem(MARKS_KEY)||'{}'); }catch{ MARKS={}; } }
    function saveMarks(){ localStorage.setItem(MARKS_KEY, JSON.stringify(MARKS)); }
    function applyMarkToRow(tr){
        const key=rowKey(tr); const on=!!MARKS[key];
        tr.classList.toggle('marked', on);
        const mc = tr.querySelector('.markCell'); if(mc) mc.classList.toggle('on', on);
    }
    function applyAllMarks(){
        document.querySelectorAll('.posTable tr[data-i]').forEach(applyMarkToRow);
    }

    function calcBaleMaxLen(){
        let m = 0;
        for(const s of baleStrips) if (s.len > m) m = Math.round(s.len);
        return m;
    }

    /* === утилиты левой таблицы === */
    function restMetersOf(tr){
        const tm = parseFloat(tr.dataset.tm || '0');
        const cut= parseFloat(tr.dataset.cutm || '0');
        return round3(tm - cut);
    }

    function applyRestColor(tr){
        const RANGE = 30;
        const rest = restMetersOf(tr);
        const absr = Math.abs(rest);

        if (absr <= eps) {
            tr.classList.add('rest-colored');
            tr.style.setProperty('--rest-bg','rgb(200,247,197)');
        } else if (absr <= RANGE + eps) {
            const g=[200,247,197], y=[255,243,176];
            const t = Math.min(1, absr / RANGE);
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

    /* подсветка подходящих по ширине (+ остаток ≥30 м) */
    function highlightWidthMatches(){
        const RANGE = 30;
        const free  = Math.max(0, BALE_WIDTH - baleWidth);
        const mat   = baleMaterial;

        document.querySelectorAll('.posTable tr[data-i]').forEach(tr=>{
            tr.classList.remove('width-cand');
            tr.style.removeProperty('--wbg');

            if(!baleStrips.length || !mat) return;
            if(tr.dataset.mat !== mat) return;

            const restM = restMetersOf(tr);
            if (restM < 30 - eps) return;

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

    /* выбор в левой таблице + обработка клика по метке */
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
        // общий клик
        tbl.addEventListener('click',e=>{
            // 1) клик по метке — просто переключаем, не открываем меню
            const markCell = e.target.closest('.markCell');
            if (markCell){
                const tr = markCell.closest('tr[data-i]'); if(!tr) return;
                const key = rowKey(tr);
                MARKS[key] = !MARKS[key];
                saveMarks();
                applyMarkToRow(tr);
                e.stopPropagation();
                return;
            }
            // 2) обычный клик по строке — выбор и меню
            const tr=e.target.closest('tr[data-i]'); if(!tr) return;
            setSelection(tr); openMenu(e.clientX, e.clientY);
        });
        // ПКМ
        tbl.addEventListener('contextmenu',e=>{
            // ПКМ по метке — не открываем меню
            if (e.target.closest('.markCell')) { e.preventDefault(); return; }
            const tr=e.target.closest('tr[data-i]'); if(!tr) return;
            e.preventDefault(); setSelection(tr); openMenu(e.clientX, e.clientY);
        });
        // первичная раскраска остатков
        tbl.querySelectorAll('tr[data-i]').forEach(applyRestColor);
    });

    function toggleCtrls(){
        const hasBale=baleStrips.length>0;
        if(el('btnSave'))  el('btnSave').disabled = !hasBale;
        if(el('btnClear')) el('btnClear').disabled= !hasBale;
    }
    if(el('btnSave'))  el('btnSave').addEventListener('click', saveBale);
    if(el('btnClear')) el('btnClear').addEventListener('click', clearBale);

    /* обновление метрик слева */
    function updateRowMeters(tr, deltaMeters){
        const total   = parseFloat(tr.dataset.tm);
        const cutPrev = parseFloat(tr.dataset.cutm||'0');
        let cutNow    = round3(cutPrev + deltaMeters);
        if (cutNow < 0) cutNow = 0;

        const effDelta = round3(cutNow - cutPrev);
        tr.dataset.cutm = cutNow;

        const rest  = round3(total - cutNow);
        tr.querySelector('.cutm').textContent = fmt3(cutNow);
        tr.querySelector('.restm').textContent = fmt3(rest);

        applyRestColor(tr);
        return effDelta;
    }

    /* интерфейс текущей бухты */
    function updBaleUI(){
        el('bw').textContent=fmt1(baleWidth);
        el('rest').textContent=fmt1(Math.max(0,BALE_WIDTH-baleWidth));
        el('baleMat').textContent=baleMaterial?baleMaterial:'—';
        if(!baleMaterial) el('baleMat').classList.add('quiet'); else el('baleMat').classList.remove('quiet');

        // если факт не трогали вручную — подстраиваем его к максимуму длин
        if(!baleFactManual) baleFactLen = calcBaleMaxLen();
        const fi = el('factLenInput'); if (fi) fi.value = String(baleFactLen||0);

        const box=el('baleList');
        if(!baleStrips.length){
            box.textContent='Пусто'; box.classList.add('quiet');
            toggleCtrls(); highlightWidthMatches(); return;
        }
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
          <div class="lenWrap">
            <input type="number" class="lenInput" data-idx="${idx}" min="0" step="10" value="${fmt0(s.len)}">
            <button class="eqBtn" data-idx="${idx}" title="Сделать длину всех равной этой">=</button>
          </div>
        </td>
      </tr>`).join('');
        html+='</table>';
        box.innerHTML=html;

        // ручное редактирование длины конкретной строки → унифицируем по введённому значению
        box.querySelectorAll('.lenInput').forEach(inp=>{
            inp.addEventListener('change', e=>{
                let L = Math.round(parseFloat(e.target.value||'0')); if(isNaN(L)||L<0) L=0;
                unifyBaleLength(L);
                if(!baleFactManual){ baleFactLen = calcBaleMaxLen(); el('factLenInput').value = String(baleFactLen); }
            });
        });
        // "=" — сделать длину всех равной выбранной
        box.querySelectorAll('.eqBtn').forEach(btn=>{
            btn.addEventListener('click', e=>{
                const i = parseInt(e.currentTarget.dataset.idx,10);
                let L = Math.round(baleStrips[i]?.len || 0);
                const wrap = e.currentTarget.closest('.lenWrap');
                const inp = wrap?.querySelector('.lenInput');
                if (inp) {
                    const v = Math.round(parseFloat(inp.value||'0'));
                    if(!isNaN(v) && v>=0) L = v;
                }
                unifyBaleLength(L);
                if(!baleFactManual){ baleFactLen = calcBaleMaxLen(); el('factLenInput').value = String(baleFactLen); }
            });
        });
        // удаление полос
        box.querySelectorAll('.delBtn').forEach(btn=>{
            btn.addEventListener('click', e=>{
                const i = parseInt(e.currentTarget.dataset.idx,10);
                removeStrip(i);
            });
        });

        toggleCtrls();
        highlightWidthMatches();
    }

    // унификация длин до L с учётом остатков
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
            Lnew = Math.round(Lmax);
            alert('Недостаточно остатка по одной из позиций. Длина бухты увеличена до максимально возможной.');
        }

        for(const g of groups){
            const desiredSum = Math.round(Lnew * g.count);
            const delta = round3(desiredSum - g.sum);
            if(g.rowEl) updateRowMeters(g.rowEl, delta);
        }
        baleStrips.forEach(s=>{ s.len=Lnew; });

        updBaleUI();
    }

    function ensureMaterial(matRaw){
        const cur = (baleMaterial ?? '').trim();          // текущий материал бухты (как текст)
        const m   = (matRaw ?? '').trim();                // материал добавляемой позиции

        // если в бухте ещё нет материала — устанавливаем (или дефолтимся к Simple)
        if (!cur) { baleMaterial = m || 'Simple'; return true; }

        // если у позиции материал пустой — считаем, что он такой же, как в бухте
        if (!m) return true;

        // сравнение без учёта регистра и пробелов
        if (cur.toLowerCase() === m.toLowerCase()) return true;

        alert('Материал текущей бухты: ' + cur + '. Нельзя смешивать с: ' + (m || '—') + '. Очистите или сохраните бухту.');
        return false;
    }


    function removeStrip(idx){
        const s = baleStrips[idx];
        if(!s) return;
        if(s.rowEl) updateRowMeters(s.rowEl, -round3(s.len));
        baleWidth = Math.max(0, round3(baleWidth - s.w));
        baleStrips.splice(idx,1);
        if(!baleStrips.length){ baleMaterial = null; baleFactLen = 0; baleFactManual=false; }
        updBaleUI();
    }

    /* добавление из левой таблицы */
    function addStrips(take){
        if(!curRow) return;
        let w   = parseFloat(curRow.dataset.w),
            h   = parseFloat(curRow.dataset.h),
            mat = curRow.dataset.mat || 'Simple';

        if(take<=0) return;
        if(!ensureMaterial(mat)) return;

        const restMeters = restMetersOf(curRow);
        if(restMeters<=0){ alert('По этой позиции метров не осталось.'); return; }

        const perLen = Math.round(restMeters / take);

        const free=Math.max(0, BALE_WIDTH-baleWidth);
        const needW=w*take;
        if(needW > free+eps){ alert('Не помещается по ширине.'); return; }

        for(let i=0;i<take;i++)
            baleStrips.push({filter:curRow.dataset.filter,w:w,h:h,len:perLen,mat:mat,rowEl:curRow});

        baleWidth += needW;

        updateRowMeters(curRow, perLen * take);
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

    // добавление из ассортимента (len=0)
    function addAssort(filter, w, h, matRaw, mode){
        const mClean = (matRaw ?? '').trim();
        const effMat = baleMaterial || mClean || 'Simple';   // что реально используем

        if(!ensureMaterial(effMat)) return;

        const free = Math.max(0, BALE_WIDTH - baleWidth);
        let take = 0;
        if(mode==='auto'){
            take = Math.floor((free + eps)/w);
            if(take<=0){ alert('Не помещается по ширине.'); return; }
        } else {
            take = 1;
            if(w > free+eps){ alert('Не помещается по ширине.'); return; }
        }

        for(let i=0;i<take;i++){
            baleStrips.push({
                filter: filter,
                w: parseFloat(w),
                h: parseFloat(h),
                len: 0,
                mat: effMat,         // <-- фикс: сохраняем нормализованный материал
                rowEl: null
            });
        }
        baleWidth = round3(baleWidth + w*take);
        updBaleUI();
    }


    // очистка
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
        baleStrips=[]; baleWidth=0; baleMaterial=null; baleFactLen=0; baleFactManual=false;
        updBaleUI();
    }

    function saveBale(){
        if(!baleStrips.length) return;
        let totalLen=baleStrips.reduce((s,x)=>s+x.len,0);
        bales.push({w:baleWidth, len:round3(totalLen), mat:baleMaterial, fact:baleFactLen||null, strips:[...baleStrips]});
        renderBales(); baleStrips=[]; baleWidth=0; baleMaterial=null; baleFactLen=0; baleFactManual=false; updBaleUI();
    }

    // удаление сохранённой бухты (возврат полос влево)
    function deleteBale(idx){
        const b = bales[idx]; if(!b) return;

        const sumsByRowEl = new Map();
        const sumsByKey   = new Map();

        for (const s of b.strips) {
            if (s.rowEl) {
                sumsByRowEl.set(s.rowEl, round3((sumsByRowEl.get(s.rowEl)||0) + s.len));
            } else {
                const key = (b.mat || s.mat || '') + '|' + (s.filter || '');
                sumsByKey.set(key, round3((sumsByKey.get(key)||0) + s.len));
            }
        }

        for (const [tr, sum] of sumsByRowEl.entries()) updateRowMeters(tr, -sum);

        if (sumsByKey.size) {
            const idxMap = {};
            document.querySelectorAll('.posTable tr[data-i]').forEach(tr=>{
                const key = (tr.dataset.mat||'') + '|' + (tr.dataset.filter||'');
                idxMap[key] = tr;
            });
            for (const [key, sum] of sumsByKey.entries()) {
                const tr = idxMap[key];
                if (tr) updateRowMeters(tr, -sum);
            }
        }

        bales.splice(idx,1);
        renderBales();
        highlightWidthMatches();
    }

    function renderBales(){
        const box=el('bales');
        if(!bales.length){box.textContent='Пока нет'; box.classList.add('quiet'); return;}
        box.classList.remove('quiet');

        const byMatIdx={};
        bales.forEach((b,idx)=>{ (byMatIdx[b.mat]??=[]).push(idx); });

        let html='';
        for(const mat of Object.keys(byMatIdx)){
            html+=`<div style="margin:4px 0 6px"><b>${mat}</b></div>`;
            html+=byMatIdx[mat].map((idx)=>{
                const b = bales[idx];
                const leftover = Math.max(0, Math.round(BALE_WIDTH - b.w));
                const rows=b.strips.map(s=>`<tr><td>${s.filter}</td><td>${fmt1(s.w)} мм</td><td>${s.h} мм</td><td>${fmt0(s.len)} м</td></tr>`).join('');
                return `<div class="card">
          <div class="cardHead">
            <div><b>Бухта #${idx+1}</b> · Материал: <b>${b.mat}</b> · Остаток: <b>${leftover} мм</b> · Формат: <b>1000 мм</b>${b.fact?` · Факт: <b>${fmt0(b.fact)} м</b>`:''}</div>
            <button class="delBaleBtn" data-idx="${idx}" title="Удалить бухту">×</button>
          </div>
          <table class="baleTbl"><colgroup><col class="bcol-pos"><col class="bcol-w"><col class="bcol-h"><col class="bcol-l"></colgroup>
            <tr><th>Позиция</th><th>Ширина</th><th>H</th><th>Длина</th></tr>${rows}
          </table>
        </div>`;
            }).join('');
        }
        box.innerHTML=html;

        box.querySelectorAll('.delBaleBtn').forEach(btn=>{
            btn.addEventListener('click', e=>{
                const idx = parseInt(e.currentTarget.dataset.idx,10);
                deleteBale(idx);
            });
        });
    }

    function printBales(){
        if(!bales.length){ alert('Нет сохранённых бухт для печати.'); return; }
        window.print();
    }
    if(el('btnPrint')) el('btnPrint').addEventListener('click', printBales);

    /* === Сохранение/Загрузка плана === */
    function buildPlanPayload(){
        return {
            order_number: ORDER_NUMBER,
            format_mm: 1000,
            bales: bales.map(b => ({
                w: b.w, len: b.len, mat: b.mat, fact: (b.fact ?? null),
                strips: b.strips.map(s => ({
                    filter: s.filter, w: s.w, h: s.h, len: s.len,
                    rowEl: s.rowEl ? true : null
                }))
            }))
        };
    }

    async function savePlanToDB(){
        if(!bales.length){ alert('Нет собранных бухт для сохранения.'); return; }
        try{
            const res = await fetch(location.pathname+'?action=save_plan', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify(buildPlanPayload())
            });
            const data = await res.json();
            if(!data.ok) throw new Error(data.error||'Ошибка сохранения');
            alert('Сохранено.');
        }catch(e){ alert('Не удалось сохранить: '+e.message); }
    }

    async function loadPlanFromDB(){
        try{
            const url = 'NP_cut_plan.php?action=load_plan&order_number='+encodeURIComponent(ORDER_NUMBER);
            const res = await fetch(url, { headers:{'Accept':'application/json'} });

            const txt = await res.text();                     // читаем ОДИН раз
            if (!res.ok) throw new Error(`HTTP ${res.status}: ${txt.slice(0,500)}`);

            let data;
            try {
                data = JSON.parse(txt);                         // пытаемся распарсить
            } catch {
                throw new Error('Backend вернул не JSON:\n'+txt.slice(0,500));
            }

            if(!data.ok) throw new Error(data.error||'Ошибка загрузки');
            if(!data.exists){ alert('Сохранённый раскрой не найден.'); return; }

            clearBale(); bales=[];
            for(const b of data.bales){
                bales.push({ w:b.w, len:b.len, mat:b.mat, fact:(b.fact ?? null),
                    strips: b.strips.map(s=>({...s, rowEl:null})) });
            }

            const sums=new Map();
            for(const b of bales){ for(const s of b.strips){
                const key=b.mat+'|'+s.filter; sums.set(key,(sums.get(key)||0)+s.len);
            }}
            const idx={};
            document.querySelectorAll('.posTable tr[data-i]').forEach(tr=>{
                const key=(tr.dataset.mat||'')+'|'+(tr.dataset.filter||'');
                idx[key]=tr; tr.dataset.cutm='0';
                tr.querySelector('.cutm').textContent='0.000';
                tr.querySelector('.restm').textContent=fmt3(parseFloat(tr.dataset.tm)||0);
                applyRestColor(tr);
            });
            for(const [key,sum] of sums.entries()){ if(idx[key]) updateRowMeters(idx[key], sum); }

            renderBales(); highlightWidthMatches(); alert('Загружено.');
        }catch(e){
            alert('Не удалось загрузить: '+e.message);
        }
    }
    if (el('btnSavePlan')) el('btnSavePlan').addEventListener('click', savePlanToDB);
    if (el('btnLoadPlan')) el('btnLoadPlan').addEventListener('click', loadPlanFromDB);
    if (el('btnSign')) el('btnSign').addEventListener('click',signPlan );

    /* контекстное меню (левая таблица) */
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

    /* события ассортимента */
    document.getElementById('assortTable').addEventListener('click', (e)=>{
        const tr = e.target.closest('tr[data-filter]'); if(!tr) return;
        const filter = tr.dataset.filter;
        const mat    = (tr.dataset.mat || '').trim();   // <-- trim
        const w      = parseFloat(tr.dataset.w);
        const h      = parseFloat(tr.dataset.h);

        if(e.target.classList.contains('btnAdd1')){
            addAssort(filter, w, h, mat, 'one');
        } else if(e.target.classList.contains('btnAuto')){
            addAssort(filter, w, h, mat, 'auto');
        }
    });


    // изменение фактической длины пользователем — только запоминаем (не унифицируем автоматически)
    const factInp = el('factLenInput');
    if (factInp){
        factInp.addEventListener('change', ()=>{
            let L = Math.round(parseFloat(factInp.value||'0')); if(isNaN(L)||L<0) L=0;
            baleFactLen = L; baleFactManual = true;
            factInp.value = String(baleFactLen);
        });
    }

    /* init */
    function toggleCtrls(){ const hasBale=baleStrips.length>0; if(el('btnSave'))  el('btnSave').disabled=!hasBale; if(el('btnClear')) el('btnClear').disabled=!hasBale; }
    loadMarks();
    updBaleUI(); setSelection(null); highlightWidthMatches();
    // применяем метки к строкам после первичной разметки
    applyAllMarks();
</script>
