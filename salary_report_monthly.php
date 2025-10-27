<?php require_once('tools/tools.php')?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Отчет по заработной плате за месяц</title>
    <style>
        /* ===== Pro UI ===== */
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
        }
        html,body{height:100%}
        body{
            margin:0; background:var(--bg); color:var(--ink);
            font:14px/1.45 "Segoe UI", Roboto, Arial, sans-serif;
            -webkit-font-smoothing:antialiased;
        }
        
        .container{ max-width:100%; margin:0 auto; padding:8px; text-align: center; }
        
        .report-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        
        .control-panel {
            display: inline-block;
            width: auto;
            max-width: fit-content;
            margin: 0 auto;
        }
        
        .panel{
            background:var(--panel);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:12px;
            margin-bottom:12px;
        }
        
        .section-title{
            font-size:14px; font-weight:600; color:#111827;
            margin:0 0 8px; padding-bottom:4px; border-bottom:1px solid var(--border);
        }
        
        button, .btn{
            appearance:none;
            border:1px solid transparent;
            cursor:pointer;
            background:var(--accent);
            color:var(--accent-ink);
            padding:6px 12px;
            border-radius:6px;
            font-weight:600;
            font-size:12px;
            transition:background .2s, box-shadow .2s;
            box-shadow:0 2px 4px rgba(0,0,0,0.1);
            text-decoration:none;
            display:inline-block;
            margin: 3px;
        }
        button:hover, .btn:hover{ 
            background:#1e47c5; 
            box-shadow:0 2px 8px rgba(2,8,20,.10); 
        }
        
        input[type="month"]{
            min-width:140px; padding:6px 10px;
            border:1px solid var(--border); border-radius:6px;
            background:#fff; color:var(--ink); outline:none;
            transition:border-color .2s, box-shadow .2s;
            font-size:12px;
        }
        input:focus{
            border-color:#c7d2fe; box-shadow:0 0 0 2px #e0e7ff;
        }
        
        .form-group{
            display:flex; align-items:center; gap:8px; margin-bottom:10px;
        }
        .form-group label{
            font-weight:600; min-width:100px; font-size:12px;
        }
        
        /* Стили для таблицы отчета */
        .report-table {
            width: auto;
            table-layout: auto;
            border-collapse: collapse;
            background: #fff;
            margin: 12px 0;
            border: 1px solid #6b7280;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        
        .report-table th,
        .report-table td {
            padding: 4px 3px;
            border: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            line-height: 1.2;
            width: 40px;
            min-width: 40px;
            max-width: 40px;
        }
        
        .report-table th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--ink);
            position: sticky;
            top: 0;
            z-index: 10;
            padding: 5px 3px;
        }
        
        .report-table th.tariff-col {
            text-align: left;
            width: 130px;
            min-width: 130px;
            max-width: 130px;
            background: #e0e7ff;
            font-size: 9px;
        }
        
        .report-table td.tariff-cell {
            text-align: left;
            font-weight: 600;
            background: #f3f4f6;
            font-size: 9px;
            padding: 3px 4px;
            width: 130px;
            min-width: 130px;
            max-width: 130px;
        }
        
        .report-table td.count-cell {
            background: #fefefe;
            font-size: 9px;
            width: 40px;
            min-width: 40px;
            max-width: 40px;
        }
        
        .report-table td.total-cell {
            background: #dbeafe;
            font-weight: 700;
            font-size: 9px;
            width: 50px;
            min-width: 50px;
            max-width: 50px;
        }
        
        .report-table th.total-cell {
            width: 50px;
            min-width: 50px;
            max-width: 50px;
        }
        
        /* Стили для скрытого содержимого столбцов */
        .report-table td.content-hidden {
            color: transparent !important;
            user-select: none;
        }
        
        /* Стили для кликабельных заголовков */
        .report-table th.clickable {
            cursor: pointer;
            user-select: none;
            transition: background-color 0.2s;
        }
        
        .report-table th.clickable:hover {
            background-color: #e5e7eb !important;
        }
        
        .report-table th.clickable.content-hidden {
            background-color: #fef3c7 !important;
        }
        
        .report-table .weekend {
            background: #fff1f2 !important;
        }
        
        .report-table .today {
            background: #fef9c3 !important;
        }
        
        .brigade-header {
            background: #4f46e5 !important;
            color: white !important;
            font-size: 13px;
            padding: 8px;
        }
        
        .tariff-rate {
            color: #059669;
            font-size: 8px;
            display: block;
            margin-top: 1px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }
        
        .summary-box {
            background: #f0f9ff;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 12px;
            margin: 10px 0;
        }
        
        .summary-box h4 {
            margin: 0 0 8px 0;
            color: #1e40af;
        }
        
        @media print {
            .no-print { display: none; }
            
            body {
                background: white !important;
                color: black !important;
                font-size: 11px !important;
                line-height: 1.2 !important;
            }
            
            .container {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .panel {
                background: white !important;
                border: none !important;
                box-shadow: none !important;
                margin: 0 0 10px 0 !important;
                padding: 8px !important;
                border-radius: 0 !important;
            }
            
            .report-table {
                font-size: 9px !important;
                border: 2px solid #000 !important;
                width: 100% !important;
            }
            
            .report-table th,
            .report-table td {
                border: 1px solid #000 !important;
                padding: 3px 2px !important;
                font-size: 9px !important;
                background: white !important;
                color: black !important;
            }
            
            .report-table th {
                background: #f0f0f0 !important;
                font-weight: bold !important;
                text-align: center !important;
            }
            
            .report-table th.tariff-col {
                background: #e0e0e0 !important;
                text-align: left !important;
                font-weight: bold !important;
            }
            
            .report-table td.tariff-cell {
                background: #f8f8f8 !important;
                font-weight: normal !important;
                text-align: left !important;
            }
            
            .report-table td.count-cell {
                background: white !important;
                text-align: center !important;
            }
            
            .report-table td.total-cell {
                background: #e0e0e0 !important;
                font-weight: bold !important;
                text-align: center !important;
            }
            
            .report-table .weekend {
                background: #f5f5f5 !important;
            }
            
            .report-table .today {
                background: #fffacd !important;
            }
            
            .section-title {
                font-size: 14px !important;
                font-weight: bold !important;
                color: black !important;
                border-bottom: 1px solid #000 !important;
                margin-bottom: 8px !important;
            }
            
            .tariff-rate {
                font-size: 8px !important;
                color: #333 !important;
            }
            
            /* Убираем все цвета для итоговых строк */
            tr[style*="background: #e0e7ff"] {
                background: #f0f0f0 !important;
                font-weight: bold !important;
            }
            
            tr[style*="background: #dcfce7"] {
                background: #e8e8e8 !important;
                font-weight: bold !important;
            }
            
            tr[style*="background: #3b82f6"] {
                background: #d0d0d0 !important;
                color: black !important;
            }
            
            tr[style*="background: #059669"] {
                background: #d0d0d0 !important;
                color: black !important;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="panel control-panel no-print">
        <div class="section-title">📊 Отчет по заработной плате за месяц</div>
        <p style="color: var(--muted); margin: 4px 0 8px 0; font-size: 11px;">Выберите месяц для формирования отчета</p>
        
        <div class="form-group">
            <label for="month_select">Выбор месяца:</label>
            <input type="month" id="month_select" value="<?php echo date('Y-m'); ?>">
            <button onclick="loadReport()">📅 Сформировать отчет</button>
            <button onclick="window.print()">🖨️ Печать</button>
            <button onclick="window.history.back()">← Назад</button>
        </div>
        <div class="form-group">
            <label for="period_select">Период:</label>
            <select id="period_select" onchange="loadReport()" style="padding: 6px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 12px; background: #fff;">
                <option value="full">Весь месяц</option>
                <option value="first">1-15 числа</option>
                <option value="second">16-31 числа</option>
            </select>
        </div>
    </div>
    
    <div id="report_container" class="report-container"></div>
</div>

<script>
function loadReport() {
    const monthInput = document.getElementById('month_select');
    const periodSelect = document.getElementById('period_select');
    const month = monthInput.value;
    const period = periodSelect.value;
    
    if (!month) {
        alert('Пожалуйста, выберите месяц');
        return;
    }
    
    const container = document.getElementById('report_container');
    container.innerHTML = '<div class="loading">⏳ Загрузка данных...</div>';
    
    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (this.readyState === 4) {
            if (this.status === 200) {
                container.innerHTML = this.responseText;
            } else {
                container.innerHTML = '<div class="panel"><p style="color:red;">❌ Ошибка загрузки данных</p></div>';
            }
        }
    };
    
    xhr.open('POST', 'generate_monthly_salary_report.php', true);
    xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhr.send('month=' + month + '&period=' + period);
}

// Функция для скрытия/показа содержимого столбца
function toggleColumn(event, columnIndex) {
    // Находим таблицу, в которой был клик
    const headerCell = event.target;
    const table = headerCell.closest('.report-table');
    
    if (!table) return;
    
    // Скрываем/показываем данные только в этой таблице
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        // +1 потому что первая td - это tariff-cell
        const cell = cells[columnIndex + 1];
        
        if (cell && !cell.classList.contains('tariff-cell') && !cell.classList.contains('total-cell')) {
            cell.classList.toggle('content-hidden');
        }
    });
    
    // Обновляем индикатор в заголовке
    headerCell.classList.toggle('content-hidden');
}

// Автозагрузка при открытии страницы
window.addEventListener('DOMContentLoaded', function() {
    loadReport();
});
</script>
</body>
</html>

