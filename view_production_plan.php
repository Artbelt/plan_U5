<?php
// view_production_plan.php — план vs факт + переносы по сменам для выбранной заявки

$dsn = 'mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

$order = $_GET['order'] ?? '';
if (!$order) die("Не указан номер заявки.");

// Получаем статус заявки
$stmt = $pdo->prepare("SELECT status FROM orders WHERE order_number = ?");
$stmt->execute([$order]);
$orderStatus = $stmt->fetch(PDO::FETCH_ASSOC);
$isReplanning = ($orderStatus['status'] ?? 'normal') === 'replanning';

/* ---------- ПЛАН (build_plan) ---------- */
$stmt = $pdo->prepare("
    SELECT plan_date, filter, `count`
    FROM build_plan
    WHERE order_number = ?
    ORDER BY plan_date, filter
");
$stmt->execute([$order]);
$planRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* нормализуем названия и группируем по дате и базе */
$planByDate = [];              // [$date][] = ['base'=>..., 'count'=>int]
$planMap    = [];              // [$base][$date] = int
$allDates   = [];

foreach ($planRows as $r) {
    $date  = $r['plan_date'];
    $label = preg_replace('/\[.*$/', '', $r['filter']);
    $label = preg_replace('/[●◩⏃]/u', '', $label);
    $base  = trim($label);
    $cnt   = (int)$r['count'];

    $planByDate[$date][] = ['base'=>$base, 'count'=>$cnt];
    if (!isset($planMap[$base])) $planMap[$base] = [];
    if (!isset($planMap[$base][$date])) $planMap[$base][$date] = 0;
    $planMap[$base][$date] += $cnt;

    $allDates[$date] = true;
}

/* ---------- ФАКТ (manufactured_production) ---------- */
$stmt = $pdo->prepare("
    SELECT date_of_production AS prod_date,
           TRIM(SUBSTRING_INDEX(name_of_filter,' [',1)) AS base_filter,
           SUM(count_of_filters) AS fact_count
    FROM manufactured_production
    WHERE name_of_order = ?
    GROUP BY prod_date, base_filter
    ORDER BY prod_date, base_filter
");
$stmt->execute([$order]);
$factRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$factByDate = [];              // [$date][$base] = int
$factMap    = [];              // [$base][$date] = int

foreach ($factRows as $r) {
    $date = $r['prod_date'];
    $base = $r['base_filter'];
    if ($base === null || $base === '') continue;
    $cnt  = (int)$r['fact_count'];

    if (!isset($factByDate[$date])) $factByDate[$date] = [];
    if (!isset($factByDate[$date][$base])) $factByDate[$date][$base] = 0;
    $factByDate[$date][$base] += $cnt;

    if (!isset($factMap[$base])) $factMap[$base] = [];
    if (!isset($factMap[$base][$date])) $factMap[$base][$date] = 0;
    $factMap[$base][$date] += $cnt;

    $allDates[$date] = true;
}

/* ---------- Диапазон дат ---------- */
if ($allDates) {
    $dates = array_keys($allDates);
    sort($dates);
    $start = new DateTime(reset($dates));
    $end   = new DateTime(end($dates)); $end->modify('+1 day');
} else {
    $dates = [];
    $start = new DateTime();
    $end   = new DateTime();
}
$period = new DatePeriod($start, new DateInterval('P1D'), $end);

/* ---------- Расчёт переносов по каждой базе ---------- */
/*
   Для каждой base идём по датам слева направо и держим "дефицит_до_сегодня":
   deficit_prev = max(0, deficit_prev + plan[d] - fact[d])
   Если fact[d] > plan[d] и deficit_prev_before > 0,
   перенос_на_сегодня = min(excess_today, deficit_prev_before)
*/
$carryInfo = []; // [$date][$base] = ['carry_in'=>k, 'miss_today'=>m]

foreach ($planMap + $factMap as $base => $_) {
    if (!isset($planMap[$base])) $planMap[$base] = [];
    if (!isset($factMap[$base])) $factMap[$base] = [];

    $deficitPrev = 0;
    foreach ($period as $dt) {
        $d = $dt->format('Y-m-d');
        $plan = $planMap[$base][$d] ?? 0;
        $fact = $factMap[$base][$d] ?? 0;

        $carryIn = 0;
        if ($fact > $plan && $deficitPrev > 0) {
            $carryIn = min($fact - $plan, $deficitPrev);
        }

        $missToday = max(0, $plan - $fact);
        $deficitPrev = max(0, $deficitPrev - $carryIn) + $missToday;

        if ($carryIn > 0 || $missToday > 0) {
            if (!isset($carryInfo[$d])) $carryInfo[$d] = [];
            $carryInfo[$d][$base] = ['carry_in'=>$carryIn, 'miss_today'=>$missToday];
        }
    }
}

/* утилиты */
function sumPlanForDay($items){ $s=0; foreach($items as $it) $s+=(int)$it['count']; return $s; }
function sumFactForDayMap($map){ $s=0; foreach($map as $v) $s+=(int)$v; return $s; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>План и факт сборки — переносы | Заявка № <?= htmlspecialchars($order) ?></title>
    <style>
        :root{
            --bg:#f6f7fb; --card:#fff; --text:#111827; --muted:#6b7280; --line:#e5e7eb;
            --ok:#16a34a; --warn:#d97706; --bad:#dc2626; --accent:#2563eb; --hl:#fef3c7; --hlborder:#facc15;
        }
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--text);margin:0;padding:16px;font-size:14px}
        h1{text-align:center;margin:6px 0 12px;font-weight:700}
        .toolbar{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;align-items:center;margin-bottom:12px}
        .toolbar input{padding:8px 10px;border:1px solid var(--line);border-radius:8px;width:280px}
        .btn{padding:8px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer}
        .btn-print{background:#eaf1ff;color:var(--accent);border-color:#cfe0ff;font-weight:600}

        .calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:10px}
        .day{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:10px;min-height:140px;display:flex;flex-direction:column;gap:6px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
        .date{font-weight:700;color:#16a34a;white-space:nowrap}
        .muted{color:var(--muted)}
        ul{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:4px}
        li{padding:4px 6px;border-radius:8px;background:#fafafa;border:1px solid var(--line); transition:background-color .15s, box-shadow .15s, border-color .15s}
        li .row{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
        li strong{cursor:pointer}
        .tag{font-size:12px;padding:1px 8px;border-radius:999px;border:1px solid var(--line);background:#fff}
        .ok{color:var(--ok);border-color:#c9f2d9;background:#f1f9f4}
        .warn{color:var(--warn);border-color:#fde7c3;background:#fff9ed}
        .bad{color:var(--bad);border-color:#ffc9c9;background:#fff1f1}
        .xtra{font-size:12px;color:#334155}
        .totals{font-size:12px;color:#374151;display:flex;justify-content:space-between;gap:8px}
        .bar{height:6px;background:#eef2ff;border-radius:999px;overflow:hidden;border:1px solid #dfe3ff}
        .bar > span{display:block;height:100%;background:#60a5fa}

        /* Подсветка всех вхождений одного фильтра */
        li.highlight-same{
            background:var(--hl);
            border-color:var(--hlborder);
            box-shadow:0 0 0 2px rgba(250,204,21,.35) inset;
        }
        li.highlight-same strong{
            text-decoration:underline;
            text-underline-offset:2px;
        }

        /* Красная полоса объявления */
        .announcement-bar {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
            color: white;
            padding: 12px 20px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-bottom: 16px;
        }

        .announcement-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .announcement-icon {
            font-size: 18px;
        }

        .announcement-text {
            font-size: 14px;
        }

        @media(max-width:900px){.calendar{grid-template-columns:repeat(3,1fr)}}
        @media(max-width:600px){.calendar{grid-template-columns:repeat(2,1fr)}}
        @media print{
            @page { size: landscape; margin: 10mm; }
            body{background:#fff}
            .toolbar{display:none}
            .day{break-inside:avoid;box-shadow:none}
        }
    </style>
</head>
<body>

<h1>План и факт сборки — заявка № <?= htmlspecialchars($order) ?></h1>

<?php if ($isReplanning): ?>
<div class="announcement-bar">
    <div class="announcement-content">
        <span class="announcement-icon">⚠️</span>
        <span class="announcement-text">План в стадии перепланирования, данные о датах могут быть изменены</span>
    </div>
</div>
<?php endif; ?>

<div class="toolbar">
    <div style="text-align:center; margin-bottom:15px;">
        <a href="view_production_plan_light.php?order=<?= urlencode($order) ?>"
           target="_blank"
           style="padding:8px 14px; background:#4CAF50; color:white; text-decoration:none; border-radius:6px;">
            📄 Версия для печати (лайт)
        </a>
    </div>

    <input type="text" id="searchInput" placeholder="Поиск фильтра...">
</div>

<div class="calendar">
    <?php foreach ($period as $dt):
        $d = $dt->format('Y-m-d');
        $planItems = $planByDate[$d] ?? [];
        $factMapDay = $factByDate[$d] ?? [];

        $sumPlan = sumPlanForDay($planItems);
        $sumFact = sumFactForDayMap($factMapDay);
        $pct = $sumPlan > 0 ? min(100, round($sumFact / $sumPlan * 100)) : ($sumFact > 0 ? 100 : 0);

        // множество всех баз на день
        $keys = [];
        foreach ($planItems as $it) $keys[$it['base']] = true;
        foreach ($factMapDay as $b => $_) $keys[$b] = true;
        ksort($keys, SORT_NATURAL|SORT_FLAG_CASE);
        ?>
        <div class="day">
            <div class="date"><?= $dt->format('d.m.Y') ?></div>

            <?php if ($planItems || $factMapDay): ?>
                <div class="totals">
                    <span>Итого: план <?= (int)$sumPlan ?> / факт <?= (int)$sumFact ?></span>
                    <span class="muted"><?= $pct ?>%</span>
                </div>
                <div class="bar"><span style="width: <?= $pct ?>%"></span></div>

                <ul>
                    <?php foreach (array_keys($keys) as $base):
                        $plan = 0; foreach ($planItems as $it) if ($it['base']===$base) $plan += (int)$it['count'];
                        $fact = (int)($factMapDay[$base] ?? 0);

                        $carryIn   = $carryInfo[$d][$base]['carry_in']   ?? 0;
                        $missToday = $carryInfo[$d][$base]['miss_today'] ?? 0;

                        $cls = ($fact >= $plan) ? 'ok' : ($fact>0 ? 'warn' : 'bad');
                        if ($plan===0 && $fact>0) $cls = 'ok';
                        ?>
                        <li data-key="<?= htmlspecialchars(mb_strtolower($base)) ?>">
                            <div class="row">
                                <strong><?= htmlspecialchars($base) ?></strong>
                                <span class="tag <?= $cls ?>">План: <?= (int)$plan ?> • Факт: <?= (int)$fact ?></span>
                            </div>
                            <?php if ($carryIn>0 || $missToday>0): ?>
                                <div class="xtra">
                                    <?php if ($carryIn>0): ?> → с прошлых дней: <b><?= (int)$carryIn ?></b><?php endif; ?>
                                    <?php if ($carryIn>0 && $missToday>0): ?> • <?php endif; ?>
                                    <?php if ($missToday>0): ?> не сделано сегодня: <b><?= (int)$missToday ?></b><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <em class="muted">Нет задач и факта</em>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
    // Поиск по названию фильтра
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.day li').forEach(li => {
            li.style.display = (!q || (li.getAttribute('data-key')||'').includes(q)) ? '' : 'none';
        });
    });

    // Сквозная подсветка одинаковых фильтров при наведении на НАЗВАНИЕ (strong)
    const calendar = document.querySelector('.calendar');

    function addHighlight(key){
        if(!key) return;
        document.querySelectorAll(`.day li[data-key="${CSS.escape(key)}"]`)
            .forEach(li => li.classList.add('highlight-same'));
    }
    function removeHighlight(){
        document.querySelectorAll('.day li.highlight-same')
            .forEach(li => li.classList.remove('highlight-same'));
    }

    // Делегируем события: реагируем только на hover по <strong>
    calendar.addEventListener('mouseover', (e) => {
        const strong = e.target.closest('strong');
        if (!strong) return;
        const li = strong.closest('li');
        if (!li) return;
        const key = (li.getAttribute('data-key')||'').toLowerCase();
        removeHighlight();
        addHighlight(key);
    });
    calendar.addEventListener('mouseout', (e) => {
        // Снимаем подсветку, когда курсор уходит с имени
        const related = e.relatedTarget;
        // Если ушли на другой strong того же ключа — подсветка обновится по mouseover
        if (!e.target.closest('strong')) return;
        // Если ушли куда-то ещё — убираем подсветку
        if (!related || !related.closest || !related.closest('strong')) {
            removeHighlight();
        }
    });
</script>

</body>
</html>
