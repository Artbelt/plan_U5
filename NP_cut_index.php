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
            --bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card: rgba(255, 255, 255, 0.95);
            --card-hover: rgba(255, 255, 255, 1);
            --text: #1a202c;
            --muted: #718096;
            --line: rgba(226, 232, 240, 0.8);
            --ok: #38a169;
            --accent: #4299e1;
            --accent2: #63b3ed;
            --danger: #e53e3e;
            --warning: #ed8936;
            --shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        *{box-sizing:border-box}
        
        body{
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            margin: 0;
            padding: 20px;
            color: var(--text);
            min-height: 100vh;
        }
        
        .wrap{
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h2{
            text-align: center;
            margin: 0 0 32px 0;
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .modern-table{
            background: var(--card);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            border: 1px solid var(--line);
        }
        
        table{
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        th{
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 12px;
            text-align: center;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        td{
            padding: 16px 12px;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid var(--line);
            background: var(--card);
            transition: background-color 0.2s ease;
        }
        
        tr:hover td{
            background: rgba(255, 255, 255, 0.8);
        }
        
        .order-cell{
            text-align: left;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text);
        }
        
        .status-badge{
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--danger);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 8px;
        }
        
        .status-badge.normal{
            background: var(--ok);
        }
        
        .status-badge.replanning{
            background: var(--warning);
        }
        
        .actions{
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        .btn{
            padding: 8px 12px;
            border-radius: 8px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn:hover{
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.4);
        }
        
        .btn-secondary{
            background: rgba(66, 153, 225, 0.1);
            color: var(--accent);
            border: 1px solid rgba(66, 153, 225, 0.2);
        }
        
        .btn-secondary:hover{
            background: rgba(66, 153, 225, 0.2);
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.2);
        }
        
        .btn-print{
            background: rgba(99, 179, 237, 0.1);
            color: var(--accent2);
            border: 1px solid rgba(99, 179, 237, 0.2);
        }
        
        .btn-print:hover{
            background: rgba(99, 179, 237, 0.2);
            box-shadow: 0 4px 12px rgba(99, 179, 237, 0.2);
        }
        
        .btn-danger{
            background: rgba(229, 62, 62, 0.1);
            color: var(--danger);
            border: 1px solid rgba(229, 62, 62, 0.2);
        }
        
        .btn-danger:hover{
            background: rgba(229, 62, 62, 0.2);
            box-shadow: 0 4px 12px rgba(229, 62, 62, 0.2);
        }
        
        .stage-cell{
            text-align: left;
            padding: 12px;
        }
        
        .stage-status{
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .stage-status.done{
            color: var(--ok);
        }
        
        .stage-status.disabled{
            color: var(--muted);
        }
        
        .stage-actions{
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }
        
        .stage-description{
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 4px;
            line-height: 1.3;
        }
        
        .sub{
            display: block;
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 8px;
            line-height: 1.4;
        }
        
        @media (max-width: 1200px){
            .modern-table{
                overflow-x: auto;
            }
            
            table{
                min-width: 800px;
            }
            
            th, td{
                padding: 12px 8px;
            }
            
            .btn{
                padding: 6px 8px;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 768px){
            th, td{
                padding: 8px 6px;
            }
            
            .order-cell{
                font-size: 1rem;
            }
            
            .status-badge{
                font-size: 0.7rem;
                padding: 4px 8px;
            }
            
            .btn{
                padding: 4px 6px;
                font-size: 0.65rem;
            }
            
            .stage-description{
                font-size: 0.7rem;
            }
        }
        
        @media print{
            body{
                background: white;
                padding: 0;
            }
            
            .order-card{
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ccc;
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h2>Планирование заявок</h2>

    <div class="modern-table">
        <table>
            <thead>
                <tr>
                    <th>Заявка</th>
                    <th>📐 Раскрой</th>
                    <th>✅ Утверждение</th>
                    <th>📋 Планирование порезки</th>
                    <th>🌊 Планирование гофрирования</th>
                    <th>🔧 Планирование сборки</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): $ord = $o['order_number']; ?>
                    <tr>
                        <!-- Заявка -->
                        <td class="order-cell">
                            <div><?= htmlspecialchars($ord) ?></div>
                            <div class="status-badge <?= $o['status'] ?>">
                                <?php if ($o['status'] === 'replanning'): ?>
                                    ⚠️ Перепланирование
                                <?php else: ?>
                                    ✅ Обычная
                                <?php endif; ?>
                            </div>
                            <div class="actions">
                                <button class="btn-danger" onclick="clearOrder('<?= htmlspecialchars($ord, ENT_QUOTES) ?>')">
                                    🗑️ Очистить все планирование 
                                </button>
                                <button class="btn-secondary" onclick="toggleReplanning('<?= htmlspecialchars($ord, ENT_QUOTES) ?>', '<?= $o['status'] === 'replanning' ? 'normal' : 'replanning' ?>')">
                                    <?= $o['status'] === 'replanning' ? '✅ снять статус перепланирования' : '⚠️ Перепланирование' ?>
                                </button>
                                <button class="btn" onclick="fullReplanning('<?= htmlspecialchars($ord, ENT_QUOTES) ?>')">
                                    🔄 Полное перепланирование
                                </button>
                            </div>
                        </td>

                        <!-- Раскрой (подготовка) -->
                        <td class="stage-cell">
                            <?php if (!empty($o['cut_ready'])): ?>
                                <div class="stage-status done">✅ Готово</div>
                                <div class="stage-actions">
                                    <a class="btn-secondary" target="_blank" href="NP_cut_plan.php?order_number=<?= urlencode($ord) ?>">Открыть</a>
                                </div>
                            <?php else: ?>
                                <div class="stage-status disabled">⏳ Не готово</div>
                                <div class="stage-actions">
                                    <a class="btn" target="_blank" href="NP_cut_plan.php?order_number=<?= urlencode($ord) ?>">Сделать</a>
                                </div>
                                <div class="stage-description">нет данных</div>
                            <?php endif; ?>
                        </td>

                        <!-- Утверждение -->
                        <td class="stage-cell">
                            <?php if (empty($o['cut_ready'])): ?>
                                <div class="stage-status disabled">⏳ Не готово</div>
                            <?php elseif (!empty($o['cut_confirmed'])): ?>
                                <div class="stage-status done">✅ Утверждено</div>
                                <div class="stage-actions">
                                    <a class="btn-secondary" target="_blank" href="NP/print_cut.php?order=<?= urlencode($ord) ?>">Печать</a>
                                </div>
                            <?php else: ?>
                                <div class="stage-status disabled">⏳ Ожидает</div>
                                <div class="stage-actions">
                                    <a class="btn" href="NP/confirm_cut.php?order=<?= urlencode($ord) ?>">Утвердить</a>
                                </div>
                                <div class="stage-description">утвердите</div>
                            <?php endif; ?>
                        </td>

                        <!-- План раскроя рулона -->
                        <td class="stage-cell">
                            <?php if (empty($o['cut_confirmed'])): ?>
                                <div class="stage-status disabled">⏳ Не утверждено</div>
                            <?php elseif (!empty($o['plan_ready'])): ?>
                                <div class="stage-status done">✅ Готово</div>
                                <div class="stage-actions">
                                    <a class="btn-secondary" target="_blank" href="NP_view_roll_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                                    <a class="btn" href="NP_roll_plan.php?order=<?= urlencode($ord) ?>">Изменить</a>
                                </div>
                            <?php else: ?>
                                <div class="stage-status disabled">⏳ Ожидает</div>
                                <div class="stage-actions">
                                    <a class="btn" href="NP_roll_plan.php?order=<?= urlencode($ord) ?>">Планировать</a>
                                </div>
                                <div class="stage-description">планирование</div>
                            <?php endif; ?>
                        </td>

                        <!-- План гофрирования -->
                        <td class="stage-cell">
                            <?php if (empty($o['plan_ready'])): ?>
                                <div class="stage-status disabled">⏳ Нет плана</div>
                            <?php elseif (!empty($o['corr_ready'])): ?>
                                <div class="stage-status done">✅ Готово</div>
                                <div class="stage-actions">
                                    <a class="btn-secondary" target="_blank" href="NP_view_corrugation_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                                    <a class="btn" href="NP_corrugation_plan.php?order=<?= urlencode($ord) ?>">Изменить</a>
                                </div>
                            <?php else: ?>
                                <div class="stage-status disabled">⏳ Ожидает</div>
                                <div class="stage-actions">
                                    <a class="btn" href="NP_corrugation_plan.php?order=<?= urlencode($ord) ?>">Планировать</a>
                                </div>
                                <div class="stage-description">планирование</div>
                            <?php endif; ?>
                        </td>

                        <!-- План сборки -->
                        <td class="stage-cell">
                            <?php if (empty($o['corr_ready'])): ?>
                                <div class="stage-status disabled">⏳ Нет гофроплана</div>
                            <?php elseif (!empty($o['build_ready'])): ?>
                                <div class="stage-status done">✅ Готово</div>
                                <div class="stage-actions">
                                    <a class="btn-secondary" target="_blank" href="view_production_plan.php?order=<?= urlencode($ord) ?>">План</a>
                                    <a class="btn-print" target="_blank" href="NP_build_tasks.php?order=<?= urlencode($ord) ?>">Задание</a>
                                    <a class="btn" href="NP_build_plan.php?order=<?= urlencode($ord) ?>">Изменить</a>
                                </div>
                            <?php else: ?>
                                <div class="stage-status disabled">⏳ Ожидает</div>
                                <div class="stage-actions">
                                    <a class="btn" href="NP_build_plan.php?order=<?= urlencode($ord) ?>">Планировать</a>
                                </div>
                                <div class="stage-description">планирование</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Изменение статуса перепланирования
    async function toggleReplanning(order, newStatus){
        const action = newStatus === 'replanning' ? 'установить' : 'снять статус перепланирования';
        if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} статус "Перепланирование" для заявки ${order}?`)) return;
        
        try{
            const res = await fetch('NP_cut_index.php?action=change_status', {
                method: 'POST',
                headers: {'Content-Type':'application/json', 'Accept':'application/json'},
                body: JSON.stringify({order, status: newStatus})
            });
            let data;
            try{ data = await res.json(); }
            catch(e){
                const t = await res.text();
                throw new Error('Backend вернул не JSON:\n'+t.slice(0,500));
            }
            if (!data.ok) throw new Error(data.error || 'unknown');
            
            alert(`Статус изменен на: ${newStatus === 'replanning' ? 'Перепланирование' : 'Обычный'}`);
            location.reload();
        }catch(e){
            alert('Не удалось изменить статус: '+e.message);
        }
    }

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

    // Полное перепланирование "с чистого листа"
    async function fullReplanning(order){
        const message = `Выполнить полное перепланирование заявки ${order}?\n\n` +
                       `Что будет сделано:\n` +
                       `1. ✅ Выполненные операции останутся в плане\n` +
                       `2. ✅ Выполненные бухты (done=1) не будут удалены\n` +
                       `3. 🗑️ Будущие планы будут очищены (кроме cut_plans)\n` +
                       `4. ⚠️ Статус заявки изменится на "Перепланирование"\n` +
                       `5. 🔄 Статусы операций сбросятся (порезка, гофрирование, сборка)\n` +
                       `6. 📋 Оставшиеся работы нужно будет запланировать вручную\n\n` +
                       `Это займет несколько секунд...`;
        
        if (!confirm(message)) return;
        
        try{
            const res = await fetch('NP_cut_index.php?action=full_replanning', {
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
            
            // Показываем детальный отчет
            const results = data.results;
            let report = `✅ Перепланирование завершено!\n\n`;
            
            // Факт → план
            if (results.fact_to_plan) {
                report += `📊 Перенесено в план (выполненные операции):\n`;
                Object.keys(results.fact_to_plan).forEach(table => {
                    if (results.fact_to_plan[table].length > 0) {
                        report += `  ${table}: ${results.fact_to_plan[table].length} фильтров\n`;
                    }
                });
                report += `\n`;
            }
            
            // Очищенные будущие планы
            if (results.cleared_future) {
                const totalCleared = results.cleared_future.reduce((a, b) => a + b, 0);
                report += `🗑️ Очищено будущих планов: ${totalCleared} записей\n`;
            }
            
            // Статус заявки и операций
            if (results.status_updated > 0) {
                report += `⚠️ Статус заявки изменен на "Перепланирование"\n`;
                report += `🔄 Статусы операций сброшены (порезка, гофрирование, сборка)\n\n`;
            }
            
            // Остатки работ для ручного планирования
            if (results.remaining_work) {
                report += `📋 Осталось запланировать вручную:\n`;
                Object.keys(results.remaining_work).forEach(process => {
                    const items = results.remaining_work[process] || [];
                    if (items.length > 0) {
                        const totalCount = items.reduce((sum, item) => sum + item.count, 0);
                        report += `  ${process}: ${items.length} фильтров (${totalCount} шт.)\n`;
                    }
                });
                report += `\n💡 Теперь можно вручную запланировать оставшиеся работы.`;
            }
            
            alert(report);
            location.reload();
            
        }catch(e){
            alert('Ошибка перепланирования: '+e.message);
        }
    }
</script>
</body>
</html>
