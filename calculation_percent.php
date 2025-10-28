<?php
// Проверяем авторизацию через новую систему
require_once('../auth/includes/config.php');
require_once('../auth/includes/auth-functions.php');

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

// Проверяем, есть ли у пользователя доступ к цеху U5
$db = Database::getInstance();
$userDepartments = $db->select("
    SELECT ud.department_code, ud.is_active
    FROM auth_user_departments ud
    WHERE ud.user_id = ? AND ud.department_code = 'U5' AND ud.is_active = 1
", [$session['user_id']]);

if (empty($userDepartments)) {
    die('У вас нет доступа к цеху U5');
}

// Подключение к базе данных
function pdo_u5(): PDO {
    return new PDO(
        "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

$today = date('Y-m-d');

// Обработка POST запроса для сохранения расчетов
if ($_POST && isset($_POST['save_calculation'])) {
    try {
        $pdo = pdo_u5();
        
        $calculation_date = $_POST['calculation_date'] ?? $today;
        $calculation_time = $_POST['calculation_time'] ?? '';
        $workers_count = intval($_POST['workers_count'] ?? 0);
        
        // Получаем данные о выпущенной продукции
        $production_data = json_decode($_POST['production_data'] ?? '[]', true);
        
        if (empty($production_data)) {
            throw new Exception('Нет данных о выпущенной продукции');
        }
        
        $pdo->beginTransaction();
        
        // Создаем запись расчета
        $stmt = $pdo->prepare("
            INSERT INTO calculation_percent 
            (calculation_date, calculation_time, workers_count, created_at, created_by)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$calculation_date, $calculation_time, $workers_count, $session['user_id']]);
        $calculation_id = $pdo->lastInsertId();
        
        // Сохраняем детали расчета
        foreach ($production_data as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO calculation_percent_details 
                (calculation_id, filter_name, order_number, quantity)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $calculation_id,
                $item['filter_name'],
                $item['order_number'],
                intval($item['quantity'])
            ]);
        }
        
        $pdo->commit();
        
        // Успех - показываем сообщение и перенаправляем
        echo "<script>
            alert('Расчет % успешно сохранен!');
            window.location.href = 'calculation_percent.php';
        </script>";
        exit;
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $error = 'Ошибка при сохранении: ' . $e->getMessage();
    }
}

// Создаем таблицы если их нет
try {
    $pdo = pdo_u5();
    
    // Создаем таблицу расчетов процентов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS calculation_percent (
            id INT AUTO_INCREMENT PRIMARY KEY,
            calculation_date DATE NOT NULL,
            calculation_time VARCHAR(5) NOT NULL,
            workers_count INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT NOT NULL
        )
    ");
    
    // Создаем таблицу деталей расчетов
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS calculation_percent_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            calculation_id INT NOT NULL,
            filter_name VARCHAR(255) NOT NULL,
            order_number VARCHAR(50) NOT NULL,
            quantity INT NOT NULL,
            FOREIGN KEY (calculation_id) REFERENCES calculation_percent(id) ON DELETE CASCADE
        )
    ");
    
} catch (Exception $e) {
    // Игнорируем ошибки создания таблиц
}

