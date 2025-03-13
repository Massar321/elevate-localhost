<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php?register_error=Méthode non autorisée");
    exit;
}

$type = $_POST['type'] ?? '';
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

if (empty($type) || empty($email) || empty($password) || empty($password_confirm)) {
    header("Location: index.php?register_error=Veuillez remplir tous les champs");
    exit;
}

if ($password !== $password_confirm) {
    header("Location: index.php?register_error=Les mots de passe ne correspondent pas");
    exit;
}

// Vérifier si l'email existe déjà
$checkEmail = $pdo->prepare("SELECT COUNT(*) FROM juniors WHERE email = ? UNION SELECT COUNT(*) FROM companies WHERE email = ? UNION SELECT COUNT(*) FROM admins WHERE email = ? UNION SELECT COUNT(*) FROM mentors WHERE email = ?");
$checkEmail->execute([$email, $email, $email, $email]);
if ($checkEmail->fetchColumn() > 0) {
    header("Location: index.php?register_error=Cet email est déjà utilisé");
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    if ($type === 'junior') {
        $stmt = $pdo->prepare("INSERT INTO juniors (email, password, firstname, lastname, birthdate, school, status, country, city, experience) 
            VALUES (:email, :password, :firstname, :lastname, :birthdate, :school, :status, :country, :city, :experience)");
        $stmt->execute([
            'email' => $email,
            'password' => $hashed_password,
            'firstname' => $_POST['firstname'] ?? '',
            'lastname' => $_POST['lastname'] ?? '',
            'birthdate' => $_POST['birthdate'] ?? '',
            'school' => $_POST['school'] ?? '',
            'status' => $_POST['status'] ?? '',
            'country' => $_POST['country'] ?? '',
            'city' => $_POST['city'] ?? '',
            'experience' => (int)($_POST['experience'] ?? 0)
        ]);
    } elseif ($type === 'company') {
        $stmt = $pdo->prepare("INSERT INTO companies (email, password, name, city, country, siret, company_type, domain) 
            VALUES (:email, :password, :name, :city, :country, :siret, :company_type, :domain)");
        $stmt->execute([
            'email' => $email,
            'password' => $hashed_password,
            'name' => $_POST['name'] ?? '',
            'city' => $_POST['city'] ?? '',
            'country' => $_POST['country'] ?? '',
            'siret' => $_POST['siret'] ?? '',
            'company_type' => $_POST['company_type'] ?? '',
            'domain' => $_POST['domain'] ?? ''
        ]);
    } elseif ($type === 'mentor') {
        $stmt = $pdo->prepare("INSERT INTO mentors (firstname, lastname, email, password, years_experience, activity_domain, company, employment_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['firstname'] ?? '',
            $_POST['lastname'] ?? '',
            $email,
            $hashed_password,
            (int)($_POST['years_experience'] ?? 0),
            $_POST['activity_domain'] ?? '',
            $_POST['company'] ?? '',
            $_POST['employment_status'] ?? ''
        ]);
    } else {
        header("Location: index.php?register_error=Type de profil invalide");
        exit;
    }

    $_SESSION['email'] = $email;
    $_SESSION['type'] = $type;
    $_SESSION['user_id'] = $pdo->lastInsertId();
    header("Location: index.php");
    exit;
} catch (PDOException $e) {
    header("Location: index.php?register_error=Erreur lors de la création : " . $e->getMessage());
    exit;
}
?>