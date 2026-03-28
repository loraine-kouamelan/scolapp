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

$stmtLib = $pdo->prepare("SELECT libelle_niveau FROM niveau WHERE id_niveau=:id LIMIT 1");
$stmtLib->execute(['id'=>$idNiveau]);
$niveauInfo = $stmtLib->fetch(PDO::FETCH_ASSOC);
$libelleNiveau = $niveauInfo && $niveauInfo['libelle_niveau'] ? (string)$niveauInfo['libelle_niveau'] : '';

function niveau_code($libelle){
    $l = mb_strtolower(trim((string)$libelle));
    if(preg_match('/licen[cs]e\s*1/', $l)) return 'L1';
    if(preg_match('/licen[cs]e\s*2/', $l)) return 'L2';
    if(preg_match('/licen[cs]e\s*3/', $l)) return 'L3';
    if(preg_match('/master\s*1/', $l)) return 'M1';
    if(preg_match('/master\s*2/', $l)) return 'M2';
    if(preg_match('/l\s*1/', $l)) return 'L1';
    if(preg_match('/l\s*2/', $l)) return 'L2';
    if(preg_match('/l\s*3/', $l)) return 'L3';
    if(preg_match('/m\s*1/', $l)) return 'M1';
    if(preg_match('/m\s*2/', $l)) return 'M2';
    return strtoupper(substr(preg_replace('/\s+/', '', $libelle), 0, 3));
}

function strip_niveau_prefix($filiere, $libelleNiveau){
    $f = trim((string)$filiere);
    $prefix = trim((string)$libelleNiveau);
    if($prefix !== ''){
        $lp = mb_strtolower($prefix);
        $lf = mb_strtolower($f);
        if(strpos($lf, $lp) === 0){
            $rest = trim(mb_substr($f, mb_strlen($prefix)));
            return $rest !== '' ? $rest : $f;
        }
    }
    return $f;
}

$codeNiveau = niveau_code($libelleNiveau);
$prefixLike = $libelleNiveau !== '' ? ($libelleNiveau.'%') : '%';
$stmtFil = $pdo->prepare("SELECT id_filiere, nom_filiere FROM filiere WHERE nom_filiere LIKE :p ORDER BY nom_filiere");
$stmtFil->execute(['p'=>$prefixLike]);
$filieres = $stmtFil->fetchAll(PDO::FETCH_ASSOC);

foreach($filieres as $f){
    $idF = (int)$f['id_filiere'];
    if($idF <= 0) continue;
    $base = strip_niveau_prefix($f['nom_filiere'] ?? '', $libelleNiveau);
    $nomClasse = trim($base.' - '.$codeNiveau);

    $stmtChk = $pdo->prepare("SELECT id_classe FROM classe WHERE id_niveau=:idNiveau AND id_filiere=:idFiliere LIMIT 1");
    $stmtChk->execute(['idNiveau'=>$idNiveau, 'idFiliere'=>$idF]);
    $existe = $stmtChk->fetch(PDO::FETCH_ASSOC);

    if(!$existe){
        $stmtIns = $pdo->prepare("INSERT INTO classe (nom_classe, id_niveau, id_filiere) VALUES (:nom, :idNiveau, :idFiliere)");
        $stmtIns->execute(['nom'=>$nomClasse, 'idNiveau'=>$idNiveau, 'idFiliere'=>$idF]);
    }
}

$stmt = $pdo->prepare("SELECT c.*, f.nom_filiere AS filiere FROM classe c LEFT JOIN filiere f ON c.id_filiere=f.id_filiere WHERE c.id_niveau=:idNiveau ORDER BY f.nom_filiere, c.nom_classe");
$stmt->execute(['idNiveau'=>$idNiveau]);
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
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
                    <h1>Classes</h1>
                    <p>Gestion des classes</p>
                </div>
                <div class="dash-actions">
                    <a class="btn btn-secondary" href="parametres.php">Retour Paramètres</a>
                    <span class="dash-pill">Total: <?= is_array($classes) ? count($classes) : 0 ?></span>
                </div>
            </div>

            <?php if($message_action !== ''): ?>
                <div class="card" style="margin-top:16px;">
                    <p><?= htmlspecialchars($message_action) ?></p>
                </div>
            <?php endif; ?>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <div class="card">
                        <h2>Liste des classes</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Filière</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($classes as $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($c['nom_classe']) ?></td>
                                        <td><?= htmlspecialchars($c['filiere']) ?></td>
                                        <td>
                                            <a class="btn btn-primary" href="etudiant.php?id_classe=<?= (int)$c['id_classe'] ?>">Voir la liste</a>
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
</body>
</html>
