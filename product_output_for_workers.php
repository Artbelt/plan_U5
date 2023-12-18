<?php require_once('tools/tools.php')?>

<html>
<head>

    <title>
        Plan system
    </title>
   <!--<link rel="stylesheet" href="sheets.css">-->
    <style type="text/css">
        div {
            width: 400px; /* Ширина элемента в пикселах */
            padding: 10px; /* Поля вокруг текста */
            margin: auto; /* Выравниваем по центру */
           <!-- background: #4b91ad;--> /* Цвет фона */
        }
        select{
            width: 180px;
        }
        info {
            background: green;
        }
        </style>
</head>
<body>

<!--Скрипт отлова нажатий клавиш -->
<script>
    document.addEventListener('keydown', function(event) {
        if (event.code == 'ShiftLeft') {
            document.getElementById("count").focus();

        }
        if ((event.code == 'Enter')||(event.code == 'NumpadEnter')){
            document.getElementById("text").focus();
            document.getElementById("add_button").click();
        }
    });
</script>

    <!-- скрипт календаря -->
    <script src="tools/calendar.js" type="text/javascript"></script>

    <!-- Обработка выбора позиции -->
    <script>
        function loadDoc_1() {

            var xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("demo").innerHTML = this.responseText;
                }
            };
            var request = "load_filters_from_db.php?filter="+document.getElementById("text").value;
            xhttp.open("GET", request, true);
            xhttp.send();
        }
    </script>
    <script> // добавление записи(позиции) в таблицу
        var array_of_filters = [];
        var main_count = 0;

        function add_to_list() {

            var count = document.getElementsByTagName("input")[2].value;               // количество выпущенной продукции
            if (count == ""){
                alert("Не указано количество");
                return;
            }

            var n = document.getElementById("select_filter").options.selectedIndex;     // номер выбранной строка списка
            var filter = document.getElementById("select_filter").options[n].text;      // выбранный фильтр в списке
            //document.getElementById("final_list").innerHTML += filter + " = " + count + "<br>";      // вывод на
            let newOption = new Option(filter+" - "+count,"200");
            //maked_filters.prepend(newOption);
            maked_filters.add(newOption);


            //создаем массив для пары значений "фильтр=количество"
            var array_of_one_filter = [];
            //добавляем в него "фильтр", "количство"
            array_of_one_filter.push(filter,count);
            //добавлем созданный массив в массив со связками "фильтер-количество"
            array_of_filters.push(array_of_one_filter);
            //подсчитываем количество выпущенной продукции
            main_count += Number(count);
        }
    </script>
    <script>

        function send_filters_to_write_off() {//отправка данных для списания выпуска продукции

            //проверка заполненности полей для проведения выпуска продукции
            //проверка таблицы с выпущенной продукцией
            let filter_list = document.getElementById('maked_filters');
            let size = filter_list.length;

            if (size === 0){
                alert("Не выбраны фильтры");
                return;
            }
            //проверка календаря
            let calendar_box = document.getElementById('calendar');
            if (calendar_box.value == "dd-mm-yy"){
                alert("Не выбрана дата");
                return;
            }

            //выбор номера заявки из заголовка списка для передачи в ajax-запрос
            let selected_order_index = document.getElementById("selected_order").selectedIndex;
            let selected_order = document.getElementById("selected_order").options[selected_order_index].text;

            //выбор даты производства
            let production_date = document.getElementById("calendar").value;

            //AJAX запрос
            let xhttp = new XMLHttpRequest();
            xhttp.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("write_off_place").innerHTML = this.responseText;
                }
            };
            let filters_for_write_off_json = JSON.stringify(array_of_filters);
            xhttp.open("POST", "write_off_filters.php", true);
            xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            xhttp.send("filters_for_write_off_json="+filters_for_write_off_json
                        +"&order_number="+selected_order+"&production_date="+production_date);

            //очистка полей "номер фильтра" "количество фильтров" "список фильтров"
            document.getElementById('text').value = '';
            document.getElementById('demo').innerHTML = '<input type="text" size="15px"/>';
            document.getElementById('count').value = '';

            //очистка массива фильтров
            array_of_filters = [];

            //очистка списка с выпущенной продукцией
            calendar_box.value = "dd-mm-yy";
            let select = document.getElementById("maked_filters");
            let length = select.options.length;
            //alert(length);
            for (let i = 0; i < length; i++) {
                select.options.item(0).remove(); // не понятный костыль
                //select.options[i] = null; // в какой-то момент перестало работать
               // alert(i);
            }

        }
    </script>

<div class="simple">

<table border="0" width="400px">
    <tr>
        <td height="25" align="center" bgcolor="#1e90ff">Продукт<br>
            <input type="text" id="text" size="10" maxlength="15" value="" onkeyup="loadDoc_1()" onFocus="this.select()" />
        </td>
        <td rowspan="2" align="center" bgcolor="#1e90ff">
            <p>Дата производства<p>
                <input type="text" id="calendar" value="dd-mm-yy" onfocus="this.select();lcs(this)" onclick="event.cancelBubble=true;this.select();lcs(this)">
               <p id="final_list"></p>
                Выпущенная продукция <p>
                <select id="maked_filters" name="filters" size="10"><!--Список выпущенной продукции -->
                </select>
                <br>
                по заявке № <br>
               <?php load_orders() ?>
        </td></tr>
    <tr>
        <td align="center" bgcolor="#1e90ff">
            <p id="demo">
                <select id="select_filter" size="1"></select>
            </p>
            Кол-во  <input type="text" id="count" size="10" maxlength="15" value="" onFocus="this.select()"/>
        <td></td>
    </tr>
    <tr>
        <td align="center" bgcolor="#1e90ff">
            <button id="add_button" onclick="add_to_list()" style="width: 150px">Добавить в перечень</button>
        </td>
        <td align="center" bgcolor="#1e90ff">

            <p id="summ"></p>
                 <input type="button" onclick="send_filters_to_write_off()" value="Провести выпуск продукции"/>
            </td>
    </tr>
</table>
    <br>
    <!--<p id="array_test">array_test</p>-->
    <!-- <p id="write_off_place"></p>-->
 </div>


<button id="back_button" onclick= window.history.back() style="width: 150px">Назад</button>
 </body>
 </html>