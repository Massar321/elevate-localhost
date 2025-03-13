<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['email']) || $_SESSION['type'] !== 'mentor') {
    header("Location: index.php");
    exit;
}

// Récupérer les informations de l'encadrant connecté
$stmt = $pdo->prepare("SELECT * FROM mentors WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$mentor = $stmt->fetch(PDO::FETCH_ASSOC);
$mentorId = $mentor['id'] ?? 0;

// Déterminer la section active
$section = $_GET['section'] ?? 'profile';

// Récupérer les projets créés par le mentor (indépendamment des inscriptions)
$projects = [];
if ($section === 'projects') {
    $projectsStmt = $pdo->prepare("
        SELECT p.*, d.name AS domain_name 
        FROM ppe_projects p 
        JOIN ppe_domains d ON p.domain_id = d.id 
        WHERE p.created_by = ?  -- Assumons que created_by est ajouté à ppe_projects
    ");
    $projectsStmt->execute([$mentorId]);
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Statistiques (toujours visibles)
$stats = [
    'projects_created' => $pdo->query("SELECT COUNT(*) FROM ppe_projects WHERE created_by = $mentorId")->fetchColumn() ?: 0,
    'projects_to_mentor' => $pdo->query("
        SELECT COUNT(*) 
        FROM ppe_projects p 
        JOIN ppe_domains d ON p.domain_id = d.id 
        JOIN ppe_registrations r ON r.domain = d.name 
        WHERE r.mentor_id = $mentorId
    ")->fetchColumn() ?: 0
];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create_project') {
        $domainId = intval($_POST['domain_id']);
        $title = $_POST['title'];
        $description = $_POST['description'];

        $filePath = null;
        if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES['project_file']['name']);
            $filePath = 'uploads/' . $fileName;
            if (!move_uploaded_file($_FILES['project_file']['tmp_name'], $filePath)) {
                $filePath = null; // Si l'upload échoue
            }
        }

        $stmt = $pdo->prepare("INSERT INTO ppe_projects (domain_id, title, description, project_file, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$domainId, $title, $description, $filePath, $mentorId]);
        header("Location: mentor_dashboard.php?section=projects");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit_project') {
        $projectId = intval($_POST['project_id']);
        $title = $_POST['title'];
        $description = $_POST['description'];

        $filePath = $_POST['existing_file'];
        if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES['project_file']['name']);
            $filePath = 'uploads/' . $fileName;
            if (!move_uploaded_file($_FILES['project_file']['tmp_name'], $filePath)) {
                $filePath = $_POST['existing_file']; // Garder l'ancien fichier si l'upload échoue
            }
        }

        $stmt = $pdo->prepare("UPDATE ppe_projects SET title = ?, description = ?, project_file = ? WHERE id = ? AND created_by = ?");
        $stmt->execute([$title, $description, $filePath, $projectId, $mentorId]);
        header("Location: mentor_dashboard.php?section=projects");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_project') {
        $projectId = intval($_POST['project_id']);
        $stmt = $pdo->prepare("DELETE FROM ppe_projects WHERE id = ? AND created_by = ?");
        $stmt->execute([$projectId, $mentorId]);
        header("Location: mentor_dashboard.php?section=projects");
        exit;
    }
}

