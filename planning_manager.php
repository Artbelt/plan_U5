<?php /** Менеджер планирования заявки */
    require_once('tools/tools.php');
    require_once('settings.php');

 ?>

<div id="header" style="background-color: #5450ff; height: 50px; width: 100%; font-family: Calibri; font-size: 20px">
    <p style="color: white">Менеджер планирования заявки:</p>
</div>

<br>Заявка №
<?php load_orders(); ?><button onclick="load_order(document.getElementById('selected_order'))">Сформировать раскрои</button>
<br>

<script>
        function CallPrint() {
            var newWindow = window.open();
            newWindow.document.write('ЗАДАНИЕ НА ПОРЕЗКУ РУЛОНОВ');
            newWindow.document.write(document.getElementById("print-content").innerHTML);
            newWindow.document.write('<p>Задание составил:_______________');
            newWindow.document.write('<p>Дата создания:');
            newWindow.document.write();
            newWindow.print();
        }
</script>

<script> /* Загрузка заявки */
    function load_order(object_id){

        //AJAX запрос
        let xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                document.getElementById("raskroy_sheet").innerHTML = this.responseText;
            }
        };

        //выбор номера заявки из заголовка списка для передачи в ajax-запрос
        let selected_order_index = document.getElementById("selected_order").selectedIndex;
        let selected_order = document.getElementById("selected_order").options[selected_order_index].text;

        xhttp.open("POST", "show_order_AJAX.php", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("order_number="+selected_order);
    }
</script>

<!-- Блок планирования раскроя -->
<p>
<div id="raskroy_sheet">

</div>

<!-- Конец блока планирования раскроя -->

