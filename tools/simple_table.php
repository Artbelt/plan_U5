<?php

require_once(realpath('../settings.php'));

/**  Строим таблицу для отображения раскроя рулона */
$test_array = array();
$test_array[0][0] = "SX1748";
$test_array[0][1] = "48";
$test_array[0][2] = "177";
$test_array[1][0] = "SX593/1";
$test_array[1][1] = "60";
$test_array[1][2] = "201";
$test_array[2][0] = "AF1612";
$test_array[2][1] = "40";
$test_array[2][2] = "171.5";
$test_array[3][0] = "AF1742s";
$test_array[3][1] = "48";
$test_array[3][2] = "253";
$test_array[4][0] = "SX752";
$test_array[4][1] = "48";
$test_array[4][2] = "143";
$test_array[5][0] = "AF1627";
$test_array[5][1] = "24";
$test_array[5][2] = "153.5";
$test_array[6][0] = "AF1804";
$test_array[6][1] = "40";
$test_array[6][2] = "75";




/** ------------------------------- Блок отрисовки одной бухты ----------------------------------*/
    echo "<table style='border-collapse: collapse' '>";
    /** Считаем остаток */
    $ostatok = 0;
    for($x = 0; $x < count($test_array); $x++){
        $ostatok += $test_array[$x][2];
    }
    $ostatok = $width_of_main_roll - $ostatok;
    /** Заносим в талицу валки */

    echo "<tr>";
    echo "<td style='font-size:11pt; border: 1px solid black' colspan='8'>Бухта ".$width_of_main_roll." мм, бумага гладкая, остаток ".$ostatok." мм</td>";
    echo "</tr>";
    echo "<tr>";
    for($x = 0; $x < count($test_array);$x++){
        /** Высчитываем ширину рулона в масштабе 1/2 */
        $roll_size = $test_array[$x][2] / 2;
        echo "<td width=".$roll_size." style='font-size:9pt; border: 1px solid black' >";
        echo $test_array[$x][0]."<br>";
        echo $test_array[$x][1]."<br>";
        echo "<b>".$test_array[$x][2]."</b> мм<br>";
        echo "</td>";
    }
    echo "<td width='.$ostatok.' style='font-size:9pt; border: 1px solid black; background-color: #ababab'> </td>";
    echo "</tr>";


    echo "</table>";

/** ---------------------------------------------------------------------------------------------- */