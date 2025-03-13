<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $type = $_POST['type'];

    if ($type === 'junior') {
        $stmt = $pdo->prepare("SELECT * FROM juniors WHERE email = ?");
    } elseif ($type === 'company') {
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE email = ?");
    } elseif ($type === 'admin') {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
    } elseif ($type === 'mentor') {
        $stmt = $pdo->prepare("SELECT * FROM mentors WHERE email = ?");
    } else {
        header("Location: index.php?login_error=Type invalide");
        exit;
    }

    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['email'] = $user['email'];
        $_SESSION['type'] = $type;
        $_SESSION['user_id'] = $user['id'];
        if ($type === 'junior') {
            header("Location: junior_dashboard.php");
        } elseif ($type === 'company') {
            header("Location: company_dashboard.php");
        } elseif ($type === 'admin') {
            header("Location: admin_dashboard.php");
        } elseif ($type === 'mentor') {
            header("Location: mentor_dashboard.php");
        }
        exit;
    } else {
        header("Location: index.php?login_error=1");
        exit;
    }
}
?>