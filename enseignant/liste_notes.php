<?php
session_start();
require '../bd.php';

if(!isset($_SESSION['id']) || $_SESSION['role'] != 'ENSEIGNANT'){
    header("Location: connexion_enseignant.php");
    exit();
}

if(!isset($_SESSION['idClasse'], $_SESSION['idMatiere'])){
    header("Location: selection.php");
    exit();
}

$idClasse = $_SESSION['idClasse'];
$idMatiere = $_SESSION['idMatiere'];

if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ../index.php");
    exit();
}

$stmtFil = $pdo->prepare("SELECT id_filiere FROM classe WHERE id_classe=:idClasse LIMIT 1");
$stmtFil->execute(['idClasse'=>$idClasse]);
$classeInfo = $stmtFil->fetch(PDO::FETCH_ASSOC);
$idFiliereClasse = $classeInfo ? (int)$classeInfo['id_filiere'] : 0;

$stmtMatList = $pdo->prepare("
    SELECT m.*
    FROM matiere m
    JOIN enseignement en ON en.id_matiere=m.id_matiere
    WHERE m.id_filiere=:idFiliere AND en.id_enseignant=:idEnseignant
    ORDER BY m.nom_matiere
");
$stmtMatList->execute(['idFiliere'=>$idFiliereClasse, 'idEnseignant'=>$_SESSION['id']]);
$matieres = $stmtMatList->fetchAll(PDO::FETCH_ASSOC);

if(!$matieres){
    $stmtMatList = $pdo->prepare("SELECT * FROM matiere WHERE id_filiere=:idFiliere ORDER BY nom_matiere");
    $stmtMatList->execute(['idFiliere'=>$idFiliereClasse]);
    $matieres = $stmtMatList->fetchAll(PDO::FETCH_ASSOC);
}

if(isset($_POST['set_matiere'])){
    $newId = isset($_POST['idMatiere']) ? (int)$_POST['idMatiere'] : 0;
    if($newId > 0){
        $stmtChk = $pdo->prepare("
            SELECT m.id_matiere
            FROM matiere m
            JOIN enseignement en ON en.id_matiere=m.id_matiere
            WHERE m.id_matiere=:idMatiere AND m.id_filiere=:idFiliere AND en.id_enseignant=:idEnseignant
            LIMIT 1
        ");
        $stmtChk->execute(['idMatiere'=>$newId, 'idFiliere'=>$idFiliereClasse, 'idEnseignant'=>$_SESSION['id']]);
        $ok = $stmtChk->fetch(PDO::FETCH_ASSOC);
        if(!$ok){
            $stmtChk = $pdo->prepare("SELECT id_matiere FROM matiere WHERE id_matiere=:idMatiere AND id_filiere=:idFiliere LIMIT 1");
            $stmtChk->execute(['idMatiere'=>$newId, 'idFiliere'=>$idFiliereClasse]);
            $ok = $stmtChk->fetch(PDO::FETCH_ASSOC);
        }
        if($ok){
            $_SESSION['idMatiere'] = $newId;
            $idMatiere = $newId;
        }
    }
    header('Location: liste_notes.php');
    exit();
}

$stmtMat = $pdo->prepare("SELECT m.* FROM matiere m WHERE m.id_matiere=:idMatiere LIMIT 1");
$stmtMat->execute(['idMatiere'=>$idMatiere]);
$matiere = $stmtMat->fetch(PDO::FETCH_ASSOC);

$stmtEtuNotes = $pdo->prepare("SELECT e.id_etudiant, e.nom_etudiant, e.prenom_etudiant, c.nom_classe AS classe, s.note
    FROM etudiant e
    JOIN classe c ON e.id_classe=c.id_classe
    LEFT JOIN suivi s ON s.id_etudiant=e.id_etudiant AND s.id_matiere=:idMatiere
    WHERE e.id_classe=:idClasse
    ORDER BY e.nom_etudiant, e.prenom_etudiant
");
$stmtEtuNotes->execute(['idClasse'=>$idClasse, 'idMatiere'=>$idMatiere]);
$rows = $stmtEtuNotes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>ScolApp - Enseignant - Liste des notes</title>
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
        <a href="selection.php">Tableau de bord</a>
        <a href="note.php">Notes</a>
        <a href="liste_notes.php">Liste des notes</a>
        <a href="absence.php">Absences</a>
        <a href="moyennes.php">Moyennes</a>
        <div class="spacer"></div>
        <a href="../index.php">Accueil</a>
        <a class="btn btn-danger" href="?logout=1">Déconnexion</a>
    </aside>

    <main class="main">
        <div class="container">
            <div class="dash-topbar">
                <div class="dash-title">
                    <h1>Liste des notes</h1>
                    <p><?= htmlspecialchars($matiere ? $matiere['nom_matiere'] : '') ?></p>
                </div>
                <div class="dash-actions">
                    <form method="post" style="display:inline-flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="set_matiere" value="1">
                        <select name="idMatiere" onchange="this.form.submit()" style="min-width:220px;">
                            <?php foreach($matieres as $m): ?>
                                <option value="<?= (int)$m['id_matiere'] ?>" <?= ((int)$idMatiere === (int)$m['id_matiere']) ? 'selected' : '' ?>><?= htmlspecialchars($m['nom_matiere']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a class="btn btn-secondary" href="selection.php?reset=1">Modifier</a>
                    </form>
                </div>
            </div>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <div class="card">
                        <h2>Notes</h2>
                        <div style="overflow:auto;">
                            <table class="table" style="min-width:640px; width:100%;">
                                <thead>
                                    <tr>
                                        <th>Étudiant</th>
                                        <th style="width:160px;">Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($rows as $r): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($r['nom_etudiant'].' '.$r['prenom_etudiant']) ?></td>
                                            <td><?= ($r['note'] === null || $r['note'] === '') ? '-' : htmlspecialchars($r['note']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../app.js"></script>
</body>
</html>
