<?php
// Реальная диаграмма Ганта с датами из БД
$order = $_GET['order'] ?? '38-44-25';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Диаграмма Ганта - Реальное планирование</title>
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
            --cutting: #10b981;
            --corrugation: #3b82f6;
            --assembly: #f59e0b;
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

        .gantt-container {
            background: var(--card);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .gantt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .gantt-table th,
        .gantt-table td {
            border: 1px solid var(--border);
            padding: 4px 2px;
            text-align: center;
            vertical-align: middle;
        }

        .gantt-table th {
            background: #f8fafc;
            font-weight: 600;
            font-size: 10px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .filter-column {
            background: #f8fafc;
            font-weight: 600;
            text-align: left;
            padding: 8px 12px;
            min-width: 120px;
            position: sticky;
            left: 0;
            z-index: 5;
        }

        .filter-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--text);
        }

        .filter-details {
            font-size: 10px;
            color: var(--muted);
            margin-top: 2px;
        }

        .stage-row {
            font-size: 10px;
            font-weight: 500;
            height: 4px;
            text-align: center;
            vertical-align: middle;
        }

        .date-header {
            background: #f8fafc;
            font-size: 9px;
            font-weight: 600;
            min-width: 30px;
            max-width: 30px;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            height: 60px;
        }

        .date-header.today {
            background: #dbeafe;
            color: var(--info);
        }

        .date-header.weekend {
            background: #fef2f2;
            color: var(--danger);
        }

        .task-cells-container {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .task-cell {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 0;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 4px;
        }

        .task-cell:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            z-index: 5;
        }

        .task-cutting {
            background: #10b981;
        }

        .task-corrugation {
            background: #3b82f6;
        }

        .task-assembly {
            background: #f59e0b;
        }

        .task-completed {
            background: #6b7280;
            opacity: 0.8;
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
            width: 20px;
            height: 12px;
            border-radius: 2px;
        }

        .legend-cutting { background: #10b981; }
        .legend-corrugation { background: #3b82f6; }
        .legend-assembly { background: #f59e0b; }

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
            
            .gantt-table {
                font-size: 10px;
            }
            
            .filter-column {
                min-width: 100px;
            }
            
            .date-header {
                min-width: 25px;
                max-width: 25px;
            }
            
            .task-cell {
                width: 100%;
                height: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Диаграмма Ганта - Реальное планирование</h1>
            <p>Заявка <?= htmlspecialchars($order) ?> - Планирование по реальным датам</p>
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

        <div class="gantt-container" id="ganttContainer">
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
        let allDates = [];
        const currentOrder = '<?= $order ?>';

        // Загрузка данных
        async function loadOrderData() {
            try {
                const response = await fetch(`api_get_gantt_data.php?order=${currentOrder}`);
                const data = await response.json();
                
                if (data.success) {
                    allFilters = data.filters;
                    allDates = data.dates;
                    
                    // Отладка
                    console.log('Loaded filters:', allFilters);
                    console.log('Loaded dates:', allDates);
                    
                    renderGanttChart(allFilters, allDates);
                } else {
                    document.getElementById('ganttContainer').innerHTML = 
                        `<div class="error">Ошибка загрузки: ${data.message}</div>`;
                }
            } catch (error) {
                document.getElementById('ganttContainer').innerHTML = 
                    `<div class="error">Ошибка: ${error.message}</div>`;
            }
        }

        function renderGanttChart(filters, dates) {
            const container = document.getElementById('ganttContainer');
            
            if (filters.length === 0) {
                container.innerHTML = '<div class="loading">Нет данных по заявке</div>';
                return;
            }

            container.innerHTML = `
                <table class="gantt-table">
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
                    <td class="filter-column">
                        <div class="filter-name">${filter.filter}</div>
                        <div class="filter-details">${filter.plan_count} шт</div>
                    </td>
                    ${dates.map(date => `
                        <td class="stage-row">
                            ${renderAllStagesInCell(filter, date)}
                        </td>
                    `).join('')}
                </tr>
            `;
        }

        function renderAllStagesInCell(filter, date) {
            const stages = ['cutting', 'corrugation', 'assembly'];
            
            // Находим первый этап, который есть на эту дату
            for (const stage of stages) {
                const hasTask = hasTaskOnDate(stage, filter, date);
                if (hasTask) {
                    const isCompleted = filter.stages[stage].completed;
                    const taskClass = isCompleted ? 'task-completed' : `task-${stage}`;
                    
                    return `
                        <div class="task-cells-container">
                            <div class="task-cell ${taskClass}" 
                                 title="${getStageName(stage)}: ${date}"
                                 onclick="showTaskDetails('${filter.filter}', '${stage}', '${date}')">
                            </div>
                        </div>
                    `;
                }
            }
            
            // Если нет задач на эту дату
            return '<div class="task-cells-container"></div>';
        }

        function renderTaskCell(stage, filter, date) {
            // Проверяем, есть ли задача на эту дату
            const hasTask = hasTaskOnDate(stage, filter, date);
            const isCompleted = filter.stages[stage].completed;
            
            if (!hasTask) {
                return '<div class="task-cell"></div>';
            }
            
            const taskClass = isCompleted ? 'task-completed' : `task-${stage}`;
            
            return `
                <div class="task-cell ${taskClass}" 
                     title="${getStageName(stage)}: ${date}"
                     onclick="showTaskDetails('${filter.filter}', '${stage}', '${date}')">
                </div>
            `;
        }

        function hasTaskOnDate(stage, filter, date) {
            // Проверяем, есть ли задача на эту дату в данных из БД
            const stageDates = filter.stages[stage].dates;
            const hasTask = stageDates.includes(date);
            
            // Отладка
            if (hasTask) {
                console.log(`Task found: ${filter.filter} - ${stage} - ${date}`);
            }
            
            return hasTask;
        }

        function getStageName(stage) {
            const names = {
                'cutting': 'Порезка бухт',
                'corrugation': 'Гофрирование',
                'assembly': 'Сборка фильтров'
            };
            return names[stage] || stage;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return `${date.getDate()}.${date.getMonth() + 1}`;
        }

        function getDateClass(dateStr) {
            const date = new Date(dateStr);
            const today = new Date();
            
            if (date.toDateString() === today.toDateString()) {
                return 'today';
            }
            if (date.getDay() === 0 || date.getDay() === 6) {
                return 'weekend';
            }
            return '';
        }

        function showTaskDetails(filter, stage, date) {
            const stageName = getStageName(stage);
            alert(`Фильтр: ${filter}\nЭтап: ${stageName}\nДата: ${date}`);
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
