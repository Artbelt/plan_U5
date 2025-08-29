<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");
$date = $_GET['date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT r.id, r.order_number, r.bale_id, r.plan_date, r.done,
           c.filter, c.length, c.width, c.height
    FROM roll_plan r
    JOIN cut_plans c ON r.bale_id = c.bale_id AND r.order_number = c.order_number
    WHERE r.plan_date = ?
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
            'plan_date' => $r['plan_date'],
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
    </style>
    <script>
        function toggleDetails(id) {
            const detailsRow = document.getElementById("details-" + id);
            detailsRow.style.display = detailsRow.style.display === "table-row" ? "none" : "table-row";
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
    </script>
</head>
<body>
<h2>Задания на порезку на <?= htmlspecialchars($date) ?></h2>
<form method="get">
    Дата: <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button type="submit">Показать</button>
</form>

<div class="section">
    <h3>Задачи от У2</h3>
    <?php if ($bales_u2): ?>
        <table>
            <tbody>
            <?php foreach ($bales_u2 as $b):
                $bale_length = $b['filters'][0]['length']; // длина бухты
                ?>
                <tr class="main-row <?= $b['done'] ? 'done' : '' ?>" data-id="<?= $b['id'] ?>" onclick="toggleDetails(<?= $b['id'] ?>)">
                    <td colspan="2" class="main-cell">
                        <span class="bale-number">
                            <?= htmlspecialchars($b['bale_id']) ?> — <?= $bale_length ?> м
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
</div>
</body>
</html>
