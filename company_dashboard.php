<?php
session_start();
require_once 'db_connect.php';

// Charger PHPMailer
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['email']) || $_SESSION['type'] !== 'company') {
    header("Location: index.php");
    exit;
}

// Récupérer le nom et l'ID de l'entreprise connectée
$stmt = $pdo->prepare("SELECT id, name FROM companies WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$companyId = $company['id'] ?? 0;
$companyName = $company['name'] ?? 'Entreprise';

// Déterminer la section active
$section = $_GET['section'] ?? 'stats';

// Récupérer les données selon la section
$projects = $applications = [];
if ($section === 'projects' || $section === 'stats') {
    $projectsStmt = $pdo->prepare("SELECT * FROM projects WHERE company_id = ?");
    $projectsStmt->execute([$companyId]);
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($section === 'applications' || $section === 'stats') {
    $applicationsStmt = $pdo->prepare("
        SELECT a.*, j.firstname, j.lastname, j.email AS junior_email, p.title AS project_title 
        FROM applications a 
        JOIN juniors j ON a.junior_id = j.id 
        JOIN projects p ON a.project_id = p.id 
        WHERE p.company_id = ?
    ");
    $applicationsStmt->execute([$companyId]);
    $applications = $applicationsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// Statistiques (toujours récupérées)
$stats = [
    'projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE company_id = $companyId")->fetchColumn() ?: 0,
    'applications' => $pdo->query("SELECT COUNT(*) FROM applications a JOIN projects p ON a.project_id = p.id WHERE p.company_id = $companyId")->fetchColumn() ?: 0,
    'pending_applications' => $pdo->query("SELECT COUNT(*) FROM applications a JOIN projects p ON a.project_id = p.id WHERE p.company_id = $companyId AND a.status = 'pending'")->fetchColumn() ?: 0
];

// Traitement de la création/modification de projet et gestion des candidatures
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create' || $_POST['action'] === 'update') {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $tjm = floatval($_POST['tjm']);
        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;

        $filePath = null;
        if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES['project_file']['name']);
            $filePath = 'uploads/' . $fileName;
            move_uploaded_file($_FILES['project_file']['tmp_name'], $filePath);
        }

        if ($_POST['action'] === 'create') {
            $stmt = $pdo->prepare("INSERT INTO projects (company_id, title, description, budget, project_file) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$companyId, $title, $description, $tjm, $filePath]);
        } elseif ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare("UPDATE projects SET title = ?, description = ?, budget = ?, project_file = COALESCE(?, project_file) WHERE id = ? AND company_id = ?");
            $stmt->execute([$title, $description, $tjm, $filePath, $projectId, $companyId]);
        }
    } elseif ($_POST['action'] === 'delete') {
        $projectId = intval($_POST['project_id']);
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND company_id = ?");
        $stmt->execute([$projectId, $companyId]);
    } elseif ($_POST['action'] === 'accept' || $_POST['action'] === 'reject') {
        $applicationId = intval($_POST['application_id']);
        $status = $_POST['action'] === 'accept' ? 'accepted' : 'rejected';

        // Mettre à jour le statut
        $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ? AND project_id IN (SELECT id FROM projects WHERE company_id = ?)");
        $stmt->execute([$status, $applicationId, $companyId]);

        // Récupérer les infos pour l'email
        $stmt = $pdo->prepare("
            SELECT j.email, j.firstname, p.title 
            FROM applications a 
            JOIN juniors j ON a.junior_id = j.id 
            JOIN projects p ON a.project_id = p.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        // Configurer PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ton.email@gmail.com'; // Remplace par ton email Gmail
            $mail->Password = 'ton_mot_de_passe_application'; // Remplace par ton mot de passe d'application
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('ton.email@gmail.com', 'ConnectSphere');
            $mail->addAddress($application['email']);
            $mail->Subject = "Statut de votre candidature pour " . $application['title'];
            $mail->Body = "Bonjour " . $application['firstname'] . ",\n\nVotre candidature pour le projet '" . $application['title'] . "' a été " . ($status === 'accepted' ? 'acceptée' : 'rejetée') . ".\nCordialement,\nL'équipe ConnectSphere";
            $mail->send();
        } catch (Exception $e) {
            error_log("Erreur PHPMailer : " . $mail->ErrorInfo);
        }
    }
    header("Location: company_dashboard.php?section=applications");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Entreprise - ConnectSphere</title>
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
        }

        .sidebar a:hover, .sidebar a.active {
            background: #34495e;
        }

        .sidebar .new-notif {
            position: relative;
        }

        .sidebar .new-notif::after {
            content: '<?php echo $stats['pending_applications']; ?>';
            position: absolute;
            top: 5px;
            right: 5px;
            background: #e74c3c;
            color: white;
            padding: 2px 6px;
            border-radius: 50%;
            font-size: 0.8rem;
            display: <?php echo $stats['pending_applications'] > 0 ? 'block' : 'none'; ?>;
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

        .item.pending {
            background: #fff3cd;
            border: 1px solid #f39c12;
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

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #219653;
        }

        h1, h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        form { margin: 20px 0; }
        input, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .stats-grid { display: flex; justify-content: space-around; flex-wrap: wrap; margin: 20px 0; }
        .stat-item { text-align: center; padding: 10px; min-width: 100px; }
        .stat-item h3 { font-size: 1.5rem; color: #3498db; }
    </style>
</head>
<body>
    <!-- Barre latérale -->
    <div class="sidebar">
        <h2>ConnectSphere</h2>
        <a href="?section=stats" class="<?php echo $section === 'stats' ? 'active' : ''; ?>">Statistiques</a>
        <a href="?section=projects" class="<?php echo $section === 'projects' ? 'active' : ''; ?>">Projets</a>
        <a href="?section=applications" class="<?php echo $section === 'applications' ? 'active' : ''; ?> new-notif">Candidatures</a>
        <a href="logout.php">Déconnexion</a>
    </div>

    <!-- Contenu principal -->
    <div class="main-content">
        <div class="container">
            <h1>Bienvenue, <?php echo htmlspecialchars($companyName); ?></h1>

            <!-- Statistiques (toujours visible) -->
            <div class="card">
                <h2>Statistiques</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <h3><?php echo $stats['projects']; ?></h3>
                        <p>Projets</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['applications']; ?></h3>
                        <p>Candidatures</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['pending_applications']; ?></h3>
                        <p>Candidatures en attente</p>
                    </div>
                </div>
            </div>

            <!-- Section dynamique -->
            <?php if ($section === 'projects'): ?>
                <div class="card" id="projects">
                    <h2><?php echo isset($_GET['edit']) ? 'Modifier le projet' : 'Créer un projet'; ?></h2>
                    <?php if (isset($_GET['edit'])): 
                        $editId = intval($_GET['edit']);
                        $editStmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND company_id = ?");
                        $editStmt->execute([$editId, $companyId]);
                        $editProject = $editStmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="project_id" value="<?php echo $editId; ?>">
                        <input type="text" name="title" value="<?php echo htmlspecialchars($editProject['title'] ?? ''); ?>" required>
                        <textarea name="description" required><?php echo htmlspecialchars($editProject['description'] ?? ''); ?></textarea>
                        <input type="number" name="tjm" step="0.01" value="<?php echo $editProject['budget'] ?? ''; ?>" required placeholder="TJM (€)">
                        <input type="file" name="project_file" accept=".pdf,.doc,.docx">
                        <button type="submit" class="btn">Mettre à jour</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create">
                        <input type="text" name="title" placeholder="Titre du projet" required>
                        <textarea name="description" placeholder="Description" required></textarea>
                        <input type="number" name="tjm" step="0.01" placeholder="TJM (€)" required>
                        <input type="file" name="project_file" accept=".pdf,.doc,.docx">
                        <button type="submit" class="btn">Créer projet</button>
                    </form>
                    <?php endif; ?>

                    <h2>Vos projets</h2>
                    <?php if (empty($projects)): ?>
                        <p>Aucun projet pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($projects as $project): ?>
                                <div class="item">
                                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($project['description']); ?></p>
                                    <p><strong>TJM :</strong> <?php echo number_format($project['budget'], 2); ?> €</p>
                                    <?php if ($project['project_file']): ?>
                                        <p><strong>Fiche :</strong> <a href="<?php echo htmlspecialchars($project['project_file']); ?>" download>Télécharger</a></p>
                                    <?php endif; ?>
                                    <a href="?section=projects&edit=<?php echo $project['id']; ?>" class="btn">Modifier</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Supprimer ce projet ?');">Supprimer</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'applications'): ?>
                <div class="card" id="applications">
                    <h2>Candidatures reçues</h2>
                    <?php if (empty($applications)): ?>
                        <p>Aucune candidature pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($applications as $app): ?>
                                <div class="item <?php echo $app['status'] === 'pending' ? 'pending' : ''; ?>">
                                    <h3><?php echo htmlspecialchars($app['project_title']); ?></h3>
                                    <p><strong>Candidat :</strong> <?php echo htmlspecialchars($app['firstname'] . ' ' . $app['lastname']); ?></p>
                                    <p><strong>CV :</strong> <a href="<?php echo htmlspecialchars($app['cv_file']); ?>" download>Télécharger</a></p>
                                    <p><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($app['applied_at'])); ?></p>
                                    <p><strong>Statut :</strong> <?php echo $app['status'] === 'pending' ? 'En attente' : ($app['status'] === 'accepted' ? 'Acceptée' : 'Rejetée'); ?></p>
                                    <?php if ($app['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" class="btn btn-success">Accepter</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Rejetter</button>
                                        </form>
                                    <?php endif; ?>
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