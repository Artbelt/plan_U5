<?php
/** write_off_filters.php в данном файле производится добавление в БД выпущенной продукции */

/** Подключаем инструменты */
require_once ('tools/tools.php');

 /**  массив "фильтр-количество"  */
 $filters_for_write_off = json_decode($_POST['filters_for_write_off_json']);

 /**  номер заявки  */
 $order_number = ($_POST['order_number']);

 /**определяем дату производства и изменяем ее из формата "dd-mm-yy" в формат "yy-mm-dd"  */
 $production_date = reverse_date($_POST['production_date']);

 /** Обработка массива фильтров. Запись их в БД  */
 if(write_of_filters($production_date,$order_number,$filters_for_write_off)) {
    echo "<div style=\"background-color:springgreen; width: 400px\" >выпуск продукции был успешно проведен</div>
            <a class='a' href='enter.php'>на главную</a>";
 } else {
    echo "<div style=\"background-color:red; width: 400px\" >выпуск продукции не был проведен</div>";
 }

?>
