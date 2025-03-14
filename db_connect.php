<?php
$host = 'localhost';
$dbname = 'connectsphere';
$username = 'root'; // Par défaut avec XAMPP
$password = '';     // Par défaut vide avec XAMPP

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>