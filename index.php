<?php
session_start();

if (isset($_SESSION['email']) && isset($_SESSION['type'])) {
    if ($_SESSION['type'] === 'junior') {
        header("Location: junior_dashboard.php");
    } elseif ($_SESSION['type'] === 'company') {
        header("Location: company_dashboard.php");
    } elseif ($_SESSION['type'] === 'admin') {
        header("Location: admin_dashboard.php");
    } elseif ($_SESSION['type'] === 'mentor') {
        header("Location: mentor_dashboard.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elevate Junior - Bienvenue</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .container {
            max-width: 600px;
            width: 90%;
            padding: 30px;
            position: relative;
            z-index: 1;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
        }

        .card.active {
            opacity: 1;
            transform: translateY(0);
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 25px;
            font-weight: 600;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            margin: 10px 0;
            border: none;
            border-radius: 8px;
            background: #f1f3f5;
            color: #333;
            font-size: 1rem;
            transition: box-shadow 0.3s ease;
        }

        input:focus, select:focus {
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            margin: 10px 0;
        }

        .btn:hover {
            background: linear-gradient(90deg, #2980b9, #1e6a9e);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: linear-gradient(90deg, #7f8c8d, #636e72);
        }

        .btn-secondary:hover {
            background: linear-gradient(90deg, #636e72, #4a5659);
        }

        .toggle-link {
            display: block;
            text-align: center;
            color: #3498db;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 10px;
            transition: color 0.3s ease;
        }

        .toggle-link:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
            font-size: 0.9rem;
            background: rgba(231, 76, 60, 0.1);
            padding: 8px;
            border-radius: 5px;
        }

        .form-section {
            margin-bottom: 15px;
        }

        /* Animation de fond */
        .bg-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
            animation: pulse 15s infinite;
            z-index: 0;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.2; }
            100% { transform: scale(1); opacity: 0.5; }
        }

        /* Media Queries pour responsivité */
        @media (max-width: 480px) {
            .container { padding: 20px; }
            h2 { font-size: 1.5rem; }
            .btn { font-size: 1rem; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="bg-animation"></div>
    <div class="container">
        <!-- Page d'accueil -->
        <div class="card" id="welcome-page">
            <h2>Bienvenue chez Elevate Junior</h2>
            <button class="btn" onclick="showPage('login-page')">Se connecter</button>
            <button class="btn btn-secondary" onclick="showPage('register-page')">Créer un compte</button>
        </div>

        <!-- Formulaire de connexion -->
        <div class="card" id="login-page" style="display: none;">
            <h2>Se connecter</h2>
            <?php if (isset($_GET['login_error'])): ?>
                <p class="error">Email, mot de passe ou type incorrect.</p>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Mot de passe" required>
                <select name="type" required>
                    <option value="">Type de profil</option>
                    <option value="junior">Junior</option>
                    <option value="company">Entreprise</option>
                    <option value="admin">Administrateur</option>
                    <option value="mentor">Encadrant</option>
                </select>
                <button type="submit" class="btn">Se connecter</button>
            </form>
            <a href="#" class="toggle-link" onclick="showPage('register-page')">Pas de compte ? Créer un compte</a>
            < #

a href="#" class="toggle-link" onclick="showPage('welcome-page')">Retour</a>
        </div>

        <!-- Formulaire d'inscription -->
        <div class="card" id="register-page" style="display: none;">
            <h2>Créer un compte</h2>
            <?php if (isset($_GET['register_error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_GET['register_error']); ?></p>
            <?php endif; ?>
            <form method="POST" action="register.php">
                <div class="form-section">
                    <select name="type" id="register-type" onchange="updateRegisterForm()" required>
                        <option value="">Type de profil</option>
                        <option value="junior">Junior</option>
                        <option value="company">Entreprise</option>
                        <option value="mentor">Encadrant</option>
                    </select>
                </div>
                <div class="form-section">
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="password" placeholder="Mot de passe" required>
                    <input type="password" name="password_confirm" placeholder="Confirmer le mot de passe" required>
                </div>
                <!-- Champs Junior -->
                <div id="junior-fields" style="display: none;">
                    <input type="text" name="firstname" placeholder="Prénom">
                    <input type="text" name="lastname" placeholder="Nom">
                    <input type="date" name="birthdate" placeholder="Date de naissance">
                    <input type="text" name="school" placeholder="Établissement (si étudiant)">
                    <select name="status">
                        <option value="">Statut</option>
                        <option value="student">Étudiant</option>
                        <option value="graduate">Diplômé sans expérience</option>
                        <option value="junior">Junior</option>
                    </select>
                    <input type="text" name="country" placeholder="Pays">
                    <input type="text" name="city" placeholder="Ville">
                    <input type="number" name="experience" placeholder="Années d'expérience" min="0">
                </div>
                <!-- Champs Entreprise -->
                <div id="company-fields" style="display: none;">
                    <input type="text" name="name" placeholder="Nom de l'entreprise">
                    <input type="text" name="city" placeholder="Ville">
                    <input type="text" name="country" placeholder="Pays">
                    <input type="text" name="siret" placeholder="Numéro SIRET">
                    <select name="company_type">
                        <option value="">Type d'entreprise</option>
                        <option value="PME">PME</option>
                        <option value="PMI">PMI</option>
                        <option value="STARTUP">Startup</option>
                    </select>
                    <input type="text" name="domain" placeholder="Domaine d'entreprise">
                </div>
                <!-- Champs Encadrant -->
                <div id="mentor-fields" style="display: none;">
                    <input type="text" name="firstname" placeholder="Prénom">
                    <input type="text" name="lastname" placeholder="Nom">
                    <input type="number" name="years_experience" placeholder="Années d'expérience" min="0">
                    <input type="text" name="activity_domain" placeholder="Domaine d'activité">
                    <input type="text" name="company" placeholder="Entreprise">
                    <select name="employment_status">
                        <option value="">Statut</option>
                        <option value="Freelance">Freelance</option>
                        <option value="Salarié">Salarié</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>
                <button type="submit" class="btn">Créer le compte</button>
            </form>
            <a href="#" class="toggle-link" onclick="showPage('login-page')">Déjà un compte ? Se connecter</a>
            <a href="#" class="toggle-link" onclick="showPage('welcome-page')">Retour</a>
        </div>
    </div>

    <script>
        function showPage(pageId) {
            document.querySelectorAll('.card').forEach(card => {
                card.style.display = 'none';
                card.classList.remove('active');
            });
            const targetCard = document.getElementById(pageId);
            targetCard.style.display = 'block';
            setTimeout(() => targetCard.classList.add('active'), 10); // Pour l'animation
        }

        function updateRegisterForm() {
            const type = document.getElementById('register-type').value;
            document.getElementById('junior-fields').style.display = type === 'junior' ? 'block' : 'none';
            document.getElementById('company-fields').style.display = type === 'company' ? 'block' : 'none';
            document.getElementById('mentor-fields').style.display = type === 'mentor' ? 'block' : 'none';

            // Gérer les champs requis dynamiquement
            document.querySelectorAll('#junior-fields input, #junior-fields select').forEach(field => field.required = type === 'junior');
            document.querySelectorAll('#company-fields input, #company-fields select').forEach(field => field.required = type === 'company');
            document.querySelectorAll('#mentor-fields input, #mentor-fields select').forEach(field => field.required = type === 'mentor');
        }

        // Afficher la page de bienvenue par défaut avec animation
        showPage('welcome-page');
    </script>
</body>
</html>