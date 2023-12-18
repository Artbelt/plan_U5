<?php
require_once('tools/tools.php');
require_once('settings.php');

/** Номер заявки которую надо нарисовать */
$order_number = $_POST['order_number'];

/** оформление номера заявки */
echo "Заявка: <section id='order_number'><b> ".$order_number."</b></section><p>";

/** Подключаемся к БД для вывода заявки по подключению №1*/
$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
if ($mysqli->connect_errno) {
    /** Если не получилось подключиться */
    echo 'Возникла проблема на сайте'
        . "Номер ошибки: " . $mysqli->connect_errno . "\n"
        . "Ошибка: " . $mysqli->connect_error . "\n";
    exit;
}
/** Выполняем запрос SQL по подключению №1*/
$sql = "SELECT * FROM orders WHERE order_number = '$order_number';";
if (!$result = $mysqli->query($sql)) {
    echo "Ошибка: Наш запрос не удался и вот почему: \n"
        . "Запрос: " . $sql . "\n"
        . "Номер ошибки: " . $mysqli->errno . "\n"
        . "Ошибка: " . $mysqli->error . "\n";
    exit;
}

/** Формируем шапку таблицы для вывода заявки */
echo "<table id='main_table' border='1' '>
        <tr>
            <th style=' border: 1px solid black'> Фильтр
            </th>
            <th style=' border: 1px solid black'> Количество, шт           
            </th>  
            <th style=' border: 1px solid black'> Распланировано
            </th>  
            <th style=' border: 1px solid black'> Не распланировано
            </th>                                                          
        </tr>";


/** Разбор массива значений по подключению №1 */
while ($row = $result->fetch_assoc()){
    $difference = (int)$row['count']-0;
    echo "<tr>"
        ."<td>".$row['filter']."</td>"
        ."<td>".$row['count']."</td>"
        ."<td>"."0"."</td>"
        ."<td>".$difference."</td>"
        ."</tr>";
}

echo    "<tr><td>Итого:</td><td></td><td></td><td></td></tr>";

echo "</table>";

/** Кнопка перехода в режим планирования для У2*/
echo "<br><form action='show_order.php' method='post'>"
    ."<input type='hidden' name='order_number' value='$order_number'>"
    ."<input type='submit' value='Выход из режима планирования'>"
    ."</form>";
?>


<script src="tools/calendar.js" type="text/javascript"></script>
<script type="text/javascript">

    trs = main_table.getElementsByTagName('tr');
    cnt = trs.length;
    cols = 2;
    let x=0;

    /* Занесение в ячейку количества фильтров */
    document.querySelector('table').onclick = (event) => {
        let cell = event.target;
        if (cell.tagName.toLowerCase() != 'td')
            return;
        let i = cell.parentNode.rowIndex;
        let j = cell.cellIndex;
        let main_table = document.getElementById('main_table');
        let added_into_plan = main_table.rows[i].cells[2].innerHTML;
        let need_count = main_table.rows[i].cells[3].innerHTML;

        /* добавляем значения если это не заголовочные ячейки */
        if ((j > 3)&&(i !== 0)&&(i !== main_table.rows.length-1)){
            let add_count = prompt("введите число");
            let difference_count = need_count - add_count;
            main_table.rows[i].cells[j].innerHTML = add_count;
            /* получаем сумму строки */
            let string_sum = 0;
            for (let z = 4; z <= main_table.rows[i].cells.length - 1; z++){
                string_sum = string_sum + Number(main_table.rows[i].cells[z].innerText);
            }
            /* вносим сумму распланированных изделий */
            main_table.rows[i].cells[2].innerHTML = string_sum;
            /* вносим сумму не распланированных изделий */
            main_table.rows[i].cells[3].innerHTML = main_table.rows[i].cells[1].innerHTML - Number(string_sum);
        }
        auto_calculate();
    }

    /* Добавление смены  в таблицу*/
    function add_row(){
        let i =0;
        for (i;i < cnt; i++){
            var newTd = document.createElement('td');
            if (i==0){
                x++;
                /* newTd.innerHTML = 'Смена №'+x; */
                newTd.innerHTML = '<input type="text" id="calendar" value="#date" size="6" onfocus="this.select();lcs(this)" onclick="event.cancelBubble=true;this.select();lcs(this)">';
            }else{
                newTd.innerHTML = ' ';
            }
            trs[i].appendChild(newTd);
        }
    }

    /* функция складывает суммы по столбцам */
    function auto_calculate() {
        /* складываем сумму во 2,3,4 столбцах */
        let ii =0;
        let main_table = document.getElementById('main_table');
        let count = 0;
        let planned =0;
        let not_planned=0;
        for (let i = 1; i < main_table.rows.length-1; i++ ){
            count = count + Number(main_table.rows[i].cells[1].innerText);
            planned = planned + Number(main_table.rows[i].cells[2].innerText);
            not_planned = not_planned + Number(main_table.rows[i].cells[3].innerText);
             ii=i;
        }

        main_table.rows[ii+1].cells[1].innerHTML=count;
        main_table.rows[ii+1].cells[2].innerHTML=planned;
        main_table.rows[ii+1].cells[3].innerHTML=not_planned;

        /* складываем столбцы смен */
        let shift_sum = 0; // сменная сумма
        if (main_table.rows.length > 4){
            /* для каждого столбца */
            for (let z = 4; z <= main_table.rows[0].cells.length - 1; z++){
                /* делаем проходы по каждой строке и собираем сумму для каждой строки */
                for (let x = 1; x < main_table.rows.length-1; x++ ){
                    shift_sum = shift_sum + Number(main_table.rows[x].cells[z].innerText);
                }
                main_table.rows[main_table.rows.length-1].cells[z].innerText = shift_sum;
                shift_sum = 0;
            }
        }
    }

