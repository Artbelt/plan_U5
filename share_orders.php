<?php

/** подключение фалйа настроек */
require_once('settings.php') ;
require_once('tools/tools.php') ;

$workshop = 'U2';


/** ---------------------------------------------------------------------------------------------------------------- */
/**                                                 Раздел ЗАЯВКИ                                                    */
/** ---------------------------------------------------------------------------------------------------------------- */

/** Форма загрузки файла с заявкой в БД */
echo '<table height="100%" ><tr><td bgcolor="white" style="border-collapse: collapse">Сохраненные заявки<br>';


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
$sql = "SELECT DISTINCT order_number, workshop FROM orders;";
//$sql = 'SELECT order_number FROM orders;';
if (!$result = $mysqli->query($sql)){
    echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"
        ."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n";
    exit;
}
/** Разбираем результат запроса */
if ($result->num_rows === 0) { echo "В базе нет ни одной заявки";}

/** Разбор массива значений  */
echo '<form action="show_order.php" method="post">';
while ($orders_data = $result->fetch_assoc()){
    if ($workshop == $orders_data['workshop']){
        //echo "<input type='submit' name='".$orders_data['order_number']."' value=".$orders_data['order_number']."><br>";
        echo "<input type='submit' name='order_number' value=".$orders_data['order_number']." style=\"height: 20px; width: 220px\">"
            //."<button type=\"button\" onclick='to_hide_order.php' value=".$orders_data['order_number'].">X</button><br>"
        ;
    }
}

echo '</form>';



/** конец формы загрузки */
echo "</td></tr></table>";
echo "</td></tr></table>";
$result->close();
$mysqli->close();


echo "<form action='search_filter_in_the_orders.php' method='post'><input type='text' name='filter'><input type='submit' value='Заявки, в которых упоминается фильтр'></form>";
echo "<form action='product_output_view.php' method='post'><input type='submit' value='Просмотр выпущенной продукции'></form>";
?>
<a href="test.php" target="_blank" rel="noopener noreferrer">
    <button style="height: 20px; width: 220px">Выпуск продукции</button>
</a>
