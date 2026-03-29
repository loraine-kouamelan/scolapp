<?php
session_start();
require '../bd.php';

if(!isset($_SESSION['id']) || $_SESSION['role'] != 'RESPONSABLE'){
    header("Location: connexion_principal.php");
    exit();
}

if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ../index.php");
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);

$message = '';

$stmt = $pdo->prepare("SELECT id_responsable, nom_responsable, prenom_responsable, mdp_responsable, id_niveau FROM responsable WHERE id_responsable=:id LIMIT 1");
$stmt->execute(['id'=>$_SESSION['id']]);
$profil = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$profil){
    session_destroy();
    header("Location: connexion_principal.php");
    exit();
}

$niveauLibelle = '';
if($profil['id_niveau'] !== null){
    $stmtN = $pdo->prepare("SELECT libelle_niveau FROM niveau WHERE id_niveau=:id LIMIT 1");
    $stmtN->execute(['id'=>(int)$profil['id_niveau']]);
    $n = $stmtN->fetch(PDO::FETCH_ASSOC);
    $niveauLibelle = $n ? (string)$n['libelle_niveau'] : '';
}

if(isset($_POST['enregistrer_profil'])){
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';

    $mdpActuel = isset($_POST['mdp_actuel']) ? (string)$_POST['mdp_actuel'] : '';
    $nouveauMdp = isset($_POST['nouveau_mdp']) ? (string)$_POST['nouveau_mdp'] : '';
    $confirmer = isset($_POST['confirmer_mdp']) ? (string)$_POST['confirmer_mdp'] : '';

    if($nom === '' || $prenom === ''){
        $message = "Veuillez renseigner le nom et le prénom.";
    } else {
        try {
            if($nouveauMdp !== '' || $confirmer !== ''){
                if($mdpActuel === ''){
                    $message = "Veuillez renseigner le mot de passe actuel.";
                } else if(!password_verify($mdpActuel, $profil['mdp_responsable'])){
                    $message = "Mot de passe actuel incorrect.";
                } else if($nouveauMdp === '' || $confirmer === ''){
                    $message = "Veuillez renseigner et confirmer le nouveau mot de passe.";
                } else if($nouveauMdp !== $confirmer){
                    $message = "La confirmation du mot de passe ne correspond pas.";
                } else {
                    $stmtU = $pdo->prepare("UPDATE responsable SET nom_responsable=:nom, prenom_responsable=:prenom, mdp_responsable=:mdp WHERE id_responsable=:id");
                    $stmtU->execute([
                        'nom'=>$nom,
                        'prenom'=>$prenom,
                        'mdp'=>password_hash($nouveauMdp, PASSWORD_DEFAULT),
                        'id'=>$_SESSION['id']
                    ]);
                    $message = "Profil mis à jour.";
                    $profil['nom_responsable'] = $nom;
                    $profil['prenom_responsable'] = $prenom;
                    $profil['mdp_responsable'] = password_hash($nouveauMdp, PASSWORD_DEFAULT);
                }
            } else {
                $stmtU = $pdo->prepare("UPDATE responsable SET nom_responsable=:nom, prenom_responsable=:prenom WHERE id_responsable=:id");
                $stmtU->execute([
                    'nom'=>$nom,
                    'prenom'=>$prenom,
                    'id'=>$_SESSION['id']
                ]);
                $message = "Profil mis à jour.";
                $profil['nom_responsable'] = $nom;
                $profil['prenom_responsable'] = $prenom;
            }
        } catch (PDOException $e){
            $message = "Impossible de mettre à jour le profil.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp - Responsable - Profil</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-header">
    <div class="app-header-inner">
        <a class="app-brand" href="../index.php">
            <div class="app-logo"><img src="../image/logo.jpeg" alt="Logo"></div>
            <div class="app-brand-text">
                <div class="app-name">ScolApp</div>
                <div class="app-subname">Responsable</div>
            </div>
        </a>

        <div class="app-header-actions">
            <button class="sidebar-toggle" type="button" aria-label="Menu" title="Menu">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 6h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M4 12h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
            <a class="icon-btn" href="#" title="Profil" aria-label="Profil">
                <span class="icon-label">Profil</span>
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 13a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>
    </div>
</div>
<div class="app-shell">
    <aside class="sidebar">
        <a href="tb_principal.php" class="<?= $currentPage === 'tb_principal.php' ? 'active' : '' ?>">Tableau de bord</a>
        <a href="moyennes.php" class="<?= $currentPage === 'moyennes.php' ? 'active' : '' ?>">Moyennes</a>
        <a href="notes.php" class="<?= $currentPage === 'notes.php' ? 'active' : '' ?>">Notes / Résultats</a>
        <a href="parametres.php" class="<?= $currentPage === 'parametres.php' ? 'active' : '' ?>">Paramètres</a>
        <div class="spacer"></div>
        <a class="btn btn-danger" href="?logout=1">Déconnexion</a>
    </aside>

    <main class="main">
        <div class="container">
            <div class="dash-topbar">
                <div class="dash-title">
                    <h1>Profil</h1>
                    <p>Modifier vos informations</p>
                </div>
                <div class="dash-actions">
                    <span class="dash-pill"><?= htmlspecialchars($niveauLibelle !== '' ? $niveauLibelle : 'Niveau') ?></span>
                </div>
            </div>

            <?php if($message !== ''): ?>
                <div class="card" style="margin-top:16px;">
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="card">
                    <h2>Informations</h2>
                    <form method="post">
                        <label>Nom</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($profil['nom_responsable']) ?>" required>

                        <label>Prénom</label>
                        <input type="text" name="prenom" value="<?= htmlspecialchars($profil['prenom_responsable']) ?>" required>

                        <h2 style="margin-top:12px;">Changer le mot de passe</h2>

                        <label>Mot de passe actuel</label>
                        <input type="password" name="mdp_actuel" placeholder="Mot de passe actuel">

                        <label>Nouveau mot de passe</label>
                        <input type="password" name="nouveau_mdp" placeholder="Nouveau mot de passe">

                        <label>Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirmer_mdp" placeholder="Confirmer">

                        <div class="auth-actions">
                            <button class="btn btn-primary" name="enregistrer_profil" type="submit">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../app.js"></script>
</body>
</html>
