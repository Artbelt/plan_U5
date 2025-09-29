<?php /** tools.php в файле прописаны разные функции */

/** ПОдключаем функции */
require_once('C:/xampp/htdocs/plan_U5/settings.php') ;


/** Вывод массива в удобном виде
 * @param $a
 */
function print_r_my ($a){
    if (gettype($a)=='array') {
        echo "<pre>";
        print_r($a);
        echo "</pre>";
    }
}

/**  */
function show_ads(){

    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;

    $host = $mysql_host;
    $db = $mysql_database;
    $user = $mysql_user;
    $pass = $mysql_user_pass;

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Запрос активных объявлений
        $stmt = $pdo->prepare("SELECT * FROM ads WHERE expires_at >= NOW() ORDER BY expires_at ASC");
        $stmt->execute();
        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Ошибка подключения: " . $e->getMessage());
    }
    ?>
    <style>
        .ads-container {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .ads-container h1 {
            font-size: 24px;
            color: #0056b3;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .ads-container ul {
            list-style: none;
            padding: 0;
        }
        .ads-container li {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .ads-container h2 {
            font-size: 18px;
            margin: 0 0 10px;
            color: #333;
        }
        .ads-container p {
            font-size: 14px;
            margin: 0 0 10px;
        }
        .ads-container small {
            font-size: 12px;
            color: #666;
        }
        .ads-container .no-ads {
            font-size: 16px;
            color: #888;
            text-align: center;
        }
    </style>
    <div class="ads-container">
       Объявления:
        <?php if (!empty($ads)): ?>
            <ul>
                <?php foreach ($ads as $ad): ?>
                    <li>
                        <h2><?= htmlspecialchars($ad['title']) ?></h2>
                        <p><?= htmlspecialchars($ad['content']) ?></p>
                        <small>Действительно до: <?= htmlspecialchars($ad['expires_at']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="no-ads">Нет актуальных объявлений.</p>
        <?php endif; ?>
    </div>
    <?php

}

/** Отображение выпуска продукции за последнюю неделю */
function show_weekly_production(){
    $count = 0;
    echo "изготовленая продукция за последние 10 дней:<p> ";
    for ($a = 1; $a < 11; $a++) {

        $production_date = date("Y-m-d", time() - (60 * 60 * 24 * $a));;
        $production_date = reverse_date($production_date);
        $sql = "SELECT * FROM manufactured_production WHERE date_of_production = '$production_date';";
        $result = mysql_execute($sql);
        /** @var $x $variant counter */
        $x = 0;
        foreach ($result as $variant) {
            $x += $variant['count_of_filters'];
        }
        /** Выводим сумму фильтров */
        echo $production_date . " " . $x . " шт <br>";
        $count = $count + $x;

    }
    $count_per_day = $count / 10;
    if ($count_per_day > 1000){
        echo "Среднее количество в смену: <span class='highlight_green' title='Это количество обеспечит 30 000 фильтров в месяц'>".$count_per_day."</span>";
    } else {
        echo "Среднее количество в смену: <span class='highlight_red' title='Это количество НЕ обеспечит 30 000 фильтров в месяц'>".$count_per_day."</span>";
    }

}
/** Выборка имен фильтров */
function get_all_filters() {
    // Замените на вашу реальную логику получения данных
    $pdo = get_connection();
    $stmt = $pdo->query("SELECT id, filter_name FROM salon_filters ORDER BY filter_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Отображение выпуска продукции за месяц */
function show_monthly_production(){
    $first_day = date("Y-m-01"); // первое число текущего месяца
    $today = date("Y-m-d");

    $first_day_reversed = reverse_date($first_day);
    $today_reversed = reverse_date($today);

    $sql = "SELECT SUM(count_of_filters) as total FROM manufactured_production 
            WHERE date_of_production >= '$first_day_reversed' AND date_of_production <= '$today_reversed';";
    $result = mysql_execute($sql);

    $total = 0;
    if ($result) {
        $row = $result->fetch_assoc();  // вот здесь — корректно работаем с mysqli_result
        if ($row && isset($row['total'])) {
            $total = $row['total'];
        }
    }

    echo "<p>В текущем месяце произведено $total фильтров (с 1 числа по сегодня).";
}






/** Создание <SELECT> списка с перечнем заявок */
//function load_orders($list){
//
//    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;
//
//    /** Создаем подключение к БД */
//    $mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);
//
//    /** Выполняем запрос SQL для загрузки заявок*/
//    $sql = "SELECT DISTINCT order_number, workshop FROM orders;";
//
//    /** Если запрос не удачный -> exit */
//    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n";
//        exit;
//    }
//
//    if ($list == '0') {
//
//        /** Разбор массива значений для выпадающего списка */
//        echo "<select id='selected_order'>";
//        while ($orders_data = $result->fetch_assoc()) {
//            echo "<option name='order_number' value=" . $orders_data['order_number'] . ">" . $orders_data['order_number'] . "</option>";
//        }
//        echo "</select>";
//    } else {
//        echo 'Перечень заявок';
//        /** Разбор массива значений для списка чекбоксов */
//        echo "<form action='orders_editor.php' method='post'>";
//        while ($orders_data = $result->fetch_assoc()) {
//            echo "<input type='checkbox' name='order_name[]'value=".$orders_data['order_number']." <label>".$orders_data['order_number'] ."</label><br>";
//        }
//        echo "<button type='submit'>Объединить для расчета</button>";
//        echo "</form>";
//
//    }
//    /** Закрываем соединение */
//    $result->close();
//    $mysqli->close();
//}

// СМОТРИ СТАРУЮ ВЕРСИЯ ФУНКЦИИ ВІШЕ!!!!!
function load_orders($list){
    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;

    $mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);
    $sql = "SELECT DISTINCT order_number, workshop FROM orders;";
    if (!$result = $mysqli->query($sql)){
        echo "Ошибка: {$mysqli->error}";
        exit;
    }

    if ($list == 0) {
        echo "<select id='selected_order' name='order_number'>";
        while ($row = $result->fetch_assoc()) {
            $val = htmlspecialchars($row['order_number'], ENT_QUOTES, 'UTF-8');
            echo "<option value=\"$val\">$val</option>";
        }
        echo "</select>";
    } else {
        echo 'Перечень заявок';
        echo "<form action='orders_editor.php' method='post'>";
        while ($row = $result->fetch_assoc()) {
            $val = htmlspecialchars($row['order_number'], ENT_QUOTES, 'UTF-8');
            echo "<label><input type='checkbox' name='order_name[]' value=\"$val\"> $val</label><br>";
        }
        echo "<button type='submit'>Объединить для расчета</button>";
        echo "</form>";
    }

    $result->close();
    $mysqli->close();
}



/** Создание <SELECT> списка с перечнем распланированных заявок */
function load_planned_orders(){
    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;

    $mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);
    
    // Получаем только заявки, которые есть в таблице build_plan (распланированные)
    $sql = "SELECT DISTINCT o.order_number, o.workshop 
            FROM orders o 
            INNER JOIN build_plan bp ON o.order_number = bp.order_number 
            ORDER BY o.order_number DESC";
    
    if (!$result = $mysqli->query($sql)){
        echo "Ошибка: {$mysqli->error}";
        exit;
    }

    echo "<select id='selected_order' name='order_number'>";
    while ($row = $result->fetch_assoc()) {
        $val = htmlspecialchars($row['order_number'], ENT_QUOTES, 'UTF-8');
        echo "<option value=\"$val\">$val</option>";
    }
    echo "</select>";
    
    $result->close();
    $mysqli->close();
}

/** СОздание <SELECT> списка с перечнем фильтров имеющихся в БД */
function load_filters_into_select(){

    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;

    /** Создаем подключение к БД */
    $mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);

    /** Выполняем запрос SQL для загрузки заявок*/
    $sql = "SELECT DISTINCT filter FROM salon_filter_structure;";

    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n";
        exit;
    }

    /** Создаем промежуточный массив для сортировки в него записываем данные, сортируем и выводим в список */
    $sorted_values = array();
    while ($orders_data = $result->fetch_assoc()){
        array_push($sorted_values, $orders_data['filter']);
    }
    sort($sorted_values);
    echo "<select name='analog_filter'>";
    echo "<option value=''>выбор фильтра</option>";
    for ($x=0; $x < count($sorted_values); $x++){
        echo "<option value='".$sorted_values[$x]."'>".$sorted_values[$x]."</option>";
    }
    echo "</select>";


    /** Закрываем соединение */
    $result->close();
    $mysqli->close();
}


