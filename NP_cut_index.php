<?php
// NP_cut_index.php
$dsn = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root"; $pass = "";

/* ================= AJAX: CHANGE STATUS ================= */
if (isset($_GET['action']) && $_GET['action'] === 'change_status') {
    header('Content-Type: application/json; charset=utf-8');
    try{
        $raw = file_get_contents('php://input');
        $in  = json_decode($raw, true);
        $order = $in['order'] ?? ($_POST['order'] ?? '');
        $status = $in['status'] ?? ($_POST['status'] ?? '');
        
        if ($order === '' || $status === '') { 
            http_response_code(400); 
            echo json_encode(['ok'=>false,'error'=>'no order or status']); 
            exit; 
        }

        $pdo = new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);

        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_number = ?");
        $stmt->execute([$status, $order]);
        
        echo json_encode(['ok'=>true,'status'=>$status]); 
        exit;
    }catch(Throwable $e){
        http_response_code(500); 
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); 
        exit;
    }
}

/* ================= AJAX: FULL REPLANNING ================= */
if (isset($_GET['action']) && $_GET['action'] === 'full_replanning') {
    header('Content-Type: application/json; charset=utf-8');
    try{
        $raw = file_get_contents('php://input');
        $in  = json_decode($raw, true);
        $order = $in['order'] ?? ($_POST['order'] ?? '');
        if ($order === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

        $pdo = new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);
        $pdo->beginTransaction();

        $currentDate = date('Y-m-d');
        $results = ['fact_to_plan' => [], 'cleared_future' => [], 'new_planning' => []];

        // 1. Переносим выполненные операции в план (факт → план)
        $factOperations = [
            'cut_plans' => "SELECT DISTINCT filter, SUM(fact_length) as total_fact FROM cut_plans WHERE order_number = ? AND fact_length > 0 GROUP BY filter",
            'corrugation_plan' => "SELECT DISTINCT filter_label as filter, SUM(fact_count) as total_fact FROM corrugation_plan WHERE order_number = ? AND fact_count > 0 GROUP BY filter_label",
            'build_plan' => "SELECT DISTINCT filter, SUM(fact_count) as total_fact FROM build_plan WHERE order_number = ? AND fact_count > 0 GROUP BY filter"
        ];

        foreach ($factOperations as $table => $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$order]);
            $facts = $stmt->fetchAll();
            $results['fact_to_plan'][$table] = $facts;
        }

        // 2. Очищаем будущие планы (начиная с текущей даты) - ИСКЛЮЧАЕМ cut_plans
        // Для roll_plans не трогаем записи с done=1 (выполненные)
        $clearFuture = [
            "DELETE FROM roll_plans WHERE order_number = ? AND work_date >= ? AND (done IS NULL OR done = 0)", 
            "DELETE FROM corrugation_plan WHERE order_number = ? AND plan_date >= ?",
            "DELETE FROM build_plan WHERE order_number = ? AND plan_date >= ?"
        ];

        foreach ($clearFuture as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$order, $currentDate]);
            $results['cleared_future'][] = $stmt->rowCount();
        }

        // 2.5. Устанавливаем статусы "replanning" для заявки и операций
        $stmt = $pdo->prepare("UPDATE orders SET status = 'replanning', plan_ready = 0, corr_ready = 0, build_ready = 0 WHERE order_number = ?");
        $stmt->execute([$order]);
        $results['status_updated'] = $stmt->rowCount();

        // 3. Рассчитываем остатки работ для информации (без автоматического планирования)
        $remainingWork = $pdo->prepare("
            SELECT filter, count as total_planned, 
                   COALESCE((SELECT SUM(fact_length) FROM cut_plans WHERE order_number = o.order_number AND filter = o.filter), 0) as cut_fact,
                   COALESCE((SELECT SUM(fact_count) FROM corrugation_plan WHERE order_number = o.order_number AND filter_label = o.filter), 0) as corr_fact,
                   COALESCE((SELECT SUM(fact_count) FROM build_plan WHERE order_number = o.order_number AND filter = o.filter), 0) as build_fact
            FROM orders o 
            WHERE o.order_number = ? AND (o.hide IS NULL OR o.hide != 1)
        ");
        $remainingWork->execute([$order]);
        $remaining = $remainingWork->fetchAll();

        // Сохраняем информацию об остатках для отчета
        foreach ($remaining as $row) {
            $cutRemaining = max(0, $row['total_planned'] - $row['cut_fact']);
            $corrRemaining = max(0, $row['total_planned'] - $row['corr_fact']); 
            $buildRemaining = max(0, $row['total_planned'] - $row['build_fact']);
            
            if ($cutRemaining > 0) {
                $results['remaining_work']['cut'][] = ['filter' => $row['filter'], 'count' => $cutRemaining];
            }
            if ($corrRemaining > 0) {
                $results['remaining_work']['corr'][] = ['filter' => $row['filter'], 'count' => $corrRemaining];
            }
            if ($buildRemaining > 0) {
                $results['remaining_work']['build'][] = ['filter' => $row['filter'], 'count' => $buildRemaining];
            }
        }

        $pdo->commit();
        echo json_encode(['ok'=>true,'results'=>$results]); exit;
    }catch(Throwable $e){
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
    }
}

