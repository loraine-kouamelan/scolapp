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

$stmtLib = $pdo->prepare("SELECT libelle_niveau FROM niveau WHERE id_niveau=:id LIMIT 1");
$stmtLib->execute(['id'=>$idNiveau]);
$niv = $stmtLib->fetch(PDO::FETCH_ASSOC);
$libelleNiveau = $niv && $niv['libelle_niveau'] ? trim((string)$niv['libelle_niveau']) : '';

$stmtCM = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='classe_matiere' LIMIT 1");
$stmtCM->execute();
$hasClasseMatiere = (bool)$stmtCM->fetchColumn();

function filiere_label($nomFiliere, $libelleNiveau){
    $nom = trim((string)$nomFiliere);
    $niv = trim((string)$libelleNiveau);
    $lf = mb_strtolower($nom);
    $ln = mb_strtolower($niv);

    if($nom === ''){
        return '';
    }

    $niveauCourt = '';
    if(preg_match('/\b(licen[cs]e|master)\s*(\d)\b/i', $niv, $m)){
        $niveauCourt = mb_strtolower($m[1].' '.$m[2]);
    }

    if($niveauCourt === ''){
        return $ln !== '' && strpos($lf, $ln) === 0 ? $lf : ($ln !== '' ? ($ln.' '.$lf) : $lf);
    }

    $resteLibelle = trim(preg_replace('/^'.preg_quote($niveauCourt, '/').'\s*/i', '', $ln));

    if(strpos($lf, $niveauCourt) === 0){
        $apres = trim(mb_substr($lf, mb_strlen($niveauCourt)));
        if($resteLibelle !== '' && strpos($apres, $resteLibelle) === 0){
            $apres = trim(mb_substr($apres, mb_strlen($resteLibelle)));
        }
        return trim($niveauCourt.' '.$apres);
    }

    if($resteLibelle !== '' && strpos($lf, $resteLibelle) === 0){
        $lf = trim(mb_substr($lf, mb_strlen($resteLibelle)));
    }

    return trim($niveauCourt.' '.$lf);
}