/** Списание фильтров в выпущенную продукцию
 * @param $date_of_production
 * @param $order_number
 * @param $filters
 * @return bool
 */
function write_of_filters($date_of_production, $order_number, $filters, $team){

    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;

    /** Создаем подключение к БД */
    $mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);

    /** Цикл для разбора значений массива со значениями "фильтер - количество" */
    foreach ($filters as $filter_record) {

        /** Получили значение {елемент масива[фильтр][количество]} */
        $filter_name = $filter_record[0];
        $filter_count = $filter_record[1];

        /** Форматируем sql-запрос, "записать в БД -> дата -> заявка -> фильтер -> количство" */
        $sql = "INSERT INTO manufactured_production (date_of_production, name_of_filter, count_of_filters, name_of_order, team) 
                VALUES ('$date_of_production','$filter_name','$filter_count','$order_number', '$team')";

        /** Выполняем запрос. Если запрос не удачный -> exit */
        if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n";
            /** в случае неудачи функция выводит FALSЕ */
            return false;
            exit;
        }
    }

    /** Закрываем соединение */
    return true;
}


/** Функция возвращает получает дату в формате dd-mm-yy а возвращает yy-mm-dd */
function reverse_date($date){

    $reverse_date=date('Y-m-d',strtotime($date));
    return $reverse_date;
}

