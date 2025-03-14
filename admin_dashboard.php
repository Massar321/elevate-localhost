<?php
session_start();
require_once 'db_connect.php';

// Vérifier si l'utilisateur est un admin
if (!isset($_SESSION['email']) || !isset($_SESSION['type']) || $_SESSION['type'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Récupérer les informations de l'admin connecté
$stmt = $pdo->prepare("SELECT email, is_superuser FROM admins WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$adminEmail = $admin['email'] ?? 'Administrateur';
$isSuperUser = $admin['is_superuser'] ?? 0;

// Déterminer la section active
$section = $_GET['section'] ?? 'stats';

// Récupérer les données selon la section
$juniors = $companies = $ppeDomains = $ppeProjects = $admins = [];
if ($section === 'juniors') {
    $juniorsStmt = $pdo->query("SELECT id, email, firstname, lastname, country, status FROM juniors");
    $juniors = $juniorsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($section === 'companies') {
    $companiesStmt = $pdo->query("SELECT id, email, name, country, company_type FROM companies");
    $companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($section === 'ppe') {
    $ppeDomainsStmt = $pdo->query("SELECT id, name FROM ppe_domains");
    $ppeDomains = $ppeDomainsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $ppeProjectsStmt = $pdo->query("SELECT p.*, d.name AS domain_name FROM ppe_projects p JOIN ppe_domains d ON p.domain_id = d.id");
    $ppeProjects = $ppeProjectsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($section === 'admins' && $isSuperUser) {
    $adminsStmt = $pdo->query("SELECT id, email, is_superuser FROM admins WHERE email != ?");
    $adminsStmt->execute([$_SESSION['email']]);
    $admins = $adminsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Statistiques (toujours récupérées)
$stats = [
    'juniors' => $pdo->query("SELECT COUNT(*) FROM juniors")->fetchColumn() ?: 0,
    'companies' => $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn() ?: 0,
    'projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?: 0,
    'applications' => $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn() ?: 0,
    'admins' => $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn() ?: 0,
    'ppe_projects' => $pdo->query("SELECT COUNT(*) FROM ppe_projects")->fetchColumn() ?: 0
];

// Compter les projets par domaine pour la navigation
$ppeProjectsByDomain = [];
$domains = ['Business Intelligence', 'Data Science', 'Data Ingénieur'];
foreach ($domains as $domain) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ppe_projects p JOIN ppe_domains d ON p.domain_id = d.id WHERE d.name = ?");
    $countStmt->execute([$domain]);
    $ppeProjectsByDomain[$domain] = $countStmt->fetchColumn() ?: 0;
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_junior') {
        $juniorId = intval($_POST['junior_id']);
        $stmt = $pdo->prepare("DELETE FROM juniors WHERE id = ?");
        $stmt->execute([$juniorId]);
        header("Location: admin_dashboard.php?section=juniors");
    } elseif ($_POST['action'] === 'delete_company') {
        $companyId = intval($_POST['company_id']);
        $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->execute([$companyId]);
        header("Location: admin_dashboard.php?section=companies");
    } elseif ($_POST['action'] === 'delete_admin' && $isSuperUser) {
        $adminId = intval($_POST['admin_id']);
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ? AND is_superuser = 0");
        $stmt->execute([$adminId]);
        header("Location: admin_dashboard.php?section=admins");
    } elseif ($_POST['action'] === 'create_admin' && $isSuperUser) {
        $newAdminEmail = filter_var($_POST['new_admin_email'], FILTER_SANITIZE_EMAIL);
        $newAdminPassword = password_hash($_POST['new_admin_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (email, password, is_superuser) VALUES (?, ?, 0)");
        $stmt->execute([$newAdminEmail, $newAdminPassword]);
        header("Location: admin_dashboard.php?section=admins");
    } elseif ($_POST['action'] === 'create_ppe_project') {
        $domainId = intval($_POST['domain_id']);
        $title = $_POST['title'];
        $description = $_POST['description'];

        $filePath = null;
        if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES['project_file']['name']);
            $filePath = 'uploads/' . $fileName;
            move_uploaded_file($_FILES['project_file']['tmp_name'], $filePath);
        }

        $stmt = $pdo->prepare("INSERT INTO ppe_projects (domain_id, title, description, project_file) VALUES (?, ?, ?, ?)");
        $stmt->execute([$domainId, $title, $description, $filePath]);
        header("Location: admin_dashboard.php?section=ppe");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Administrateur - ConnectSphere</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f5f6fa;
            color: #333;
            line-height: 1.6;
        }

        .sidebar {
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            background: #2c3e50;
            color: white;
            padding: 20px;
        }

        .sidebar h2 {
            margin-bottom: 20px;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            position: relative;
        }

        .sidebar a:hover, .sidebar a.active {
            background: #34495e;
        }

        .sidebar a .count {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: #e74c3c;
            color: white;
            padding: 2px 6px;
            border-radius: 50%;
            font-size: 0.8rem;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }

        .btn:hover {
            background: #2980b9;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        h1, h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        form { margin: 20px 0; }
        input, textarea, select { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .stats-grid { display: flex; justify-content: space-around; flex-wrap: wrap; margin: 20px 0; }
        .stat-item { text-align: center; padding: 10px; min-width: 100px; }
        .stat-item h3 { font-size: 1.5rem; color: #3498db; }
    </style>
</head>
<body>
    <!-- Barre latérale -->
    <div class="sidebar">
        <h2>ConnectSphere Admin</h2>
        <a href="?section=stats" class="<?php echo $section === 'stats' ? 'active' : ''; ?>">Statistiques</a>
        <a href="?section=juniors" class="<?php echo $section === 'juniors' ? 'active' : ''; ?>">Profils Juniors <span class="count"><?php echo $stats['juniors']; ?></span></a>
        <a href="?section=companies" class="<?php echo $section === 'companies' ? 'active' : ''; ?>">Profils Entreprises <span class="count"><?php echo $stats['companies']; ?></span></a>
        <a href="?section=ppe" class="<?php echo $section === 'ppe' ? 'active' : ''; ?>">Projets PPE 
            <span class="count"><?php echo array_sum($ppeProjectsByDomain); ?></span>
        </a>
        <?php if ($isSuperUser): ?>
            <a href="?section=admins" class="<?php echo $section === 'admins' ? 'active' : ''; ?>">Administrateurs <span class="count"><?php echo $stats['admins']; ?></span></a>
        <?php endif; ?>
        <a href="logout.php">Déconnexion</a>
    </div>

    <!-- Contenu principal -->
    <div class="main-content">
        <div class="container">
            <h1>Bienvenue, <?php echo htmlspecialchars($adminEmail); ?><?php echo $isSuperUser ? ' (Super User)' : ''; ?></h1>

            <!-- Statistiques (toujours visible) -->
            <div class="card">
                <h2>Statistiques</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <h3><?php echo $stats['juniors']; ?></h3>
                        <p>Juniors</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['companies']; ?></h3>
                        <p>Entreprises</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['projects']; ?></h3>
                        <p>Projets</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['applications']; ?></h3>
                        <p>Candidatures</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['admins']; ?></h3>
                        <p>Administrateurs</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['ppe_projects']; ?></h3>
                        <p>Projets PPE</p>
                    </div>
                </div>
            </div>

            <!-- Section dynamique -->
            <?php if ($section === 'juniors'): ?>
                <div class="card" id="juniors">
                    <h2>Profils Juniors</h2>
                    <?php if (empty($juniors)): ?>
                        <p>Aucun profil Junior pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($juniors as $junior): ?>
                                <div class="item">
                                    <h3><?php echo htmlspecialchars($junior['firstname'] . ' ' . $junior['lastname']); ?></h3>
                                    <p><strong>Email :</strong> <?php echo htmlspecialchars($junior['email']); ?></p>
                                    <p><strong>Pays :</strong> <?php echo htmlspecialchars($junior['country']); ?></p>
                                    <p><strong>Statut :</strong> <?php echo htmlspecialchars($junior['status']); ?></p>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_junior">
                                        <input type="hidden" name="junior_id" value="<?php echo $junior['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Supprimer ce profil Junior ?');">Supprimer</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'companies'): ?>
                <div class="card" id="companies">
                    <h2>Profils Entreprises</h2>
                    <?php if (empty($companies)): ?>
                        <p>Aucune entreprise pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($companies as $company): ?>
                                <div class="item">
                                    <h3><?php echo htmlspecialchars($company['name']); ?></h3>
                                    <p><strong>Email :</strong> <?php echo htmlspecialchars($company['email']); ?></p>
                                    <p><strong>Pays :</strong> <?php echo htmlspecialchars($company['country']); ?></p>
                                    <p><strong>Type :</strong> <?php echo htmlspecialchars($company['company_type']); ?></p>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_company">
                                        <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Supprimer cette entreprise ?');">Supprimer</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'ppe'): ?>
                <div class="card" id="ppe">
                    <h2>Projets Professionnels Encadrés</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <h3>Créer un Projet PPE</h3>
                        <input type="hidden" name="action" value="create_ppe_project">
                        <select name="domain_id" required>
                            <option value="">Choisir un domaine</option>
                            <?php foreach ($ppeDomains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="title" placeholder="Titre du projet" required>
                        <textarea name="description" placeholder="Description" required></textarea>
                        <input type="file" name="project_file" accept=".pdf,.doc,.docx">
                        <button type="submit" class="btn">Créer</button>
                    </form>
                    <?php if (empty($ppeDomains)): ?>
                        <p>Aucun domaine PPE défini pour le moment.</p>
                    <?php else: ?>
                        <?php foreach ($ppeDomains as $domain): ?>
                            <div class="domain-group">
                                <h3><?php echo htmlspecialchars($domain['name']); ?> (<?php echo $ppeProjectsByDomain[$domain['name']]; ?> projets)</h3>
                                <div class="grid">
                                    <?php
                                    $domainProjects = array_filter($ppeProjects, function($project) use ($domain) {
                                        return $project['domain_name'] === $domain['name'];
                                    });
                                    if (empty($domainProjects)): ?>
                                        <p>Aucun projet dans ce domaine pour le moment.</p>
                                    <?php else: ?>
                                        <?php foreach ($domainProjects as $project): ?>
                                            <div class="item">
                                                <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                                <p><?php echo htmlspecialchars($project['description']); ?></p>
                                                <?php if ($project['project_file']): ?>
                                                    <p><strong>Fichier :</strong> <a href="<?php echo htmlspecialchars($project['project_file']); ?>" download>Télécharger</a></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'admins' && $isSuperUser): ?>
                <div class="card" id="admins">
                    <h2>Gestion des Administrateurs</h2>
                    <form method="POST">
                        <h3>Ajouter un Administrateur</h3>
                        <input type="hidden" name="action" value="create_admin">
                        <input type="email" name="new_admin_email" placeholder="Email" required>
                        <input type="password" name="new_admin_password" placeholder="Mot de passe" required>
                        <button type="submit" class="btn">Créer</button>
                    </form>
                    <?php if (empty($admins)): ?>
                        <p>Aucun autre administrateur pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($admins as $admin): ?>
                                <div class="item">
                                    <h3><?php echo htmlspecialchars($admin['email']); ?></h3>
                                    <p><strong>Super User :</strong> <?php echo $admin['is_superuser'] ? 'Oui' : 'Non'; ?></p>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_admin">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Supprimer cet administrateur ?');">Supprimer</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>