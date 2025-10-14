<?php
// Проверяем авторизацию через новую систему
require_once('../auth/includes/config.php');
require_once('../auth/includes/auth-functions.php');

// Подключаем файлы настроек/инструментов
require_once('settings.php');
require_once('tools/tools.php');

// Инициализация системы авторизации
initAuthSystem();

// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new AuthManager();
$session = $auth->checkSession();

if (!$session) {
    header('Location: ../auth/login.php');
    exit;
}

// Получаем информацию о пользователе
$db = Database::getInstance();
$users = $db->select("SELECT * FROM auth_users WHERE id = ?", [$session['user_id']]);
$user = $users[0] ?? null;

// Если пользователь не найден, используем данные из сессии
if (!$user) {
    $user = [
        'full_name' => $session['full_name'] ?? 'Пользователь',
        'phone' => $session['phone'] ?? ''
    ];
}

$userDepartments = $db->select("
    SELECT ud.department_code, r.name as role_name, r.display_name as role_display_name
    FROM auth_user_departments ud
    JOIN auth_roles r ON ud.role_id = r.id
    WHERE ud.user_id = ?
", [$session['user_id']]);

// Проверяем, есть ли у пользователя доступ к цеху U5
$hasAccessToU5 = false;
$userRole = null;
foreach ($userDepartments as $dept) {
    if ($dept['department_code'] === 'U5') {
        $hasAccessToU5 = true;
        $userRole = $dept['role_name'];
        break;
    }
}

// Определяем текущий цех
$currentDepartment = $_SESSION['auth_department'] ?? 'U5';

// Если отдел пустой в сессии, но у пользователя есть доступ к U5, устанавливаем U5
if (empty($_SESSION['auth_department']) && $hasAccessToU5) {
    $currentDepartment = 'U5';
    $_SESSION['auth_department'] = 'U5'; // Обновляем сессию
}

// Если нет доступа к U5, показываем предупреждение, но не блокируем
if (!$hasAccessToU5) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px; border-radius: 5px;'>";
    echo "<h3>⚠️ Внимание: Нет доступа к цеху U5</h3>";
    echo "<p>Ваши доступные цеха: ";
    $deptNames = [];
    foreach ($userDepartments as $dept) {
        $deptNames[] = $dept['department_code'] . " (" . $dept['role_name'] . ")";
    }
    echo implode(", ", $deptNames);
    echo "</p>";
    echo "<p><a href='../index.php'>← Вернуться на главную страницу</a></p>";
    echo "</div>";
    
    // Устанавливаем роль по умолчанию для отображения
    $userRole = 'guest';
}
?>
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

        /* модальные окна */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: var(--panel);
            margin: 5% auto;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--ink);
        }
        .close {
            color: var(--muted);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .close:hover {
            color: var(--ink);
        }
        .modal-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .modal-buttons button {
            width: 100%;
            text-align: left;
            padding: 12px 16px;
            font-size: 14px;
        }

        /* Стили для модального окна параметров фильтра */
        .modal-body .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .modal-body .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 900px) {
            .modal-body .row { 
                grid-template-columns: 1fr; 
            }
        }

        .modal-body .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .modal-body .table th, 
        .modal-body .table td {
            border-bottom: 1px solid var(--border);
            padding: 6px 4px;
            text-align: left;
            vertical-align: top;
            font-size: 12px;
        }

        .modal-body .table th { 
            width: 35%; 
            color: var(--muted); 
            font-weight: 600; 
        }

        .modal-body .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            border: 1px solid var(--border);
            background: #fafafa;
        }

        .modal-body .yn-yes { 
            color: #2e7d32; 
            font-weight: 600; 
        }

        .modal-body .yn-no { 
            color: #c62828; 
            font-weight: 600; 
        }

        .modal-body .section-title { 
            font-size: 13px; 
            font-weight: 700; 
            margin: 0 0 6px; 
        }

        .modal-body .small { 
            font-size: 11px; 
            color: var(--muted); 
        }

        .modal-body .value-mono { 
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; 
        }

        .modal-body .pair { 
            display: flex; 
            gap: 8px; 
            align-items: center; 
            flex-wrap: wrap; 
        }

        /* Стили для выпадающего списка фильтров */
        .filter-suggestion-item {
            padding: 8px 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
            transition: background-color 0.2s;
        }

        .filter-suggestion-item:hover {
            background-color: #f8f9fa;
        }

        .filter-suggestion-item:last-child {
            border-bottom: none;
        }

        .filter-suggestion-item.highlighted {
            background-color: var(--accent-soft);
            color: var(--accent);
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
        
        /* оранжевые кнопки для перепланирования */
        .saved-orders input[type="submit"].replanning-btn{
            background:#f59e0b!important; color:#fff!important;
            box-shadow:0 1px 4px rgba(245, 158, 11, 0.3);
        }
        
        .saved-orders input[type="submit"].replanning-btn:hover{
            background:#d97706!important;
            box-shadow:0 2px 8px rgba(245, 158, 11, 0.4);
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
/** подключение файлов настроек/инструментов уже выполнено в начале файла */

global $mysql_host, $mysql_user, $mysql_user_pass, $mysql_database;

// Устанавливаем переменные для совместимости со старым кодом
$workshop = $currentDepartment;
$advertisement = 'Информация';

// Добавляем аккуратную панель авторизации
echo "<!-- Аккуратная панель авторизации -->
<div style='position: fixed; top: 10px; right: 10px; background: white; padding: 12px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; border: 1px solid #e5e7eb;'>
    <div style='display: flex; align-items: center; gap: 12px;'>
        <div style='width: 32px; height: 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px;'>
            " . mb_substr($user['full_name'] ?? 'П', 0, 1, 'UTF-8') . "
        </div>
        <div>
            <div style='font-weight: 600; font-size: 14px; color: #1f2937;'>" . htmlspecialchars($user['full_name'] ?? 'Пользователь') . "</div>
            <div style='font-size: 12px; color: #6b7280;'>" . htmlspecialchars($user['phone'] ?? '') . "</div>
            <div style='font-size: 11px; color: #9ca3af;'>" . $currentDepartment . " • " . ucfirst($userRole ?? 'guest') . "</div>
        </div>
        <a href='../auth/change-password.php' style='padding: 4px 8px; background: transparent; color: #9ca3af; text-decoration: none; border-radius: 3px; font-size: 11px; font-weight: 400; transition: all 0.2s; border: 1px solid #e5e7eb;' onmouseover='this.style.background=\"#f9fafb\"; this.style.color=\"#6b7280\"; this.style.borderColor=\"#d1d5db\"' onmouseout='this.style.background=\"transparent\"; this.style.color=\"#9ca3af\"; this.style.borderColor=\"#e5e7eb\"'>Пароль</a>
        <a href='../auth/logout.php' style='padding: 6px 12px; background: #f3f4f6; color: #374151; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 500; transition: background-color 0.2s;' onmouseover='this.style.background=\"#e5e7eb\"' onmouseout='this.style.background=\"#f3f4f6\"'>Выход</a>
    </div>
</div>";
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
                       
                    </div>
                    <div class="topbar-right">
                        <!-- Панель авторизации перенесена вверх -->
                    </div>
                </div>
            </td>
        </tr>

            </td>
        </tr>

        <!-- Контент: 3 колонки -->
        <tr class="content-row">
            <!-- Левая панель -->
            <td class="panel panel--left" style="width:30%;">
                <div class="section-title">Операции</div>
                <div class="stack">
                    <a href="product_output.php" target="_blank" rel="noopener" class="stack"><button>Выпуск продукции</button></a>
                    <form action="product_output_view.php" method="post" class="stack" target="_blank"><input type="submit" value="Обзор выпуска продукции"></form>
                    <button onclick="openDataEditor()">Редактор данных</button>
                    <a href="NP_supply_requirements.php" target="_blank" rel="noopener" class="stack"><button>Потребность комплектующих</button></a>
                </div>

                <div class="section-title" style="margin-top:14px">Дополнения</div>
                <div class="stack">
                    <form action="BOX_CREATOR.htm" method="post" class="stack" target="_blank"><input type="submit" value="Расчет коробок"></form>
                    <form action="BOX_CREATOR_2.htm" method="post" class="stack" target="_blank"><input type="submit" value="Максимальное количество"></form>
                </div>

                <div class="section-title" style="margin-top:14px">Мониторинг</div>
                <div class="stack">
                    <form action='NP_full_build_plan.php' method='post' target="_blank" class="stack"><input type='submit' value='Полный план сборки'></form>

                    <form action="NP_build_plan_week.php" method="get" target="_blank" style="display: flex; gap: 10px; align-items: end; width: 100%;">
                        <div style="flex: 1;">
                            <?php load_planned_orders(); ?>
                        </div>
                        <input type="submit" value="План по заявке" style="flex: 1; white-space: nowrap;">
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
                    <button onclick="openFilterParamsModal()">Просмотреть параметры фильтра</button>
                    <form action='add_filter_properties_into_db.php' method='post' target='_blank' class="stack">
                        <input type='hidden' name='workshop' value='<?php echo htmlspecialchars($workshop); ?>'>
                        <input type='submit' value='Изменить параметры фильтра'>
                    </form>
                </div>

                <div class="section-title" style="margin-top:14px">Объявление</div>
                <div class="stack">
                    <button onclick="openCreateAdModal()">Создать объявление</button>
                </div>
            </td>

            <!-- Центральная панель -->
            <td class="panel panel--main" style="width:40%;">
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
            <td class="panel panel--right" style="width:30%;">
                <?php
                /* загрузка заявок */
                $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
                if ($mysqli->connect_errno) { echo 'Возникла проблема на сайте'; exit; }
                $sql = "SELECT DISTINCT order_number, workshop, hide, status FROM orders;";
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
                            $status = $orders_data['status'] ?? 'normal';
                            $class = ($status === 'replanning') ? ' class="replanning-btn"' : '';
                            echo "<input type='submit' name='order_number' value='{$val}'{$class}>";
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

<!-- Модальное окно редактора данных -->
<div id="dataEditorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Редактор данных</h2>
            <span class="close" onclick="closeDataEditor()">&times;</span>
        </div>
        <div class="modal-buttons">
            <button onclick="openProductEditor()">📊 Редактор выпущенной продукции</button>
            <button onclick="openAuditLogs()" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">📋 Логи аудита</button>
            <button onclick="closeDataEditor()">❌ Закрыть</button>
        </div>
    </div>
</div>

<!-- Модальное окно редактора продукции -->
<div id="productEditorModal" class="modal">
    <div class="modal-content" style="max-width: 1200px;">
        <div class="modal-header">
            <h2 class="modal-title">Редактор выпущенной продукции</h2>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button onclick="openAuditLogs()" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                    📋 Логи аудита
                </button>
                <span class="close" onclick="closeProductEditor()">&times;</span>
            </div>
        </div>
            <div id="productEditorContent">
                <div style="margin-bottom: 20px; padding: 16px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                    <h4 style="margin: 0 0 12px 0; color: #495057;">📅 Выберите дату для редактирования</h4>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <input type="date" id="editDate" style="padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <button onclick="loadDataForDate()" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;">
                            🔍 Загрузить данные
                        </button>
                    </div>
                </div>
                <div id="dataTableContainer" style="display: none;">
                    <!-- Здесь будет таблица с данными -->
                </div>
            </div>
    </div>
</div>

<!-- Модальное окно добавления позиции -->
<div id="addPositionModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 class="modal-title">➕ Добавить позицию</h2>
            <span class="close" onclick="closeAddPositionModal()">&times;</span>
        </div>
        <div id="addPositionContent">
            <form id="addPositionForm">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 500; color: #374151;">Дата производства:</label>
                    <input type="date" id="addPositionDate" required style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 500; color: #374151;">Название фильтра:</label>
                    <select id="addPositionFilter" required style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <option value="">Выберите фильтр</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 500; color: #374151;">Количество:</label>
                    <input type="number" id="addPositionQuantity" required min="1" placeholder="Введите количество" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 500; color: #374151;">Название заявки:</label>
                    <select id="addPositionOrder" required style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <option value="">Выберите заявку</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 4px; font-weight: 500; color: #374151;">Бригада:</label>
                    <select id="addPositionTeam" required style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <option value="">Выберите бригаду</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeAddPositionModal()" style="padding: 8px 16px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 6px; cursor: pointer;">
                        Отмена
                    </button>
                    <button type="submit" style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        ➕ Добавить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Функции для модальных окон
function openDataEditor() {
    document.getElementById('dataEditorModal').style.display = 'block';
}

function closeDataEditor() {
    document.getElementById('dataEditorModal').style.display = 'none';
}

function openProductEditor() {
    document.getElementById('productEditorModal').style.display = 'block';
    loadProductEditor();
}

function closeProductEditor() {
    document.getElementById('productEditorModal').style.display = 'none';
}

function openAuditLogs() {
    // Закрываем модальное окно редактора данных
    closeDataEditor();
    // Открываем страницу логов аудита в новой вкладке
    window.open('audit_viewer.php', '_blank');
}

function closeAddPositionModal() {
    document.getElementById('addPositionModal').style.display = 'none';
}

// Закрытие модальных окон при клике вне их
window.onclick = function(event) {
    const dataModal = document.getElementById('dataEditorModal');
    const productModal = document.getElementById('productEditorModal');
    const addPositionModal = document.getElementById('addPositionModal');
    
    if (event.target === dataModal) {
        closeDataEditor();
    }
    if (event.target === productModal) {
        closeProductEditor();
    }
    if (event.target === addPositionModal) {
        closeAddPositionModal();
    }
}

// Функция загрузки редактора продукции
function loadProductEditor() {
    // Устанавливаем сегодняшнюю дату по умолчанию
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('editDate').value = today;
    
    // Скрываем таблицу данных
    document.getElementById('dataTableContainer').style.display = 'none';
}

// Функция загрузки данных по выбранной дате
function loadDataForDate() {
    const selectedDate = document.getElementById('editDate').value;
    
    if (!selectedDate) {
        alert('Пожалуйста, выберите дату');
        return;
    }
    
    const container = document.getElementById('dataTableContainer');
    container.innerHTML = '<p>Загрузка данных...</p>';
    container.style.display = 'block';
    
    // AJAX запрос для загрузки данных по дате
    const formData = new FormData();
    formData.append('action', 'load_data_by_date');
    formData.append('date', selectedDate);
    
    fetch('product_editor_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                renderProductEditor(data.data, selectedDate);
            } else {
                container.innerHTML = `<p style="color: red;">Ошибка: ${data.error}</p>`;
            }
        } catch (e) {
            container.innerHTML = `
                <div style="color: red;">
                    <p><strong>Ошибка парсинга JSON:</strong></p>
                    <p>${e.message}</p>
                    <p><strong>Ответ сервера:</strong></p>
                    <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow: auto;">${text}</pre>
                </div>
            `;
        }
    })
    .catch(error => {
        container.innerHTML = `<p style="color: red;">Ошибка загрузки: ${error.message}</p>`;
    });
}