/** Функция возвращает количество произведенных указанных фильтров по указанной заявке */
/** функция возвращает массив ARRAY['ПЕРЕЧЕНЬ_ДАТ_И_КОЛИЧЕСТВ','КОЛИЧЕСТВО_СУММАРНОЕ'] */
function select_produced_filters_by_order($filter_name, $order_name){

    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;
    $count = 0;
    /** Подключение к БД   */
    $mysqli = new mysqli($mysql_host,$mysql_user, $mysql_user_pass, $mysql_database);

    /** Если не получилось подключиться.  */
    if ($mysqli->connect_errno) {
        echo  "Номер ошибки: " . $mysqli->connect_errno . "\n" . "Ошибка: " . $mysqli->connect_error . "\n";
        return "ERROR#02";
    }
    /** Выполняем запрос SQL по подключению */
    $sql = "SELECT * FROM manufactured_production WHERE name_of_order = '$order_name' AND name_of_filter = '$filter_name';";

    /** Если запрос не удался */
    if (!$result = $mysqli->query($sql)) {
        echo "Номер ошибки: " . $mysqli->errno . "\n". "Ошибка: " . $mysqli->error . "\n";
        return "ERROR#01";
    }

    $dates = [];

    /** Разбираем результата запроса */
    while ($row = $result->fetch_assoc()){
        $count += $row['count_of_filters'];
        array_push($dates, $row['date_of_production'],$row['count_of_filters']);
    }

    /** Создаем массив для вывода результата*/
    $result_part_one = $dates;
    $result_part_two = $count;
    $result_array = [];
    array_push($result_array,$result_part_one);
    array_push($result_array,$result_part_two);

    return $result_array;}

/** Функция выполняет запрос к БД и создает выборку заявки по выбранному номеру */
function show_order($order_number){
    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;
    /** Подключаемся к БД для вывода заявки */
    $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
    if ($mysqli->connect_errno) {
        /** Если не получилось подключиться */
        echo 'Возникла проблема на сайте'
            . "Номер ошибки: " . $mysqli->connect_errno . "\n"
            . "Ошибка: " . $mysqli->connect_error . "\n";
        exit;
    }
    /** Выполняем запрос SQL */
    $sql = "SELECT * FROM orders WHERE order_number = '$order_number';";
    if (!$result = $mysqli->query($sql)) {
        echo "Ошибка: Наш запрос не удался и вот почему: \n"
            . "Запрос: " . $sql . "\n"
            . "Номер ошибки: " . $mysqli->errno . "\n"
            . "Ошибка: " . $mysqli->error . "\n";
        exit;
    }
    return $result; // Выход из функции, дальше какая-то лажа


//************************************************** надо разобраться, тут какая-то лажа получилась*********************************//
    /** Формируем шапку таблицы для вывода заявки */
    echo "<table style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>
        <tr>
            <th style=' border: 1px solid black'> Фильтр
            </th>
            <th style=' border: 1px solid black'> Количество, шт           
            </th>                                                     
        </tr>";

    /**  массив для сохранения заявки в объект планирования */
    $order_array = array();

    /** Разбор массива значений по подключению */
    while ($row = $result->fetch_assoc()){

        echo "<tr>"
            ."<td style=' border: 1px solid black'>".$row['filter']."</td>"
            ."<td style=' border: 1px solid black'>".$row['count']."</td>"
            ."</tr>";

        /** наполняем массив для сохранения заявки */
        $temp_array = array();
        array_push($temp_array, $row['filter']);
        array_push($temp_array, $row['count']);
        array_push($order_array,$temp_array);
    }

    echo "</table>";
//************************************************** конец лажи где надо разобраться********************************************//


}

