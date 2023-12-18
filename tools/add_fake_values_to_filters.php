<?php

require_once('../settings.php');
require_once ('functions.php');

$action = $_GET['action'];
$workshop = 'U2';
switch ($action){

    /** заполняем случайными данными таблицу для теста */
    case 'fill':

        /** Подключаемся к БД для вывода заявки */
        $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
        if ($mysqli->connect_errno) {
            /** Если не получилось подключиться */
            echo 'Возникла проблема на сайте'
                . "Номер ошибки: " . $mysqli->connect_errno . "\n"
                . "Ошибка: " . $mysqli->connect_error . "\n";
            exit;
        }

        for ($x = 1602; $x < 1900; $x++){
            $filter = "AF".$x;
            $sql = "INSERT INTO filters (filter, workshop) VALUES ('$filter', '$workshop');";
            if (!$result = $mysqli->query($sql)) {
                echo "Ошибка: Наш запрос не удался и вот почему: \n"
                    . "Запрос: " . $sql . "\n"
                    . "Номер ошибки: " . $mysqli->errno . "\n"
                    . "Ошибка: " . $mysqli->error . "\n";
                exit;
            }
        }
        break;


}






