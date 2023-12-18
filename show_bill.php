<?php

require_once('tools/tools.php');
require_once ('style/table.txt');

/** @var  $production_date */
$production_date = date("d.m.y");

/** ------------------------------------------- ТЕСТОВОЕ МЕСТО УДАЛИТЬ ПОСЛЕ СТАРТА-------------------------------- */
//    $production_date = date("Y-m-d", strtotime("-1 day"));
/** --------------------------------------------------------------------------------------------------------------- */


/** Шапка таблицы и заголовки */
echo "Участок №2 <p>";
echo "Дата изготовления :".$production_date."<p>";
echo "Упаковщик _______________________<p>";
echo "Дата приемки: ____________________<p>";
echo "Кладовщик: ______________________<p>";
echo " <br>";


echo "<table>";
echo "<tr><td width='10'>№</td><td width='150'>Фильтр</td><td width='15'>Заявка</td><td width='15'>Изгот.,шт</td>";
echo "<td width='25'>Брак</td><td width='80'>Принято на склад, шт</td><td>Примечание</td></tr>";




// запрос для выборки фильтров, произведенных в заданную дату
$production_date = reverse_date($production_date);

$sql = "SELECT * FROM manufactured_production WHERE date_of_production = '$production_date';";

$result = mysql_execute($sql);

/** @var $x $variant counter */
$x=0;
/** @var  $y cчетчик строк */
$y=0;
foreach ($result as $variant){
    $x += $variant['count_of_filters'];
    $y = $y+1;
    echo "<tr style=' border: 1px solid black'><td>$y</td><td>".$variant['name_of_filter']."</td><td>".$variant['name_of_order']."</td><td>".$variant['count_of_filters']."</td><td></td><td></td><td></td></tr>";
}

echo "<tr style=' border: 1px solid black'><td colspan='3'>ВСЕГО</td><td><b>$x</b></td><td></td><td></td><td></td></tr>";
echo '</table>';
echo '<p>';
echo '<p>';
echo "Отход ППУ _________ кг<p>";



