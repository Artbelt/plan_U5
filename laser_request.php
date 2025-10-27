<?php
// Проверяем авторизацию через новую систему
require_once('../auth/includes/config.php');
require_once('../auth/includes/auth-functions.php');

// Подключаем настройки базы данных
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

$userDepartments = $db->select("
    SELECT ud.department_code, r.name as role_name, r.display_name as role_display_name
    FROM auth_user_departments ud
    JOIN auth_roles r ON ud.role_id = r.id
    WHERE ud.user_id = ?
", [$session['user_id']]);

// Определяем текущий цех
$currentDepartment = $_SESSION['auth_department'] ?? 'U5';

// Функция проверки доступа к заявкам на лазер
function canAccessLaserRequests($userDepartments, $currentDepartment) {
    foreach ($userDepartments as $dept) {
        if ($dept['department_code'] === $currentDepartment) {
            $role = $dept['role_name'];
            return in_array($role, ['assembler', 'corr_operator', 'supervisor', 'director']) && $role !== 'manager';
        }
    }
    return false;
}

$canAccessLaser = canAccessLaserRequests($userDepartments, $currentDepartment);

if (!$canAccessLaser) {
    header('Location: main.php');
    exit;
}

// Подключение к БД для работы с заявками
$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);
if ($mysqli->connect_errno) {
    die("Ошибка подключения к БД: " . $mysqli->connect_error);
}

// Создание таблицы для заявок на лазер, если её нет
$create_table_sql = "
CREATE TABLE IF NOT EXISTS laser_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_name VARCHAR(255) NOT NULL,
    department VARCHAR(10) NOT NULL,
    component_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    desired_delivery_time DATETIME NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$mysqli->query($create_table_sql);

// Добавляем поле completed_at, если оно не существует
$alter_sql = "ALTER TABLE laser_requests ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL AFTER is_completed";
$mysqli->query($alter_sql);

// Создание таблицы для справочника комплектующих
$create_components_table_sql = "
CREATE TABLE IF NOT EXISTS laser_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
$mysqli->query($create_components_table_sql);

// Проверяем, является ли пользователь мастером (supervisor)
$isSupervisor = false;
foreach ($userDepartments as $dept) {
    if ($dept['department_code'] === $currentDepartment && $dept['role_name'] === 'supervisor') {
        $isSupervisor = true;
        break;
    }
}

// Обработка добавления нового комплектующего (только для мастеров)
if (isset($_POST['action']) && $_POST['action'] === 'add_component' && $isSupervisor) {
    $component_name = trim($_POST['component_name'] ?? '');
    $component_description = trim($_POST['component_description'] ?? '');
    
    if ($component_name) {
        $insert_component_sql = "INSERT INTO laser_components (name, description, created_by) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($insert_component_sql);
        $stmt->bind_param("sss", $component_name, $component_description, $user['full_name']);
        
        if ($stmt->execute()) {
            $success_message = "Комплектующее успешно добавлено!";
        } else {
            if ($mysqli->errno === 1062) { // Duplicate entry
                $error_message = "Комплектующее с таким названием уже существует!";
            } else {
                $error_message = "Ошибка при добавлении комплектующего!";
            }
        }
        $stmt->close();
    }
}

// Получение списка комплектующих для автодополнения
$components_query = "SELECT DISTINCT component_name FROM laser_requests ORDER BY component_name";
$components_result = $mysqli->query($components_query);
$components_list = [];
if ($components_result) {
    while ($row = $components_result->fetch_assoc()) {
        $components_list[] = $row['component_name'];
    }
}

