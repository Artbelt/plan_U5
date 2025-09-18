<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "");
$date = $_GET['date'] ?? date('Y-m-d');

// грузим сырые строки
$stmt = $pdo->prepare("
    SELECT id, order_number, plan_date, filter_label, `count`, fact_count
    FROM corrugation_plan
    WHERE plan_date = ?
    ORDER BY order_number, id
");
$stmt->execute([$date]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// группируем по (order_number, filter_label)
$groups = [];
foreach ($rows as $r) {
    $key = $r['order_number'].'|'.$r['filter_label'];
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'order_number' => $r['order_number'],
            'filter_label' => $r['filter_label'],
            'ids'          => [],
            'items'        => [],
            'plan_sum'     => 0,
            'fact_sum'     => 0,
        ];
    }
    $groups[$key]['ids'][] = (int)$r['id'];
    $groups[$key]['items'][] = [
        'id'         => (int)$r['id'],
        'count'      => (int)$r['count'],
        'fact_count' => (int)$r['fact_count'],
    ];
    $groups[$key]['plan_sum'] += (int)$r['count'];
    $groups[$key]['fact_sum'] += (int)$r['fact_count'];
}
$group_list = array_values($groups);

// даты для стрелок
$dt       = new DateTime($date);
$prevDate = $dt->modify('-1 day')->format('Y-m-d');
$nextDate = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
$today    = date('Y-m-d');

