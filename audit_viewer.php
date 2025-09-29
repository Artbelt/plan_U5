<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр логов аудита</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .header p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .filters {
            padding: 15px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .filter-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.8rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.8rem;
            min-width: 120px;
        }

        .filter-group input[type="date"] {
            min-width: 140px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            align-self: flex-end;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .stats {
            padding: 15px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .stat-card {
            background: white;
            padding: 12px 15px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 3px;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .table-container {
            padding: 15px 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        th {
            background: #f8fafc;
            padding: 8px 6px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            position: sticky;
            top: 0;
            font-size: 0.75rem;
            white-space: nowrap;
        }

        td {
            padding: 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            max-width: 200px;
        }

        tr:hover {
            background: #f8fafc;
        }

        .operation {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .operation-INSERT {
            background: #d1fae5;
            color: #065f46;
        }

        .operation-UPDATE {
            background: #fef3c7;
            color: #92400e;
        }

        .operation-DELETE {
            background: #fee2e2;
            color: #991b1b;
        }

        .json-data {
            background: #f3f4f6;
            padding: 4px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
        }

        .json-data:hover {
            white-space: normal;
            overflow: visible;
            position: relative;
            z-index: 10;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            max-width: 400px;
        }

        .record-id {
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .record-id:hover {
            white-space: normal;
            overflow: visible;
            position: relative;
            z-index: 10;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .additional-info {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.7rem;
        }

        .additional-info:hover {
            white-space: normal;
            overflow: visible;
            position: relative;
            z-index: 10;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 300px;
        }

        .time-cell {
            font-size: 0.7rem;
            white-space: nowrap;
        }

        .table-name {
            font-family: 'Courier New', monospace;
            font-size: 0.7rem;
        }

        .hidden-column {
            display: none;
        }

        .column-toggle {
            position: fixed;
            top: 50%;
            right: 20px;
            transform: translateY(-50%);
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
        }

        .column-toggle h3 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            color: #374151;
        }

        .column-toggle label {
            display: block;
            margin: 5px 0;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .column-toggle input[type="checkbox"] {
            margin-right: 8px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-group select,
            .filter-group input {
                min-width: auto;
                width: 100%;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .table-container {
                padding: 10px;
            }
            
            table {
                font-size: 0.7rem;
            }
            
            th, td {
                padding: 4px 3px;
            }
            
            .json-data {
                max-width: 100px;
            }
            
            .record-id {
                max-width: 80px;
            }
            
            .additional-info {
                max-width: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Логи аудита</h1>
            <p>Отслеживание всех изменений в базе данных</p>
        </div>

        <div class="filters">
            <div class="filter-group">
                <label>Таблица:</label>
                <select id="tableFilter">
                    <option value="">Все таблицы</option>
                    <option value="manufactured_production">manufactured_production</option>
                    <option value="orders">orders</option>
                    <option value="salon_filter_structure">salon_filter_structure</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Операция:</label>
                <select id="operationFilter">
                    <option value="">Все операции</option>
                    <option value="INSERT">INSERT</option>
                    <option value="UPDATE">UPDATE</option>
                    <option value="DELETE">DELETE</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>С даты:</label>
                <input type="date" id="dateFrom">
            </div>
            
            <div class="filter-group">
                <label>По дату:</label>
                <input type="date" id="dateTo">
            </div>
            
            <button class="btn" onclick="loadAuditLogs()">🔍 Загрузить</button>
            <button class="btn" onclick="toggleColumns()" style="background: #6b7280;">📊 Колонки</button>
        </div>

        <div class="stats" id="statsContainer">
            <div class="stat-card">
                <div class="stat-number" id="totalRecords">-</div>
                <div class="stat-label">Всего записей</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="insertCount">-</div>
                <div class="stat-label">Добавлений</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="updateCount">-</div>
                <div class="stat-label">Обновлений</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="deleteCount">-</div>
                <div class="stat-label">Удалений</div>
            </div>
        </div>

        <div class="table-container">
            <div id="loadingIndicator" class="loading" style="display: none;">
                <p>🔄 Загрузка данных...</p>
            </div>
            
            <div id="noDataIndicator" class="no-data" style="display: none;">
                <p>📭 Нет данных для отображения</p>
            </div>
            
            <table id="auditTable" style="display: none;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Время</th>
                        <th>Таблица</th>
                        <th>Операция</th>
                        <th>ID записи</th>
                        <th>Старые</th>
                        <th>Новые</th>
                        <th>Поля</th>
                        <th>IP</th>
                        <th>Инфо</th>
                    </tr>
                </thead>
                <tbody id="auditTableBody">
                </tbody>
            </table>
        </div>
    </div>

    <!-- Переключатель колонок -->
    <div class="column-toggle" id="columnToggle">
        <h3>Показать колонки:</h3>
        <label><input type="checkbox" checked onchange="toggleColumn('id')"> ID</label>
        <label><input type="checkbox" checked onchange="toggleColumn('time')"> Время</label>
        <label><input type="checkbox" checked onchange="toggleColumn('table')"> Таблица</label>
        <label><input type="checkbox" checked onchange="toggleColumn('operation')"> Операция</label>
        <label><input type="checkbox" checked onchange="toggleColumn('record')"> ID записи</label>
        <label><input type="checkbox" checked onchange="toggleColumn('old')"> Старые</label>
        <label><input type="checkbox" checked onchange="toggleColumn('new')"> Новые</label>
        <label><input type="checkbox" checked onchange="toggleColumn('fields')"> Поля</label>
        <label><input type="checkbox" checked onchange="toggleColumn('ip')"> IP</label>
        <label><input type="checkbox" checked onchange="toggleColumn('info')"> Инфо</label>
    </div>

    <script>
        // Устанавливаем даты по умолчанию
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            
            document.getElementById('dateFrom').value = weekAgo.toISOString().split('T')[0];
            document.getElementById('dateTo').value = today.toISOString().split('T')[0];
            
            // Загружаем данные при загрузке страницы
            loadAuditLogs();
        });

        // Закрываем переключатель колонок при клике вне его
        document.addEventListener('click', function(event) {
            const toggle = document.getElementById('columnToggle');
            const button = event.target.closest('button[onclick="toggleColumns()"]');
            
            if (!toggle.contains(event.target) && !button) {
                toggle.style.display = 'none';
            }
        });

        function loadAuditLogs() {
            const tableFilter = document.getElementById('tableFilter').value;
            const operationFilter = document.getElementById('operationFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            // Показываем индикатор загрузки
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('noDataIndicator').style.display = 'none';
            document.getElementById('auditTable').style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'get_audit_logs');
            formData.append('table_name', tableFilter);
            formData.append('operation', operationFilter);
            formData.append('date_from', dateFrom);
            formData.append('date_to', dateTo);

            fetch('audit_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingIndicator').style.display = 'none';
                
                if (data.success) {
                    displayAuditLogs(data.logs);
                    updateStats(data.stats);
                } else {
                    document.getElementById('noDataIndicator').style.display = 'block';
                    console.error('Ошибка загрузки логов:', data.error);
                }
            })
            .catch(error => {
                document.getElementById('loadingIndicator').style.display = 'none';
                document.getElementById('noDataIndicator').style.display = 'block';
                console.error('Ошибка:', error);
            });
        }

        function displayAuditLogs(logs) {
            const tbody = document.getElementById('auditTableBody');
            tbody.innerHTML = '';

            if (logs.length === 0) {
                document.getElementById('noDataIndicator').style.display = 'block';
                return;
            }

            logs.forEach(log => {
                const row = document.createElement('tr');
                
                const oldValues = log.old_values ? JSON.stringify(log.old_values, null, 2) : '';
                const newValues = log.new_values ? JSON.stringify(log.new_values, null, 2) : '';
                const changedFields = log.changed_fields ? log.changed_fields.join(', ') : '';
                
                row.innerHTML = `
                    <td>${log.id}</td>
                    <td class="time-cell">${new Date(log.created_at).toLocaleString('ru-RU')}</td>
                    <td class="table-name">${log.table_name}</td>
                    <td><span class="operation operation-${log.operation}">${log.operation}</span></td>
                    <td><div class="record-id" title="${log.record_id || '-'}">${log.record_id || '-'}</div></td>
                    <td><div class="json-data" title="${oldValues}">${oldValues || '-'}</div></td>
                    <td><div class="json-data" title="${newValues}">${newValues || '-'}</div></td>
                    <td>${changedFields || '-'}</td>
                    <td>${log.user_ip || '-'}</td>
                    <td><div class="additional-info" title="${log.additional_info || '-'}">${log.additional_info || '-'}</div></td>
                `;
                
                tbody.appendChild(row);
            });

            document.getElementById('auditTable').style.display = 'table';
        }

        function updateStats(stats) {
            document.getElementById('totalRecords').textContent = stats.total || 0;
            document.getElementById('insertCount').textContent = stats.INSERT || 0;
            document.getElementById('updateCount').textContent = stats.UPDATE || 0;
            document.getElementById('deleteCount').textContent = stats.DELETE || 0;
        }

        function toggleColumns() {
            const toggle = document.getElementById('columnToggle');
            toggle.style.display = toggle.style.display === 'none' ? 'block' : 'none';
        }

        function toggleColumn(columnName) {
            const columnIndex = {
                'id': 0,
                'time': 1,
                'table': 2,
                'operation': 3,
                'record': 4,
                'old': 5,
                'new': 6,
                'fields': 7,
                'ip': 8,
                'info': 9
            };

            const index = columnIndex[columnName];
            if (index !== undefined) {
                const table = document.getElementById('auditTable');
                const rows = table.querySelectorAll('tr');
                
                rows.forEach(row => {
                    const cell = row.cells[index];
                    if (cell) {
                        cell.classList.toggle('hidden-column');
                    }
                });
            }
        }
    </script>
</body>
</html>
