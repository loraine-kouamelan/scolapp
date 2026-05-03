<?php
session_start();
require '../bd.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] != 'RESPONSABLE') {
    header("Location: connexion_principal.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);

$message_action = '';

$idNiveau = isset($_SESSION['id_niveau']) ? (int)$_SESSION['id_niveau'] : 0;
if ($idNiveau <= 0) {
    $stmtN = $pdo->prepare("SELECT id_niveau FROM responsable WHERE id_responsable=:id LIMIT 1");
    $stmtN->execute(['id' => $_SESSION['id']]);
    $r = $stmtN->fetch(PDO::FETCH_ASSOC);
    $idNiveau = $r && $r['id_niveau'] !== null ? (int)$r['id_niveau'] : 0;
    $_SESSION['id_niveau'] = $idNiveau > 0 ? $idNiveau : null;
}

$stmtLib = $pdo->prepare("SELECT libelle_niveau FROM niveau WHERE id_niveau=:id LIMIT 1");
$stmtLib->execute(['id' => $idNiveau]);
$niveauInfo = $stmtLib->fetch(PDO::FETCH_ASSOC);
$libelleNiveau = $niveauInfo && $niveauInfo['libelle_niveau'] ? (string)$niveauInfo['libelle_niveau'] : '';

$stmtDescCol = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='classe' AND column_name='description_classe' LIMIT 1");
$stmtDescCol->execute();
$hasDescriptionClasse = (bool)$stmtDescCol->fetchColumn();

$stmtRespFilCol = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='responsable' AND column_name='id_filiere' LIMIT 1");
$stmtRespFilCol->execute();
$hasResponsableFiliere = (bool)$stmtRespFilCol->fetchColumn();

$idFiliereResponsable = 0;
if ($hasResponsableFiliere) {
    $stmtRF = $pdo->prepare("SELECT id_filiere FROM responsable WHERE id_responsable=:id LIMIT 1");
    $stmtRF->execute(['id' => $_SESSION['id']]);
    $rf = $stmtRF->fetch(PDO::FETCH_ASSOC);
    $idFiliereResponsable = $rf && $rf['id_filiere'] !== null ? (int)$rf['id_filiere'] : 0;
}



function niveau_code($libelle)
{
    $l = mb_strtolower(trim((string)$libelle));
    if (preg_match('/licen[cs]e\s*1/', $l)) {
        return 'L1';
    }
    if (preg_match('/licen[cs]e\s*2/', $l)) {
        return 'L2';
    }
    if (preg_match('/licen[cs]e\s*3/', $l)) {
        return 'L3';
    }
    if (preg_match('/master\s*1/', $l)) {
        return 'M1';
    }
    if (preg_match('/master\s*2/', $l)) {
        return 'M2';
    }
    if (preg_match('/l\s*1/', $l)) {
        return 'L1';
    }
    if (preg_match('/l\s*2/', $l)) {
        return 'L2';
    }
    if (preg_match('/l\s*3/', $l)) {
        return 'L3';
    }
    if (preg_match('/m\s*1/', $l)) {
        return 'M1';
    }
    if (preg_match('/m\s*2/', $l)) {
        return 'M2';
    }
    return strtoupper(substr(preg_replace('/\s+/', '', $libelle), 0, 3));
}

function strip_niveau_prefix($filiere, $libelleNiveau)
{
    $f = trim((string)$filiere);
    $prefix = trim((string)$libelleNiveau);
    if ($prefix !== '') {
        $lp = mb_strtolower($prefix);
        $lf = mb_strtolower($f);
        if (strpos($lf, $lp) === 0) {
            $rest = trim(mb_substr($f, mb_strlen($prefix)));
            return $rest !== '' ? $rest : $f;
        }
    }
    return $f;
}

$codeNiveau = niveau_code($libelleNiveau);

if (isset($_POST['modifier_classe'])) {
    $idClasseMod = isset($_POST['id_classe']) ? (int)$_POST['id_classe'] : 0;
    $descMod = isset($_POST['description_classe']) ? trim((string)$_POST['description_classe']) : '';
    if ($idClasseMod > 0 && $descMod !== '') {
        $stmtChk = $pdo->prepare(
            "SELECT 1 FROM classe WHERE id_classe=:idClasse AND id_niveau=:idNiveau" .
            ($idFiliereResponsable > 0 ? " AND id_filiere=:idFiliere" : "") .
            " LIMIT 1"
        );
        $params = ['idClasse' => $idClasseMod, 'idNiveau' => $idNiveau];
        if ($idFiliereResponsable > 0) {
            $params['idFiliere'] = $idFiliereResponsable;
        }
        $stmtChk->execute($params);
        $ok = (bool)$stmtChk->fetchColumn();
        if (!$ok) {
            $message_action = "Classe invalide.";
        } else {
            $nomMod = trim($descMod . ' - ' . $codeNiveau);
            try {
                if ($hasDescriptionClasse) {
                    $stmtUp = $pdo->prepare("UPDATE classe SET description_classe=:desc, nom_classe=:nom WHERE id_classe=:id");
                    $stmtUp->execute(['desc' => $descMod, 'nom' => $nomMod, 'id' => $idClasseMod]);
                } else {
                    $stmtUp = $pdo->prepare("UPDATE classe SET nom_classe=:nom WHERE id_classe=:id");
                    $stmtUp->execute(['nom' => $nomMod, 'id' => $idClasseMod]);
                }
                $message_action = "Classe modifiée.";
            } catch (PDOException $e) {
                $message_action = "Impossible de modifier cette classe.";
            }
        }
    } else {
        $message_action = "Veuillez renseigner une description valide.";
    }
}

if (isset($_POST['supprimer_classe'])) {
    $idClasseSup = isset($_POST['id_classe']) ? (int)$_POST['id_classe'] : 0;
    if ($idClasseSup > 0) {
        $stmtChk = $pdo->prepare(
            "SELECT 1 FROM classe WHERE id_classe=:idClasse AND id_niveau=:idNiveau" .
            ($idFiliereResponsable > 0 ? " AND id_filiere=:idFiliere" : "") .
            " LIMIT 1"
        );
        $params = ['idClasse' => $idClasseSup, 'idNiveau' => $idNiveau];
        if ($idFiliereResponsable > 0) {
            $params['idFiliere'] = $idFiliereResponsable;
        }
        $stmtChk->execute($params);
        $ok = (bool)$stmtChk->fetchColumn();
        if (!$ok) {
            $message_action = "Classe invalide.";
        } else {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM classe WHERE id_classe=:id");
                $stmtDel->execute(['id' => $idClasseSup]);
                $message_action = "Classe supprimée.";
            } catch (PDOException $e) {
                $message_action = "Impossible de supprimer cette classe (elle est utilisée ailleurs).";
            }
        }
    }
}