// Начальная заливка по плану/факту (зелёная шкала 80–100%+)
function greenShadeStyle(int $plan, int $fact): string {
    if ($plan <= 0) return '';
    $ratio = $fact / $plan;

    $h = 120;     // оттенок зелёного
    $s = 60;      // насыщенность
    $L_dark  = 35; // тёмный (при >=100%)
    $L_light = 85; // светлый (при 80%)

    if ($ratio >= 1) {
        $L = $L_dark;
    } elseif ($ratio >= 0.8) {
        $def = 1 - $ratio;         // 0..0.2
        $t   = $def / 0.2;         // 0..1
        $L   = $L_dark + ($L_light - $L_dark) * $t;
    } else {
        return '';
    }
    $L = max(0, min(100, $L));
    return "style=\"background-color: hsl($h, {$s}%, {$L}%);\"";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Задания гофромашины</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <style>
        body{font-family:sans-serif;background:#f0f0f0;padding:10px}
        h2{text-align:center;margin:6px 0 12px}

        .section{max-width:900px;margin:0 auto;background:#fff;padding:10px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08)}

        /* NAV */
        .nav{max-width:900px;margin:0 auto 10px;display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap}
        .nav a, .nav button{
            border:1px solid #d1d5db;background:#fff;padding:6px 10px;border-radius:8px;cursor:pointer;
            box-shadow:0 1px 2px rgba(0,0,0,.05)
        }
        .nav a:hover, .nav button:hover{background:#f9fafb}
        .nav input[type="date"]{padding:6px 10px;border:1px solid #d1d5db;border-radius:8px}

        table{border-collapse:collapse;width:100%;font-size:14px}
        th,td{border:1px solid #ddd;padding:6px 8px;text-align:center}
        thead th{background:#f5f5f5}
        tbody tr:nth-child(even){background:#fafafa}

        /* выполнено — только оформление текста; фон задаём инлайном */
        .is-done td{ text-decoration: line-through; color:#0f3d0f }

        /* >>> tiny save & qty */
        /* узкое поле количества */
        input[type="number"].qty{
            width:65px;              /* ещё уже */
            padding:2px 3px;
            text-align:center;
            font-variant-numeric: tabular-nums;
        }
        /* убираем стрелки у number */
        input[type="number"].qty::-webkit-outer-spin-button,
        input[type="number"].qty::-webkit-inner-spin-button{ -webkit-appearance:none; margin:0; }
        input[type="number"].qty{ -moz-appearance: textfield; }

        /* крошечная кнопка ✓ */
        button.save{
            padding:4px 6px;
            font-size:13px;
            line-height:1;
            cursor:pointer;
            border:1px solid #d1d5db;
            background:#fff;
            border-radius:8px;
            min-width:30px;          /* чтобы удобно кликалось */
        }
        button.save:hover{background:#f0f2f5}
        /* <<< tiny save & qty */

        @media (max-width:600px){
            .nav{gap:6px}
            table{font-size:13px}
            th,td{padding:4px}
            input[type="number"].qty{ width:44px; padding:2px 2px; } /* еще компактнее на мобиле */
            button.save{ padding:4px 6px; font-size:12px; min-width:26px; }
        }
        @media (max-width:600px){
            /* таблица держит заданные ширины колонок */
            table{ table-layout: fixed; }

            /* фиксируем ширину колонки "Факт" */
            thead th:nth-child(4),
            tbody td:nth-child(4){ width: 112px; } /* подправь число под себя */

            /* внутри "Факт": одна строка, без переносов */
            tbody td:nth-child(4){
                display: flex;                 /* ряд */
                align-items: center;
                justify-content: center;
                gap: 6px;                      /* расстояние между полем и кнопкой */
                white-space: nowrap;           /* запрет переносов */
            }

            /* чтобы длинный Фильтр не толкал верстку — режем с троеточием */
            tbody td:nth-child(2){
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            /* поле ещё уже (если нужно) */
            input[type="number"].qty{ width: 44px; }  /* можно 40–42px, если хватает */
        }

    </style>
    <script>
        // навигация по датам
        function setDateAndReload(dStr){
            if(!dStr) return;
            const url = new URL(window.location.href);
            url.searchParams.set('date', dStr);
            window.location.href = url.toString();
        }
        function shiftDate(delta){
            const inp = document.getElementById('date-input');
            if(!inp.value) return;
            const d = new Date(inp.value + 'T00:00:00');
            d.setDate(d.getDate() + delta);
            const y = d.getFullYear();
            const m = String(d.getMonth()+1).padStart(2,'0');
            const day = String(d.getDate()).padStart(2,'0');
            setDateAndReload(`${y}-${m}-${day}`);
        }
        // автоперезагрузка при смене даты
        function onDateChange(e){ setDateAndReload(e.target.value); }
        // стрелки с клавиатуры ← →
        document.addEventListener('keydown', (e)=>{
            const tag = (e.target && e.target.tagName || '').toLowerCase();
            if(tag === 'input' || tag === 'textarea') return;
            if(e.key === 'ArrowLeft'){ shiftDate(-1); }
            if(e.key === 'ArrowRight'){ shiftDate(1); }
        });

        // заливка после сохранения
        function applyShade(rowEl, plan, fact){
            if (!rowEl || plan <= 0) return;
            const ratio = fact / plan;

            const H = 120, S = 60;
            const L_dark = 35, L_light = 85;

            rowEl.style.backgroundColor = '';
            if (ratio >= 1){
                rowEl.classList.add('is-done');
                rowEl.style.backgroundColor = `hsl(${H}, ${S}%, ${L_dark}%)`;
            } else if (ratio >= 0.8){
                rowEl.classList.remove('is-done');
                const def = 1 - ratio; // 0..0.2
                const t   = def / 0.2; // 0..1
                const L   = L_dark + (L_light - L_dark) * t;
                rowEl.style.backgroundColor = `hsl(${H}, ${S}%, ${L}%)`;
            } else {
                rowEl.classList.remove('is-done');
            }
        }

        async function saveGroup(idsCsv, itemsJson, inputId, plan){
            const inp = document.getElementById(inputId);
            const val = Number((inp.value||'').trim());
            if(isNaN(val) || val < 0){ alert('Введите корректное число'); return; }

            const items = JSON.parse(itemsJson);

            // распределение общего факта по строкам (не превышая их план)
            let rest = val, dist = [];
            for (const it of items){
                if (rest <= 0){ dist.push({id:it.id,fact:0}); continue; }
                const take = Math.min(rest, Number(it.count));
                dist.push({id:it.id,fact:take}); rest -= take;
            }

            // сохраняем по каждой строке
            for (const d of dist){
                const resp = await fetch('save_corr_fact.php',{
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body:'id='+d.id+'&fact='+d.fact
                }).then(r=>r.json()).catch(()=>null);
                if (!resp || !resp.success){
                    alert('Ошибка сохранения факта по строкам группы.');
                    return;
                }
            }

            // применим подсветку
            const row = document.getElementById('grow-'+idsCsv.split(',').join('-'));
            applyShade(row, Number(plan), val);

            alert('Сохранено');
        }

        // Enter в поле «Факт» = сохранить
        function onQtyKey(e, idsCsv, itemsJson, inputId, plan){
            if (e.key === 'Enter') {
                e.preventDefault();
                saveGroup(idsCsv, itemsJson, inputId, plan);
            }
        }

        // навешиваем обработчик на date input после загрузки
        document.addEventListener('DOMContentLoaded', ()=>{
            const di = document.getElementById('date-input');
            if (di) di.addEventListener('change', onDateChange);
        });
    </script>
</head>
<body>

<h2>Задания гофромашины на <?= htmlspecialchars($date) ?></h2>

<div class="nav">
    <a href="?date=<?= htmlspecialchars($prevDate) ?>" title="День назад">⬅️</a>
    <input id="date-input" type="date" value="<?= htmlspecialchars($date) ?>" />
    <a href="?date=<?= htmlspecialchars($nextDate) ?>" title="День вперёд">➡️</a>
    <a href="?date=<?= htmlspecialchars($today) ?>" title="Сегодня">Сегодня</a>
</div>

<div class="section">
    <?php if ($group_list): ?>
        <table>
            <thead>
            <tr>
                <th>Заявка</th>
                <th>Фильтр</th>
                <th>План</th>
                <th>Факт</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($group_list as $g):
                $idsCsv   = implode(',', $g['ids']);
                $rowId    = 'grow-'.str_replace(',', '-', $idsCsv);
                $inputId  = 'gfact-'.str_replace(',', '-', $idsCsv);
                $itemsArr = array_map(fn($it)=>['id'=>$it['id'],'count'=>$it['count']], $g['items']);
                $itemsJson = htmlspecialchars(json_encode($itemsArr), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $ratio  = ($g['plan_sum']>0) ? $g['fact_sum']/$g['plan_sum'] : 0;
                $isDone = ($ratio >= 1);
                $style  = greenShadeStyle((int)$g['plan_sum'], (int)$g['fact_sum']); // начальный фон
                ?>
                <tr id="<?= $rowId ?>" class="<?= $isDone ? 'is-done' : '' ?>" <?= $style ?>>
                    <td><?= htmlspecialchars($g['order_number']) ?></td>
                    <td><?= htmlspecialchars($g['filter_label']) ?></td>
                    <td><?= (int)$g['plan_sum'] ?></td>
                    <td>
                        <input
                            type="number" class="qty" id="<?= $inputId ?>"
                            value="<?= (int)$g['fact_sum']  ?>" min="0" max="<?= (int)$g['plan_sum'] ?>"
                            onkeydown="onQtyKey(event,'<?= $idsCsv ?>','<?= $itemsJson ?>','<?= $inputId ?>',<?= (int)$g['plan_sum'] ?>)"
                        >
                        <button class="save" type="button" title="Сохранить"
                                onclick="saveGroup('<?= $idsCsv ?>','<?= $itemsJson ?>','<?= $inputId ?>',<?= (int)$g['plan_sum'] ?>)">
                            ✓
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align:center">Заданий нет</p>
    <?php endif; ?>
</div>

</body>
</html>