/** Функция складывает одинаковые элементы массива. ПРимер
 *      [[1][100]    =>  [[1][140]
 *       [2][ 50]         [2][100]
 *       [2][ 50]         [3][ 10]]
 *       [1][ 40]
 *       [3][ 10]]
 */
function summ_the_same_elements_of_array($input_array){

    function compare($a, $b){ // функция для сортировки массива в usort
        if ($a == $b){
            return 0;
        }
        return ($a < $b) ? -1 : 1;
    }

    usort($input_array,"compare");


    $finish_array = array();
    $summ = 0;
    $x = 0;
    $b = 0;
    $c = 0;
    $size = count($input_array) - 1;
    for ($n = 0; $n <= $size; $n++) {

        if ($n == $size) {
            $a = $input_array[$size][0];
            $summ += $input_array[$n][1];
            $x++;
            array_push($finish_array, array($a,$summ));
            $summ = 0;
        } else {
            $a = $input_array[$n][0];
            $b = $input_array[$n + 1][0];
            $c = (int)$b - (int)$a;

            switch ($c) {
                case 0:
                    $summ += $input_array[$n][1];
                    break;
                case ($c != 0):
                    $summ += $input_array[$n][1];
                    $x++;
                    array_push($finish_array, array($a,$summ));
                    $summ = 0;
                    break;
            }
        }
    }
    return $finish_array;
}


/** Функция из заявки возвращает массив вида ...[[filter][count]][[filter][count]] */
function get_order($order_number){

    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;
    /** Подключаемся к БД для вывода заявки */
    $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
    if ($mysqli->connect_errno) {
        /** Если не получилось подключиться */
        echo 'Возникла проблема на сайте'
            . "Номер ошибки: " . $mysqli->connect_errno . "\n"
            . "Ошибка: " . $mysqli->connect_error . "\n";
        exit;
    }
    /** Выполняем запрос SQL */
    $sql = "SELECT * FROM orders WHERE order_number = '$order_number';";
    if (!$result = $mysqli->query($sql)) {
        echo "Ошибка: Наш запрос не удался и вот почему: \n"
            . "Запрос: " . $sql . "\n"
            . "Номер ошибки: " . $mysqli->errno . "\n"
            . "Ошибка: " . $mysqli->error . "\n";
        exit;
    }

    /**  массив для сохранения заявки в объект планирования */
    $order_array = array();

    /** Разбор массива значений по подключению */
    while ($row = $result->fetch_assoc()){

        /** наполняем массив для сохранения заявки */
        $temp_array = array();
        array_push($temp_array, $row['filter']);
        array_push($temp_array, $row['count']);
        array_push($order_array,$temp_array);
    }
    return $order_array;
}

/** Функция обеспечивает подключение к БД и выполняет запрос sql */
/** возвращает результат выполнения sql запроса */
function mysql_execute($sql){
    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;
    /** Подключаемся к БД */
    $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
    if ($mysqli->connect_errno) {
        /** Если не получилось подключиться */
        echo 'Возникла проблема на сайте'
            . "Номер ошибки: " . $mysqli->connect_errno . "\n"
            . "Ошибка: " . $mysqli->connect_error . "\n";
        exit;
    }
    /** Выполняем запрос SQL */
    if (!$result = $mysqli->query($sql)) {
        echo "Ошибка: Наш запрос не удался и вот почему: \n"
            . "Запрос: " . $sql . "\n"
            . "Номер ошибки: " . $mysqli->errno . "\n"
            . "Ошибка: " . $mysqli->error . "\n";
        exit;
    }

    return $result;
}

