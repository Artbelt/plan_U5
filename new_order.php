<?php
// new_order_v2.php — улучшенная версия:
// 1) По умолчанию в полях маркировки/упаковки стоит «стандарт»
// 2) Поле фильтра — searchable (datalist): вводим часть имени и список сужается
// 3) Колонки приведены к виду из примера (см. скрин):
//    Фильтр | Количество, шт | Маркировка | Упаковка инд. | Этикетка инд. | Упаковка групп. | Норма упаковки | Этикетка групп. | Примечание

$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Миграция orders
$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
  order_number tinytext DEFAULT NULL,
  workshop tinytext DEFAULT NULL,
  `filter` tinytext DEFAULT NULL,
  `count` int(5) DEFAULT NULL,
  marking text DEFAULT NULL,
  personal_packaging text DEFAULT NULL,
  personal_label text DEFAULT NULL,
  group_packaging text DEFAULT NULL,
  packaging_rate int(5) DEFAULT NULL,
  group_label text DEFAULT NULL,
  remark text DEFAULT NULL,
  hide int(11) DEFAULT 0,
  cut_ready tinyint(1) DEFAULT 0,
  cut_confirmed tinyint(1) DEFAULT 0,
  plan_ready tinyint(1) DEFAULT 0,
  corr_ready tinyint(1) DEFAULT 0,
  build_ready tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// API: создать заявку
