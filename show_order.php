<?php
// Получаем номер заявки для заголовка
$order_number = $_POST['order_number'] ?? '';
$page_title = $order_number ? $order_number : "Заявка";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <style>
        /* ===== Modern UI palette (to match main.php) ===== */
        :root{
            --bg:#f6f7f9;
            --panel:#ffffff;
            --ink:#1e293b;
            --muted:#64748b;
            --border:#e2e8f0;
            --accent:#667eea;
            --radius:14px;
            --shadow:0 10px 25px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.06);
            --shadow-soft:0 2px 8px rgba(0,0,0,0.08);
        }
        html,body{height:100%}
        body{
            margin:0; background:var(--bg); color:var(--ink);
            font: 16px/1.6 "Inter","Segoe UI", Arial, sans-serif;
            -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
        }

        .container{ max-width:1200px; margin:0 auto; padding:16px; }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: max-content;
            max-width: 400px;
            background-color: #333;
            color: #fff;
            text-align: left;
            padding: 5px 10px;
            border-radius: 6px;
            position: absolute;
            z-index: 10;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            white-space: pre-line;
        }
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Индикатор загрузки */
        #loading {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15,23,42,0.25);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            color: #fff;
            font-weight: bold;
        }
        .spinner {
            border: 8px solid rgba(255,255,255,0.3);
            border-top: 8px solid #fff;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-text {
            font-size: 20px;
            color: #fff;
        }

        /* Таблица */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 16px;
            background: var(--panel);
            border:1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }
        th, td {
            border-bottom: 1px solid var(--border);
            padding: 10px 12px;
            text-align: center;
            color: var(--ink);
        }
        tr:last-child td{ border-bottom: 0; }
        thead th{
            background:#f8fafc;
            font-weight:600;
        }
        h3{ margin:0; font-size:18px; font-weight:700; }

        /* Buttons */
        input[type='submit'], .btn{
            appearance:none; cursor:pointer; border:none; color:#fff;
            background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            padding: 10px 16px; border-radius: 10px; font-weight:600; box-shadow: var(--shadow-soft);
            transition: transform .15s ease, box-shadow .2s ease, filter .2s ease;
        }
        input[type='submit']:hover, .btn:hover{ transform: translateY(-1px); box-shadow: var(--shadow); filter: brightness(1.05); }
        input[type='submit']:active, .btn:active{ transform: translateY(0); }

        /* Responsive table */
        .table-wrap{ overflow:auto; border-radius: var(--radius); box-shadow: var(--shadow); }
        @media (max-width: 900px){
            .container{ padding:16px; }
            table{ font-size:13px; }
            th, td{ padding: 8px 10px; }
        }

        /* Modal styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            margin: auto;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .close {
            color: #9ca3af;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover,
        .close:focus {
            color: #374151;
        }

        .modal-body {
            padding: 12px 16px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        /* Стили для модального окна параметров фильтра */
        .modal-body .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .modal-body .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 900px) {
            .modal-body .row { 
                grid-template-columns: 1fr; 
            }
        }

        .modal-body .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .modal-body .table th, 
        .modal-body .table td {
            border-bottom: 1px solid var(--border);
            padding: 6px 4px;
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }

        .modal-body .table th { 
            width: 35%; 
            color: var(--muted); 
            font-weight: 600; 
        }

        .modal-body .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            border: 1px solid var(--border);
            background: #fafafa;
        }

        .modal-body .yn-yes { 
            color: #2e7d32; 
            font-weight: 600; 
        }

        .modal-body .yn-no { 
            color: #c62828; 
            font-weight: 600; 
        }

        .modal-body .section-title { 
            font-size: 13px; 
            font-weight: 700; 
            margin: 0 0 6px; 
        }

        .modal-body .small { 
            font-size: 11px; 
            color: var(--muted); 
        }

        .modal-body .value-mono { 
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; 
        }

        .modal-body .pair { 
            display: flex; 
            gap: 8px; 
            align-items: center; 
            flex-wrap: wrap; 
        }
        
        /* Анимация спиннера */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .zero-position-item {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 6px 10px;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .zero-position-info {
            flex: 1;
        }

        .zero-position-filter {
            font-weight: 600;
            color: #92400e;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .zero-position-planned {
            color: #6b7280;
            font-size: 0.8rem;
            font-weight: normal;
        }

        .zero-position-details {
            color: #6b7280;
            font-size: 0.75rem;
            margin-top: 2px;
        }

        .zero-position-count {
            background: #f59e0b;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .no-zero-positions {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 1rem;
        }

        .no-zero-positions .icon {
            font-size: 2rem;
            margin-bottom: 8px;
            display: block;
        }

        .zero-positions-header {
            margin: 0 0 12px 0;
            font-size: 1rem;
            color: #374151;
            font-weight: 600;
        }

        /* Мобильная адаптация для модального окна */
        @media (max-width: 768px) {
            .modal-content {
                max-width: 95%;
                max-height: 85vh;
            }

            .modal-header {
                padding: 10px 12px;
            }

            .modal-title {
                font-size: 1.1rem;
            }

            .close {
                font-size: 20px;
            }

            .modal-body {
                padding: 10px 12px;
            }

            .zero-position-item {
                padding: 5px 8px;
                margin-bottom: 3px;
            }

            .zero-position-filter {
                font-size: 0.9rem;
                gap: 6px;
            }

            .zero-position-planned {
                font-size: 0.75rem;
            }

            .zero-position-details {
                font-size: 0.7rem;
            }

            .zero-position-count {
                padding: 3px 6px;
                font-size: 0.8rem;
            }

            .zero-positions-header {
                font-size: 0.9rem;
                margin-bottom: 8px;
            }
        }
    </style>
</head>

<body>

<div id="loading">
    <div class="spinner"></div>
    <div class="loading-text">Загрузка...</div>
</div>

<div class="container">
    <?php
    require('tools/tools.php');
    require('settings.php');
    require('style/table.txt');

    /**
     * Рендер ячейки с тултипом по датам.
     * $dateList — массив вида [дата1, кол-во1, дата2, кол-во2, ...]
     * $totalQty — итоговое число, которое показываем в самой ячейке
     */
    function renderTooltipCell($dateList, $totalQty) {
        if (empty($dateList)) {
            return "<td>$totalQty</td>";
        }
        $tooltip = '';
        for ($i = 0; $i < count($dateList); $i += 2) {
            $tooltip .= $dateList[$i] . ' — ' . $dateList[$i + 1] . " шт\n";
        }
        return "<td><div class='tooltip'>$totalQty<span class='tooltiptext'>".htmlspecialchars(trim($tooltip))."</span></div></td>";
    }

    /**
     * Грузим FАCT гофропакетов из corrugation_plan:
     * - по заявке и фильтру
     * - суммируем fact_count
     * - для тултипа возвращаем разбивку по plan_date (по каждой строке плана, где fact_count>0)
     *
     * Возвращает [ $dateList, $totalFact ] как в renderTooltipCell
     */
    function normalize_filter_label($label) {
        $pos = mb_strpos($label, ' [');
        if ($pos !== false) {
            return trim(mb_substr($label, 0, $pos));
        }
        return trim($label);
    }

    function get_corr_fact_for_filter(PDO $pdo, string $orderNumber, string $filterLabel): array {
        $filterLabel = normalize_filter_label($filterLabel);

        $stmt = $pdo->prepare("
        SELECT plan_date, COALESCE(fact_count,0) AS fact_count
        FROM corrugation_plan
        WHERE order_number = ?
          AND TRIM(SUBSTRING_INDEX(filter_label, ' [', 1)) = ?
          AND COALESCE(fact_count,0) > 0
        ORDER BY plan_date
    ");
        $stmt->execute([$orderNumber, $filterLabel]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dateList = [];
        $total = 0;
        foreach ($rows as $r) {
            $dateList[] = $r['plan_date'];
            $dateList[] = (int)$r['fact_count'];
            $total += (int)$r['fact_count'];
        }
        return [$dateList, $total];
    }

    /**
     * Получает конструктивные параметры фильтра из salon_filter_structure и paper_package_salon
     */
    function get_filter_structure($filter_name) {
        global $mysql_host, $mysql_user, $mysql_user_pass, $mysql_database;
        
        try {
            $pdo = new PDO("mysql:host=$mysql_host;dbname=$mysql_database", $mysql_user, $mysql_user_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("
                SELECT 
                    sfs.*,
                    pps.p_p_height as height,
                    pps.p_p_width as width,
                    pps.p_p_pleats_count as ribs_count,
                    pps.p_p_material as material
                FROM salon_filter_structure sfs
                LEFT JOIN paper_package_salon pps ON CONCAT('гофропакет ', sfs.filter) = pps.p_p_name
                WHERE sfs.filter = ?
            ");
            $stmt->execute([$filter_name]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    // Номер заявки уже получен в начале файла

    // Подключим отдельный PDO для выборок из corrugation_plan (факт гофропакетов)
    $pdo_corr = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "");
    $pdo_corr->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Загружаем заявку (как и раньше)
    $result = show_order($order_number);

    // Инициализация счётчиков
    $filter_count_in_order = 0;   // всего фильтров по заявке (план)
    $filter_count_produced = 0;   // Всего изготовлено готовых фильтров (факт) — из select_produced_filters_by_order
    $count = 0;                   // номер п/п
    $corr_fact_summ = 0;          // суммарно изготовлено гофропакетов по всей заявке (из corrugation_plan)

    // Отрисовка таблицы
    echo "<h3>Заявка: ".htmlspecialchars($order_number)."</h3>";
    echo "<div class='table-wrap'>";
    echo "<table id='order_table'>";
    echo "<tr>
        <th>I</th>
        <th>Фильтр</th>
        <th>Количество, шт</th>
        <th>Маркировка</th>
        <th>Упаковка инд.</th>
        <th>Этикетка инд.</th>
        <th>Упаковка групп.</th>
        <th>Норма упаковки</th>
        <th>Этикетка групп.</th>
        <th>Примечание</th>
        <th>Изготовлено, шт</th>
        <th>Остаток, шт</th>
        <th>Изготовленные гофропакеты, шт</th>
      </tr>";

    while ($row = $result->fetch_assoc()) {
        $count++;

        // Готовые фильтры по заявке/фильтру (как было)
        $prod_info = select_produced_filters_by_order($row['filter'], $order_number);
        $date_list_filters = $prod_info[0]; // массив дат/кол-в
        $total_qty_filters = $prod_info[1]; // итог изготовлено фильтров

        $filter_count_in_order += (int)$row['count'];
        $filter_count_produced += $total_qty_filters;

        $difference = (int)$row['count'] - $total_qty_filters;

        // Гофропакеты: теперь из corrugation_plan.fact_count
        list($corr_date_list, $corr_total) = get_corr_fact_for_filter($pdo_corr, $order_number, $row['filter']);
        $corr_fact_summ += (int)$corr_total;

        // Получаем информацию о структуре фильтра
        $filter_structure = get_filter_structure($row['filter']);
        $has_structure = $filter_structure !== false;
        
        echo "<tr>
        <td style='text-align: center;'>
            <button onclick='showFilterInfo(\"".htmlspecialchars($row['filter'])."\")' 
                    style='background: white; color: #3b82f6; border: 1px solid #3b82f6; border-radius: 50%; padding: 4px; cursor: pointer; font-weight: bold; font-size: 11px; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto;'
                    title='Информация о фильтре'>
                i
            </button>
        </td>
        <td>".htmlspecialchars($row['filter'])."</td>
        <td>".(int)$row['count']."</td>
        <td>".htmlspecialchars($row['marking'])."</td>
        <td>".htmlspecialchars($row['personal_packaging'])."</td>
        <td>".htmlspecialchars($row['personal_label'])."</td>
        <td>".htmlspecialchars($row['group_packaging'])."</td>
        <td>".htmlspecialchars($row['packaging_rate'])."</td>
        <td>".htmlspecialchars($row['group_label'])."</td>
        <td>".htmlspecialchars($row['remark'])."</td>";

        // Колонка «Изготовлено, шт» — готовые фильтры с тултипом по датам (как было)
        echo renderTooltipCell($date_list_filters, $total_qty_filters);

        // Остаток по фильтрам
        echo "<td>".(int)$difference."</td>";

        // Новая логика «Изготовленные гофропакеты, шт» — из corrugation_plan.fact_count (+ тултип по plan_date)
        echo renderTooltipCell($corr_date_list, (int)$corr_total);

        echo "</tr>";
    }

    // Итоговая строка
    $summ_difference = $filter_count_in_order - $filter_count_produced;

    echo "<tr>
        <td></td>
        <td>Итого:</td>
        <td>".(int)$filter_count_in_order."</td>
        <td colspan='7'></td>
        <td>".(int)$filter_count_produced."</td>
        <td>".(int)$summ_difference."*</td>
        <td>".(int)$corr_fact_summ."*</td>
      </tr>";

    echo "</table>";
    echo "</div>";
    echo "<p>* - без учета перевыполнения</p>";
    ?>

    <br>
    <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
        <button onclick="showZeroProductionPositions()" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            ⚠️ Позиции выпуск которых = 0
        </button>
        <button onclick="checkGofraPackages()" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);">
            🔍 Проверка гофропакетов
        </button>
        <button onclick="openWorkersSpecification()" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
            👷 Спецификация для рабочих
        </button>
        <form action='hiding_order.php' method='post' style="margin: 0;">
            <input type='hidden' name='order_number' value='<?= htmlspecialchars($order_number) ?>'>
            <input type='submit' value='Отправить заявку в архив'>
        </form>
    </div>

</div>

<!-- Модальное окно для позиций с нулевым выпуском -->
<div id="zeroProductionModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2 class="modal-title">⚠️ Позиции с нулевым выпуском</h2>
            <span class="close" onclick="closeZeroProductionModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="zeroProductionContent">
                <p>Загрузка данных...</p>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для проверки гофропакетов -->
<div id="gofraCheckModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2 class="modal-title">🔍 Проверка гофропакетов</h2>
            <div style="display: flex; gap: 10px;">
                <button onclick="printGofraCheck()" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                    🖨️ Печать
                </button>
                <span class="close" onclick="closeGofraCheckModal()">&times;</span>
            </div>
        </div>
        <div class="modal-body">
            <!-- Фильтры для типов проблем -->
            <div id="gofraFilters" style="margin-bottom: 15px; padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                <div style="font-weight: bold; margin-bottom: 8px; color: #374151;">🔍 Фильтр по типу проблемы:</div>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input type="checkbox" id="filterNoGofra" checked style="margin: 0;">
                        <span style="color: #dc2626; font-weight: bold;">Нет гофропакетов</span>
                        <span style="color: #64748b; font-size: 12px;">(0 гофропакетов, но есть выпуск)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input type="checkbox" id="filterShortage" checked style="margin: 0;">
                        <span style="color: #f59e0b; font-weight: bold;">Недостаток</span>
                        <span style="color: #64748b; font-size: 12px;">(недостаток ≥ 20 штук)</span>
                    </label>
                </div>
            </div>
            <div id="gofraCheckContent">
                <p>Загрузка данных...</p>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для информации о фильтре -->
<div id="filterInfoModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px; max-height: 70vh; overflow-y: auto; padding: 20px;">
        <span class="close" onclick="closeFilterInfoModal()" style="position: absolute; top: 10px; right: 20px; color: #9ca3af; font-size: 28px; cursor: pointer;">&times;</span>
        <div id="filterInfoContent">
            <p style="color: #9ca3af; text-align: center; padding: 20px;">
                Загрузка данных...
            </p>
        </div>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        document.getElementById('loading').style.display = 'none';
    });

    // Функция для показа модального окна с позициями нулевого выпуска
    function showZeroProductionPositions() {
        const modal = document.getElementById('zeroProductionModal');
        const content = document.getElementById('zeroProductionContent');
        
        // Показываем модальное окно
        modal.style.display = 'flex';
        
        // Загружаем данные
        loadZeroProductionData();
    }

    // Функция для закрытия модального окна
    function closeZeroProductionModal() {
        document.getElementById('zeroProductionModal').style.display = 'none';
    }

    // Функция для загрузки данных о позициях с нулевым выпуском
    function loadZeroProductionData() {
        const content = document.getElementById('zeroProductionContent');
        content.innerHTML = '<p>Загрузка данных...</p>';
        
        // Получаем данные из таблицы на странице
        const table = document.getElementById('order_table');
        const rows = table.querySelectorAll('tr');
        const zeroPositions = [];
        
        // Пропускаем заголовок и итоговую строку
        for (let i = 1; i < rows.length - 1; i++) {
            const row = rows[i];
            const cells = row.querySelectorAll('td');
            
            if (cells.length >= 12) {
                const filter = cells[1].textContent.trim();
                const plannedCount = parseInt(cells[2].textContent) || 0;
                const producedCount = parseInt(cells[10].textContent) || 0;
                const remark = cells[9].textContent.trim();
                
                if (producedCount === 0 && plannedCount > 0) {
                    zeroPositions.push({
                        filter: filter,
                        plannedCount: plannedCount,
                        producedCount: producedCount,
                        remark: remark
                    });
                }
            }
        }
        
        // Отображаем результаты
        displayZeroPositions(zeroPositions);
    }

    // Функция для отображения позиций с нулевым выпуском
    function displayZeroPositions(positions) {
        const content = document.getElementById('zeroProductionContent');
        
        if (positions.length === 0) {
            content.innerHTML = `
                <div class="no-zero-positions">
                    <span class="icon">✅</span>
                    <p>Отлично! Все позиции имеют выпуск больше 0</p>
                </div>
            `;
            return;
        }
        
        let html = `<div class="zero-positions-header">Найдено позиций с нулевым выпуском: ${positions.length}</div>`;
        
        positions.forEach((position, index) => {
            html += `
                <div class="zero-position-item">
                    <div class="zero-position-info">
                        <div class="zero-position-filter">
                            ${position.filter}
                            <span class="zero-position-planned">(${position.plannedCount} шт)</span>
                        </div>
                        ${position.remark ? `<div class="zero-position-details">Примечание: ${position.remark}</div>` : ''}
                    </div>
                    <div class="zero-position-count">0 шт</div>
                </div>
            `;
        });
        
        content.innerHTML = html;
    }

    // Функция для открытия спецификации для рабочих
    function openWorkersSpecification() {
        const orderNumber = '<?= htmlspecialchars($order_number) ?>';
        
        // Создаем форму для POST запроса
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'show_order_for_workers.php';
        form.target = '_blank';
        
        // Добавляем скрытое поле с номером заявки
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'order_number';
        input.value = orderNumber;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    // Функция для проверки гофропакетов
    function checkGofraPackages() {
        const modal = document.getElementById('gofraCheckModal');
        const content = document.getElementById('gofraCheckContent');
        
        // Показываем модальное окно
        modal.style.display = 'flex';
        
        // Загружаем данные
        loadGofraCheckData();
        
        // Добавляем обработчики событий для фильтров
        const filterNoGofra = document.getElementById('filterNoGofra');
        const filterShortage = document.getElementById('filterShortage');
        
        // Удаляем старые обработчики, если они есть
        filterNoGofra.removeEventListener('change', loadGofraCheckData);
        filterShortage.removeEventListener('change', loadGofraCheckData);
        
        // Добавляем новые обработчики
        filterNoGofra.addEventListener('change', loadGofraCheckData);
        filterShortage.addEventListener('change', loadGofraCheckData);
    }

    // Функция для закрытия модального окна проверки гофропакетов
    function closeGofraCheckModal() {
        document.getElementById('gofraCheckModal').style.display = 'none';
    }

    // Функция для печати таблицы проверки гофропакетов
    function printGofraCheck() {
        const orderNumber = '<?= htmlspecialchars($order_number) ?>';
        const content = document.getElementById('gofraCheckContent');
        
        // Создаем новое окно для печати
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        
        // Формируем HTML для печати
        const printHTML = `
            <!DOCTYPE html>
            <html lang="ru">
            <head>
                <meta charset="UTF-8">
                <title>Проверка гофропакетов - ${orderNumber}</title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        margin: 20px; 
                        font-size: 12px;
                        line-height: 1.4;
                    }
                    h1 { 
                        color: #dc2626; 
                        text-align: center; 
                        margin-bottom: 20px;
                        font-size: 18px;
                    }
                    h2 { 
                        color: #374151; 
                        margin: 15px 0 10px 0;
                        font-size: 14px;
                    }
                    table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        margin-bottom: 20px;
                        font-size: 11px;
                    }
                    th, td { 
                        border: 1px solid #374151; 
                        padding: 6px; 
                        text-align: center;
                    }
                    th { 
                        background-color: #f3f4f6; 
                        font-weight: bold;
                    }
                    .no-problems { 
                        text-align: center; 
                        color: #10b981; 
                        font-weight: bold;
                        padding: 20px;
                    }
                    .problem-count { 
                        color: #dc2626; 
                        font-weight: bold; 
                        margin-bottom: 10px;
                    }
                    .description { 
                        color: #6b7280; 
                        margin-bottom: 15px;
                        font-size: 10px;
                    }
                    @media print {
                        body { margin: 0; }
                        h1 { font-size: 16px; }
                    }
                </style>
            </head>
            <body>
                <h1>🔍 Проверка гофропакетов</h1>
                <h2>Заявка: ${orderNumber}</h2>
                <p style="color: #6b7280; font-size: 11px;">Дата проверки: ${new Date().toLocaleDateString('ru-RU')}</p>
                <p style="color: #374151; font-size: 11px; margin: 10px 0;">Проверяются позиции с проблемами гофропакетов:</p>
                <ul style="color: #6b7280; font-size: 10px; margin: 5px 0 15px 0;">
                    <li>• Гофропакетов = 0, но выпущено фильтров > 0</li>
                    <li>• Недостаток гофропакетов ≥ 20 штук</li>
                </ul>
                
                ${content.innerHTML}
                
                <div style="margin-top: 30px; font-size: 10px; color: #6b7280; text-align: center;">
                    Документ сформирован автоматически системой планирования производства
                </div>
            </body>
            </html>
        `;
        
        // Записываем HTML в новое окно
        printWindow.document.write(printHTML);
        printWindow.document.close();
        
        // Ждем загрузки и открываем диалог печати
        printWindow.onload = function() {
            printWindow.focus();
            printWindow.print();
        };
    }

    // Функция для загрузки данных о гофропакетах
    function loadGofraCheckData() {
        const content = document.getElementById('gofraCheckContent');
        content.innerHTML = '<p>Загрузка данных...</p>';
        
        // Получаем настройки фильтров
        const showNoGofra = document.getElementById('filterNoGofra').checked;
        const showShortage = document.getElementById('filterShortage').checked;
        
        // Получаем данные из таблицы на странице
        const table = document.getElementById('order_table');
        const rows = table.querySelectorAll('tr');
        const problemPositions = [];
        
        // Пропускаем заголовок и итоговую строку
        for (let i = 1; i < rows.length - 1; i++) {
            const row = rows[i];
            const cells = row.querySelectorAll('td');
            
            if (cells.length >= 13) {
                const num = cells[0].textContent.trim();
                const filter = cells[1].textContent.trim();
                const plan = cells[2].textContent.trim();
                // Извлекаем только число из ячейки, игнорируя тултип
                // Ищем первый элемент с текстом (число) в ячейке
                const producedElement = cells[10].querySelector('.tooltip') || cells[10];
                const gofraElement = cells[12].querySelector('.tooltip') || cells[12];
                
                const produced = producedElement.firstChild ? producedElement.firstChild.textContent.trim() : cells[10].textContent.trim();
                const gofra = gofraElement.firstChild ? gofraElement.firstChild.textContent.trim() : cells[12].textContent.trim();
                
                const gofraCount = parseInt(gofra) || 0;
                const producedCount = parseInt(produced) || 0;
                const shortage = Math.max(0, producedCount - gofraCount);
                
                // Определяем тип проблемы и проверяем фильтры
                let problemType = '';
                let shouldShow = false;
                
                if (gofraCount === 0 && producedCount > 0) {
                    problemType = 'Нет гофропакетов';
                    shouldShow = showNoGofra;
                } else if (gofraCount < producedCount && producedCount > 0 && shortage >= 20) {
                    problemType = 'Недостаток';
                    shouldShow = showShortage;
                }
                
                if (shouldShow) {
                    problemPositions.push({
                        num: num,
                        filter: filter,
                        plan: plan,
                        produced: producedCount,
                        gofra: gofraCount,
                        problemType: problemType,
                        shortage: shortage
                    });
                }
            }
        }
        
        // Формируем HTML с результатами
        if (problemPositions.length === 0) {
            let message = '';
            if (!showNoGofra && !showShortage) {
                message = 'Выберите хотя бы один тип проблемы для отображения.';
            } else {
                message = 'Для выбранных типов проблем ничего не найдено.';
            }
            
            content.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <p style="color: #10b981; font-size: 18px; font-weight: bold;">✅ ${message}</p>
                    <p style="color: #64748b;">Проверьте настройки фильтров или убедитесь, что данные корректны.</p>
                </div>
            `;
        } else {
            // Формируем список активных фильтров
            let activeFilters = [];
            if (showNoGofra) activeFilters.push('Гофропакетов = 0, но выпущено фильтров > 0');
            if (showShortage) activeFilters.push('Недостаток гофропакетов ≥ 20 штук');
            
            let html = `
                <div style="margin-bottom: 10px;">
                    <p style="color: #dc2626; font-weight: bold;">⚠️ Обнаружено проблемных позиций: ${problemPositions.length}</p>
                    <p style="color: #64748b; font-size: 14px;">Активные фильтры:</p>
                    <ul style="color: #64748b; font-size: 13px; margin: 5px 0;">
                        ${activeFilters.map(filter => `<li>• ${filter}</li>`).join('')}
                    </ul>
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr style="background: #f1f5f9;">
                        <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left;">№</th>
                        <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: left;">Фильтр</th>
                        <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">План, шт</th>
                        <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">Выпущено фильтров, шт</th>
                        <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">Гофропакетов, шт</th>
                        <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">Недостаток, шт</th>
                        <th style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">Тип проблемы</th>
                    </tr>
            `;
            
            problemPositions.forEach(pos => {
                // Цвет для типа проблемы
                let typeColor = pos.problemType === 'Нет гофропакетов' ? '#dc2626' : '#f59e0b';
                let typeBg = pos.problemType === 'Нет гофропакетов' ? '#fee2e2' : '#fef3c7';
                
                html += `
                    <tr>
                        <td style="padding: 8px; border: 1px solid #e2e8f0;">${pos.num}</td>
                        <td style="padding: 8px; border: 1px solid #e2e8f0;">${pos.filter}</td>
                        <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center;">${pos.plan}</td>
                        <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center; color: #10b981; font-weight: bold;">${pos.produced}</td>
                        <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center; color: #dc2626; font-weight: bold;">${pos.gofra}</td>
                        <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center; color: #dc2626; font-weight: bold;">${pos.shortage}</td>
                        <td style="padding: 8px; border: 1px solid #e2e8f0; text-align: center; background: ${typeBg}; color: ${typeColor}; font-weight: bold; font-size: 12px;">${pos.problemType}</td>
                    </tr>
                `;
            });
            
            html += '</table>';
            content.innerHTML = html;
        }
    }

    // Функция для показа информации о фильтре
    function showFilterInfo(filterName) {
        const modal = document.getElementById('filterInfoModal');
        const content = document.getElementById('filterInfoContent');
        
        // Показываем модальное окно
        modal.style.display = 'flex';
        
        // Загружаем данные о фильтре
        loadFilterInfo(filterName);
    }

    // Функция для закрытия модального окна информации о фильтре
    function closeFilterInfoModal() {
        document.getElementById('filterInfoModal').style.display = 'none';
    }

    // Функция для загрузки информации о фильтре
    function loadFilterInfo(filterName) {
        const content = document.getElementById('filterInfoContent');
        content.innerHTML = '<div style="text-align: center; padding: 20px;"><div style="display: inline-block; width: 20px; height: 20px; border: 2px solid var(--border); border-top: 2px solid var(--accent); border-radius: 50%; animation: spin 1s linear infinite;"></div><br>Загрузка параметров...</div>';
        
        // Создаем AJAX запрос для получения данных о фильтре
        fetch('get_filter_structure.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'filter_name=' + encodeURIComponent(filterName)
        })
        .then(response => response.text())
        .then(html => {
            // Извлекаем только содержимое body из ответа
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const bodyContent = doc.body.innerHTML;
            content.innerHTML = bodyContent;
        })
        .catch(error => {
            content.innerHTML = '<p style="color: red; text-align: center; padding: 20px;">Ошибка загрузки данных: ' + error.message + '</p>';
        });
    }

    // Закрытие модального окна при клике вне его
    window.onclick = function(event) {
        const zeroModal = document.getElementById('zeroProductionModal');
        const gofraModal = document.getElementById('gofraCheckModal');
        const filterModal = document.getElementById('filterInfoModal');
        
        if (event.target === zeroModal) {
            closeZeroProductionModal();
        }
        if (event.target === gofraModal) {
            closeGofraCheckModal();
        }
        if (event.target === filterModal) {
            closeFilterInfoModal();
        }
    }
</script>

</body>
</html>
