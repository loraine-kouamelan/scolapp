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

$showResults = true;

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

$stmtMoyennes = $pdo->prepare("
    SELECT e.id_etudiant, e.nom_etudiant, e.prenom_etudiant,
           c.id_classe,
           c.nom_classe AS classe,
           c.description_classe,
           f.nom_filiere AS filiere,
           AVG(n.note) AS moyenne_generale
    FROM etudiant e
    JOIN classe c ON e.id_classe=c.id_classe
    LEFT JOIN filiere f ON f.id_filiere=c.id_filiere
    LEFT JOIN notes n ON n.id_etudiant=e.id_etudiant
    WHERE c.id_niveau=:idNiveau".($idFiliereResp > 0 ? " AND c.id_filiere=:idFiliere" : "")."
    GROUP BY e.id_etudiant
    ORDER BY c.nom_classe, e.nom_etudiant, e.prenom_etudiant
");
$params = ['idNiveau'=>$idNiveau];
if($idFiliereResp > 0){
    $params['idFiliere'] = $idFiliereResp;
}
$stmtMoyennes->execute($params);
$notesMoyennes = $stmtMoyennes->fetchAll(PDO::FETCH_ASSOC);

$moyennesParClasse = [];
foreach($notesMoyennes as $row){
    $desc = isset($row['description_classe']) ? trim((string)$row['description_classe']) : '';
    $base = $desc !== '' ? $desc : (string)($row['classe'] ?? '');
    $label = $libelleNiveau !== '' ? ($libelleNiveau.' — '.$base) : $base;
    if(!isset($moyennesParClasse[$label])){
        $moyennesParClasse[$label] = [];
    }
    $moyennesParClasse[$label][] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp - Responsable - Moyennes</title>
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
                    <h1>Moyennes</h1>
                    <p>Moyennes par classe </p>
                </div>
                <div class="dash-actions">
                    <span class="dash-pill">Total: <?= is_array($notesMoyennes) ? count($notesMoyennes) : 0 ?></span>
                    <button class="btn btn-secondary" type="button" onclick="window.print()">Imprimer</button>
                </div>
            </div>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <div class="card">
                        <?php foreach($moyennesParClasse as $nomClasse => $rows): ?>
                            <details style="margin-top:12px;">
                                <summary style="display:flex; align-items:center; justify-content:space-between; gap:10px; cursor:pointer; padding:10px 12px; border-radius:14px; background: rgba(31,42,68,0.03); border:1px solid rgba(31,42,68,0.08);">
                                    <span><strong>Classe : <?= htmlspecialchars($nomClasse) ?></strong></span>
                                    <span style="display:flex; align-items:center; gap:10px;">
                                        <span class="dash-pill">Étudiants: <?= count($rows) ?></span>
                                        <span class="btn btn-secondary" data-details-label style="pointer-events:none;">Voir</span>
                                    </span>
                                </summary>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Moyenne générale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($rows as $n): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($n['nom_etudiant']) ?></td>
                                            <td><?= htmlspecialchars($n['prenom_etudiant']) ?></td>
                                            <td><?= $n['moyenne_generale'] !== null ? round($n['moyenne_generale'],2) : '' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../app.js"></script>
</body>
</html>
