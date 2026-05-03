<?php
session_start();
require '../bd.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] != 'ENSEIGNANT') {
    header("Location: connexion_enseignant.php");
    exit();
}

if (!isset($_SESSION['idClasse'], $_SESSION['idMatiere'])) {
    header("Location: selection.php");
    exit();
}

$idClasse = $_SESSION['idClasse'];
$idMatiere = $_SESSION['idMatiere'];

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);

$stmtFil = $pdo->prepare("SELECT id_filiere FROM classe WHERE id_classe=:idClasse LIMIT 1");
$stmtFil->execute(['idClasse' => $idClasse]);
$classeInfo = $stmtFil->fetch(PDO::FETCH_ASSOC);
$idFiliereClasse = $classeInfo ? (int)$classeInfo['id_filiere'] : 0;

$stmtCM = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='classe_matiere' LIMIT 1");
$stmtCM->execute();
$hasClasseMatiere = (bool)$stmtCM->fetchColumn();

$stmtMatList = $pdo->prepare("
    SELECT m.*
    FROM matiere m
    JOIN enseignement en ON en.id_matiere=m.id_matiere
    " . ($hasClasseMatiere ? "JOIN classe_matiere cm ON cm.id_matiere=m.id_matiere AND cm.id_classe=:idClasse" : "") . "
    WHERE m.id_filiere=:idFiliere AND en.id_enseignant=:idEnseignant
    ORDER BY m.nom_matiere
");
$params = ['idFiliere' => $idFiliereClasse, 'idEnseignant' => $_SESSION['id']];
if ($hasClasseMatiere) {
    $params['idClasse'] = (int)$idClasse;
}
$stmtMatList->execute($params);
$matieres = $stmtMatList->fetchAll(PDO::FETCH_ASSOC);

if (!$matieres) {
    $stmtMatList = $pdo->prepare(
        "SELECT m.* FROM matiere m " . ($hasClasseMatiere ? "JOIN classe_matiere cm ON cm.id_matiere=m.id_matiere AND cm.id_classe=:idClasse " : "") .
        "WHERE m.id_filiere=:idFiliere ORDER BY m.nom_matiere"
    );
    $params = ['idFiliere' => $idFiliereClasse];
    if ($hasClasseMatiere) {
        $params['idClasse'] = (int)$idClasse;
    }
    $stmtMatList->execute($params);
    $matieres = $stmtMatList->fetchAll(PDO::FETCH_ASSOC);
}

$waitMessages = [];
if (empty($matieres)) {
    $waitMessages[] = "Aucune matière n'est disponible pour cette classe. Veuillez attendre que le responsable paramètre les matières (et éventuellement l'association classe/matière ou l'affectation enseignant/matière).";
}

if (isset($_POST['set_matiere'])) {
    $newId = isset($_POST['idMatiere']) ? (int)$_POST['idMatiere'] : 0;
    if ($newId > 0) {
        $stmtChk = $pdo->prepare("
            SELECT m.id_matiere
            FROM matiere m
            JOIN enseignement en ON en.id_matiere=m.id_matiere
            WHERE m.id_matiere=:idMatiere AND m.id_filiere=:idFiliere AND en.id_enseignant=:idEnseignant
            LIMIT 1
        ");
        $stmtChk->execute(['idMatiere' => $newId, 'idFiliere' => $idFiliereClasse, 'idEnseignant' => $_SESSION['id']]);
        $ok = $stmtChk->fetch(PDO::FETCH_ASSOC);
        if (!$ok) {
            $stmtChk = $pdo->prepare("SELECT id_matiere FROM matiere WHERE id_matiere=:idMatiere AND id_filiere=:idFiliere LIMIT 1");
            $stmtChk->execute(['idMatiere' => $newId, 'idFiliere' => $idFiliereClasse]);
            $ok = $stmtChk->fetch(PDO::FETCH_ASSOC);
        }
        if ($ok) {
            $_SESSION['idMatiere'] = $newId;
            $idMatiere = $newId;
        }
    }
    header('Location: note.php');
    exit();
}

$stmtMat = $pdo->prepare("SELECT m.* FROM matiere m WHERE m.id_matiere=:idMatiere LIMIT 1");
$stmtMat->execute(['idMatiere' => $idMatiere]);
$matiere = $stmtMat->fetch(PDO::FETCH_ASSOC);

$stmtEtu = $pdo->prepare("SELECT e.id_etudiant, e.nom_etudiant, e.prenom_etudiant, c.nom_classe AS classe FROM etudiant e JOIN classe c ON e.id_classe=c.id_classe WHERE e.id_classe=:idClasse ORDER BY e.nom_etudiant, e.prenom_etudiant");
$stmtEtu->execute(['idClasse' => $idClasse]);
$etudiants = $stmtEtu->fetchAll(PDO::FETCH_ASSOC);

if (empty($etudiants)) {
    $waitMessages[] = "Aucun étudiant n'est encore inscrit dans cette classe. Veuillez attendre que le responsable ajoute les étudiants.";
}

$notesByEtudiant = [];
$moyenneByEtudiant = [];
$maxNotes = 0;

foreach ($etudiants as $e) {
    $eid = (int)$e['id_etudiant'];
    $notesByEtudiant[$eid] = [];
    $moyenneByEtudiant[$eid] = null;
}

$stmtNotes = $pdo->prepare("
    SELECT n.id_etudiant, n.note
    FROM notes n
    JOIN etudiant e ON e.id_etudiant=n.id_etudiant
    WHERE e.id_classe=:idClasse AND n.id_matiere=:idMatiere
    ORDER BY n.date_note ASC, n.id_note ASC
");
$stmtNotes->execute(['idClasse' => $idClasse, 'idMatiere' => $idMatiere]);
$notesRows = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);
foreach ($notesRows as $r) {
    $eid = (int)$r['id_etudiant'];
    if (!isset($notesByEtudiant[$eid])) {
        $notesByEtudiant[$eid] = [];
    }
    $notesByEtudiant[$eid][] = $r['note'] !== null ? (float)$r['note'] : null;
}

foreach ($notesByEtudiant as $eid => $arr) {
    $maxNotes = max($maxNotes, is_array($arr) ? count($arr) : 0);
    $sum = 0.0;
    $cnt = 0;
    foreach ($arr as $v) {
        if ($v === null) {
            continue;
        }
        $sum += (float)$v;
        $cnt++;
    }
    $moyenneByEtudiant[(int)$eid] = $cnt > 0 ? ($sum / $cnt) : null;
}

if (isset($_POST['ajouter_note'])) {
    $idEt = isset($_POST['id_etudiant']) ? (int)$_POST['id_etudiant'] : 0;
    $val = isset($_POST['note_new']) ? trim((string)$_POST['note_new']) : '';
    if ($idEt > 0 && $val !== '') {
        $stmtChkEtu = $pdo->prepare("SELECT id_etudiant FROM etudiant WHERE id_etudiant=:idEtudiant AND id_classe=:idClasse LIMIT 1");
        $stmtChkEtu->execute(['idEtudiant' => $idEt, 'idClasse' => $idClasse]);
        $etuOk = $stmtChkEtu->fetch(PDO::FETCH_ASSOC);
        if ($etuOk) {
            $stmtIns = $pdo->prepare("INSERT INTO notes (note, type_note, id_etudiant, id_matiere) VALUES (:note, NULL, :idEtudiant, :idMatiere)");
            $stmtIns->execute(['note' => $val, 'idEtudiant' => $idEt, 'idMatiere' => $idMatiere]);
        }
    }
    header('Location: note.php');
    exit();
}

if (isset($_POST['supprimer_note'])) {
    $idEt = isset($_POST['id_etudiant']) ? (int)$_POST['id_etudiant'] : 0;
    if ($idEt > 0) {
        $stmtChkEtu = $pdo->prepare("SELECT id_etudiant FROM etudiant WHERE id_etudiant=:idEtudiant AND id_classe=:idClasse LIMIT 1");
        $stmtChkEtu->execute(['idEtudiant' => $idEt, 'idClasse' => $idClasse]);
        $etuOk = $stmtChkEtu->fetch(PDO::FETCH_ASSOC);
        if ($etuOk) {
            $stmtLast = $pdo->prepare("SELECT n.id_note FROM notes n WHERE n.id_etudiant=:idEtudiant AND n.id_matiere=:idMatiere ORDER BY n.date_note DESC, n.id_note DESC LIMIT 1");
            $stmtLast->execute(['idEtudiant' => $idEt, 'idMatiere' => $idMatiere]);
            $idNote = (int)$stmtLast->fetchColumn();
            if ($idNote > 0) {
                $stmtDel = $pdo->prepare("DELETE FROM notes WHERE id_note=:id");
                $stmtDel->execute(['id' => $idNote]);
            }
        }
    }
    header('Location: note.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp - Enseignant - Notes</title>
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
                    <h1>Notes</h1>
                    <p><?= htmlspecialchars($matiere ? $matiere['nom_matiere'] : '') ?></p>
                </div>
                <div class="dash-actions">
                    <form method="post" style="display:inline-flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="set_matiere" value="1">
                        <select name="idMatiere" onchange="this.form.submit()" style="min-width:220px;">
                            <?php foreach ($matieres as $m) : ?>
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
                        <h2>Liste des notes</h2>
                        <?php if (!empty($waitMessages)) : ?>
                            <div class="card" style="margin-top:12px;">
                                <?php foreach ($waitMessages as $msg) : ?>
                                    <p><?= htmlspecialchars($msg) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                        <div>
                            <table class="table" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th>Prénom</th>
                                            <?php for ($i = 1; $i <= $maxNotes; $i++) : ?>
                                                <th style="width:120px;">Note <?= (int)$i ?></th>
                                            <?php endfor; ?>
                                            <th style="width:140px;">Moyenne</th>
                                            <th style="width:180px;">Ajouter</th>
                                            <th style="width:220px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($etudiants as $e) : ?>
                                            <?php $eid = (int)$e['id_etudiant']; ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)$e['nom_etudiant']) ?></td>
                                                <td><?= htmlspecialchars((string)$e['prenom_etudiant']) ?></td>
                                                <?php
                                                    $arr = $notesByEtudiant[$eid] ?? [];
                                                ?>
                                                <?php for ($i = 0; $i < $maxNotes; $i++) : ?>
                                                    <?php $v = isset($arr[$i]) ? $arr[$i] : null; ?>
                                                    <td>
                                                        <?php if ($v !== null) : ?>
                                                            <?php $vv = (float)$v; ?>
                                                            <span class="<?= $vv >= 10 ? 'score-ok' : 'score-ko' ?>"><?= round($vv, 2) ?></span>
                                                        <?php else : ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endfor; ?>
                                                <?php $mg = $moyenneByEtudiant[$eid] ?? null; ?>
                                                <td>
                                                    <?php if ($mg !== null) : ?>
                                                        <?php $mm = (float)$mg; ?>
                                                        <span class="<?= $mm >= 10 ? 'score-ok' : 'score-ko' ?>"><?= round($mm, 2) ?></span>
                                                    <?php else : ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <?php $formId = 'noteRow' . $eid; ?>
                                                <td>
                                                    <input type="number" step="0.01" name="note_new" form="<?= htmlspecialchars($formId) ?>" value="" placeholder="Nouvelle note" style="width:100%;" />
                                                </td>
                                                <td style="white-space:nowrap;">
                                                    <form method="post" id="<?= htmlspecialchars($formId) ?>" style="display:inline-flex; gap:10px; align-items:center;">
                                                        <input type="hidden" name="id_etudiant" value="<?= (int)$eid ?>">
                                                        <button class="btn btn-primary" name="ajouter_note" type="submit">Enregistrer</button>
                                                        <button class="btn btn-danger" name="supprimer_note" type="submit" onclick="return confirm('Supprimer la dernière note de cet étudiant ?');">Supprimer</button>
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
