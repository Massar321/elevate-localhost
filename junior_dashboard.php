<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['email']) || $_SESSION['type'] !== 'junior') {
    header("Location: index.php");
    exit;
}

// Vérifier que l'email de la session correspond à un utilisateur existant
$stmt = $pdo->prepare("SELECT id, firstname, lastname, profile_picture FROM juniors WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$junior = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$junior) {
    // Si aucun utilisateur n'est trouvé, détruire la session et rediriger
    session_destroy();
    header("Location: index.php");
    exit;
}

$juniorId = $junior['id'] ?? 0;
$juniorFirstname = $junior['firstname'] ?? 'Utilisateur';
$juniorLastname = $junior['lastname'] ?? '';
$profilePicture = $junior['profile_picture'] ?? 'uploads/default_profile.jpg';

// Traitement de l'upload de la photo de profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $fileName = time() . '_' . basename($_FILES['profile_picture']['name']);
        $filePath = 'uploads/' . $fileName;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filePath)) {
            $stmt = $pdo->prepare("UPDATE juniors SET profile_picture = ? WHERE id = ?");
            $stmt->execute([$filePath, $juniorId]);
            $profilePicture = $filePath;
        }
    }
    header("Location: junior_dashboard.php?section=" . ($_GET['section'] ?? 'stats'));
    exit;
}

// Déterminer la section active
$section = $_GET['section'] ?? 'stats';