$filieres = [];
if ($idFiliereResponsable > 0) {
    $stmtFil = $pdo->prepare("SELECT id_filiere, nom_filiere FROM filiere WHERE id_filiere=:id LIMIT 1");
    $stmtFil->execute(['id' => $idFiliereResponsable]);
    $f = $stmtFil->fetch(PDO::FETCH_ASSOC);
    if ($f) {
        $filieres = [$f];
    }
} else {
    $prefixLike = $libelleNiveau !== '' ? ($libelleNiveau . '%') : '%';
    $stmtFil = $pdo->prepare("SELECT id_filiere, nom_filiere FROM filiere WHERE nom_filiere LIKE :p ORDER BY nom_filiere");
    $stmtFil->execute(['p' => $prefixLike]);
    $filieres = $stmtFil->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_POST['creer_classe'])) {
    $idF = isset($_POST['id_filiere']) ? (int)$_POST['id_filiere'] : 0;
    if ($idFiliereResponsable > 0) {
        $idF = $idFiliereResponsable;
    }
    $desc = isset($_POST['description_classe']) ? trim((string)$_POST['description_classe']) : '';
    $nom = '';

    if ($idF > 0 && $desc !== '') {
        $nom = trim($desc . ' - ' . $codeNiveau);
        try {
            if ($hasDescriptionClasse) {
                $stmtIns = $pdo->prepare("INSERT INTO classe (nom_classe, description_classe, id_niveau, id_filiere) VALUES (:nom, :desc, :idNiveau, :idFiliere)");
                $stmtIns->execute(['nom' => $nom, 'desc' => $desc, 'idNiveau' => $idNiveau, 'idFiliere' => $idF]);
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO classe (nom_classe, id_niveau, id_filiere) VALUES (:nom, :idNiveau, :idFiliere)");
                $stmtIns->execute(['nom' => $nom, 'idNiveau' => $idNiveau, 'idFiliere' => $idF]);
            }
            $message_action = 'Classe créée.';
        } catch (PDOException $e) {
            $message_action = "Impossible de créer cette classe.";
        }
    } else {
        $message_action = "Veuillez renseigner une description et une filière.";
    }
}

