<?php
// Тестовая страница с диаграммой Ганта для планирования производства
$order = $_GET['order'] ?? '38-44-25';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Диаграмма Ганта - Планирование производства</title>
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
            line-height: 1.5;
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

        .header .subtitle {
            color: var(--muted);
            font-size: 16px;
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
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .gantt-header {
            display: flex;
            background: #f8fafc;
            border-bottom: 2px solid var(--border);
        }

        .gantt-filters-column {
            width: 300px;
            min-width: 300px;
            padding: 16px;
            border-right: 2px solid var(--border);
            background: #f8fafc;
        }

        .gantt-timeline {
            flex: 1;
            overflow-x: auto;
        }

        .gantt-dates {
            display: flex;
            min-width: 100%;
        }

        .gantt-date {
            min-width: 60px;
            padding: 8px 4px;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            border-right: 1px solid var(--border);
            background: #f8fafc;
        }

        .gantt-date.today {
            background: #dbeafe;
            color: var(--info);
        }

        .gantt-date.weekend {
            background: #fef2f2;
            color: var(--danger);
        }

        .gantt-rows {
            display: flex;
            flex-direction: column;
        }

        .gantt-row {
            display: flex;
            min-height: 60px;
            border-bottom: 1px solid var(--border);
        }

        .gantt-row:nth-child(even) {
            background: #fafafa;
        }

        .gantt-row:hover {
            background: #f0f9ff;
        }

        .filter-info {
            width: 300px;
            min-width: 300px;
            padding: 16px;
            border-right: 2px solid var(--border);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .filter-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .filter-details {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .filter-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-in-progress {
            background: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-critical {
            background: #fee2e2;
            color: #991b1b;
        }

        .gantt-tasks {
            flex: 1;
            display: flex;
            position: relative;
            min-height: 60px;
        }

        .task-bar {
            position: absolute;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .task-bar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .task-cutting {
            background: linear-gradient(90deg, var(--cutting), #1d4ed8);
            top: 5px;
        }

        .task-corrugation {
            background: linear-gradient(90deg, var(--corrugation), #d97706);
            top: 25px;
        }

        .task-assembly {
            background: linear-gradient(90deg, var(--assembly), #059669);
            top: 35px;
        }

        .task-completed {
            opacity: 0.7;
            background: linear-gradient(90deg, #6b7280, #4b5563);
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
            border-radius: 6px;
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
            .gantt-filters-column,
            .filter-info {
                width: 200px;
                min-width: 200px;
            }
            
            .gantt-date {
                min-width: 40px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Диаграмма Ганта - Планирование производства</h1>
            <p class="subtitle">Заявка <?= htmlspecialchars($order) ?> - Визуализация этапов производства</p>
        </div>

        <div class="controls">
            <div class="control-group">
                <label>Заявка:</label>
                <select id="orderFilter" onchange="changeOrder()">
                    <option value="38-44-25" <?= $order === '38-44-25' ? 'selected' : '' ?>>38-44-25</option>
                    <option value="39-45-26" <?= $order === '39-45-26' ? 'selected' : '' ?>>39-45-26</option>
                </select>
            </div>
            <div class="control-group">
                <label>Масштаб:</label>
                <select id="scaleFilter" onchange="updateScale()">
                    <option value="week">По неделям</option>
                    <option value="day" selected>По дням</option>
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
        let currentOrder = '<?= $order ?>';
        let currentScale = 'day';

        // Загрузка данных
        async function loadOrderData() {
            try {
                const response = await fetch(`api_get_order_filters.php?order=${currentOrder}`);
                const data = await response.json();
                
                if (data.success) {
                    allFilters = data.filters;
                    renderGanttChart(allFilters);
                } else {
                    document.getElementById('ganttContainer').innerHTML = 
                        `<div class="error">Ошибка загрузки: ${data.message}</div>`;
                }
            } catch (error) {
                document.getElementById('ganttContainer').innerHTML = 
                    `<div class="error">Ошибка: ${error.message}</div>`;
            }
        }

        function renderGanttChart(filters) {
            const container = document.getElementById('ganttContainer');
            
            if (filters.length === 0) {
                container.innerHTML = '<div class="loading">Нет данных по заявке</div>';
                return;
            }

            // Генерируем даты
            const dates = generateDates();
            
            container.innerHTML = `
                <div class="gantt-header">
                    <div class="gantt-filters-column">
                        <strong>Фильтр</strong>
                    </div>
                    <div class="gantt-timeline">
                        <div class="gantt-dates">
                            ${dates.map(date => `
                                <div class="gantt-date ${getDateClass(date)}">
                                    ${formatDate(date)}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                <div class="gantt-rows">
                    ${filters.map(filter => renderFilterRow(filter, dates)).join('')}
                </div>
            `;
        }

        function renderFilterRow(filter, dates) {
            const startDate = new Date(filter.dates.plan || new Date());
            const endDate = new Date(filter.dates.end || new Date());
            
            return `
                <div class="gantt-row">
                    <div class="filter-info">
                        <div class="filter-name">${filter.filter}</div>
                        <div class="filter-details">
                            ${filter.plan_count} шт • ${filter.height || '?'}мм • ${filter.material || '?'}
                        </div>
                        <div class="filter-status status-${filter.status}">
                            ${getStatusText(filter.status)}
                        </div>
                    </div>
                    <div class="gantt-tasks">
                        ${renderTaskBars(filter, dates)}
                    </div>
                </div>
            `;
        }

        function renderTaskBars(filter, dates) {
            const tasks = [];
            
            // Порезка
            if (filter.progress.cutting.percent > 0) {
                tasks.push(renderTaskBar('cutting', filter.progress.cutting, dates, 'Порезка'));
            }
            
            // Гофрирование
            if (filter.progress.corrugation.percent > 0) {
                tasks.push(renderTaskBar('corrugation', filter.progress.corrugation, dates, 'Гофрирование'));
            }
            
            // Сборка
            if (filter.progress.assembly.percent > 0) {
                tasks.push(renderTaskBar('assembly', filter.progress.assembly, dates, 'Сборка'));
            }
            
            return tasks.join('');
        }

        function renderTaskBar(type, progress, dates, label) {
            const isCompleted = progress.percent >= 100;
            const width = Math.max(progress.percent * 0.8, 20); // Минимум 20px
            const left = 10; // Отступ слева
            
            return `
                <div class="task-bar task-${type} ${isCompleted ? 'task-completed' : ''}" 
                     style="left: ${left}px; width: ${width}px;"
                     title="${label}: ${progress.percent}% (${progress.label})">
                    ${progress.percent}%
                </div>
            `;
        }

        function generateDates() {
            const dates = [];
            const today = new Date();
            const startDate = new Date(today);
            startDate.setDate(today.getDate() - 7); // Начинаем с недели назад
            
            for (let i = 0; i < 30; i++) {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);
                dates.push(date);
            }
            
            return dates;
        }

        function formatDate(date) {
            if (currentScale === 'week') {
                const weekStart = new Date(date);
                weekStart.setDate(date.getDate() - date.getDay());
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekStart.getDate() + 6);
                return `${weekStart.getDate()}.${weekStart.getMonth() + 1}-${weekEnd.getDate()}.${weekEnd.getMonth() + 1}`;
            } else {
                return `${date.getDate()}.${date.getMonth() + 1}`;
            }
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

        function getStatusText(status) {
            const statusMap = {
                'in-progress': 'В работе',
                'completed': 'Завершено',
                'critical': 'Критично'
            };
            return statusMap[status] || status;
        }

        function changeOrder() {
            const newOrder = document.getElementById('orderFilter').value;
            window.location.href = `?order=${newOrder}`;
        }

        function updateScale() {
            currentScale = document.getElementById('scaleFilter').value;
            if (allFilters.length > 0) {
                renderGanttChart(allFilters);
            }
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', () => {
            loadOrderData();
        });
    </script>
</body>
</html>

