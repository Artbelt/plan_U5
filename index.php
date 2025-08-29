<?php
session_start();

// простая флеш-ошибка из сессии
$err = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// CSRF-токен
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Вход • U3</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #f3f4f6;            /* светлый фон */
            --card: #ffffff;          /* карточка */
            --text: #111827;          /* почти чёрный */
            --muted: #6b7280;         /* серый текст */
            --primary: #2563eb;       /* синий */
            --primary-hover: #1d4ed8; /* синий темнее */
            --danger-bg: #fee2e2;
            --danger-border: #fecaca;
            --danger-text: #b91c1c;
            --border: #d1d5db;
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: var(--text); }

        .auth-body {
            min-height: 100%;
            background: var(--bg);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .auth-card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 10px 40px rgba(0,0,0,.08);
        }

        .auth-title { margin: 0 0 6px; font-size: 24px; font-weight: 700; }
        .auth-subtitle { margin: 0 0 18px; color: var(--muted); }

        .alert {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .auth-form { display: grid; gap: 14px; }

        .field { display: grid; gap: 6px; }
        .field span { font-size: 14px; color: var(--muted); }
        .field input, .field select, .field textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            outline: none;
            transition: border .15s ease, box-shadow .15s ease;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,.25);
        }

        .btn-primary {
            margin-top: 6px;
            width: 100%;
            padding: 12px 14px;
            border: none;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: transform .04s ease, background .15s ease;
        }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-primary:active { transform: translateY(1px); }

        .auth-footer {
            margin-top: 18px;
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }


    </style>
</head>
<body class="auth-body">
<div class="auth-card">
    <h1 class="auth-title">Система U5</h1>
    <p class="auth-subtitle">Авторизация пользователя</p>

    <?php if ($err): ?>
        <div class="alert"><?= htmlspecialchars($err, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>

    <form class="auth-form" method="post" action="enter.php" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <label class="field">
            <span>Имя пользователя</span>
            <input name="user_name" type="text" required autofocus placeholder="Введите имя">
        </label>

        <label class="field">
            <span>Пароль</span>
            <input name="user_pass" type="password" required placeholder="Введите пароль">
        </label>

        <label class="field">
            <span>Подразделение</span>
            <select name="workshop" required>
                <option value="" selected disabled>Выберите подразделение</option>

                <option value="U5">U5</option>

            </select>
        </label>

        <button type="submit" class="btn-primary">Войти</button>
    </form>

    <div class="auth-footer">© <?php echo date('Y'); ?> AlphaFilter</div>
</div>
</body>
</html>
