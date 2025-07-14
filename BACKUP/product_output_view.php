<script>
    function generateReport() {
        // Отримуємо таблицю
        var table = document.getElementById('produced_filters_table');
        var rows = table.rows;

        // Об'єкти для зберігання підрахунків
        var filterCounts = {};
        var packagingCounts = {};

        // Проходимо по кожному рядку таблиці, починаючи з другого (індекс 1)
        for (var i = 1; i < rows.length; i++) {
            var cells = rows[i].cells;
            var filter = cells[1].textContent;
            var quantity = parseInt(cells[2].textContent);
            var packaging = cells[4].textContent;

            // Підраховуємо кількість фільтрів
            if (filterCounts[filter]) {
                filterCounts[filter] += quantity;
            } else {
                filterCounts[filter] = quantity;
            }

            // Підраховуємо кількість упаковок
            if (packagingCounts[packaging]) {
                packagingCounts[packaging] += quantity;
            } else {
                packagingCounts[packaging] = quantity;
            }
        }

        // Створюємо нове вікно для звіту
        var reportWindow = window.open("", "Report", "width=800,height=600");

        // Створюємо таблицю для фільтрів
        var filterTable = "<h2>Фільтри</h2><table border='1'><tr><th>Фільтр</th><th>Кількість</th></tr>";
        for (var filter in filterCounts) {
            filterTable += "<tr><td>" + filter + "</td><td>" + filterCounts[filter] + "</td></tr>";
        }
        filterTable += "</table>";

        // Сортуємо упаковки за кількістю
        var sortedPackagings = Object.keys(packagingCounts).sort(function(a, b) {
            return packagingCounts[b] - packagingCounts[a];
        });

        // Створюємо таблицю для упаковок
        var packagingTable = "<h2>Упаковки</h2><table border='1'><tr><th>Упаковка</th><th>Кількість</th></tr>";
        sortedPackagings.forEach(function(packaging) {
            packagingTable += "<tr><td>" + packaging + "</td><td>" + packagingCounts[packaging] + "</td></tr>";
        });
        packagingTable += "</table>";

        // Вставляємо таблиці у нове вікно
        reportWindow.document.write("<html><head><title>Звіт</title></head><body>");
        //reportWindow.document.write(filterTable);
        reportWindow.document.write(packagingTable);
        reportWindow.document.write("</body></html>");
    }
</script>
<script>
    function show_raiting() {
        // Отримуємо таблицю
        var table = document.getElementById('produced_filters_table');

// Створюємо об'єкт для збереження суми кількостей за кожним фільтром
        var sums = {};

// Проходимо по кожному рядку таблиці (починаючи з другого рядка, оскільки перший - заголовок)
        for (var i = 1; i < table.rows.length; i++) {
            var row = table.rows[i];
            var filter = row.cells[1].innerText; // Отримуємо назву фільтру з другого стовпця
            var quantity = parseInt(row.cells[2].innerText); // Отримуємо кількість з третього стовпця

            // Додаємо кількість до суми для відповідного фільтру
            if (sums[filter]) {
                sums[filter] += quantity;
            } else {
                sums[filter] = quantity;
            }
        }

// Створюємо масив об'єктів для подальшого сортування
        var sumsArray = [];
        for (var filter in sums) {
            sumsArray.push({ filter: filter, quantity: sums[filter] });
        }

// Сортуємо масив за кількістю у спадаючому порядку
        sumsArray.sort(function(a, b) {
            return b.quantity - a.quantity;
        });

// Створюємо нову таблицю
        var newTable = document.createElement('table');
        var headerRow = newTable.insertRow();
        var filterHeader = headerRow.insertCell();
        var quantityHeader = headerRow.insertCell();
        filterHeader.innerText = 'Фильтр';
        quantityHeader.innerText = 'Количество';

// Додаємо відсортовані дані до нової таблиці
        sumsArray.forEach(function(item) {
            var newRow = newTable.insertRow();
            var filterCell = newRow.insertCell();
            var quantityCell = newRow.insertCell();
            filterCell.innerText = item.filter;
            quantityCell.innerText = item.quantity;
        });

// Відкриваємо нове вікно для відображення нової таблиці
        var newWindow = window.open('', 'Нове вікно', 'width=600,height=400');
        newWindow.document.body.appendChild(newTable);
        newWindow.document.body.clearAll();
    }
</script>

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
<script>
    function saveHours() {
        const inputs = document.querySelectorAll("input[name^='hours']");
        const formData = new FormData();

        inputs.forEach(input => {
            if (input.value.trim() !== '') {
                formData.append(input.name, input.value);
            }
        });

        fetch('save_hours.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(result => {
                alert("✅ Часы успешно сохранены!");
            })
            .catch(error => {
                console.error("Ошибка:", error);
                alert("❌ Не удалось сохранить часы.");
            });
    }
</script>
<script>
    const productionDate = "<?= $production_date ?>";

    function saveHours() {
        const inputs = document.querySelectorAll("input[name^='hours']");
        const formData = new FormData();

        inputs.forEach(input => {
            if (input.value.trim() !== '') {
                formData.append(input.name, input.value);
            }
        });

        formData.append('date', productionDate); // ⬅️ добавляем дату в форму

        fetch('save_hours.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(result => {
                alert("✅ Часы успешно сохранены!");
                console.log(result);
            })
            .catch(error => {
                console.error("❌ Ошибка сохранения:", error);
                alert("❌ Не удалось сохранить часы.");
            });
    }
</script>


</body>