// Récupérer les domaines pour le formulaire
$domainsStmt = $pdo->query("SELECT id, name FROM ppe_domains");
$domains = $domainsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Encadrant - ConnectSphere</title>
    <style>
        body { background-color: #f5f6fa; color: #333; font-family: Arial, sans-serif; line-height: 1.6; }
        .sidebar { width: 250px; position: fixed; left: 0; top: 0; height: 100%; background: #2c3e50; color: white; padding: 20px; }
        .sidebar h2 { margin-bottom: 20px; }
        .sidebar a { color: white; text-decoration: none; display: block; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .sidebar a:hover, .sidebar a.active { background: #34495e; }
        .main-content { margin-left: 250px; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .item { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #2c3e50; margin-bottom: 15px; }
        .btn { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        form { margin: 20px 0; }
        input, textarea, select { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .stats-grid { display: flex; justify-content: center; flex-wrap: wrap; margin: 10px 0; }
        .stat-item { text-align: center; padding: 5px; min-width: 150px; }
        .stat-item h3 { font-size: 1.2rem; color: #27ae60; }
    </style>
</head>
<body>
    <!-- Barre latérale -->
    <div class="sidebar">
        <h2>ConnectSphere Encadrant</h2>
        <a href="?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>">Mon Profil</a>
        <a href="?section=projects" class="<?php echo $section === 'projects' ? 'active' : ''; ?>">Projets à encadrer</a>
        <a href="logout.php">Déconnexion</a>
    </div>

    <!-- Contenu principal -->
    <div class="main-content">
        <div class="container">
            <h1>Bienvenue, <?php echo htmlspecialchars($mentor['firstname'] . ' ' . $mentor['lastname']); ?></h1>

            <!-- Statistiques (toujours visible) -->
            <div class="card">
                <div class="stats-grid">
                    <div class="stat-item">
                        <h3><?php echo $stats['projects_created']; ?></h3>
                        <p>Projets créés</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['projects_to_mentor']; ?></h3>
                        <p>Projets à encadrer</p>
                    </div>
                </div>
            </div>

            <!-- Section dynamique -->
            <?php if ($section === 'profile'): ?>
                <div class="card">
                    <h2>Mon Profil</h2>
                    <p><strong>Prénom :</strong> <?php echo htmlspecialchars($mentor['firstname']); ?></p>
                    <p><strong>Nom :</strong> <?php echo htmlspecialchars($mentor['lastname']); ?></p>
                    <p><strong>Email :</strong> <?php echo htmlspecialchars($mentor['email']); ?></p>
                    <p><strong>Années d'expérience :</strong> <?php echo htmlspecialchars($mentor['years_experience']); ?></p>
                    <p><strong>Domaine d'activité :</strong> <?php echo htmlspecialchars($mentor['activity_domain']); ?></p>
                    <p><strong>Entreprise :</strong> <?php echo htmlspecialchars($mentor['company']); ?></p>
                    <p><strong>Statut :</strong> <?php echo htmlspecialchars($mentor['employment_status']); ?></p>
                </div>
            <?php elseif ($section === 'projects'): ?>
                <div class="card">
                    <h2>Projets à encadrer</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <h3>Créer un Projet</h3>
                        <input type="hidden" name="action" value="create_project">
                        <select name="domain_id" required>
                            <option value="">Choisir un domaine</option>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="title" placeholder="Titre du projet" required>
                        <textarea name="description" placeholder="Description" required></textarea>
                        <input type="file" name="project_file" accept=".pdf,.doc,.docx">
                        <button type="submit" class="btn">Créer</button>
                    </form>
                    <?php if (empty($projects)): ?>
                        <p>Aucun projet à encadrer pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($projects as $project): ?>
                                <div class="item">
                                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($project['description']); ?></p>
                                    <p><strong>Domaine :</strong> <?php echo htmlspecialchars($project['domain_name']); ?></p>
                                    <?php if ($project['project_file']): ?>
                                        <p><strong>Fichier :</strong> <a href="<?php echo htmlspecialchars($project['project_file']); ?>" download>Télécharger</a></p>
                                    <?php endif; ?>
                                    <form method="POST" enctype="multipart/form-data" style="margin-top: 10px;">
                                        <input type="hidden" name="action" value="edit_project">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <input type="hidden" name="existing_file" value="<?php echo htmlspecialchars($project['project_file']); ?>">
                                        <input type="text" name="title" value="<?php echo htmlspecialchars($project['title']); ?>" required>
                                        <textarea name="description" required><?php echo htmlspecialchars($project['description']); ?></textarea>
                                        <input type="file" name="project_file" accept=".pdf,.doc,.docx">
                                        <button type="submit" class="btn">Modifier</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Supprimer ce projet ?');">Supprimer</button>
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