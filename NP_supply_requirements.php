<?php
// NP_supply_by_order.php — потребность по конкретной заявке
// Печать: таблица разбивается на несколько страниц по N дат (по умолчанию 20)

$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4","root","",[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
]);

/* ===== AJAX: отрисовать только таблицы ===== */
if (isset($_GET['ajax']) && $_GET['ajax']=='1') {
    $order = $_POST['order'] ?? '';
    $ctype = $_POST['ctype'] ?? '';          // box
    $chunkSize = 20; // Фиксированное значение для печати

    if ($order==='' || $ctype==='') {
        http_response_code(400);
        echo "<p>Не указана заявка или тип комплектующих.</p>";
        exit;
    }

    // Единый запрос по выбранной заявке
    $sql = "
    WITH bp AS (SELECT
                order_number,
                TRIM(SUBSTRING_INDEX(`filter`, ' [', 1)) AS base_filter,
                `filter`           AS filter_label,
                plan_date          AS need_by_date,
                `count`
              FROM build_plan
              WHERE order_number = :ord
            ),
            p AS (
                SELECT
                b.order_number,
                b.base_filter,
                b.filter_label,
                b.need_by_date,
                b.`count`,
                sfs.box,
                sfs.g_box
              FROM bp b
              LEFT JOIN salon_filter_structure sfs
                ON sfs.`filter` = b.base_filter
            ),
            o AS (
                SELECT
                order_number,
                COALESCE(packaging_rate, 1) AS packaging_rate
              FROM orders
              WHERE order_number = :ord
            )
            SELECT
            'box' AS component_type,
              p.box AS component_name,
              p.need_by_date AS need_by_date,
              p.filter_label,
              p.base_filter,
              p.`count` AS qty
            FROM p
            WHERE p.box IS NOT NULL AND p.box <> ''
            ORDER BY p.need_by_date, component_name, p.base_filter;";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ord'=>$order]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "<p>По заявке <b>".htmlspecialchars($order)."</b> для индивидуальных коробок данных нет.</p>";
        exit;
    }

    // Пивот-структура
    $dates = [];       // список дат
    $items = [];       // список компонент (строки)
    $matrix = [];      // matrix[item][date] = qty
    foreach ($rows as $r) {
        $d = $r['need_by_date'];
        $name = $r['component_name'];
        if ($name === null || $name === '') continue;

        $dates[$d] = true;
        $items[$name] = true;

        if (!isset($matrix[$name])) $matrix[$name] = [];
        if (!isset($matrix[$name][$d])) $matrix[$name][$d] = 0;
        $matrix[$name][$d] += (float)$r['qty'];
    }
    $dates = array_keys($dates);
    sort($dates);
    $items = array_keys($items);
    sort($items, SORT_NATURAL|SORT_FLAG_CASE);

    $title = 'индивидуальная коробка';

    // Хелпер форматирования
    function fmt($x){ 
        $formatted = rtrim(rtrim(number_format((float)$x,3,'.',''), '0'), '.');
        // Если число очень длинное, обрезаем его для отображения
        if (strlen($formatted) > 8) {
            return substr($formatted, 0, 8) . '...';
        }
        return $formatted;
    }
    
    // Хелпер для получения полного значения для title
    function fmtFull($x){ 
        return rtrim(rtrim(number_format((float)$x,3,'.',''), '0'), '.'); 
    }

    // Заголовок для печати (минимальный, в одну строку)
    echo "<div class=\"print-header\">";
    echo "<div class=\"print-title-line\">";
    echo "<span class=\"print-title\">Потребность комплектующих по заявке</span>";
    echo "<span class=\"print-order\">Заявка ".htmlspecialchars($order).": потребность — ".htmlspecialchars($title)."</span>";
    echo "</div>";
    echo "</div>";

    // Создаем одну цельную таблицу для отображения на экране
    echo '<div class="table-wrap"><table class="pivot">';
    echo '<thead><tr><th class="left">Позиция</th>';
    foreach ($dates as $d) {
        echo '<th class="nowrap vertical-date">' . date('d-m-y', strtotime($d)) . '</th>';
    }
    echo '<th class="nowrap">Итого</th></tr></thead><tbody>';

    foreach ($items as $name) {
        $rowTotal = 0;
        echo '<tr><td class="left">'.htmlspecialchars($name).'</td>';
        foreach ($dates as $d) {
            $v = $matrix[$name][$d] ?? 0;
            $rowTotal += $v;
            if ($v) {
                $displayValue = fmt($v);
                $fullValue = fmtFull($v);
                if (strlen($displayValue) != strlen($fullValue)) {
                    echo '<td title="'.$fullValue.'">'.$displayValue.'</td>';
                } else {
                    echo '<td>'.$displayValue.'</td>';
                }
            } else {
                echo '<td></td>';
            }
        }
        $displayTotal = fmt($rowTotal);
        $fullTotal = fmtFull($rowTotal);
        if (strlen($displayTotal) != strlen($fullTotal)) {
            echo '<td class="total" title="'.$fullTotal.'">'.$displayTotal.'</td></tr>';
        } else {
            echo '<td class="total">'.$displayTotal.'</td></tr>';
        }
    }

    // Итоги по датам
    echo '<tr class="foot"><td class="left nowrap">Итого по дням</td>';
    $grand = 0;
    foreach ($dates as $d) {
        $col = 0;
        foreach ($items as $name) $col += $matrix[$name][$d] ?? 0;
        $grand += $col;
        if ($col) {
            $displayCol = fmt($col);
            $fullCol = fmtFull($col);
            if (strlen($displayCol) != strlen($fullCol)) {
                echo '<td class="total" title="'.$fullCol.'">'.$displayCol.'</td>';
            } else {
                echo '<td class="total">'.$displayCol.'</td>';
            }
        } else {
            echo '<td class="total"></td>';
        }
    }
    $displayGrand = fmt($grand);
    $fullGrand = fmtFull($grand);
    if (strlen($displayGrand) != strlen($fullGrand)) {
        echo '<td class="grand" title="'.$fullGrand.'">'.$displayGrand.'</td></tr>';
    } else {
        echo '<td class="grand">'.$displayGrand.'</td></tr>';
    }

    echo '</tbody></table></div>'; // table-wrap

    // Создаем скрытые копии таблицы для печати с разбивкой на страницы
    echo '<div class="print-only">';
    $dateChunks = array_chunk($dates, $chunkSize, true);

    // Вычисляем общие итоги за все страницы для каждой позиции
    $grandTotalsByItem = [];
    $grandTotalAll = 0;
    foreach ($items as $name) {
        $rowTotalAll = 0;
        foreach ($dates as $d) {
            $rowTotalAll += $matrix[$name][$d] ?? 0;
        }
        $grandTotalsByItem[$name] = $rowTotalAll;
        $grandTotalAll += $rowTotalAll;
    }

    $totalChunks = count($dateChunks);
    foreach ($dateChunks as $i => $chunkDates) {
        $isLastPage = ($i == $totalChunks - 1);
        
        echo '<div class="sheet">';                   // оболочка страницы
        echo '<div class="table-wrap"><table class="pivot">';
        echo '<thead><tr><th class="left">Позиция</th>';
        foreach ($chunkDates as $d) {
            echo '<th class="nowrap vertical-date">' . date('d-m-y', strtotime($d)) . '</th>';
        }
        echo '<th class="nowrap">Итого</th>';
        if ($isLastPage) {
            echo '<th class="nowrap grand-total-header">Итого за все страницы</th>';
            echo '<th class="nowrap position-names-header">Позиция</th>';
        }
        echo '</tr></thead><tbody>';

        // Пересчитываем итоги только для текущего чанка дат
        foreach ($items as $name) {
            $rowTotal = 0;
            echo '<tr><td class="left">'.htmlspecialchars($name).'</td>';
            foreach ($chunkDates as $d) {
                $v = $matrix[$name][$d] ?? 0;
                $rowTotal += $v;
                if ($v) {
                    $displayValue = fmt($v);
                    $fullValue = fmtFull($v);
                    if (strlen($displayValue) != strlen($fullValue)) {
                        echo '<td title="'.$fullValue.'">'.$displayValue.'</td>';
                    } else {
                        echo '<td>'.$displayValue.'</td>';
                    }
                } else {
                    echo '<td></td>';
                }
            }
            // Итого для строки - только по датам в этом чанке
            $displayTotal = fmt($rowTotal);
            $fullTotal = fmtFull($rowTotal);
            if (strlen($displayTotal) != strlen($fullTotal)) {
                echo '<td class="total" title="'.$fullTotal.'">'.$displayTotal.'</td>';
            } else {
                echo '<td class="total">'.$displayTotal.'</td>';
            }
            
            // Дополнительные колонки только на последней странице
            if ($isLastPage) {
                // Итого за все страницы для этой позиции
                $grandTotalForItem = $grandTotalsByItem[$name];
                $displayGrandItem = fmt($grandTotalForItem);
                $fullGrandItem = fmtFull($grandTotalForItem);
                if (strlen($displayGrandItem) != strlen($fullGrandItem)) {
                    echo '<td class="grand-total-cell" title="'.$fullGrandItem.'"><strong>'.$displayGrandItem.'</strong></td>';
                } else {
                    echo '<td class="grand-total-cell"><strong>'.$displayGrandItem.'</strong></td>';
                }
                
                // Дублированное название позиции справа
                echo '<td class="left position-names-cell">'.htmlspecialchars($name).'</td>';
            }
            echo '</tr>';
        }

        // Строка "Итого по дням" убрана для экономии места при печати

        echo '</tbody></table></div>'; // table-wrap
        echo '</div>'; // sheet
    }
    echo '</div>'; // print-only

    exit;
}

