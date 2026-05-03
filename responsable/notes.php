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
$idClasse = isset($_POST['idClasse']) ? $_POST['idClasse'] : null;
$idMatiere = isset($_POST['idMatiere']) ? $_POST['idMatiere'] : null;

$showNotesList = (isset($_POST['showNotesList']) && $_POST['showNotesList'] === '1') || (isset($_POST['voir_notes']) && $_POST['voir_notes'] === '1');
$showResultatsList = (isset($_POST['showResultatsList']) && $_POST['showResultatsList'] === '1') || (isset($_POST['voir_resultats']) && $_POST['voir_resultats'] === '1');

if(isset($_POST['masquer_notes']) && $_POST['masquer_notes'] === '1'){
    $showNotesList = false;
}
if(isset($_POST['masquer_resultats']) && $_POST['masquer_resultats'] === '1'){
    $showResultatsList = false;
}

$idNiveau = isset($_SESSION['id_niveau']) ? (int)$_SESSION['id_niveau'] : 0;
$idFiliereResp = isset($_SESSION['id_filiere']) ? (int)$_SESSION['id_filiere'] : 0;
if($idNiveau <= 0){
    $stmtN = $pdo->prepare("SELECT id_niveau, id_filiere FROM responsable WHERE id_responsable=:id LIMIT 1");
    $stmtN->execute(['id'=>$_SESSION['id']]);
    $r = $stmtN->fetch(PDO::FETCH_ASSOC);
    $idNiveau = $r && $r['id_niveau'] !== null ? (int)$r['id_niveau'] : 0;
    $idFiliereResp = $r && $r['id_filiere'] !== null ? (int)$r['id_filiere'] : 0;
    $_SESSION['id_niveau'] = $idNiveau > 0 ? $idNiveau : null;
    $_SESSION['id_filiere'] = $idFiliereResp > 0 ? $idFiliereResp : null;
}

$libelleNiveau = '';
if($idNiveau > 0){
    $stmtLib = $pdo->prepare("SELECT libelle_niveau FROM niveau WHERE id_niveau=:id LIMIT 1");
    $stmtLib->execute(['id'=>$idNiveau]);
    $niv = $stmtLib->fetch(PDO::FETCH_ASSOC);
    $libelleNiveau = $niv && $niv['libelle_niveau'] ? trim((string)$niv['libelle_niveau']) : '';
}

$stmtCM = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='classe_matiere' LIMIT 1");
$stmtCM->execute();
$hasClasseMatiere = (bool)$stmtCM->fetchColumn();

