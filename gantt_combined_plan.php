<?php
// Комплексный план производства: порезка + гофрирование + сборка
// Проверяем авторизацию через новую систему
require_once('../auth/includes/config.php');
require_once('../auth/includes/auth-functions.php');

// Инициализация системы авторизации
initAuthSystem();

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new AuthManager();
$session = $auth->checkSession();

if (!$session) {
    header('Location: ../auth/login.php');
    exit;
}

// Подключение к базе данных
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Получаем номер заявки из параметров
$orderNumber = $_GET['order'] ?? '';

if (empty($orderNumber)) {
    die('Не указан номер заявки. Укажите параметр ?order=НОМЕР_ЗАЯВКИ');
}

// Получаем список фильтров по заявке
$sqlFilters = "
    SELECT DISTINCT 
        TRIM(SUBSTRING_INDEX(o.filter, ' [', 1)) as filter_base,
        o.filter as filter_full,
        o.count as total_count
    FROM orders o
    WHERE o.order_number = :order
    AND (o.hide IS NULL OR o.hide = 0)
    ORDER BY o.filter
";

$stmtFilters = $pdo->prepare($sqlFilters);
$stmtFilters->execute([':order' => $orderNumber]);
$filters = $stmtFilters->fetchAll();

if (empty($filters)) {
    die('Заявка не найдена или в ней нет фильтров');
}

// Получаем даты для плана порезки (из roll_plans) с номерами бухт
$sqlCutDates = "
    SELECT DISTINCT rp.work_date, cp.filter, rp.bale_id
    FROM roll_plans rp
    JOIN cut_plans cp ON cp.order_number = rp.order_number AND cp.bale_id = rp.bale_id
    WHERE rp.order_number = :order
    ORDER BY rp.work_date
";

$stmtCutDates = $pdo->prepare($sqlCutDates);
$stmtCutDates->execute([':order' => $orderNumber]);
$cutDates = [];
$cutBales = [];
while ($row = $stmtCutDates->fetch()) {
    $filterBase = trim(preg_replace('/\[.*?\]/', '', $row['filter']));
    if (!isset($cutDates[$filterBase])) {
        $cutDates[$filterBase] = [];
        $cutBales[$filterBase] = [];
    }
    $cutDates[$filterBase][] = $row['work_date'];
    $cutBales[$filterBase][$row['work_date']] = $row['bale_id'];
}

// Получаем даты для плана гофрирования (из corrugation_plan) с количествами
$sqlCorrDates = "
    SELECT DISTINCT plan_date, filter_label, count
    FROM corrugation_plan
    WHERE order_number = :order
    ORDER BY plan_date
";

$stmtCorrDates = $pdo->prepare($sqlCorrDates);
$stmtCorrDates->execute([':order' => $orderNumber]);
$corrDates = [];
$corrQuantities = [];
while ($row = $stmtCorrDates->fetch()) {
    $filterBase = trim(preg_replace('/\[.*?\]/', '', $row['filter_label']));
    if (!isset($corrDates[$filterBase])) {
        $corrDates[$filterBase] = [];
        $corrQuantities[$filterBase] = [];
    }
    $corrDates[$filterBase][] = $row['plan_date'];
    $corrQuantities[$filterBase][$row['plan_date']] = $row['count'];
}

// Получаем даты для плана сборки (из build_plan) с количествами
$sqlBuildDates = "
    SELECT DISTINCT plan_date, filter, count
    FROM build_plan
    WHERE order_number = :order
    ORDER BY plan_date
";

$stmtBuildDates = $pdo->prepare($sqlBuildDates);
$stmtBuildDates->execute([':order' => $orderNumber]);
$buildDates = [];
$buildQuantities = [];
while ($row = $stmtBuildDates->fetch()) {
    $filterBase = trim(preg_replace('/\[.*?\]/', '', $row['filter']));
    if (!isset($buildDates[$filterBase])) {
        $buildDates[$filterBase] = [];
        $buildQuantities[$filterBase] = [];
    }
    $buildDates[$filterBase][] = $row['plan_date'];
    $buildQuantities[$filterBase][$row['plan_date']] = $row['count'];
}

