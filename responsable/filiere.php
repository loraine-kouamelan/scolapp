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

$message_action = '';

$idNiveau = isset($_SESSION['id_niveau']) ? (int)$_SESSION['id_niveau'] : 0;
if($idNiveau <= 0){
    $stmtN = $pdo->prepare("SELECT id_niveau FROM responsable WHERE id_responsable=:id LIMIT 1");
    $stmtN->execute(['id'=>$_SESSION['id']]);
    $r = $stmtN->fetch(PDO::FETCH_ASSOC);
    $idNiveau = $r && $r['id_niveau'] !== null ? (int)$r['id_niveau'] : 0;
    $_SESSION['id_niveau'] = $idNiveau > 0 ? $idNiveau : null;
}

if(isset($_POST['supprimer_filiere'])){
    $id = isset($_POST['id_filiere']) ? (int)$_POST['id_filiere'] : 0;
    if($id > 0){
        try {
            $stmt = $pdo->prepare("DELETE FROM filiere WHERE id_filiere=:id");
            $stmt->execute(['id'=>$id]);
            $message_action = "Filière supprimée.";
        } catch (PDOException $e){
            $message_action = "Impossible de supprimer cette filière (elle est utilisée ailleurs).";
        }
    }
}

if(isset($_POST['modifier_filiere'])){
    $id = isset($_POST['id_filiere']) ? (int)$_POST['id_filiere'] : 0;
    $nom = isset($_POST['nom_filiere']) ? trim($_POST['nom_filiere']) : '';
    if($id > 0 && $nom !== ''){
        $stmtLib = $pdo->prepare("SELECT libelle_niveau FROM niveau WHERE id_niveau=:id LIMIT 1");
        $stmtLib->execute(['id'=>$idNiveau]);
        $niv = $stmtLib->fetch(PDO::FETCH_ASSOC);
        $lib = $niv && $niv['libelle_niveau'] ? trim((string)$niv['libelle_niveau']) : '';
        if($lib !== ''){
            $nom = $lib.' '.ltrim($nom);
        }
        $stmt = $pdo->prepare("UPDATE filiere SET nom_filiere=:nom WHERE id_filiere=:id");
        $stmt->execute(['nom'=>$nom,'id'=>$id]);
        $message_action = "Filière modifiée.";
    } else {
        $message_action = "Veuillez renseigner un nom valide.";
    }
}

if(isset($_POST['creer_filiere'])){
    $stmtLib = $pdo->prepare("SELECT libelle_niveau FROM niveau WHERE id_niveau=:id LIMIT 1");
    $stmtLib->execute(['id'=>$idNiveau]);
    $niv = $stmtLib->fetch(PDO::FETCH_ASSOC);
    $lib = $niv && $niv['libelle_niveau'] ? trim((string)$niv['libelle_niveau']) : '';
    $nom = trim((string)($_POST['nom_filiere'] ?? ''));
    if($lib !== ''){
        $nom = $lib.' '.ltrim($nom);
    }
    $stmt = $pdo->prepare("INSERT INTO filiere (nom_filiere) VALUES (:nom)");
    $stmt->execute(['nom'=>$nom]);
}

$filiereEdit = null;
if(isset($_GET['edit'])){
    $idEdit = (int)$_GET['edit'];
    if($idEdit > 0){
        $stmt = $pdo->prepare("SELECT * FROM filiere WHERE id_filiere=:id LIMIT 1");
        $stmt->execute(['id'=>$idEdit]);
        $filiereEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$stmtFilieres = $pdo->prepare("
    SELECT DISTINCT f.*
    FROM filiere f
    JOIN classe c ON c.id_filiere=f.id_filiere
    WHERE c.id_niveau=:idNiveau
    ORDER BY f.nom_filiere
");
$stmtFilieres->execute(['idNiveau'=>$idNiveau]);
$filieres = $stmtFilieres->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>ScolApp - Responsable - Filières</title>
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
            <a class="icon-btn" href="profil.php" title="Profil" aria-label="Profil">
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
        <a href="parametres.php" class="<?= $currentPage === 'parametres.php' ? 'active' : '' ?>">Paramètres</a>
        <a href="moyennes.php" class="<?= $currentPage === 'moyennes.php' ? 'active' : '' ?>">Moyennes</a>
        <a href="notes.php" class="<?= $currentPage === 'notes.php' ? 'active' : '' ?>">Notes / Résultats</a>
        <div class="spacer"></div>
        <a href="../index.php">Accueil</a>
        <a class="btn btn-danger" href="?logout=1">Déconnexion</a>
    </aside>

    <main class="main">
        <div class="container">
            <div class="dash-topbar">
                <div class="dash-title">
                    <h1>Filières</h1>
                    <p>Gestion des filières</p>
                </div>
                <div class="dash-actions">
                    <a class="btn btn-secondary" href="parametres.php">Retour Paramètres</a>
                    <span class="dash-pill">Total: <?= is_array($filieres) ? count($filieres) : 0 ?></span>
                </div>
            </div>

            <?php if($message_action !== ''): ?>
                <div class="card" style="margin-top:16px;">
                    <p><?= htmlspecialchars($message_action) ?></p>
                </div>
            <?php endif; ?>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <?php if($filiereEdit): ?>
                    <div class="card">
                        <h2>Modifier une filière</h2>
                        <form method="post">
                            <input type="hidden" name="id_filiere" value="<?= (int)$filiereEdit['id_filiere'] ?>">
                            <input type="text" name="nom_filiere" value="<?= htmlspecialchars($filiereEdit['nom_filiere']) ?>" required>
                            <div class="auth-actions">
                                <button class="btn btn-primary" name="modifier_filiere" type="submit">Enregistrer</button>
                                <a class="btn btn-secondary" href="filiere.php">Annuler</a>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <h2 style="margin:0;">Liste des filières</h2>
                            <button class="btn btn-primary" type="button" id="btnAddFiliere">Ajouter</button>
                        </div>

                        <div id="boxAddFiliere" style="display:none; margin-top:12px;">
                            <form method="post">
                                <input type="text" name="nom_filiere" placeholder="Nom filière" required>
                                <div class="auth-actions">
                                    <button class="btn btn-primary" name="creer_filiere" type="submit">Créer</button>
                                </div>
                            </form>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($filieres as $f): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($f['nom_filiere']) ?></td>
                                        <td>
                                            <a class="btn btn-secondary" href="filiere.php?edit=<?= (int)$f['id_filiere'] ?>">Modifier</a>
                                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Supprimer cette filière ?');">
                                                <input type="hidden" name="id_filiere" value="<?= (int)$f['id_filiere'] ?>">
                                                <button class="btn btn-danger" name="supprimer_filiere" type="submit">Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../app.js"></script>
<script>
    (function(){
        var btn = document.getElementById('btnAddFiliere');
        var box = document.getElementById('boxAddFiliere');
        if(!btn || !box){
            return;
        }
        function sync(){
            var open = box.style.display !== 'none' && box.style.display !== '';
            btn.textContent = open ? 'Fermer' : 'Ajouter';
            btn.classList.toggle('btn-secondary', open);
            btn.classList.toggle('btn-primary', !open);
        }
        btn.addEventListener('click', function(){
            var open = box.style.display !== 'none' && box.style.display !== '';
            box.style.display = open ? 'none' : 'block';
            sync();
        });
        sync();
    })();
</script>
</body>
</html>
