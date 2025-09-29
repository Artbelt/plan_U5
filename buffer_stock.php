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
            a.last_ass_date,
            COALESCE(
                CAST(pps.p_p_height AS DECIMAL(10,3)),
                CAST(cp.height AS DECIMAL(10,3))
            ) AS height
        FROM ($corrSub) AS c
        LEFT JOIN ($asmSub) AS a
          ON a.order_number = c.order_number
         AND a.filter_label = c.filter_label
        LEFT JOIN salon_filter_structure sfs 
          ON sfs.filter = c.filter_label
        LEFT JOIN paper_package_salon pps 
          ON pps.p_p_name = sfs.paper_package
        LEFT JOIN (
            SELECT filter, height 
            FROM cut_plans 
            WHERE height IS NOT NULL 
            GROUP BY filter 
            HAVING COUNT(*) > 0
        ) cp ON cp.filter = c.filter_label
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
            /* ===== Pro UI (neutral + single accent) ===== */
            :root{
                --bg:#f6f7f9;
                --panel:#ffffff;
                --ink:#1f2937;
                --muted:#6b7280;
                --border:#e5e7eb;
                --accent:#2457e6;
                --accent-ink:#ffffff;
                --radius:12px;
                --shadow:0 2px 12px rgba(2,8,20,.06);
                --shadow-soft:0 1px 8px rgba(2,8,20,.05);
            }
            html,body{height:100%}
            body{
                margin:0; background:var(--bg); color:var(--ink);
                font:14px/1.45 "Segoe UI", Roboto, Arial, sans-serif;
                -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
            }
            a{color:var(--accent); text-decoration:none}
            a:hover{text-decoration:underline}

            /* контейнер и сетка */
            .container{ max-width:1280px; margin:0 auto; padding:16px; }

            /* панели */
            .panel{
                background:var(--panel);
                border:1px solid var(--border);
                border-radius:var(--radius);
                box-shadow:var(--shadow);
                padding:16px;
                margin-bottom:16px;
            }
            .section-title{
                font-size:15px; font-weight:600; color:#111827;
                margin:0 0 12px; padding-bottom:6px; border-bottom:1px solid var(--border);
            }

            /* таблицы внутри панелей */
            .panel table{
                width:100%;
                border-collapse:collapse;
                background:#fff;
                border:1px solid var(--border);
                border-radius:10px;
                box-shadow:var(--shadow-soft);
                overflow:hidden;
            }
            .panel td,.panel th{padding:10px;border-bottom:1px solid var(--border);vertical-align:top}
            .panel tr:last-child td{border-bottom:0}
            th{background:#f8fafc; text-align:left; font-weight:600; color:var(--ink)}
            tr.highlight td{background:#fffbe6;}

            /* кнопки (единый стиль) */
            button, input[type="submit"]{
                appearance:none;
                border:1px solid transparent;
                cursor:pointer;
                background:var(--accent);
                color:var(--accent-ink);
                padding:7px 14px;
                border-radius:9px;
                font-weight:600;
                transition:background .2s, box-shadow .2s, transform .04s, border-color .2s;
                box-shadow:0 3px 6px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
            }
            button:hover, input[type="submit"]:hover{ background:#1e47c5; box-shadow:0 2px 8px rgba(2,8,20,.10); transform:translateY(-1px); }
            button:active, input[type="submit"]:active{ transform:translateY(0); }
            button:disabled, input[type="submit"]:disabled{
                background:#e5e7eb; color:#9ca3af; border-color:#e5e7eb; box-shadow:none; cursor:not-allowed;
            }

            /* поля ввода/селекты */
            input[type="text"], input[type="date"], input[type="number"], input[type="password"],
            textarea, select{
                min-width:180px; padding:7px 10px;
                border:1px solid var(--border); border-radius:9px;
                background:#fff; color:var(--ink); outline:none;
                transition:border-color .2s, box-shadow .2s;
            }
            input:focus, textarea:focus, select:focus{
                border-color:#c7d2fe; box-shadow:0 0 0 3px #e0e7ff;
            }

            /* инфоблоки */
            .alert{
                background:#fffbe6; border:1px solid #f4e4a4; color:#634100;
                padding:10px; border-radius:9px; margin:12px 0; font-weight:600;
            }
            .muted{color:var(--muted); font-size:12px}

            /* специфичные стили */
            .num{text-align:right; font-weight:500}
            .filters{
                display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; 
                align-items:center; padding:12px; background:var(--panel); 
                border-radius:var(--radius); border:1px solid var(--border);
                box-shadow:var(--shadow-soft);
            }
            .filters input{padding:7px 10px; border:1px solid var(--border); border-radius:9px}
            .filters button{padding:7px 14px; border-radius:9px; font-weight:600}
            .tag{font-size:12px; color:var(--muted); display:flex; align-items:center; gap:6px}
            .tag input[type="checkbox"]{margin:0}
            
            /* сортировка */
            .sortable{cursor:pointer; user-select:none; position:relative; transition:background-color 0.2s}
            .sortable:hover{background-color:#f1f5f9}
            .sortable::after{content:''; position:absolute; right:8px; top:50%; transform:translateY(-50%); width:0; height:0; border-left:4px solid transparent; border-right:4px solid transparent; opacity:0.5}
            .sortable.asc::after{border-bottom:6px solid var(--accent); opacity:1}
            .sortable.desc::after{border-top:6px solid var(--accent); opacity:1}

            /* итоги */
            #totals{
                margin-top:16px; font-weight:600; padding:12px; 
                background:var(--panel); border-radius:var(--radius); 
                border:1px solid var(--border); box-shadow:var(--shadow-soft);
                color:var(--ink);
            }

            /* адаптив */
            @media (max-width:768px){
                .filters{flex-direction:column; align-items:stretch; gap:8px}
                .filters input{min-width:auto; width:100%}
                .panel table{font-size:12px}
                .panel td,.panel th{padding:8px 6px}
            }
        </style>

    </head>
    <body>
    <div class="container">
        <div class="panel">
            <div class="section-title">Буфер гофропакетов</div>
            <p class="muted">Наличие заготовок в буфере (гофра → сборка)</p>
        </div>

        <div class="panel">
            <div class="section-title">Фильтры</div>
            <form class="filters" method="get">
                <input type="date" name="date_from" value="<?=h($date_from)?>" placeholder="От даты">
                <input type="date" name="date_to"   value="<?=h($date_to)?>" placeholder="До даты">
                <input type="text" name="order"     value="<?=h($order)?>"   placeholder="Заявка (order_number)">
                <input type="text" name="filter"    value="<?=h($filter)?>"  placeholder="Фильтр (filter_label)">
                <label class="tag"><input type="checkbox" name="include_zero" value="1" <?= $includeZero?'checked':''; ?>> показывать нули/минусы</label>
                <button type="submit">Показать</button>
                <label class="tag">
                    <input type="checkbox" id="hideSmall"> Скрывать остатки меньше 30
                </label>
            </form>
        </div>

        <div class="panel">
            <div class="section-title">Данные буфера</div>
            <table>
                <thead>
                <tr>
                    <th class="sortable" data-column="0">Заявка</th>
                    <th class="sortable" data-column="1">Фильтр</th>
                    <th class="sortable num" data-column="2">Высота (мм)</th>
                    <th class="sortable num" data-column="3">Сгофрировано</th>
                    <th class="sortable num" data-column="4">Собрано</th>
                    <th class="sortable num" data-column="5">Буфер</th>
                    <th class="sortable" data-column="6">Последняя гофра</th>
                    <th class="sortable" data-column="7">Последняя сборка</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="tag">Нет данных под выбранные фильтры.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="<?=($r['buffer']>0?'highlight':'')?>">
                        <td><?=h($r['order_number'])?></td>
                        <td><?=h($r['filter_label'])?></td>
                        <td class="num"><?= $r['height'] ? rtrim(rtrim(number_format((float)$r['height'], 1, '.', ' '), '0'), '.') : '-' ?></td>
                        <td class="num"><?=number_format((float)$r['corrugated'], 0, '.', ' ')?></td>
                        <td class="num"><?=number_format((float)$r['assembled'],   0, '.', ' ')?></td>
                        <td class="num"><strong><?=number_format((float)$r['buffer'], 0, '.', ' ')?></strong></td>
                        <td><?=h($r['last_corr_date'] ?? '')?></td>
                        <td><?=h($r['last_ass_date']  ?? '')?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <div id="totals">
                Итого буфер: 0
            </div>
        </div>
    </div>

    <script>
        document.getElementById('hideSmall').addEventListener('change', function() {
            const rows = document.querySelectorAll("table tbody tr");
            rows.forEach(tr => {
                const bufCell = tr.querySelector("td:nth-child(6)"); // 6-й столбец — буфер (после добавления высоты)
                if (!bufCell) return;
                const val = parseInt(bufCell.textContent.replace(/\s+/g,'')) || 0;
                if (this.checked && val < 30) {
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
                const bufCell = tr.querySelector("td:nth-child(6)"); // 6-й столбец — буфер (после добавления высоты)
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
                const bufCell = tr.querySelector("td:nth-child(6)"); // 6-й столбец — буфер (после добавления высоты)
                if (!bufCell) return;
                const val = parseInt(bufCell.textContent.replace(/\s+/g,'')) || 0;
                if (this.checked && val < 30) {
                    tr.style.display = "none";
                } else {
                    tr.style.display = "";
                }
            });
            refreshTotals();
        });

        // посчитать один раз при загрузке
        refreshTotals();
        
        // Функциональность сортировки таблицы
        let currentSort = { column: -1, direction: 'asc' };
        
        function sortTable(columnIndex) {
            const tbody = document.querySelector('table tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Определяем направление сортировки
            if (currentSort.column === columnIndex) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.direction = 'asc';
            }
            currentSort.column = columnIndex;
            
            // Сортируем строки
            rows.sort((a, b) => {
                const aCell = a.cells[columnIndex];
                const bCell = b.cells[columnIndex];
                
                if (!aCell || !bCell) return 0;
                
                let aValue = aCell.textContent.trim();
                let bValue = bCell.textContent.trim();
                
                // Обработка числовых значений
                if (columnIndex >= 2 && columnIndex <= 5) { // числовые колонки
                    aValue = parseFloat(aValue.replace(/\s+/g, '')) || 0;
                    bValue = parseFloat(bValue.replace(/\s+/g, '')) || 0;
                } else if (columnIndex === 6 || columnIndex === 7) { // даты
                    aValue = aValue === '' ? '0000-00-00' : aValue;
                    bValue = bValue === '' ? '0000-00-00' : bValue;
                }
                
                let comparison = 0;
                if (aValue < bValue) comparison = -1;
                else if (aValue > bValue) comparison = 1;
                
                return currentSort.direction === 'asc' ? comparison : -comparison;
            });
            
            // Перестраиваем таблицу
            rows.forEach(row => tbody.appendChild(row));
            
            // Обновляем визуальные индикаторы сортировки
            document.querySelectorAll('th.sortable').forEach((th, index) => {
                th.classList.remove('asc', 'desc');
                if (index === columnIndex) {
                    th.classList.add(currentSort.direction);
                }
            });
            
            // Пересчитываем итоги после сортировки
            refreshTotals();
        }
        
        // Добавляем обработчики кликов на заголовки
        document.querySelectorAll('th.sortable').forEach((th, index) => {
            th.addEventListener('click', () => sortTable(index));
        });
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
