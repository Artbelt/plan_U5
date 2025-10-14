<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "");
$date = $_GET['date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT r.id, r.order_number, r.bale_id, r.work_date, r.done,
           c.filter, c.length, c.width, c.height
    FROM roll_plans r
    JOIN cut_plans c ON r.bale_id = c.bale_id AND r.order_number = c.order_number
    WHERE r.work_date = ?
    ORDER BY r.order_number, r.bale_id
");
$stmt->execute([$date]);
$rows_u2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bales_u2 = [];
foreach ($rows_u2 as $r) {
    $key = $r['id'];
    if (!isset($bales_u2[$key])) {
        $bales_u2[$key] = [
            'id' => $r['id'],
            'order_number' => $r['order_number'],
            'bale_id' => $r['bale_id'],
            'plan_date' => $r['work_date'],
            'done' => $r['done'],
            'filters' => [],
            'total_width' => 0
        ];
    }
    $bales_u2[$key]['filters'][] = [
        'name'   => $r['filter'],
        'length' => $r['length'],
        'width'  => $r['width'],
        'height' => $r['height']
    ];
    $bales_u2[$key]['total_width'] += $r['width'];
}

/* Проверяем недоделанные бухты за прошедшие дни */
$overdueBales = [];
$today = date('Y-m-d');
// Сначала получим уникальные просроченные бухты из roll_plans
$stmtRollPlans = $pdo->prepare("
    SELECT r.id, r.order_number, r.bale_id, r.work_date, r.done
    FROM roll_plans r
    LEFT JOIN orders o ON r.order_number = o.order_number
    WHERE r.work_date < ? 
      AND (r.done IS NULL OR r.done = 0)
      AND (o.hide IS NULL OR o.hide != 1)
      AND o.status NOT IN ('completed', 'closed', 'finished')
      AND r.order_number NOT IN ('5273a', 'доп.34-35-25', '29-35-25', '24-28-25-долги')
    ORDER BY r.work_date ASC, r.order_number, r.bale_id
");
$stmtRollPlans->execute([$today]);
$uniqueOverdueRollPlans = $stmtRollPlans->fetchAll(PDO::FETCH_ASSOC);

$overdueBales = [];
foreach ($uniqueOverdueRollPlans as $rollPlan) {
    $key = $rollPlan['id'];
    $daysOverdue = (strtotime($today) - strtotime($rollPlan['work_date'])) / (60 * 60 * 24);

    $overdueBales[$key] = [
        'id' => $rollPlan['id'],
        'order_number' => $rollPlan['order_number'],
        'bale_id' => $rollPlan['bale_id'],
        'plan_date' => $rollPlan['work_date'],
        'done' => $rollPlan['done'],
        'days_overdue' => $daysOverdue,
        'filters' => [],
        'total_width' => 0,
    ];

    // Теперь для каждой уникальной бухты получаем связанные записи из cut_plans
    $stmtCutPlans = $pdo->prepare("
        SELECT c.filter, c.length, c.width, c.height
        FROM cut_plans c
        WHERE c.order_number = ? AND c.bale_id = ?
    ");
    $stmtCutPlans->execute([$rollPlan['order_number'], $rollPlan['bale_id']]);
    $cutPlanDetails = $stmtCutPlans->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cutPlanDetails as $cutDetail) {
        $overdueBales[$key]['filters'][] = [
            'name'   => $cutDetail['filter'],
            'length' => $cutDetail['length'],
            'width'  => $cutDetail['width'],
            'height' => $cutDetail['height']
        ];
        $overdueBales[$key]['total_width'] += $cutDetail['width'];
    }
}

// Отладочная информация
// echo "<!-- DEBUG: Найдено " . count($uniqueOverdueRollPlans) . " уникальных просроченных бухт из roll_plans, обработано " . count($overdueBales) . " бухт с деталями из cut_plans -->";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задания на порезку</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 14px;
            background: #f0f0f0;
            padding: 10px;
        }
        .section {
            max-width: 600px;
            margin: 0 auto 20px;
            background: white;
            padding: 8px;
            border-radius: 5px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 10px;
            background: white;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            text-align: center;
            font-weight: normal;
        }
        tr.done {
            background-color: #d4edda;
        }
        button {
            padding: 6px 10px;
            font-size: 14px;
            cursor: pointer;
        }
        input[type="date"] {
            font-size: 14px;
            padding: 4px;
        }
        h2, h3 {
            text-align: center;
            margin: 5px 0;
        }
        form {
            text-align: center;
            margin-bottom: 10px;
        }
        .details-row {
            display: none;
        }
        tr.main-row {
            cursor: pointer;
            background: #fafafa;
        }
        tr.main-row:hover {
            background: #f1f1f1;
        }
        .positions {
            border-collapse: collapse;
            width: 100%;
            font-size: 13px;
        }
        .positions th, .positions td {
            border: 1px solid #ccc;
            padding: 2px 3px;
            text-align: center;
        }
        .positions th {
            background: #f0f0f0;
        }
        /* Зебра */
        .positions tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .positions tbody tr:nth-child(odd) {
            background-color: #fff;
        }
        .residual {
            font-weight: bold;
        }
        .residual.low {
            color: red;
        }

        /* --- Мобильная версия --- */
        @media (max-width: 600px) {
            body {
                font-size: 16px;
            }
            h2 {
                font-size: 18px;
                line-height: 1.3;
            }
            .section {
                max-width: 100%;
                padding: 8px;
            }
            input[type="date"] {
                font-size: 15px;
                padding: 4px;
                height: 40px;
                width: 160px;
            }
            button {
                width: 100%;
                padding: 10px 0;
                font-size: 16px;
            }
            .positions {
                font-size: 11px;
            }
            .positions th, .positions td {
                padding: 1px 2px;
            }
        }
        
        /* Простое уведомление о просроченных бухтах */
        .overdue-notice {
            background: #dbeafe;
            border: 1px solid #3b82f6;
            border-radius: 6px;
            padding: 12px 16px;
            margin: 15px auto;
            max-width: 600px;
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #1e40af;
        }
        
        .notice-icon {
            font-size: 18px;
            margin-right: 10px;
        }
        
        .notice-text {
            flex: 1;
        }
        
        /* Стили для просроченных бухт в основной таблице */
        .main-row.overdue.warning {
            background-color: #fef3c7 !important;
            border-left: 4px solid #f59e0b;
        }
        
        .main-row.overdue.critical {
            background-color: #fee2e2 !important;
            border-left: 4px solid #dc2626;
        }
        
        .main-row.overdue .bale-status {
            color: #dc2626;
            font-weight: bold;
        }
        
        /* Фон деталей просроченных бухт соответствует заголовку (на 30% менее насыщенный) */
        .main-row.overdue.warning + .details-row td {
            background-color: #fef9e7 !important;
        }
        
        .main-row.overdue.critical + .details-row td {
            background-color: #fef7f7 !important;
        }
    </style>
    <script>
        function toggleDetails(id) {
            const detailsRow = document.getElementById("details-" + id);
            if (detailsRow) {
                detailsRow.style.display = detailsRow.style.display === "table-row" ? "none" : "table-row";
            }
        }

        function markDone(id, btn) {
            if (!confirm("Отметить как выполнено?")) return;
            fetch('mark_cut_done.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        btn.outerHTML = '<span style="color:green;">✔ Выполнено</span>';
                        const mainRow = document.querySelector('.main-row[data-id="' + id + '"]');
                        if (mainRow) mainRow.classList.add('done');
                    } else {
                        alert("Ошибка: не удалось обновить статус");
                    }
                })
                .catch(err => alert("Ошибка запроса: " + err));
        }

        function markAsDone(id) {
            if (!confirm("Отметить просроченную бухту как выполненную?")) return;
            fetch('mark_cut_done.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert("Ошибка: не удалось обновить статус");
                    }
                });
        }

        // Автоматическая загрузка при изменении даты
        document.addEventListener('DOMContentLoaded', function() {
            const dateSelector = document.getElementById('date-selector');
            
            dateSelector.addEventListener('change', function() {
                const selectedDate = this.value;
                if (selectedDate) {
                    // Перенаправляем на страницу с новой датой
                    window.location.href = '?date=' + selectedDate;
                }
            });
        });
    </script>
