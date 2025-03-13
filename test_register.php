<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "Test démarré.<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "Formulaire soumis.<br>";
    $email = $_POST['email'] ?? 'non défini';
    echo "Email reçu : $email<br>";
} else {
    echo "Aucun formulaire soumis.<br>";
}
?>

<form method="POST" action="test_register.php">
    <input type="email" name="email" placeholder="Email" required>
    <button type="submit">Tester</button>
</form>