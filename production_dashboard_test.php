<?php
// Тестовая страница для демонстрации интерфейса планирования производства
$order = $_GET['order'] ?? '38-44-25';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель планирования производства - Тест</title>
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
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
        }

        .header .subtitle {
            color: var(--muted);
            font-size: 16px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 8px;
            padding: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .stat-card .label {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
        }

        .filters {
            background: var(--card);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .filter-group label {
            font-size: 14px;
            color: var(--muted);
            white-space: nowrap;
        }

        .filter-group select, .filter-group input {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }

        .positions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }

        .position-card {
            background: var(--card);
            border-radius: 8px;
            padding: 16px;
            box-shadow: var(--shadow);
            border: 2px solid var(--border);
            transition: all 0.2s ease;
            position: relative;
            margin-bottom: 12px;
        }

        .position-card:hover {
            border-color: var(--info);
            box-shadow: 0 2px 8px 0 rgba(0, 0, 0, 0.1);
        }

        .position-card.critical {
            border-color: var(--danger);
            background: #fef2f2;
        }

        .position-card.warning {
            border-color: var(--warning);
            background: #fffbeb;
        }

        .position-card.success {
            border-color: var(--success);
            background: #f0fdf4;
        }

        .position-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .position-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .position-subtitle {
            font-size: 14px;
            color: var(--muted);
        }

        .position-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
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

        .progress-section {
            margin: 12px 0;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .progress-item {
            text-align: center;
            padding: 8px;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .progress-icon {
            font-size: 20px;
            margin-bottom: 4px;
            display: block;
        }

        .progress-content {
            text-align: center;
        }

        .progress-label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--muted);
        }

        .progress-percent {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .progress-fill.cutting {
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
        }

        .progress-fill.corrugation {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .progress-fill.assembly {
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .progress-fill.completed {
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .position-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--muted);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .priority-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-high {
            background: #fee2e2;
            color: #991b1b;
        }

        .priority-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .priority-low {
            background: #e0f2fe;
            color: #0c4a6e;
        }

        @media (max-width: 768px) {
            .positions-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Панель планирования производства</h1>
            <p class="subtitle">Мониторинг прогресса по всем этапам производства - Заявка <?= htmlspecialchars($order) ?></p>
        </div>

        <div class="stats" id="statsContainer">
            <div class="stat-card">
                <div class="label">Всего позиций</div>
                <div class="value" id="totalPositions">-</div>
            </div>
            <div class="stat-card">
                <div class="label">В работе</div>
                <div class="value" id="inProgressPositions">-</div>
            </div>
            <div class="stat-card">
                <div class="label">Завершено</div>
                <div class="value" id="completedPositions">-</div>
            </div>
            <div class="stat-card">
                <div class="label">Критично</div>
                <div class="value" id="criticalPositions">-</div>
            </div>
        </div>

        <div class="filters">
            <div class="filter-group">
                <label>Заявка:</label>
                <select id="orderFilter" onchange="changeOrder()">
                    <option value="38-44-25" <?= $order === '38-44-25' ? 'selected' : '' ?>>38-44-25</option>
                    <option value="39-45-26" <?= $order === '39-45-26' ? 'selected' : '' ?>>39-45-26</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Статус:</label>
                <select id="statusFilter">
                    <option value="">Все статусы</option>
                    <option value="in-progress">В работе</option>
                    <option value="completed">Завершено</option>
                    <option value="critical">Критично</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Этап:</label>
                <select id="stageFilter">
                    <option value="">Все этапы</option>
                    <option value="cutting">Порезка</option>
                    <option value="corrugation">Гофрирование</option>
                    <option value="assembly">Сборка</option>
                </select>
            </div>
        </div>

        <div class="positions-grid" id="positionsGrid">
            <div class="loading" style="text-align: center; padding: 40px; color: var(--muted);">
                Загрузка данных...
            </div>
        </div>
    </div>

    <script>
        let allFilters = [];
        const currentOrder = '<?= $order ?>';

        // Загрузка данных при инициализации
        async function loadOrderData() {
            try {
                const response = await fetch(`api_get_order_filters.php?order=${currentOrder}`);
                const data = await response.json();
                
                if (data.success) {
                    allFilters = data.filters;
                    renderFilters(allFilters);
                    updateStats(allFilters);
                } else {
                    document.getElementById('positionsGrid').innerHTML = 
                        `<div style="text-align: center; padding: 40px; color: var(--danger);">
                            Ошибка загрузки: ${data.message}
                        </div>`;
                }
            } catch (error) {
                document.getElementById('positionsGrid').innerHTML = 
                    `<div style="text-align: center; padding: 40px; color: var(--danger);">
                        Ошибка: ${error.message}
                    </div>`;
            }
        }

        function renderFilters(filters) {
            const container = document.getElementById('positionsGrid');
            
            if (filters.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--muted);">Нет данных по заявке</div>';
                return;
            }

            container.innerHTML = filters.map(filter => `
                <div class="position-card ${filter.status}" 
                     data-order="${currentOrder}" 
                     data-status="${filter.status}" 
                     data-stage="${filter.current_stage}">
                    <div class="position-header">
                        <div>
                            <div class="position-title">${filter.filter}</div>
                            <div class="position-subtitle">Заявка ${currentOrder} • ${filter.plan_count} шт</div>
                            ${filter.height ? `<div class="position-subtitle">Высота: ${filter.height}мм • Материал: ${filter.material}</div>` : ''}
                        </div>
                        <div class="position-status status-${filter.status}">
                            ${getStatusText(filter.status)}
                        </div>
                    </div>

                    <div class="progress-section">
                        <div class="progress-item">
                            <div class="progress-icon">📦</div>
                            <div class="progress-content">
                                <div class="progress-label">
                                    <span>Порезка бухт</span>
                                    <span>${filter.progress.cutting.percent}% (${filter.progress.cutting.label})</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill ${filter.progress.cutting.percent >= 100 ? 'completed' : 'cutting'}" 
                                         style="width: ${filter.progress.cutting.percent}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="progress-item">
                            <div class="progress-icon">🔄</div>
                            <div class="progress-content">
                                <div class="progress-label">
                                    <span>Гофрирование</span>
                                    <span>${filter.progress.corrugation.percent}% (${filter.progress.corrugation.label})</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill ${filter.progress.corrugation.percent >= 100 ? 'completed' : 'corrugation'}" 
                                         style="width: ${filter.progress.corrugation.percent}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="progress-item">
                            <div class="progress-icon">✅</div>
                            <div class="progress-content">
                                <div class="progress-label">
                                    <span>Сборка фильтров</span>
                                    <span>${filter.progress.assembly.percent}% (${filter.progress.assembly.label})</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill ${filter.progress.assembly.percent >= 100 ? 'completed' : 'assembly'}" 
                                         style="width: ${filter.progress.assembly.percent}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="position-meta">
                        <div>
                            ${filter.dates.plan ? `<span>📅 План: ${filter.dates.plan}</span>` : ''}
                            ${filter.dates.end ? `<span>⏰ Срок: ${filter.dates.end}</span>` : ''}
                        </div>
                        <div class="priority-badge priority-${filter.priority}">
                            ${getPriorityText(filter.priority)}
                        </div>
                    </div>
                </div>
            `).join('');

            // Анимация прогресс-баров
            setTimeout(() => {
                const progressBars = document.querySelectorAll('.progress-fill');
                progressBars.forEach(bar => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                });
            }, 100);
        }

        function getStatusText(status) {
            const statusMap = {
                'in-progress': 'В работе',
                'completed': 'Завершено',
                'critical': 'Критично'
            };
            return statusMap[status] || status;
        }

        function getPriorityText(priority) {
            const priorityMap = {
                'high': 'Высокий',
                'medium': 'Средний',
                'low': 'Низкий'
            };
            return priorityMap[priority] || priority;
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

        function filterPositions() {
            const statusFilter = document.getElementById('statusFilter').value;
            const stageFilter = document.getElementById('stageFilter').value;
            
            let filtered = allFilters;
            
            if (statusFilter) {
                filtered = filtered.filter(f => f.status === statusFilter);
            }
            
            if (stageFilter) {
                filtered = filtered.filter(f => f.current_stage === stageFilter);
            }
            
            renderFilters(filtered);
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', () => {
            loadOrderData();
            
            // Обработчики фильтров
            document.getElementById('statusFilter').addEventListener('change', filterPositions);
            document.getElementById('stageFilter').addEventListener('change', filterPositions);
        });
    </script>
</body>
</html>
