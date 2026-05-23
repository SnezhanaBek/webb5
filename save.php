<?php
require_once 'config.php';

// Автоматически создаём таблицы, если их нет
initDatabaseIfNeeded();

session_start();
header('Content-Type: text/html; charset=UTF-8');

$pdo = getPDO();

function saveToCookie($name, $value) {
    setcookie($name, $value, time() + 365 * 24 * 60 * 60, '/');
}

$errors = [];

// Валидация
$fio = trim($_POST['fio'] ?? '');
if (empty($fio)) { $errors[] = 'ФИО обязательно'; }
elseif (strlen($fio) > 150) { $errors[] = 'ФИО не длиннее 150 символов'; }
elseif (!preg_match('/^[A-Za-zА-Яа-яЁё\s]+$/u', $fio)) { $errors[] = 'Только буквы и пробелы'; }
else { saveToCookie('fio_value', $fio); }

$phone = trim($_POST['phone'] ?? '');
if (empty($phone)) { $errors[] = 'Телефон обязателен'; }
elseif (!preg_match('/^[\+\(\)\d\s-]{10,20}$/', $phone)) { $errors[] = 'Неверный формат телефона'; }
else { saveToCookie('phone_value', $phone); }

$email = trim($_POST['email'] ?? '');
if (empty($email)) { $errors[] = 'Email обязателен'; }
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Неверный формат email'; }
else { saveToCookie('email_value', $email); }

$birth_date = $_POST['birth_date'] ?? '';
if (empty($birth_date)) { $errors[] = 'Дата рождения обязательна'; }
elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) { $errors[] = 'Неверный формат даты'; }
else { saveToCookie('birth_date_value', $birth_date); }

$gender = $_POST['gender'] ?? '';
if (!in_array($gender, ['male', 'female', 'other'])) { $errors[] = 'Выберите пол'; }
else { saveToCookie('gender_value', $gender); }

$languages = $_POST['languages'] ?? [];
if (empty($languages)) { $errors[] = 'Выберите хотя бы один язык'; }
else { saveToCookie('languages_value', json_encode($languages)); }

$biography = trim($_POST['biography'] ?? '');
saveToCookie('biography_value', $biography);

$contract = isset($_POST['contract']) && $_POST['contract'] == 1 ? 1 : 0;
if ($contract != 1) { $errors[] = 'Подтвердите согласие с контрактом'; }
else { saveToCookie('contract_value', $contract); }

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header('Location: index.php');
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$isNewUser = false;
$generatedLogin = null;
$generatedPass = null;

if (!$isLoggedIn) {
    $isNewUser = true;
    $generatedLogin = 'user_' . bin2hex(random_bytes(4));
    $generatedPass = bin2hex(random_bytes(4));
    $passwordHash = password_hash($generatedPass, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (login, password_hash) VALUES (?, ?)");
    $stmt->execute([$generatedLogin, $passwordHash]);
    $userId = $pdo->lastInsertId();
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['login'] = $generatedLogin;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $existingApp = $stmt->fetch();
    
    if ($existingApp) {
        $stmt = $pdo->prepare("UPDATE applications SET fio=?, phone=?, email=?, birth_date=?, gender=?, biography=?, contract_agreed=? WHERE id=?");
        $stmt->execute([$fio, $phone, $email, $birth_date, $gender, $biography, $contract, $existingApp['id']]);
        $application_id = $existingApp['id'];
        
        $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id=?");
        $stmt->execute([$application_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO applications (user_id, fio, phone, email, birth_date, gender, biography, contract_agreed) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $fio, $phone, $email, $birth_date, $gender, $biography, $contract]);
        $application_id = $pdo->lastInsertId();
    }
    
    $stmtLang = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
    foreach ($languages as $lang_id) {
        $stmtLang->execute([$application_id, $lang_id]);
    }
    
    $pdo->commit();
    
    if ($isNewUser) {
        $_SESSION['generated_login'] = $generatedLogin;
        $_SESSION['generated_pass'] = $generatedPass;
        header('Location: index.php');
    } else {
        header('Location: index.php?success=1');
    }
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['errors'] = ['Ошибка сохранения: ' . $e->getMessage()];
    header('Location: index.php');
    exit;
}
?>