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

$idNiveau = isset($_SESSION['id_niveau']) ? (int)$_SESSION['id_niveau'] : 0;
if($idNiveau <= 0){
    $stmtN = $pdo->prepare("SELECT id_niveau FROM responsable WHERE id_responsable=:id LIMIT 1");
    $stmtN->execute(['id'=>$_SESSION['id']]);
    $r = $stmtN->fetch(PDO::FETCH_ASSOC);
    $idNiveau = $r && $r['id_niveau'] !== null ? (int)$r['id_niveau'] : 0;
    $_SESSION['id_niveau'] = $idNiveau > 0 ? $idNiveau : null;
}

$stmtMoyennes = $pdo->prepare("
    SELECT e.id_etudiant, e.nom_etudiant, e.prenom_etudiant,
           c.nom_classe AS classe,
           f.nom_filiere AS filiere,
           AVG(s.note) AS moyenne_generale
    FROM etudiant e
    JOIN classe c ON e.id_classe=c.id_classe
    LEFT JOIN filiere f ON f.id_filiere=c.id_filiere
    LEFT JOIN suivi s ON s.id_etudiant=e.id_etudiant
    WHERE c.id_niveau=:idNiveau
    GROUP BY e.id_etudiant
    ORDER BY f.nom_filiere, c.nom_classe, e.nom_etudiant, e.prenom_etudiant
");
$stmtMoyennes->execute(['idNiveau'=>$idNiveau]);
$notesMoyennes = $stmtMoyennes->fetchAll(PDO::FETCH_ASSOC);

$moyennesParFiliere = [];
foreach($notesMoyennes as $row){
    $f = isset($row['filiere']) && $row['filiere'] !== null ? (string)$row['filiere'] : 'Sans filière';
    if(!isset($moyennesParFiliere[$f])){
        $moyennesParFiliere[$f] = [];
    }
    $moyennesParFiliere[$f][] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
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
                    <h1>Moyennes</h1>
                    <p>Moyennes par filière (niveau automatiquement appliqué)</p>
                </div>
                <div class="dash-actions">
                    <span class="dash-pill">Total: <?= is_array($notesMoyennes) ? count($notesMoyennes) : 0 ?></span>
                    <button class="btn btn-secondary" type="button" onclick="window.print()">Imprimer</button>
                </div>
            </div>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <?php foreach($moyennesParFiliere as $nomFiliere => $rows): ?>
                        <div class="card" style="margin-bottom:16px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                                <h2 style="margin:0;">Filière : <?= htmlspecialchars($nomFiliere) ?></h2>
                                <span class="dash-pill">Étudiants: <?= count($rows) ?></span>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Classe</th>
                                        <th>Moyenne générale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($rows as $n): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($n['nom_etudiant']) ?></td>
                                            <td><?= htmlspecialchars($n['prenom_etudiant']) ?></td>
                                            <td><?= htmlspecialchars($n['classe'] ?? '') ?></td>
                                            <td><?= $n['moyenne_generale'] !== null ? round($n['moyenne_generale'],2) : '' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../app.js"></script>
</body>
</html>