// Обработка отправки формы
if (isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    $component_name = trim($_POST['component_name'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $desired_delivery_date = $_POST['desired_delivery_date'] ?? '';
    $desired_delivery_hour = $_POST['desired_delivery_hour'] ?? '';
    
    if ($component_name && $quantity > 0) {
        $insert_sql = "INSERT INTO laser_requests (user_name, department, component_name, quantity, desired_delivery_time) VALUES (?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($insert_sql);
        
        // Формируем datetime из отдельной даты и часа
        $datetime = null;
        if ($desired_delivery_date && $desired_delivery_hour) {
            $datetime = $desired_delivery_date . ' ' . $desired_delivery_hour . ':00:00';
        }
        
        $stmt->bind_param("sssis", $user['full_name'], $currentDepartment, $component_name, $quantity, $datetime);
        $stmt->execute();
        $stmt->close();
        
        $success_message = "Заявка успешно отправлена!";
    }
}

// Обработка отметки заявки как выполненной
if (isset($_POST['action']) && $_POST['action'] === 'mark_completed' && isset($_POST['request_id'])) {
    $request_id = (int)$_POST['request_id'];
    
    // Проверяем, что заявка принадлежит пользователю
    $check_sql = "SELECT id FROM laser_requests WHERE id = ? AND user_name = ? AND department = ?";
    $stmt = $mysqli->prepare($check_sql);
    $stmt->bind_param("iss", $request_id, $user['full_name'], $currentDepartment);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Отмечаем как выполненную и записываем время выполнения
        $update_sql = "UPDATE laser_requests SET is_completed = TRUE, completed_at = NOW() WHERE id = ?";
        $stmt = $mysqli->prepare($update_sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();
        
        $success_message = "Заявка отмечена как выполненная!";
    }
}

// Получение заявок пользователя
$user_requests_query = "SELECT * FROM laser_requests WHERE user_name = ? AND department = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $mysqli->prepare($user_requests_query);
$stmt->bind_param("ss", $user['full_name'], $currentDepartment);
$stmt->execute();
$user_requests_result = $stmt->get_result();
$user_requests = $user_requests_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Заявка на лазерную резку</title>
    <style>
        :root{
            --bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --bg-solid: #f8fafc;
            --panel: #ffffff;
            --ink: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-solid: #667eea;
            --accent-ink: #ffffff;
            --radius: 16px;
            --radius-sm: 12px;
            --shadow: 0 10px 25px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
        }
        
        body{
            margin: 0; 
            background: var(--bg-solid); 
            color: var(--ink);
            font: 16px/1.6 "Inter", "Segoe UI", -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
        }
        
        .container{
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .panel{
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 20px;
        }
        
        .section-title{
            font-size: 24px;
            font-weight: 600;
            color: var(--ink);
            margin: 0 0 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }
        
        .form-group{
            margin-bottom: 20px;
        }
        
        label{
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--ink);
        }
        
        input, select{
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: #fff;
            color: var(--ink);
            outline: none;
            transition: all 0.2s;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        input:focus, select:focus{
            border-color: var(--accent-solid);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .datalist-suggestions{
            position: absolute;
            background: white;
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 var(--radius-sm) var(--radius-sm);
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            z-index: 1000;
            box-shadow: var(--shadow);
        }
        
        .datalist-option{
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .datalist-option:hover{
            background: #f8fafc;
        }
        
        .datalist-option:last-child{
            border-bottom: none;
        }
        
        .btn{
            background: var(--accent-solid);
            color: var(--accent-ink);
            border: none;
            border-radius: var(--radius-sm);
            padding: 12px 24px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn:hover{
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .success-message{
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
        }
        
        .table-wrapper{
            overflow-x: auto;
            margin-top: 20px;
            -webkit-overflow-scrolling: touch;
        }
        
        .requests-table{
            width: 100%;
            min-width: 300px;
            border-collapse: collapse;
        }
        
        .requests-table th,
        .requests-table td{
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .requests-table th{
            background: #f8fafc;
            font-weight: 600;
        }
        
        .status-completed{
            color: #059669;
            font-weight: 500;
        }
        
        .status-pending{
            color: #d97706;
            font-weight: 500;
        }
        
        .btn-complete{
            background: var(--accent);
            color: var(--accent-ink);
            border: none;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-complete:hover{
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        /* Адаптивные стили для мобильных устройств */
        @media (max-width: 768px) {
            .requests-table th,
            .requests-table td{
                padding: 8px;
                font-size: 14px;
            }
            
            .requests-table{
                min-width: 350px;
            }
            
            .btn-complete{
                padding: 4px 8px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .requests-table th,
            .requests-table td{
                padding: 6px;
                font-size: 12px;
            }
            
            .requests-table{
                min-width: 400px;
            }
        }
        
        .error-message {
            background: #fecaca;
            border: 1px solid #f87171;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
        }
        
        /* Модальное окно */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 101;
            padding: 20px;
        }
        
        .modal__panel {
            width: min(500px, calc(100% - 40px));
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            transform: translateY(20px) scale(0.95);
            transition: all 0.3s;
            opacity: 0;
            overflow: hidden;
        }
        
        .modal--open .modal__panel {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        
        .modal--open,
        .modal--open + .modal-backdrop {
            pointer-events: auto;
            opacity: 1;
        }
        
        .modal__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 2px solid var(--border);
            background: var(--gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
            color: white;
        }
        
        .modal__title {
            font-size: 18px;
            font-weight: 600;
            color: white;
            margin: 0;
        }
        
        .modal__body {
            padding: 24px;
        }
        
        .modal__foot {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            background: #f8fafc;
        }
        
        .modal__close {
            appearance: none;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: var(--radius-sm);
            padding: 6px 12px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .modal__close:hover {
            background: rgba(255,255,255,0.3);
        }
        
    </style>
</head>
<body>
    <div class="container">
        
        <div class="panel">
            <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
                <span>Заявка на лазерную резку</span>
                <?php if ($isSupervisor): ?>
                    <button type="button" onclick="openAddComponentModal()" style="width: 40px; height: 40px; padding: 0; font-size: 24px; line-height: 1; border-radius: 50%;" title="Добавить новое комплектующее">+</button>
                <?php endif; ?>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="success-message"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="submit_request">
                
                <div class="form-group">
                    <label for="component_name">Комплектующие:</label>
                    <input type="text" id="component_name" name="component_name" required 
                           placeholder="Введите название комплектующих..." 
                           autocomplete="off" list="components_datalist">
                    <datalist id="components_datalist">
                        <?php foreach ($components_list as $component): ?>
                            <option value="<?= htmlspecialchars($component) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Количество штук:</label>
                    <input type="number" id="quantity" name="quantity" required min="1" placeholder="Введите количество">
                </div>
                
                <div class="form-group">
                    <label for="desired_delivery_date">Дата поставки:</label>
                    <input type="date" id="desired_delivery_date" name="desired_delivery_date">
                </div>
                
                <div class="form-group">
                    <label for="desired_delivery_hour">Время поставки (час):</label>
                    <select id="desired_delivery_hour" name="desired_delivery_hour">
                        <option value="">Выберите час</option>
                        <?php for ($h = 8; $h <= 18; $h++): ?>
                            <option value="<?= sprintf('%02d', $h) ?>"><?= sprintf('%02d', $h) ?>:00</option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn">Отправить заявку</button>
            </form>
        </div>
        
        <div class="panel">
            <div class="section-title">Ваши последние заявки</div>
            
            <div class="table-wrapper">
            <table class="requests-table">
                <thead>
                    <tr>
                        <th>Комплектующие</th>
                        <th>Количество</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($user_requests) > 0): ?>
                        <?php foreach ($user_requests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['component_name']) ?></td>
                                <td><?= $request['quantity'] ?></td>
                                <td>
                                    <span class="<?= $request['is_completed'] ? 'status-completed' : 'status-pending' ?>">
                                        <?= $request['is_completed'] ? 'Выполнено' : 'В работе' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--muted); padding: 20px;">
                                У вас пока нет заявок
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно для добавления комплектующего -->
    <?php if ($isSupervisor): ?>
    <div id="addComponentModal" class="modal" aria-hidden="true" role="dialog">
        <div class="modal__panel" role="document">
            <div class="modal__head">
                <h3 class="modal__title">Добавить новое комплектующее</h3>
                <button type="button" class="modal__close" onclick="closeAddComponentModal()" aria-label="Закрыть">Закрыть</button>
            </div>
            <form id="addComponentForm" method="POST" action="">
                <input type="hidden" name="action" value="add_component">
                <div class="modal__body">
                    <div class="form-group">
                        <label for="new_component_name">Название комплектующего:</label>
                        <input type="text" id="new_component_name" name="component_name" required 
                               placeholder="Введите название комплектующего">
                    </div>
                    <div class="form-group">
                        <label for="new_component_description">Описание (необязательно):</label>
                        <input type="text" id="new_component_description" name="component_description" 
                               placeholder="Введите описание">
                    </div>
                </div>
                <div class="modal__foot">
                    <button type="button" class="modal__close" onclick="closeAddComponentModal()">Отмена</button>
                    <button type="submit" class="btn">Добавить</button>
                </div>
            </form>
        </div>
    </div>
    <div id="addComponentBackdrop" class="modal-backdrop"></div>
    <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Устанавливаем минимальную дату на сегодня
            const dateInput = document.getElementById('desired_delivery_date');
            if (dateInput) {
                const today = new Date();
                dateInput.min = today.toISOString().split('T')[0];
            }
        });
        
        <?php if ($isSupervisor): ?>
        // Функции для управления модальным окном
        function openAddComponentModal() {
            const modal = document.getElementById('addComponentModal');
            const backdrop = document.getElementById('addComponentBackdrop');
            const firstInput = document.getElementById('new_component_name');
            
            if (modal && backdrop) {
                modal.classList.add('modal--open');
                backdrop.classList.add('modal--open');
                modal.setAttribute('aria-hidden', 'false');
                
                setTimeout(() => {
                    if (firstInput) firstInput.focus();
                }, 100);
                
                document.addEventListener('keydown', handleEscapeKey);
            }
        }
        
        function closeAddComponentModal() {
            const modal = document.getElementById('addComponentModal');
            const backdrop = document.getElementById('addComponentBackdrop');
            
            if (modal && backdrop) {
                modal.classList.remove('modal--open');
                backdrop.classList.remove('modal--open');
                modal.setAttribute('aria-hidden', 'true');
                
                // Очищаем форму
                const form = document.getElementById('addComponentForm');
                if (form) form.reset();
                
                document.removeEventListener('keydown', handleEscapeKey);
            }
        }
        
        function handleEscapeKey(e) {
            if (e.key === 'Escape') {
                closeAddComponentModal();
            }
        }
        
        // Закрытие модального окна по клику на backdrop
        const backdrop = document.getElementById('addComponentBackdrop');
        if (backdrop) {
            backdrop.addEventListener('click', closeAddComponentModal);
        }
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Закрываем соединение с БД после вывода
$mysqli->close();
?>
