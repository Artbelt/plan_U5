<?php

require_once('../settings.php');
require_once ('functions.php');

$action = $_GET['action'];

switch ($action){

    /** заполняем случайными данными таблицу для теста */
    case 'ADD':

        /** Подключаемся к БД для вывода заявки */
        $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
        if ($mysqli->connect_errno) {
            /** Если не получилось подключиться */
            echo 'Возникла проблема на сайте'
                . "Номер ошибки: " . $mysqli->connect_errno . "\n"
                . "Ошибка: " . $mysqli->connect_error . "\n";
            exit;
        }

        for ($x = 1601; $x < 1900; $x++){
            $paper_package = "AF".$x;
            $height = choose_height();
            $width = choose_width();
            $pleats_count = choose_pleats_count();
            $sql = "INSERT INTO paper_package VALUES ('AF$x',$height,$width,$pleats_count);";
            if (!$result = $mysqli->query($sql)) {
                echo "Ошибка: Наш запрос не удался и вот почему: \n"
                    . "Запрос: " . $sql . "\n"
                    . "Номер ошибки: " . $mysqli->errno . "\n"
                    . "Ошибка: " . $mysqli->error . "\n";
                exit;
            }
        }
        break;

    /** Удалаем в названии "гофропакет" */
    case 'delete_first_part':

        /** Подключаемся к БД для вывода заявки */
        $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
        if ($mysqli->connect_errno) {
            /** Если не получилось подключиться */
            echo 'Возникла проблема на сайте'
                . "Номер ошибки: " . $mysqli->connect_errno . "\n"
                . "Ошибка: " . $mysqli->connect_error . "\n";
            exit;
        }
        $sql = "SELECT paper_package_name FROM paper_package;";
        if (!$result = $mysqli->query($sql)) {
            echo "Ошибка: Наш запрос не удался и вот почему: \n"
                . "Запрос: " . $sql . "\n"
                . "Номер ошибки: " . $mysqli->errno . "\n"
                . "Ошибка: " . $mysqli->error . "\n";
            exit;
        }

        /** Подключаемся к БД для вывода заявки */
        $mysqli2 = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
        if ($mysqli2->connect_errno) {
            /** Если не получилось подключиться */
            echo 'Возникла проблема на сайте'
                . "Номер ошибки: " . $mysqli2->connect_errno . "\n"
                . "Ошибка: " . $mysqli2->connect_error . "\n";
            exit;
        }

        foreach ($result as $row){
            $old_name = $row['paper_package_name'];
            $pos = strpos($old_name,'гофро');
            if ($pos === false) {continue;}
            $new_name = substr($row['paper_package_name'],-5,5);

            $sql2 = "UPDATE paper_package SET paper_package_name = '$new_name' WHERE paper_package_name = '$old_name';";
            if (!$result2 = $mysqli2->query($sql2)) {
                echo "Ошибка: Наш запрос не удался и вот почему: \n"
                    . "Запрос: " . $sql2 . "\n"
                    . "Номер ошибки: " . $mysqli2->errno . "\n"
                    . "Ошибка: " . $mysqli2->error . "\n";
                exit;

        }

    }
    break;

    /** добавляем в названии "AF" */
    case 'add_first_part':

        /** Подключаемся к БД для вывода заявки */
        $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
        if ($mysqli->connect_errno) {
            /** Если не получилось подключиться */
            echo 'Возникла проблема на сайте'
                . "Номер ошибки: " . $mysqli->connect_errno . "\n"
                . "Ошибка: " . $mysqli->connect_error . "\n";
            exit;
        }
        $sql = "SELECT paper_package_name FROM paper_package;";
        if (!$result = $mysqli->query($sql)) {
            echo "Ошибка: Наш запрос не удался и вот почему: \n"
                . "Запрос: " . $sql . "\n"
                . "Номер ошибки: " . $mysqli->errno . "\n"
                . "Ошибка: " . $mysqli->error . "\n";
            exit;
        }

        /** Подключаемся к БД для вывода заявки */
        $mysqli2 = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
        if ($mysqli2->connect_errno) {
            /** Если не получилось подключиться */
            echo 'Возникла проблема на сайте'
                . "Номер ошибки: " . $mysqli2->connect_errno . "\n"
                . "Ошибка: " . $mysqli2->connect_error . "\n";
            exit;
        }

        foreach ($result as $row){
            $old_name = $row['paper_package_name'];
            $new_name = "AF".$old_name;

            $sql2 = "UPDATE paper_package SET paper_package_name = '$new_name' WHERE paper_package_name = '$old_name';";
            if (!$result2 = $mysqli2->query($sql2)) {
                echo "Ошибка: Наш запрос не удался и вот почему: \n"
                    . "Запрос: " . $sql2 . "\n"
                    . "Номер ошибки: " . $mysqli2->errno . "\n"
                    . "Ошибка: " . $mysqli2->error . "\n";
                exit;

            }

        }
        break;

    /** убираем пробелы в названии */
    case 'delete_spaces':

        /** Подключаемся к БД для вывода заявки */
        $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
        if ($mysqli->connect_errno) {
            /** Если не получилось подключиться */
            echo 'Возникла проблема на сайте'
                . "Номер ошибки: " . $mysqli->connect_errno . "\n"
                . "Ошибка: " . $mysqli->connect_error . "\n";
            exit;
        }
        $sql = "SELECT paper_package_name FROM paper_package;";
        if (!$result = $mysqli->query($sql)) {
            echo "Ошибка: Наш запрос не удался и вот почему: \n"
                . "Запрос: " . $sql . "\n"
                . "Номер ошибки: " . $mysqli->errno . "\n"
                . "Ошибка: " . $mysqli->error . "\n";
            exit;
        }

        /** Подключаемся к БД для вывода заявки */
        $mysqli2 = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
        if ($mysqli2->connect_errno) {
            /** Если не получилось подключиться */
            echo 'Возникла проблема на сайте'
                . "Номер ошибки: " . $mysqli2->connect_errno . "\n"
                . "Ошибка: " . $mysqli2->connect_error . "\n";
            exit;
        }

        foreach ($result as $row){
            $old_name = $row['paper_package_name'];
            $new_name = str_replace(" ","",$old_name);

            $sql2 = "UPDATE paper_package SET paper_package_name = '$new_name' WHERE paper_package_name = '$old_name';";
            if (!$result2 = $mysqli2->query($sql2)) {
                echo "Ошибка: Наш запрос не удался и вот почему: \n"
                    . "Запрос: " . $sql2 . "\n"
                    . "Номер ошибки: " . $mysqli2->errno . "\n"
                    . "Ошибка: " . $mysqli2->error . "\n";
                exit;

            }

        }
        break;
}






