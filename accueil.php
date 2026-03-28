<?php
session_start();

if(isset($_GET['logout'])){
    $_SESSION = [];
    if(ini_get('session.use_cookies')){
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: accueil.php');
    exit();
}

$isLogged = isset($_SESSION['id'], $_SESSION['role']);
$dashboardUrl = 'roles.php';
if($isLogged){
    if($_SESSION['role'] === 'RESPONSABLE'){
        $dashboardUrl = 'pricipal/tb_principal.php';
    } elseif($_SESSION['role'] === 'ENSEIGNANT'){
        $dashboardUrl = 'enseignant/selection.php';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="landing-body">
<header class="landing-header">
    <div class="landing-nav">
        <a class="landing-brand" href="accueil.php">
            <span class="landing-brand-logo"><img src="image/logo.jpeg" alt="Logo"></span>
            <span class="landing-brand-name">ScolApp</span>
        </a>
        <nav class="landing-links">
            <a href="#features">Fonctionnalités</a>
            <a href="#how">Comment ça marche</a>
        </nav>
        <div class="landing-actions">
            <?php if($isLogged): ?>
                <a class="btn btn-secondary" href="<?= htmlspecialchars($dashboardUrl) ?>">Mon espace</a>
                <a class="btn btn-secondary" href="?logout=1">Déconnexion</a>
            <?php else: ?>
                <a class="btn btn-secondary" href="roles.php">Accéder</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="landing-main">
    <section class="landing-hero">
        <div class="landing-hero-content">
            <h1>Gérez votre établissement avec <span class="landing-accent">ScolApp</span>.</h1>
            <p>Une plateforme simple et moderne pour le suivi des étudiants, des notes, des moyennes et des absences.</p>
            <div class="landing-hero-cta">
                <a class="btn btn-primary" href="<?= htmlspecialchars($isLogged ? $dashboardUrl : 'roles.php') ?>"><?= $isLogged ? 'Continuer' : 'Commencer' ?></a>
                <a class="btn btn-secondary" href="#features">Découvrir</a>
            </div>
        </div>
        <div class="landing-hero-visual" aria-hidden="true">
            <div class="landing-orb"></div>
            <div class="landing-card">
                <div class="landing-card-head">
                    <span class="landing-pill">Tableau de bord</span>
                    <span class="landing-pill">Suivi</span>
                </div>
                <div class="landing-card-body">
                    <div class="landing-stat">
                        <div class="landing-stat-label">Classes</div>
                        <div class="landing-stat-value">12</div>
                    </div>
                    <div class="landing-stat">
                        <div class="landing-stat-label">Étudiants</div>
                        <div class="landing-stat-value">340</div>
                    </div>
                    <div class="landing-stat">
                        <div class="landing-stat-label">Moyennes</div>
                        <div class="landing-stat-value">Auto</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="landing-section">
        <div class="landing-section-title">
            <h2>Fonctionnalités</h2>
            <p>Tout ce dont vous avez besoin, organisé et accessible.</p>
        </div>
        <div class="landing-features">
            <div class="landing-feature">
                <div class="landing-feature-icon">📚</div>
                <h3>Gestion</h3>
                <p>Filières, classes, matières, étudiants — tout au même endroit.</p>
            </div>
            <div class="landing-feature">
                <div class="landing-feature-icon">📊</div>
                <h3>Notes & Moyennes</h3>
                <p>Calcul et consultation rapides, filtrés automatiquement selon le niveau.</p>
            </div>
            <div class="landing-feature">
                <div class="landing-feature-icon">✅</div>
                <h3>Absences</h3>
                <p>Suivi des absences et accès simplifié pour les enseignants.</p>
            </div>
        </div>
    </section>

    <section id="how" class="landing-section">
        <div class="landing-section-title">
            <h2>Comment ça marche</h2>
            <p>Accédez à votre espace en un clic.</p>
        </div>
        <div class="landing-steps">
            <div class="landing-step">
                <div class="landing-step-num">1</div>
                <div class="landing-step-text">Cliquez sur <strong>Commencer</strong>.</div>
            </div>
            <div class="landing-step">
                <div class="landing-step-num">2</div>
                <div class="landing-step-text">Choisissez votre rôle (Responsable/Enseignant).</div>
            </div>
            <div class="landing-step">
                <div class="landing-step-num">3</div>
                <div class="landing-step-text">Connectez-vous et gérez votre niveau.</div>
            </div>
        </div>
    </section>
</main>

<footer class="landing-footer">
    <div class="landing-footer-inner">
        <span>© <?= date('Y') ?> ScolApp</span>
        <a href="index.php">Accéder</a>
    </div>
</footer>
</body>
</html>
