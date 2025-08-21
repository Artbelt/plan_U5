<?php
/* NP_cut_index.php — панель этапов для салонного проекта */

$dsn  = "mysql:host=127.0.0.1;dbname=plan_U5;charset=utf8mb4";
$user = "root";
$pass = "";

/* Страница раскроя (ваш рабочий файл с параметром ?order_number=...) */
$CUT_PAGE = "NP_cut_plan.php";

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* Утилиты */
function tableExists(PDO $pdo, string $table): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $st->execute([$table]);
    return (bool)$st->fetchColumn();
}
function columnExists(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $st->execute([$table, $col]);
    return (bool)$st->fetchColumn();
}

/* Список заявок */
$orders = $pdo->query("
    SELECT DISTINCT order_number
    FROM orders
    WHERE COALESCE(hide,0) != 1
    ORDER BY order_number
")->fetchAll();

/* Статусы по умолчанию */
$statuses = [];
foreach ($orders as $o) {
    $ord = $o['order_number'];
    $statuses[$ord] = [
        'cut_ready'     => 0,
        'cut_confirmed' => 0,
        'plan_ready'    => 0,
        'corr_ready'    => 0,
        'build_ready'   => 0,
    ];
}

/* Этап 1: раскрой — готов, если есть строки в cut_plan_salon */
if (tableExists($pdo, 'cut_plan_salon')) {
    $st = $pdo->query("SELECT DISTINCT order_number FROM cut_plan_salon");
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ord) {
        if (isset($statuses[$ord])) $statuses[$ord]['cut_ready'] = 1;
    }
}

/* Остальные статусы — если есть соответствующие колонки в orders */
$flagCols = ['cut_confirmed','plan_ready','corr_ready','build_ready'];
$existingCols = array_filter($flagCols, fn($c)=>columnExists($pdo,'orders',$c));
if ($existingCols) {
    $sql = "SELECT order_number," . implode(',', array_map(fn($c)=>"MAX($c) AS $c", $existingCols)) .
        " FROM orders WHERE COALESCE(hide,0) != 1 GROUP BY order_number";
    foreach ($pdo->query($sql) as $row) {
        $ord = $row['order_number'];
        if (!isset($statuses[$ord])) continue;
        foreach ($existingCols as $c) $statuses[$ord][$c] = (int)$row[$c];
    }
}

