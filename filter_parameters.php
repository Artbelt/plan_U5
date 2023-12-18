<?php /** filter_parameters.php  файл отображает инофрмацию про конструктив фильтра */

require_once('tools/tools.php');
require_once('settings.php');

/** @var  $filter  содержит наименование фильтра параметры которого мы запрашиваем*/
$filter = $_POST['filter_name'];

/** Если наименование фильтра не передано сценарию - просто прекращаем работу сценария */
if (!$filter) {exit();}

?>
    <div id="header" style="background-color: #5450ff; height: 50px; width: 100%; font-family: Calibri; font-size: 20px">
        <p style="color: white"><?php echo $filter; ?>:</p>
    </div>

<?php



/** Создаем подключение к БД */
$mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);


/** Выполняем запрос SQL  выборка из таблицы данных фильтров*/
$sql = "SELECT * FROM salon_filter_structure WHERE filter='".$filter."';";

/** Если запрос не удачный -> exit */
if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }

/** Разбор массива значений  */

while ($filter_data = $result->fetch_assoc()) {

    $type_of_filter = $filter_data['category'];
    $paper_package = $filter_data['paper_package'];
    $wireframe = $filter_data['wireframe'];
    $prefilter = $filter_data['prefilter'];
    $box = $filter_data['box'];
    $g_box = $filter_data['g_box'];
    $comment = $filter_data['comment'];
}
$result->close();

/** Выполняем запрос SQL  выборка из таблицы данных гофропакетов*/
$sql_paper = "SELECT * FROM paper_package_salon WHERE p_p_name='".$paper_package."';";

/** Если запрос не удачный -> exit */
if (!$result_paper= $mysqli->query($sql_paper)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }

    /** Разбор массива значений  */

    while ($paper_package_data = $result_paper->fetch_assoc()) {

        $p_p_length = $paper_package_data['p_p_length'];
        $p_p_height = $paper_package_data['p_p_height'];
        $p_p_width = $paper_package_data['p_p_width'];
        $p_p_pleats_count = $paper_package_data['p_p_pleats_count'];
        $p_p_amplifier = $paper_package_data['p_p_amplifier'];
        $p_p_remark = $paper_package_data['p_p_remark'];
    }

/** Закрываем соединение */
$result_paper->close();

/** Выполняем запрос SQL  выборка из таблицы данных каркасов*/
$sql_wireframe = "SELECT * FROM wireframe_panel WHERE w_name='".$wireframe."';";

/** Если запрос не удачный -> exit */
if (!$result_wireframe= $mysqli->query($sql_wireframe)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }

/** Разбор массива значений  */

while ($wireframe_data = $result_wireframe->fetch_assoc()) {

    $w_length = $wireframe_data['w_length'];
    $w_width = $wireframe_data['w_width'];
    $w_material = $wireframe_data['w_material'];

}

/** Закрываем соединение */
$result_wireframe->close();

/** Выполняем запрос SQL  выборка из таблицы данных предфильтров*/
$sql_prefilter = "SELECT * FROM prefilter_panel WHERE p_name='".$prefilter."';";

/** Если запрос не удачный -> exit */
if (!$result_prefilter= $mysqli->query($sql_prefilter)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }

/** Разбор массива значений  */

while ($prefilter_data = $result_prefilter->fetch_assoc()) {

    $p_length = $prefilter_data['p_length'];
    $p_width = $prefilter_data['p_width'];
    $p_material = $prefilter_data['p_material'];
    $p_remark = $prefilter_data['p_remark'];

}

/** Закрываем соединение */
$result_prefilter->close();

/** Закрываем соединение */
$mysqli->close();

/** Выводим данные */

    echo "Тип фильтра: ".$type_of_filter."<br>";
    echo "<p>";
    echo "<table style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>";
    echo "<tr><td  style=' border: 1px solid black'>Гофропакет: </td><td  style=' border: 1px solid black'>".$paper_package."</td></tr>";
    echo "<tr><td  style=' border: 1px solid black'>Длина : </td><td  style=' border: 1px solid black'> ".$p_p_length."</td></tr>";
    echo "<tr><td  style=' border: 1px solid black'>Высота : </td><td  style=' border: 1px solid black'> ".$p_p_height."</td></tr>";
    echo "<tr><td  style=' border: 1px solid black'>Ширина : </td><td  style=' border: 1px solid black'> ".$p_p_width."</td></tr>>";
    echo "<tr><td  style=' border: 1px solid black'>Количество ребер:  </td><td  style=' border: 1px solid black'>".$p_p_pleats_count."</td></tr>";
    echo "<tr><td  style=' border: 1px solid black'>Усилитель:  </td><td  style=' border: 1px solid black'>".$p_p_amplifier."</td></tr>";
    echo "<tr><td  style=' border: 1px solid black'>Комментарий:  </td><td  style=' border: 1px solid black'>".$p_p_remark."</td></tr>";
    echo "</table>";
    echo "<p>";
    if ($wireframe == ''){echo "Каркаса нет<br>";}
    else {echo "".$wireframe."<br>";
          echo "Длина :".$w_length."<br>";
          echo "Ширина :".$w_width."<br>";
          echo "Материал :".$w_material."<br>";
    }
    echo "<p>";
    if ($prefilter == ''){echo "Предфильтра нет<br>";}
    else {echo " ".$prefilter."<br>";
        echo "Длина :".$p_length."<br>";
        echo "Ширина :".$p_width."<br>";
        echo "Материал :".$p_material."<br>";
        echo "Комментарий :".$p_remark."<br>";
    }
    echo "<p>";
    echo "Упаковка: <br>";
    echo "индивидуальная №: ".$box."<br>";
    echo "групповая №: ".$g_box."<br><br>";
    echo "Комментарий: ".$comment."<br>";




echo '</form>';