// Собираем все уникальные даты
$allDates = [];
foreach ($filters as $filter) {
    $filterBase = $filter['filter_base'];
    if (isset($cutDates[$filterBase])) {
        $allDates = array_merge($allDates, $cutDates[$filterBase]);
    }
    if (isset($corrDates[$filterBase])) {
        $allDates = array_merge($allDates, $corrDates[$filterBase]);
    }
    if (isset($buildDates[$filterBase])) {
        $allDates = array_merge($allDates, $buildDates[$filterBase]);
    }
}

$allDates = array_unique($allDates);
sort($allDates);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Комплексный план производства - <?= htmlspecialchars($orderNumber) ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Arial,sans-serif;padding:10px}
        .header{background:#fff;padding:10px;margin-bottom:10px;border:1px solid #ddd}
        .header h1{font-size:18px;margin-bottom:5px}

        .gantt-container{overflow:auto;max-height:80vh;max-width:100%}
        .gantt-table{width:100%;border-collapse:collapse;font-size:11px;min-width:800px}
        .gantt-table th{background:#333;color:#fff;padding:5px 3px;text-align:center;border:1px solid #000;position:sticky;top:0;z-index:10}
        .header-filter{position:sticky;left:0;z-index:15}
        .header-count{position:sticky;left:150px;z-index:15}
        .filter-cell{position:sticky;left:0;background:#f0f0f0;z-index:5;padding:3px;min-width:150px;font-size:10px}
        .count-cell{position:sticky;left:150px;background:#f0f0f0;z-index:5;padding:3px;text-align:center;min-width:50px}
        .gantt-table td{border:1px solid #999;padding:0}
        .date-cell{min-width:50px;font-size:9px}
        .operation-row{display:table;width:100%;height:15px}
        .operation-sub-row{display:table-row;height:5px}
        .operation-sub-row>div{display:table-cell;vertical-align:middle;text-align:center}
        .operation-sub-row.cut>div.filled{background:#ff4444}
        .operation-sub-row.corr>div.filled{background:#4444ff}
        .operation-sub-row.build>div.filled{background:#44ff44}
        
        /* Стили для перепланирования */
        .operation-sub-row>div{cursor:pointer;position:relative}
        .operation-sub-row>div:hover{opacity:0.8;transform:scale(1.1)}
        .operation-sub-row>div.dragging{opacity:0.5;z-index:1000}
        .operation-sub-row>div.drop-target{border:2px dashed #000;background:#ffff00}
        .operation-sub-row>div.invalid-drop{border:2px dashed #ff0000;background:#ffcccc}
        
        .replanning-controls{position:fixed;top:10px;right:10px;background:#fff;padding:10px;border:1px solid #ccc;border-radius:5px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
        .replanning-controls button{margin:2px;padding:5px 10px;border:1px solid #ccc;background:#f9f9f9;cursor:pointer}
        .replanning-controls button:hover{background:#e9e9e9}
        .replanning-controls button.active{background:#007cba;color:#fff}

        .legend{margin-bottom:10px;padding:5px;background:#fff;border:1px solid #ddd}
        .legend-item{display:inline-block;margin-right:15px;font-size:11px}
        .legend-color{display:inline-block;width:15px;height:10px;margin-right:3px;border:1px solid #999}
        .legend-color.cut{background:#ff4444}
        .legend-color.corr{background:#4444ff}
        .legend-color.build{background:#44ff44}
    </style>
</head>
<body>
    <div class="header">
        Комплексный план производства
        Заявка: <strong><?= htmlspecialchars($orderNumber) ?></strong>
        Всего фильтров: <strong><?= count($filters) ?></strong>
    </div>

    <div class="legend">
        <div style="margin-bottom:8px;font-size:12px">
            <strong>Отображаемые процессы:</strong>
            <label style="margin-left:15px;cursor:pointer;font-size:11px"><input type="checkbox" id="show-cut" checked onchange="toggleProcess()"> <span class="legend-color cut"></span> Порезка</label>
            <label style="margin-left:15px;cursor:pointer;font-size:11px"><input type="checkbox" id="show-corr" checked onchange="toggleProcess()"> <span class="legend-color corr"></span> Гофрирование</label>
            <label style="margin-left:15px;cursor:pointer;font-size:11px"><input type="checkbox" id="show-build" checked onchange="toggleProcess()"> <span class="legend-color build"></span> Сборка</label>
        </div>
    </div>

    <!-- Панель управления перепланированием -->
    <div class="replanning-controls">
        <div><strong>Перепланирование заявки:</strong></div>
        <button id="analyze-btn" onclick="analyzeCurrentState()">Анализ текущего состояния</button>
        <button id="replan-btn" onclick="showReplanModal()" disabled>Перепланировать с чистого листа</button>
        <div style="margin-top:5px;font-size:10px;color:#666">
            Перенос факта в план + новое планирование
        </div>
    </div>

    <!-- Модальное окно перепланирования -->
    <div id="replan-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000">
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px;border-radius:5px;max-width:600px;max-height:80vh;overflow-y:auto">
            <h3>Перепланирование заявки <?= htmlspecialchars($orderNumber) ?></h3>
            <div id="analysis-results"></div>
            <div style="margin:15px 0">
                <label><input type="checkbox" id="include-fact" checked> Перенести выполненные операции в план</label><br>
                <label><input type="checkbox" id="auto-distribute" checked> Автоматически распределить оставшиеся работы</label>
            </div>
            <div style="text-align:right">
                <button onclick="closeReplanModal()">Отмена</button>
                <button onclick="executeReplanning()" style="background:#007cba;color:#fff;margin-left:10px">Выполнить перепланирование</button>
            </div>
        </div>
    </div>

    <div class="gantt-container">
        <table class="gantt-table">
            <thead>
                <tr>
                    <th class="header-filter">Фильтр</th>
                    <th class="header-count">Кол-во</th>
                    <?php foreach ($allDates as $date): ?>
                        <th class="date-cell"><?= date('d.m', strtotime($date)) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filters as $filter): 
                    $filterBase = $filter['filter_base'];
                ?>
                    <tr>
                        <td class="filter-cell"><?= htmlspecialchars($filter['filter_full']) ?></td>
                        <td class="count-cell"><?= htmlspecialchars($filter['total_count']) ?></td>
                        <?php foreach ($allDates as $date): ?>
                            <td><div class="operation-row">
<div class="operation-sub-row cut"><div<?php 
$cutTitle = '';
$cutDataAttrs = '';
if (isset($cutDates[$filterBase]) && in_array($date, $cutDates[$filterBase])) {
    $baleId = $cutBales[$filterBase][$date] ?? '';
    $cutTitle = 'Порезка - ' . date('d.m.Y', strtotime($date)) . ($baleId ? ' (Бухта: ' . $baleId . ')' : '');
    $cutDataAttrs = ' data-process="cut" data-date="' . $date . '" data-filter="' . htmlspecialchars($filterBase) . '" data-bale="' . htmlspecialchars($baleId) . '"';
}
echo $cutTitle ? ' class="filled" title="' . htmlspecialchars($cutTitle) . '"' . $cutDataAttrs : '';
?>></div></div>
<div class="operation-sub-row corr"><div<?php 
$corrTitle = '';
$corrDataAttrs = '';
if (isset($corrDates[$filterBase]) && in_array($date, $corrDates[$filterBase])) {
    $quantity = $corrQuantities[$filterBase][$date] ?? '';
    $corrTitle = 'Гофрирование - ' . date('d.m.Y', strtotime($date)) . ($quantity ? ' (Кол-во: ' . $quantity . ')' : '');
    $corrDataAttrs = ' data-process="corr" data-date="' . $date . '" data-filter="' . htmlspecialchars($filterBase) . '" data-quantity="' . htmlspecialchars($quantity) . '"';
}
echo $corrTitle ? ' class="filled" title="' . htmlspecialchars($corrTitle) . '"' . $corrDataAttrs : '';
?>></div></div>
<div class="operation-sub-row build"><div<?php 
$buildTitle = '';
$buildDataAttrs = '';
if (isset($buildDates[$filterBase]) && in_array($date, $buildDates[$filterBase])) {
    $quantity = $buildQuantities[$filterBase][$date] ?? '';
    $buildTitle = 'Сборка - ' . date('d.m.Y', strtotime($date)) . ($quantity ? ' (Кол-во: ' . $quantity . ')' : '');
    $buildDataAttrs = ' data-process="build" data-date="' . $date . '" data-filter="' . htmlspecialchars($filterBase) . '" data-quantity="' . htmlspecialchars($quantity) . '"';
}
echo $buildTitle ? ' class="filled" title="' . htmlspecialchars($buildTitle) . '"' . $buildDataAttrs : '';
?>></div></div>
</div></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        // Глобальные переменные для перепланирования
        let analysisData = null;
        
        function toggleProcess() {
            const showCut = document.getElementById('show-cut').checked;
            const showCorr = document.getElementById('show-corr').checked;
            const showBuild = document.getElementById('show-build').checked;
            
            const cutRows = document.querySelectorAll('.operation-sub-row.cut');
            const corrRows = document.querySelectorAll('.operation-sub-row.corr');
            const buildRows = document.querySelectorAll('.operation-sub-row.build');
            
            cutRows.forEach(row => row.style.display = showCut ? 'table-row' : 'none');
            corrRows.forEach(row => row.style.display = showCorr ? 'table-row' : 'none');
            buildRows.forEach(row => row.style.display = showBuild ? 'table-row' : 'none');
            
            // Пересчитываем высоту строк: всегда 15px, но подстроки делят её пропорционально
            const visibleCount = (showCut ? 1 : 0) + (showCorr ? 1 : 0) + (showBuild ? 1 : 0);
            let subRowHeight;
            if (visibleCount === 3) {
                subRowHeight = 5; // 1/3 от 15px
            } else if (visibleCount === 2) {
                subRowHeight = 7.5; // 1/2 от 15px
            } else if (visibleCount === 1) {
                subRowHeight = 15; // 1/1 от 15px
            }
            
            cutRows.forEach(row => { if(showCut) row.style.height = subRowHeight + 'px'; });
            corrRows.forEach(row => { if(showCorr) row.style.height = subRowHeight + 'px'; });
            buildRows.forEach(row => { if(showBuild) row.style.height = subRowHeight + 'px'; });
        }
        
        function toggleEditMode() {
            editMode = !editMode;
            const btn = document.getElementById('edit-mode-btn');
            const saveBtn = document.getElementById('save-changes-btn');
            const cancelBtn = document.getElementById('cancel-changes-btn');
            
            if (editMode) {
                btn.textContent = 'Отключить редактирование';
                btn.classList.add('active');
                saveBtn.disabled = false;
                cancelBtn.disabled = false;
                initDragAndDrop();
            } else {
                btn.textContent = 'Редактирование';
                btn.classList.remove('active');
                saveBtn.disabled = true;
                cancelBtn.disabled = true;
                disableDragAndDrop();
            }
        }
        
        function initDragAndDrop() {
            const filledElements = document.querySelectorAll('.operation-sub-row > div.filled');
            
            filledElements.forEach(element => {
                element.draggable = true;
                element.addEventListener('dragstart', handleDragStart);
                element.addEventListener('dragend', handleDragEnd);
            });
            
            // Добавляем обработчики для всех ячеек (цели для drop)
            const allCells = document.querySelectorAll('.operation-sub-row > div');
            allCells.forEach(cell => {
                cell.addEventListener('dragover', handleDragOver);
                cell.addEventListener('drop', handleDrop);
                cell.addEventListener('dragenter', handleDragEnter);
                cell.addEventListener('dragleave', handleDragLeave);
            });
        }
        
        function disableDragAndDrop() {
            const allElements = document.querySelectorAll('.operation-sub-row > div');
            allElements.forEach(element => {
                element.draggable = false;
                element.removeEventListener('dragstart', handleDragStart);
                element.removeEventListener('dragend', handleDragEnd);
                element.removeEventListener('dragover', handleDragOver);
                element.removeEventListener('drop', handleDrop);
                element.removeEventListener('dragenter', handleDragEnter);
                element.removeEventListener('dragleave', handleDragLeave);
                element.classList.remove('drop-target', 'invalid-drop');
            });
        }
        
        function handleDragStart(e) {
            e.target.classList.add('dragging');
            e.dataTransfer.setData('text/plain', JSON.stringify({
                process: e.target.dataset.process,
                date: e.target.dataset.date,
                filter: e.target.dataset.filter,
                bale: e.target.dataset.bale,
                quantity: e.target.dataset.quantity
            }));
        }
        
        function handleDragEnd(e) {
            e.target.classList.remove('dragging');
            // Убираем все индикаторы drop
            document.querySelectorAll('.drop-target, .invalid-drop').forEach(el => {
                el.classList.remove('drop-target', 'invalid-drop');
            });
        }
        
        function handleDragOver(e) {
            e.preventDefault();
        }
        
        function handleDragEnter(e) {
            e.preventDefault();
            const draggedData = JSON.parse(e.dataTransfer.getData('text/plain') || '{}');
            const targetProcess = e.target.closest('.operation-sub-row').className.includes('cut') ? 'cut' : 
                                e.target.closest('.operation-sub-row').className.includes('corr') ? 'corr' : 'build';
            const targetDate = e.target.closest('td').previousElementSibling?.textContent || 
                              e.target.closest('td').querySelector('.date-cell')?.textContent;
            
            if (isValidDrop(draggedData.process, targetProcess, draggedData.date, targetDate)) {
                e.target.classList.add('drop-target');
            } else {
                e.target.classList.add('invalid-drop');
            }
        }
        
        function handleDragLeave(e) {
            e.target.classList.remove('drop-target', 'invalid-drop');
        }
        
        function handleDrop(e) {
            e.preventDefault();
            const draggedData = JSON.parse(e.dataTransfer.getData('text/plain'));
            const targetProcess = e.target.closest('.operation-sub-row').className.includes('cut') ? 'cut' : 
                                e.target.closest('.operation-sub-row').className.includes('corr') ? 'corr' : 'build';
            const targetDate = getDateFromCell(e.target);
            
            if (isValidDrop(draggedData.process, targetProcess, draggedData.date, targetDate)) {
                moveOperation(draggedData, targetProcess, targetDate);
            }
            
            e.target.classList.remove('drop-target', 'invalid-drop');
        }
        
        function isValidDrop(draggedProcess, targetProcess, fromDate, toDate) {
            // Проверяем зависимости процессов
            const dependencies = PROCESS_DEPENDENCIES[targetProcess];
            
            // Если перемещаем в зависимый процесс, проверяем что родительские процессы уже запланированы
            if (dependencies.length > 0) {
                for (let dep of dependencies) {
                    if (!hasOperationOnDate(dep, toDate)) {
                        return false;
                    }
                }
            }
            
            // Проверяем что целевая дата не раньше исходной для зависимых процессов
            if (dependencies.includes(draggedProcess)) {
                const fromDateObj = new Date(fromDate);
                const toDateObj = new Date(toDate);
                if (toDateObj < fromDateObj) {
                    return false;
                }
            }
            
            return true;
        }
        
        function hasOperationOnDate(process, date) {
            // Проверяем есть ли операция данного процесса на указанную дату
            const processElements = document.querySelectorAll(`[data-process="${process}"][data-date="${date}"]`);
            return processElements.length > 0;
        }
        
        function getDateFromCell(cell) {
            // Получаем дату из заголовка столбца
            const columnIndex = Array.from(cell.closest('tr').children).indexOf(cell.closest('td'));
            const headerRow = cell.closest('table').querySelector('thead tr');
            const dateHeader = headerRow.children[columnIndex];
            return dateHeader.textContent.trim();
        }
        
        function moveOperation(operationData, newProcess, newDate) {
            // Сохраняем изменение
            const changeKey = `${operationData.filter}_${operationData.process}_${operationData.date}`;
            pendingChanges[changeKey] = {
                filter: operationData.filter,
                process: operationData.process,
                oldDate: operationData.date,
                newDate: newDate,
                newProcess: newProcess,
                bale: operationData.bale,
                quantity: operationData.quantity
            };
            
            // Обновляем UI
            updateGanttDisplay();
        }
        
        function updateGanttDisplay() {
            // Здесь будет логика обновления отображения диаграммы Ганта
            console.log('Обновление отображения...', pendingChanges);
        }
        
        function saveAllChanges() {
            // Отправляем изменения на сервер
            fetch('save_replanning.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order: '<?= $orderNumber ?>',
                    changes: pendingChanges
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Изменения сохранены!');
                    location.reload();
                } else {
                    alert('Ошибка сохранения: ' + data.error);
                }
            });
        }
        
        function cancelAllChanges() {
            pendingChanges = {};
            location.reload();
        }
        
        // Автопечать если указан параметр print=1
        if (window.location.search.includes('print=1')) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>

