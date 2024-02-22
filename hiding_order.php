<?php

require_once('tools/tools.php');
require_once ('style/table.txt');

$mysqli = new mysqli('127.0.0.1','root','','plan_U5');

$order = $_POST['order_number'];

echo "Заявка отправлена в архив <p>";

/** Выполняем запрос SQL для загрузки заявок*/

$sql = "UPDATE orders SET hide = 1 WHERE order_number = '$order'";
if (!$result = $mysqli->query($sql)){
    echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"
        ."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n";
    exit;
}

echo "<a class='a' href='enter.php'>на главную</a>";
