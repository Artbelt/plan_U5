<?php
// Упрощенная версия планирования производства
$order = $_GET['order'] ?? '38-44-25';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Простое планирование производства</title>
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
            max-width: 1200px;
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

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--muted);
        }

        .filters-table {
            background: var(--card);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        .table-header {
            background: #f8fafc;
            padding: 16px;
            border-bottom: 2px solid var(--border);
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 16px;
            font-weight: 600;
            font-size: 14px;
        }

        .table-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 16px;
            padding: 16px;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }

        .table-row:nth-child(even) {
            background: #fafafa;
        }

        .table-row:hover {
            background: #f0f9ff;
        }

        .filter-name {
            font-weight: 600;
            font-size: 16px;
        }

        .filter-details {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        .stage {
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
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

        .stage-completed {
            background: #f3f4f6;
            color: #6b7280;
        }

        .progress-simple {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-bar-simple {
            flex: 1;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill-simple {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 12px;
            font-weight: 600;
            min-width: 40px;
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
            .table-header,
            .table-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .table-header > div,
            .table-row > div {
                padding: 4px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Планирование производства</h1>
            <p>Заявка <?= htmlspecialchars($order) ?> - Простой и понятный вид</p>
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

        <div class="stats" id="statsContainer">
            <div class="stat-card">
                <div class="stat-value" id="totalPositions">-</div>
                <div class="stat-label">Всего позиций</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="inProgressPositions">-</div>
                <div class="stat-label">В работе</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="completedPositions">-</div>
                <div class="stat-label">Завершено</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="criticalPositions">-</div>
                <div class="stat-label">Критично</div>
            </div>
        </div>

        <div class="filters-table" id="filtersTable">
            <div class="loading">Загрузка данных...</div>
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
                    updateStats(allFilters);
                } else {
                    document.getElementById('filtersTable').innerHTML = 
                        `<div class="error">Ошибка загрузки: ${data.message}</div>`;
                }
            } catch (error) {
                document.getElementById('filtersTable').innerHTML = 
                    `<div class="error">Ошибка: ${error.message}</div>`;
            }
        }

        function renderTable(filters) {
            const container = document.getElementById('filtersTable');
            
            if (filters.length === 0) {
                container.innerHTML = '<div class="loading">Нет данных по заявке</div>';
                return;
            }

            container.innerHTML = `
                <div class="table-header">
                    <div>Фильтр</div>
                    <div>Порезка</div>
                    <div>Гофрирование</div>
                    <div>Сборка</div>
                    <div>Статус</div>
                </div>
                ${filters.map(filter => renderFilterRow(filter)).join('')}
            `;
        }

        function renderFilterRow(filter) {
            return `
                <div class="table-row">
                    <div>
                        <div class="filter-name">${filter.filter}</div>
                        <div class="filter-details">${filter.plan_count} шт • ${filter.height || '?'}мм</div>
                    </div>
                    <div class="stage stage-${getStageClass('cutting', filter.progress.cutting.percent)}">
                        ${renderStageProgress(filter.progress.cutting)}
                    </div>
                    <div class="stage stage-${getStageClass('corrugation', filter.progress.corrugation.percent)}">
                        ${renderStageProgress(filter.progress.corrugation)}
                    </div>
                    <div class="stage stage-${getStageClass('assembly', filter.progress.assembly.percent)}">
                        ${renderStageProgress(filter.progress.assembly)}
                    </div>
                    <div>
                        <div class="stage status-${filter.status}">
                            ${getStatusText(filter.status)}
                        </div>
                    </div>
                </div>
            `;
        }

        function renderStageProgress(progress) {
            return `
                <div class="progress-simple">
                    <div class="progress-bar-simple">
                        <div class="progress-fill-simple" style="width: ${progress.percent}%; background: ${getProgressColor(progress.percent)}"></div>
                    </div>
                    <div class="progress-text">${progress.percent}%</div>
                </div>
            `;
        }

        function getStageClass(stage, percent) {
            if (percent >= 100) return 'completed';
            return stage;
        }

        function getProgressColor(percent) {
            if (percent >= 100) return '#10b981';
            if (percent >= 50) return '#f59e0b';
            return '#ef4444';
        }

        function getStatusText(status) {
            const statusMap = {
                'in-progress': 'В работе',
                'completed': 'Завершено',
                'critical': 'Критично'
            };
            return statusMap[status] || status;
        }

        function updateStats(filters) {
            const total = filters.length;
            const inProgress = filters.filter(f => f.status === 'in-progress').length;
            const completed = filters.filter(f => f.status === 'completed').length;
            const critical = filters.filter(f => f.status === 'critical').length;

            document.getElementById('totalPositions').textContent = total;
            document.getElementById('inProgressPositions').textContent = inProgress;
            document.getElementById('completedPositions').textContent = completed;
            document.getElementById('criticalPositions').textContent = critical;
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