$stmtC = $pdo->prepare("
    SELECT c.id_classe, c.nom_classe, c.description_classe, f.nom_filiere
    FROM classe c
    LEFT JOIN filiere f ON f.id_filiere=c.id_filiere
    WHERE c.id_niveau=:idNiveau".($idFiliereResp > 0 ? " AND c.id_filiere=:idFiliere" : "")."
    ORDER BY f.nom_filiere, c.nom_classe
");
$params = ['idNiveau'=>$idNiveau];
if($idFiliereResp > 0){
    $params['idFiliere'] = $idFiliereResp;
}
$stmtC->execute($params);
$classes = $stmtC->fetchAll(PDO::FETCH_ASSOC);

$matieres = [];
if($idClasse){
    $stmtClasse = $pdo->prepare("SELECT id_filiere FROM classe WHERE id_classe=:idClasse AND id_niveau=:idNiveau".($idFiliereResp > 0 ? " AND id_filiere=:idFiliere" : "")." LIMIT 1");
    $params = ['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau];
    if($idFiliereResp > 0){
        $params['idFiliere'] = $idFiliereResp;
    }
    $stmtClasse->execute($params);
    $classeInfo = $stmtClasse->fetch(PDO::FETCH_ASSOC);
    $idFiliereClasse = $classeInfo ? (int)$classeInfo['id_filiere'] : 0;

    if($hasClasseMatiere){
        $stmt = $pdo->prepare("\n            SELECT m.*\n            FROM classe_matiere cm\n            JOIN matiere m ON m.id_matiere=cm.id_matiere\n            WHERE cm.id_classe=:idClasse\n            ORDER BY m.nom_matiere\n        ");
        $stmt->execute(['idClasse'=>$idClasse]);
        $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM matiere WHERE id_filiere=:idFiliere ORDER BY nom_matiere");
        $stmt->execute(['idFiliere'=>$idFiliereClasse]);
        $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$etudiantsNotes = [];
$notesByEtudiant = [];
$moyenneByEtudiant = [];
$maxNotes = 0;
if($idClasse && $idMatiere){
    $stmt = $pdo->prepare("
        SELECT e.id_etudiant, e.nom_etudiant AS nom, e.prenom_etudiant AS prenom
        FROM etudiant e
        JOIN classe c ON c.id_classe=e.id_classe
        WHERE e.id_classe=:idClasse AND c.id_niveau=:idNiveau".($hasClasseMatiere ? " AND EXISTS (SELECT 1 FROM classe_matiere cm WHERE cm.id_classe=:idClasse AND cm.id_matiere=:idMatiere)" : "")."
        ORDER BY e.nom_etudiant, e.prenom_etudiant
    ");
    $stmt->execute(['idClasse'=>$idClasse,'idMatiere'=>$idMatiere,'idNiveau'=>$idNiveau]);
    $etudiantsNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($etudiantsNotes as $e){
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
    $stmtNotes->execute(['idClasse'=>$idClasse,'idMatiere'=>$idMatiere]);
    $rows = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $r){
        $eid = (int)$r['id_etudiant'];
        if(!isset($notesByEtudiant[$eid])){
            $notesByEtudiant[$eid] = [];
        }
        $notesByEtudiant[$eid][] = $r['note'] !== null ? (float)$r['note'] : null;
    }

    foreach($notesByEtudiant as $eid => $arr){
        $maxNotes = max($maxNotes, is_array($arr) ? count($arr) : 0);
        $sum = 0.0;
        $cnt = 0;
        foreach($arr as $v){
            if($v === null){
                continue;
            }
            $sum += (float)$v;
            $cnt++;
        }
        $moyenneByEtudiant[(int)$eid] = $cnt > 0 ? ($sum / $cnt) : null;
    }
}

$resultats = [];
if($idClasse){
    $stmt = $pdo->prepare("
        SELECT e.id_etudiant, e.nom_etudiant AS nom, e.prenom_etudiant AS prenom,
               m.nom_matiere AS matiere,
               moy.moyenne_matiere
        FROM etudiant e
        JOIN classe c ON c.id_classe=e.id_classe
        JOIN matiere m ON m.id_filiere=c.id_filiere".($hasClasseMatiere ? "\n        JOIN classe_matiere cm ON cm.id_classe=c.id_classe AND cm.id_matiere=m.id_matiere" : "")."
        LEFT JOIN (
            SELECT id_etudiant, id_matiere, AVG(note) AS moyenne_matiere
            FROM notes
            GROUP BY id_etudiant, id_matiere
        ) moy ON moy.id_etudiant=e.id_etudiant AND moy.id_matiere=m.id_matiere
        WHERE e.id_classe=:idClasse AND c.id_niveau=:idNiveau
        ORDER BY e.nom_etudiant, e.prenom_etudiant, m.nom_matiere
    ");
    $stmt->execute(['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau]);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$matieresResultats = [];
if($idClasse){
    if($hasClasseMatiere){
        $stmt = $pdo->prepare("
            SELECT m.id_matiere, m.nom_matiere
            FROM classe_matiere cm
            JOIN matiere m ON m.id_matiere=cm.id_matiere
            WHERE cm.id_classe=:idClasse
            ORDER BY m.nom_matiere
        ");
        $stmt->execute(['idClasse'=>$idClasse]);
        $matieresResultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmtClasse = $pdo->prepare("SELECT id_filiere FROM classe WHERE id_classe=:idClasse AND id_niveau=:idNiveau".($idFiliereResp > 0 ? " AND id_filiere=:idFiliere" : "")." LIMIT 1");
        $params = ['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau];
        if($idFiliereResp > 0){
            $params['idFiliere'] = $idFiliereResp;
        }
        $stmtClasse->execute($params);
        $classeInfo = $stmtClasse->fetch(PDO::FETCH_ASSOC);
        $idFiliereClasse = $classeInfo ? (int)$classeInfo['id_filiere'] : 0;

        $stmt = $pdo->prepare("SELECT id_matiere, nom_matiere FROM matiere WHERE id_filiere=:idFiliere ORDER BY nom_matiere");
        $stmt->execute(['idFiliere'=>$idFiliereClasse]);
        $matieresResultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$resultatsPivot = [];
$moyennesGeneralesById = [];
if($idClasse){
    $stmt = $pdo->prepare("
        SELECT e.id_etudiant, AVG(n.note) AS moyenne_generale
        FROM etudiant e
        JOIN classe c ON c.id_classe=e.id_classe
        LEFT JOIN notes n ON n.id_etudiant=e.id_etudiant".($hasClasseMatiere ? " AND n.id_matiere IN (SELECT id_matiere FROM classe_matiere WHERE id_classe=:idClasse)" : "")."
        WHERE e.id_classe=:idClasse AND c.id_niveau=:idNiveau
        GROUP BY e.id_etudiant
    ");
    $stmt->execute(['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $r){
        $moyennesGeneralesById[(int)$r['id_etudiant']] = $r['moyenne_generale'] !== null ? (float)$r['moyenne_generale'] : null;
    }

    foreach($resultats as $r){
        $eid = (int)$r['id_etudiant'];
        if(!isset($resultatsPivot[$eid])){
            $resultatsPivot[$eid] = [
                'nom' => (string)$r['nom'],
                'prenom' => (string)$r['prenom'],
                'matieres' => []
            ];
        }
        $resultatsPivot[$eid]['matieres'][(string)$r['matiere']] = $r['moyenne_matiere'] !== null ? (float)$r['moyenne_matiere'] : null;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp - Responsable - Notes & Résultats</title>
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
        <a href="moyennes.php" class="<?= $currentPage === 'moyennes.php' ? 'active' : '' ?>">Moyennes</a>
        <a href="notes.php" class="<?= $currentPage === 'notes.php' ? 'active' : '' ?>">Notes / Résultats</a>
        <a href="parametres.php" class="<?= $currentPage === 'parametres.php' ? 'active' : '' ?>">Paramètre</a>
        <div class="spacer"></div>
        <a class="btn btn-danger" href="?logout=1">Déconnexion</a>
    </aside>

    <main class="main">
        <div class="container">
            <div class="dash-topbar">
                <div class="dash-title">
                    <h1>Notes / Résultats</h1>
                    <p>Filtrer et consulter les notes</p>
                </div>
                <div class="dash-actions">
                    <a class="btn btn-secondary" href="notes.php">Modifier</a>
                </div>
            </div>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <div class="card">
                        <h2>Filtrer</h2>
                        <form method="post">
                            <input type="hidden" name="showNotesList" value="<?= $showNotesList ? '1' : '0' ?>">
                            <input type="hidden" name="showResultatsList" value="<?= $showResultatsList ? '1' : '0' ?>">
                            <label>Classe</label>
                            <select name="idClasse" onchange="this.form.submit()">
                                <option value="">-- Choisir --</option>
                                <?php foreach($classes as $c): ?>
                                    <?php
                                        $desc = isset($c['description_classe']) ? trim((string)$c['description_classe']) : '';
                                        $base = $desc !== '' ? $desc : (string)$c['nom_classe'];
                                        $label = $libelleNiveau !== '' ? ($libelleNiveau.' — '.$base) : $base;
                                    ?>
                                    <option value="<?= (int)$c['id_classe'] ?>" <?= ((string)$idClasse === (string)$c['id_classe']) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label>Matière (pour liste des notes)</label>
                            <select name="idMatiere" onchange="this.form.submit()" <?= $idClasse ? '' : 'disabled' ?>>
                                <option value="">-- Choisir --</option>
                                <?php foreach($matieres as $m): ?>
                                    <option value="<?= $m['id_matiere'] ?>" <?= ($idMatiere==$m['id_matiere']) ? 'selected' : '' ?>><?= htmlspecialchars($m['nom_matiere']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <div class="auth-actions" style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; width:100%;">
                                <div>
                                    <?php if(!$showNotesList): ?>
                                        <button class="btn btn-primary" name="voir_notes" value="1" type="submit">Voir notes</button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" name="masquer_notes" value="1" type="submit">Masquer notes</button>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <?php if(!$showResultatsList): ?>
                                        <button class="btn btn-primary" name="voir_resultats" value="1" type="submit">Voir résultats</button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" name="masquer_resultats" value="1" type="submit">Masquer résultats</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php if($showNotesList): ?>
                    <div class="card">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <h2 style="margin:0;">Liste des notes </h2>
                            <button class="btn btn-secondary" type="button" onclick="printSection('print-notes', 'Liste des notes')">Imprimer</button>
                        </div>
                        <?php if($idClasse && $idMatiere): ?>
                        <div id="print-notes">
                        <?php
                            $hasAnyNote = false;
                            if($maxNotes > 0){
                                foreach($notesByEtudiant as $arr){
                                    if(is_array($arr) && count($arr) > 0){
                                        $hasAnyNote = true;
                                        break;
                                    }
                                }
                            }
                        ?>
                        <?php if(empty($etudiantsNotes)): ?>
                            <p>Aucun étudiant n'est disponible pour cette classe.</p>
                        <?php elseif(!$hasAnyNote): ?>
                            <p>Aucune note pour le moment.</p>
                        <?php else: ?>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <?php for($i=1; $i<=$maxNotes; $i++): ?>
                                            <th>Note <?= (int)$i ?></th>
                                        <?php endfor; ?>
                                        <th>Moyenne</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($etudiantsNotes as $e): ?>
                                        <?php $eid = (int)$e['id_etudiant']; ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)$e['nom']) ?></td>
                                            <td><?= htmlspecialchars((string)$e['prenom']) ?></td>
                                            <?php $arr = $notesByEtudiant[$eid] ?? []; ?>
                                            <?php for($i=0; $i<$maxNotes; $i++): ?>
                                                <?php $v = isset($arr[$i]) ? $arr[$i] : null; ?>
                                                <td>
                                                    <?php if($v !== null): ?>
                                                        <?php $vv = (float)$v; ?>
                                                        <span class="<?= $vv >= 10 ? 'score-ok' : 'score-ko' ?>"><?= round($vv,2) ?></span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>
                                            <?php $mg = $moyenneByEtudiant[$eid] ?? null; ?>
                                            <td>
                                                <?php if($mg !== null): ?>
                                                    <?php $mm = (float)$mg; ?>
                                                    <span class="<?= $mm >= 10 ? 'score-ok' : 'score-ko' ?>"><?= round($mm,2) ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        </div>
                        <?php else: ?>
                            <p>Choisis une classe et une matière.</p>
                        <?php endif; ?>
                    </div>

                    <?php endif; ?>

                    <?php if($showResultatsList): ?>
                    <div class="card">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <h2 style="margin:0;">Résultats (toutes les matières)</h2>
                            <button class="btn btn-secondary" type="button" onclick="printSection('print-resultats', 'Résultats')">Imprimer</button>
                        </div>
                        <?php if($idClasse): ?>
                        <div id="print-resultats">
                        <?php if(empty($resultatsPivot) || empty($matieresResultats)): ?>
                            <p>Aucun résultat pour le moment.</p>
                        <?php else: ?>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <?php foreach($matieresResultats as $m): ?>
                                            <th><?= htmlspecialchars((string)$m['nom_matiere']) ?></th>
                                        <?php endforeach; ?>
                                        <th>Moyenne générale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($resultatsPivot as $idEtudiant => $r): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($r['nom']) ?></td>
                                            <td><?= htmlspecialchars($r['prenom']) ?></td>
                                            <?php foreach($matieresResultats as $m): ?>
                                                <?php $val = $r['matieres'][(string)$m['nom_matiere']] ?? null; ?>
                                                <td>
                                                    <?php if($val !== null): ?>
                                                        <?php $vv = (float)$val; ?>
                                                        <span class="<?= $vv >= 10 ? 'score-ok' : 'score-ko' ?>"><?= round($vv,2) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <?php $mg = $moyennesGeneralesById[(int)$idEtudiant] ?? null; ?>
                                            <td>
                                                <?php if($mg !== null): ?>
                                                    <?php $mm = (float)$mg; ?>
                                                    <span class="<?= $mm >= 10 ? 'score-ok' : 'score-ko' ?>"><?= round($mm,2) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        </div>
                        <?php else: ?>
                            <p>Choisis une classe.</p>
                        <?php endif; ?>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../app.js"></script>
<script>
function printSection(sectionId, title){
    var el = document.getElementById(sectionId);
    if(!el){
        window.print();
        return;
    }
    var w = window.open('', '_blank');
    if(!w){
        window.print();
        return;
    }
    var cssHref = '../style.css';
    w.document.open();
    w.document.write('<!doctype html><html lang="fr"><head><meta charset="utf-8">');
    w.document.write('<meta name="viewport" content="width=device-width, initial-scale=1.0">');
    w.document.write('<title>'+String(title || 'Impression')+'</title>');
    w.document.write('<link rel="stylesheet" href="'+cssHref+'">');
    w.document.write('<style>@media print{.table-scroll{overflow:visible!important} .table-scroll table{width:100%!important;min-width:0!important}}</style>');
    w.document.write('</head><body>');
    w.document.write(el.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    setTimeout(function(){
        w.print();
        setTimeout(function(){ w.close(); }, 50);
    }, 250);
}
</script>
</body>
</html>
