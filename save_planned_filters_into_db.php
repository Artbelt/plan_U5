<?php
/** save_planned_filters_into_db.php  в данном файле производится добавление в БД распланированной продукции */

/** Подключаем инструменты */
require_once ('tools/tools.php');

/**  массив "распланированная продукция"  */
$send_array = json_decode($_POST['JSON_send_array']);

/**  номер заявки  */
$order_number = ($_POST['order_number']);

/** Обработка массива фильтров. Запись их в БД  */
/**
if(write_of_filters($production_date,$order_number,$filters_for_write_off)) {
    echo "<div style=\"background-color:springgreen; width: 400px\" >выпуск продукции был успешно проведен</div>
            <a class='a' href='enter.php'>на главную</a>";
} else {
    echo "<div style=\"background-color:red; width: 400px\" >выпуск продукции не был проведен</div>";
} */

print_r_my($send_array);

echo "<p>order_number=".$order_number;

?>