/** Функция формирует список коробок имеющихся в БД
 * если в функцию передается переменная, то выбирается коробка, соответствующая переменной
 */
function select_boxes($index){
    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;
    $mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);
    /** Подключаемся к БД */
    if ($mysqli->connect_errno){/** Если не получилось подключиться */
        echo 'Возникла проблема на сайте'."Номер ошибки: " . $mysqli->connect_errno . "\n"."Ошибка: " . $mysqli->connect_error . "\n";
        exit;
    }

    $sql = "SELECT * FROM box ORDER BY b_name";
    #$sql = "SELECT * FROM box";
    /** Выполняем запрос SQL */
    if (!$result = $mysqli->query($sql)) {
        echo "Ошибка: Наш запрос не удался и вот почему: \n"
            . "Запрос: " . $sql . "\n"
            . "Номер ошибки: " . $mysqli->errno . "\n"
            . "Ошибка: " . $mysqli->error . "\n";
        exit;
    }
    /** извлечение ассоциативного массива */

    echo "<option></option>";

    while ($row = $result->fetch_assoc()) {
        echo "<option";
        // если номер коробки указан, то делаем ее выбранной
        if ($row['b_name'] == $index) echo " selected ";
        echo ">".$row['b_name']."</option>";
    }

    /* удаление выборки */
    $result->free();

}

/** Функция формирует список ящиков имеющихся в БД */
function select_g_boxes($index){

    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;
    $mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);
    /** Подключаемся к БД */
    if ($mysqli->connect_errno){/** Если не получилось подключиться */
        echo 'Возникла проблема на сайте'."Номер ошибки: " . $mysqli->connect_errno . "\n"."Ошибка: " . $mysqli->connect_error . "\n";
        exit;
    }

    $sql = "SELECT * FROM g_box";
    /** Выполняем запрос SQL */
    if (!$result = $mysqli->query($sql)) {
        echo "Ошибка: Наш запрос не удался и вот почему: \n"
            . "Запрос: " . $sql . "\n"
            . "Номер ошибки: " . $mysqli->errno . "\n"
            . "Ошибка: " . $mysqli->error . "\n";
        exit;
    }
    /** извлечение ассоциативного массива */
    echo "<option></option>";


     while ($row = $result->fetch_assoc()) {
        echo "<option";
        // если номер ящика указан, то делаем ее выбранной
        if ($row['gb_name'] == $index) echo " selected ";
        echo ">".$row['gb_name']."</option>";
    }



    /* удаление выборки */
    $result->free();

}

/** Проверка наличия фильтра в БД */
function check_filter($filter){

    $result = mysql_execute("SELECT * FROM salon_filter_structure WHERE filter = '$filter'");

    if ($result->num_rows > 0) {
        $a = true;
    } else {
        $a = false;
    }

    return $a;
}

/** Получаем всю информацию о фильтре:
 * -------------------------------
 * гофропакет: длина, ширина, высота, Количество ребер, усилитель, поставщик, комментарий
 * каркас: длина, ширина, материал, поставщик
 * предфильтр: длина, ширина, материал, поставщик, комментарий
 * индивидуальная упаковка
 * групповая упаковка
 * примечание
 * ----------------------------
 *
 */
