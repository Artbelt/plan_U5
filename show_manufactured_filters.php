<?php

require_once('tools/tools.php');
require_once ('style/table.txt');

/** @var  $production_date */
$production_date = $_POST['production_date'];

    // запрос для выборки фильтров, произведенных в заданную дату
    $production_date = reverse_date($production_date);

    $sql = "SELECT * FROM manufactured_production WHERE date_of_production = '$production_date';";

    $result = mysql_execute($sql);

    //echo 'выбранная дата: '.$production_date.'<p>';
    echo "<table style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'><tr><td>Фильтр</td><td>Количество</td><td>Заявка</td></tr>";
    /** @var $x $variant counter */
    $x=0;
    foreach ($result as $variant){
        $x += $variant['count_of_filters'];
        echo "<tr style=' border: 1px solid black'><td>".$variant['name_of_filter'].'</td><td>'.$variant['count_of_filters'].'</td><td>'.$variant['name_of_order'].'</td></tr>';
    }

    echo '</table>';

    /** Выводим сумму фильтров */
    echo "<p> Сумма выпущенной продукции: ".$x." штук";