// Récupérer les projets inscrits pour la barre latérale et "Mes Projets"
$registeredStmt = $pdo->prepare("SELECT DISTINCT project_id FROM ppe_registrations WHERE junior_id = ?");
$registeredStmt->execute([$juniorId]);
$registeredProjects = $registeredStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Récupérer les domaines inscrits (facultatif, pour compatibilité)
$registeredDomainsStmt = $pdo->prepare("SELECT domain FROM ppe_registrations WHERE junior_id = ?");
$registeredDomainsStmt->execute([$juniorId]);
$registeredDomains = $registeredDomainsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Récupérer les données selon la section
$projects = $trainings = $certifications = $opensource = $ppeDomains = $ppeProjects = [];
if ($section === 'projects' || $section === 'stats') {
    $projectsStmt = $pdo->query("SELECT p.*, c.name AS company_name, c.country AS company_country, p.created_at FROM projects p JOIN companies c ON p.company_id = c.id");
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $appliedStmt = $pdo->prepare("SELECT project_id, status FROM applications WHERE junior_id = ?");
    $appliedStmt->execute([$juniorId]);
    $appliedProjects = $appliedStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
}
if ($section === 'trainings' || $section === 'stats') {
    $trainingsStmt = $pdo->query("SELECT * FROM trainings");
    $trainings = $trainingsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($section === 'certifications' || $section === 'stats') {
    $certificationsStmt = $pdo->prepare("SELECT t.title, c.obtained_at FROM certifications c JOIN trainings t ON c.training_id = t.id WHERE c.junior_id = ?");
    $certificationsStmt->execute([$juniorId]);
    $certifications = $certificationsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($section === 'opensource' || $section === 'stats') {
    $opensourceStmt = $pdo->query("SELECT * FROM opensource_projects");
    $opensource = $opensourceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($section === 'ppe_elevate' || $section === 'stats') {
    $domainsStmt = $pdo->query("SELECT id, name FROM ppe_domains");
    $domains = $domainsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($domains as $domain) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ppe_projects WHERE domain_id = ?");
        $countStmt->execute([$domain['id']]);
        $ppeDomains[$domain['name']] = $countStmt->fetchColumn() ?: 0;
    }
}
if ($section === 'ppe_mentor' || $section === 'my_projects') {
    $ppeStmt = $pdo->prepare("
        SELECT p.*, d.name AS domain_name, m.firstname, m.lastname, m.employment_status, m.years_experience, p.created_at
        FROM ppe_projects p 
        JOIN ppe_domains d ON p.domain_id = d.id 
        LEFT JOIN mentors m ON p.created_by = m.id
    ");
    $ppeStmt->execute();
    $ppeProjects = $ppeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if ($section === 'my_projects') {
    if (!empty($registeredProjects)) {
        $placeholders = implode(',', array_fill(0, count($registeredProjects), '?'));
        $ppeStmt = $pdo->prepare("
            SELECT p.*, d.name AS domain_name, m.firstname, m.lastname, m.employment_status, m.years_experience, p.created_at
            FROM ppe_projects p 
            JOIN ppe_domains d ON p.domain_id = d.id 
            LEFT JOIN mentors m ON p.created_by = m.id
            WHERE p.id IN ($placeholders) 
            ORDER BY d.name
        ");
        $ppeStmt->execute($registeredProjects);
        $ppeProjects = $ppeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

// Statistiques pour la section stats
if (!empty($registeredProjects)) {
    $placeholders = implode(',', array_fill(0, count($registeredProjects), '?'));
    $statsStmt = $pdo->prepare("SELECT COUNT(*) FROM ppe_projects WHERE id IN ($placeholders)");
    $statsStmt->execute($registeredProjects);
    $ppeRegisteredCount = $statsStmt->fetchColumn();
} else {
    $ppeRegisteredCount = 0;
}

$stats = [
    'ppe_registered' => $ppeRegisteredCount,
    'projects_accepted' => $pdo->query("SELECT COUNT(*) FROM applications WHERE junior_id = $juniorId AND status = 'accepted'")->fetchColumn() ?: 0,
    'trainings' => $pdo->query("SELECT COUNT(*) FROM trainings")->fetchColumn() ?: 0,
    'certifications' => $pdo->query("SELECT COUNT(*) FROM certifications WHERE junior_id = $juniorId")->fetchColumn() ?: 0,
    'opensource' => $pdo->query("SELECT COUNT(*) FROM opensource_projects")->fetchColumn() ?: 0
];

// Récupérer les encadrants pour assignation
$mentorsStmt = $pdo->query("SELECT m.*, COUNT(r.id) as project_count 
    FROM mentors m 
    LEFT JOIN ppe_registrations r ON r.mentor_id = m.id 
    GROUP BY m.id");
$mentors = $mentorsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Traitement de la candidature et inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply'])) {
        $projectId = intval($_POST['project_id']);
        if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
            $cvName = time() . '_' . basename($_FILES['cv_file']['name']);
            $cvPath = 'uploads/' . $cvName;
            move_uploaded_file($_FILES['cv_file']['tmp_name'], $cvPath);

            $stmt = $pdo->prepare("INSERT INTO applications (junior_id, project_id, cv_file, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$juniorId, $projectId, $cvPath]);
        }
        header("Location: junior_dashboard.php?section=projects");
        exit;
    } elseif (isset($_POST['register_ppe'])) {
        $projectId = intval($_POST['project_id']);
        $domainStmt = $pdo->prepare("SELECT domain_id FROM ppe_projects WHERE id = ?");
        $domainStmt->execute([$projectId]);
        $domainId = $domainStmt->fetchColumn();
        $domainQuery = $pdo->prepare("SELECT name FROM ppe_domains WHERE id = ?");
        $domainQuery->execute([$domainId]);
        $domain = $domainQuery->fetchColumn();

        if (!in_array($projectId, $registeredProjects)) {
            $mentorId = null;
            if (!empty($mentors)) {
                $randomMentor = $mentors[array_rand($mentors)];
                $mentorId = $randomMentor['id'];
            }
            $stmt = $pdo->prepare("INSERT INTO ppe_registrations (junior_id, domain, project_id, mentor_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$juniorId, $domain, $projectId, $mentorId]);
        }
        header("Location: junior_dashboard.php?section=ppe_mentor");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Junior - Elevate Junior</title>
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
            height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #1a2633 100%);
            color: white;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .sidebar .profile-pic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid #27ae60;
            overflow: hidden;
            margin: 0 auto 20px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .sidebar .profile-pic:hover {
            transform: scale(1.05);
        }

        .sidebar .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar .profile-pic input[type="file"] {
            display: none;
        }

        .sidebar h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
            color: #ecf0f1;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .sidebar a:hover, .sidebar a.active {
            background: #34495e;
        }

        .sidebar a.logout {
            background: #e74c3c;
            text-align: center;
            border-radius: 0;
            padding: 10px;
            margin-top: 20px;
        }

        .sidebar a.logout:hover {
            background: #c0392b;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-size: cover;
            background-position: center;
        }

        /* Fonds spécifiques pour chaque section */
        .main-content.stats { background-image: linear-gradient(to bottom right, #e8f5e9, #c8e6c9); }
        .main-content.projects { background-image: linear-gradient(to bottom right, #e3f2fd, #bbdefb); }
        .main-content.ppe_elevate { background-image: linear-gradient(to bottom right, #fff3e0, #ffe0b2); }
        .main-content.ppe_mentor { background-image: linear-gradient(to bottom right, #f3e5f5, #e1bee7); }
        .main-content.my_projects { background-image: linear-gradient(to bottom right, #e0f7fa, #b2ebf2); }
        .main-content.trainings { background-image: linear-gradient(to bottom right, #f9fbe7, #e6ee9c); }
        .main-content.certifications { background-image: linear-gradient(to bottom right, #efebe9, #d7ccc8); }
        .main-content.opensource { background-image: linear-gradient(to bottom right, #fafafa, #e0e0e0); }

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
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        .item.bi { border-left: 5px solid #e74c3c; }
        .item.ds { border-left: 5px solid #27ae60; }
        .item.di { border-left: 5px solid #3498db; }

        .item .new-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #27ae60;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .item .time-since {
            font-size: 0.9rem;
            color: #666;
            margin-top: 10px;
        }

        .btn {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn:hover { background: #2980b9; }
        .btn:disabled { background: #95a5a6; cursor: not-allowed; }

        h1, h2, h3 { color: #2c3e50; margin-bottom: 15px; }
        form { margin: 10px 0; }
        select { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        .stats-grid { display: flex; justify-content: space-around; flex-wrap: wrap; margin: 20px 0; }
        .stat-item { text-align: center; padding: 10px; min-width: 100px; }
        .stat-item h3 { font-size: 1.5rem; color: #27ae60; }
        .status { font-weight: bold; margin-top: 10px; }
        .status.pending { color: #f39c12; }
        .status.accepted { color: #27ae60; }
        .status.rejected { color: #e74c3c; }
        .registered { color: #27ae60; font-weight: bold; margin-top: 10px; display: block; }
        .domain-group { margin-bottom: 20px; }
    </style>
</head>
<body>
    <!-- Barre latérale -->
    <div class="sidebar">
        <div class="profile-pic">
            <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Photo de profil" id="profileImage">
            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <input type="file" name="profile_picture" id="profileUpload" accept="image/*" onchange="this.form.submit()">
            </form>
        </div>
        <h2>Elevate Junior</h2>
        <a href="?section=stats" class="<?php echo $section === 'stats' ? 'active' : ''; ?>">Statistiques</a>
        <a href="?section=projects" class="<?php echo $section === 'projects' ? 'active' : ''; ?>">Projets et Missions d'Entreprise</a>
        <a href="?section=ppe_elevate" class="<?php echo $section === 'ppe_elevate' ? 'active' : ''; ?>">PPE/Elevate Junior</a>
        <a href="?section=ppe_mentor" class="<?php echo $section === 'ppe_mentor' ? 'active' : ''; ?>">PPE/Mentor</a>
        <a href="?section=my_projects" class="<?php echo $section === 'my_projects' ? 'active' : ''; ?>">Mes Projets</a>
        <a href="?section=trainings" class="<?php echo $section === 'trainings' ? 'active' : ''; ?>">Formations</a>
        <a href="?section=certifications" class="<?php echo $section === 'certifications' ? 'active' : ''; ?>">Certifications</a>
        <a href="?section=opensource" class="<?php echo $section === 'opensource' ? 'active' : ''; ?>">Open Source</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <!-- Contenu principal -->
    <div class="main-content <?php echo htmlspecialchars($section); ?>">
        <div class="container">
            <h1>Bienvenue, <?php echo htmlspecialchars($juniorFirstname . ' ' . $juniorLastname); ?></h1>

            <!-- Statistiques (toujours visible) -->
            <div class="card">
                <h2>Statistiques</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <h3><?php echo $stats['ppe_registered']; ?></h3>
                        <p>Projets PPE inscrits</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['projects_accepted']; ?></h3>
                        <p>Projets acceptés</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['trainings']; ?></h3>
                        <p>Formations</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['certifications']; ?></h3>
                        <p>Certifications</p>
                    </div>
                    <div class="stat-item">
                        <h3><?php echo $stats['opensource']; ?></h3>
                        <p>Projets Open Source</p>
                    </div>
                </div>
            </div>

            <!-- Section dynamique -->
            <?php if ($section === 'projects'): ?>
                <div class="card" id="projects">
                    <h2>Projets et Missions d'Entreprise</h2>
                    <?php if (empty($projects)): ?>
                        <p>Aucun projet ou mission disponible pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($projects as $project): ?>
                                <div class="item">
                                    <?php
                                    $isNew = (strtotime($project['created_at']) > strtotime('-7 days'));
                                    $isAccepted = isset($appliedProjects[$project['id']]) && $appliedProjects[$project['id']] === 'accepted';
                                    if ($isNew && !$isAccepted): ?>
                                        <span class="new-badge">Nouveau</span>
                                    <?php endif; ?>
                                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($project['description']); ?></p>
                                    <p><strong>Entreprise :</strong> <?php echo htmlspecialchars($project['company_name']); ?></p>
                                    <p><strong>Pays :</strong> <?php echo htmlspecialchars($project['company_country']); ?></p>
                                    <p><strong>TJM :</strong> <?php echo number_format($project['budget'], 2); ?> €</p>
                                    <p><strong>Publié le :</strong> <?php echo date('d/m/Y H:i', strtotime($project['created_at'])); ?></p>
                                    <p class="time-since" data-created="<?php echo strtotime($project['created_at']); ?>"></p>
                                    <?php if ($project['project_file']): ?>
                                        <p><strong>Fiche :</strong> <a href="<?php echo htmlspecialchars($project['project_file']); ?>" download>Télécharger</a></p>
                                    <?php endif; ?>
                                    <?php if (isset($appliedProjects[$project['id']])): ?>
                                        <p class="status <?php echo $appliedProjects[$project['id']]; ?>">
                                            Statut : <?php echo $appliedProjects[$project['id']] === 'pending' ? 'En attente' : ($appliedProjects[$project['id']] === 'accepted' ? 'Acceptée' : 'Rejetée'); ?>
                                        </p>
                                    <?php else: ?>
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                            <input type="file" name="cv_file" accept=".pdf" required>
                                            <button type="submit" name="apply" class="btn">Postuler</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'ppe_elevate'): ?>
                <div class="card" id="ppe_elevate">
                    <h2>PPE/Elevate Junior</h2>
                    <h3>Domaines disponibles</h3>
                    <div class="grid">
                        <?php foreach ($ppeDomains as $domain => $count): ?>
                            <div class="item <?php echo strtolower(str_replace(' ', '-', $domain)); ?>">
                                <h3><?php echo htmlspecialchars($domain); ?></h3>
                                <p><strong>Nombre de projets :</strong> <?php echo $count; ?></p>
                                <?php if (in_array($domain, $registeredDomains)): ?>
                                    <p class="registered">Inscrit</p>
                                <?php endif; ?>
                                <form method="POST">
                                    <input type="hidden" name="domain" value="<?php echo $domain; ?>">
                                    <button type="submit" name="register_ppe" class="btn" <?php echo in_array($domain, $registeredDomains) ? 'disabled' : ''; ?>>S'inscrire</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($section === 'ppe_mentor'): ?>
                <div class="card" id="ppe_mentor">
                    <h2>PPE/Mentor</h2>
                    <h3>Projets disponibles</h3>
                    <?php if (empty($ppeProjects)): ?>
                        <p>Aucun projet disponible pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($ppeProjects as $project): ?>
                                <div class="item <?php echo strtolower(str_replace(' ', '-', $project['domain_name'])); ?>">
                                    <?php
                                    $isNew = (strtotime($project['created_at']) > strtotime('-7 days'));
                                    $isRegistered = in_array($project['id'], $registeredProjects);
                                    if ($isNew && !$isRegistered): ?>
                                        <span class="new-badge">Nouveau</span>
                                    <?php endif; ?>
                                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($project['description']); ?></p>
                                    <p><strong>Domaine :</strong> <?php echo htmlspecialchars($project['domain_name']); ?></p>
                                    <?php if ($project['firstname'] && $project['lastname']): ?>
                                        <p><strong>Mentor :</strong> <?php echo htmlspecialchars($project['firstname'] . ' ' . $project['lastname']); ?></p>
                                        <p><strong>Statut :</strong> <?php echo htmlspecialchars($project['employment_status']); ?></p>
                                        <p><strong>Années d'expérience :</strong> <?php echo htmlspecialchars($project['years_experience']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($project['project_file']): ?>
                                        <p><strong>Fichier :</strong> <a href="<?php echo htmlspecialchars($project['project_file']); ?>" download>Télécharger</a></p>
                                    <?php endif; ?>
                                    <form method="POST">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" name="register_ppe" class="btn" <?php echo in_array($project['id'], $registeredProjects) ? 'disabled' : ''; ?>>
                                            <?php echo in_array($project['id'], $registeredProjects) ? 'Inscrit' : 'S\'inscrire'; ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'my_projects'): ?>
                <div class="card" id="my_projects">
                    <h2>Mes Projets</h2>
                    <?php if (empty($registeredProjects)): ?>
                        <p>Vous n'êtes inscrit à aucun projet PPE. Inscrivez-vous dans 'PPE/Mentor' pour voir vos projets.</p>
                    <?php else: ?>
                        <?php
                        $projectsByDomain = [];
                        $mentorsByRegistration = [];
                        foreach ($ppeProjects as $project) {
                            $projectsByDomain[$project['domain_name']][] = $project;
                        }
                        $mentorStmt = $pdo->prepare("SELECT r.project_id, m.* FROM ppe_registrations r LEFT JOIN mentors m ON r.mentor_id = m.id WHERE r.junior_id = ?");
                        $mentorStmt->execute([$juniorId]);
                        $mentorsData = $mentorStmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($mentorsData as $data) {
                            $mentorsByRegistration[$data['project_id']] = $data;
                        }
                        foreach ($projectsByDomain as $domain => $domainProjects):
                            foreach ($domainProjects as $project):
                                if (in_array($project['id'], $registeredProjects)):
                        ?>
                            <div class="domain-group">
                                <h3><?php echo htmlspecialchars($domain); ?></h3>
                                <?php if (isset($mentorsByRegistration[$project['id']]) && !empty($mentorsByRegistration[$project['id']]['id'])): 
                                    $mentor = $mentorsByRegistration[$project['id']]; ?>
                                    <div class="card">
                                        <h3>Encadrant</h3>
                                        <p><strong>Prénom :</strong> <?php echo htmlspecialchars($mentor['firstname']); ?></p>
                                        <p><strong>Nom :</strong> <?php echo htmlspecialchars($mentor['lastname']); ?></p>
                                        <p><strong>Email :</strong> <?php echo htmlspecialchars($mentor['email']); ?></p>
                                        <p><strong>Années d'expérience :</strong> <?php echo htmlspecialchars($mentor['years_experience']); ?></p>
                                        <p><strong>Domaine d'activité :</strong> <?php echo htmlspecialchars($mentor['activity_domain']); ?></p>
                                        <p><strong>Entreprise :</strong> <?php echo htmlspecialchars($mentor['company']); ?></p>
                                        <p><strong>Statut :</strong> <?php echo htmlspecialchars($mentor['employment_status']); ?></p>
                                    </div>
                                <?php endif; ?>
                                <div class="grid">
                                    <div class="item <?php echo strtolower(str_replace(' ', '-', $project['domain_name'])); ?>">
                                        <?php
                                        // Pas de badge "Nouveau" car le projet est inscrit
                                        ?>
                                        <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($project['description']); ?></p>
                                        <?php if ($project['project_file']): ?>
                                            <p><strong>Fichier :</strong> <a href="<?php echo htmlspecialchars($project['project_file']); ?>" download>Télécharger</a></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if (empty($ppeProjects)): ?>
                            <p>Aucun projet dans vos projets inscrits pour le moment.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'trainings'): ?>
                <div class="card" id="trainings">
                    <h2>Formations</h2>
                    <?php if (empty($trainings)): ?>
                        <p>Aucune formation disponible pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($trainings as $training): ?>
                                <div class="item">
                                    <h3><?php echo htmlspecialchars($training['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($training['description']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'certifications'): ?>
                <div class="card" id="certifications">
                    <h2>Vos certifications</h2>
                    <?php if (empty($certifications)): ?>
                        <p>Aucune certification obtenue pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($certifications as $cert): ?>
                                <div class="item">
                                    <h3><?php echo htmlspecialchars($cert['title']); ?></h3>
                                    <p><strong>Obtenue le :</strong> <?php echo date('d/m/Y', strtotime($cert['obtained_at'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($section === 'opensource'): ?>
                <div class="card" id="opensource">
                    <h2>Projets Open Source</h2>
                    <?php if (empty($opensource)): ?>
                        <p>Aucun projet open source disponible pour le moment.</p>
                    <?php else: ?>
                        <div class="grid">
                            <?php foreach ($opensource as $project): ?>
                                <div class="item">
                                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($project['description']); ?></p>
                                    <p><strong>Repository :</strong> <a href="<?php echo htmlspecialchars($project['repository_url']); ?>" target="_blank"><?php echo htmlspecialchars($project['repository_url']); ?></a></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Activer le clic sur la photo pour uploader
        document.getElementById('profileImage').addEventListener('click', function() {
            document.getElementById('profileUpload').click();
        });

        // Compteur de temps pour les projets
        document.querySelectorAll('.time-since').forEach(function(element) {
            const createdTimestamp = parseInt(element.getAttribute('data-created')) * 1000; // Convertir en millisecondes
            function updateTimeSince() {
                const now = new Date().getTime();
                const diff = now - createdTimestamp;
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                element.textContent = `Depuis : ${days}j ${hours}h ${minutes}m`;
            }
            updateTimeSince();
            setInterval(updateTimeSince, 60000); // Mettre à jour toutes les minutes
        });
    </script>
</body>
</html>