</script>
<script>
    /* функция сохраняет распланированные фильтры по заявке в БД */
    /* проходим построчно все записи таблицы */
    function save_function() {
        let order_number; /* номер заявки */
        let filter_name; /* номер фильтра */
        let send_array; /* массив для отправки данных в php-скрипт
        формат массива: {send_array_part,send_array_part,send_array_part...send_array_part}*/
        let send_array_part; /* массив для формирования массива для отправки данных в php-скрипт для сохранения
        формат массива: {order_number, filter_name, {date,count},{date,count},..{date,count}} */
        let main_table = document.getElementById('main_table');
        send_array =[]; /* обнуляем отправочный массив */
        let date_count_array;
        if (main_table.rows[0].cells.length > 4){ /* если столбцов больше 4 значит есть распланированные дни -> выполняем */
            for (let i = 1; i < main_table.rows.length-1; i++){ /* для каждой строки таблицы... */
                send_array_part = []; /* обнуляем массив для формирования отправочного массива */
                order_number = document.getElementById('order_number').innerText; /* получаем номер заявки */
                filter_name = main_table.rows[i].cells[0].innerText; /* получаем номер фильтра */
                /* send_array_part.push(order_number); номер заявки будет передан скрипту в post-запросе */
                send_array_part.push(filter_name);
                for (let j = 4; j < main_table.rows[0].cells.length; j++){ /* проходим по ячейкам с 4 по последнюю и читаем
                    дату и количество фильтров, добавляем эту информацию в массив и массив добавляем в отправочный массив*/
                    date_count_array = []; /* обнуляем массив даты-количества */
                    date_count_array.push(main_table.rows[0].cells[j].children[0].value); /* получаем значение из календаря в ячейке */
                    date_count_array.push(main_table.rows[i].cells[j].innerText); /* получаем значение количества фильтров в ячейке */
                    send_array_part.push(date_count_array); /* */
                }
                /* Здесь будем добавлять массив send_array_part в массив send_array */
                send_array.push(send_array_part); /* имеем сформированный массив для отправки */
            }
            /* Здесь отправим массив php-скрипту в AJAX запросе */
            let xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("result_area").innerHTML = this.responseText; /* здесь будет выведен результат php-скрипта */
                }
            };
            let JSON_send_array = JSON.stringify(send_array);
            xhttp.open("POST", "save_planned_filters_into_db.php", true);
            xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhttp.send("JSON_send_array="+JSON_send_array+"&order_number="+order_number);

        } else {
            alert("Нечего сохранять") /* столбцов 4 и ни чего не спланировано */
        }
    }
</script>
<button id='add_button' value='' onclick="add_row()">Добавить смену</button>
<p>
<button id="save_button" value="" onclick="save_function()">Сохранить план</button>
<div id="result_area">ждем ответ скрипта</div>


