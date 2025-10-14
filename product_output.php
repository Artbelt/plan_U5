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

// ========= DB =========
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

// ========= API: список фильтров по заявке =========
// GET ?filters=1&order=29-35-25  → ["Panther AirMax  T-Rex (без коробки)", ...]
if (isset($_GET['filters']) && isset($_GET['order'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo = pdo_u5();
        $order = trim($_GET['order']);
        $stmt = $pdo->prepare("
            SELECT DISTINCT `filter`
            FROM `orders`
            WHERE `order_number` = ?
              AND (`hide` IS NULL OR `hide` = 0)
            ORDER BY `filter`
        ");
        $stmt->execute([$order]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

// ========= API: строгая проверка "фильтр есть в этой заявке" =========
// GET ?check=1&order=29-35-25&filter=...
if (isset($_GET['check'])) {
    header('Content-Type: application/json; charset=utf-8');
    $order  = trim($_GET['order']  ?? '');
    $filter = $_GET['filter'] ?? ''; // берём как есть (без trim), чтобы не терять пробелы
    if ($order === '' || $filter === '') { echo json_encode(['exists'=>false]); exit; }
    try {
        $pdo = pdo_u5();
        $stmt = $pdo->prepare("
            SELECT 1
            FROM `orders`
            WHERE BINARY `order_number` = BINARY ?
              AND BINARY `filter`       = BINARY ?
              AND (`hide` IS NULL OR `hide` = 0)
            LIMIT 1
        ");
        $stmt->execute([$order, $filter]);
        echo json_encode(['exists' => (bool)$stmt->fetchColumn()]);
    } catch (Throwable $e) {
        echo json_encode(['exists' => false]);
    }
    exit;
}

// ========= API: сохранение смены =========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo = pdo_u5();
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        $date     = $data['date']     ?? null;
        $brigade  = $data['brigade']  ?? null;
        $products = $data['products'] ?? [];

        if (!$date || !$brigade || !is_array($products) || count($products) === 0) {
            echo json_encode(['status'=>'error','message'=>'Пустые данные']); exit;
        }

        // Строгая серверная валидация каждой пары (order, filter)
        $chk = $pdo->prepare("
            SELECT 1 FROM `orders`
            WHERE BINARY `order_number` = BINARY ?
              AND BINARY `filter`       = BINARY ?
              AND (`hide` IS NULL OR `hide` = 0)
            LIMIT 1
        ");
        $invalid = [];
        foreach ($products as $p) {
            // НЕ трогаем пробелы в name/order_number
            $name  = array_key_exists('name', $p) ? (string)$p['name'] : '';
            $order = array_key_exists('order_number', $p) ? (string)$p['order_number'] : '';
            if ($name === '' || $order === '') { $invalid[] = $p; continue; }
            $chk->execute([$order, $name]);
            if (!$chk->fetchColumn()) {
                $invalid[] = ['name'=>$name,'order_number'=>$order];
            }
        }
        if ($invalid) {
            echo json_encode(['status'=>'error','code'=>'INVALID_ITEMS','invalid'=>$invalid]); exit;
        }

        // Сохранение
        $ins = $pdo->prepare("
            INSERT INTO `manufactured_production`
            (`date_of_production`, `name_of_filter`, `count_of_filters`, `name_of_order`, `team`)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($products as $p) {
            $ins->execute([
                $date,
                (string)$p['name'],
                (int)$p['produced'],
                (string)$p['order_number'],
                (int)$brigade,
            ]);
        }

        echo json_encode(['status'=>'ok']);
    } catch (Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

// ========= Данные для формы =========
try {
    $pdo = pdo_u5();
    $orders = $pdo->query("
        SELECT DISTINCT `order_number`
        FROM `orders`
        WHERE (`hide` IS NULL OR `hide` = 0)
        ORDER BY `order_number`
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $orders = [];
}
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сменная продукция</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="bg-gray-100 p-4">
<div class="max-w-2xl mx-auto bg-white rounded-xl shadow-md p-4 space-y-4">
    <h1 class="text-xl font-bold text-center">Сменная продукция</h1>

    <div>
        <label class="block text-sm font-medium">Дата производства</label>
        <input type="date" id="prodDate" class="w-full border rounded px-3 py-2" value="<?= htmlspecialchars($today) ?>">
    </div>

    <div class="flex justify-between items-end mt-2 flex-wrap gap-2">
        <div>
            <label class="block text-sm font-medium">Бригада</label>
            <div class="flex gap-4 mt-2">
                <label class="flex items-center gap-1"><input type="radio" name="brigade" value="1" checked> 1</label>
                <label class="flex items-center gap-1"><input type="radio" name="brigade" value="2"> 2</label>
                <label class="flex items-center gap-1"><input type="radio" name="brigade" value="3"> 3</label>
                <label class="flex items-center gap-1"><input type="radio" name="brigade" value="4"> 4</label>                
            </div>
        </div>
        <div class="text-right">
            <span class="text-sm text-gray-500">Всего изготовлено:</span><br>
            <span id="totalCount" class="text-lg font-semibold">0 шт</span>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full table-auto text-sm border mt-4">
            <thead class="bg-gray-200">
            <tr>
                <th class="border px-2 py-1">Наименование</th>
                <th class="border px-2 py-1">Заявка</th>
                <th class="border px-2 py-1">Изготовлено</th>
                <th class="border px-2 py-1">!</th>
                <th class="border px-2 py-1">Удалить</th>
            </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
    </div>

    <div class="space-y-2">
        <button onclick="openModal()" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
            Добавить изделие
        </button>
        <button onclick="submitForm()" class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700">
            Сохранить смену
        </button>
    </div>
</div>

<!-- MODAL -->
<div id="modal" class="fixed inset-0 bg-black bg-opacity-40 z-50 overflow-y-auto hidden">
    <div class="flex justify-center items-start min-h-screen pt-12 px-4">
        <div class="bg-white p-6 rounded shadow w-full max-w-sm">
            <h2 class="text-lg font-semibold mb-4">Добавить изделие</h2>

            <!-- Номер заявки -->
            <label class="block text-sm">Номер заявки</label>
            <select id="modalOrder" class="w-full border px-3 py-2 rounded mb-2">
                <option value="">-- Выберите заявку --</option>
                <?php foreach ($orders as $order): ?>
                    <option value="<?= htmlspecialchars($order) ?>"><?= htmlspecialchars($order) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Наименование (строго из выбранной заявки) -->
            <label class="block text-sm">Наименование</label>
            <select id="modalName" class="w-full border px-3 py-2 rounded mb-2" disabled>
                <option value="">Сначала выберите заявку</option>
            </select>

            <!-- Количество -->
            <label class="block text-sm">Изготовлено</label>
            <input type="number" id="modalCount" class="w-full border px-3 py-2 rounded mb-4" placeholder="150" min="1">

            <div class="flex justify-end gap-2">
                <button onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded">Отмена</button>
                <button onclick="addProduct()" class="px-4 py-2 bg-blue-500 text-white rounded">Добавить</button>
            </div>
        </div>
    </div>
</div>

<script>
    function escapeHtml(s){
        return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function statusCell(content, extraClass='') {
        return `<span class="inline-flex items-center gap-1 ${extraClass}">${content}</span>`;
    }

    function openModal() {
        document.getElementById('modal').classList.remove('hidden');
        const order = document.getElementById('modalOrder').value;
        loadFiltersForOrder(order);
        setTimeout(()=>document.getElementById('modalOrder')?.focus(), 50);
    }

    function closeModal() {
        document.getElementById('modal').classList.add('hidden');
        document.getElementById('modalOrder').value = '';
        const sel = document.getElementById('modalName');
        sel.innerHTML = '<option value="">Сначала выберите заявку</option>';
        sel.disabled = true;
        document.getElementById('modalCount').value = '';
    }

    // Подгружаем точные строки filter для выбранной заявки
    async function loadFiltersForOrder(order){
        const sel = document.getElementById('modalName');
        sel.innerHTML=''; sel.disabled = true;
        if(!order){
            sel.innerHTML = '<option value="">Сначала выберите заявку</option>';
            return;
        }
        try{
            const res = await fetch(`?filters=1&order=${encodeURIComponent(order)}`);
            const arr = await res.json();
            if(!arr || arr.length===0){
                sel.innerHTML = '<option value="">В этой заявке нет позиций</option>';
                return;
            }
            for(const f of arr){
                const o = document.createElement('option');
                o.value = f; o.textContent = f;
                sel.appendChild(o);
            }
            sel.disabled = false;
        }catch(e){
            sel.innerHTML = '<option value="">Ошибка загрузки</option>';
        }
    }
    document.getElementById('modalOrder').addEventListener('change', e=>{
        loadFiltersForOrder(e.target.value);
    });

    function addProduct(){
        const order = document.getElementById('modalOrder').value;
        const name  = document.getElementById('modalName').value; // берём как есть, без trim!
        const count = document.getElementById('modalCount').value.trim();

        if(!order || !name || !count){ alert('Заполните все поля!'); return; }
        if(parseInt(count) <= 0){ alert('Количество должно быть > 0'); return; }

        const row = document.createElement('tr');
        row.setAttribute('data-valid','pending'); // pending|1|0

        // Храним точные значения (включая двойные пробелы и пр.)
        row.dataset.name  = name;
        row.dataset.order = order;

        row.innerHTML = `
    <td class="border px-2 py-1 whitespace-pre" data-col="name">${escapeHtml(name)}</td>
    <td class="border px-2 py-1"               data-col="order">${escapeHtml(order)}</td>
    <td class="border px-2 py-1"               data-col="count">${escapeHtml(count)}</td>
    <td class="border px-2 py-1 text-center"   data-status>${statusCell('Проверка…','text-gray-500')}</td>
    <td class="border px-2 py-1 text-center">
      <button onclick="this.closest('tr').remove(); updateTotalCount();" class="text-red-500">✖</button>
    </td>`;
        document.getElementById('tableBody').appendChild(row);
        closeModal();
        updateTotalCount();
        validateRow(row);
    }

    async function validateRow(row){
        const name  = row.dataset.name;   // точная строка
        const order = row.dataset.order;  // точная строка
        const cell  = row.querySelector('[data-status]');

        try{
            const res = await fetch(`?check=1&order=${encodeURIComponent(order)}&filter=${encodeURIComponent(name)}`);
            const js = await res.json();
            if(js && js.exists){
                row.setAttribute('data-valid','1');
                cell.innerHTML = statusCell('✅','text-green-600 font-semibold');
            }else{
                row.setAttribute('data-valid','0');
                cell.innerHTML = statusCell('❗ Нет в заявке','text-red-600 font-semibold');
            }
        }catch(e){
            row.setAttribute('data-valid','0');
            cell.innerHTML = statusCell('❗ Ошибка проверки','text-red-600 font-semibold');
        }
    }

    async function submitForm(){
        const date = document.getElementById('prodDate').value;
        const brigade = document.querySelector('input[name="brigade"]:checked').value;
        const rows = Array.from(document.querySelectorAll('#tableBody tr'));

        if(!date || rows.length===0){ alert('Заполните дату и добавьте хотя бы одно изделие'); return; }

        // Запрет сохранять при несоответствиях
        const bad = rows.filter(r => r.getAttribute('data-valid') !== '1');
        if(bad.length){
            alert('Есть позиции, которых нет в выбранных заявках. Исправьте или удалите.');
            return;
        }

        const products = rows.map(row => ({
            name:         row.dataset.name,          // точные строки
            order_number: row.dataset.order,
            produced:     parseInt(row.querySelector('[data-col="count"]').textContent)
        }));

        const res = await fetch(window.location.href, {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({date, brigade: parseInt(brigade), products})
        });
        const js = await res.json();
        if(js.status==='ok'){
            alert('Смена успешно сохранена');
            location.reload();
        }else if(js.code==='INVALID_ITEMS'){
            const inv = new Set((js.invalid||[]).map(x=>`${x.order_number}||${x.name}`));
            document.querySelectorAll('#tableBody tr').forEach(row=>{
                const k = `${row.dataset.order}||${row.dataset.name}`;
                if(inv.has(k)){
                    row.setAttribute('data-valid','0');
                    row.querySelector('[data-status]').innerHTML = statusCell('❗ Нет в заявке','text-red-600 font-semibold');
                }
            });
            alert('Сервер отклонил сохранение: есть позиции, которых нет в заявках.');
        }else{
            alert('Ошибка при сохранении: ' + (js.message || 'неизвестно'));
        }
    }

    function updateTotalCount(){
        let total = 0;
        document.querySelectorAll('#tableBody tr').forEach(row=>{
            const n = parseInt(row.querySelector('[data-col="count"]').textContent || '0');
            if(!isNaN(n)) total += n;
        });
        document.getElementById('totalCount').textContent = `${total} шт`;
    }

    // UX: Enter — внутри модалки добавляет, снаружи открывает
    document.addEventListener('keydown', function(e){
        if(e.key==='Enter'){
            const modal = document.getElementById('modal');
            const isVisible = !modal.classList.contains('hidden');
            if(isVisible){
                e.preventDefault();
                addProduct();
            }else{
                e.preventDefault();
                openModal();
            }
        }
    });
</script>
</body>
</html>
