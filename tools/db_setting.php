<?php
/** Создаем БД и таблици для работы программы */

/** Подключение к MySql */
$server_name  = '127.0.0.1';
$user_name = 'root';
$password = '';
$database_name = 'plan';
$table_name = 'orders';

/** Создание подключения */
$connection = new mysqli($server_name,$user_name,$password);

/** Проверка соединения */
if ($connection->error){
    die('Ошибка подключения'.$connection->error.' error_code= '.$connection->errno);
}
echo 'Подключение к MySql успешно.<br>';

/** Создание БД */
$sql = "CREATE DATABASE IF NOT EXISTS $database_name";
if ($connection->query($sql)===TRUE){
    echo 'База данных"'.$database_name.'" создана успешно <br>';
}else{
    die('Ошибка создания БД'.$connection->error.' error_code= '.$connection->errno);
}

/** Подключение к БД */
$sql = "USE $database_name";
if ($connection->query($sql)===TRUE){ echo 'Подключение к БД "'.$database_name.'" успешно <br>';
}else {
    die('Ошибка подключения к БД' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу заявок */
$sql = "CREATE  TABLE IF NOT EXISTS orders(
order_number TEXT(25),
workshop TEXT(15),
filter TEXT(15),
count INT(5),
marking TEXT(255),
personal_packaging TEXT(255),
personal_label TEXT(255),
group_packaging TEXT(255),
packaging_rate INT(5),
group_label TEXT(255),
remark TEXT(255));";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу пользователей*/
$table_name ='users';
$sql = "CREATE  TABLE IF NOT EXISTS users(
user TEXT(25),
pass TEXT(15),
ZU INT(1),
U1 INT(1),
U2 INT(1),
U3 INT(1),
U4 INT(1),
U5 INT(1),
U6 INT(1));";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу фильтров*/
$table_name ='filters';
$sql = "CREATE  TABLE IF NOT EXISTS filters(
filter TEXT(50),
workshop TEXT(20),
UNIQUE (filter)
);";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу выпущенной продукци*/
$table_name ='manufactured_production';
$sql = "CREATE TABLE IF NOT EXISTS manufactured_production(
 date_of_production DATE, 
 name_of_filter TEXT(50), 
 count_of_filters INT(6), 
 name_of_order TEXT(5))";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу гофропакетов для панельных фильтров*/
$table_name ='paper_package';
$sql = "CREATE  TABLE IF NOT EXISTS paper_package_salon(
p_p_name TEXT(50),
p_p_length FLOAT(6),
p_p_height FLOAT (6),
p_p_width FLOAT(6),
p_p_pleats_count INT(4),
p_p_amplifier INT(1),
supplier TEXT(10),
p_p_remark TEXT(255),
UNIQUE (p_p_name)
);";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу "структура продукта" для У2*/
$table_name ='salon_filter_structure';
$sql = "CREATE  TABLE IF NOT EXISTS salon_filter_structure(
filter TEXT(100),
category TEXT(20),
paper_package TEXT(20),
wireframe TEXT(20),
prefilter TEXT(20),
box TEXT(20),
g_box TEXT(20),
comment TEXT(100),
UNIQUE (filter)
);";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу "ящики" */
$table_name ='g_box';
$sql = "CREATE  TABLE IF NOT EXISTS g_box(
gb_name TEXT(100),
gb_length FLOAT(5),
gb_width FLOAT(5),
gb_heght FLOAT(5),
gb_supplier TEXT(30),
UNIQUE (gb_name)
);";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу "коробки индивидуальные" */
$table_name ='box';
$sql = "CREATE  TABLE IF NOT EXISTS box(
b_name TEXT(100),
b_length FLOAT(5),
b_width FLOAT(5),
b_heght FLOAT(5),
b_supplier TEXT(30),
UNIQUE (b_name)
);";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу "каркасы для панельных" */
$table_name ='wireframe_panel';
$sql = "CREATE  TABLE IF NOT EXISTS wireframe_panel(
w_name TEXT(100),
w_length FLOAT(5),
w_width FLOAT(5),
w_material TEXT(30),
w_supplier TEXT(30),
UNIQUE (w_name)
);";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}

/** Создаем таблицу "предфильтры для панельных" */
$table_name ='prefilter_panel';
$sql = "CREATE  TABLE IF NOT EXISTS prefilter_panel(
p_name TEXT(100),
p_length FLOAT(5),
p_width FLOAT(5),
p_material TEXT(30),
p_supplier TEXT(30),
p_remark TEXT(255),
UNIQUE (p_name)
);";
if ($connection->query($sql)===TRUE){
    echo 'Таблица"'.$table_name.'" создана успешно <br>';
}else {
    die('Ошибка создания таблицы' . $connection->error . ' error_code= ' . $connection->errno);
}


/** Закрываем подключение */
$connection->close();
echo "Подключение закрыто.";
