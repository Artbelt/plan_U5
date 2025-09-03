<?php
/** show_order.php — быстрый просмотр выбранной заявки (агрегирующий запрос, без N+1)
 *  Подключение к БД:
 *   - если есть $conn (mysqli) — используем его;
 *   - иначе, если есть $dsn/$user/$pass — создаём PDO;
 *   - иначе — выдаём понятную ошибку.
 */
session_start();

require_once('tools/tools.php');
require_once('settings.php'); // может создавать $conn (mysqli) или что-то своё
// require_once('style/table.txt'); // лучше выносить стили в .css

$order_number = $_POST['order_number'] ?? '';
if ($order_number === '') {
    http_response_code(400);
    echo 'Не указан номер заявки.';
    exit;
}

/** ---------- Универсальный агрегирующий SQL (общий для PDO и mysqli) ---------- */
$sql = "
SELECT 
    o.filter,
    o.count,
    o.marking,
    o.personal_packaging,
    o.personal_label,
    o.group_packaging,
    o.packaging_rate,
    o.group_label,
    o.remark,
    COALESCE(SUM(mp.count_of_filters), 0)              AS produced,
    GROUP_CONCAT(
        CONCAT(DATE_FORMAT(mp.date_of_production, '%d.%m.%Y'), ' — ', mp.count_of_filters, ' шт')
        ORDER BY mp.date_of_production ASC
        SEPARATOR '\n'
    )                                                   AS tooltip
FROM orders o
LEFT JOIN manufactured_production mp
    ON mp.name_of_order  = o.order_number
   AND mp.name_of_filter = o.filter
WHERE o.order_number = ?
GROUP BY 
    o.filter, o.count, o.marking, o.personal_packaging, o.personal_label,
    o.group_packaging, o.packaging_rate, o.group_label, o.remark
ORDER BY o.filter
";

/** ---------- Попытки подключиться к БД ---------- */
$rows = null;
$used_driver = null;

// Вариант 1: если уже есть mysqli-подключение ($conn)
if (isset($conn) && $conn instanceof mysqli) {
    $used_driver = 'mysqli';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo "DB error (prepare, mysqli): " . htmlspecialchars($conn->error);
        exit;
    }
    $stmt->bind_param('s', $order_number);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
// Вариант 2: если есть реквизиты для PDO ($dsn/$user/$pass)
elseif (isset($dsn, $user, $pass)) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        $used_driver = 'pdo';
        $stmt = $pdo->prepare(str_replace('?', ':ord', $sql));
        $stmt->execute([':ord' => $order_number]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        http_response_code(500);
        echo "DB error (PDO): " . htmlspecialchars($e->getMessage());
        exit;
    }
}
// Вариант 3: нет ни mysqli-коннекта, ни $dsn/$user/$pass
else {
    http_response_code(500);
    echo "Не найдено подключение к БД. 
Подключи mysqli (например, \$conn в settings.php/tools.php) ИЛИ определи \$dsn, \$user, \$pass для PDO.
Пример для PDO в settings.php:
\$dsn  = 'mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4';
\$user = 'root';
\$pass = '';";
    exit;
}