</head>
<body>

<?php if (!empty($overdueBales)): ?>
<!-- Простое уведомление о просроченных бухтах -->
<div class="overdue-notice">
    <div class="notice-icon">ℹ️</div>
    <div class="notice-text">
        Есть просроченные бухты за прошедшие дни (<?= count($overdueBales) ?> шт.) - см. таблицу ниже
    </div>
</div>
<?php endif; ?>

<h2>Задания на порезку</h2>
<div style="margin: 15px 0; text-align: center;">
    Дата: <input type="date" id="date-selector" value="<?= htmlspecialchars($date) ?>" style="padding: 5px; border: 1px solid #ccc; border-radius: 4px;">
</div>

<div class="section">
    <h3>Задачи от У5</h3>
    <?php if ($bales_u2): ?>
        <table>
            <tbody>
            <?php foreach ($bales_u2 as $b):
                $bale_length = $b['filters'][0]['length']; // длина бухты
                $bale_length_formatted = number_format($bale_length, 0, '.', ''); // убираем .000
                ?>
                <tr class="main-row <?= $b['done'] ? 'done' : '' ?>" data-id="<?= $b['id'] ?>" onclick="toggleDetails(<?= $b['id'] ?>)">
                    <td colspan="2" class="main-cell">
                        <span class="bale-number">
                            <?= htmlspecialchars($b['bale_id']) ?> [<?= $bale_length_formatted ?> м]
                        </span>
                        <span class="bale-status"><?= $b['done'] ? 'Готово' : 'Не готово' ?></span>
                    </td>
                </tr>
                <tr class="details-row" id="details-<?= $b['id'] ?>">
                    <td colspan="2" style="text-align:left; background:#fafafa; padding:8px;">
                        <strong>Заявка №<?= htmlspecialchars($b['order_number']) ?></strong><br><br>
                        <strong>Позиции:</strong>
                        <table class="positions">
                            <thead>
                            <tr>
                                <th>Фильтр</th>
                                <th>Ширина</th>
                                <th>Высота</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($b['filters'] as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['name']) ?></td>
                                    <td><?= $f['width'] ?> мм</td>
                                    <td><?= $f['height'] ?> мм</td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p><strong>Суммарная ширина:</strong> <?= $b['total_width'] ?> мм</p>
                        <?php $residual = 1200 - $b['total_width']; ?>
                        <p class="residual <?= ($residual < 100) ? 'low' : '' ?>">
                            <strong>Остаток:</strong> <?= max(0, $residual) ?> мм
                        </p>
                        <?php if (!$b['done']): ?>
                            <button type="button" onclick="markDone(<?= $b['id'] ?>, this)">ВЫПОЛНЕНО</button>
                        <?php else: ?>
                            <span style="color:green;">✔ Выполнено</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align:center;">Заданий нет</p>
    <?php endif; ?>

    <?php if (!empty($overdueBales)): ?>
    <div class="section">
        <h3>Просроченные бухты</h3>
        <table>
            <?php foreach ($overdueBales as $b): ?>
                <?php
                $bale_length = $b['filters'][0]['length']; // длина бухты
                $bale_length_formatted = number_format($bale_length, 0, '.', ''); // убираем .000
                $daysOverdue = (int)$b['days_overdue'];
                $overdueClass = $daysOverdue >= 2 ? 'critical' : 'warning';
                ?>
                <tr class="main-row overdue <?= $overdueClass ?>" data-id="overdue-<?= $b['id'] ?>" onclick="toggleDetails('overdue-<?= $b['id'] ?>')">
                    <td colspan="2" class="main-cell">
                        <span class="bale-number">
                            <?= htmlspecialchars($b['bale_id']) ?> [<?= $bale_length_formatted ?> м]
                            <span style="color: #dc2626; font-weight: bold;">(Просрочено <?= $daysOverdue ?> дн.)</span>
                        </span>
                        <span class="bale-status"></span>
                    </td>
                </tr>
                <tr class="details-row" id="details-overdue-<?= $b['id'] ?>">
                    <td colspan="2" style="text-align:left; padding:8px;">
                        <strong>Заявка №<?= htmlspecialchars($b['order_number']) ?></strong><br>
                        <strong>План дата:</strong> <?= htmlspecialchars($b['plan_date']) ?><br>
                        <strong>Просрочено:</strong> <?= $daysOverdue ?> дней<br><br>
                        <strong>Позиции:</strong>
                        <table class="positions">
                            <thead>
                            <tr>
                                <th>Фильтр</th>
                                <th>Ширина</th>
                                <th>Высота</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($b['filters'] as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['name']) ?></td>
                                    <td><?= $f['width'] ?> мм</td>
                                    <td><?= $f['height'] ?> мм</td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button onclick="markAsDone(<?= $b['id'] ?>)" style="margin-top: 10px;">
                            Отметить как выполненное
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
