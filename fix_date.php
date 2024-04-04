<?php

require_once('tools/tools.php');
require_once('settings.php');

set_time_limit(600);

global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;
/** Создаем подключение к БД */
$mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);


$sql = "SELECT * FROM manufactured_production WHERE YEAR(date_of_production) = 2424;";
/** Если запрос не удачный -> exit */
if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
/** Разбор массива значений  */

while ( $row = mysqli_fetch_row($result)){

    $current_date = date_create($row[0]);
    $modified_date = $current_date;
    date_modify($modified_date, '-400 year');
    $sql1 = "UPDATE manufactured_production SET date_of_production = '".date_format($current_date,'Y-m-d')."' WHERE date_of_production ='".$row[0]."' AND name_of_filter = '".$row[1]."' AND count_of_filters ='".$row[2]."' AND name_of_order = '".$row[3]."';";
    /** Выполняем запрос SQL */
    if (!$result1 = $mysqli->query($sql1)) { echo "Ошибка: Наш запрос не удался и вот почему: \n". "Запрос: " . $sql1 . "\n". "Номер ошибки: " . $mysqli->errno . "\n". "Ошибка: " . $mysqli->error . "\n";    exit;   }
}

echo "2424 filters fixed<p>";

$sql = "SELECT * FROM manufactured_production WHERE YEAR(date_of_production) = 2323;";
/** Если запрос не удачный -> exit */
if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
/** Разбор массива значений  */

while ( $row = mysqli_fetch_row($result)){

    $current_date = date_create($row[0]);
    $modified_date = $current_date;
    date_modify($modified_date, '-300 year');
    $sql1 = "UPDATE manufactured_production SET date_of_production = '".date_format($current_date,'Y-m-d')."' WHERE date_of_production ='".$row[0]."' AND name_of_filter = '".$row[1]."' AND count_of_filters ='".$row[2]."' AND name_of_order = '".$row[3]."';";
    /** Выполняем запрос SQL */
    if (!$result1 = $mysqli->query($sql1)) { echo "Ошибка: Наш запрос не удался и вот почему: \n". "Запрос: " . $sql1 . "\n". "Номер ошибки: " . $mysqli->errno . "\n". "Ошибка: " . $mysqli->error . "\n";    exit;   }
}

echo "2323 filters fixed<p>";

$sql = "SELECT * FROM manufactured_production WHERE YEAR(date_of_production) = 2222;";
/** Если запрос не удачный -> exit */
if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
/** Разбор массива значений  */

while ( $row = mysqli_fetch_row($result)){

    $current_date = date_create($row[0]);
    $modified_date = $current_date;
    date_modify($modified_date, '-200 year');
    $sql1 = "UPDATE manufactured_production SET date_of_production = '".date_format($current_date,'Y-m-d')."' WHERE date_of_production ='".$row[0]."' AND name_of_filter = '".$row[1]."' AND count_of_filters ='".$row[2]."' AND name_of_order = '".$row[3]."';";
    /** Выполняем запрос SQL */
    if (!$result1 = $mysqli->query($sql1)) { echo "Ошибка: Наш запрос не удался и вот почему: \n". "Запрос: " . $sql1 . "\n". "Номер ошибки: " . $mysqli->errno . "\n". "Ошибка: " . $mysqli->error . "\n";    exit;   }
}

echo "2222 filters fixed<p>";

$sql = "SELECT * FROM manufactured_production WHERE YEAR(date_of_production) = 2121;";
/** Если запрос не удачный -> exit */
if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
/** Разбор массива значений  */

while ( $row = mysqli_fetch_row($result)){

    $current_date = date_create($row[0]);
    $modified_date = $current_date;
    date_modify($modified_date, '-100 year');
    $sql1 = "UPDATE manufactured_production SET date_of_production = '".date_format($current_date,'Y-m-d')."' WHERE date_of_production ='".$row[0]."' AND name_of_filter = '".$row[1]."' AND count_of_filters ='".$row[2]."' AND name_of_order = '".$row[3]."';";
    /** Выполняем запрос SQL */
    if (!$result1 = $mysqli->query($sql1)) { echo "Ошибка: Наш запрос не удался и вот почему: \n". "Запрос: " . $sql1 . "\n". "Номер ошибки: " . $mysqli->errno . "\n". "Ошибка: " . $mysqli->error . "\n";    exit;   }
}

echo "2121 filters fixed<p>";

set_time_limit(60);

?>