/* Для примера: если есть corrugation_plan (или *_salon), показываем «Готово» в столбце гофро */
$corrTables = [];
if (tableExists($pdo,'corrugation_plan'))       $corrTables[] = 'corrugation_plan';
if (tableExists($pdo,'corrugation_plan_salon')) $corrTables[] = 'corrugation_plan_salon';
foreach ($corrTables as $t) {
    foreach ($pdo->query("SELECT DISTINCT order_number FROM $t")->fetchAll(PDO::FETCH_COLUMN) as $ord) {
        if (isset($statuses[$ord])) $statuses[$ord]['corr_ready'] = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Этапы планирования (Salon)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --bg:#f6f7fb; --card:#ffffff; --text:#111827; --muted:#6b7280;
            --line:#e5e7eb; --ok:#16a34a; --accent:#2563eb; --accent2:#0ea5e9;
        }
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);margin:20px;color:var(--text)}
        h2{text-align:center;margin:6px 0 16px}
        .wrap{max-width:1200px;margin:0 auto}
        table{border-collapse:collapse;width:100%;background:#fff;border:1px solid var(--line);box-shadow:0 2px 10px rgba(0,0,0,.05)}
        th,td{border:1px solid var(--line);padding:10px 12px;text-align:center;vertical-align:middle}
        th{background:#f3f4f6;font-weight:600}
        td:first-child{font-weight:600}
        .btn{padding:6px 10px;border-radius:8px;background:var(--accent);color:#fff;text-decoration:none;border:1px solid var(--accent)}
        .btn:hover{filter:brightness(.95)}
        .btn-secondary{background:#eef6ff;color:var(--accent);border-color:#cfe0ff}
        .btn-print{background:#ecfeff;color:var(--accent2);border-color:#bae6fd}
        .done{color:var(--ok);font-weight:700}
        .disabled{color:#9ca3af}
        .stack{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
        .sub{display:block;font-size:12px;color:var(--muted);margin-top:4px}
        @media (max-width:900px){
            table{font-size:13px}
            .stack{gap:6px}
            .btn,.btn-secondary,.btn-print{padding:6px 8px}
        }
        @media print{
            body{background:#fff;margin:0}
            .wrap{max-width:none}
            a.btn, a.btn-secondary, a.btn-print{display:none}
        }
    </style>
</head>
<body>
<div class="wrap">
    <h2>Планирование заявок (Salon)</h2>

    <table>
        <tr>
            <th>Заявка</th>
            <th>Раскрой (подготовка)</th>
            <th>Утверждение</th>
            <th>План раскроя рулона</th>
            <th>План гофрирования</th>
            <th>План сборки</th>
        </tr>

        <?php foreach ($orders as $o):
            $ord = $o['order_number'];
            $st  = $statuses[$ord] ?? ['cut_ready'=>0,'cut_confirmed'=>0,'plan_ready'=>0,'corr_ready'=>0,'build_ready'=>0];
            ?>
            <tr>
                <td><?= htmlspecialchars($ord) ?></td>

                <!-- Раскрой (подготовка) -->
                <td>
                    <?php if ($st['cut_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="<?= htmlspecialchars($CUT_PAGE) ?>?order_number=<?= urlencode($ord) ?>">Открыть раскрой</a>
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" target="_blank" href="<?= htmlspecialchars($CUT_PAGE) ?>?order_number=<?= urlencode($ord) ?>">Сделать раскрой</a>
                        </div>
                        <span class="sub">нет данных для просмотра</span>
                    <?php endif; ?>
                </td>

                <!-- Утверждение -->
                <td>
                    <?php if ($st['cut_ready']): ?>
                        <span class="disabled">Не готово</span>
                    <?php elseif ($st['cut_confirmed']): ?>
                        <div class="done">✅ Утверждено</div>
                        <div class="stack">
                            <!-- при необходимости добавьте ссылку на печать утверждения -->
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP/confirm_cut.php?order=<?= urlencode($ord) ?>">Утвердить</a>
                        </div>
                        <span class="sub">утвердите, затем появится печать</span>
                    <?php endif; ?>
                    <?php if ($st['cut_confirmed']): ?>
                    <a class="btn-secondary" target="_blank" href="<?= htmlspecialchars('NP/confirm_cut.php') ?>?order=<?= urlencode($ord) ?>">Открыть утверждение</a>
                    <?php endif; ?>
                </td>

                <!-- План раскроя рулона -->
                <td>
                    <?php if (!$st['cut_confirmed']): ?>
                        <span class="disabled">Не утверждено</span>
                    <?php elseif ($st['plan_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="NP_roll_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP_roll_plan.php?order=<?= urlencode($ord) ?>">Планировать раскрой</a>
                        </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                    <?php endif; ?>
                </td>

                <!-- План гофрирования -->
                <td>
                    <?php if (!$st['plan_ready']): ?>
                        <span class="disabled">Не готов план раскроя</span>
                    <?php elseif ($st['corr_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="NP_view_corrugation_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP_corrugation_plan.php?order=<?= urlencode($ord) ?>">Планировать гофрирование</a>
                        </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                    <?php endif; ?>
                </td>

                <!-- План сборки -->
                <td>
                    <?php if (!$st['corr_ready']): ?>
                        <span class="disabled">Нет гофроплана</span>
                    <?php elseif ($st['build_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="NP_view_production_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP_build_plan.php?order=<?= urlencode($ord) ?>">Планировать сборку</a>
                        </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>
