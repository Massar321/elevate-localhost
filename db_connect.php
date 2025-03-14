<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO(
        "mysql:host=elevatlxmpdb123.mysql.db;dbname=elevatlxmpdb123",
        "elevatlxmpdb123",
        "ZpUmjZkHph1BO3bHA3HnJk76E", // Remplace par le mot de passe exact d’OVH
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]
    );
    echo "Connexion à la base réussie !"; // Pour tester
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>