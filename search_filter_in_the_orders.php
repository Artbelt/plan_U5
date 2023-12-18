<?php
/** СТраница отображает заявки в которых присутствует запрашиваемый фильтр */

/** ПОдключаем функции */
require_once('settings.php') ;
require_once ('tools/tools.php');

$filter =$_POST['filter'];

echo "<h4>Информация по наличию фильтра " . $filter . " в заявках</h4><p>";

/** Создаем подключение к БД */
$mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);


/** Выполняем запрос SQL */
$sql = "SELECT order_number FROM orders WHERE filter ='".$filter."';";

/** Если запрос не удачный -> exit */
if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }

/** Разбор массива значений  */
echo "";
echo "Заявки, в которых присутствует эта позиция:<br>";

echo '<form action="show_order.php" method="post">';
while ($orders_data = $result->fetch_assoc()){
    echo "<input type='submit' name='order_number' value=".$orders_data['order_number']." style=\"height: 20px; width: 220px\">";

    /** Выполняем запрос о количестве заказанных фильтров */
    $sql_count = "SELECT count FROM orders WHERE order_number='".$orders_data['order_number']."' AND filter ='".$filter."';";
    /** Если запрос не удачный -> exit */
    if (!$result_count = $mysqli->query($sql_count)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
    $show_count = $result_count->fetch_assoc();

    echo " заказано: ".$show_count['count']." изготовлено: ".(int)select_produced_filters_by_order($filter,$orders_data['order_number'])[1]."<p>";
    //echo "".$orders_data['order_number']."<br>";
}
echo '</form>';

/** Закрываем соединение */
$result->close();
$mysqli->close();