/* ===== обычная загрузка страницы ===== */

// Список заявок
$orders = $pdo->query("SELECT DISTINCT order_number FROM build_plan ORDER BY order_number")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Потребность по заявке</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        h2 {
            margin: 0 0 24px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            color: var(--text);
        }

        .panel {
            max-width: 1200px;
            margin: 0 auto 20px;
            background: var(--card);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: center;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-weight: 500;
            color: var(--text);
            white-space: nowrap;
        }

        select, button {
            padding: 10px 12px;
            font-size: 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--card);
            color: var(--text);
            transition: all 0.2s ease;
            font-family: inherit;
        }

        select:focus, button:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        button {
            cursor: pointer;
            font-weight: 500;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--muted);
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-soft {
            background: #f1f5f9;
            color: var(--accent);
            border: 1px solid var(--border);
        }

        .btn-soft:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }

        #result {
            max-width: 1200px;
            margin: 0 auto;
        }

        .subtitle {
            margin: 16px 0 12px;
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
        }

        .print-header {
            margin-bottom: 8px;
        }

        .print-title-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
        }

        .print-title {
            font-weight: 600;
        }

        .print-order {
            font-weight: 500;
            color: var(--muted);
        }

        .table-wrap {
            overflow-x: auto;
            overflow-y: visible;
            background: var(--card);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
            max-height: 80vh;
            position: relative;
            scrollbar-width: thin;
            scrollbar-color: var(--accent) var(--border);
        }

        .table-wrap::-webkit-scrollbar {
            height: 12px;
        }

        .table-wrap::-webkit-scrollbar-track {
            background: var(--border);
            border-radius: 6px;
        }

        .table-wrap::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 6px;
            border: 2px solid var(--border);
        }

        .table-wrap::-webkit-scrollbar-thumb:hover {
            background: var(--accent-hover);
        }

        /* Индикатор прокрутки */
        .scroll-indicator {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            z-index: 100;
            pointer-events: none;
        }

        table.pivot {
            border-collapse: collapse;
            width: 100%;
            min-width: 640px;
            font-size: 13px;
            table-layout: auto;
        }

        table.pivot th, 
        table.pivot td {
            border: 1px solid var(--border);
            padding: 4px 8px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            min-width: 60px;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        table.pivot tbody td {
            padding: 2px 6px;
            height: 24px;
        }

        table.pivot thead th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--text);
            border-bottom: 2px solid var(--border);
            padding: 8px 12px;
            height: auto;
        }

        .left {
            text-align: left;
            white-space: nowrap;
            min-width: 80px;
            max-width: 150px;
            padding: 4px 8px;
        }

        table.pivot tbody .left {
            padding: 2px 6px;
            height: 24px;
        }

        .nowrap {
            white-space: nowrap;
        }

        /* Специальные стили для ячеек с большими числами */
        table.pivot td[title] {
            position: relative;
            cursor: help;
        }

        table.pivot td[title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--text);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
            pointer-events: none;
        }

        table.pivot td[title]:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(100%);
            border: 5px solid transparent;
            border-top-color: var(--text);
            z-index: 1000;
            pointer-events: none;
        }

        .vertical-date {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            padding: 8px 4px;
            font-size: 11px;
            font-weight: 500;
            height: auto;
        }

        table.pivot td.total {
            background: #f1f5f9;
            font-weight: 600;
            color: var(--text);
        }

        table.pivot tr.foot td {
            background: #e0f2fe;
            font-weight: 600;
            color: var(--text);
            border-top: 2px solid var(--border);
        }

        table.pivot td.grand {
            background: #dcfce7;
            font-weight: 700;
            color: var(--success);
        }

        table.pivot tr.grand-total td {
            background: #fef3c7;
            font-weight: 700;
            color: var(--warning);
            border-top: 3px solid var(--warning);
        }

        table.pivot th.grand-total-header {
            background: #fbbf24;
            color: #92400e;
            font-weight: 700;
            font-size: 12px;
        }

        table.pivot th.position-names-header {
            background: #e0f2fe;
            color: #0369a1;
            font-weight: 700;
            font-size: 12px;
        }

        table.pivot td.grand-total-cell {
            background: #fbbf24;
            color: #92400e;
            font-weight: 800;
            font-size: 14px;
        }

        table.pivot td.position-names-cell {
            background: #f0f9ff;
            color: #0369a1;
            font-weight: 600;
            font-size: 12px;
            text-align: left;
            white-space: nowrap;
            min-width: 100px;
            max-width: 200px;
        }

        tbody tr:nth-child(even) {
            background: #fafbfc;
        }

        tbody tr:hover {
            background: #f1f5f9;
        }

        /* Мобильная версия */
        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            h2 {
                font-size: 20px;
                margin-bottom: 16px;
            }

            .panel {
                padding: 16px;
                gap: 12px;
            }

            .form-group {
                width: 100%;
            }

            select, button {
                width: 100%;
                padding: 12px;
                font-size: 16px;
            }

            .table-wrap {
                padding: 12px;
                margin: 0 -12px 16px;
                border-radius: 0;
                border-left: none;
                border-right: none;
            }

            table.pivot {
                font-size: 12px;
                min-width: 500px;
            }

            table.pivot thead th {
                padding: 6px 8px;
                height: auto;
            }

            table.pivot tbody td {
                padding: 2px 4px;
                height: 22px;
            }

            .vertical-date {
                padding: 6px 3px;
                font-size: 10px;
                height: auto;
            }

            table.pivot tbody .left {
                padding: 2px 4px;
                height: 22px;
            }
        }

        /* Скрываем печатные версии на экране */
        .print-only {
            display: none;
        }

        /* Блок-страница для печати каждой части */
        .sheet {
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        @media print {
            @page { 
                size: A4 landscape; 
                margin: 5mm; 
            }
            
            body {
                background: #fff;
                padding: 0;
            }
            
            .panel {
                display: none !important;
            }
            
            /* Скрываем основную таблицу при печати */
            .table-wrap:not(.print-only .table-wrap) {
                display: none !important;
            }
            
            /* Показываем печатные версии */
            .print-only {
                display: block !important;
            }
            
            .print-title-line {
                font-size: 10px !important;
                margin: 2px 0 2px !important;
                line-height: 1.2 !important;
            }

            .print-title {
                font-weight: 600 !important;
                font-size: 10px !important;
            }

            .print-order {
                font-weight: 500 !important;
                font-size: 9px !important;
                color: #666 !important;
            }

            .print-only .table-wrap {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
                overflow: visible;
                border: none;
                margin-bottom: 0;
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .print-only table.pivot {
                font-size: 10px;
                min-width: 0 !important;
                width: 100% !important;
                table-layout: fixed;
            }
            
            .print-only table.pivot thead th {
                padding: 3px 4px !important;
                height: auto !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-only table.pivot tbody td {
                padding: 1px 2px !important;
                height: 16px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* Фиксированные ширины колонок для печати */
            .print-only table.pivot th:first-child,
            .print-only table.pivot td:first-child {
                width: 8% !important; /* Позиция */
            }

            .print-only table.pivot th:nth-last-child(3),
            .print-only table.pivot td:nth-last-child(3) {
                width: 6% !important; /* Итого */
            }

            .print-only table.pivot th:nth-last-child(2),
            .print-only table.pivot td:nth-last-child(2) {
                width: 8% !important; /* Итого за все страницы */
            }

            .print-only table.pivot th:last-child,
            .print-only table.pivot td:last-child {
                width: 8% !important; /* Дублированная позиция */
            }

            /* Остальные колонки (даты) делят оставшееся место поровну */
            .print-only table.pivot th:not(:first-child):not(:nth-last-child(3)):not(:nth-last-child(2)):not(:last-child),
            .print-only table.pivot td:not(:first-child):not(:nth-last-child(3)):not(:nth-last-child(2)):not(:last-child) {
                width: auto !important;
                min-width: 0 !important;
                max-width: 4% !important;
            }
            
            .print-only .vertical-date {
                padding: 2px 1px !important;
                letter-spacing: 0.1px;
                height: auto !important;
                font-size: 8px !important;
                writing-mode: vertical-rl !important;
                transform: rotate(180deg) !important;
                white-space: nowrap !important;
            }

            .print-only tr.grand-total td {
                background: #fef3c7 !important;
                font-weight: 700 !important;
                color: #92400e !important;
                border-top: 3px solid #f59e0b !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-only th.grand-total-header {
                background: #fbbf24 !important;
                color: #92400e !important;
                font-weight: 700 !important;
                font-size: 10px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-only th.position-names-header {
                background: #e0f2fe !important;
                color: #0369a1 !important;
                font-weight: 700 !important;
                font-size: 10px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-only td.grand-total-cell {
                background: #fbbf24 !important;
                color: #92400e !important;
                font-weight: 800 !important;
                font-size: 11px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-only td.position-names-cell {
                background: #f0f9ff !important;
                color: #0369a1 !important;
                font-weight: 600 !important;
                font-size: 10px !important;
                text-align: left !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

<h2>Потребность комплектующих по заявке</h2>

<div class="panel">
    <div class="form-group">
    <label>Заявка:</label>
    <select id="order">
        <option value="">— выберите —</option>
        <?php foreach ($orders as $o): ?>
            <option value="<?= htmlspecialchars($o) ?>"><?= htmlspecialchars($o) ?></option>
        <?php endforeach; ?>
    </select>
    </div>

    <div class="form-group">
    <label>Тип комплектующих:</label>
    <select id="ctype">
        <option value="">— выберите —</option>
        <option value="box">Коробка индивидуальная</option>
    </select>
    </div>

    <button class="btn-primary" onclick="loadPivot()">📊 Показать потребность</button>
    <button class="btn-secondary" onclick="exportToExcel()" id="exportBtn" disabled>📥 Экспорт Excel</button>
    <button class="btn-soft" onclick="window.print()">🖨️ Печать</button>
</div>

<div id="result"></div>

<script>
    function loadPivot() {
        const order = document.getElementById('order').value;
        const ctype = document.getElementById('ctype').value;
        const chunk = 20; // Фиксированное значение для печати
        
        if (!order) { 
            showNotification('Выберите заявку', 'warning');
            return; 
        }
        if (!ctype) { 
            showNotification('Выберите тип комплектующих', 'warning');
            return; 
        }

        // Показываем индикатор загрузки
        const resultDiv = document.getElementById('result');
        resultDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--muted);"><div style="display: inline-block; width: 20px; height: 20px; border: 2px solid var(--border); border-top: 2px solid var(--accent); border-radius: 50%; animation: spin 1s linear infinite;"></div><br>Загрузка данных...</div>';
        
        // Добавляем CSS для анимации
        if (!document.getElementById('loading-styles')) {
            const style = document.createElement('style');
            style.id = 'loading-styles';
            style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
            document.head.appendChild(style);
        }

        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (this.readyState === 4) {
                if (this.status === 200) {
                    resultDiv.innerHTML = this.responseText;
                    showNotification('Данные успешно загружены', 'success');
                    // Включаем кнопку экспорта
                    document.getElementById('exportBtn').disabled = false;
                    // Инициализируем улучшенную прокрутку
                    initTableScroll();
                } else {
                    resultDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--danger);">Ошибка загрузки: ' + this.status + '</div>';
                    showNotification('Ошибка загрузки данных', 'danger');
                    document.getElementById('exportBtn').disabled = true;
                }
            }
        };
        
        xhr.open('POST', '?ajax=1', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('order=' + encodeURIComponent(order) + '&ctype=' + encodeURIComponent(ctype) + '&chunk=' + encodeURIComponent(chunk));
    }

    function showNotification(message, type = 'info') {
        // Удаляем предыдущие уведомления
        const existing = document.querySelector('.notification');
        if (existing) existing.remove();

        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? 'var(--success)' : type === 'warning' ? 'var(--warning)' : type === 'danger' ? 'var(--danger)' : 'var(--accent)'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            font-weight: 500;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Анимация появления
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Автоматическое скрытие через 3 секунды
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // Добавляем обработчики клавиш для удобства
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            loadPivot();
        }
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });

    // Функция экспорта в Excel
    function exportToExcel() {
        const tables = document.querySelectorAll('table.pivot');
        if (tables.length === 0) {
            showNotification('Нет данных для экспорта', 'warning');
            return;
        }

        let csvContent = '';
        const order = document.getElementById('order').value;
        const ctype = document.getElementById('ctype').value;
        const title = 'индивидуальная коробка';
        
        csvContent += `Заявка ${order}: потребность — ${title}\n\n`;

        tables.forEach((table, tableIndex) => {
            if (tableIndex > 0) csvContent += '\n';
            
            const rows = table.querySelectorAll('tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('th, td');
                const rowData = Array.from(cells).map(cell => {
                    let text = cell.textContent.trim();
                    // Экранируем кавычки и запятые
                    if (text.includes('"') || text.includes(',')) {
                        text = '"' + text.replace(/"/g, '""') + '"';
                    }
                    return text;
                });
                csvContent += rowData.join(',') + '\n';
            });
        });

        // Создаем и скачиваем файл
        const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `потребность_${order}_коробки_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('Файл успешно экспортирован', 'success');
    }

    // Функция инициализации улучшенной прокрутки таблицы
    function initTableScroll() {
        const tableWrap = document.querySelector('.table-wrap');
        if (!tableWrap) return;

        // Добавляем индикатор прокрутки
        const scrollIndicator = document.createElement('div');
        scrollIndicator.className = 'scroll-indicator';
        scrollIndicator.textContent = '← →';
        tableWrap.appendChild(scrollIndicator);

        // Обновляем индикатор при прокрутке
        tableWrap.addEventListener('scroll', function() {
            const scrollLeft = this.scrollLeft;
            const scrollWidth = this.scrollWidth;
            const clientWidth = this.clientWidth;
            const scrollPercent = Math.round((scrollLeft / (scrollWidth - clientWidth)) * 100);
            
            if (scrollWidth > clientWidth) {
                scrollIndicator.textContent = `← ${scrollPercent}% →`;
                scrollIndicator.style.display = 'block';
            } else {
                scrollIndicator.style.display = 'none';
            }
        });

        // Скрываем индикатор, если прокрутка не нужна
        const checkScroll = () => {
            const scrollWidth = tableWrap.scrollWidth;
            const clientWidth = tableWrap.clientWidth;
            if (scrollWidth <= clientWidth) {
                scrollIndicator.style.display = 'none';
            }
        };

        // Проверяем при загрузке и изменении размера окна
        checkScroll();
        window.addEventListener('resize', checkScroll);

        // Добавляем клавиши для прокрутки
        tableWrap.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                this.scrollLeft -= 100;
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                this.scrollLeft += 100;
            }
        });

        // Делаем таблицу фокусируемой для клавиатурной навигации
        tableWrap.setAttribute('tabindex', '0');
    }

    // Автоматическая загрузка при изменении параметров (опционально)
    let autoLoadTimeout;
    document.getElementById('order').addEventListener('change', function() {
        if (this.value && document.getElementById('ctype').value) {
            clearTimeout(autoLoadTimeout);
            autoLoadTimeout = setTimeout(loadPivot, 500);
        }
    });
    
    document.getElementById('ctype').addEventListener('change', function() {
        if (this.value && document.getElementById('order').value) {
            clearTimeout(autoLoadTimeout);
            autoLoadTimeout = setTimeout(loadPivot, 500);
        }
    });
</script>
</body>
</html>
