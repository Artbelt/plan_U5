<?php
// NP_cut_index.php
$dsn = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root"; $pass = "";

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
        SELECT DISTINCT order_number, cut_ready, cut_confirmed, plan_ready, corr_ready, build_ready
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
            --line:#e5e7eb; --ok:#16a34a; --accent:#2563eb; --accent2:#0ea5e9; --danger:#dc2626;
        }
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);margin:20px;color:var(--text)}
        h2{text-align:center;margin:6px 0 16px}
        .wrap{max-width:1200px;margin:0 auto}
        table{border-collapse:collapse;width:100%;background:#fff;border:1px solid var(--line);box-shadow:0 2px 10px rgba(0,0,0,.05)}
        th,td{border:1px solid var(--line);padding:10px 12px;text-align:center;vertical-align:middle}
        th{background:#f3f4f6;font-weight:600}
        td:first-child{font-weight:600}
        .stack{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
        .btn{padding:6px 10px;border-radius:8px;background:var(--accent);color:#fff;text-decoration:none;border:1px solid var(--accent);cursor:pointer}
        .btn:hover{filter:brightness(.95)}
        .btn-secondary{background:#eef6ff;color:var(--accent);border-color:#cfe0ff}
        .btn-print{background:#ecfeff;color:var(--accent2);border-color:#bae6fd}
        .btn-danger{background:#fee2e2;color:var(--danger);border-color:#fecaca}
        .btn-danger:hover{filter:brightness(.97)}
        .done{color:var(--ok);font-weight:700}
        .disabled{color:#9ca3af}
        .sub{display:block;font-size:12px;color:var(--muted);margin-top:4px}
        @media (max-width:900px){
            table{font-size:13px}
            .stack{gap:6px}
            .btn,.btn-secondary,.btn-print,.btn-danger{padding:6px 8px}
        }
        @media print{
            body{background:#fff;margin:0}
            .wrap{max-width:none}
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
            <th>Утверждение</th>
            <th>План раскроя рулона</th>
            <th>План гофрирования</th>
            <th>План сборки</th>
        </tr>

        <?php foreach ($orders as $o): $ord = $o['order_number']; ?>
            <tr>
                <td>
                    <?= htmlspecialchars($ord) ?>
                    <div class="stack" style="margin-top:6px">
                        <button class="btn-danger" onclick="clearOrder('<?= htmlspecialchars($ord, ENT_QUOTES) ?>')">Очистить всё</button>
                    </div>
                    <span class="sub">Удалит раскрой, раскладку по дням, гофро- и сборочный планы, а также сбросит статусы.</span>
                </td>

                <!-- Раскрой (подготовка) -->
                <td>
                    <?php if (!empty($o['cut_ready'])): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="NP_cut_plan.php?order_number=<?= urlencode($ord) ?>">Открыть</a>
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" target="_blank" href="NP_cut_plan.php?order_number=<?= urlencode($ord) ?>">Сделать раскрой</a>
                        </div>
                        <span class="sub">нет данных для просмотра</span>
                    <?php endif; ?>
                </td>

                <!-- Утверждение -->
                <td>
                    <?php if (empty($o['cut_ready'])): ?>
                        <span class="disabled">Не готово</span>
                    <?php elseif (!empty($o['cut_confirmed'])): ?>
                        <div class="done">✅ Утверждено</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="NP/print_cut.php?order=<?= urlencode($ord) ?>">Печать</a>
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP/confirm_cut.php?order=<?= urlencode($ord) ?>">Утвердить</a>
                        </div>
                        <span class="sub">утвердите, затем появится печать</span>
                    <?php endif; ?>
                </td>

                <!-- План раскроя рулона -->
                <td>
                    <?php if (empty($o['cut_confirmed'])): ?>
                        <span class="disabled">Не утверждено</span>
                    <?php elseif (!empty($o['plan_ready'])): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="NP_view_roll_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP_roll_plan.php?order=<?= urlencode($ord) ?>">Планировать раскрой</a>
                        </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                    <?php endif; ?>
                </td>

                <!-- План гофрирования -->
                <td>
                    <?php if (empty($o['plan_ready'])): ?>
                        <span class="disabled">Не готов план раскроя</span>
                    <?php elseif (isset($corr_done[$ord])): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="NP_view_corrugation_plan.php?order=<?= urlencode($ord) ?>">Просмотр</a>
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP_corrugation_plan.php?order=<?= urlencode($ord) ?>">Планировать гофрирование</a>
                        </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                    <?php endif; ?>
                </td>

                <!-- План сборки -->
                <td>
                    <?php if (!isset($corr_done[$ord])): ?>
                        <span class="disabled">Нет гофроплана</span>
                    <?php elseif (!empty($o['build_ready'])): ?>
                        <div class="done">✅ Готово</div>
                        <div class="stack">
                            <a class="btn-secondary" target="_blank" href="view_production_plan.php?order=<?= urlencode($ord) ?>">Просмотр плана</a>

                            <a class="btn-print" target="_blank" href="NP_build_tasks.php?order=<?= urlencode($ord) ?>">Просмотр задания</a>
                        </div>
                    <?php else: ?>
                        <div class="stack">
                            <a class="btn" href="NP_build_plan.php?order=<?= urlencode($ord) ?>">Планировать сборку</a>
                        </div>
                        <span class="sub">после планирования будет доступен просмотр</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
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
</script>
</body>
</html>