// Функция отображения редактора продукции
function renderProductEditor(data, selectedDate) {
    const container = document.getElementById('dataTableContainer');
    
    if (data.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #6b7280;">
                <h3>📅 ${selectedDate}</h3>
                <p>Нет данных за выбранную дату</p>
                <button onclick="addNewPosition('${selectedDate}')" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; margin-top: 16px;">
                    ➕ Добавить позицию
                </button>
            </div>
        `;
        return;
    }
    
    // Группируем данные по бригаде (дата уже известна)
    const groupedData = {};
    data.forEach(item => {
        const brigade = item.brigade || 'Не указана';
        const key = brigade;
        
        if (!groupedData[key]) {
            groupedData[key] = {
                brigade: brigade,
                items: []
            };
        }
        groupedData[key].items.push(item);
    });
    
    let html = `
        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #374151;">📅 ${selectedDate}</h3>
            <button onclick="addNewPosition('${selectedDate}')" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                ➕ Добавить позицию
            </button>
        </div>
    `;
    
    // Отображаем данные по группам
    Object.values(groupedData).forEach(group => {
        html += `
            <div style="margin-bottom: 30px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px;">
                <h4 style="margin: 0 0 16px 0; color: #374151;">
                    👥 Бригада ${group.brigade}
                </h4>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Фильтр</th>
                                <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">Кол-во</th>
                                <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">Заявка</th>
                                <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        group.items.forEach(item => {
            const filterName = item.filter_name || 'Не указан';
            const quantity = item.quantity || 0;
            const orderNumber = item.order_number || 'Не указан';
            const itemId = item.virtual_id || '';
            
            html += `
                <tr>
                    <td style="padding: 8px; border: 1px solid #e5e7eb;">${filterName}</td>
                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">
                        <input type="number" value="${quantity}" min="0" 
                               onchange="updateQuantity('${itemId}', this.value)" 
                               style="width: 60px; padding: 4px; border: 1px solid #d1d5db; border-radius: 4px;">
                    </td>
                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">
                        <select onchange="moveToOrder('${itemId}', this.value)" 
                                class="order-select" data-item-id="${itemId}"
                                style="padding: 4px; border: 1px solid #d1d5db; border-radius: 4px; min-width: 100px;">
                            <option value="${orderNumber}">${orderNumber}</option>
                        </select>
                    </td>
                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">
                        <button onclick="removePosition('${itemId}')" 
                                data-item-id="${itemId}"
                                style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                            🗑️ Удалить
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Загружаем заявки для всех выпадающих списков в таблице
    loadOrdersForTableDropdowns();
}

// Функция загрузки заявок для выпадающих списков в таблице
function loadOrdersForTableDropdowns() {
    console.log('Начинаем загрузку заявок для таблицы...');
    
    const orderFormData = new FormData();
    orderFormData.append('action', 'load_orders_for_dropdown');
    
    fetch('product_editor_api.php', {
        method: 'POST',
        body: orderFormData
    })
    .then(response => {
        console.log('Ответ получен, статус:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('Ответ сервера:', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                console.log('Заявки получены:', data.orders);
                // Находим все выпадающие списки заявок в таблице
                const orderSelects = document.querySelectorAll('.order-select');
                console.log('Найдено выпадающих списков:', orderSelects.length);
                
                orderSelects.forEach((select, index) => {
                    const currentValue = select.querySelector('option').value;
                    console.log(`Обрабатываем список ${index + 1}, текущее значение:`, currentValue);
                    
                    select.innerHTML = '';
                    
                    // Добавляем текущее значение как выбранное
                    const currentOption = document.createElement('option');
                    currentOption.value = currentValue;
                    currentOption.textContent = currentValue;
                    currentOption.selected = true;
                    select.appendChild(currentOption);
                    
                    // Добавляем все заявки
                    data.orders.forEach(order => {
                        if (order !== currentValue) {
                            const option = document.createElement('option');
                            option.value = order;
                            option.textContent = order;
                            select.appendChild(option);
                        }
                    });
                });
                console.log('Заявки для таблицы загружены:', data.orders);
            } else {
                console.error('Ошибка загрузки заявок для таблицы:', data.error);
            }
        } catch (e) {
            console.error('Ошибка парсинга заявок для таблицы:', e, text);
        }
    })
    .catch(error => {
        console.error('Ошибка загрузки заявок для таблицы:', error);
    });
}

// Функции для работы с данными
function updateQuantity(id, quantity) {
    const formData = new FormData();
    formData.append('action', 'update_quantity');
    formData.append('id', id);
    formData.append('quantity', quantity);
    
    fetch('product_editor_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Количество успешно обновлено
            console.log('Количество обновлено для ID:', id);
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        alert('Ошибка обновления: ' + error.message);
    });
}

function moveToOrder(id, newOrderId) {
    const formData = new FormData();
    formData.append('action', 'move_to_order');
    formData.append('id', id);
    formData.append('new_order_id', newOrderId);
    
    fetch('product_editor_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Позиция успешно перенесена');
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        alert('Ошибка переноса: ' + error.message);
    });
}

function removePosition(id) {
    if (!confirm('Вы уверены, что хотите удалить эту позицию?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove_position');
    formData.append('id', id);
    
    fetch('product_editor_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Находим и удаляем строку из таблицы по data-атрибуту
            const rowToRemove = document.querySelector(`button[data-item-id="${id}"]`).closest('tr');
            if (rowToRemove) {
                rowToRemove.remove();
            }
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        alert('Ошибка удаления: ' + error.message);
    });
}

function addNewPosition(selectedDate) {
    if (!selectedDate) {
        selectedDate = document.getElementById('editDate').value;
    }
    
    if (!selectedDate) {
        alert('Пожалуйста, выберите дату');
        return;
    }
    
    // Устанавливаем выбранную дату в форму
    document.getElementById('addPositionDate').value = selectedDate;
    
    // Очищаем остальные поля
    document.getElementById('addPositionFilter').value = '';
    document.getElementById('addPositionQuantity').value = '';
    document.getElementById('addPositionOrder').value = '';
    document.getElementById('addPositionTeam').value = '';
    
    // Загружаем данные для выпадающих списков
    loadFiltersAndOrders();
    
    // Открываем модальное окно
    document.getElementById('addPositionModal').style.display = 'block';
}

// Функция загрузки фильтров и заявок
function loadFiltersAndOrders() {
    // Загружаем фильтры
    const filterFormData = new FormData();
    filterFormData.append('action', 'load_filters');
    
    fetch('product_editor_api.php', {
        method: 'POST',
        body: filterFormData
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                const filterSelect = document.getElementById('addPositionFilter');
                filterSelect.innerHTML = '<option value="">Выберите фильтр</option>';
                data.filters.forEach(filter => {
                    const option = document.createElement('option');
                    option.value = filter;
                    option.textContent = filter;
                    filterSelect.appendChild(option);
                });
                console.log('Фильтры загружены:', data.filters);
            } else {
                console.error('Ошибка загрузки фильтров:', data.error);
            }
        } catch (e) {
            console.error('Ошибка парсинга фильтров:', e, text);
        }
    })
    .catch(error => {
        console.error('Ошибка загрузки фильтров:', error);
    });
    
    // Загружаем заявки
    const orderFormData = new FormData();
    orderFormData.append('action', 'load_orders');
    
    fetch('product_editor_api.php', {
        method: 'POST',
        body: orderFormData
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                const orderSelect = document.getElementById('addPositionOrder');
                orderSelect.innerHTML = '<option value="">Выберите заявку</option>';
                data.orders.forEach(order => {
                    const option = document.createElement('option');
                    option.value = order;
                    option.textContent = order;
                    orderSelect.appendChild(option);
                });
                console.log('Заявки загружены:', data.orders);
            } else {
                console.error('Ошибка загрузки заявок:', data.error);
            }
        } catch (e) {
            console.error('Ошибка парсинга заявок:', e, text);
        }
    })
    .catch(error => {
        console.error('Ошибка загрузки заявок:', error);
    });
}

// Обработчик формы добавления позиции
document.addEventListener('DOMContentLoaded', function() {
    const addPositionForm = document.getElementById('addPositionForm');
    if (addPositionForm) {
        addPositionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitAddPosition();
        });
    }
});

function submitAddPosition() {
    const date = document.getElementById('addPositionDate').value;
    const filter = document.getElementById('addPositionFilter').value;
    const quantity = document.getElementById('addPositionQuantity').value;
    const order = document.getElementById('addPositionOrder').value;
    const team = document.getElementById('addPositionTeam').value;
    
    if (!date || !filter || !quantity || !order || !team) {
        alert('Пожалуйста, заполните все поля');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_position');
    formData.append('production_date', date);
    formData.append('filter_name', filter);
    formData.append('quantity', quantity);
    formData.append('order_name', order);
    formData.append('team', team);
    
    fetch('product_editor_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Позиция успешно добавлена!');
            closeAddPositionModal();
            // Перезагружаем данные для выбранной даты
            loadDataForDate();
        } else {
            alert('Ошибка: ' + data.error);
        }
    })
    .catch(error => {
        alert('Ошибка добавления: ' + error.message);
    });
}

// Функции для модального окна параметров фильтра
function openFilterParamsModal() {
    document.getElementById('filterParamsModal').style.display = 'block';
}

function closeFilterParamsModal() {
    document.getElementById('filterParamsModal').style.display = 'none';
}

function loadFilterParams() {
    const filterName = document.getElementById('filterNameInput').value.trim();
    if (!filterName) {
        alert('Введите имя фильтра');
        return;
    }

    const contentDiv = document.getElementById('filterParamsContent');
    contentDiv.innerHTML = '<div style="text-align: center; padding: 20px;"><div style="display: inline-block; width: 20px; height: 20px; border: 2px solid var(--border); border-top: 2px solid var(--accent); border-radius: 50%; animation: spin 1s linear infinite;"></div><br>Загрузка параметров...</div>';

    fetch('view_salon_filter_params.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'filter_name=' + encodeURIComponent(filterName)
    })
    .then(response => response.text())
    .then(html => {
        // Извлекаем только содержимое body из ответа
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const bodyContent = doc.body.innerHTML;
        
        // Убираем header и оставляем только данные
        const dataStart = bodyContent.indexOf('<div class="card">');
        if (dataStart !== -1) {
            contentDiv.innerHTML = bodyContent.substring(dataStart);
        } else {
            contentDiv.innerHTML = '<p style="color: var(--danger); text-align: center; padding: 20px;">Фильтр не найден или произошла ошибка</p>';
        }
    })
    .catch(error => {
        contentDiv.innerHTML = '<p style="color: var(--danger); text-align: center; padding: 20px;">Ошибка загрузки: ' + error.message + '</p>';
    });
}

// Переменные для автодополнения
let filterSuggestions = [];
let currentHighlightIndex = -1;

// Функция поиска фильтров
function searchFilters(query) {
    if (query.length < 2) {
        hideFilterSuggestions();
        return;
    }

    // Загружаем список фильтров из БД
    fetch('get_filter_list.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'query=' + encodeURIComponent(query)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.filters) {
            filterSuggestions = data.filters;
            showFilterSuggestions(data.filters);
        } else {
            hideFilterSuggestions();
        }
    })
    .catch(error => {
        console.error('Ошибка загрузки фильтров:', error);
        hideFilterSuggestions();
    });
}

// Показать выпадающий список
function showFilterSuggestions(filters) {
    const suggestionsDiv = document.getElementById('filterSuggestions');
    suggestionsDiv.innerHTML = '';
    
    if (filters.length === 0) {
        suggestionsDiv.innerHTML = '<div class="filter-suggestion-item" style="color: var(--muted);">Фильтры не найдены</div>';
    } else {
        filters.forEach((filter, index) => {
            const item = document.createElement('div');
            item.className = 'filter-suggestion-item';
            item.textContent = filter;
            item.onclick = () => selectFilter(filter);
            item.onmouseover = () => highlightSuggestion(index);
            suggestionsDiv.appendChild(item);
        });
    }
    
    suggestionsDiv.style.display = 'block';
    currentHighlightIndex = -1;
}

// Скрыть выпадающий список
function hideFilterSuggestions() {
    setTimeout(() => {
        document.getElementById('filterSuggestions').style.display = 'none';
    }, 200);
}

// Выделить элемент в списке
function highlightSuggestion(index) {
    const items = document.querySelectorAll('.filter-suggestion-item');
    items.forEach((item, i) => {
        item.classList.toggle('highlighted', i === index);
    });
    currentHighlightIndex = index;
}

// Выбрать фильтр
function selectFilter(filterName) {
    document.getElementById('filterNameInput').value = filterName;
    hideFilterSuggestions();
    loadFilterParams();
}

// Обработка клавиш в поле ввода
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('filterNameInput');
    if (input) {
        input.addEventListener('keydown', function(e) {
            const suggestionsDiv = document.getElementById('filterSuggestions');
            const items = suggestionsDiv.querySelectorAll('.filter-suggestion-item');
            
            if (suggestionsDiv.style.display === 'block' && items.length > 0) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    currentHighlightIndex = Math.min(currentHighlightIndex + 1, items.length - 1);
                    highlightSuggestion(currentHighlightIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    currentHighlightIndex = Math.max(currentHighlightIndex - 1, -1);
                    if (currentHighlightIndex === -1) {
                        items.forEach(item => item.classList.remove('highlighted'));
                    } else {
                        highlightSuggestion(currentHighlightIndex);
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (currentHighlightIndex >= 0 && items[currentHighlightIndex]) {
                        selectFilter(items[currentHighlightIndex].textContent);
                    } else {
                        loadFilterParams();
                    }
                } else if (e.key === 'Escape') {
                    hideFilterSuggestions();
                }
            }
        });
    }
});

// Функции для модального окна создания объявления
function openCreateAdModal() {
    document.getElementById('createAdModal').style.display = 'block';
    // Устанавливаем дату по умолчанию (через неделю)
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 7);
    document.querySelector('input[name="expires_at"]').value = tomorrow.toISOString().split('T')[0];
}

function closeCreateAdModal() {
    document.getElementById('createAdModal').style.display = 'none';
    document.getElementById('createAdForm').reset();
}

function submitAd(event) {
    event.preventDefault();
    
    const form = document.getElementById('createAdForm');
    const formData = new FormData(form);
    
    // Показываем индикатор загрузки
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Создание...';
    submitBtn.disabled = true;
    
    fetch('create_ad.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('success') || data.includes('успешно')) {
            alert('Объявление успешно создано!');
            closeCreateAdModal();
            // Перезагружаем страницу для обновления списка объявлений
            location.reload();
        } else {
            alert('Ошибка при создании объявления: ' + data);
        }
    })
    .catch(error => {
        alert('Ошибка при создании объявления: ' + error.message);
    })
    .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
}

// Закрытие модальных окон при клике вне их
window.onclick = function(event) {
    const filterModal = document.getElementById('filterParamsModal');
    const adModal = document.getElementById('createAdModal');
    
    if (event.target === filterModal) {
        closeFilterParamsModal();
    } else if (event.target === adModal) {
        closeCreateAdModal();
    }
}
</script>

<!-- Модальное окно для просмотра параметров фильтра -->
<div id="filterParamsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px; max-height: 70vh; overflow-y: auto;">
        <div class="modal-header">
            <h3 class="modal-title">Просмотр параметров фильтра</h3>
            <span class="close" onclick="closeFilterParamsModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 16px; position: relative;">
                <input type="text" id="filterNameInput" placeholder="Введите имя фильтра (например: AF1593)" 
                       style="width: 300px; padding: 10px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 10px;"
                       oninput="searchFilters(this.value)" onfocus="searchFilters(this.value)" onblur="hideFilterSuggestions()">
                <div id="filterSuggestions" style="position: absolute; top: 50px; left: 0; width: 300px; background: white; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; display: none; max-height: 200px; overflow-y: auto;">
                </div>
                <button onclick="loadFilterParams()" style="padding: 10px 20px; background: var(--accent); color: white; border: none; border-radius: 8px; cursor: pointer; margin-left: 10px;">
                    Показать параметры
                </button>
            </div>
            <div id="filterParamsContent">
                <p style="color: var(--muted); text-align: center; padding: 20px;">
                    Введите имя фильтра и нажмите "Показать параметры"
                </p>
            </div>
        </div>
    </div>
    </div>

    <!-- Модальное окно для создания объявления -->
    <div id="createAdModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px; max-height: 80vh; overflow-y: auto; overflow-x: hidden;">
            <div class="modal-header">
                <h3 class="modal-title">📢 Создать объявление</h3>
                <span class="close" onclick="closeCreateAdModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="createAdForm" onsubmit="submitAd(event)">
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Название объявления:</label>
                        <input type="text" name="title" placeholder="Введите название объявления" required
                               style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 6px; font-weight: 500;">Текст объявления:</label>
                        <textarea name="content" placeholder="Введите текст объявления" required
                                  style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; min-height: 120px; resize: vertical;"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: space-between; align-items: end; flex-wrap: wrap;">
                        <div style="min-width: 160px; max-width: 180px;">
                            <label style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px;">Дата окончания:</label>
                            <input type="date" name="expires_at" required
                                   style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px;">
                        </div>
                        <div style="display: flex; gap: 10px; flex-shrink: 0;">
                            <button type="button" onclick="closeCreateAdModal()" 
                                    style="padding: 10px 20px; background: var(--muted); color: white; border: none; border-radius: 8px; cursor: pointer;">
                                Отмена
                            </button>
                            <button type="submit" 
                                    style="padding: 10px 20px; background: var(--accent); color: white; border: none; border-radius: 8px; cursor: pointer;">
                                Создать объявление
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
