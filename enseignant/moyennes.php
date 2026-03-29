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

$showResults = isset($_GET['view']) && $_GET['view'] == '1';

if(isset($_GET['logout'])){
    session_destroy();
    header("Location: ../index.php");
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);

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
    header('Location: moyennes.php'.($showResults ? '?view=1' : ''));
    exit();
}

$stmtMat = $pdo->prepare("SELECT m.* FROM matiere m WHERE m.id_matiere=:idMatiere LIMIT 1");
$stmtMat->execute(['idMatiere'=>$idMatiere]);
$matiere = $stmtMat->fetch(PDO::FETCH_ASSOC);

$stmtEtu = $pdo->prepare("SELECT e.id_etudiant, e.nom_etudiant, e.prenom_etudiant FROM etudiant e WHERE e.id_classe=:idClasse ORDER BY e.nom_etudiant, e.prenom_etudiant");
$stmtEtu->execute(['idClasse'=>$idClasse]);
$etudiants = $stmtEtu->fetchAll(PDO::FETCH_ASSOC);

$stmtNotes = $pdo->prepare("SELECT n.id_etudiant, AVG(n.note) AS moyenne
    FROM notes n
    JOIN etudiant e ON e.id_etudiant=n.id_etudiant
    WHERE n.id_matiere=:idMatiere AND e.id_classe=:idClasse
    GROUP BY n.id_etudiant
");
$stmtNotes->execute(['idMatiere'=>$idMatiere, 'idClasse'=>$idClasse]);
$notesRaw = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);
$notes = [];
foreach($notesRaw as $row){
    $notes[(int)$row['id_etudiant']] = $row['moyenne'];
}

$stmtAbs = $pdo->prepare("SELECT a.id_etudiant, COUNT(*) AS nb
    FROM absence a
    JOIN etudiant e ON e.id_etudiant=a.id_etudiant
    WHERE a.id_matiere=:idMatiere AND e.id_classe=:idClasse
    GROUP BY a.id_etudiant
");
$stmtAbs->execute(['idMatiere'=>$idMatiere, 'idClasse'=>$idClasse]);
$absRaw = $stmtAbs->fetchAll(PDO::FETCH_ASSOC);
$absences = [];
foreach($absRaw as $row){
    $absences[(int)$row['id_etudiant']] = (int)$row['nb'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp - Enseignant - Moyennes</title>
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
        <a class="btn btn-danger" href="?logout=1">Déconnexion</a>
    </aside>

    <main class="main">
        <div class="container">
            <div class="dash-topbar">
                <div class="dash-title">
                    <h1>Moyennes</h1>
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
                        <?php if(!$showResults): ?>
                            <a class="btn btn-primary" href="moyennes.php?view=1">Voir</a>
                        <?php else: ?>
                            <a class="btn btn-secondary" href="moyennes.php">Masquer</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <?php if($showResults): ?>
                    <div class="card">
                        <h2>Résultats</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Moyenne</th>
                                    <th>Absences</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($etudiants as $e): 
                                    $moy = isset($notes[$e['id_etudiant']]) ? $notes[$e['id_etudiant']] : null;
                                    $abs = isset($absences[$e['id_etudiant']]) ? $absences[$e['id_etudiant']] : 0;
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($e['nom_etudiant'].' '.$e['prenom_etudiant']) ?></td>
                                        <td><?= $moy !== null ? round($moy,2) : '' ?></td>
                                        <td><?= $abs ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="auth-actions" style="justify-content:flex-end; margin-top:12px;">
                            <button class="btn btn-secondary" type="button" onclick="window.print()">Imprimer</button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card">
                        <h2>Résultats</h2>
                        <p>Clique sur "Voir" pour afficher la liste.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../app.js"></script>
</body>
</html>
