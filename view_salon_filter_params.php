<?php
require_once('tools/tools.php');

/**
 * Ожидаем имя фильтра из GET/POST
 * Приоритет: GET name -> POST filter_name
 */
$filterName = '';
if (isset($_GET['name']) && $_GET['name'] !== '') {
    $filterName = trim($_GET['name']);
} elseif (isset($_POST['filter_name']) && $_POST['filter_name'] !== '') {
    $filterName = trim($_POST['filter_name']);
}

/**
 * Загружаем данные о фильтре, если имя задано
 * Ожидаемые ключи такие же, как в твоём исходнике:
 * paper_package_width, paper_package_height, paper_package_pleats_count,
 * paper_package_remark, paper_package_supplier, insertion_count, paper_package_material,
 * box, g_box, comment, foam_rubber, form_factor, tail, side_type, ...
 */
$data = null;
$error = null;

if ($filterName !== '') {
    try {
        $data = get_salon_filter_data($filterName); // должна вернуть ассоц. массив или пустой/false, если не найден
        if (!$data || !is_array($data)) {
            $error = 'Фильтр с таким именем не найден.';
            $data = null;
        }
    } catch (Throwable $e) {
        $error = 'Ошибка при получении данных: ' . $e->getMessage();
        $data = null;
    }
}

/** Утилита форматирования «Да/Нет» для чекбоксов */
function yn($v): string {
    // Поддержим варианты: 'on', '1', 1, true, 'checked', 'yes'
    $truthy = ['on','1',1,true,'checked','yes','да','Да','true','True'];
    return in_array($v, $truthy, true) ? 'Да' : 'Нет';
}

