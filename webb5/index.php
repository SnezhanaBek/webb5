<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

// Подключение к БД
$host = 'localhost';
$dbname = 'webb5_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

$isLoggedIn = isset($_SESSION['user_id']);
$userData = null;
$defaultLanguages = [];

// Если пользователь авторизован, загружаем его данные
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userData) {
        $stmtLang = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
        $stmtLang->execute([$userData['id']]);
        $defaultLanguages = $stmtLang->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Функция сохранения в Cookies
function saveToCookie($name, $value) {
    setcookie($name, $value, time() + 365 * 24 * 60 * 60, '/');
}

// Загружаем значения из Cookies (для неавторизованных)
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

// Загружаем ошибки из сессии
$errors = $_SESSION['errors'] ?? [];
$success = isset($_GET['success']);
$generatedLogin = $_SESSION['generated_login'] ?? null;
$generatedPass = $_SESSION['generated_pass'] ?? null;
unset($_SESSION['errors']);
unset($_SESSION['generated_login']);
unset($_SESSION['generated_pass']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета — Задание 5</title>
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
        .error-list { margin: 0; padding-left: 20px; }
        .note { text-align: center; color: gray; margin-bottom: 20px; font-size: 14px; }
        .auth-bar { background: #e8f4fd; padding: 10px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .auth-bar a { color: #3498db; text-decoration: none; }
        .login-info { font-weight: bold; color: #2c3e50; }
        .credentials-box { background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .credentials-box strong { color: #856404; }
    </style>
</head>
<body>
<div class="container">
    <h1>Регистрационная анкета — Задание 5</h1>
    
    <!-- Блок авторизации -->
    <div class="auth-bar">
        <?php if ($isLoggedIn): ?>
            <span class="login-info">✅ Вы вошли как <strong><?php echo htmlspecialchars($_SESSION['login']); ?></strong></span>
            <a href="logout.php">Выйти</a>
        <?php else: ?>
            <span>🔒 Вы не авторизованы</span>
            <a href="login.php">Войти</a>
        <?php endif; ?>
    </div>
    
    <!-- Сообщение с логином и паролем (при первой отправке) -->
    <?php if ($generatedLogin && $generatedPass): ?>
        <div class="credentials-box">
            <strong>✅ Данные успешно сохранены!</strong><br>
            Ваш логин: <strong><?php echo htmlspecialchars($generatedLogin); ?></strong><br>
            Ваш пароль: <strong><?php echo htmlspecialchars($generatedPass); ?></strong><br>
            <span style="color: #856404;">⚠️ Сохраните эти данные для входа! Пароль показывается только один раз.</span>
        </div>
    <?php elseif ($success): ?>
        <div class="success-message">✅ Данные успешно обновлены!</div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <strong>⚠️ Пожалуйста, исправьте ошибки:</strong>
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li>• <?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="note">(Все поля, отмеченные *, обязательны для заполнения)</div>

    <form action="save.php" method="POST">
        <div class="form-group">
            <label>1. ФИО *</label>
            <input type="text" name="fio" value="<?php echo htmlspecialchars($defaultValues['fio'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label>2. Телефон *</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($defaultValues['phone'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label>3. E-mail *</label>
            <input type="text" name="email" value="<?php echo htmlspecialchars($defaultValues['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label>4. Дата рождения *</label>
            <input type="date" name="birth_date" value="<?php echo htmlspecialchars($defaultValues['birth_date'] ?? ''); ?>">
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
                <option value="1" <?php echo in_array('1', $defaultLanguages) ? 'selected' : ''; ?>>Pascal</option>
                <option value="2" <?php echo in_array('2', $defaultLanguages) ? 'selected' : ''; ?>>C</option>
                <option value="3" <?php echo in_array('3', $defaultLanguages) ? 'selected' : ''; ?>>C++</option>
                <option value="4" <?php echo in_array('4', $defaultLanguages) ? 'selected' : ''; ?>>JavaScript</option>
                <option value="5" <?php echo in_array('5', $defaultLanguages) ? 'selected' : ''; ?>>PHP</option>
                <option value="6" <?php echo in_array('6', $defaultLanguages) ? 'selected' : ''; ?>>Python</option>
                <option value="7" <?php echo in_array('7', $defaultLanguages) ? 'selected' : ''; ?>>Java</option>
                <option value="8" <?php echo in_array('8', $defaultLanguages) ? 'selected' : ''; ?>>Haskell</option>
                <option value="9" <?php echo in_array('9', $defaultLanguages) ? 'selected' : ''; ?>>Clojure</option>
                <option value="10" <?php echo in_array('10', $defaultLanguages) ? 'selected' : ''; ?>>Prolog</option>
                <option value="11" <?php echo in_array('11', $defaultLanguages) ? 'selected' : ''; ?>>Scala</option>
                <option value="12" <?php echo in_array('12', $defaultLanguages) ? 'selected' : ''; ?>>Go</option>
            </select>
        </div>

        <div class="form-group">
            <label>7. Биография</label>
            <textarea name="biography" rows="5"><?php echo htmlspecialchars($defaultValues['biography'] ?? ''); ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="contract" value="1" <?php echo ($defaultValues['contract'] ?? '') == '1' ? 'checked' : ''; ?>>
            <label>Я ознакомлен(а) с контрактом *</label>
        </div>

        <button type="submit">Сохранить</button>
    </form>
</div>
</body>
</html>