if (($_GET['action'] ?? '') === 'create_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Пустое тело']); exit; }

    $order_number = trim((string)($payload['order_number'] ?? ''));
    $workshop     = trim((string)($payload['workshop'] ?? '')) ?: null;
    $items        = $payload['items'] ?? [];

    if ($order_number==='') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Укажите имя заявки']); exit; }
    if (!is_array($items) || !count($items)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Пустой список позиций']); exit; }

    $ins = $pdo->prepare("INSERT INTO orders (
        order_number, workshop, `filter`, `count`, marking,
        personal_packaging, personal_label, group_packaging, packaging_rate, group_label, remark,
        hide, cut_ready, cut_confirmed, plan_ready, corr_ready, build_ready
    ) VALUES (
        :order_number, :workshop, :filter, :count, :marking,
        :personal_packaging, :personal_label, :group_packaging, :packaging_rate, :group_label, :remark,
        0,0,0,0,0,0
    )");

    $pdo->beginTransaction();
    try {
        foreach ($items as $i => $it) {
            $filter = trim((string)($it['filter'] ?? ''));
            $count  = (int)($it['count'] ?? 0);
            if ($filter==='' || $count<=0) { throw new RuntimeException('Строка #'.($i+1).': укажите фильтр и количество>0'); }

            $marking            = trim((string)($it['marking'] ?? '')) ?: null;
            $personal_packaging = trim((string)($it['personal_packaging'] ?? '')) ?: null;
            $personal_label     = trim((string)($it['personal_label'] ?? '')) ?: null;
            $group_packaging    = trim((string)($it['group_packaging'] ?? '')) ?: null;
            $packaging_rate     = isset($it['packaging_rate']) && $it['packaging_rate']!=='' ? (int)$it['packaging_rate'] : null;
            $group_label        = trim((string)($it['group_label'] ?? '')) ?: null;
            $remark             = trim((string)($it['remark'] ?? '')) ?: null;

            $ins->execute([
                ':order_number'=>$order_number,
                ':workshop'=>$workshop,
                ':filter'=>$filter,
                ':count'=>$count,
                ':marking'=>$marking,
                ':personal_packaging'=>$personal_packaging,
                ':personal_label'=>$personal_label,
                ':group_packaging'=>$group_packaging,
                ':packaging_rate'=>$packaging_rate,
                ':group_label'=>$group_label,
                ':remark'=>$remark,
            ]);
        }
        $pdo->commit();
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// список фильтров (для datalist)
$filters = $pdo->query("SELECT DISTINCT TRIM(`filter`) AS f FROM salon_filter_structure WHERE TRIM(`filter`)<>'' ORDER BY f")->fetchAll();
$filtersList = array_map(fn($r)=>$r['f'],$filters);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Создание заявки (v2)</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:16px}
        .row{display:grid;grid-template-columns:260px 130px 240px 180px 170px 190px 140px 180px 240px 44px;gap:8px;align-items:center}
        .row.header{font-weight:600}
        .row+.row{margin-top:6px}
        input,select{width:100%;padding:6px 8px}
        .muted{color:#666;font-size:12px}
        .actions{margin-top:16px;display:flex;gap:8px}
        .wrap{max-width:1700px}
        .filter-cell{display:flex;align-items:center;gap:6px}
        .icon-btn{padding:4px 8px;border:1px solid #ccc;background:#eef;border-radius:6px;cursor:pointer}
    </style>
</head>
<body>
<div class="wrap">
    <h2>Новая заявка</h2>
    <div style="display:flex;gap:16px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
        <label>Имя заявки: <input id="order_number" type="text" placeholder="Напр.: Z-2025-001" style="width:260px"></label>
        <label>Цех (опц.): <input id="workshop" type="text" value="U5" style="width:200px"></label>
        <span class="muted">Строки сохраняются в таблицу <b>orders</b></span>
    </div>

    <div class="row header">
        <div>Фильтр</div>
        <div>Количество, шт</div>
        <div>Маркировка</div>
        <div>Упаковка инд.</div>
        <div>Этикетка инд.</div>
        <div>Упаковка групп.</div>
        <div>Норма упаковки</div>
        <div>Этикетка групп.</div>
        <div>Примечание</div>
        <div></div>
    </div>
    <div id="rows"></div>

    <div class="actions">
        <button onclick="addRow()">+ Добавить позицию</button>
        <button onclick="saveOrder()">💾 Сохранить заявку</button>
        <span id="status" class="muted"></span>
    </div>
</div>

<datalist id="filters_datalist">
    <?php foreach ($filtersList as $f): ?>
        <option value="<?= htmlspecialchars($f) ?>"></option>
    <?php endforeach; ?>
</datalist>

<script>
    // helper: поле фильтра с datalist (поиск по вводу)
    function makeFilterInput(value=''){
        const wrap=document.createElement('div');
        wrap.className='filter-cell';
        const inp=document.createElement('input');
        inp.type='text';
        inp.name='filter';
        inp.setAttribute('list','filters_datalist');
        inp.placeholder='начните вводить название…';
        inp.value=value||'';
        const info=document.createElement('button');
        info.type='button'; info.className='icon-btn'; info.textContent='i'; info.title='Инфо (можно привязать позже)';
        wrap.append(inp,info);
        return wrap;
    }

    function addRow(prefill={}){
        const r=document.createElement('div');
        r.className='row';

        const colFilter=document.createElement('div');
        colFilter.appendChild(makeFilterInput(prefill.filter||''));

        const colCount=document.createElement('div');
        const inCount=document.createElement('input'); inCount.type='number'; inCount.min='1'; inCount.placeholder='шт'; inCount.value=prefill.count||''; inCount.name='count'; colCount.appendChild(inCount);

        const colMark=document.createElement('div');
        const inMark=document.createElement('input'); inMark.type='text'; inMark.name='marking'; inMark.placeholder='маркировка'; inMark.value=(prefill.marking??'стандарт'); colMark.appendChild(inMark);

        const colPPack=document.createElement('div');
        const inPPack=document.createElement('input'); inPPack.type='text'; inPPack.name='personal_packaging'; inPPack.placeholder='инд. упаковка'; inPPack.value=(prefill.personal_packaging??'стандарт'); colPPack.appendChild(inPPack);

        const colPLabel=document.createElement('div');
        const inPLabel=document.createElement('input'); inPLabel.type='text'; inPLabel.name='personal_label'; inPLabel.placeholder='инд. этикетка'; inPLabel.value=(prefill.personal_label??'стандарт'); colPLabel.appendChild(inPLabel);

        const colGPack=document.createElement('div');
        const inGPack=document.createElement('input'); inGPack.type='text'; inGPack.name='group_packaging'; inGPack.placeholder='групп. упаковка'; inGPack.value=(prefill.group_packaging??'стандарт'); colGPack.appendChild(inGPack);

        const colRate=document.createElement('div');
        const inRate=document.createElement('input'); inRate.type='number'; inRate.name='packaging_rate'; inRate.placeholder='шт в коробке'; inRate.min='0'; inRate.step='1'; inRate.value=(prefill.packaging_rate??0); colRate.appendChild(inRate);

        const colGLabel=document.createElement('div');
        const inGLabel=document.createElement('input'); inGLabel.type='text'; inGLabel.name='group_label'; inGLabel.placeholder='этикетка групп.'; inGLabel.value=(prefill.group_label??'стандарт'); colGLabel.appendChild(inGLabel);

        const colRemark=document.createElement('div');
        const inRem=document.createElement('input'); inRem.type='text'; inRem.name='remark'; inRem.placeholder='примечание'; inRem.value=prefill.remark||''; colRemark.appendChild(inRem);

        const colDel=document.createElement('div');
        const btnX=document.createElement('button'); btnX.type='button'; btnX.textContent='✕'; btnX.title='Удалить'; btnX.onclick=()=>r.remove(); colDel.appendChild(btnX);

        r.append(colFilter,colCount,colMark,colPPack,colPLabel,colGPack,colRate,colGLabel,colRemark,colDel);
        document.getElementById('rows').appendChild(r);
    }

    function buildPayload(){
        const order_number=document.getElementById('order_number').value.trim();
        const workshop=document.getElementById('workshop').value.trim();
        const rows=[...document.querySelectorAll('#rows .row')];
        const items=rows.map(row=>{
            const q=(name)=> (row.querySelector(`[name="${name}"]`)?.value ?? '').trim();
            return {
                filter:q('filter'),
                count:Number(q('count')||0),
                marking:q('marking'),
                personal_packaging:q('personal_packaging'),
                personal_label:q('personal_label'),
                group_packaging:q('group_packaging'),
                packaging_rate:q('packaging_rate')===''? '': Number(q('packaging_rate')),
                group_label:q('group_label'),
                remark:q('remark')
            };
        });
        return {order_number,workshop,items};
    }

    async function saveOrder(){
        const status=document.getElementById('status');
        const payload=buildPayload();
        if(!payload.order_number){ alert('Укажите имя заявки'); return; }
        if(!payload.items.length){ alert('Добавьте хотя бы одну позицию'); return; }
        try{
            status.textContent='Сохраняем…';
            const res=await fetch(location.pathname+'?action=create_order',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
            const data=await res.json();
            if(!data.ok) throw new Error(data.error||'Ошибка сохранения');
            status.textContent='';
            alert('Заявка сохранена');
            document.getElementById('rows').innerHTML=''; addRow();
        }catch(e){ status.textContent=''; alert('Не удалось сохранить: '+e.message); }
    }

    // стартуем с одной строкой
    addRow();
</script>
</body>
</html>
