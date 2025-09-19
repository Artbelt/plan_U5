<?php
// buffer_stock.php — буфер гофропакетов (что сгофрировано и еще не собрано)

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

$dsn  = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root";
$pass = "";

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/**
 * Возвращает массив остатков буфера по (order_number, filter_label)
 * Поля строки: order_number, filter_label, corrugated, assembled, buffer, last_corr_date, last_ass_date
 *
 * Фильтры:
 * - date_from (Y-m-d)  — нижняя граница для план/факт (опционально)
 * - date_to   (Y-m-d)  — верхняя граница (опционально)
 * - order               — конкретная заявка (опционально)
 * - filter              — конкретный фильтр (опционально)
 * - include_zero        — если true, показывать и buffer<=0
 */
function get_buffer(PDO $pdo, array $opts = []): array {
    $date_from   = $opts['date_from'] ?? null;   // применяем отдельно к каждой подвыборке
    $date_to     = $opts['date_to']   ?? null;
    $order       = $opts['order']     ?? null;
    $filter      = $opts['filter']    ?? null;
    $includeZero = !empty($opts['include_zero']);

    // --- подзапрос по гофре (что произведено) ---
    $wCorr = ["c.fact_count > 0"];
    $paramsCorr = [];
    if ($date_from) { $wCorr[] = "c.plan_date >= ?"; $paramsCorr[] = $date_from; }
    if ($date_to)   { $wCorr[] = "c.plan_date <= ?"; $paramsCorr[] = $date_to; }
    if ($order)     { $wCorr[] = "c.order_number = ?"; $paramsCorr[] = $order; }
    if ($filter)    { $wCorr[] = "c.filter_label = ?"; $paramsCorr[] = $filter; }
    $whereCorr = $wCorr ? ("WHERE ".implode(" AND ", $wCorr)) : "";

    $corrSub = "
        SELECT
            c.order_number,
            c.filter_label,
            SUM(COALESCE(c.fact_count,0)) AS corrugated,
            MAX(c.plan_date)              AS last_corr_date
        FROM corrugation_plan c
        $whereCorr
        GROUP BY c.order_number, c.filter_label
    ";

    // --- подзапрос по сборке (что уже забрали) ---
    $wAsm = [];
    $paramsAsm = [];
    if ($date_from) { $wAsm[] = "m.date_of_production >= ?"; $paramsAsm[] = $date_from; }
    if ($date_to)   { $wAsm[] = "m.date_of_production <= ?"; $paramsAsm[] = $date_to; }
    if ($order)     { $wAsm[] = "m.name_of_order = ?";       $paramsAsm[] = $order; }
    if ($filter)    { $wAsm[] = "m.name_of_filter = ?";      $paramsAsm[] = $filter; }
    $whereAsm = $wAsm ? ("WHERE ".implode(" AND ", $wAsm)) : "";

    $asmSub = "
        SELECT
            m.name_of_order  AS order_number,
            m.name_of_filter AS filter_label,
            SUM(COALESCE(m.count_of_filters,0)) AS assembled,
            MAX(m.date_of_production)           AS last_ass_date
        FROM manufactured_production m
        $whereAsm
        GROUP BY m.name_of_order, m.name_of_filter
    ";

    // --- объединение и расчёт буфера ---
    // берём LEFT JOIN, чтобы видеть даже то, что еще ни разу не собирали
    $sql = "
        SELECT
            c.order_number,
            c.filter_label,
            c.corrugated,
            COALESCE(a.assembled, 0) AS assembled,
            (c.corrugated - COALESCE(a.assembled, 0)) AS buffer,
            c.last_corr_date,
            a.last_ass_date
        FROM ($corrSub) AS c
        LEFT JOIN ($asmSub) AS a
          ON a.order_number = c.order_number
         AND a.filter_label = c.filter_label
    ";

    if (!$includeZero) {
        $sql .= " HAVING buffer > 0";
    }

    $sql .= " ORDER BY buffer DESC, c.order_number, c.filter_label";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($paramsCorr, $paramsAsm));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- контроллер ----------