/** ---------- Итоги одним проходом ---------- */
$total_in_order = 0;
$total_produced = 0;
foreach ($rows as $r) {
    $total_in_order += (int)$r['count'];
    $total_produced += (int)$r['produced'];
}
$total_left = $total_in_order - $total_produced;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявка №<?= htmlspecialchars($order_number) ?></title>
    <style>
        body{font-family:Arial,sans-serif;margin:10px}
        h3{font-size:1.5em;margin-bottom:10px}
        button,input[type="submit"]{padding:8px 12px;margin:5px 0;font-size:14px;cursor:pointer}
        #order_table{width:100%;border-collapse:collapse;font-size:14px;border:1px solid #000;margin-bottom:20px}
        #order_table th,#order_table td{border:1px solid #000;padding:8px;text-align:left}
        #order_table th{background:#f2f2f2}
        #order_table tr:hover{background:#e0e0e0}
        .filter-cell{display:flex;justify-content:space-between;align-items:center}
        .filter-name{flex:1;padding-right:10px;word-break:break-word}
        .info-btn{all:unset;display:inline-block;background:#007BFF;color:#fff;font-size:14px;padding:4px 8px;border-radius:4px;cursor:pointer}
        .modal{display:none;position:fixed;z-index:1000;padding-top:60px;left:0;top:0;width:100%;height:100%;overflow:auto;background:rgba(0,0,0,.5)}
        .modal-content{background:#fff;margin:auto;padding:20px;border-radius:8px;width:80%;max-width:700px;position:relative;max-height:90vh;overflow:auto;box-sizing:border-box}
        .close{color:#aaa;position:absolute;top:10px;right:20px;font-size:28px;font-weight:bold;cursor:pointer}
        @media(max-width:600px){
            #order_table{font-size:12px;display:block;overflow-x:auto;white-space:nowrap}
            #order_table th,#order_table td{padding:5px;min-width:80px}
            h3{font-size:1.2em}
            input[type="submit"],button:not(.info-btn){font-size:12px;padding:6px 10px;width:100%;box-sizing:border-box}
            #order_table th:nth-child(5),#order_table td:nth-child(5),
            #order_table th:nth-child(6),#order_table td:nth-child(6),
            #order_table th:nth-child(7),#order_table td:nth-child(7),
            #order_table th:nth-child(9),#order_table td:nth-child(9){display:none}
        }
    </style>
</head>
<body>

<h3>Заявка: <?= htmlspecialchars($order_number) ?></h3>

<button onclick="show_zero()">Позиции, выпуск которых = 0</button>

<form action="show_order_for_workers.php" method="post">
    <input type="hidden" name="order_number" value="<?= htmlspecialchars($order_number) ?>">
    <input type="submit" value="Подготовить спецификацию заявки">
</form>

<table id="order_table">
    <tr>
        <th>№п/п</th>
        <th>Фильтр</th>
        <th>Количество, шт</th>
        <th>Маркировка</th>
        <th>Упаковка инд.</th>
        <th>Этикетка инд.</th>
        <th>Упаковка групп.</th>
        <th>Норма упаковки</th>
        <th>Этикетка групп.</th>
        <th>Примечание</th>
        <th>Изготовлено, шт</th>
        <th>Остаток, шт</th>
    </tr>
    <?php $i=0; foreach ($rows as $r): $i++;
        $left = (int)$r['count'] - (int)$r['produced']; ?>
        <tr>
            <td><?= $i ?></td>
            <td class="filter-cell">
                <span class="filter-name"><?= htmlspecialchars($r['filter']) ?></span>
                <button class="info-btn" onclick="openModal('<?= htmlspecialchars($r['filter'], ENT_QUOTES) ?>')">i</button>
            </td>
            <td><?= (int)$r['count'] ?></td>
            <td><?= htmlspecialchars($r['marking']) ?></td>
            <td><?= htmlspecialchars($r['personal_packaging']) ?></td>
            <td><?= htmlspecialchars($r['personal_label']) ?></td>
            <td><?= htmlspecialchars($r['group_packaging']) ?></td>
            <td><?= htmlspecialchars($r['packaging_rate']) ?></td>
            <td><?= htmlspecialchars($r['group_label']) ?></td>
            <td><?= htmlspecialchars($r['remark']) ?></td>
            <td title="<?= htmlspecialchars($r['tooltip'] ?? '') ?>"><?= (int)$r['produced'] ?></td>
            <td><?= $left ?></td>
        </tr>
    <?php endforeach; ?>
    <tr>
        <td>Итого:</td>
        <td></td>
        <td><?= $total_in_order ?></td>
        <td colspan="7"></td>
        <td><?= $total_produced ?></td>
        <td><?= $total_left ?></td>
    </tr>
</table>

<form action="hiding_order.php" method="post">
    <input type="hidden" name="order_number" value="<?= htmlspecialchars($order_number) ?>">
    <input type="submit" value="Отправить заявку в архив">
</form>

<!-- Модалка с подробностями фильтра -->
<div id="filterModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <div id="modalBody">Загрузка...</div>
    </div>
</div>

<script>
    function show_zero(){
        const table = document.getElementById('order_table');
        const newTable = document.createElement('table');
        newTable.style.border='1px solid black';
        newTable.style.borderCollapse='collapse';
        newTable.style.fontSize='14px';
        newTable.appendChild(table.rows[0].cloneNode(true));
        for(let i=1;i<table.rows.length-1;i++){
            const manufactured = parseInt(table.rows[i].cells[10].innerText||'0',10);
            if(manufactured===0){ newTable.appendChild(table.rows[i].cloneNode(true)); }
        }
        const w = window.open('', 'Zero', 'width=800,height=600');
        w.document.body.append('Позиции, производство которых не начато');
        w.document.body.appendChild(newTable);
    }

    // Подсветка дублей в столбце "Фильтр"
    (() => {
        const table = document.getElementById('order_table');
        const seen = {};
        for (let i=1;i<table.rows.length-1;i++){
            const cell = table.rows[i].cells[1];
            const val  = (cell.textContent||'').trim();
            if (seen[val]) { seen[val].style.backgroundColor='red'; cell.style.backgroundColor='red'; }
            else { seen[val]=cell; }
        }
    })();

    function openModal(filterId){
        fetch('get_filter_details.php?id='+encodeURIComponent(filterId))
            .then(r=>r.text())
            .then(html=>{
                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('filterModal').style.display = 'block';
            });
    }
    function closeModal(){ document.getElementById('filterModal').style.display='none'; }
    window.onclick = function(e){
        const modal = document.getElementById('filterModal');
        if (e.target === modal) modal.style.display='none';
    };
</script>

</body>
</html>
