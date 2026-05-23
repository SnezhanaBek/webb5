<?php
require_once 'config.php';

// Проверяем и создаём таблицы, если их нет
ensureTablesExist();

session_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: text/html; charset=UTF-8');

$pdo = getPDO();

$isLoggedIn = isset($_SESSION['user_id']);
$userData = null;
$defaultLanguages = [];

if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    
    if ($userData) {
        $stmtLang = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
        $stmtLang->execute([$userData['id']]);
        $defaultLanguages = $stmtLang->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Функция для сохранения данных в Cookies на год
function saveToCookie($name, $value) {
    setcookie($name, $value, time() + 365 * 24 * 60 * 60, '/');
}

$defaultValues = [];
if (!$isLoggedIn) {
    $defaultValues['fio'] = $_COOKIE['fio_value'] ?? '';
    $defaultValues['phone'] = $_COOKIE['phone_value'] ?? '';
    $defaultValues['email'] = $_COOKIE['email_value'] ?? '';
    $defaultValues['birth_date'] = $_COOKIE['birth_date_value'] ?? '';
    $defaultValues['gender'] = $_COOKIE['gender_value'] ?? '';
    $defaultValues['biography'] = $_COOKIE['biography_value'] ?? '';
    $defaultValues['contract'] = $_COOKIE['contract_value'] ?? '';
    $defaultLanguages = json_decode($_COOKIE['languages_value'] ?? '[]', true);
} else if ($userData) {
    $defaultValues['fio'] = $userData['fio'];
    $defaultValues['phone'] = $userData['phone'];
    $defaultValues['email'] = $userData['email'];
    $defaultValues['birth_date'] = $userData['birth_date'];
    $defaultValues['gender'] = $userData['gender'];
    $defaultValues['biography'] = $userData['biography'];
    $defaultValues['contract'] = $userData['contract_agreed'];
}

$errors = $_SESSION['errors'] ?? [];
$success = isset($_GET['success']);
$generatedLogin = $_SESSION['generated_login'] ?? null;
$generatedPass = $_SESSION['generated_pass'] ?? null;
unset($_SESSION['errors']);
unset($_SESSION['generated_login']);
unset($_SESSION['generated_pass']);

$languagesList = $pdo->query("SELECT * FROM programming_languages ORDER BY id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета — Задание 5 (авторизация)</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #2c3e50; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: #2c3e50; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; }
        .error-field { border: 2px solid red !important; background: #fff0f0; }
        .radio-group { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        .radio-group label { display: inline-flex; align-items: center; gap: 6px; font-weight: normal; }
        .radio-group input { width: auto; }
        select[multiple] { height: 140px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
        button { background: #3498db; color: white; border: none; padding: 12px; border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; font-weight: bold; }
        button:hover { background: #2980b9; }
        .success-message { background: #e0ffe8; color: #2a6e3b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2a6e3b; }
        .error-message { background: #fee; color: #c00; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c00; }
        .auth-bar { background: #e8f4fd; padding: 10px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .auth-bar a { color: #3498db; text-decoration: none; }
        .login-info { font-weight: bold; color: #2c3e50; }
        .credentials-box { background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .note { text-align: center; color: gray; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Регистрационная анкета — Задание 5</h1>
    
    <div class="auth-bar">
        <?php if ($isLoggedIn): ?>
            <span class="login-info">✅ Вы вошли как <strong><?php echo htmlspecialchars($_SESSION['login'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
            <a href="logout.php">Выйти</a>
        <?php else: ?>
            <span>🔒 Вы не авторизованы</span>
            <a href="login.php">Войти</a>
        <?php endif; ?>
    </div>
    
    <?php if ($generatedLogin && $generatedPass): ?>
        <div class="credentials-box">
            <strong>✅ Данные успешно сохранены!</strong><br>
            Ваш логин: <strong><?php echo htmlspecialchars($generatedLogin, ENT_QUOTES, 'UTF-8'); ?></strong><br>
            Ваш пароль: <strong><?php echo htmlspecialchars($generatedPass, ENT_QUOTES, 'UTF-8'); ?></strong><br>
            <span style="color: #856404;">⚠️ Сохраните эти данные для входа! Пароль показывается только один раз.</span>
        </div>
    <?php elseif ($success): ?>
        <div class="success-message">✅ Данные успешно обновлены!</div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <strong>⚠️ Пожалуйста, исправьте ошибки:</strong>
            <ul class="error-list" style="margin:0;padding-left:20px;">
                <?php foreach ($errors as $error): ?>
                    <li>• <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="note">(Все поля, отмеченные *, обязательны для заполнения)</div>

    <form action="save.php" method="POST">
        <div class="form-group">
            <label>1. ФИО *</label>
            <input type="text" name="fio" value="<?php echo htmlspecialchars($defaultValues['fio'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group">
            <label>2. Телефон *</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($defaultValues['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="+7 (999) 123-45-67">
        </div>

        <div class="form-group">
            <label>3. E-mail *</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($defaultValues['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="example@mail.ru">
        </div>

        <div class="form-group">
            <label>4. Дата рождения *</label>
            <input type="date" name="birth_date" value="<?php echo htmlspecialchars($defaultValues['birth_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group">
            <label>5. Пол *</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?php echo ($defaultValues['gender'] ?? '') == 'male' ? 'checked' : ''; ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?php echo ($defaultValues['gender'] ?? '') == 'female' ? 'checked' : ''; ?>> Женский</label>
                <label><input type="radio" name="gender" value="other" <?php echo ($defaultValues['gender'] ?? '') == 'other' ? 'checked' : ''; ?>> Другой</label>
            </div>
        </div>

        <div class="form-group">
            <label>6. Любимые языки программирования *</label>
            <select name="languages[]" multiple>
                <?php foreach ($languagesList as $lang): ?>
                    <option value="<?php echo $lang['id']; ?>" <?php echo in_array($lang['id'], $defaultLanguages) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>7. Биография</label>
            <textarea name="biography" rows="5" placeholder="Расскажите о себе..."><?php echo htmlspecialchars($defaultValues['biography'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="contract" value="1" <?php echo ($defaultValues['contract'] ?? '') == '1' ? 'checked' : ''; ?>>
            <label>Я ознакомлен(а) с контрактом *</label>
        </div>

        <button type="submit">Сохранить</button>
    </form>
    
    <?php if (!$isLoggedIn): ?>
        <p style="margin-top: 20px; text-align: center;">
            <a href="login.php">🔐 Уже есть аккаунт? Войти</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>