/** Безопасный вывод */
function h($s): string { return htmlspecialchars((string)$s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="ru" xmlns="http://www.w3.org/1999/html">
<head>
    <meta charset="utf-8" />
    <title>Просмотр фильтра<?= $filterName ? ' — ' . h($filterName) : '' ?></title>
    <style>
        :root {
            --bg: #f7f7fb;
            --card: #fff;
            --text: #222;
            --muted: #666;
            --line: #e7e7ef;
            --accent: #4c7cff;
            --green: #2e7d32;
            --red: #c62828;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
        }
        header {
            margin-bottom: 18px;
        }
        h1 {
            margin: 0 0 8px 0;
            font-size: 22px;
        }
        .muted { color: var(--muted); font-size: 14px; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 900px) {
            .row { grid-template-columns: 1fr; }
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .table th, .table td {
            border-bottom: 1px solid var(--line);
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
        }
        .table th { width: 32%; color: var(--muted); font-weight: 600; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid var(--line);
            background: #fafafa;
        }
        .yn-yes { color: var(--green); font-weight: 600; }
        .yn-no { color: var(--red); font-weight: 600; }
        form.search {
            display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
            margin-top: 6px;
        }
        .search input[type="text"] {
            flex: 1 1 320px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            outline: none;
        }
        .search button, .btn {
            padding: 10px 14px;
            border: 1px solid var(--accent);
            background: var(--accent);
            color: #fff;
            border-radius: 10px;
            cursor: pointer;
        }
        .btn-outline {
            background: transparent;
            color: var(--accent);
        }
        .error {
            padding: 10px 12px; border: 1px solid #ffd4d4; background: #fff3f3; border-radius: 10px; color: #b00020;
            margin-top: 8px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        @media (max-width: 900px) {
            .grid-3 { grid-template-columns: 1fr; }
        }
        .section-title { font-size: 16px; font-weight: 700; margin: 0 0 8px; }
        .small { font-size: 13px; color: var(--muted); }
        .hr { height: 1px; background: var(--line); margin: 12px 0; }
        .value-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .pair { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>Просмотр фильтра</h1>
        <div class="muted">Введите имя фильтра и нажмите «Показать».</div>
        <form class="search" method="get" action="">
            <input type="text" name="name" placeholder="Например: AF1593" value="<?= h($filterName) ?>" autofocus>
            <button type="submit">Показать</button>
            <?php if ($filterName): ?>
                <a class="btn btn-outline" href="?">Сброс</a>
            <?php endif; ?>
        </form>

        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>
    </header>

    <?php if ($data): ?>
        <div class="card">
            <div class="pair">
                <h2 style="margin:0; font-size:20px;"><?= h($filterName) ?></h2>
                <span class="badge">Категория: Салонный</span>
            </div>
            <div class="small" style="margin-top:6px;">Ниже собраны основные параметры фильтра и упаковки.</div>
        </div>

        <div class="row">
            <!-- Гофропакет -->
            <section class="card">
                <div class="section-title">Гофропакет</div>
                <table class="table">
                    <tr>
                        <th>Ширина шторы</th>
                        <td class="value-mono"><?= h($data['paper_package_width'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Высота шторы</th>
                        <td class="value-mono"><?= h($data['paper_package_height'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Кол-во рёбер</th>
                        <td class="value-mono"><?= h($data['paper_package_pleats_count'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Поставщик</th>
                        <td><?= h($data['paper_package_supplier'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Материал</th>
                        <td><?= h($data['paper_package_material'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Комментарий</th>
                        <td><?= h($data['paper_package_remark'] ?? '') ?></td>
                    </tr>
                </table>
            </section>

            <!-- Вставка / Боковая лента -->
            <section class="card">
                <div class="grid-3">
                    <div>
                        <div class="section-title">Вставка</div>
                        <table class="table">
                            <tr>
                                <th>Кол-во в фильтре</th>
                                <td class="value-mono"><?= h($data['insertion_count'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <th>Поставщик</th>
                                <td><?= h($data['insertion_supplier'] ?? ($data['paper_package_supplier'] ?? '')) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <div class="section-title">Боковая лента</div>
                        <table class="table">
                            <tr>
                                <th>Высота ленты</th>
                                <td class="value-mono"><?= h($data['side_type'] ?? '') ?></td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <div class="section-title">Особенности</div>
                        <table class="table">
                            <tr>
                                <th>Поролон</th>
                                <td><span class="<?= yn($data['foam_rubber'] ?? null) === 'Да' ? 'yn-yes' : 'yn-no' ?>"><?= yn($data['foam_rubber'] ?? null) ?></span></td>
                            </tr>
                            <tr>
                                <th>Язычок</th>
                                <td><span class="<?= yn($data['tail'] ?? null) === 'Да' ? 'yn-yes' : 'yn-no' ?>"><?= yn($data['tail'] ?? null) ?></span></td>
                            </tr>
                            <tr>
                                <th>Трапеция</th>
                                <td><span class="<?= yn($data['form_factor'] ?? null) === 'Да' ? 'yn-yes' : 'yn-no' ?>"><?= yn($data['form_factor'] ?? null) ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <section class="row">
            <!-- Упаковка -->
            <div class="card">
                <div class="section-title">Упаковка</div>
                <table class="table">
                    <tr>
                        <th>Индивидуальная упак. (Коробка №)</th>
                        <td class="value-mono"><?= h($data['box'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Групповая упак. (Ящик №)</th>
                        <td class="value-mono"><?= h($data['g_box'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>Примечание</th>
                        <td><?= h($data['comment'] ?? '') ?></td>
                    </tr>
                </table>
            </div>
        </section>

        <div class="card">
            <div class="small">Источник данных: <span class="value-mono">get_salon_filter_data("<?= h($filterName) ?>")</span></div>
        </div>
    <?php elseif ($filterName === '' && !$error): ?>
        <div class="card">
            <div class="small">
                Подсказка: укажи имя фильтра в поле выше (например, <span class="value-mono">AF1593</span>) и нажми «Показать».
                Эта страница служит только для просмотра и не изменяет данные.
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