$stmt = $pdo->prepare($idFiliereResponsable > 0
    ? "SELECT c.*, f.nom_filiere AS filiere FROM classe c LEFT JOIN filiere f ON c.id_filiere=f.id_filiere WHERE c.id_niveau=:idNiveau AND c.id_filiere=:idFiliere ORDER BY f.nom_filiere, c.nom_classe"
    : "SELECT c.*, f.nom_filiere AS filiere FROM classe c LEFT JOIN filiere f ON c.id_filiere=f.id_filiere WHERE c.id_niveau=:idNiveau ORDER BY f.nom_filiere, c.nom_classe");
$params = ['idNiveau' => $idNiveau];
if ($idFiliereResponsable > 0) {
    $params['idFiliere'] = $idFiliereResponsable;
}
$stmt->execute($params);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp - Responsable - Classes</title>
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
                    <h1>Classes</h1>
                    <p>Gestion des classes</p>
                </div>
                <div class="dash-actions">
                    <a class="btn btn-secondary" href="parametres.php">Retour Paramètres</a>
                    <span class="dash-pill">Total: <?= is_array($classes) ? count($classes) : 0 ?></span>
                </div>
            </div>

            <?php if ($message_action !== '') : ?>
                <div class="card" style="margin-top:16px;">
                    <p><?= htmlspecialchars($message_action) ?></p>
                </div>
            <?php endif; ?>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <div class="card">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <h2 style="margin:0;">Liste des classes</h2>
                            <button class="btn btn-primary" type="button" id="btnAddClasse">Ajouter</button>
                        </div>

                        <div id="boxAddClasse" style="display:none; margin-top:12px;">
                            <form method="post">
                                <label>Niveau</label>
                                <input type="text" value="<?= htmlspecialchars($libelleNiveau) ?>" disabled>

                                <?php if ($idFiliereResponsable <= 0) : ?>
                                    <label>Filière</label>
                                    <select name="id_filiere" required>
                                        <option value="">-- Choisir --</option>
                                        <?php foreach ($filieres as $f) : ?>
                                            <option value="<?= (int)$f['id_filiere'] ?>"><?= htmlspecialchars((string)$f['nom_filiere']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else : ?>
                                    <input type="hidden" name="id_filiere" value="<?= (int)$idFiliereResponsable ?>">
                                <?php endif; ?>

                                <label>Description</label>
                                <input type="text" name="description_classe" placeholder="Ex: MIAGE" required>

                                <div class="auth-actions">
                                    <button class="btn btn-primary" name="creer_classe" type="submit">Créer</button>
                                </div>
                            </form>
                        </div>

                        <?php if (empty($classes)) : ?>
                            <p style="margin-top:12px;">Aucune classe pour le moment.</p>
                        <?php else : ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Filière</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $c) : ?>
                                    <tr>
                                        <td>
                                            <?php
                                                $desc = $hasDescriptionClasse ? trim((string)($c['description_classe'] ?? '')) : '';
                                                $base = $desc !== '' ? $desc : (string)$c['nom_classe'];
                                                $prefix = trim((string)$libelleNiveau);
                                                $label = $prefix !== '' ? ($prefix . ' — ' . $base) : $base;
                                                echo htmlspecialchars($label);
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($c['filiere']) ?></td>
                                        <td>
                                            <a class="btn btn-primary" href="etudiant.php?id_classe=<?= (int)$c['id_classe'] ?>">Voir la liste</a>
                                            <button class="btn btn-secondary" type="button" onclick="toggleEditClasse(<?= (int)$c['id_classe'] ?>)">Modifier</button>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette classe ?');">
                                                <input type="hidden" name="id_classe" value="<?= (int)$c['id_classe'] ?>">
                                                <button class="btn btn-danger" name="supprimer_classe" type="submit">Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr id="editRow<?= (int)$c['id_classe'] ?>" style="display:none;">
                                        <td colspan="3">
                                            <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                                                <input type="hidden" name="id_classe" value="<?= (int)$c['id_classe'] ?>">
                                                <div style="min-width:260px; flex:1;">
                                                    <label>Description</label>
                                                    <input type="text" name="description_classe" value="<?= htmlspecialchars((string)($c['description_classe'] ?? '')) ?>" required>
                                                </div>
                                                <div class="auth-actions" style="margin:0;">
                                                    <button class="btn btn-primary" name="modifier_classe" type="submit">Enregistrer</button>
                                                    <button class="btn btn-secondary" type="button" onclick="toggleEditClasse(<?= (int)$c['id_classe'] ?>)">Annuler</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
        var btn = document.getElementById('btnAddClasse');
        var box = document.getElementById('boxAddClasse');
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

    function toggleEditClasse(id){
        var row = document.getElementById('editRow'+id);
        if(!row){
            return;
        }
        row.style.display = (row.style.display === 'none' || row.style.display === '') ? 'table-row' : 'none';
    }
</script>
</body>
</html>
