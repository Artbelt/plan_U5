<?php
/**                 add_filter_into_db_processing                     */
/** файл обрабатывает запрос доюавления названия нового фильтра в БД */

require_once('tools/tools.php');
require_once('settings.php');

/** @var  $filter  получаем имя фильтра для внесения в БД*/
$filter =  $_GET['filter'];
/** @var  $workshop получаем имя участка на котором производится фильтр*/
$workshop = $_GET['workshop'];

/** Подключаемся к БД */
$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);

/** Если не получилось подключиться */
if ($mysqli->connect_errno) {  echo "Номер ошибки: " . $mysqli->connect_errno . "\n". "Ошибка: " . $mysqli->connect_error . "\n";
    exit;
}

/** Выполняем запрос SQL */
$sql = "SELECT * FROM filters WHERE filter = '$filter';";
/** Если запрос вернет ошибку */
if (!$result = $mysqli->query($sql)) {  echo "Номер ошибки: " . $mysqli->errno . "\n" . "Ошибка: " . $mysqli->error . "\n";
    exit;
}

/** Разбираем результат запроса. Если нет ни одной строки, значит можно вносить фильтр в БД */
if ($result->num_rows === 0) {

    /** Записываем название фильтра в БД */
    $sql = "INSERT INTO filters(filter, workshop) VALUES ('$filter','$workshop');";

    /** Если произошла ошибка при добавлении */
    if (!$result = $mysqli->query($sql)){ echo "Номер ошибки: " . $mysqli->errno . "\n" . "Ошибка: " . $mysqli->error . "\n";
        exit;
    }
    /** Если внесение в БД успешно */
    echo "Фильтр ".$filter." успешно добавлен в БД";

} else {/** Если такой фильтр уже есть */
    echo "Фильтр ".$filter." уже есть в БД";
}


?>
