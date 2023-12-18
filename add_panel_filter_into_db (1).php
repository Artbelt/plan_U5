<?php
require_once('tools/tools.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <title>Добавление нового панельного фильтра в БД</title>
    <style>
        article, aside, details, figcaption, figure, footer,header,
        hgroup, menu, nav, section { display: block; }
    </style>
    <style>
        .colortext {
            background-color: #ffffff; /* Цвет фона */
            color: #ff0000; /* Цвет текста */
        }
    </style>
</head>
<body>


<?php  echo $_POST['workshop'];?>

<form action="processing_add_salon_filter_into_db.php" method="post" >
    <label>Наименование фильтра
    <input type="text" name="filter_name" size="10">
    </label>
    <div id="mark"></div>
    <label>Категория
    <select name="category">
        <option>Панельный</option>
    </select>
    </label><br>
    <hr>
    <label><b>Гофропакет:</b></label><p>
        <label>Длина: <input type="text" size="5" name="p_p_length"></label>
        <label>Ширина: <input type="text" size="5" name="p_p_width"></label>
        <label>Высота:<input type="text" size="5" name="p_p_height"> </label>
        <label>Кол-во ребер: <input type="text" size="5" name="p_p_pleats_count"></label>
        <label>Усилитель: <input type="text" size="2" name="p_p_amplifier"></label>
        <label>Поставщик: <select name="p_p_supplier"><option></option><option>У2</option><option>ЗУ</option></select></label><br><br>
        <label>Примечание: <input type="text" size="100" name="p_p_remark" class="colortext"></label>
        <hr>
    <label><b>Каркас</b></label><p>
        <label>Длина: <input type="text" size="5" name="wf_length"></label>
        <label>Ширина: <input type="text" size="5" name="wf_width"></label>
        <label>Материал: <select name="wf_material"><option></option><option>ОЦ 0,45</option><option>Жесть 0,22</option></select></label>
        <label>Поставщик: <select name="wf_supplier"><option></option><option>ЗУ</option></select><br></label>
        <hr>
    <label><b>Предфильтр</b></label><p>
        <label>Длина:<input type="text" size="5" name="pf_length"> </label>
        <label>Ширина: <input type="text" size="5" name="pf_width"></label>
        <label>Материал:<select name="pf_material"><option></option><option>Н/т полотно</option></select> </label>
        <label>Поставщик:<select name="pf_supplier"><option></option><option>УУ</option></select></label><br><br>
        <label>Примечание: <input type="text" size="100" name="pf_remark" class="colortext"></label>
    <hr>
    <label><b>Индивидуальная упаковка</b></label><p>
        <label>Коробка №:   <select name="box"><?php select_boxes();?></select></label><br>
    <hr>
    <label><b>Групповая упаковка</b></label><p>
        <label>Ящик №: <select name="g_box"><?php select_g_boxes();?></select></label><br>
    <hr>
    <label><b>Примечание</b>
        <input type="text" size="100" name="remark" class="colortext">
    </label><p>
    <hr>
    <input type="submit" value="Сохранить фильтр">

</form>

</body>
</html>



