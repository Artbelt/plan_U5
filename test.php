<?php
// === АВТОДОПОЛНЕНИЕ ===
if (isset($_GET['q'])) {
    header('Content-Type: application/json');
    try {
        $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "");
        $query = $_GET['q'];
        $stmt = $pdo->prepare("SELECT DISTINCT filter FROM salon_filter_structure WHERE filter LIKE ? LIMIT 10");
        $stmt->execute(["%$query%"]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));

    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// === СОХРАНЕНИЕ В БД ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "");
    $data = json_decode(file_get_contents("php://input"), true);

    $date = $data['date'] ?? null;
    $team = $data['brigade'] ?? null;
    $products = $data['products'] ?? [];

    $stmt = $pdo->prepare("
        INSERT INTO manufactured_production 
        (date_of_production, name_of_filter, count_of_filters, name_of_order, team) 
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($products as $p) {
        $stmt->execute([
            $date,
            $p['name'],
            $p['produced'],
            $p['order_number'],
            $team
        ]);
    }

    echo json_encode(["status" => "ok"]);
    exit;
}

// === ПОЛУЧЕНИЕ СПИСКА ЗАЯВОК ===
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "");
    $orders = $pdo->query("SELECT DISTINCT order_number FROM orders WHERE hide IS NULL OR hide = 0")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $orders = [];
}
$today = date('Y-m-d');
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=plan;charset=utf8mb4", "root", "");
    $existing = $pdo->prepare("SELECT name_of_filter, name_of_order, count_of_filters FROM manufactured_production WHERE date_of_production = ?");
    $existing->execute([$today]);
    $existingProducts = $existing->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $existingProducts = [];
}
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
        <input type="date" id="prodDate" class="w-full border rounded px-3 py-2">
    </div>

    <div class="flex justify-between items-end mt-2 flex-wrap gap-2">
        <div>
            <label class="block text-sm font-medium">Бригада</label>
            <div class="flex gap-4 mt-2">
                <label><input type="radio" name="brigade" value="1" checked> 1</label>
                <label><input type="radio" name="brigade" value="2"> 2</label>
                <label><input type="radio" name="brigade" value="3"> 3</label>
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

