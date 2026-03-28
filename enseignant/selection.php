<?php
session_start();
require '../bd.php';

if(!isset($_SESSION['id']) || $_SESSION['role'] != 'ENSEIGNANT'){
    header("Location: connexion_enseignant.php");
    exit();
}

if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ../index.php");
    exit();
}

if(isset($_GET['reset'])){
    unset($_SESSION['idFiliere'], $_SESSION['idClasse'], $_SESSION['idMatiere']);
    unset($_SESSION['selection_validated']);
    header("Location: selection.php");
    exit();
}

if(isset($_GET['edit'])){
    unset($_SESSION['selection_validated']);
    header("Location: selection.php");
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);

if(isset($_POST['choisir'])){
    $_SESSION['idFiliere'] = $_POST['idFiliere'];
    $_SESSION['idClasse'] = $_POST['idClasse'];
    $_SESSION['idMatiere'] = $_POST['idMatiere'];
    $_SESSION['selection_validated'] = 1;
    header("Location: selection.php");
    exit();
}

$stmtEns = $pdo->prepare("SELECT nom_enseignant, prenom_enseignant FROM enseignant WHERE id_enseignant=:id LIMIT 1");
$stmtEns->execute(['id'=>(int)$_SESSION['id']]);
$ensInfo = $stmtEns->fetch(PDO::FETCH_ASSOC);
$enseignantNom = $ensInfo ? trim(($ensInfo['nom_enseignant'] ?? '').' '.($ensInfo['prenom_enseignant'] ?? '')) : '';

$idFiliere = isset($_POST['idFiliere']) ? $_POST['idFiliere'] : (isset($_SESSION['idFiliere']) ? $_SESSION['idFiliere'] : null);
$idClasse = isset($_POST['idClasse']) ? $_POST['idClasse'] : (isset($_SESSION['idClasse']) ? $_SESSION['idClasse'] : null);
$idMatiere = isset($_POST['idMatiere']) ? $_POST['idMatiere'] : (isset($_SESSION['idMatiere']) ? $_SESSION['idMatiere'] : null);

$selectionValidated = !empty($_SESSION['selection_validated']);

$filieres = $pdo->query("SELECT * FROM filiere ORDER BY nom_filiere")->fetchAll(PDO::FETCH_ASSOC);

