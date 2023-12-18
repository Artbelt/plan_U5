<?php


/** Подключаемся к БД */
$mysqli = new mysqli('127.0.0.1','root','','plan');
if ($mysqli->connect_errno){/** Если не получилось подключиться */
    echo 'Возникла проблема на сайте'
        ."Номер ошибки: " . $mysqli->connect_errno . "\n"
        ."Ошибка: " . $mysqli->connect_error . "\n";
    exit;
}
$filter = 'AF';
$number = 1604;
/** Наполняем таблицу данными */
for ($number = 1604; $number < 1650;$number++) {
    /** Выполняем запрос SQL */

    $sql = "INSERT INTO test VALUES ('AF".$number."');";
    if (!$result = $mysqli->query($sql)) {
        echo "Ошибка: Наш запрос не удался и вот почему: \n"
            . "Запрос: " . $sql . "\n"
            . "Номер ошибки: " . $mysqli->errno . "\n"
            . "Ошибка: " . $mysqli->error . "\n";
        exit;
    }
}
?>
