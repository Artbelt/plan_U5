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

/* Получаем информацию о выполненных операциях (fact_count > 0) */
$factData = [];
$stFact = $pdo->prepare("
    SELECT plan_date, filter_label, bale_id, strip_no, count, fact_count 
    FROM corrugation_plan 
    WHERE order_number = ? AND fact_count > 0
");
$stFact->execute([$order]);
while ($row = $stFact->fetch()) {
    $key = $row['bale_id'] . ':' . $row['strip_no'];
    $factData[$key] = [
        'plan_count' => (int)$row['count'],
        'fact_count' => (int)$row['fact_count'],
        'plan_date' => $row['plan_date']
    ];
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

    $key = $r['bale_id'].':'.$r['strip_no'];
    $pool[$d][] = [
        'key'      => $key,
        'bale_id'  => (int)$r['bale_id'],
        'strip_no' => (int)$r['strip_no'],
        'filter'   => (string)$r['filter'], // чистое имя (для БД)
        'label'    => $label_visible,
        'tip'      => $tooltip,
        'packs'    => $cnt,
        'fact_count' => isset($factData[$key]) ? $factData[$key]['fact_count'] : 0,
        'plan_count' => isset($factData[$key]) ? $factData[$key]['plan_count'] : 0,
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
    body{margin:0;background:var(--bg);font:11px system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111}
    h2{margin:8px 6px 4px;font-size:16px}
    .wrap{width:100vw;margin:0;padding:0 6px}
    .panel{background:var(--card);border:1px solid var(--line);border-radius:6px;padding:6px;margin:6px 0}
    .head{display:flex;align-items:center;justify-content:space-between;margin:1px 0 6px;gap:6px;flex-wrap:wrap}
    .btn{background:var(--accent);color:#fff;border:1px solid var(--accent);border-radius:6px;padding:4px 8px;cursor:pointer;font-size:10px}
    .btn:disabled{opacity:.5;cursor:not-allowed}
    .muted{color:var(--muted);font-size:10px}
    .sub{font-size:10px;color:var(--muted)}

    .gridTop{display:flex;gap:6px;overflow-x:auto;padding-bottom:6px}
    .gridBot{display:grid;gap:6px}
    .col{border-left:1px solid var(--line);padding-left:6px;min-height:120px;flex-shrink:0}
    .gridTop .col{width:180px}
    .col h4{margin:0 0 4px;font-weight:600;font-size:12px}

    .pill{display:flex;align-items:center;justify-content:space-between;gap:4px;border:1px solid #dbe3f0;background:#eef6ff;border-radius:6px;padding:3px 6px;margin:2px 0;cursor:pointer;font-size:10px;position:relative;flex-wrap:wrap}
    .pill-date{color:#666;font-size:9px;margin-left:auto}
    .day-separator{background:#e5e7eb;color:#6b7280;padding:2px 6px;margin:4px 0;border-radius:4px;font-size:9px;font-weight:600;text-align:center}
    .pill:hover{background:#e6f1ff}
    .pill-disabled{opacity:.45;filter:grayscale(.15);pointer-events:none}

    /* Выполненные полосы */
    .pill-done{background:#d1f4e0 !important;border-color:#10b981}
    .pill-done::after{content:"✓";position:absolute;right:4px;top:50%;transform:translateY(-50%);color:#10b981;font-weight:bold;font-size:12px}
    
    /* Частично выполненные полосы */
    .pill-partial{background:#fef3c7 !important;border-color:#f59e0b}
    .pill-partial::after{content:"◐";position:absolute;right:4px;top:50%;transform:translateY(-50%);color:#f59e0b;font-weight:bold;font-size:12px}

    .dropzone{min-height:28px;border:1px dashed var(--line);border-radius:4px;padding:4px}
    .rowItem{display:flex;align-items:center;justify-content:space-between;background:#dff7c7;border:1px solid #bddda2;border-radius:6px;padding:3px 6px;margin:2px 0;font-size:10px}
    .rowItem .rm{border:none;background:#fff;border:1px solid #ccc;border-radius:4px;padding:1px 6px;cursor:pointer;font-size:9px}
    .dayTotal{margin-top:4px;font-size:10px}
    .rowItem b.qty{margin-left:6px}
    /* керування всередині картки низу */
    .rowItem .controls{display:flex;align-items:center;gap:3px}
    .rowItem .mv{
        min-width: 18px;
        padding: 0 4px;
        font-size: 12px;
        line-height: 1;
        text-align: center;
    }

    .rowItem .mv:hover{background:#f1f5f9}
    .rowItem .mv:disabled{opacity:.4;cursor:not-allowed}

    .tools{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
    .tools label{font-size:10px;color:#333}
    .tools input[type=date], .tools input[type=number]{padding:2px 6px;border:1px solid #dcdfe5;border-radius:6px;font-size:10px}
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
    .modal{background:#fff;border-radius:8px;border:1px solid var(--line);min-width:280px;max-width:400px;max-height:60vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 8px 20px rgba(0,0,0,.2)}
    .modalHeader{display:flex;align-items:center;justify-content:space-between;padding:6px 8px;border-bottom:1px solid var(--line)}
    .modalTitle{font-weight:600;font-size:12px}
    .modalClose{border:1px solid #ccc;background:#f8f8f8;border-radius:6px;padding:2px 6px;cursor:pointer;font-size:10px}
    .modalBody{padding:6px;overflow:auto}
    .daysGrid{display:grid;grid-template-columns:repeat(2,1fr);gap:6px}
    .dayBtn{display:flex;flex-direction:column;gap:2px;padding:6px;border:1px solid #d9e2f1;border-radius:6px;background:#f4f8ff;cursor:pointer;text-align:left;font-size:10px}
    .dayBtn:hover{background:#ecf4ff}
    .dayHead{font-weight:600;font-size:10px}
    .daySub{font-size:9px;color:#6b7280}
    .dayBtn:disabled{
        opacity:.5;
        cursor:not-allowed;
    }
    .topCol h4{display:flex;align-items:center;justify-content:space-between}


    @media (max-width:560px){ .daysGrid{grid-template-columns:1fr;} .modal{min-width:240px;max-width:90vw;} }
    
    /* Плашка активного дня */
    .active-day-info {
        position: fixed;
        top: 10px;
        right: 10px;
        background: #f0f9ff;
        border: 1px solid #0ea5e9;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 10px;
        color: #0369a1;
        text-align: center;
        min-width: 140px;
        z-index: 100;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .active-day-date {
        font-weight: bold;
        margin-bottom: 2px;
    }
    .active-day-count {
        color: #0c4a6e;
    }
</style>

<div class="wrap">
    <h2>Гофроплан — <?=htmlspecialchars($order)?></h2>
    
    <!-- Фиксированная плашка активного дня -->
    <div id="activeDayInfo" class="active-day-info">
        <div class="active-day-date">Активный день: <span id="activeDayDate">-</span></div>
        <div class="active-day-count">Гофропакетов: <span id="activeDayCount">0</span> шт</div>
    </div>

    <div class="panel" id="topPanel">
        <div class="head">
            <div><b>Полосы из раскроя</b> <span class="sub">клик → дата внизу (Shift+клик → последний день)</span></div>
            <div class="muted">
                <?php $cnt=0; foreach($pool as $list) $cnt+=count($list); echo $cnt; ?> полос
            </div>
        </div>
        <div class="gridTop" id="gridTop">
            <?php 
            // Группируем позиции по дням в столбцах (максимум 30 позиций на столбец)
            $maxItemsPerColumn = 30;
            $columns = [];
            $currentColumn = [];
            $currentColumnItems = 0;
            $currentDay = null;
            
            foreach($dates as $d): 
                if(empty($pool[$d])) continue;
                
                foreach($pool[$d] as $p): 
                    // Если текущий столбец заполнен, создаем новый
                    if($currentColumnItems >= $maxItemsPerColumn) {
                        $columns[] = $currentColumn;
                        $currentColumn = [];
                        $currentColumnItems = 0;
                        $currentDay = null;
                    }
                    
                    // Если день изменился, добавляем разделитель
                    if($currentDay !== $d) {
                        if($currentDay !== null && $currentColumnItems > 0) {
                            // Добавляем разделитель только если в столбце уже есть позиции
                            $currentColumn[] = [
                                'type' => 'separator',
                                'date' => $d
                            ];
                            $currentColumnItems++;
                        }
                        $currentDay = $d;
                    }
                    
                    // Добавляем позицию в текущий столбец
                    $currentColumn[] = [
                        'type' => 'pill',
                        'date' => $d,
                        'data' => $p
                    ];
                    $currentColumnItems++;
                endforeach;
            endforeach;
            
            // Добавляем последний столбец, если в нем есть данные
            if(!empty($currentColumn)) {
                $columns[] = $currentColumn;
            }
            
            // Если нет данных, создаем один пустой столбец
            if(empty($columns)) {
                $columns[] = [];
            }
            
            // Выводим столбцы
            foreach($columns as $columnIndex => $column): 
                // Находим первую дату в столбце
                $firstDate = null;
                if(!empty($column)) {
                    foreach($column as $item) {
                        if($item['type'] === 'pill' || $item['type'] === 'separator') {
                            $firstDate = $item['date'];
                            break;
                        }
                    }
                }
                ?>
                <div class="col topCol" data-column="<?=$columnIndex?>">
                    <h4>
                        <span><?= $firstDate ?: 'Пустой' ?></span>
                    </h4>

                    <?php if(empty($column)): ?>
                        <div class="muted">нет</div>
                    <?php else: 
                        $daysInColumn = [];
                        foreach($column as $item): 
                            if($item['type'] === 'separator'): 
                                if(!in_array($item['date'], $daysInColumn)) {
                                    $daysInColumn[] = $item['date'];
                                }
                                echo '<div class="day-separator">' . $item['date'] . '</div>';
                            else:
                                $d = $item['date'];
                                $p = $item['data'];
                                
                                // Собираем дни в столбце для заголовка
                                if(!in_array($d, $daysInColumn)) {
                                    $daysInColumn[] = $d;
                                }
                                
                                // Определяем статус выполнения
                                $factCount = $p['fact_count'] ?? 0;
                                $planCount = $p['plan_count'] ?? 0;
                                $pillClass = 'pill';
                                $tooltipExtra = '';
                                
                                if ($factCount > 0) {
                                    if ($factCount >= $planCount && $planCount > 0) {
                                        $pillClass .= ' pill-done';
                                        $tooltipExtra = ' · ✓ Выполнено: ' . $factCount . ' шт';
                                    } else {
                                        $pillClass .= ' pill-partial';
                                        $tooltipExtra = ' · ◐ Выполнено: ' . $factCount . ' из ' . $planCount . ' шт';
                                    }
                                }
                                
                                echo '<div class="' . $pillClass . '"';
                                echo ' title="' . htmlspecialchars($d . ' · Бухта #'.$p['bale_id'].' · Полоса №'.$p['strip_no'].' · '.($p['tip'] ?? '') . $tooltipExtra) . '"';
                                echo ' data-key="' . htmlspecialchars($p['key']) . '"';
                                echo ' data-cut-date="' . $d . '"';
                                echo ' data-bale-id="' . $p['bale_id'] . '"';
                                echo ' data-strip-no="' . $p['strip_no'] . '"';
                                echo ' data-filter-name="' . htmlspecialchars($p['filter']) . '"';
                                echo ' data-packs="' . (int)$p['packs'] . '">';
                                echo '<span>' . htmlspecialchars($p['label'] ?? '') . '</span>';
                                echo '</div>';
                            endif;
                        endforeach;
                    endif; ?>
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

        <div class="sub" style="margin-top:6px">
            Полоса добавляется один раз. Удалите внизу → вернется вверху.
        </div>
    </div>
</div>

<div class="modalWrap" id="datePicker">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dpTitle">
        <div class="modalHeader">
            <div class="modalTitle" id="dpTitle">Выберите дату</div>
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
    const previousISO = ds => { const d = parseISO(ds); d.setDate(d.getDate()-1); return iso(d); };

    function topEnsureDayCol(ds){
        // Ищем столбец, который содержит этот день
        let col = null;
        const allCols = topGrid.querySelectorAll('.topCol');
        
        for(let c of allCols) {
            const pills = c.querySelectorAll('.pill[data-cut-date="' + ds + '"]');
            if(pills.length > 0) {
                col = c;
                break;
            }
        }
        
        if (col) return col;

        // Если столбец не найден, создаем новый
        const colCount = topGrid.querySelectorAll('.topCol').length;
        col = document.createElement('div');
        col.className = 'col topCol';
        col.dataset.column = colCount;
        col.innerHTML = `
    <h4><span>Новый столбец</span></h4>
    <div class="muted">нет</div>
  `;
        topGrid.appendChild(col);
        return col;
    }

    function topSetEmptyState(col){
        const hasPill = !!col.querySelector('.pill');
        const ph = col.querySelector('.muted');
        if (!hasPill && !ph){
            const m = document.createElement('div'); m.className='muted'; m.textContent='нет'; col.appendChild(m);
        } else if (hasPill && ph){ ph.remove(); }
    }




    const cutDateByKey = new Map(); // key => 'YYYY-MM-DD'

    let lastPickedDay = null;

    const initialDays = <?= json_encode($dates, JSON_UNESCAPED_UNICODE) ?>;

    // Функция для обновления плашки активного дня
    function updateActiveDayInfo() {
        const activeDayDateEl = document.getElementById('activeDayDate');
        const activeDayCountEl = document.getElementById('activeDayCount');
        
        if (lastPickedDay) {
            activeDayDateEl.textContent = lastPickedDay;
            const totalPacks = dayPacks(lastPickedDay);
            activeDayCountEl.textContent = totalPacks;
        } else {
            activeDayDateEl.textContent = '-';
            activeDayCountEl.textContent = '0';
        }
    }

    function ensureDay(ds){ if(!plan.has(ds)) plan.set(ds, new Set()); }
    
    // Функция для добавления дня в визуальную таблицу плана
    function addDayToPlanGrid(dayStr) {
        // Проверяем, есть ли уже такой день
        if (planGrid.querySelector(`.col[data-day="${dayStr}"]`)) {
            return; // День уже существует
        }
        
        // Создаем новую колонку дня
        const col = document.createElement('div');
        col.className = 'col';
        col.dataset.day = dayStr;
        col.innerHTML = `
            <h4>${dayStr}</h4>
            <div class="dropzone"></div>
            <div class="dayTotal muted">Итого: <b class="n">0</b> шт</div>
        `;
        
        // Добавляем в конец таблицы
        planGrid.appendChild(col);
        
        // Обновляем ширину грида
        const totalCols = planGrid.querySelectorAll('.col').length;
        planGrid.style.gridTemplateColumns = `repeat(${Math.max(1, totalCols)}, minmax(220px, 1fr))`;
        
        // Убеждаемся, что день есть в плане данных
        ensureDay(dayStr);
        
        // Показываем уведомление
        showNotification(`День ${dayStr} добавлен в план`);
        console.log(`День ${dayStr} добавлен в план`);
    }
    // Функция для показа уведомления
    function showNotification(message) {
        // Создаем элемент уведомления
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;
        notification.textContent = message;
        
        // Добавляем в DOM
        document.body.appendChild(notification);
        
        // Анимация появления
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Автоматически убираем через 3 секунды
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

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

    // Функция для получения всех дней между первым и последним днем заявки
    function getAllDaysInRange(){
        if (initialDays.length === 0) return [];
        
        // Получаем все дни из заявки
        const firstDay = initialDays[0];
        const lastDay = initialDays[initialDays.length - 1];
        
        // Получаем все дни, которые уже добавлены в план
        const existingDays = getAllDays();
        
        // Создаем массив всех дней между первым и последним днем заявки
        const allDays = [];
        const startDate = parseISO(firstDay);
        const endDate = parseISO(lastDay);
        
        for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            allDays.push(iso(d));
        }
        
        // Добавляем все дни, которые были добавлены вручную и выходят за рамки заявки
        existingDays.forEach(day => {
            if (!allDays.includes(day)) {
                allDays.push(day);
            }
        });
        
        // Сортируем по дате
        allDays.sort();
        
        return allDays;
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
        updateActiveDayInfo();
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
        updateActiveDayInfo();

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
        
        // Обновляем плашку активного дня, если это текущий активный день
        if (ds === lastPickedDay) {
            updateActiveDayInfo();
        }
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

        let dz = planGrid.querySelector(`.col[data-day="${targetDay}"] .dropzone`);
        if(!dz){ 
            // Автоматически добавляем день в план
            addDayToPlanGrid(targetDay);
            dz = planGrid.querySelector(`.col[data-day="${targetDay}"] .dropzone`);
            if (!dz) return; // На всякий случай проверяем еще раз
        }

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
        updateActiveDayInfo();
    }

    // Модалка выбора даты
    const dpWrap = document.getElementById('datePicker');
    const dpDays = document.getElementById('dpDays');
    const dpClose= document.getElementById('dpClose');
    let pendingPill = null;

    function openDatePicker(pillEl){
        pendingPill = pillEl;
        dpDays.innerHTML = '';
        const days = getAllDaysInRange();
        if (!days.length){ alert('Нет дат для заявки.'); return; }

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
        updateActiveDayInfo();
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
