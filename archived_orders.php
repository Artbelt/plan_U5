<?php
require_once('tools/tools.php');

require_once('settings.php');

require_once ('style/table_1.txt');

?>
    <head></head>
    <style>
        /* Обнуляем отступы и используем box-sizing */
        * {
            margin: 10;
            padding: 0;
            box-sizing: border-box;
        }

        /* Устанавливаем высоту для всей страницы */
        html, body {
            height: 100%;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Центрирование контейнера с кнопками */
        .button-container {
            display: flex;
            flex-direction: column; /* Выстраиваем кнопки столбиком */
            gap: 20px; /* Задаем расстояние между кнопками */
        }

        /* Стили для кнопок */
        .btn {
            padding: 15px 30px;
            font-size: 18px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        /* Эффект при наведении */
        .btn:hover {
            background-color: green; /* Цвет фона при наведении */
            transform: scale(1.1); /* Увеличение размера кнопки на 10% */
        }
    </style>
<?php

/** Подключаемся к БД */
$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
if ($mysqli->connect_errno) {
    /** Если не получилось подключиться */
    echo 'Возникла проблема на сайте'
        . "Номер ошибки: " . $mysqli->connect_errno . "\n"
        . "Ошибка: " . $mysqli->connect_error . "\n";
    exit;
}

/** Выполняем запрос SQL для загрузки заявок*/
$sql = "SELECT DISTINCT order_number, workshop, hide FROM orders;";
//$sql = 'SELECT order_number FROM orders;';
if (!$result = $mysqli->query($sql)){
    echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"
        ."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n";
    exit;
}
/** Разбираем результат запроса */
if ($result->num_rows === 0) { echo "В базе нет ни одной заявки";}

/** Разбор массива значений  */
echo '<form action="show_order.php" method="post" target="_blank" >';
while ($orders_data = $result->fetch_assoc()){
    if ( $orders_data['hide'] != 1) {
        if (str_contains($orders_data['order_number'], '[!]')) {
            echo "<input type='submit' class='alert-button' name='order_number' value=" . $orders_data['order_number'] . " style='background-color: orange;>";
        } else {
            echo "<input type='submit' class='btn' name='order_number' value=" . $orders_data['order_number'] . " >";
        }
    } else {
        echo "<input type='submit' class='btn'  name='order_number' value=" . $orders_data['order_number'] . " style='background-color: orange;'>";
    }
}
echo '</form>';
?>