<?php

if(isset($_FILES['userfile'])) {
    $uploaddir = 'uploads/';
    $uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

    $copied = copy($_FILES['userfile']['tmp_name'], $uploadfile);

    if ($copied)
    {
        echo "Файл корректен и был успешно загружен.\n";
    } else {
        echo "Неудача";
        die();
    }
}
$info = new SplFileInfo($uploadfile);
@rename ($uploadfile, "/upload/1.$info->getExtension();");
error_reporting(E_ALL);
set_time_limit(0);
date_default_timezone_set('Europe/London');
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

</head>
<body>

<?php

/** Include path **/
set_include_path(get_include_path() . PATH_SEPARATOR . '../../../Classes/');

/** PHPExcel_IOFactory */
@include 'PHPExcel/IOFactory.php';

//$inputFileName = './upload/'.$_FILES['userfile']['name'];
@$inputFileName = $uploadfile;

echo 'Загружен файл ',pathinfo($inputFileName,PATHINFO_BASENAME),' <br />';
@$objPHPExcel = PHPExcel_IOFactory::load($inputFileName);

@$sheetData = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);

/**Вывод заявки на экран */
$propusk = true;/** маркер пропуска начальной части файла и заголовков*/
$order = [];/**массив - заявка без лишних элементов, заголовков etc.*/
$workshop = 'U'.$sheetData['1']['C'];
echo '<h2>Заявка загружена</h2>';
echo 'для участка  №'.$sheetData['1']['C'] . '<br>' . 'на период ' . $sheetData['1']['E'] . ' = ' . $sheetData['1']['F']    ;
echo '<hr />';
echo '<table border="0">';
echo '<tr><td><b>Фильтр</b></td><td><b>Кол-во</b></td><td><b>Маркировка</b></td><td><b>Инд.упак.</b>'
    .'</td><td><b>Этик.инд.</b></td><td><b>групп.упак.<b></td><td><b>Hорма упак.</b></td><td><b>этик.групп.</b>'
    .'</td><td><b>Примечание</b></td></tr>';
foreach ($sheetData as $arr){
    if($arr['B']=='Марка фильтра') {$propusk = false; continue;}
    if(($propusk == false) && ($arr['B']!='')){/**Убираем пустые ячейки*/
        array_push($order, $arr);
        echo '<tr><td>' . $arr['B'] . '</td><td>' . $arr['C'] . '</td><td>' . $arr['D'] . '</td><td>' . $arr['E'] . '</td><td>' . $arr['F']
            . '</td><td>' . $arr['G'] . '</td><td>' . $arr['H'] . '</td><td>' . $arr['I'] . '</td><td>' . $arr['J'] . '</td></tr>';
    }
}
$propusk = true;
echo '</table><br>';
/** Переменная для сериализации и передачи массива в следующий скрипт */
$order_str = serialize($order);

echo <<<FORM
<hr>
<form action="save_order_into_DB.php" method="post">
Присвоить номер заявке
<input name="order_name" type="text"  placeholder="№X-X" width="200"/>
FORM;
echo "<input type='hidden' name='order_str' value='$order_str'/>";
echo "<input type='hidden' name='workshop' value='$workshop'/>";
echo "<input type='submit' value=' и сохранить в БД'/>";
echo "</form>";
?>
</body>
</html>