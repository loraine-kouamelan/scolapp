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
        $dashboardUrl = 'responsable/tb_principal.php';
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
<title>ScolApp - Accueil</title>
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
            <p>Une plateforme simple et moderne pour la gestion des étudiants, des notes, des moyennes et des absences.</p>
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
                    <span class="landing-pill">Gestion</span>
                </div>
                <div class="landing-card-body">
                    <div class="landing-stat">
                        <div class="landing-stat-label">Filières</div>
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
                <div class="landing-feature-icon">1</div>
                <h3>Gestion</h3>
                <p>Filières, classes, matières, étudiants — tout au même endroit.</p>
            </div>
            <div class="landing-feature">
                <div class="landing-feature-icon">2</div>
                <h3>Notes & Moyennes</h3>
                <p>Calcul et consultation rapides, filtrés automatiquement selon le niveau.</p>
            </div>
            <div class="landing-feature">
                <div class="landing-feature-icon">3</div>
                <h3>Absences</h3>
                <p>Gestion des absences et accès simplifié pour les enseignants.</p>
            </div>
        </div>
    </section>

    <section id="how" class="landing-section">
        <div class="landing-section-title">
            <h2>Comment ça marche</h2>
            <p>Suivez le parcours selon votre rôle pour utiliser l'application efficacement.</p>
        </div>
        <div class="landing-features" style="margin-bottom:18px;">
            <div class="landing-feature">
                <div class="landing-feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3l8 4v6c0 5-3.5 9-8 10-4.5-1-8-5-8-10V7l8-4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M9 12l2 2 4-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3>Responsable</h3>
                <p><strong>1.</strong> Se connecter (Responsable).</p>
                <p><strong>2.</strong> Paramétrer les classes et les matières.</p>
                <p><strong>3.</strong> Ajouter les étudiants et vérifier les listes.</p>
                <p><strong>4.</strong> Consulter les notes, résultats et moyennes.</p>
            </div>
            <div class="landing-feature">
                <div class="landing-feature-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 18v-8a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M21 18H3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M8 7l4-4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3>Enseignant</h3>
                <p><strong>1.</strong> Se connecter (Enseignant).</p>
                <p><strong>2.</strong> Choisir la filière, la classe et la matière.</p>
                <p><strong>3.</strong> Saisir les notes et les absences .</p>
                <p><strong>4.</strong> Consulter les moyennes et imprimer si besoin.</p>
            </div>
        </div>

        <div class="landing-alert">
            <h3 class="landing-alert-title">Important</h3>
            <p><strong>Enseignant :</strong> vous pouvez exécuter vos tâches uniquement si le <strong>Responsable</strong> a déjà paramétré les <strong>classes</strong>, <strong>matières</strong> et <strong>étudiants</strong>.</p>
            <p><strong>Responsable :</strong> l'affichage des <strong>notes</strong>, <strong>résultats</strong> et <strong>moyennes</strong> dépend des saisies effectuées par l'<strong>Enseignant</strong>.</p>
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
