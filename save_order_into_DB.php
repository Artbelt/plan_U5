<?php /** Блок сохранения заявки в БД */

/** подключение фалйа настроек */
require_once('settings.php') ;
require_once('tools/tools.php') ;

if ($_POST['order_str']) {
    echo 'сохранение заявки...';

    $order = unserialize($_POST['order_str']);
    $workshop = $_POST['workshop'];

    /** соединение  с БД*/
   # $mysqli = new mysqli('127.0.0.1','root','','plan_U5');
    $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
    if($mysqli->connect_errno){ /** Соединение с БД не удалось */
        echo 'соединение с БД не удалось';
        echo "Номер ошибки: " . $mysqli->connect_errno . "\n";
        echo "Ошибка: " . $mysqli->connect_error . "\n";
        exit();
    }
    /** Соединение с БД удалось */



    foreach($order as $value) {
        $order0 = $_POST['order_name'];
        $order1 = $workshop;
        $order2 = $value['B'];
        $order3 = $value['C'];
        $order4 = $value['D'];
        $order5 = $value['E'];
        $order6 = $value['F'];
        $order7 = $value['G'];
        $order8 = $value['H'];
        $order9 = $value['I'];
        $order10 = $value['J'];
        $sql = "INSERT INTO orders (order_number, workshop,	filter,	count, marking, personal_packaging, personal_label, group_packaging, packaging_rate, group_label, remark)"
            . "VALUES ('$order0','$order1','$order2','$order3','$order4','$order5','$order6','$order7','$order8','$order9','$order10');";
        if (!$result = $mysqli->query($sql)){ echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n";
            exit;
        }
    }
    echo 'успешно завершено <p>';
    echo "<a href='main.php'>на главную</a>";

}
?>