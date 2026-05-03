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

$currentPage = basename($_SERVER['PHP_SELF']);

$stmtFil = $pdo->prepare("SELECT id_filiere FROM classe WHERE id_classe=:idClasse LIMIT 1");
$stmtFil->execute(['idClasse'=>$idClasse]);
$classeInfo = $stmtFil->fetch(PDO::FETCH_ASSOC);
$idFiliereClasse = $classeInfo ? (int)$classeInfo['id_filiere'] : 0;

$stmtCM = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='classe_matiere' LIMIT 1");
$stmtCM->execute();
$hasClasseMatiere = (bool)$stmtCM->fetchColumn();

$stmtMatList = $pdo->prepare("
    SELECT m.*
    FROM matiere m
    JOIN enseignement en ON en.id_matiere=m.id_matiere
    ".($hasClasseMatiere ? "JOIN classe_matiere cm ON cm.id_matiere=m.id_matiere AND cm.id_classe=:idClasse" : "")."
    WHERE m.id_filiere=:idFiliere AND en.id_enseignant=:idEnseignant
    ORDER BY m.nom_matiere
");
$params = ['idFiliere'=>$idFiliereClasse, 'idEnseignant'=>$_SESSION['id']];
if($hasClasseMatiere){
    $params['idClasse'] = (int)$idClasse;
}
$stmtMatList->execute($params);
$matieres = $stmtMatList->fetchAll(PDO::FETCH_ASSOC);

if(!$matieres){
    $stmtMatList = $pdo->prepare(
        "SELECT m.* FROM matiere m ".($hasClasseMatiere ? "JOIN classe_matiere cm ON cm.id_matiere=m.id_matiere AND cm.id_classe=:idClasse " : "").
        "WHERE m.id_filiere=:idFiliere ORDER BY m.nom_matiere"
    );
    $params = ['idFiliere'=>$idFiliereClasse];
    if($hasClasseMatiere){
        $params['idClasse'] = (int)$idClasse;
    }
    $stmtMatList->execute($params);
    $matieres = $stmtMatList->fetchAll(PDO::FETCH_ASSOC);
}

$waitMessages = [];
if(empty($matieres)){
    $waitMessages[] = "Aucune matière n'est disponible pour cette classe. Veuillez attendre que le responsable paramètre les matières (et éventuellement l'association classe/matière ou l'affectation enseignant/matière).";
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
    header('Location: absence.php');
    exit();
}

if(isset($_POST['supprimer_absence'])){
    $idEt = isset($_POST['id_etudiant']) ? (int)$_POST['id_etudiant'] : 0;
    if($idEt > 0){
        $stmtChkEtu = $pdo->prepare("SELECT id_etudiant FROM etudiant WHERE id_etudiant=:idEtudiant AND id_classe=:idClasse LIMIT 1");
        $stmtChkEtu->execute(['idEtudiant'=>$idEt, 'idClasse'=>$idClasse]);
        $etuOk = $stmtChkEtu->fetch(PDO::FETCH_ASSOC);
        if($etuOk){
            $stmtLast = $pdo->prepare("SELECT a.id_absence FROM absence a WHERE a.id_etudiant=:idEtudiant AND a.id_matiere=:idMatiere ORDER BY a.date_absence DESC, a.id_absence DESC LIMIT 1");
            $stmtLast->execute(['idEtudiant'=>$idEt, 'idMatiere'=>$idMatiere]);
            $idAbs = (int)$stmtLast->fetchColumn();
            if($idAbs > 0){
                $stmtDel = $pdo->prepare("DELETE FROM absence WHERE id_absence=:id");
                $stmtDel->execute(['id'=>$idAbs]);
            }
        }
    }
    header('Location: absence.php');
    exit();
}

$stmtMat = $pdo->prepare("SELECT m.* FROM matiere m WHERE m.id_matiere=:idMatiere LIMIT 1");
$stmtMat->execute(['idMatiere'=>$idMatiere]);
$matiere = $stmtMat->fetch(PDO::FETCH_ASSOC);

$stmtEtu = $pdo->prepare("SELECT e.id_etudiant, e.nom_etudiant, e.prenom_etudiant, c.nom_classe AS classe FROM etudiant e JOIN classe c ON e.id_classe=c.id_classe WHERE e.id_classe=:idClasse ORDER BY e.nom_etudiant, e.prenom_etudiant");
$stmtEtu->execute(['idClasse'=>$idClasse]);
$etudiants = $stmtEtu->fetchAll(PDO::FETCH_ASSOC);

if(empty($etudiants)){
    $waitMessages[] = "Aucun étudiant n'est encore inscrit dans cette classe. Veuillez attendre que le responsable ajoute les étudiants.";
}

$absencesByEtudiant = [];
$maxAbsences = 0;
foreach($etudiants as $e){
    $eid = (int)$e['id_etudiant'];
    $absencesByEtudiant[$eid] = [];
}

$stmtAbs = $pdo->prepare("
    SELECT a.id_etudiant, a.date_absence, a.justifier
    FROM absence a
    JOIN etudiant e ON e.id_etudiant=a.id_etudiant
    WHERE e.id_classe=:idClasse AND a.id_matiere=:idMatiere
    ORDER BY a.date_absence ASC, a.id_absence ASC
");
$stmtAbs->execute(['idClasse'=>$idClasse, 'idMatiere'=>$idMatiere]);
$absRows = $stmtAbs->fetchAll(PDO::FETCH_ASSOC);
foreach($absRows as $r){
    $eid = (int)$r['id_etudiant'];
    if(!isset($absencesByEtudiant[$eid])){
        $absencesByEtudiant[$eid] = [];
    }
    $absencesByEtudiant[$eid][] = [
        'date_absence' => (string)$r['date_absence'],
        'justifier' => (int)($r['justifier'] ?? 0)
    ];
}

foreach($absencesByEtudiant as $eid => $arr){
    $maxAbsences = max($maxAbsences, is_array($arr) ? count($arr) : 0);
}

if(isset($_POST['ajouter_absences'])){
    $datesNew = isset($_POST['abs_date']) && is_array($_POST['abs_date']) ? $_POST['abs_date'] : [];
    $justNew = isset($_POST['abs_just']) && is_array($_POST['abs_just']) ? $_POST['abs_just'] : [];

    $stmtIns = $pdo->prepare("INSERT INTO absence (date_absence, justifier, id_etudiant, id_matiere) VALUES (:dateAbsence, :justifier, :idEtudiant, :idMatiere)");
    $stmtChkEtu = $pdo->prepare("SELECT id_etudiant FROM etudiant WHERE id_etudiant=:idEtudiant AND id_classe=:idClasse LIMIT 1");

    foreach($datesNew as $idEt => $dateAbs){
        $idEt = (int)$idEt;
        $dateAbs = trim((string)$dateAbs);
        if($idEt <= 0 || $dateAbs === ''){
            continue;
        }
        $stmtChkEtu->execute(['idEtudiant'=>$idEt, 'idClasse'=>$idClasse]);
        $etuOk = $stmtChkEtu->fetch(PDO::FETCH_ASSOC);
        if(!$etuOk){
            continue;
        }

        $just = isset($justNew[$idEt]) && (string)$justNew[$idEt] === '1' ? 1 : 0;
        $stmtIns->execute([
            'dateAbsence'=>$dateAbs,
            'justifier'=>$just,
            'idEtudiant'=>$idEt,
            'idMatiere'=>$idMatiere
        ]);
    }

    header('Location: absence.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp - Enseignant - Absences</title>
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
                    <h1>Absences</h1>
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
                        <h2>Liste des absences</h2>
                        <?php if(!empty($waitMessages)): ?>
                            <div class="card" style="margin-top:12px;">
                                <?php foreach($waitMessages as $msg): ?>
                                    <p><?= htmlspecialchars($msg) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                        <div>
                            <table class="table" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Prénom</th>
                                            <?php for($i=1; $i<=$maxAbsences; $i++): ?>
                                                <th style="width:140px;">Absence <?= (int)$i ?></th>
                                            <?php endfor; ?>
                                            <th style="width:170px;">Date</th>
                                            <th style="width:140px;">Justifiée</th>
                                            <th style="width:220px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($etudiants as $e): ?>
                                            <?php $eid = (int)$e['id_etudiant']; ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)$e['nom_etudiant']) ?></td>
                                                <td><?= htmlspecialchars((string)$e['prenom_etudiant']) ?></td>
                                                <?php $arr = $absencesByEtudiant[$eid] ?? []; ?>
                                                <?php for($i=0; $i<$maxAbsences; $i++): ?>
                                                    <?php
                                                        $a = isset($arr[$i]) ? $arr[$i] : null;
                                                        $isJust = $a ? ((int)$a['justifier'] === 1) : 0;
                                                        $bg = $a ? ($isJust ? '#d1fae5' : '#fee2e2') : 'transparent';
                                                        $color = $a ? ($isJust ? '#065f46' : '#991b1b') : 'inherit';
                                                        $dateTxt = $a ? (string)$a['date_absence'] : '';
                                                    ?>
                                                    <td>
                                                        <?php if($a): ?>
                                                            <span style="display:inline-block; padding:6px 10px; border-radius:999px; background:<?= htmlspecialchars($bg) ?>; color:<?= htmlspecialchars($color) ?>; font-weight:600;">
                                                                <?= htmlspecialchars($dateTxt) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endfor; ?>
                                                <?php $formId = 'absRow'.$eid; ?>
                                                <td>
                                                    <input type="date" name="abs_date[<?= (int)$eid ?>]" form="<?= htmlspecialchars($formId) ?>" value="" style="width:100%;" />
                                                </td>
                                                <td>
                                                    <select name="abs_just[<?= (int)$eid ?>]" form="<?= htmlspecialchars($formId) ?>" style="width:100%;">
                                                        <option value="0" selected>Non</option>
                                                        <option value="1">Oui</option>
                                                    </select>
                                                </td>
                                                <td style="white-space:nowrap;">
                                                    <form method="post" id="<?= htmlspecialchars($formId) ?>" style="display:inline-flex; gap:10px; align-items:center;">
                                                        <input type="hidden" name="id_etudiant" value="<?= (int)$eid ?>">
                                                        <button class="btn btn-primary" name="ajouter_absences" type="submit">Enregistrer</button>
                                                        <button class="btn btn-danger" name="supprimer_absence" type="submit" onclick="return confirm('Supprimer la dernière absence de cet étudiant ?');">Supprimer</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                        </div>
                        <div class="auth-actions" style="justify-content:flex-end; margin-top:12px;">
                            <button class="btn btn-secondary" type="button" onclick="window.print()">Imprimer</button>
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