if(isset($_POST['supprimer_matiere'])){
    $id = isset($_POST['id_matiere']) ? (int)$_POST['id_matiere'] : 0;
    if($id > 0){
        if($hasClasseMatiere){
            $idClasse = isset($_POST['id_classe']) ? (int)$_POST['id_classe'] : 0;
            if($idClasse <= 0){
                $message_action = "Classe invalide.";
            } else {
                $stmtChk = $pdo->prepare(
                    "SELECT 1 FROM classe WHERE id_classe=:idClasse AND id_niveau=:idNiveau".
                    ($idFiliereResp > 0 ? " AND id_filiere=:idFiliere" : "").
                    " LIMIT 1"
                );
                $params = ['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau];
                if($idFiliereResp > 0){
                    $params['idFiliere'] = $idFiliereResp;
                }
                $stmtChk->execute($params);
                $ok = (bool)$stmtChk->fetchColumn();
                if(!$ok){
                    $message_action = "Classe invalide.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM classe_matiere WHERE id_classe=:idClasse AND id_matiere=:idMatiere");
                    $stmt->execute(['idClasse'=>$idClasse, 'idMatiere'=>$id]);
                    $message_action = "Matière retirée de la classe.";
                }
            }
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM matiere WHERE id_matiere=:id");
                $stmt->execute(['id'=>$id]);
                $message_action = "Matière supprimée.";
            } catch (PDOException $e){
                $message_action = "Impossible de supprimer cette matière (elle est utilisée ailleurs).";
            }
        }
    }
}

    if(isset($_POST['modifier_matiere'])){
    $id = isset($_POST['id_matiere']) ? (int)$_POST['id_matiere'] : 0;
    $nom = isset($_POST['nom_matiere']) ? trim($_POST['nom_matiere']) : '';
    $idFiliere = isset($_POST['id_filiere']) ? (int)$_POST['id_filiere'] : 0;

    if($id > 0 && $nom !== '' && $idFiliere > 0){
        $stmt = $pdo->prepare("
            UPDATE matiere
            SET nom_matiere=:nom, id_filiere=:idFiliere
            WHERE id_matiere=:id
        ");
        $stmt->execute([
            'nom'=>$nom,
            'idFiliere'=>$idFiliere,
            'id'=>$id
        ]);
        $message_action = "Matière modifiée.";
    } else {
        $message_action = "Veuillez renseigner des informations valides.";
    }
}

if(isset($_POST['creer_matiere'])){
    $nom = isset($_POST['nom_matiere']) ? trim((string)$_POST['nom_matiere']) : '';
    $idClasse = isset($_POST['id_classe']) ? (int)$_POST['id_classe'] : 0;
    if($nom === '' || $idClasse <= 0){
        $message_action = "Veuillez renseigner une classe et un nom de matière.";
    } else {
        $stmtClasse = $pdo->prepare(
            "SELECT id_filiere FROM classe WHERE id_classe=:idClasse AND id_niveau=:idNiveau".
            ($idFiliereResp > 0 ? " AND id_filiere=:idFiliere" : "").
            " LIMIT 1"
        );
        $params = ['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau];
        if($idFiliereResp > 0){
            $params['idFiliere'] = $idFiliereResp;
        }
        $stmtClasse->execute($params);
        $cl = $stmtClasse->fetch(PDO::FETCH_ASSOC);
        $idFiliere = $cl ? (int)$cl['id_filiere'] : 0;
        if($idFiliere <= 0){
            $message_action = "Classe invalide.";
        } else {
            $stmtFind = $pdo->prepare("SELECT id_matiere FROM matiere WHERE LOWER(nom_matiere)=LOWER(:nom) AND id_filiere=:idFiliere LIMIT 1");
            $stmtFind->execute(['nom'=>$nom, 'idFiliere'=>$idFiliere]);
            $found = $stmtFind->fetch(PDO::FETCH_ASSOC);
            if($found){
                $idMatiere = (int)$found['id_matiere'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO matiere (nom_matiere, id_filiere) VALUES (:nom, :idFiliere)");
                $stmt->execute([
                    'nom'=>$nom,
                    'idFiliere'=>$idFiliere
                ]);
                $idMatiere = (int)$pdo->lastInsertId();
            }

            if($hasClasseMatiere){
                try {
                    $stmtLink = $pdo->prepare("INSERT INTO classe_matiere (id_classe, id_matiere) VALUES (:idClasse, :idMatiere)");
                    $stmtLink->execute(['idClasse'=>$idClasse, 'idMatiere'=>$idMatiere]);
                    $message_action = "Matière ajoutée à la classe.";
                } catch (PDOException $e){
                    $message_action = "Matière déjà ajoutée à cette classe.";
                }
            } else {
                $message_action = "Matière créée (mode filière).";
            }
        }
    }
}


$filieres = [];
if($idFiliereResp > 0){
    $stmtFilieres = $pdo->prepare("SELECT * FROM filiere WHERE id_filiere=:id LIMIT 1");
    $stmtFilieres->execute(['id'=>$idFiliereResp]);
    $f = $stmtFilieres->fetch(PDO::FETCH_ASSOC);
    if($f){
        $filieres = [$f];
    }
} else {
    $stmtFilieres = $pdo->prepare("
        SELECT DISTINCT f.*
        FROM filiere f
        JOIN classe c ON c.id_filiere=f.id_filiere
        WHERE c.id_niveau=:idNiveau
        ORDER BY f.nom_filiere
    ");
    $stmtFilieres->execute(['idNiveau'=>$idNiveau]);
    $filieres = $stmtFilieres->fetchAll(PDO::FETCH_ASSOC);
}

$stmtClasses = $pdo->prepare($idFiliereResp > 0
    ? "\n        SELECT c.id_classe, c.id_filiere, c.nom_classe, c.description_classe, f.nom_filiere\n        FROM classe c\n        LEFT JOIN filiere f ON f.id_filiere=c.id_filiere\n        WHERE c.id_niveau=:idNiveau AND c.id_filiere=:idFiliere\n        ORDER BY c.nom_classe\n    "
    : "\n        SELECT c.id_classe, c.id_filiere, c.nom_classe, c.description_classe, f.nom_filiere\n        FROM classe c\n        LEFT JOIN filiere f ON f.id_filiere=c.id_filiere\n        WHERE c.id_niveau=:idNiveau\n        ORDER BY f.nom_filiere, c.nom_classe\n    "
);
$params = ['idNiveau'=>$idNiveau];
if($idFiliereResp > 0){
    $params['idFiliere'] = $idFiliereResp;
}
$stmtClasses->execute($params);
$classes = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);
$matiereEdit = null;
if(isset($_GET['edit'])){
    $idEdit = (int)$_GET['edit'];
    if($idEdit > 0){
        $stmt = $pdo->prepare("SELECT * FROM matiere WHERE id_matiere=:id LIMIT 1");
        $stmt->execute(['id'=>$idEdit]);
        $matiereEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
$stmtMatieres = $pdo->prepare($hasClasseMatiere
    ? "
    SELECT DISTINCT m.*, f.nom_filiere
    FROM matiere m
    JOIN filiere f ON m.id_filiere=f.id_filiere
    JOIN classe_matiere cm ON cm.id_matiere=m.id_matiere
    JOIN classe c ON c.id_classe=cm.id_classe
    WHERE c.id_niveau=:idNiveau".($idFiliereResp > 0 ? " AND c.id_filiere=:idFiliere" : "")."
    ORDER BY f.nom_filiere, m.nom_matiere
    "
    : ($idFiliereResp > 0
        ? "
        SELECT m.*, f.nom_filiere
        FROM matiere m
        JOIN filiere f ON m.id_filiere=f.id_filiere
        WHERE m.id_filiere=:idFiliere
        ORDER BY f.nom_filiere, m.nom_matiere
        "
        : "
        SELECT DISTINCT m.*, f.nom_filiere
        FROM matiere m
        JOIN filiere f ON m.id_filiere=f.id_filiere
        JOIN classe c ON c.id_filiere=f.id_filiere
        WHERE c.id_niveau=:idNiveau
        ORDER BY f.nom_filiere, m.nom_matiere
        "
    )
);
$params = ['idNiveau'=>$idNiveau];
if(!$hasClasseMatiere && $idFiliereResp > 0){
    $params = ['idFiliere'=>$idFiliereResp];
} else if($hasClasseMatiere && $idFiliereResp > 0){
    $params['idFiliere'] = $idFiliereResp;
}
$stmtMatieres->execute($params);
$matieres = $stmtMatieres->fetchAll(PDO::FETCH_ASSOC);

$matieresByFiliereId = [];
if(is_array($matieres)){
    foreach($matieres as $m){
        $idF = isset($m['id_filiere']) ? (int)$m['id_filiere'] : 0;
        if($idF <= 0){
            continue;
        }
        if(!isset($matieresByFiliereId[$idF])){
            $matieresByFiliereId[$idF] = [];
        }
        $matieresByFiliereId[$idF][] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp - Responsable - Matières</title>
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
                    <h1>Matières</h1>
                    <p>Gestion des matières</p>
                </div>
                <div class="dash-actions">
                    <a class="btn btn-secondary" href="parametres.php">Retour Paramètres</a>
                    <span class="dash-pill">Total: <?= is_array($matieres) ? count($matieres) : 0 ?></span>
                </div>
            </div>

            <?php if($message_action !== ''): ?>
                <div class="card" style="margin-top:16px;">
                    <p><?= htmlspecialchars($message_action) ?></p>
                </div>
            <?php endif; ?>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <?php if($matiereEdit): ?>
                    <div class="card">
                        <h2>Modifier une matière</h2>
                        <form method="post">
                            <input type="hidden" name="id_matiere" value="<?= (int)$matiereEdit['id_matiere'] ?>">
                            <input type="text" name="nom_matiere" value="<?= htmlspecialchars($matiereEdit['nom_matiere']) ?>" required>
                            <select name="id_filiere" required>
                                <?php foreach($filieres as $f): ?>
                                    <option value="<?= (int)$f['id_filiere'] ?>" <?= ((int)$matiereEdit['id_filiere']===(int)$f['id_filiere']) ? 'selected' : '' ?>><?= htmlspecialchars(filiere_label($f['nom_filiere'], $libelleNiveau)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="auth-actions">
                                <button class="btn btn-primary" name="modifier_matiere" type="submit">Enregistrer</button>
                                <a class="btn btn-secondary" href="matiere.php">Annuler</a>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <h2 style="margin:0;">Liste des matières</h2>
                            <button class="btn btn-primary" type="button" id="btnAddMatiere">Ajouter</button>
                        </div>

                        <div id="boxAddMatiere" style="display:none; margin-top:12px;">
                            <form method="post">
                                <input type="text" name="nom_matiere" placeholder="Nom matière" required>
                                <select name="id_classe" required>
                                    <option value="">-- Classe --</option>
                                    <?php foreach($classes as $c): ?>
                                        <?php
                                            $desc = isset($c['description_classe']) ? trim((string)$c['description_classe']) : '';
                                            $base = $desc !== '' ? $desc : (string)$c['nom_classe'];
                                            $label = $libelleNiveau !== '' ? ($libelleNiveau.' — '.$base) : $base;
                                        ?>
                                        <option value="<?= (int)$c['id_classe'] ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="auth-actions">
                                    <button class="btn btn-primary" name="creer_matiere" type="submit">Créer</button>
                                </div>
                            </form>
                        </div>

                        <?php if(empty($classes)): ?>
                            <p style="margin-top:12px;">Aucune classe pour le moment. Ajoutez d'abord une classe.</p>
                        <?php else: ?>
                        <?php foreach($classes as $c): ?>
                            <?php
                                $idF = isset($c['id_filiere']) ? (int)$c['id_filiere'] : 0;
                                if($hasClasseMatiere){
                                    $stmtItems = $pdo->prepare("\n                                        SELECT m.*\n                                        FROM classe_matiere cm\n                                        JOIN matiere m ON m.id_matiere=cm.id_matiere\n                                        WHERE cm.id_classe=:idClasse\n                                        ORDER BY m.nom_matiere\n                                    ");
                                    $stmtItems->execute(['idClasse'=>(int)$c['id_classe']]);
                                    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                                } else {
                                    $items = $idF > 0 && isset($matieresByFiliereId[$idF]) ? $matieresByFiliereId[$idF] : [];
                                }
                                $desc = isset($c['description_classe']) ? trim((string)$c['description_classe']) : '';
                                $base = $desc !== '' ? $desc : (string)$c['nom_classe'];
                                $labelClasse = $libelleNiveau !== '' ? ($libelleNiveau.' — '.$base) : $base;
                            ?>
                            <details style="margin-top:12px;">
                                <summary style="display:flex; align-items:center; justify-content:space-between; gap:10px; cursor:pointer; padding:10px 12px; border-radius:14px; background: rgba(31,42,68,0.03); border:1px solid rgba(31,42,68,0.08);">
                                    <span><strong><?= htmlspecialchars($labelClasse) ?></strong></span>
                                    <span class="btn btn-secondary" data-details-label style="padding:8px 12px;">Voir</span>
                                </summary>
                                <div style="margin-top:10px;">
                                    <?php if(empty($items)): ?>
                                        <p>Aucune matière.</p>
                                    <?php else: ?>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($items as $m): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($m['nom_matiere']) ?></td>
                                                    <td>
                                                        <a class="btn btn-secondary" href="matiere.php?edit=<?= (int)$m['id_matiere'] ?>">Modifier</a>
                                                        <form method="post" style="display:inline-block;" onsubmit="return confirm('Supprimer cette matière ?');">
                                                            <input type="hidden" name="id_classe" value="<?= (int)$c['id_classe'] ?>">
                                                            <input type="hidden" name="id_matiere" value="<?= (int)$m['id_matiere'] ?>">
                                                            <button class="btn btn-danger" name="supprimer_matiere" type="submit">Supprimer</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../app.js"></script>
<script>
    (function(){
        var btn = document.getElementById('btnAddMatiere');
        var box = document.getElementById('boxAddMatiere');
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