/* ================= AJAX: CLEAR ================= */
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    header('Content-Type: application/json; charset=utf-8');
    try{
        $raw = file_get_contents('php://input');
        $in  = json_decode($raw, true);
        $order = $in['order'] ?? ($_POST['order'] ?? '');
        if ($order === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'no order']); exit; }

        $pdo = new PDO($dsn,$user,$pass,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
        ]);
        $pdo->beginTransaction();

        $aff = ['cut_plans'=>0,'roll_plans'=>0,'corr'=>0,'build'=>0,'orders'=>0];

        // Удаления (таблицы из текущего проекта)
        foreach ([
                     ['sql'=>"DELETE FROM build_plan WHERE order_number=?", 'key'=>'build'],
                     ['sql'=>"DELETE FROM corrugation_plan WHERE order_number=?", 'key'=>'corr'],
                     ['sql'=>"DELETE FROM roll_plans WHERE order_number=?", 'key'=>'roll_plans'],
                     ['sql'=>"DELETE FROM cut_plans WHERE order_number=?", 'key'=>'cut_plans'],
                 ] as $q){
            try{
                $st = $pdo->prepare($q['sql']);
                $st->execute([$order]);
                $aff[$q['key']] = $st->rowCount();
            } catch(Throwable $e){
                // если таблицы нет — молча игнорируем
            }
        }

        // Сброс статусов в orders (только существующие поля)
        $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders'")->fetchAll(PDO::FETCH_COLUMN);
        $want = ['cut_ready','cut_confirmed','plan_ready','corr_ready','build_ready'];
        $set  = [];
        foreach($want as $c){ if(in_array($c,$cols,true)) $set[] = "$c=0"; }
        if ($set){
            $sql = "UPDATE orders SET ".implode(',', $set)." WHERE order_number=?";
            $st  = $pdo->prepare($sql); $st->execute([$order]);
            $aff['orders'] = $st->rowCount();
        }

        $pdo->commit();
        echo json_encode(['ok'=>true,'aff'=>$aff]); exit;
    }catch(Throwable $e){
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
    }
}

