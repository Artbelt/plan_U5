<?php
// Четкая таблица планирования производства
$order = $_GET['order'] ?? '38-44-25';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Таблица планирования производства</title>
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --cutting: #3b82f6;
            --corrugation: #f59e0b;
            --assembly: #10b981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.4;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--card);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .controls {
            background: var(--card);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .control-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .control-group label {
            font-size: 14px;
            color: var(--muted);
            white-space: nowrap;
        }

        .control-group select {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }

        .table-container {
            background: var(--card);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .production-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .production-table th,
        .production-table td {
            border: 1px solid var(--border);
            padding: 8px 4px;
            text-align: center;
            vertical-align: middle;
        }

        .production-table th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 11px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .filter-column {
            background: #f8fafc;
            font-weight: 600;
            text-align: left;
            padding: 8px 12px;
            min-width: 200px;
            position: sticky;
            left: 0;
            z-index: 5;
        }

        .filter-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .filter-details {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }

        .stage-row {
            font-size: 11px;
            font-weight: 500;
        }

        .stage-cutting {
            background: #dbeafe;
            color: #1e40af;
        }

        .stage-corrugation {
            background: #fef3c7;
            color: #92400e;
        }

        .stage-assembly {
            background: #d1fae5;
            color: #065f46;
        }

        .date-header {
            background: #f8fafc;
            font-size: 10px;
            font-weight: 600;
            min-width: 60px;
            max-width: 60px;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            height: 80px;
        }

        .date-header.today {
            background: #dbeafe;
            color: var(--info);
        }

        .date-header.weekend {
            background: #fef2f2;
            color: var(--danger);
        }

        .cell-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 2px;
        }

        .cell-percent {
            font-size: 10px;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .cell-bar {
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }

        .cell-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .cell-fill.cutting {
            background: var(--cutting);
        }

        .cell-fill.corrugation {
            background: var(--corrugation);
        }

        .cell-fill.assembly {
            background: var(--assembly);
        }

        .cell-fill.completed {
            background: #6b7280;
        }

        .legend {
            background: var(--card);
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .legend h3 {
            font-size: 16px;
            margin-bottom: 12px;
        }

        .legend-items {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-color {
            width: 16px;
            height: 8px;
            border-radius: 4px;
        }

        .legend-cutting { background: var(--cutting); }
        .legend-corrugation { background: var(--corrugation); }
        .legend-assembly { background: var(--assembly); }

        .loading {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }

        .error {
            text-align: center;
            padding: 40px;
            color: var(--danger);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .production-table {
                font-size: 10px;
            }
            
            .filter-column {
                min-width: 150px;
            }
            
            .date-header {
                min-width: 40px;
                max-width: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Таблица планирования производства</h1>
            <p>Заявка <?= htmlspecialchars($order) ?> - Четкая структура по датам и этапам</p>
        </div>

        <div class="controls">
            <div class="control-group">
                <label>Заявка:</label>
                <select id="orderFilter" onchange="changeOrder()">
                    <option value="38-44-25" <?= $order === '38-44-25' ? 'selected' : '' ?>>38-44-25</option>
                    <option value="39-45-26" <?= $order === '39-45-26' ? 'selected' : '' ?>>39-45-26</option>
                </select>
            </div>
        </div>

        <div class="table-container" id="tableContainer">
            <div class="loading">Загрузка данных...</div>
        </div>

        <div class="legend">
            <h3>Легенда</h3>
            <div class="legend-items">
                <div class="legend-item">
                    <div class="legend-color legend-cutting"></div>
                    <span>Порезка бухт</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-corrugation"></div>
                    <span>Гофрирование</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-assembly"></div>
                    <span>Сборка фильтров</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allFilters = [];
        const currentOrder = '<?= $order ?>';

        // Загрузка данных
        async function loadOrderData() {
            try {
                const response = await fetch(`api_get_order_filters.php?order=${currentOrder}`);
                const data = await response.json();
                
                if (data.success) {
                    allFilters = data.filters;
                    renderTable(allFilters);
                } else {
                    document.getElementById('tableContainer').innerHTML = 
                        `<div class="error">Ошибка загрузки: ${data.message}</div>`;
                }
            } catch (error) {
                document.getElementById('tableContainer').innerHTML = 
                    `<div class="error">Ошибка: ${error.message}</div>`;
            }
        }

        function renderTable(filters) {
            const container = document.getElementById('tableContainer');
            
            if (filters.length === 0) {
                container.innerHTML = '<div class="loading">Нет данных по заявке</div>';
                return;
            }

            // Генерируем даты
            const dates = generateDates();
            
            container.innerHTML = `
                <table class="production-table">
                    <thead>
                        <tr>
                            <th class="filter-column">Фильтр</th>
                            ${dates.map(date => `
                                <th class="date-header ${getDateClass(date)}">
                                    ${formatDate(date)}
                                </th>
                            `).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${filters.map(filter => renderFilterRows(filter, dates)).join('')}
                    </tbody>
                </table>
            `;
        }

        function renderFilterRows(filter, dates) {
            return `
                <tr>
                    <td class="filter-column" rowspan="3">
                        <div class="filter-name">${filter.filter}</div>
                        <div class="filter-details">${filter.plan_count} шт • ${filter.height || '?'}мм</div>
                    </td>
                    ${dates.map(date => `
                        <td class="stage-row stage-cutting">
                            ${renderCellContent('cutting', filter.progress.cutting, date)}
                        </td>
                    `).join('')}
                </tr>
                <tr>
                    ${dates.map(date => `
                        <td class="stage-row stage-corrugation">
                            ${renderCellContent('corrugation', filter.progress.corrugation, date)}
                        </td>
                    `).join('')}
                </tr>
                <tr>
                    ${dates.map(date => `
                        <td class="stage-row stage-assembly">
                            ${renderCellContent('assembly', filter.progress.assembly, date)}
                        </td>
                    `).join('')}
                </tr>
            `;
        }

        function renderCellContent(stage, progress, date) {
            const isCompleted = progress.percent >= 100;
            const colorClass = isCompleted ? 'completed' : stage;
            
            return `
                <div class="cell-content">
                    <div class="cell-percent">${progress.percent}%</div>
                    <div class="cell-bar">
                        <div class="cell-fill ${colorClass}" style="width: ${progress.percent}%"></div>
                    </div>
                </div>
            `;
        }

        function generateDates() {
            const dates = [];
            const today = new Date();
            const startDate = new Date(today);
            startDate.setDate(today.getDate() - 7); // Начинаем с недели назад
            
            for (let i = 0; i < 21; i++) { // 3 недели
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);
                dates.push(date);
            }
            
            return dates;
        }

        function formatDate(date) {
            return `${date.getDate()}.${date.getMonth() + 1}`;
        }

        function getDateClass(date) {
            const today = new Date();
            if (date.toDateString() === today.toDateString()) {
                return 'today';
            }
            if (date.getDay() === 0 || date.getDay() === 6) {
                return 'weekend';
            }
            return '';
        }

        function changeOrder() {
            const newOrder = document.getElementById('orderFilter').value;
            window.location.href = `?order=${newOrder}`;
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', () => {
            loadOrderData();
        });
    </script>
</body>
</html>

