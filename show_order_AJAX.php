<?php /** show_order_AJAX.php  файл отображает выбранную заявку в режиме просмотра*/

require_once('tools/tools.php');
require_once('settings.php');
require_once('Planned_order_for_salon_2times.php');

global $main_roll_length;
global $max_gap;
global $min_gap;
global $width_of_main_roll;


/** Номер заявки которую надо нарисовать */
$order_number = $_POST['order_number'];

/** Отображаем позиции заявки */
show_order($order_number);                                                                             //---------SERVICE_FUNCTION_MUST_BE_HIDE

/** Создаем объект планирования заявки */
$initial_order = new Planned_order;

/** Задаем ему имя */
$initial_order->set_name($order_number);

/** Задаем ему заявку */
$initial_order->set_order(get_order($order_number));

/** Проверяем на наличие фильтров в БД */
$initial_order->check_for_new_filters();

/** получаем данные для расчета раскроя (параметры гофропакетов) */
$initial_order->get_data_for_cutting($main_roll_length);

/** инициализируем массив для формирования раскроев */
$initial_order->cut_arrays_init();

/** соритруем cut_array по высоте шторы */
$initial_order->sort_cut_arrays();

/** отображаем заявку с загруженными данными по г/пакетам*/
$initial_order->show_order();                                                                                  //---------SERVICE_FUNCTION_MUST_BE_HIDE

/** отображаем cut_array массив подготовленный для раскроя*/
$initial_order->show_cut_array_simple();                                                                       //---------SERVICE_FUNCTION_MUST_BE_HIDE

/** отображаем cut_array массив подготовленный для раскроя*/
$initial_order->show_cut_array_carbon();


/** Тестовый раскрой */
//$initial_order->test_cut_execute_for_carbon();
#$initial_order->test_cut_execute_for_simple();                            #ОТКЛЮЧЕН РАСКРОЙ

/** отображаем сформированные рулоны */
#$initial_order->show_completed_rolls_for_simple();                         #ОТКЛЮЧЕН РАСКРОЙ
//$initial_order->show_completed_rolls_for_carbon();

/** сортируем позиции не вошедшие в раскрой по высоте валков */
//$initial_order->sort_not_completed_rolls_array();

/** отображаем не вошедшие в раскрой рулоны */
//$initial_order->show_not_completed_rolls();