function get_salon_filter_data($target_filter){

    global $mysql_host,$mysql_user,$mysql_user_pass,$mysql_database;
    /** Создаем подключение к БД */
    $mysqli = new mysqli($mysql_host,$mysql_user,$mysql_user_pass,$mysql_database);

    /** @var  $result_array  массив вывода результата*/
    $result_array = array();

    /** ГОФРОПАКЕТ */
    $result_array['paper_package_name'] = 'гофропакет '.$target_filter;

    /** Выполняем запрос SQL */
    $sql = "SELECT * FROM paper_package_salon WHERE p_p_name = '".$result_array['paper_package_name']."';";
    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
    /** Разбор массива значений  */
    $paper_package_data = $result->fetch_assoc();

    if (isset($paper_package_data['p_p_width'])){$result_array['paper_package_width'] = $paper_package_data['p_p_width'];}else{$result_array['paper_package_width'] ='';}
    if (isset( $paper_package_data['p_p_height'])){$result_array['paper_package_height'] = $paper_package_data['p_p_height'];}else{$result_array['paper_package_height'] ='';}
    if (isset( $paper_package_data['p_p_pleats_count'])){$result_array['paper_package_pleats_count'] = $paper_package_data['p_p_pleats_count'];}else{$result_array['paper_package_pleats_count'] ='';}
    if (isset( $paper_package_data['p_p_supplier'])){$result_array['paper_package_supplier'] = $paper_package_data['p_p_supplier'];}else{$result_array['paper_package_supplier'] = '';}
    if (isset( $paper_package_data['p_p_remark'])){$result_array['paper_package_remark'] = $paper_package_data['p_p_remark'];}else{$result_array['paper_package_remark'] = '';}
    if (isset( $paper_package_data['p_p_material'])){$result_array['paper_package_material'] = $paper_package_data['p_p_material'];}else{$result_array['paper_package_material'] ='';}


    /** Вставка */
    $sql = "SELECT * FROM salon_filter_structure WHERE filter = '".$target_filter."';";
    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
   /** Разбор массивыа значений */
    $salon_filter_data = $result->fetch_assoc();

    if (isset($salon_filter_data['insertion_count'])){$result_array['insertion_count'] = $salon_filter_data['insertion_count'];}else{$result_array['insertion_count'] = '';}


    /** КОРОБКА ИНДИВИДУАЛЬНАЯ */
    /** Выполняем запрос SQL */
    $sql = "SELECT box FROM salon_filter_structure WHERE filter = '".$target_filter."';";
    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
    /** Разбор массива значений  */
    $box_data = $result->fetch_assoc();

    if (isset($box_data['box'])){$result_array['box'] = $box_data['box'];}else{$result_array['box'] ='';}

    /** КОРОБКА ГРУППОВАЯ */
    /** Выполняем запрос SQL */
    $sql = "SELECT g_box FROM salon_filter_structure WHERE filter = '".$target_filter."';";
    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
    /** Разбор массива значений  */
    $g_box_data = $result->fetch_assoc();

    if (isset($g_box_data['g_box'])){$result_array['g_box'] = $g_box_data['g_box'];}else{$result_array['g_box'] = '';}


    /** ПРИМЕЧАНИЯ */
    /** Выполняем запрос SQL */
    $sql = "SELECT comment FROM salon_filter_structure WHERE filter = '".$target_filter."';";
    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
    /** Разбор массива значений  */
    $comment_data = $result->fetch_assoc();

    if (isset($comment_data['comment'])){$result_array['comment'] = $comment_data['comment'];}else{$result_array['comment'] = '';}


    /** Поролон */
    $sql = "SELECT foam_rubber FROM salon_filter_structure WHERE filter = '".$target_filter."';";
    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
    /** Разбор массива значений  */
    $foam_rubber_data = $result->fetch_assoc();


    if (isset($foam_rubber_data['foam_rubber'])){$result_array['foam_rubber'] = $foam_rubber_data['foam_rubber'];}else{$result_array['foam_rubber'] = '';}


    if ($result_array['foam_rubber'] == 'поролон'){
        $result_array['foam_rubber_checkbox_state'] = 'checked';
    } else  $result_array['foam_rubber_checkbox_state'] = '';

    /** Язычек */
    $sql = "SELECT tail FROM salon_filter_structure WHERE filter = '".$target_filter."';";
    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
    /** Разбор массива значений  */
    $tail_data = $result->fetch_assoc();



    if (isset($tail_data['tail'])){$result_array['tail'] = $tail_data['tail'];}else{$result_array['tail'] = '';}


    if ($result_array['tail']  == 'язычек'){
        $result_array['tail_checkbox_state'] = 'checked';
    } else  $result_array['tail_checkbox_state'] = '';

    /** Форма */
    $sql = "SELECT form_factor FROM salon_filter_structure WHERE filter = '".$target_filter."';";
    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
    /** Разбор массива значений  */
    $form_factor_data = $result->fetch_assoc();


    if (isset($form_factor_data['form_factor'])){$result_array['form_factor'] = $form_factor_data['form_factor'];}else{$result_array['form_factor'] ='';}

    if ($result_array['form_factor'] == 'трапеция'){
        $result_array['form_factor_checkbox_state'] = 'checked';
    } else  $result_array['form_factor_checkbox_state'] = '';

    /** Высота ленты*/
    $sql = "SELECT side_type FROM salon_filter_structure WHERE filter = '".$target_filter."';";
    /** Если запрос не удачный -> exit */
    if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n"; exit; }
    /** Разбор массива значений  */
    $side_type_data = $result->fetch_assoc();

    if (isset($side_type_data['side_type'])){$result_array['side_type'] = $side_type_data['side_type'];}else{$result_array['side_type'] ='';}



    /** Закрываем соединение */
    $result->close();
    $mysqli->close();

    return $result_array;
}

