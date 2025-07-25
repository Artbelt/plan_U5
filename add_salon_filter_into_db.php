<?php
require_once('tools/tools.php');
?>
<!DOCTYPE html>
<html lang="ru" xmlns="http://www.w3.org/1999/html">
<head>
    <meta charset="utf-8" />
    <title>Добавление нового панельного фильтра в БД</title>
    <style>
        article, aside, details, figcaption, figure, footer,header,
        hgroup, menu, nav, section { display: block; }
    </style>
</head>
<body>


<?php  echo '<table border="1"><tr><td>'.$_POST['workshop'].'</td></tr></table>';
if (isset($_POST['filter_name'])){
    $filter_name = $_POST['filter_name'];
    //echo 'TEST_FILTER_NAME:'.$_POST['analog_filter'];
} else {
    $filter_name = '';
}

if (isset($_POST['analog_filter']) AND ($_POST['analog_filter'] != '')){
    $analog_filter = $_POST['analog_filter'];
    /** Если аналог установлен то загружаем всю информацию в поля о аналоге */
    echo "<p>ANALOG_FILTER=".$analog_filter;
    // массив для записи всех значений аналога
    $analog_data = get_salon_filter_data($analog_filter);
    //var_dump(get_salon_filter_data($analog_filter));

}else{
    echo "<p> Аналог не определен";
    $analog_data = array();
    $analog_data['paper_package_width'] ='';
    $analog_data['paper_package_height'] ='';
    $analog_data['paper_package_pleats_count'] ='';
    $analog_data['paper_package_remark'] ='';
    $analog_data['paper_package_supplier'] ='';
    $analog_data['insertion_count']='';
    $analog_data['paper_package_material']='';
    $analog_data['box'] ='';
    $analog_data['g_box'] ='';
    $analog_data['comment'] ='';
    $analog_data['foam_rubber']='';
    $analog_data['form_factor']='';
    $analog_data['tail']='';
    $analog_data['side_type']='';
}

?>

<form action="processing_add_salon_filter_into_db.php" method="post" >
    <label><b>Наименование фильтра</b>
    <input type="text" name="filter_name" size="40" value="<?php echo $filter_name?>"><p>
    </label>
    <div id="mark"></div>
    <label>Категория
    <select name="category">
        <option>Салонный</option>
    </select>
    </label><br>
    <hr>
    <label><b>Гофропакет:</b></label><p>

        <label>Ширина шторы: <input type="text" id="width_input" size="5" name="p_p_width" value="<?php echo $analog_data['paper_package_width'] ?>"></label>
        <label>Высота шторы:<input type="text" id="height_input" size="5" name="p_p_height" value="<?php echo $analog_data['paper_package_height'] ?>"> </label>
        <label>Кол-во ребер: <input type="text" size="5" name="p_p_pleats_count" value="<?php echo $analog_data['paper_package_pleats_count'] ?>"></label>
        <label>Поставщик: <select name="p_p_supplier"  ><option></option>
                                                        <option  <?php if ($analog_data['paper_package_supplier'] == 'У5'){echo 'selected';} ?> >У5</option>
                          </select></label><p>
        <label>Материал: Carbon <select name="p_p_material" ><option></option>
                                                         <option   <?php if ($analog_data['paper_package_material'] == 'Carbon'){echo 'selected';} ?>>Carbon</option>
                          </select></label><p>
        <label>Комментарий: <input type="text" size="50" name="p_p_remark" value=""></label><p>


        <hr>
    <label><b>Вставка</b></label><p>
        <label>Количество в фильтре: <input type="text" size="2" name="insertions_count" value="<?php echo $analog_data['insertion_count']?>"></label>

        <label>Поставщик: <select name="insertions_supplier"><option></option>
                                                    <option <?php if ($analog_data['insertion_count'] !='' ) {echo 'selected';} ?>> УУ</option>
                          </select><br></label>

    <hr>
    <label><b>Лента боковая</b></label><p>
        <label>Высота ленты: <input type="text" id="line_width_input" size="2" name="side_type" value="<?php echo $analog_data['side_type']?>"></label>


    <hr>
      <input type="checkbox" name="foam_rubber" <?php if(isset($analog_data['foam_rubber_checkbox_state'])){echo $analog_data['foam_rubber_checkbox_state'];}?> >Поролон

    <hr>
    <input type="checkbox" name="tail" <?php if(isset($analog_data['tail_checkbox_state'])){echo $analog_data['tail_checkbox_state'];}?> >Язычек

    <hr>
    <input type="checkbox" name="form_factor" <?php if (isset($analog_data['form_factor_checkbox_state'])){echo $analog_data['form_factor_checkbox_state'];}?> >Трапеция


    <hr>
    <label><b>Индивидуальная упаковка</b></label><p>
        <label>Коробка №:  <select name="box"><?php select_boxes($analog_data['box']);?></select></label><br>
    <hr>
    <label><b>Групповая упаковка</b></label><p>
        <label>Ящик №: <select name="g_box"><?php select_g_boxes($analog_data['g_box']);?></select></label><br>
    <hr>
    <label><b>Примечание</b>
        <input type="text" size="100" name="remark" value="<?php echo $analog_data['comment'] ; if( isset($analog_filter)){echo  'ANALOG_FILTER='.$analog_filter;};?>">
    </label><p>
    <hr>
    <input type="submit" value="Сохранить фильтр">

</form>
<script>

    function replacement ( field){
        // Отримуємо поле вводу
        var inputField = document.getElementById(field);

        // Додаємо обробник події "input"
        inputField.addEventListener('input', function() {
            // Отримуємо текст з поля вводу
            var text = inputField.value;

            // Замінюємо всі запяті на точки у тексті
            var newText = text.replace(/,/g, '.');

            // Оновлюємо значення поля вводу
            inputField.value = newText;
        });
    }
    replacement("width_input");
    replacement("height_input");
    replacement("line_width_input");

</script>

</body>
</html>



