<head>
    <style>
        .highlight_green {
            background-color: lightgreen; /* Зеленый цвет фона */
            padding: 2px 5px; /* Добавление небольшого отступа вокруг текста */
            color: #333333;
        }
        .highlight_red {
            background-color: gold; /* Зеленый цвет фона */
            padding: 2px 5px; /* Добавление небольшого отступа вокруг текста */
            color: black;
        }
        /* Стили для блока с важным сообщением */
        .important-message {
            background-color: #ffc;
            border: 1px solid #f00;
            padding: 10px;
            margin: 20px 0;
            font-weight: bold;
        }
        .table-container {
            display: flex; /* Используем flexbox для расположения элементов в ряд */
        }
        .table-container table {
            width: 50%; /* Каждая таблица будет занимать половину ширины родительского контейнера */
            margin-right: 20px; /* Добавляем небольшой отступ между таблицами (можно изменить по желанию) */
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>



<?php
/** Запуск сессии */
session_start();

/** подключение фалйа настроек */
require_once('settings.php') ;
require_once('tools/tools.php') ;

global $mysql_host, $mysql_user, $mysql_user_pass, $mysql_database;

echo "<link rel=\"stylesheet\" href=\"sheets.css\">";
/** ---------------------------------------------------------------------------------------------------------------- */
/**                                                  Блок авторизации                                                */
/** ---------------------------------------------------------------------------------------------------------------- */

/**  Проверка ввода имени и пароля */
if ((isset($_GET['user_name']))&&(!$_SESSION)) {
    if (!$_GET['user_name']) {
        echo '<div class="alert">'
            . 'вы не ввели имя'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }
}

if ((isset($_GET['user_pass']))&&(!$_SESSION)) {
    if (!$_GET['user_pass']) {
        echo '<div class="alert">'
            . 'вы не ввели пароль'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }
}

if ((isset($_SESSION['user'])&&(isset($_SESSION['workshop'])))){

    $user = $_SESSION['user'];
    $workshop = $_SESSION['workshop'];
    $advertisement = '~~~~~';

}else{

    $user = $_GET['user_name'];
    $password = $_GET['user_pass'];
    $workshop = $_GET['workshop'];
    $advertisement = 'SOME INFORMATION';

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
    $sql = "SELECT * FROM users WHERE user = '$user';";
    if (!$result = $mysqli->query($sql)) {
        echo "Ошибка: Наш запрос не удался и вот почему: \n"
            . "Запрос: " . $sql . "\n"
            . "Номер ошибки: " . $mysqli->errno . "\n"
            . "Ошибка: " . $mysqli->error . "\n";
        exit;
    }
    /** Разбираем результат запроса */
    if ($result->num_rows === 0) {
        // Упс! в запросе нет ни одной строки!
        echo '<div class="alert">'
            . 'Нет такого пользователя'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }

    /** Разбор массива значений  */
    $user_data = $result->fetch_assoc();

    /** Проверка пароля */
    if ($password != $user_data['pass']) {
        echo '<div class="alert">'
            . 'Ошибка доступа'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }

    /** Проверка соответствия уровня доступа выбранному значению участка */
    $access = false;//маркер доступа
    switch ($_GET['workshop']) {
        case 'ZU':
            if ($user_data['ZU'] > 0) $access = true;
            break;
        case 'U1':
            if ($user_data['U1'] > 0) $access = true;
            break;
        case 'U2':
            if ($user_data['U2'] > 0) $access = true;
            break;
        case 'U3':
            if ($user_data['U3'] > 0) $access = true;
            break;
        case 'U4':
            if ($user_data['U4'] > 0) $access = true;
            break;
        case 'U5':
            if ($user_data['U5'] > 0) $access = true;
            break;
        case 'U6':
            if ($user_data['U6'] > 0) $access = true;
            break;
    }

    /** Если выбран участок к которому нет доступа */
    if (!$access) {
        echo '<div class="alert">'
            . 'Доступ к данному подразделению закрыт'
            . '</div><p><div class="center">'
            . '<a href="index.php">назад</a></div>';
        exit;
    }
    /** Регистрация имени пользователя в сессии */
    $_SESSION['user'] = $user;
    $_SESSION['workshop'] = $workshop;
}
?>
<title>U5</title>

<table  width=100% height=100% style='background-color: #6495ed' >
    <tr height='10%' align='center' style='background-color: #dedede'><td width='20%'>Подразделение: <?php echo $workshop; ?>
        </td><td width='80%'><!--#application_name=--><br>  <br></td>
        <td >Пользователь: <?php echo $user; ?><br><a href='logout.php'>выход из системы</a></td></tr>
    <tr height='10%' align='center' ><td colspan='3'>#attention block<br>

            /** Раздел объявлений */

            <?php echo $advertisement."</td></tr>"; ?>

    <tr align='center'><td>
            <table  height='100%' width='100%' bgcolor='white' style='border-collapse: collapse'>
                <tr height='80%'><td>Операции: <p>

                            <a href="test.php" target="_blank" rel="noopener noreferrer">
                                <button style="height: 20px; width: 220px">Выпуск продукции</button>
                            </a>

                            <?php
                            echo"<form action='product_output_view.php' method='post'><input type='submit' value='Обзор выпуска продукции'  style=\"height: 20px; width: 220px\"></form>"
                                ."Дополнения:<p>"
                                ."<form action='BOX_CREATOR.htm' method='post'><input type='submit' value='Расчет коробок'  style=\"height: 20px; width: 220px\"></form>"
                                ."<form action='BOX_CREATOR_2.htm' method='post'><input type='submit' value='Максимальное количество'  style=\"height: 20px; width: 220px\"></form>"
                        ?>
                        Мониториг:  <p>

                        <form action='NP_full_build_plan.php' method='post' target="_blank"><input type='submit' value='План сборки'  style="height: 20px; width: 220px"></form>
                        <form action='NP_full_build_plan.php' method='post' target="_blank"><input type='submit' value='План сборки'  style="height: 20px; width: 220px"></form>
                        <form action='NP_monitor.php' method='post' target="_blank"><input type='submit' value='Мониторинг'  style="height: 20px; width: 220px"></form>

                        <form action="worker_modules/tasks_corrugation.php" method="post" target="_blank">
                              <input type="submit" value="Модуль оператора ГМ" style="height: 20px; width: 220px">
                        </form>
                        <form action="worker_modules/tasks_cut.php" method="post" target="_blank">
                            <input type="submit" value="Модуль оператора бумагорезки" style="height: 20px; width: 220px">

                        </form>
                        <form action="NP/corrugation_print.php" method="post" target="_blank">
                            <input type="submit" value="План гофропакетчика " style="height: 20px; width: 220px">
                        </form>
                        <form action="buffer_stock.php" method="post" target="_blank">
                            <input type="submit" value="Буфер гофропакетов" style="height: 20px; width: 220px">
                        </form>
                        <p>
                        Табель:  <p>
                        <form action="http://localhost/timekeeping/U5/index.php" method="post" target="_blank">
                            <input type="submit" value="Табель У5" style="height: 20px; width: 220px" disabled>
                        </form>

                        <?php
                        echo"</td></tr>"
                            ."<tr bgcolor='#6495ed'><td>"


                            /** ---------------------------------------------------------------------------------------------------------------- */
                            /**                                                 Раздел ПРИЛОЖЕНИЯ                                                */
                            /** ---------------------------------------------------------------------------------------------------------------- */
                            ."Управление данными <p>"
                            /**
                            ."<form action='add_filter_into_db.php' method='post'>"
                            ."<input type='hidden' name='workshop' value='$workshop'>"
                            ."<input type='submit'  value='добавить фильтр в БД-----ххх'  style=\"height: 20px; width: 220px\">"
                            ."</form>"
                             */
                            /** Добавление полной информации по фильтру  */
                            . "<form action='add_salon_filter_into_db.php' method='post' target='_blank'>"
                            ."<input type='hidden' name='workshop' value='$workshop'>"
                            ."<input type='submit'  value='Добавить фильтр в БД(full)'  style=\"height: 20px; width: 220px\">"
                            ."</form>"
                            . "<form action='view_salon_filter_params.php' method='post' target='_blank'>"
                            ."<input type='hidden' name='workshop' value='$workshop'>"
                            ."<input type='submit'  value='Просмотреть параметры фильтра'  style=\"height: 20px; width: 220px\">"
                            ."</form>"

                            ."<form action='add_filter_properties_into_db.php' method='post' target='_blank'>"
                            ."<input type='hidden' name='workshop' value='$workshop'>"
                            ."<input type='submit'  value='Изменить параметры фильтра'  style=\"height: 20px; width: 220px\">"
                            ."</form>"

                        ?>

                        <form action="create_ad.php" method="post">
                            <input type="text" name="title" placeholder="Название объявления" required>
                            <textarea name="content" placeholder="Текст объявления" required></textarea>
                            <input type="date" name="expires_at" required>
                            <button type="submit">Создать объявление</button>
                        </form>

                        <?php

                        echo "</td></tr>"
                            ."</table>";

                        /** ---------------------------------------------------------------------------------------------------------------- */
                        /**                                                 Раздел ЗАДАЧИ                                                    */
                        /** ---------------------------------------------------------------------------------------------------------------- */
                        echo "</td><td>"
                            ."<table height='100%' width='100%' bgcolor='white' style='border-collapse: collapse'>"
                            //."<tr><td>1</td><td>2</td></tr>"
                            ."<tr><td style='color: cornflowerblue' valign='top'><p>";

                        ?>

                        <?php

                        show_ads();

                        show_weekly_production();
                        show_monthly_production();
                        ?>
                        <p>


                            <div style="max-width:600px;padding:12px;border:1px solid #ddd;border-radius:10px;margin:12px 0;">
                                <h4 style="margin:0 0 8px;">Поиск заявок по фильтру</h4>

                                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                    <label for="filterSelect">Фильтр:</label>
                                    <?php
                                    load_filters_into_select(); // <select name="analog_filter">
                                    ?>
                                </div>

                                <div id="filterSearchResult" style="margin-top:12px;"></div>
                            </div>



                            <script>
                                (function(){
                                    const resultBox = document.getElementById('filterSearchResult');

                                    function getSelectEl(){
                                        return document.querySelector('select[name="analog_filter"]');
                                    }

                                    async function runSearch(){
                                        const sel = getSelectEl();
                                        if(!sel){ resultBox.innerHTML = '<div style="color:red">Не найден выпадающий список.</div>'; return; }
                                        const val = sel.value.trim();
                                        if(!val){ resultBox.innerHTML = '<div style="color:#666">Выберите фильтр…</div>'; return; }

                                        resultBox.innerHTML = 'Загрузка…';

                                        try{
                                            const formData = new FormData();
                                            formData.append('filter', val);

                                            const resp = await fetch('search_filter_in_the_orders.php', {
                                                method: 'POST',
                                                body: formData
                                            });

                                            if(!resp.ok){
                                                resultBox.innerHTML = `<div style="color:red">Ошибка запроса: ${resp.status} ${resp.statusText}</div>`;
                                                return;
                                            }

                                            const html = await resp.text();
                                            resultBox.innerHTML = html;
                                        }catch(e){
                                            resultBox.innerHTML = `<div style="color:red">Ошибка: ${e}</div>`;
                                        }
                                    }

                                    const sel = getSelectEl();
                                    if(sel){
                                        sel.id = 'filterSelect'; // для label for
                                        sel.addEventListener('change', runSearch);
                                    }
                                })();
                            </script>

                        </td></tr><tr><td></td></tr>
                        </table>
                        </td><td>
                        <?php
                        /** ---------------------------------------------------------------------------------------------------------------- */
                        /**                                                 Раздел ЗАЯВКИ                                                    */
                        /** ---------------------------------------------------------------------------------------------------------------- */

                        /** Форма загрузки файла с заявкой в БД */
                        echo '<table height="100%" ><tr><td bgcolor="white" style="border-collapse: collapse">Сохраненные заявки<br>';


                        /** Подключаемся к БД */
                        $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
                        if ($mysqli->connect_errno) {
                            /** Если не получилось подключиться */
                            echo 'Возникла проблема на сайте'
                                . "Номер ошибки: " . $mysqli->connect_errno . "\n"
                                . "Ошибка: " . $mysqli->connect_error . "\n";
                            exit;
                        }

                        /** Выполняем запрос SQL для загрузки заявок*/
                        $sql = "SELECT DISTINCT order_number, workshop, hide FROM orders;";
                        //$sql = 'SELECT order_number FROM orders;';
                        if (!$result = $mysqli->query($sql)){
                            echo "Ошибка: Наш запрос не удался и вот почему: \n Запрос: " . $sql . "\n"
                                ."Номер ошибки: " . $mysqli->errno . "\n Ошибка: " . $mysqli->error . "\n";
                            exit;
                        }
                        /** Разбираем результат запроса */
                        if ($result->num_rows === 0) { echo "В базе нет ни одной заявки";}

                        /** Разбор массива значений  */
                        echo '<form action="show_order.php" method="post" target="_blank">';
                        while ($orders_data = $result->fetch_assoc()){
                            if ( $orders_data['hide'] != 1){
                                echo "<input type='submit' name='order_number' value=".$orders_data['order_number']." style=\"height: 20px; width: 115px\">";

                            }
                        }

                        echo '</form>';

                        /** Блок распланированных заявок  */
                        echo "Управление заявками";

                        ?>

                        <section class="card" style="margin-top:16px">
                            <button type="button" class="btn" id="btn-create-resid" style="width:220px"> Создать заявку для остатков </button>
                            <span class="muted" id="resid-hint"></span>
                        </section>

                        <script>
                            document.getElementById('btn-create-resid').addEventListener('click', async () => {
                                const btn = document.getElementById('btn-create-resid');
                                const hint = document.getElementById('resid-hint');
                                btn.disabled = true; hint.textContent = 'Создаю...';

                                try {
                                    const res = await fetch('residual_create.php', {
                                        method:'POST',
                                        headers:{'Content-Type':'application/x-www-form-urlencoded'},
                                        body: new URLSearchParams({workshop:'U5'}).toString()
                                    });

                                    const text = await res.text(); // читаем как текст
                                    let data;
                                    try { data = JSON.parse(text); } catch { throw new Error('Сервер вернул не-JSON: ' + text.slice(0,200)); }

                                    if (!data.ok) throw new Error(data.error || 'Ошибка');

                                    hint.textContent = (data.created ? 'Создана' : 'Уже существует') + ' заявка: ' + data.order_number;
                                } catch(e) {
                                    hint.textContent = 'Ошибка: ' + e.message;
                                } finally {
                                    btn.disabled = false;
                                }
                            });

                        </script>


                        <?php







                        echo "<form action='new_order.php' method='post' target='_blank'>"
                            ."<input type='submit' value='Создать заявку вручную' style='height: 20px; width: 220px'>"
                            ."</form>";

                        echo "<form action='planning_manager.php' method='post' target='_blank'>"
                            ."<input type='submit' value='Менеджер планирования (старый)' style='height: 20px; width: 220px'>"
                            ."</form>";
                        echo "<form action='NP_cut_index.php' method='post' target='_blank'>"
                            ."<input type='submit' value='Менеджер планирования (новый)' style='height: 20px; width: 220px'>"
                            ."</form>";
                        echo "<form action='combine_orders.php' method='post'>"
                            ."<input type='submit' value='Объединение заявок' style='height: 20px; width: 220px'>"
                            ."</form>";

                        /** Блок загрузки заявок */
                        echo "</td></tr><tr><td height='20%'>"
                            .'<form enctype="multipart/form-data" action="load_file.php" method="POST">'
                            .'<input type="hidden" name="MAX_FILE_SIZE" value="3000000" />'
                            .'Добавить заявку: <input name="userfile" type="file" /><br>'
                            .'<input type="submit" value="Загрузить файл"  style="height: 20px; width: 220px" />'
                            .'</form>'
                            .'</td></tr></table>';

                        /** конец формы загрузки */
                        echo "</td></tr></table>";
                        echo "</td></tr></table>";
                        $result->close();
                        $mysqli->close();


                        ?>

</body>