/** Расчет  необходимого количества каркасов для выполнения запявки*/
function component_analysis_wireframe($order_number){

    // шапка таблицы
    echo '<table style=" border-collapse: collapse;">';
    echo '<tr><td colspan="4"><h3 style="font-family: Calibri; size: 20px;text-align: center">Заявка</h3></td></tr>';
    echo '<tr><td colspan="4">на поставку комплектующих для: У2</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td>№п/п</td><td>Комплектующее</td><td>Кол-во</td><td>Дата поставки</td></tr>';

    // запрос для выборки необходимых каркасов для выполнения заявки
    $sql = "SELECT orders.filter, salon_filter_structure.wireframe, salon_filter_structure.filter, orders.count ".
        "FROM orders, salon_filter_structure ".
        "WHERE orders.order_number='$order_number' ".
        "AND orders.filter = salon_filter_structure.filter ".
        "AND salon_filter_structure.wireframe!='';";

    $result = mysql_execute($sql);

    $i=1;// счетчик циклов для отображения в таблице порядкового номера
    foreach ($result as $value){

              echo '<tr><td>'.$i.'</td><td>'.$value['wireframe'].'</td><td>'.$value['count'].'</td><td><input type="text"></td>';
              $i++;
    }

    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Дата составления заявки:</td><td colspan="2">'.date('d.m.y ').'</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Заявку составил:</td><td colspan="2"><input type="text"></td></tr>';
    echo '</table>';
}

/** Расчет  необходимого количества предфильтров для выполнения запявки*/
function component_analysis_prefilter($order_number){

    // шапка таблицы
    echo '<table style=" border-collapse: collapse;">';
    echo '<tr><td colspan="4"><h3 style="font-family: Calibri; size: 20px;text-align: center">Заявка</h3></td></tr>';
    echo '<tr><td colspan="4">на поставку комплектующих для: У2</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td>№п/п</td><td>Комплектующее</td><td>Кол-во</td><td>Дата поставки</td></tr>';

    // запрос для выборки необходимых каркасов для выполнения заявки
    $sql = "SELECT orders.filter, salon_filter_structure.prefilter, salon_filter_structure.filter, orders.count ".
        "FROM orders, salon_filter_structure ".
        "WHERE orders.order_number='$order_number' ".
        "AND orders.filter = salon_filter_structure.filter ".
        "AND salon_filter_structure.prefilter!='';";

    $result = mysql_execute($sql);

    $i=1;// счетчик циклов для отображения в таблице порядкового номера
    foreach ($result as $value){

        echo '<tr><td>'.$i.'</td><td>'.$value['prefilter'].'</td><td>'.$value['count'].'</td><td><input type="text"></td>';
        $i++;
    }

    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Дата составления заявки:</td><td colspan="2">'.date('d.m.y ').'</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Заявку составил:</td><td colspan="2"><input type="text"></td></tr>';
    echo '</table>';
}

