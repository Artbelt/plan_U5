<?php
require_once('tools/tools.php');

$filter_name =  $_POST['filter_name'];
$category = $_POST['category'];
/** ГОФРОПАКЕТ */
$p_p_name = "гофропакет ".$filter_name;
$p_p_width = $_POST['p_p_width'];
$p_p_height = $_POST['p_p_height'];
$p_p_pleats_count = $_POST['p_p_pleats_count'];
$p_p_supplier = $_POST['p_p_supplier'];
$p_p_remark = $_POST['p_p_remark'];
$p_p_material = $_POST['p_p_material'];
/** ВСТАВКА Если заполнены все поля вставок то присваиваем ей имя */
$insertion_count = $_POST['insertions_count'];
$insertion_supplier = $_POST['insertions_supplier'];
if (($insertion_count != '') and ($insertion_supplier != '')){

    $insertion_name = "вставка ".$filter_name;

} else {
    $insertion_name = "";
}

/** УПАКОВКА ИНД*/
$box = $_POST['box'];
/** УПАКОВКА ГР */
$g_box = $_POST['g_box'];
/** ПРИМЕЧАНИЕ */
$remark = $_POST['remark'];
/** Высота ленты  */
$side_type = $_POST['side_type'];
/** Поролон */
if (isset($_POST['foam_rubber'])){
    $foam_rubber = 'поролон';
} else $foam_rubber = '';
/** Язычек */
if (isset($_POST['tail'])){
    $tail = 'язычек';
}else $tail='';
/** Форм-фактор */
if (isset($_POST['form_factor'])){
    $form_factor = 'трапеция';
}else $form_factor = '';

/** ПРоверка наличия фильтра в БД */
$a = check_filter($_POST['filter_name']);

/** Если фильтр уже есть в БД -> выход */
if ($a > 0){
    echo "Фильтр {$filter_name} уже есть в БД";
    exit();
}

/** Если фильтра в БД такого нет -> начинаем запись */

/** Запись информации о фильтре в БД */
$sql = "INSERT INTO salon_filter_structure(filter, category, paper_package, insertion_count, box, g_box, comment, foam_rubber, form_factor, tail, side_type) 
        VALUES ('$filter_name','$category','$p_p_name','$insertion_count','$box','$g_box','$remark', '$foam_rubber', '$form_factor', '$tail', '$side_type');";
$result = mysql_execute($sql);

/** Запись информации о гофропакете в БД */
$sql = "INSERT INTO paper_package_salon(p_p_name, p_p_height, p_p_width, p_p_pleats_count, p_p_supplier, p_p_remark, p_p_material) 
        VALUES ('$p_p_name','$p_p_height','$p_p_width','$p_p_pleats_count','$p_p_supplier','$p_p_remark','$p_p_material');";
$result = mysql_execute($sql);



echo "Фильтр {$filter_name} успешно добавлен в БД";

?>

<button onclick="window.close();">Закрыть окно</button>
