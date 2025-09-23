<?php /** Запуск сессии */ session_start(); ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>U5</title>

    <style>
        /* ===== Pro UI (neutral + single accent) ===== */
        :root{
            --bg:#f6f7f9;
            --panel:#ffffff;
            --ink:#1f2937;
            --muted:#6b7280;
            --border:#e5e7eb;
            --accent:#2457e6;
            --accent-ink:#ffffff;
            --radius:12px;
            --shadow:0 2px 12px rgba(2,8,20,.06);
            --shadow-soft:0 1px 8px rgba(2,8,20,.05);
        }
        html,body{height:100%}
        body{
            margin:0; background:var(--bg); color:var(--ink);
            font:14px/1.45 "Segoe UI", Roboto, Arial, sans-serif;
            -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;
        }
        a{color:var(--accent); text-decoration:none}
        a:hover{text-decoration:underline}

        /* контейнер и сетка */
        .container{ max-width:1280px; margin:0 auto; padding:16px; }
        .layout{ width:100%; border-spacing:16px; border:0; background:transparent; }
        .header-row .header-cell{ padding:0; border:0; background:transparent; }
        .headerbar{ display:flex; align-items:center; gap:12px; padding:10px 4px; color:#374151; }
        .headerbar .spacer{ flex:1; }

        /* панели-колонки */
        .content-row > td{ vertical-align:top; }
        .panel{
            background:var(--panel);
            border:1px solid var(--border);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:14px;
        }
        .panel--main{ box-shadow:var(--shadow-soft); }
        .section-title{
            font-size:15px; font-weight:600; color:#111827;
            margin:0 0 10px; padding-bottom:6px; border-bottom:1px solid var(--border);
        }

        /* таблицы внутри панелей как карточки */
        .panel table{
            width:100%;
            border-collapse:collapse;
            background:#fff;
            border:1px solid var(--border);
            border-radius:10px;
            box-shadow:var(--shadow-soft);
            overflow:hidden;
        }
        .panel td,.panel th{padding:10px;border-bottom:1px solid var(--border);vertical-align:top}
        .panel tr:last-child td{border-bottom:0}

        /* вертикальные стеки вместо <p> */
        .stack{ display:flex; flex-direction:column; gap:8px; }
        .stack-lg{ gap:12px; }

        /* кнопки (единый стиль) */
        button, input[type="submit"]{
            appearance:none;
            border:1px solid transparent;
            cursor:pointer;
            background:var(--accent);
            color:var(--accent-ink);
            padding:7px 14px;
            border-radius:9px;
            font-weight:600;
            transition:background .2s, box-shadow .2s, transform .04s, border-color .2s;

            /* новая тень */
            box-shadow: 0 3px 6px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
        }
        button:hover, input[type="submit"]:hover{ background:#1e47c5; box-shadow:0 2px 8px rgba(2,8,20,.10); transform:translateY(-1px); }
        button:active, input[type="submit"]:active{ transform:translateY(0); }
        button:disabled, input[type="submit"]:disabled{
            background:#e5e7eb; color:#9ca3af; border-color:#e5e7eb; box-shadow:none; cursor:not-allowed;
        }
        /* если где-то остались инлайновые background — приглушим */
        input[type="submit"][style*="background"], button[style*="background"]{
            background:var(--accent)!important; color:#fff!important;
        }

        /* поля ввода/селекты */
        input[type="text"], input[type="date"], input[type="number"], input[type="password"],
        textarea, select{
            min-width:180px; padding:7px 10px;
            border:1px solid var(--border); border-radius:9px;
            background:#fff; color:var(--ink); outline:none;
            transition:border-color .2s, box-shadow .2s;
        }
        input:focus, textarea:focus, select:focus{
            border-color:#c7d2fe; box-shadow:0 0 0 3px #e0e7ff;
        }
        textarea{min-height:92px; resize:vertical}

        /* инфоблоки */
        .alert{
            background:#fffbe6; border:1px solid #f4e4a4; color:#634100;
            padding:10px; border-radius:9px; margin:12px 0; font-weight:600;
        }
        .important-message{
            background:#fff1f2; border:1px solid #ffd1d8; color:#6b1220;
            padding:12px; border-radius:9px; margin:12px 0; font-weight:700;
        }
        .highlight_green{
            background:#e7f5ee; color:#0f5132; border:1px solid #cfe9db;
            padding:2px 6px; border-radius:6px; font-weight:600;
        }
        .highlight_red{
            background:#fff7e6; color:#7a3e00; border:1px solid #ffe1ad;
            padding:2px 6px; border-radius:6px; font-weight:600;
        }

        /* чипы заявок справа */
        .saved-orders input[type="submit"]{
            display:inline-block; margin:4px 6px 0 0;
            border-radius:999px!important; padding:6px 10px!important;
            background:var(--accent)!important; color:#fff!important;
            border:none!important; box-shadow:0 1px 4px rgba(2,8,20,.06);
        }

        /* карточка поиска */
        .search-card{
            border:1px solid var(--border);
            border-radius:10px; background:#fff;
            box-shadow:var(--shadow-soft); padding:12px; margin-top:8px;
        }
        .muted{color:var(--muted)}

        /* адаптив */
        @media (max-width:1100px){
            .layout{ border-spacing:10px; }
            .content-row > td{ display:block; width:auto!important; }
        }
        .topbar{
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:10px 18px;
            background:var(--panel);
            border-bottom:1px solid var(--border);
            box-shadow:var(--shadow-soft);
            border-radius:var(--radius);
            margin-bottom:16px;
        }
        .topbar-left, .topbar-right, .topbar-center{
            display:flex;
            align-items:center;
            gap:10px;
        }
        .topbar-center{
            font-weight:600;
            font-size:15px;
            color:var(--ink);
        }
        .logo{
            font-size:18px;
            font-weight:700;
            color:var(--accent);
        }
        .system-name{
            font-size:14px;
            font-weight:500;
            color:var(--muted);
        }
        .logout-btn{
            background:var(--accent);
            color:var(--accent-ink);
            padding:6px 12px;
            border-radius:8px;
            font-weight:600;
            box-shadow:0 2px 6px rgba(0,0,0,0.08);
        }
        .logout-btn:hover{
            background:#1e47c5;
            text-decoration:none;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php
/** подключение файлов настроек/инструментов */
require_once('settings.php');
require_once('tools/tools.php');

global $mysql_host, $mysql_user, $mysql_user_pass, $mysql_database;

/** --- Блок авторизации --- */
if ((isset($_GET['user_name']))&&(!$_SESSION)) {
    if (!$_GET['user_name']) { echo '<div class="alert">вы не ввели имя</div><div><a href="index.php">назад</a></div>'; exit; }
}
if ((isset($_GET['user_pass']))&&(!$_SESSION)) {
    if (!$_GET['user_pass']) { echo '<div class="alert">вы не ввели пароль</div><div><a href="index.php">назад</a></div>'; exit; }
}

if ((isset($_SESSION['user'])&&(isset($_SESSION['workshop'])))) {
    $user = $_SESSION['user'];
    $workshop = $_SESSION['workshop'];
    $advertisement = '~~~~~';
} else {
    $user = $_GET['user_name'];
    $password = $_GET['user_pass'];
    $workshop = $_GET['workshop'];
    $advertisement = 'SOME INFORMATION';

    $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
    if ($mysqli->connect_errno) {
        echo 'Возникла проблема на сайте' . "Номер ошибки: " . $mysqli->connect_errno . "\n" . "Ошибка: " . $mysqli->connect_error . "\n"; exit;
    }
    $sql = "SELECT * FROM users WHERE user = '$user';";
    if (!$result = $mysqli->query($sql)) {
        echo "Ошибка: Наш запрос не удался и вот почему: \nЗапрос: $sql\nНомер ошибки: ".$mysqli->errno."\nОшибка: ".$mysqli->error."\n"; exit;
    }
    if ($result->num_rows === 0) { echo '<div class="alert">Нет такого пользователя</div><div><a href="index.php">назад</a></div>'; exit; }
    $user_data = $result->fetch_assoc();
    if ($password != $user_data['pass']) { echo '<div class="alert">Ошибка доступа</div><div><a href="index.php">назад</a></div>'; exit; }

    $access = false;
    switch ($workshop) {
        case 'ZU': if ($user_data['ZU'] > 0) $access = true; break;
        case 'U1': if ($user_data['U1'] > 0) $access = true; break;
        case 'U2': if ($user_data['U2'] > 0) $access = true; break;
        case 'U3': if ($user_data['U3'] > 0) $access = true; break;
        case 'U4': if ($user_data['U4'] > 0) $access = true; break;
        case 'U5': if ($user_data['U5'] > 0) $access = true; break;
        case 'U6': if ($user_data['U6'] > 0) $access = true; break;
    }
    if (!$access) { echo '<div class="alert">Доступ к данному подразделению закрыт</div><div><a href="index.php">назад</a></div>'; exit; }

    $_SESSION['user'] = $user;
    $_SESSION['workshop'] = $workshop;
}
?>

<div class="container">
    <table class="layout">
        <!-- Шапка -->
        <tr class="header-row">
            <td class="header-cell" colspan="3">
                <!-- Шапка -->
        <tr class="header-row">
            <td class="header-cell" colspan="3">
                <div class="topbar">
                    <div class="topbar-left">
                        <span class="logo">U5</span>
                        <span class="system-name">Система управления</span>
                    </div>
                    <div class="topbar-center">
                        Подразделение: <strong><?php echo htmlspecialchars($workshop); ?></strong>
                    </div>
                    <div class="topbar-right">
                        Пользователь: <strong><?php echo htmlspecialchars($user); ?></strong>
                        <a href="logout.php" class="logout-btn">⎋ Выход</a>
                    </div>
                </div>
            </td>
        </tr>

            </td>
        </tr>

        <!-- Контент: 3 колонки -->
        <tr class="content-row">
            <!-- Левая панель -->
            <td class="panel panel--left" style="width:22%;">
                <div class="section-title">Операции</div>
                <div class="stack">
                    <a href="test.php" target="_blank" rel="noopener" class="stack"><button>Выпуск продукции</button></a>
                    <form action="product_output_view.php" method="post" class="stack"><input type="submit" value="Обзор выпуска продукции"></form>
                </div>

                <div class="section-title" style="margin-top:14px">Дополнения</div>
                <div class="stack">
                    <form action="BOX_CREATOR.htm" method="post" class="stack"><input type="submit" value="Расчет коробок"></form>
                    <form action="BOX_CREATOR_2.htm" method="post" class="stack"><input type="submit" value="Максимальное количество"></form>
                </div>

                <div class="section-title" style="margin-top:14px">Мониторинг</div>
                <div class="stack">
                    <form action='NP_full_build_plan.php' method='post' target="_blank" class="stack"><input type='submit' value='Полный план сборки'></form>

                    <form action="NP_build_plan_week.php" method="get" target="_blank" class="stack">
                        <?php load_orders(0); ?>
                        <input type="submit" value="План сборки по заявке">
                    </form>

                    <form action='NP_monitor.php' method='post' target="_blank" class="stack"><input type='submit' value='Мониторинг'></form>
                    <form action="worker_modules/tasks_corrugation.php" method="post" target="_blank" class="stack"><input type="submit" value="Модуль оператора ГМ"></form>
                    <form action="worker_modules/tasks_cut.php" method="post" target="_blank" class="stack"><input type="submit" value="Модуль оператора бумагорезки"></form>
                    <form action="NP/corrugation_print.php" method="post" target="_blank" class="stack"><input type="submit" value="План гофропакетчика"></form>
                    <form action="buffer_stock.php" method="post" target="_blank" class="stack"><input type="submit" value="Буфер гофропакетов"></form>
                </div>

                <div class="section-title" style="margin-top:14px">Табель</div>
                <div class="stack">
                    <form action="http://localhost/timekeeping/U5/index.php" method="post" target="_blank" class="stack">
                        <input type="submit" value="Табель У5" disabled>
                    </form>
                </div>

                <div class="section-title" style="margin-top:14px">Управление данными</div>
                <div class="stack">
                    <form action='add_salon_filter_into_db.php' method='post' target='_blank' class="stack">
                        <input type='hidden' name='workshop' value='<?php echo htmlspecialchars($workshop); ?>'>
                        <input type='submit' value='Добавить фильтр в БД(full)'>
                    </form>
                    <form action='view_salon_filter_params.php' method='post' target='_blank' class="stack">
                        <input type='hidden' name='workshop' value='<?php echo htmlspecialchars($workshop); ?>'>
                        <input type='submit' value='Просмотреть параметры фильтра'>
                    </form>
                    <form action='add_filter_properties_into_db.php' method='post' target='_blank' class="stack">
                        <input type='hidden' name='workshop' value='<?php echo htmlspecialchars($workshop); ?>'>
                        <input type='submit' value='Изменить параметры фильтра'>
                    </form>
                </div>

                <div class="section-title" style="margin-top:14px">Объявление</div>
                <form action="create_ad.php" method="post" class="stack">
                    <input type="text" name="title" placeholder="Название объявления" required>
                    <textarea name="content" placeholder="Текст объявления" required></textarea>
                    <input type="date" name="expires_at" required>
                    <button type="submit">Создать объявление</button>
                </form>
            </td>

            <!-- Центральная панель -->
            <td class="panel panel--main">
                <div class="section-title">Объявления</div>
                <div class="stack-lg">

                    <?php show_ads();?>
                    <?php show_weekly_production();?>
                    <?php show_monthly_production();?>

                    <div class="search-card">
                        <h4 style="margin:0 0 8px;">Поиск заявок по фильтру</h4>
                        <div class="stack">
                            <label for="filterSelect">Фильтр:</label>
                            <?php load_filters_into_select(); /* <select name="analog_filter"> */ ?>
                        </div>
                        <div id="filterSearchResult" style="margin-top:10px;"></div>
                    </div>
                </div>

                <script>
                    (function(){
                        const resultBox = document.getElementById('filterSearchResult');
                        function getSelectEl(){ return document.querySelector('select[name="analog_filter"]'); }
                        async function runSearch(){
                            const sel = getSelectEl();
                            if(!sel){ resultBox.innerHTML = '<div class="muted">Не найден выпадающий список.</div>'; return; }
                            const val = sel.value.trim();
                            if(!val){ resultBox.innerHTML = '<div class="muted">Выберите фильтр…</div>'; return; }
                            resultBox.textContent = 'Загрузка…';
                            try{
                                const formData = new FormData(); formData.append('filter', val);
                                const resp = await fetch('search_filter_in_the_orders.php', { method:'POST', body:formData });
                                if(!resp.ok){ resultBox.innerHTML = `<div class="alert">Ошибка запроса: ${resp.status} ${resp.statusText}</div>`; return; }
                                resultBox.innerHTML = await resp.text();
                            }catch(e){ resultBox.innerHTML = `<div class="alert">Ошибка: ${e}</div>`; }
                        }
                        const sel = getSelectEl(); if(sel){ sel.id='filterSelect'; sel.addEventListener('change', runSearch); }
                    })();
                </script>
            </td>

            <!-- Правая панель -->
            <td class="panel panel--right" style="width:24%;">
                <?php
                /* загрузка заявок */
                $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
                if ($mysqli->connect_errno) { echo 'Возникла проблема на сайте'; exit; }
                $sql = "SELECT DISTINCT order_number, workshop, hide FROM orders;";
                if (!$result = $mysqli->query($sql)){
                    echo "Ошибка: Наш запрос не удался\n"; exit;
                }
                ?>

                <div class="section-title">Сохраненные заявки</div>
                <div class="saved-orders">
                    <?php
                    echo '<form action="show_order.php" method="post" target="_blank">';
                    if ($result->num_rows === 0) { echo "<div class='muted'>В базе нет ни одной заявки</div>"; }
                    while ($orders_data = $result->fetch_assoc()){
                        if ($orders_data['hide'] != 1){
                            $val = htmlspecialchars($orders_data['order_number']);
                            echo "<input type='submit' name='order_number' value='{$val}'>";
                        }
                    }
                    echo '</form>';
                    ?>
                </div>

                <div class="section-title" style="margin-top:14px">Управление заявками</div>
                <section class="stack">
                    <section class="stack">
                        <button type="button" id="btn-create-resid" >Создать заявку для остатков</button>
                        <span class="muted" id="resid-hint" style="margin-left:6px;"></span>
                    </section>

                    <form action='new_order.php' method='post' target='_blank' class="stack"><input type='submit' value='Создать заявку вручную'></form>
                    <form action='planning_manager.php' method='post' target='_blank' class="stack"><input type='submit' value='Менеджер планирования (старый)'></form>
                    <form action='NP_cut_index.php' method='post' target='_blank' class="stack"><input type='submit' value='Менеджер планирования (новый)'></form>
                    <form action='combine_orders.php' method='post' class="stack"><input type='submit' value='Объединение заявок'></form>

                    <div class="card">
                        <form enctype="multipart/form-data" action="load_file.php" method="POST" class="stack">
                            <input type="hidden" name="MAX_FILE_SIZE" value="3000000" />
                            <label class="muted">Добавить заявку коммерческого отдела:</label>
                            <input name="userfile" type="file" />
                            <input type="submit" value="Загрузить файл" />
                        </form>
                    </div>
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
                            const text = await res.text();
                            let data; try { data = JSON.parse(text); } catch { throw new Error('Сервер вернул не-JSON: ' + text.slice(0,200)); }
                            if (!data.ok) throw new Error(data.error || 'Ошибка');
                            hint.textContent = (data.created ? 'Создана' : 'Уже существует') + ' заявка: ' + data.order_number;
                        } catch(e) {
                            hint.textContent = 'Ошибка: ' + e.message;
                        } finally { btn.disabled = false; }
                    });
                </script>

                <?php $result->close(); $mysqli->close(); ?>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