/* ================= PAGE ================= */
try{
    $pdo = new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);

    // Статусы заявок
    $orders = $pdo->query("
        SELECT DISTINCT order_number, cut_ready, cut_confirmed, plan_ready, corr_ready, build_ready, status
        FROM orders
        WHERE hide IS NULL OR hide != 1
        ORDER BY order_number
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Заявки, по которым уже есть гофроплан
    $stmt = $pdo->query("SELECT DISTINCT order_number FROM corrugation_plan");
    $corr_done = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

} catch(Throwable $e){
    http_response_code(500); exit("Ошибка БД: ".htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Этапы планирования</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --bg:#f6f7fb; --card:#ffffff; --text:#111827; --muted:#6b7280;
            --line:#e5e7eb; --ok:#16a34a; --accent:#2563eb; --accent2:#0ea5e9;
        }
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);margin:20px;color:var(--text)}
        h2{text-align:center;margin:6px 0 16px}
        .wrap{max-width:1200px;margin:0 auto}
        table{border-collapse:collapse;width:100%;background:#fff;border:1px solid var(--line);box-shadow:0 2px 10px rgba(0,0,0,.05)}
        th,td{border:1px solid var(--line);padding:10px 12px;text-align:center;vertical-align:middle}
        th{background:#f3f4f6;font-weight:600}
        td:first-child{font-weight:600}
        .btn{padding:6px 10px;border-radius:8px;background:var(--accent);color:#fff;text-decoration:none;border:1px solid var(--accent)}
        .btn:hover{filter:brightness(.95)}
        .btn-secondary{background:#eef6ff;color:var(--accent);border-color:#cfe0ff}
        .btn-print{background:#ecfeff;color:var(--accent2);border-color:#bae6fd}
        .btn-danger{background:#fee;color:#dc2626;border-color:#fecaca}
        .btn-danger:hover{filter:brightness(.95)}
        .done{color:var(--ok);font-weight:700}
        .disabled{color:#9ca3af}
        .stack{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
        .sub{display:block;font-size:12px;color:var(--muted);margin-top:4px}
        @media (max-width:900px){
            table{font-size:13px}
            .stack{gap:6px}
            .btn,.btn-secondary,.btn-print{padding:6px 8px}
        }
        @media print{
            body{background:#fff;margin:0}
            .wrap{max-width:none}
        }
        
        /* Кнопка анализа */
        .btn-analysis{
            padding:4px 8px;
            border-radius:6px;
            background:#f0fdf4;
            color:#16a34a;
            border:1px solid #bbf7d0;
            cursor:pointer;
            font-size:14px;
            transition:all .15s;
        }
        .btn-analysis:hover{
            background:#dcfce7;
            transform:scale(1.05);
        }
        
        /* Модальное окно */
        .modal{
            display:none;
            position:fixed;
            inset:0;
            background:rgba(0,0,0,0.6);
            z-index:1000;
            align-items:center;
            justify-content:center;
            padding:20px;
        }
        .modal-content{
            background:#fff;
            border-radius:16px;
            box-shadow:0 20px 60px rgba(0,0,0,0.3);
            max-width:900px;
            width:100%;
            max-height:90vh;
            overflow-y:auto;
            animation:slideIn .3s ease-out;
        }
        @keyframes slideIn{
            from{transform:translateY(-30px);opacity:0}
            to{transform:translateY(0);opacity:1}
        }
        .modal-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:20px 24px;
            border-bottom:2px solid #e5e7eb;
        }
        .modal-header h3{
            margin:0;
            font-size:20px;
            color:#111827;
        }
        .modal-close{
            background:none;
            border:none;
            font-size:28px;
            cursor:pointer;
            color:#9ca3af;
            width:36px;
            height:36px;
            display:flex;
            align-items:center;
            justify-content:center;
            border-radius:50%;
            transition:all .15s;
        }
        .modal-close:hover{
            background:#fef3f2;
            color:#f87171;
        }
        .modal-body{
            padding:20px;
            background:#fafafa;
        }
        .info-grid{
            display:grid;
            grid-template-columns:repeat(3, 1fr);
            gap:10px;
            margin-bottom:16px;
        }
        .info-card{
            background:#ffffff;
            border:1px solid #e5e7eb;
            border-radius:6px;
            padding:12px;
            transition:box-shadow .15s;
        }
        .info-card:hover{
            box-shadow:0 2px 8px rgba(0,0,0,0.08);
        }
        .info-card h4{
            margin:0 0 6px;
            font-size:10px;
            color:#9ca3af;
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:0.5px;
        }
        .info-value{
            font-size:24px;
            font-weight:700;
            color:#111827;
            line-height:1;
        }
        .info-label{
            font-size:10px;
            color:#9ca3af;
            margin-top:4px;
        }
        .section-block{
            margin-top:12px;
            padding:14px;
            background:#ffffff;
            border:1px solid #e5e7eb;
            border-radius:6px;
        }
        .section-title{
            margin:0 0 10px;
            font-size:11px;
            color:#6b7280;
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:0.5px;
        }
        .heights-grid{
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(140px, 1fr));
            gap:8px;
        }
        .height-tile{
            background:#fafafa;
            border:1px solid #e5e7eb;
            border-radius:6px;
            padding:10px;
            transition:all .15s;
        }
        .height-tile:hover{
            background:#ffffff;
            box-shadow:0 2px 6px rgba(0,0,0,0.06);
        }
    </style>
</head>
<body>
<div class="wrap">
    <h2>Планирование заявок</h2>

        <table>
                <tr>
                    <th>Заявка</th>
            <th>Раскрой (подготовка)</th>
            <th>План раскроя рулона</th>
            <th>План гофрирования</th>
            <th>План сборки</th>
                </tr>
                <?php foreach ($orders as $o): $ord = $o['order_number']; ?>
                    <tr>
                <td>
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                        <strong><?= htmlspecialchars($ord) ?></strong>
                        <button class="btn-analysis" onclick="showAnalysis('<?= htmlspecialchars($ord, ENT_QUOTES) ?>')" title="Анализ заявки">📊</button>
                            </div>
                    <div class="stack">
                        <button class="btn-danger" onclick="clearOrder('<?= htmlspecialchars($ord, ENT_QUOTES) ?>')">Очистить всё</button>
                            </div>
                    <span class="sub">Удалит раскрой, раскладку по дням, гофро- и сборочный планы, а также сбросит статусы.</span>
                        </td>

                        <!-- Раскрой (подготовка) -->
                <td>
                    <?php if ($o['cut_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-print" target="_blank" href="NP/print_cut_report.php?order=<?= urlencode($ord) ?>">Печать</a>
                            <a class="btn-secondary" href="#" onclick="editCutPlan('<?= htmlspecialchars($ord, ENT_QUOTES) ?>'); return false;">Изменить</a>
                                </div>
                            <?php else: ?>
                        <div class="stack">
                                    <a class="btn" target="_blank" href="NP_cut_plan.php?order_number=<?= urlencode($ord) ?>">Сделать</a>
                                </div>
                        <span class="sub">нет данных для просмотра</span>
                            <?php endif; ?>
                        </td>

                        <!-- План раскроя рулона -->
                <td>
                    <?php if (!$o['cut_ready']): ?>
                        <span class="disabled">Раскрой не готов</span>
                    <?php elseif ($o['plan_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                                    <a class="btn-secondary" target="_blank" href="NP_view_roll_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                                    <a class="btn-secondary" href="#" onclick="editRollPlan('<?= htmlspecialchars($ord, ENT_QUOTES) ?>'); return false;">Изменить</a>
                                </div>
                            <?php else: ?>
                        <div class="stack">
                                    <a class="btn" href="NP_roll_plan.php?order=<?= urlencode($ord) ?>">Планировать</a>
                                </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                            <?php endif; ?>
                        </td>

                        <!-- План гофрирования -->
                <td>
                    <?php if (!$o['plan_ready']): ?>
                        <span class="disabled">Не готов план раскроя</span>
                    <?php elseif ($o['corr_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                                    <a class="btn-secondary" target="_blank" href="NP_view_corrugation_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                                </div>
                            <?php else: ?>
                        <div class="stack">
                                    <a class="btn" href="NP_corrugation_plan.php?order=<?= urlencode($ord) ?>">Планировать</a>
                                </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                            <?php endif; ?>
                        </td>

                        <!-- План сборки -->
                <td>
                    <?php if (!$o['corr_ready']): ?>
                        <span class="disabled">Нет гофроплана</span>
                    <?php elseif ($o['build_ready']): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="view_production_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                                </div>
                            <?php else: ?>
                        <div class="stack">
                                    <a class="btn" href="NP_build_plan.php?order=<?= urlencode($ord) ?>">Планировать</a>
                                </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
        </table>
</div>

<!-- Модальное окно анализа -->
<div id="analysisModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Анализ заявки</h3>
            <button class="modal-close" onclick="closeAnalysis()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div style="text-align:center;padding:40px;color:#9ca3af;">
                <p>Загрузка данных...</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Очистка плана целиком
    async function clearOrder(order){
        if (!confirm('Очистить ВСЁ планирование по заявке '+order+'?\nБудут удалены: раскрой, раскладка по дням, гофро- и сборочный планы.\nСтатусы заявки будут сброшены.')) return;
        try{
            const res = await fetch('NP_cut_index.php?action=clear', {
                method: 'POST',
                headers: {'Content-Type':'application/json', 'Accept':'application/json'},
                body: JSON.stringify({order})
            });
            let data;
            try{ data = await res.json(); }
            catch(e){
                const t = await res.text();
                throw new Error('Backend вернул не JSON:\n'+t.slice(0,500));
            }
            if (!data.ok) throw new Error(data.error || 'unknown');
            alert('Готово. Удалено записей:\n' +
                'cut_plans: '+(data.aff?.cut_plans ?? 0)+'\n' +
                'roll_plans: '+(data.aff?.roll_plans ?? 0)+'\n' +
                'corrugation_plan: '+(data.aff?.corr ?? 0)+'\n' +
                'build_plan: '+(data.aff?.build ?? 0));
            location.reload();
        }catch(e){
            alert('Не удалось очистить: '+e.message);
        }
    }
    
    // Редактирование раскроя с предупреждением
    function editCutPlan(order){
        if (confirm(
            '⚠️ ВНИМАНИЕ!\n\n' +
            'При редактировании раскроя нарушится синхронизация с остальными частями плана:\n\n' +
            '• План раскроя рулона\n' +
            '• План гофрирования\n' +
            '• План сборки\n\n' +
            'Вероятно, их придется переделывать заново.\n\n' +
            'Продолжить редактирование?'
        )) {
            window.open('NP_cut_plan.php?order_number=' + encodeURIComponent(order), '_blank');
        }
    }
    
    // Редактирование плана раскроя рулона с предупреждением
    function editRollPlan(order){
        if (confirm(
            '⚠️ ВНИМАНИЕ!\n\n' +
            'При редактировании плана раскроя рулона нарушится синхронизация с последующими этапами:\n\n' +
            '• План гофрирования\n' +
            '• План сборки\n\n' +
            'Вероятно, их придется переделывать заново.\n\n' +
            'Продолжить редактирование?'
        )) {
            window.open('NP_roll_plan.php?order=' + encodeURIComponent(order), '_blank');
        }
    }
    
    // Анализ заявки
    async function showAnalysis(order){
        const modal = document.getElementById('analysisModal');
        const title = document.getElementById('modalTitle');
        const body = document.getElementById('modalBody');
        
        title.textContent = 'Анализ заявки ' + order;
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#9ca3af;"><p>Загрузка данных...</p></div>';
        modal.style.display = 'flex';
        
        try {
            const response = await fetch('NP/get_order_analysis.php?order=' + encodeURIComponent(order));
            const data = await response.json();
            
            if (!data.ok) {
                body.innerHTML = '<div style="text-align:center;padding:40px;color:#dc2626;"><p>Ошибка загрузки данных</p></div>';
                return;
            }
            
            // Формируем HTML с информацией
            let html = '<div class="info-grid">';
            
            // Общая информация
            html += `
                <div class="info-card">
                    <h4>Всего фильтров</h4>
                    <div class="info-value">${data.total_filters || 0}</div>
                    <div class="info-label">в заявке</div>
                </div>
                <div class="info-card">
                    <h4>Уникальных позиций</h4>
                    <div class="info-value">${data.unique_filters || 0}</div>
                    <div class="info-label">типов фильтров</div>
                </div>
                <div class="info-card">
                    <h4>Бухты</h4>
                    <div class="info-value">${data.bales_count || 0}</div>
                    <div class="info-label">в раскрое</div>
                </div>
            `;
            
            // Прогресс по этапам
            if (data.progress) {
                html += `
                    <div class="info-card">
                        <h4>Раскрой</h4>
                        <div class="info-value">${data.progress.cut || 0}%</div>
                        <div class="info-label">выполнено</div>
                    </div>
                    <div class="info-card">
                        <h4>Гофрирование</h4>
                        <div class="info-value">${data.progress.corr || 0}%</div>
                        <div class="info-label">выполнено</div>
                    </div>
                    <div class="info-card">
                        <h4>Сборка</h4>
                        <div class="info-value">${data.progress.build || 0}%</div>
                        <div class="info-label">выполнено</div>
                    </div>
                `;
            }
            
            html += '</div>';
            
            // Распределение по высотам плиткой
            if (data.heights && data.heights.length > 0) {
                html += '<div class="section-block">';
                html += '<div class="section-title">Распределение по высотам</div>';
                html += '<div class="heights-grid">';
                
                data.heights.forEach(h => {
                    const complexCount = parseInt(h.complex_filters) || 0;
                    const totalCount = parseInt(h.total_filters) || 0;
                    const complexPercent = totalCount > 0 ? Math.round((complexCount / totalCount) * 100) : 0;
                    
                    html += `
                        <div class="height-tile">
                            <div style="text-align:center;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #e5e7eb;">
                                <div style="font-size:20px;font-weight:700;color:#111827;line-height:1;">${h.height}</div>
                                <div style="font-size:9px;color:#9ca3af;text-transform:uppercase;letter-spacing:0.5px;margin-top:2px;">высота</div>
                            </div>
                            <div style="text-align:center;margin-bottom:6px;">
                                <div style="font-size:18px;font-weight:700;color:#111827;">${totalCount}</div>
                                <div style="font-size:10px;color:#6b7280;">фильтров</div>
                            </div>
                            ${complexCount > 0 ? `
                                <div style="text-align:center;padding:4px;background:#ffffff;border-radius:4px;border:1px solid #e5e7eb;margin-bottom:6px;">
                                    <div style="font-size:11px;font-weight:600;color:#374151;">сложных: ${complexCount}</div>
                                    <div style="font-size:9px;color:#9ca3af;">${complexPercent}%</div>
                                </div>
                            ` : ''}
                            <div style="font-size:9px;color:#9ca3af;text-align:center;">
                                ${h.strips_count} полос • ${h.unique_filters} типов
                            </div>
                        </div>
                    `;
                });
                
                html += '</div></div>';
            }
            
            // Анализ сложности и период - в одной строке
            html += '<div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;">';
            
            // Анализ сложности
            if (data.complexity && (data.complexity.simple_count > 0 || data.complexity.complex_count > 0)) {
                const total = parseInt(data.complexity.simple_count) + parseInt(data.complexity.complex_count);
                const simplePercent = total > 0 ? Math.round((data.complexity.simple_count / total) * 100) : 0;
                const complexPercent = total > 0 ? Math.round((data.complexity.complex_count / total) * 100) : 0;
                
                html += '<div class="section-block" style="margin-top:0;">';
                html += '<div class="section-title">Анализ сложности сборки</div>';
                html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">';
                html += `
                    <div style="background:#fafafa;padding:10px;border-radius:4px;border:1px solid #e5e7eb;text-align:center;">
                        <div style="font-size:9px;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;letter-spacing:0.5px;">Простые (≥600)</div>
                        <div style="font-size:20px;font-weight:700;color:#111827;line-height:1;">${data.complexity.simple_count}</div>
                        <div style="font-size:10px;color:#9ca3af;margin-top:2px;">${simplePercent}%</div>
                    </div>
                    <div style="background:#fafafa;padding:10px;border-radius:4px;border:1px solid #e5e7eb;text-align:center;">
                        <div style="font-size:9px;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;letter-spacing:0.5px;">Сложные (<600)</div>
                        <div style="font-size:20px;font-weight:700;color:#111827;line-height:1;">${data.complexity.complex_count}</div>
                        <div style="font-size:10px;color:#9ca3af;margin-top:2px;">${complexPercent}%</div>
                    </div>
                `;
                html += '</div>';
                
                if (data.complexity.avg_complexity) {
                    html += '<div style="font-size:10px;color:#6b7280;padding:8px;background:#fafafa;border-radius:4px;border:1px solid #e5e7eb;text-align:center;">';
                    html += `<strong style="color:#374151;">Средняя:</strong> ${parseFloat(data.complexity.avg_complexity).toFixed(1)} `;
                    html += `<span style="color:#9ca3af;">(${data.complexity.min_complexity || 0}—${data.complexity.max_complexity || 0})</span>`;
                    html += '</div>';
                }
                html += '</div>';
            }
            
            // Период планирования
            if (data.dates && data.dates.start_date) {
                html += '<div class="section-block" style="margin-top:0;">';
                html += '<div class="section-title">Период планирования</div>';
                const startDate = new Date(data.dates.start_date).toLocaleDateString('ru-RU');
                const endDate = new Date(data.dates.end_date).toLocaleDateString('ru-RU');
                html += `<div style="font-size:13px;color:#374151;font-weight:500;text-align:center;padding:20px 10px;">${startDate}<br>—<br>${endDate}</div>`;
                html += '</div>';
            }
            
            html += '</div>';
            
            body.innerHTML = html;
            
        } catch (error) {
            console.error('Ошибка загрузки анализа:', error);
            body.innerHTML = '<div style="text-align:center;padding:40px;color:#dc2626;"><p>Ошибка: ' + error.message + '</p></div>';
        }
    }
    
    function closeAnalysis(){
        document.getElementById('analysisModal').style.display = 'none';
    }
    
    // Закрытие по клику вне модального окна
    document.getElementById('analysisModal').addEventListener('click', function(e){
        if (e.target === this) closeAnalysis();
    });
</script>
</body>
</html>
