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

$stmtRespFilCol = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='responsable' AND column_name='id_filiere' LIMIT 1");
$stmtRespFilCol->execute();
$hasResponsableFiliere = (bool)$stmtRespFilCol->fetchColumn();

$stmtR = $pdo->prepare($hasResponsableFiliere
    ? "SELECT nom_responsable, prenom_responsable, id_niveau, id_filiere FROM responsable WHERE id_responsable=:id LIMIT 1"
    : "SELECT nom_responsable, prenom_responsable, id_niveau, NULL AS id_filiere FROM responsable WHERE id_responsable=:id LIMIT 1"
);
$stmtR->execute(['id'=>$_SESSION['id']]);
$responsable = $stmtR->fetch(PDO::FETCH_ASSOC);

$idNiveau = null;
if($responsable && $responsable['id_niveau'] !== null){
    $idNiveau = (int)$responsable['id_niveau'];
}

$niveau = null;
if($idNiveau !== null){
    $stmtN = $pdo->prepare("SELECT libelle_niveau FROM niveau WHERE id_niveau=:id LIMIT 1");
    $stmtN->execute(['id'=>$idNiveau]);
    $niveau = $stmtN->fetch(PDO::FETCH_ASSOC);
}

$filiereResponsable = null;
$idFiliereResponsable = null;
if($responsable && $responsable['id_filiere'] !== null){
    $idFiliereResponsable = (int)$responsable['id_filiere'];
}
if($idFiliereResponsable !== null && $idFiliereResponsable > 0){
    $stmtFR = $pdo->prepare("SELECT nom_filiere FROM filiere WHERE id_filiere=:id LIMIT 1");
    $stmtFR->execute(['id'=>$idFiliereResponsable]);
    $filiereResponsable = $stmtFR->fetch(PDO::FETCH_ASSOC);
}

$stmtC = $pdo->prepare("\n    SELECT c.*, f.nom_filiere\n    FROM classe c\n    LEFT JOIN filiere f ON f.id_filiere=c.id_filiere\n    WHERE c.id_niveau=:idNiveau\n    ORDER BY f.nom_filiere, c.nom_classe\n");
$stmtC->execute(['idNiveau'=>$idNiveau]);
$classes = $stmtC->fetchAll(PDO::FETCH_ASSOC);

$nbClasses = is_array($classes) ? count($classes) : 0;
$nbEtudiants = 0;
if($idNiveau !== null){
    $stmtE = $pdo->prepare("SELECT COUNT(*) AS nb FROM etudiant e JOIN classe c ON c.id_classe=e.id_classe WHERE c.id_niveau=:idNiveau");
    $stmtE->execute(['idNiveau'=>$idNiveau]);
    $rowE = $stmtE->fetch(PDO::FETCH_ASSOC);
    $nbEtudiants = $rowE ? (int)$rowE['nb'] : 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ScolApp - Responsable - Tableau de bord</title>
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
                    <h1>Tableau de bord</h1>
                    <p>
                        Tableau de bord —
                        <?= htmlspecialchars(trim(($responsable['nom_responsable'] ?? '').' '.($responsable['prenom_responsable'] ?? ''))) ?>
                        <?php if($niveau): ?>
                            — <?= htmlspecialchars($niveau['libelle_niveau']) ?>
                        <?php endif; ?>
                        <?php if($filiereResponsable && isset($filiereResponsable['nom_filiere'])): ?>
                            — <?= htmlspecialchars((string)$filiereResponsable['nom_filiere']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="dash-actions">
                    <span class="dash-pill">Classes: <?= (int)$nbClasses ?></span>
                    <span class="dash-pill">Étudiants: <?= (int)$nbEtudiants ?></span>
                </div>
            </div>

            <div class="dash-grid">
                <div class="dash-col">
                    <div class="card">
                        <h2>Mes classes</h2>
                        <?php if(empty($classes)): ?>
                            <p>Aucune classe associée à ce niveau.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Classe</th>
                                        <th>Filière</th>
                                        <th>Voir la liste</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($classes as $c): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                    $desc = isset($c['description_classe']) ? trim((string)$c['description_classe']) : '';
                                                    $base = $desc !== '' ? $desc : (string)$c['nom_classe'];
                                                    $prefix = $niveau && isset($niveau['libelle_niveau']) ? trim((string)$niveau['libelle_niveau']) : '';
                                                    $label = $prefix !== '' ? ($prefix.' — '.$base) : $base;
                                                    echo htmlspecialchars($label);
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($c['nom_filiere'] ?? '') ?></td>
                                            <td><a class="btn btn-primary" href="etudiant.php?id_classe=<?= (int)$c['id_classe'] ?>">Étudiants</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-col">
                    <div class="card">
                        <h2>Statistiques</h2>
                        <div class="stat-grid">
                            <div class="stat">
                                <div class="label">Classes</div>
                                <div class="value"><?= (int)$nbClasses ?></div>
                            </div>
                            <div class="stat">
                                <div class="label">Étudiants</div>
                                <div class="value"><?= (int)$nbEtudiants ?></div>
                            </div>
                            <div class="stat">
                                <div class="label">Niveau</div>
                                <div class="value"><?= htmlspecialchars($niveau ? $niveau['libelle_niveau'] : '-') ?></div>
                            </div>
                            <?php if($filiereResponsable && isset($filiereResponsable['nom_filiere'])): ?>
                            <div class="stat">
                                <div class="label">Filière</div>
                                <div class="value"><?= htmlspecialchars((string)$filiereResponsable['nom_filiere']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Accès rapide</h2>
                        <div class="auth-actions">
                            <a class="btn btn-secondary" href="classe.php">Classes</a>
                            <a class="btn btn-secondary" href="etudiant.php">Étudiants</a>
                            <a class="btn btn-secondary" href="notes.php">Notes</a>
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
