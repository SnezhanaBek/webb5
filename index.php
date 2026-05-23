<?php
require_once 'config.php';

// ========== ПРИНУДИТЕЛЬНОЕ ПЕРЕСОЗДАНИЕ ТАБЛИЦ ==========
try {
    $pdo = getPDO();
    
    // Удаляем старые таблицы
    $pdo->exec("DROP TABLE IF EXISTS application_languages");
    $pdo->exec("DROP TABLE IF EXISTS applications");
    $pdo->exec("DROP TABLE IF EXISTS users");
    $pdo->exec("DROP TABLE IF EXISTS programming_languages");
    
    // Создаём новые
    $pdo->exec("CREATE TABLE programming_languages (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(50) NOT NULL UNIQUE,
        PRIMARY KEY (id)
    )");
    
    $pdo->exec("INSERT INTO programming_languages (name) VALUES 
        ('Pascal'),('C'),('C++'),('JavaScript'),('PHP'),('Python'),
        ('Java'),('Haskell'),('Clojure'),('Prolog'),('Scala'),('Go')");
    
    $pdo->exec("CREATE TABLE users (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        login VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    )");
    
    $pdo->exec("CREATE TABLE applications (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT(10) UNSIGNED NOT NULL,
        fio VARCHAR(150) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(100) NOT NULL,
        birth_date DATE NOT NULL,
        gender ENUM('male','female','other') NOT NULL,
        biography TEXT,
        contract_agreed TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    $pdo->exec("CREATE TABLE application_languages (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        application_id INT(10) UNSIGNED NOT NULL,
        language_id INT(10) UNSIGNED NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
        FOREIGN KEY (language_id) REFERENCES programming_languages(id) ON DELETE CASCADE
    )");
    
} catch (PDOException $e) {
    die("Ошибка создания таблиц: " . $e->getMessage());
}
// ========================================================

session_start();
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
unset($_SESSION['errors'], $_SESSION['generated_login'], $_SESSION['generated_pass']);

$languagesList = $pdo->query("SELECT * FROM programming_languages ORDER BY id")->fetchAll();
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
        .radio-group { display: flex; gap: 20px; align-items: center; }
        .radio-group label { display: inline-flex; align-items: center; gap: 6px; font-weight: normal; }
        .radio-group input { width: auto; }
        select[multiple] { height: 140px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
        button { background: #3498db; color: white; border: none; padding: 12px; border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; font-weight: bold; }
        button:hover { background: #2980b9; }
        .success-message { background: #e0ffe8; color: #2a6e3b; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .error-message { background: #fee; color: #c00; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .auth-bar { background: #e8f4fd; padding: 10px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .auth-bar a { color: #3498db; text-decoration: none; }
        .credentials-box { background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .note { text-align: center; color: gray; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Регистрационная анкета — Задание 5</h1>
    
    <div class="auth-bar">
        <?php if ($isLoggedIn): ?>
            <span>✅ Вы вошли как <strong><?php echo htmlspecialchars($_SESSION['login']); ?></strong></span>
            <a href="logout.php">Выйти</a>
        <?php else: ?>
            <span>🔒 Вы не авторизованы</span>
            <a href="login.php">Войти</a>
        <?php endif; ?>
    </div>
    
    <?php if ($generatedLogin && $generatedPass): ?>
        <div class="credentials-box">
            <strong>✅ Данные успешно сохранены!</strong><br>
            Ваш логин: <strong><?php echo htmlspecialchars($generatedLogin); ?></strong><br>
            Ваш пароль: <strong><?php echo htmlspecialchars($generatedPass); ?></strong><br>
            <span>Сохраните эти данные для входа!</span>
        </div>
    <?php elseif ($success): ?>
        <div class="success-message">✅ Данные успешно обновлены!</div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <?php foreach ($errors as $error): ?>
                <div>• <?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="note">Все поля, отмеченные *, обязательны</div>

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
        <input type="email" name="email" value="<?php echo htmlspecialchars($defaultValues['email'] ?? ''); ?>">
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
        <label>6. Языки программирования *</label>
        <select name="languages[]" multiple>
            <?php foreach ($languagesList as $lang): ?>
                <option value="<?php echo $lang['id']; ?>" <?php echo in_array($lang['id'], $defaultLanguages) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lang['name']); ?>
                </option>
            <?php endforeach; ?>
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
    
    <?php if (!$isLoggedIn): ?>
        <p style="margin-top: 20px; text-align: center;">
            <a href="login.php">🔐 Уже есть аккаунт? Войти</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>