// Получаем выпущенную продукцию за сегодня
$production_items = [];
try {
    $pdo = pdo_u5();
    
    // Получаем данные о выпущенной продукции за сегодня
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            po.filter_name,
            po.order_number,
            SUM(po.quantity) as total_quantity
        FROM product_output po
        WHERE po.production_date = ?
        GROUP BY po.filter_name, po.order_number
        ORDER BY po.filter_name
    ");
    $stmt->execute([$today]);
    $production_items = $stmt->fetchAll();
    
} catch (Exception $e) {
    // Игнорируем ошибки
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Расчет %</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .time-input {
            width: 80px;
        }
        .workers-input {
            width: 60px;
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
<div class="max-w-4xl mx-auto bg-white rounded-xl shadow-md p-6 space-y-6">
    <h1 class="text-2xl font-bold text-center">Расчет %</h1>
    
    <?php if (isset($error)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="post" id="calculationForm">
        <input type="hidden" name="save_calculation" value="1">
        <input type="hidden" name="production_data" id="productionDataInput">
        
        <!-- Параметры расчета -->
        <div class="bg-blue-50 p-4 rounded-lg space-y-4">
            <h2 class="text-lg font-semibold">Параметры расчета</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Дата расчета</label>
                    <input type="date" name="calculation_date" value="<?= htmlspecialchars($today) ?>" 
                           class="w-full border rounded px-3 py-2" required>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Время расчета (часы:минуты)</label>
                    <select name="calculation_time" class="border rounded px-3 py-2 time-input" required>
                        <option value="">Выберите время</option>
                        <?php for ($hour = 0; $hour < 24; $hour++): ?>
                            <option value="<?= sprintf('%02d:00', $hour) ?>"><?= sprintf('%02d:00', $hour) ?></option>
                            <option value="<?= sprintf('%02d:30', $hour) ?>"><?= sprintf('%02d:30', $hour) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Количество людей</label>
                    <input type="number" name="workers_count" min="1" max="50" 
                           class="border rounded px-3 py-2 workers-input" required>
                </div>
            </div>
        </div>

        <!-- Список выпущенной продукции -->
        <div class="bg-gray-50 p-4 rounded-lg">
            <h2 class="text-lg font-semibold mb-4">Выпущенная продукция за сегодня (<?= htmlspecialchars($today) ?>)</h2>
            
            <?php if (empty($production_items)): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>Нет данных о выпущенной продукции за сегодня</p>
                    <p class="text-sm mt-2">
                        <a href="product_output.php" class="text-blue-600 hover:underline">Перейти к внесению продукции</a>
                    </p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full table-auto text-sm border">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="border px-3 py-2 text-left">Наименование фильтра</th>
                                <th class="border px-3 py-2 text-left">Заявка</th>
                                <th class="border px-3 py-2 text-center">Количество (шт)</th>
                                <th class="border px-3 py-2 text-center">Включить в расчет</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($production_items as $item): ?>
                            <tr class="production-row" data-filter="<?= htmlspecialchars($item['filter_name']) ?>" 
                                data-order="<?= htmlspecialchars($item['order_number']) ?>">
                                <td class="border px-3 py-2"><?= htmlspecialchars($item['filter_name']) ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($item['order_number']) ?></td>
                                <td class="border px-3 py-2 text-center quantity-cell">
                                    <?= htmlspecialchars($item['total_quantity']) ?>
                                </td>
                                <td class="border px-3 py-2 text-center">
                                    <input type="checkbox" class="include-checkbox" checked>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 text-right">
                    <span class="text-sm text-gray-600">Всего позиций: </span>
                    <span id="totalPositions" class="font-semibold"><?= count($production_items) ?></span>
                    <span class="ml-4">Включено в расчет: </span>
                    <span id="includedPositions" class="font-semibold"><?= count($production_items) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Кнопки -->
        <div class="flex justify-center space-x-4 pt-6">
            <button type="button" onclick="selectAll()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                Выбрать все
            </button>
            <button type="button" onclick="selectNone()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                Убрать все
            </button>
            <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                Сохранить расчет
            </button>
        </div>
    </form>
</div>

<script>
    // Обновление счетчиков
    function updateCounters() {
        const totalCheckboxes = document.querySelectorAll('.include-checkbox').length;
        const checkedCheckboxes = document.querySelectorAll('.include-checkbox:checked').length;
        
        document.getElementById('totalPositions').textContent = totalCheckboxes;
        document.getElementById('includedPositions').textContent = checkedCheckboxes;
    }

    // Выбрать все
    function selectAll() {
        document.querySelectorAll('.include-checkbox').forEach(checkbox => {
            checkbox.checked = true;
        });
        updateCounters();
    }

    // Убрать все
    function selectNone() {
        document.querySelectorAll('.include-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateCounters();
    }

    // Отслеживание изменений чекбоксов
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('include-checkbox')) {
            updateCounters();
        }
    });

    // Подготовка данных перед отправкой формы
    document.getElementById('calculationForm').addEventListener('submit', function(e) {
        const productionData = [];
        
        document.querySelectorAll('.production-row').forEach(row => {
            const checkbox = row.querySelector('.include-checkbox');
            if (checkbox && checkbox.checked) {
                productionData.push({
                    filter_name: row.dataset.filter,
                    order_number: row.dataset.order,
                    quantity: row.querySelector('.quantity-cell').textContent.trim()
                });
            }
        });
        
        if (productionData.length === 0) {
            e.preventDefault();
            alert('Выберите хотя бы одну позицию для расчета');
            return;
        }
        
        document.getElementById('productionDataInput').value = JSON.stringify(productionData);
    });

    // Инициализация счетчиков
    updateCounters();
</script>
</body>
</html>