<div id="modal" class="fixed inset-0 bg-black bg-opacity-40 z-50 overflow-y-auto hidden">
    <div class="flex justify-center items-start min-h-screen pt-12 px-4">
        <div class="bg-white p-6 rounded shadow w-full max-w-sm">
            <h2 class="text-lg font-semibold mb-4">Добавить изделие</h2>

            <!-- Наименование -->
            <label class="block text-sm">Наименование</label>
            <div class="relative mb-2">
                <input type="text" id="modalName" class="w-full border px-3 py-2 rounded" placeholder="AE5704" oninput="autocompleteFilter(this.value)">
                <ul id="filterSuggestions" class="absolute z-10 bg-white border w-full rounded shadow hidden max-h-48 overflow-y-auto"></ul>
            </div>

            <!-- Номер заявки -->
            <label class="block text-sm">Номер заявки</label>
            <select id="modalOrder" class="w-full border px-3 py-2 rounded mb-2">
                <option value="">-- Выберите заявку --</option>
                <?php foreach ($orders as $order): ?>
                    <option value="<?= htmlspecialchars($order) ?>"><?= htmlspecialchars($order) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Количество -->
            <label class="block text-sm">Изготовлено</label>
            <input type="number" id="modalCount" class="w-full border px-3 py-2 rounded mb-4" placeholder="150">

            <!-- Кнопки -->
            <div class="flex justify-end gap-2">
                <button onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded">Отмена</button>
                <button onclick="addProduct()" class="px-4 py-2 bg-blue-500 text-white rounded">Добавить</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openModal() {
        const modal = document.getElementById('modal');
        modal.classList.remove('hidden');

        // Установим фокус в поле "Наименование" через короткую задержку
        setTimeout(() => {
            document.getElementById('modalName')?.focus();
        }, 100); // небольшая задержка, чтобы DOM успел "развернуться"
    }

    function closeModal() {
        document.getElementById('modal').classList.add('hidden');
        document.getElementById('modalName').value = '';
        document.getElementById('modalOrder').value = '';
        document.getElementById('modalCount').value = '';
        document.getElementById('filterSuggestions').classList.add('hidden');
    }

    function addProduct() {
        const name = document.getElementById('modalName').value.trim();
        const order = document.getElementById('modalOrder').value.trim();
        const count = document.getElementById('modalCount').value.trim();

        if (!name || !order || !count) return alert('Заполните все поля!');

        const row = document.createElement('tr');
        row.innerHTML = `
        <td class="border px-2 py-1">${name}</td>
        <td class="border px-2 py-1">${order}</td>
        <td class="border px-2 py-1">${count}</td>
        <td class="border px-2 py-1 text-center">
          <button onclick="this.closest('tr').remove();  updateTotalCount();" class="text-red-500">✖</button>
        </td>
      `;
        document.getElementById('tableBody').appendChild(row);
        closeModal();
    }

    async function submitForm() {
        const date = document.getElementById('prodDate').value;
        const brigade = document.querySelector('input[name="brigade"]:checked').value;
        const products = [];

        document.querySelectorAll('#tableBody tr').forEach(row => {
            const cells = row.querySelectorAll('td');
            products.push({
                name: cells[0].innerText,
                order_number: cells[1].innerText,
                produced: parseInt(cells[2].innerText)
            });
        });

        if (!date || products.length === 0) {
            alert('Заполните дату и добавьте хотя бы одно изделие');
            return;
        }

        const payload = { date, brigade: parseInt(brigade), products };

        const res = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await res.json();
        if (result.status === 'ok') {
            alert('Смена успешно сохранена');
            location.reload();
        } else {
            alert('Ошибка при сохранении');
        }
    }

    async function autocompleteFilter(query) {
        const list = document.getElementById('filterSuggestions');
        list.innerHTML = '';
        if (query.length < 2) {
            list.classList.add('hidden');
            return;
        }

        try {
            const res = await fetch('?q=' + encodeURIComponent(query));
            const suggestions = await res.json();

            if (!suggestions.length) {
                const li = document.createElement('li');
                li.textContent = 'Нет совпадений';
                li.className = 'px-3 py-2 text-gray-400';
                list.appendChild(li);
                list.classList.remove('hidden');
                return;
            }

            suggestions.forEach(text => {
                const li = document.createElement('li');
                li.textContent = text;
                li.className = 'px-3 py-2 hover:bg-blue-100 cursor-pointer';
                li.onclick = () => {
                    document.getElementById('modalName').value = text;
                    list.classList.add('hidden');
                };
                list.appendChild(li);
            });

            list.classList.remove('hidden');
        } catch (err) {
            console.error('Ошибка запроса фильтров:', err);
        }
    }


    document.addEventListener('click', e => {
        const list = document.getElementById('filterSuggestions');
        if (!document.getElementById('modalName').contains(e.target)) {
            list.classList.add('hidden');
        }
    });
</script>
<script>
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const modal = document.getElementById('modal');
            const isVisible = !modal.classList.contains('hidden');

            if (isVisible) {
                // Enter внутри модального окна — добавить изделие
                e.preventDefault(); // чтобы не было случайных сабмитов форм
                addProduct();
                updateTotalCount();
            } else {
                // Enter вне модального окна — открыть модальное окно
                e.preventDefault();
                openModal();
            }
        }
    });
    function updateTotalCount() {
        let total = 0;
        document.querySelectorAll('#tableBody tr').forEach(row => {
            const count = parseInt(row.children[2]?.innerText || 0);
            total += isNaN(count) ? 0 : count;
        });
        document.getElementById('totalCount').textContent = `${total} шт`;
    }
</script>
</body>
</html>