$classes = [];
if($idFiliere){
    $stmt = $pdo->prepare("SELECT * FROM classe WHERE id_filiere=:idFiliere ORDER BY nom_classe");
    $stmt->execute(['idFiliere'=>$idFiliere]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$matieres = [];
if($idClasse){
    $stmtClasse = $pdo->prepare("SELECT id_filiere FROM classe WHERE id_classe=:idClasse LIMIT 1");
    $stmtClasse->execute(['idClasse'=>$idClasse]);
    $classeInfo = $stmtClasse->fetch(PDO::FETCH_ASSOC);
    $idFiliereClasse = $classeInfo ? (int)$classeInfo['id_filiere'] : 0;

    $stmt = $pdo->prepare("
        SELECT m.*
        FROM matiere m
        JOIN enseignement en ON en.id_matiere=m.id_matiere
        WHERE m.id_filiere=:idFiliere AND en.id_enseignant=:idEnseignant
        ORDER BY m.nom_matiere
    ");
    $stmt->execute(['idFiliere'=>$idFiliereClasse, 'idEnseignant'=>$_SESSION['id']]);
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(!$matieres){
        $stmt = $pdo->prepare("SELECT * FROM matiere WHERE id_filiere=:idFiliere ORDER BY nom_matiere");
        $stmt->execute(['idFiliere'=>$idFiliereClasse]);
        $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$filiereNom = '-';
if($idFiliere){
    $stmt = $pdo->prepare("SELECT nom_filiere FROM filiere WHERE id_filiere=:id LIMIT 1");
    $stmt->execute(['id'=>(int)$idFiliere]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if($row && $row['nom_filiere'] !== null){
        $filiereNom = (string)$row['nom_filiere'];
    }
}

$classeNom = '-';
if($idClasse){
    $stmt = $pdo->prepare("SELECT nom_classe FROM classe WHERE id_classe=:id LIMIT 1");
    $stmt->execute(['id'=>(int)$idClasse]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if($row && $row['nom_classe'] !== null){
        $classeNom = (string)$row['nom_classe'];
    }
}

$matiereNom = '-';
if($idMatiere){
    $stmt = $pdo->prepare("SELECT nom_matiere FROM matiere WHERE id_matiere=:id LIMIT 1");
    $stmt->execute(['id'=>(int)$idMatiere]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if($row && $row['nom_matiere'] !== null){
        $matiereNom = (string)$row['nom_matiere'];
    }
}

$notesParEtudiant = '-';
if($idClasse && $idMatiere){
    try{
        $stmt = $pdo->query("SHOW TABLES LIKE 'notes_historique'");
        $hasHistorique = $stmt && $stmt->fetch();
    }catch(Exception $e){
        $hasHistorique = false;
    }

    if($hasHistorique){
        $stmtMaxNotes = $pdo->prepare("\n            SELECT COALESCE(MAX(t.cnt), 0) AS nb\n            FROM (\n                SELECT nh.id_etudiant, COUNT(*) AS cnt\n                FROM notes_historique nh\n                JOIN etudiant e ON e.id_etudiant=nh.id_etudiant\n                WHERE e.id_classe=:idClasse AND nh.id_matiere=:idMatiere\n                GROUP BY nh.id_etudiant\n            ) t\n        ");
        $stmtMaxNotes->execute(['idClasse'=>(int)$idClasse, 'idMatiere'=>(int)$idMatiere]);
        $rowNotes = $stmtMaxNotes->fetch(PDO::FETCH_ASSOC);
        $notesParEtudiant = $rowNotes ? (string)(int)$rowNotes['nb'] : '0';
    }else{
        $stmtMaxNotes = $pdo->prepare("\n            SELECT COALESCE(MAX(CASE WHEN s.note IS NOT NULL THEN 1 ELSE 0 END), 0) AS nb\n            FROM etudiant e\n            LEFT JOIN suivi s ON s.id_etudiant=e.id_etudiant AND s.id_matiere=:idMatiere\n            WHERE e.id_classe=:idClasse\n        ");
        $stmtMaxNotes->execute(['idClasse'=>(int)$idClasse, 'idMatiere'=>(int)$idMatiere]);
        $rowNotes = $stmtMaxNotes->fetch(PDO::FETCH_ASSOC);
        $notesParEtudiant = $rowNotes ? (string)(int)$rowNotes['nb'] : '0';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>ScolApp - Enseignant - Tableau de bord</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-header">
    <div class="app-header-inner">
        <a class="app-brand" href="../index.php">
            <div class="app-logo"><img src="../image/logo.jpeg" alt="Logo"></div>
            <div class="app-brand-text">
                <div class="app-name">ScolApp</div>
                <div class="app-subname">Enseignant</div>
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
        <a href="selection.php" class="<?= $currentPage === 'selection.php' ? 'active' : '' ?>">Tableau de bord</a>
        <a href="note.php" class="<?= $currentPage === 'note.php' ? 'active' : '' ?>">Notes</a>
        <a href="absence.php" class="<?= $currentPage === 'absence.php' ? 'active' : '' ?>">Absences</a>
        <a href="moyennes.php" class="<?= $currentPage === 'moyennes.php' ? 'active' : '' ?>">Moyennes</a>
        <div class="spacer"></div>
        <a href="../index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Accueil</a>
        <a class="btn btn-danger" href="?logout=1">Déconnexion</a>
    </aside>

    <main class="main">
        <div class="container">
            <div class="dash-topbar">
                <div class="dash-title">
                    <h1>Dashboard</h1>
                    <p>Choisis la filière, la classe et la matière.</p>
                </div>
                <div class="dash-actions">
                    <span class="dash-pill"><?= htmlspecialchars($enseignantNom !== '' ? $enseignantNom : ('Enseignant #'.(int)$_SESSION['id'])) ?></span>
                </div>
            </div>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <div class="card">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                            <h2 style="margin:0;">Sélection</h2>
                            <?php if($selectionValidated && ($idFiliere || $idClasse || $idMatiere)): ?>
                                <div style="display:inline-flex; gap:10px; align-items:center;">
                                    <a class="btn btn-secondary" href="?edit=1">Modifier</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if(!$selectionValidated): ?>
                        <form method="post">
                            <label>Filière</label>
                            <select name="idFiliere" onchange="this.form.submit()" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach($filieres as $f): ?>
                                    <option value="<?= $f['id_filiere'] ?>" <?= ($idFiliere==$f['id_filiere']) ? 'selected' : '' ?>><?= htmlspecialchars($f['nom_filiere']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label>Classe</label>
                            <select name="idClasse" onchange="this.form.submit()" <?= $idFiliere ? '' : 'disabled' ?> required>
                                <option value="">-- Choisir --</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= $c['id_classe'] ?>" <?= ($idClasse==$c['id_classe']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nom_classe']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label>Matière</label>
                            <select name="idMatiere" <?= $idClasse ? '' : 'disabled' ?> required>
                                <option value="">-- Choisir --</option>
                                <?php foreach($matieres as $m): ?>
                                    <option value="<?= $m['id_matiere'] ?>" <?= ($idMatiere==$m['id_matiere']) ? 'selected' : '' ?>><?= htmlspecialchars($m['nom_matiere']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <div class="auth-actions">
                                <button class="btn btn-primary" name="choisir" type="submit">Valider</button>
                            </div>
                        </form>
                        <?php else: ?>
                            <div class="stat-grid" style="margin-top:12px;">
                                <div class="stat">
                                    <div class="label">Filière</div>
                                    <div class="value"><?= htmlspecialchars($filiereNom) ?></div>
                                </div>
                                <div class="stat">
                                    <div class="label">Classe</div>
                                    <div class="value"><?= htmlspecialchars($classeNom) ?></div>
                                </div>
                                <div class="stat">
                                    <div class="label">Matière</div>
                                    <div class="value"><?= htmlspecialchars($matiereNom) ?></div>
                                </div>
                                <div class="stat">
                                    <div class="label">Nombre de notes</div>
                                    <div class="value"><?= htmlspecialchars((string)$notesParEtudiant) ?></div>
                                </div>
                            </div>
                            <div class="auth-actions" style="margin-top:12px;">
                                <a class="btn btn-primary" href="note.php">Notes</a>
                                <a class="btn btn-secondary" href="absence.php">Absences</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../app.js"></script>
</body>
</html>