/** Расчет  необходимого количества гофропакетов для выполнения запявки*/
function component_analysis_paper_package($order_number){

    // шапка таблицы
    echo '<table style=" border-collapse: collapse;">';
    echo '<tr><td colspan="4"><h3 style="font-family: Calibri; size: 20px;text-align: center">Заявка</h3></td></tr>';
    echo '<tr><td colspan="4">на поставку комплектующих для: У2</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td>№п/п</td><td>Комплектующее</td><td>Кол-во</td><td>Дата поставки</td></tr>';

    // запрос для выборки необходимых каркасов для выполнения заявки
    $sql = "SELECT orders.filter, salon_filter_structure.paper_package, salon_filter_structure.filter, orders.count ".
        "FROM orders, salon_filter_structure ".
        "WHERE orders.order_number='$order_number' ".
        "AND orders.filter = salon_filter_structure.filter ".
        "AND salon_filter_structure.paper_package!='';";

    $result = mysql_execute($sql);

    $i=1;// счетчик циклов для отображения в таблице порядкового номера
    foreach ($result as $value){

        echo '<tr><td>'.$i.'</td><td>'.$value['paper_package'].'</td><td>'.$value['count'].'</td><td><input type="text"></td>';
        $i++;
    }

    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Дата составления заявки:</td><td colspan="2">'.date('d.m.y ').'</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Заявку составил:</td><td colspan="2"><input type="text"></td></tr>';
    echo '</table>';
}

/** Расчет  необходимого количества групповых ящиков для выполнения заявки*/
function component_analysis_group_box($order_number){

    // запрос для выборки необходимых каркасов для выполнения заявки
    $sql = "SELECT orders.filter, salon_filter_structure.paper_package, salon_filter_structure.g_box, orders.count ".
        "FROM orders, salon_filter_structure ".
        "WHERE orders.order_number='$order_number' ".
        "AND orders.filter = salon_filter_structure.filter ".
        "AND salon_filter_structure.g_box!='';";

    $result = mysql_execute($sql);
    $temp_array = array(); // массив для сложения одинковых элементов

    foreach ($result as $value){
        array_push($temp_array,array($value['g_box'],$value['count']));
    }

     $temp_array = summ_the_same_elements_of_array($temp_array);

    echo '<table style=" border-collapse: collapse;">';
    echo '<tr><td colspan="4"><h3 style="font-family: Calibri; size: 20px;text-align: center">Заявка</h3></td></tr>';
    echo '<tr><td colspan="4">на поставку ящиков груповых для: У2</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td>№п/п</td><td>Комплектующее</td><td>Кол-во</td><td>Дата поставки</td></tr>';

    $i=1;// счетчик циклов для отображения в таблице порядкового номера
    foreach ($temp_array as $value){
        echo '<tr><td>'.$i.'</td><td>'.$value[0].'</td><td>'.round(($value[1]/10)).'</td><td><input type="text"></td>';
        $i++;
    }

    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Дата составления заявки:</td><td colspan="2">'.date('d.m.y ').'</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Заявку составил:</td><td colspan="2"><input type="text"></td></tr>';
    echo '</table>';

}


/** Расчет  необходимого количества коробок индивидуальных для выполнения заявки*/
function component_analysis_box($order_number){

    // запрос для выборки необходимых каркасов для выполнения заявки
    $sql = "SELECT orders.filter, salon_filter_structure.box, orders.count ".
        "FROM orders, salon_filter_structure ".
        "WHERE orders.order_number='$order_number' ".
        "AND orders.filter = salon_filter_structure.filter ".
        "AND salon_filter_structure.box!='';";

    $result = mysql_execute($sql);
    $temp_array = array(); // массив для сложения одинковых элементов

    foreach ($result as $value){
        array_push($temp_array,array($value['box'],$value['count']));
    }

    /** временно выключаем функцию сложения однаковых позиций, так как в ней очевидно ошибка */
   // $temp_array = summ_the_same_elements_of_array($temp_array);


    echo '<table style=" border-collapse: collapse;">';
    echo '<tr><td colspan="4"><h3 style="font-family: Calibri; size: 20px;text-align: center">Заявка</h3></td></tr>';
    echo '<tr><td colspan="4">на поставку коробок индивидуальных для: У2</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td>№п/п</td><td>Комплектующее</td><td>Кол-во</td><td>Дата поставки</td></tr>';

    $i=1;// счетчик циклов для отображения в таблице порядкового номера
    foreach ($temp_array as $value){
        echo '<tr><td>'.$i.'</td><td>'.$value[0].'</td><td>'.$value[1].'</td><td><input type="text"></td>';
        $i++;
    }

    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Дата составления заявки:</td><td colspan="2">'.date('d.m.y ').'</td></tr>';
    echo '<tr><td colspan="4"><pre> </pre></td></tr>';
    echo '<tr><td colspan="2">Заявку составил:</td><td colspan="2"><input type="text"></td></tr>';
    echo '</table>';
}

?>