$format     = $_GET['format']     ?? null;      // 'json' | (html)
$date_from  = $_GET['date_from']  ?? null;
$date_to    = $_GET['date_to']    ?? null;
$order      = $_GET['order']      ?? null;
$filter     = $_GET['filter']     ?? null;
$includeZero= isset($_GET['include_zero']) && $_GET['include_zero'] == '1';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $rows = get_buffer($pdo, [
        'date_from'    => $date_from,
        'date_to'      => $date_to,
        'order'        => $order,
        'filter'       => $filter,
        'include_zero' => $includeZero,
    ]);

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>true, 'count'=>count($rows), 'items'=>$rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Простая HTML-таблица для быстрых глаз
    ?>
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <title>Буфер гофропакетов</title>
        <style>
            body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin:20px; }
            table { border-collapse: collapse; width:100%; }
            th, td { border:1px solid #ddd; padding:8px; }
            th { background:#f7f7f7; text-align:left; }
            tr.highlight td { background:#fffbe6; }
            .num { text-align:right; }
            .filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
            .filters input { padding:6px 8px; }
            .filters button { padding:6px 10px; cursor:pointer; }
            .tag { font-size:12px; color:#666; }
        </style>

    </head>
    <body>

    <h2>Наличие заготовок в буфере (гофра → сборка)</h2>

    <form class="filters" method="get">
        <input type="date" name="date_from" value="<?=h($date_from)?>" placeholder="От даты">
        <input type="date" name="date_to"   value="<?=h($date_to)?>" placeholder="До даты">
        <input type="text" name="order"     value="<?=h($order)?>"   placeholder="Заявка (order_number)">
        <input type="text" name="filter"    value="<?=h($filter)?>"  placeholder="Фильтр (filter_label)">
        <label class="tag"><input type="checkbox" name="include_zero" value="1" <?= $includeZero?'checked':''; ?>> показывать нули/минусы</label>
        <button type="submit">Показать</button>
        <label>
            <input type="checkbox" id="hideSmall"> Скрывать остатки меньше 20
        </label>

    </form>

    <table>
        <thead>
        <tr>
            <th>Заявка</th>
            <th>Фильтр</th>
            <th class="num">Сгофрировано</th>
            <th class="num">Собрано</th>
            <th class="num">Буфер</th>
            <th>Последняя гофра</th>
            <th>Последняя сборка</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="7" class="tag">Нет данных под выбранные фильтры.</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <tr class="<?=($r['buffer']>0?'highlight':'')?>">
                <td><?=h($r['order_number'])?></td>
                <td><?=h($r['filter_label'])?></td>
                <td class="num"><?=number_format((float)$r['corrugated'], 0, '.', ' ')?></td>
                <td class="num"><?=number_format((float)$r['assembled'],   0, '.', ' ')?></td>
                <td class="num"><strong><?=number_format((float)$r['buffer'], 0, '.', ' ')?></strong></td>
                <td><?=h($r['last_corr_date'] ?? '')?></td>
                <td><?=h($r['last_ass_date']  ?? '')?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div id="totals" style="margin-top:10px; font-weight:bold;">
        Итого буфер: 0
    </div>

    <script>
        document.getElementById('hideSmall').addEventListener('change', function() {
            const rows = document.querySelectorAll("table tbody tr");
            rows.forEach(tr => {
                const bufCell = tr.querySelector("td:nth-child(5)"); // 5-й столбец — буфер
                if (!bufCell) return;
                const val = parseInt(bufCell.textContent.replace(/\s+/g,'')) || 0;
                if (this.checked && val < 20) {
                    tr.style.display = "none";
                } else {
                    tr.style.display = "";
                }
            });
        });
    </script>
    <script>
        function refreshTotals() {
            let sum = 0;
            document.querySelectorAll("table tbody tr").forEach(tr => {
                if (tr.style.display === "none") return;
                const bufCell = tr.querySelector("td:nth-child(5)");
                if (bufCell) {
                    const val = parseInt(bufCell.textContent.replace(/\s+/g,'')) || 0;
                    sum += val;
                }
            });
            document.getElementById("totals").textContent = "Итого буфер: " + sum.toLocaleString("ru-RU");
        }

        document.getElementById('hideSmall').addEventListener('change', function() {
            const rows = document.querySelectorAll("table tbody tr");
            rows.forEach(tr => {
                const bufCell = tr.querySelector("td:nth-child(5)");
                if (!bufCell) return;
                const val = parseInt(bufCell.textContent.replace(/\s+/g,'')) || 0;
                if (this.checked && val < 20) {
                    tr.style.display = "none";
                } else {
                    tr.style.display = "";
                }
            });
            refreshTotals();
        });

        // посчитать один раз при загрузке
        refreshTotals();
    </script>


    </body>
    </html>
    <?php

} catch (Throwable $e) {
    http_response_code(500);
    if (($format ?? '') === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    } else {
        echo "<pre style='color:#b00'>Ошибка: ".h($e->getMessage())."</pre>";
    }
}
