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
<body>
<!-- скрипт календаря -->
<script src="tools/calendar.js" type="text/javascript"></script>
<script>

    function show_manufactured_filters() {//отправка данных для списания выпуска продукции

        //проверка заполненности полей для проведения выпуска продукции

        //проверка календаря
        let calendar_box = document.getElementById('calendar');
        if (calendar_box.value == "dd-mm-yy"){
            alert("Не выбрана дата");
            return;
        }

        //выбор даты производства
        let production_date = document.getElementById("calendar").value;

        //AJAX запрос
        let xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                document.getElementById("show_filters_place").innerHTML = this.responseText;
            }
        };

        xhttp.open("POST", "show_manufactured_filters.php", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("production_date="+production_date);

        //очистка списка с выпущенной продукцией
        //calendar_box.value = "dd-mm-yy";

    }

    function show_manufactured_filters_more() {//отправка данных для списания выпуска продукции

        //проверка заполненности полей для проведения выпуска продукции

        //проверка календаря
        let calendar_box_start = document.getElementById('calendar_start');
        if (calendar_box_start.value == "dd-mm-yy"){
            alert("Не выбрана дата");
            return;
        }
        let calendar_box_end = document.getElementById('calendar_end');
        if (calendar_box_end.value == "dd-mm-yy"){
            alert("Не выбрана дата");
            return;
        }
        //выбор даты производства
        let production_date_start = document.getElementById("calendar_start").value;
        let production_date_end = document.getElementById("calendar_end").value;

        //AJAX запрос
        let xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                document.getElementById("show_filters_place").innerHTML = this.responseText;
            }
        };

        xhttp.open("POST", "show_manufactured_filters_more.php", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("production_date_start="+production_date_start+'&production_date_end='+production_date_end);

        //очистка списка с выпущенной продукцией
        //calendar_box.value = "dd-mm-yy";

    }
</script>

Выбор даты: <input type="text" id="calendar" value="dd-mm-yy" onfocus="this.select();lcs(this)" onclick="event.cancelBubble=true;this.select();lcs(this)"><p>

                <input type="button" onclick="show_manufactured_filters()" value="Просмотр выпущенной за выбранную дату"/>


<p>Выбор даты: <input type="text" id="calendar_start" value="dd-mm-yy" onfocus="this.select();lcs(this)" onclick="event.cancelBubble=true;this.select();lcs(this)">
               <input type="text" id="calendar_end" value="dd-mm-yy" onfocus="this.select();lcs(this)" onclick="event.cancelBubble=true;this.select();lcs(this)"><p>
               <input type="button" onclick="show_manufactured_filters_more()" value="Просмотр выпущенной в заданном диапазоне дат"/>
<p id="show_filters_place"></p>

<button id="back_button" onclick= window.history.back() style="width: 150px">Назад</button>

</body>