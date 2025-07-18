<?php
/** show_order.php  файл отображает выбранную заявку в режиме просмотра */
require_once('tools/tools.php');
require_once('settings.php');
require_once('style/table.txt');

/** Номер заявки которую надо нарисовать */
$order_number = $_POST['order_number'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявка №<?php echo $order_number; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 10px;
        }
        h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }
        button, input[type="submit"] {
            padding: 8px 12px;
            margin: 5px 0;
            font-size: 14px;
            cursor: pointer;
        }
        #order_table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            border: 1px solid black;
            margin-bottom: 20px;
        }
        #order_table th, #order_table td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        #order_table th {
            background-color: #f2f2f2;
        }
        #order_table tr:hover {
            background-color: #e0e0e0; /* Более контрастный серый для подсветки */
        }
        /* Сохраняем приоритет для красной подсветки дубликатов */
        #order_table td[style*="background-color: red"] {
            background-color: red !important;
        }

        /* Адаптивные стили для мобильных устройств */
        @media (max-width: 600px) {
            .filter-blocks {
                flex-direction: column;
            }
        }
        @media screen and (max-width: 600px) {
            #order_table {
                font-size: 12px;
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            #order_table th, #order_table td {
                padding: 5px;
                min-width: 80px;
            }

            h3 {
                font-size: 1.2em;
            }

            /* Применяем ширину только к кнопкам вне ячеек */
            input[type="submit"], button:not(.info-btn) {
                font-size: 12px;
                padding: 6px 10px;
                width: 100%;
                box-sizing: border-box;
            }

            #order_table th:nth-child(5), #order_table td:nth-child(5),
            #order_table th:nth-child(6), #order_table td:nth-child(6),
            #order_table th:nth-child(7), #order_table td:nth-child(7),
            #order_table th:nth-child(9), #order_table td:nth-child(9) {
                display: none;
            }
        }        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            padding-top: 60px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 700px;
            position: relative;

            max-height: 90vh;   /* окно не больше 90% высоты экрана */
            overflow-y: auto;   /* прокрутка контента */
            box-sizing: border-box;
        }

        .close {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .info-btn {
            float: right;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #007BFF;
            margin-left: 1px;
        }
        #loader {
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            z-index: 2000;
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #loader::after {
            content: "";
            width: 60px;
            height: 60px;
            border: 8px solid #ccc;
            border-top: 8px solid #007BFF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .filter-cell {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filter-name {
            flex: 1;
            padding-right: 10px;
            word-break: break-word;
        }

        .info-btn {
            background: #007BFF;
            border: none;
            color: white;
            border-radius: 4px;
            font-size: 16px;
            padding: 3px 8px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .info-btn {
            all: unset; /* сбрасывает ВСЁ */
            display: inline-block;
            background-color: #007BFF;
            color: white;
            font-size: 14px;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
        }
        .info-btn {
            outline: none;
        }
        .info-btn:focus {
            outline: none;
            box-shadow: none;
        }
    </style>
</head>
<body>
<div id="loader"></div>
<div id="filterModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <div id="modalBody">
            Загрузка...
        </div>
    </div>
</div>
<h3>Заявка: <?php echo htmlspecialchars($order_number); ?></h3>

<button onclick="show_zero()">Позиции, выпуск которых = 0</button>

<form action="show_order_for_workers.php" method="post">
    <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($order_number); ?>">
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
    <?php
    /** Загружаем из БД заявку */
    $result = show_order($order_number);
    $filter_count_in_order = 0;
    $filter_count_produced = 0;
    $count = 0;

    while ($row = $result->fetch_assoc()) {
        $difference = (int)$row['count'] - (int)select_produced_filters_by_order($row['filter'], $order_number)[1];
        $filter_count_in_order += (int)$row['count'];
        $filter_count_produced += (int)select_produced_filters_by_order($row['filter'], $order_number)[1];
        $count++;

        $prod_info = select_produced_filters_by_order($row['filter'], $order_number);
        $date_list = $prod_info[0];
        $total_qty = $prod_info[1];
        $tooltip = '';
        for ($i = 0; $i < count($date_list); $i += 2) {
            $tooltip .= $date_list[$i] . ' — ' . $date_list[$i + 1] . " шт\n";
        }
        ?>
        <tr>
            <td><?php echo $count; ?></td>

            <td class="filter-cell">
                <span class="filter-name"><?php echo htmlspecialchars($row['filter']); ?></span>
                <button class="info-btn" onclick="openModal('<?php echo htmlspecialchars($row['filter'], ENT_QUOTES); ?>')">ℹ️</button>
            </td>

            <td><?php echo htmlspecialchars($row['count']); ?></td>
            <td><?php echo htmlspecialchars($row['marking']); ?></td>
            <td><?php echo htmlspecialchars($row['personal_packaging']); ?></td>
            <td><?php echo htmlspecialchars($row['personal_label']); ?></td>
            <td><?php echo htmlspecialchars($row['group_packaging']); ?></td>
            <td><?php echo htmlspecialchars($row['packaging_rate']); ?></td>
            <td><?php echo htmlspecialchars($row['group_label']); ?></td>
            <td><?php echo htmlspecialchars($row['remark']); ?></td>
            <td title="<?php echo trim($tooltip); ?>"><?php echo $total_qty; ?></td>
            <td><?php echo $difference; ?></td>
        </tr>
        <?php
    }
    $summ_difference = $filter_count_in_order - $filter_count_produced;
    ?>
    <tr>
        <td>Итого:</td>
        <td></td>
        <td><?php echo $filter_count_in_order; ?></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td><?php echo $filter_count_produced; ?></td>
        <td><?php echo $summ_difference; ?></td>
    </tr>
</table>

<form action="order_planning_U2.php" method="post">
    <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($order_number); ?>">
    <input type="submit" value="Режим простого планирования">
</form>

<form action="hiding_order.php" method="post">
    <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($order_number); ?>">
    <input type="submit" value="Отправить заявку в архив">
</form>

<script>
    function show_zero() {
        var table = document.getElementById('order_table');
        var newTable = document.createElement('table');
        newTable.style.border = '1px solid black';
        newTable.style.borderCollapse = 'collapse';
        newTable.style.fontSize = '14px';

        var header = table.rows[0].cloneNode(true);
        newTable.appendChild(header);

        for (var i = 1; i < table.rows.length; i++) {
            var currentRow = table.rows[i];
            var manufactured = parseInt(currentRow.cells[10].innerText);
            if (manufactured === 0) {
                var newRow = currentRow.cloneNode(true);
                newTable.appendChild(newRow);
            }
        }

        var newWindow = window.open('', 'New Window', 'width=800,height=600');
        newWindow.document.body.append('Позиции, производство которых не начато');
        newWindow.document.body.appendChild(newTable);
    }

    // Проверка дубликатов в столбце "Фильтр"
    var table = document.getElementById('order_table');
    var columnIndex = 1;
    var seen = {};
    for (var i = 1; i < table.rows.length; i++) {
        var cell = table.rows[i].cells[columnIndex];
        var value = cell.textContent || cell.innerText;
        if (seen[value]) {
            seen[value].style.backgroundColor = 'red';
            cell.style.backgroundColor = 'red';
        } else {
            seen[value] = cell;
        }
    }
</script>
<script>
    function openModal(filterId) {
        fetch('get_filter_details.php?id=' + filterId)
            .then(response => response.text())
            .then(html => {
                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('filterModal').style.display = 'block';
            });
    }

    function closeModal() {
        document.getElementById('filterModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('filterModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
</script>
<script>
    window.addEventListener("load", function () {
        document.getElementById("loader").style.display = "none";
    });
</script>
</body>
</html>