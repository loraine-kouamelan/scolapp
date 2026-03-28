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

$idNiveau = isset($_SESSION['id_niveau']) ? (int)$_SESSION['id_niveau'] : 0;
if($idNiveau <= 0){
    $stmtN = $pdo->prepare("SELECT id_niveau FROM responsable WHERE id_responsable=:id LIMIT 1");
    $stmtN->execute(['id'=>$_SESSION['id']]);
    $r = $stmtN->fetch(PDO::FETCH_ASSOC);
    $idNiveau = $r && $r['id_niveau'] !== null ? (int)$r['id_niveau'] : 0;
    $_SESSION['id_niveau'] = $idNiveau > 0 ? $idNiveau : null;
}

$stmtC = $pdo->prepare("
    SELECT c.id_classe, c.nom_classe, f.nom_filiere
    FROM classe c
    LEFT JOIN filiere f ON f.id_filiere=c.id_filiere
    WHERE c.id_niveau=:idNiveau
    ORDER BY f.nom_filiere, c.nom_classe
");
$stmtC->execute(['idNiveau'=>$idNiveau]);
$classes = $stmtC->fetchAll(PDO::FETCH_ASSOC);

$matieres = [];
if($idClasse){
    $stmtClasse = $pdo->prepare("SELECT id_filiere FROM classe WHERE id_classe=:idClasse AND id_niveau=:idNiveau LIMIT 1");
    $stmtClasse->execute(['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau]);
    $classeInfo = $stmtClasse->fetch(PDO::FETCH_ASSOC);
    $idFiliereClasse = $classeInfo ? (int)$classeInfo['id_filiere'] : 0;

    $stmt = $pdo->prepare("SELECT * FROM matiere WHERE id_filiere=:idFiliere ORDER BY nom_matiere");
    $stmt->execute(['idFiliere'=>$idFiliereClasse]);
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$notes = [];
if($idClasse && $idMatiere){
    $stmt = $pdo->prepare("
        SELECT e.nom_etudiant AS nom, e.prenom_etudiant AS prenom, s.note
        FROM suivi s
        JOIN etudiant e ON s.id_etudiant=e.id_etudiant
        JOIN classe c ON c.id_classe=e.id_classe
        WHERE e.id_classe=:idClasse AND s.id_matiere=:idMatiere AND c.id_niveau=:idNiveau
        ORDER BY e.nom_etudiant, e.prenom_etudiant
    ");
    $stmt->execute(['idClasse'=>$idClasse,'idMatiere'=>$idMatiere,'idNiveau'=>$idNiveau]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$resultats = [];
if($idClasse){
    $stmt = $pdo->prepare("
        SELECT e.id_etudiant, e.nom_etudiant AS nom, e.prenom_etudiant AS prenom,
               m.nom_matiere AS matiere,
               s.note AS moyenne_matiere
        FROM etudiant e
        JOIN classe c ON c.id_classe=e.id_classe
        JOIN matiere m ON m.id_filiere=c.id_filiere
        LEFT JOIN suivi s ON s.id_etudiant=e.id_etudiant AND s.id_matiere=m.id_matiere
        WHERE e.id_classe=:idClasse AND c.id_niveau=:idNiveau
        ORDER BY e.nom_etudiant, e.prenom_etudiant, m.nom_matiere
    ");
    $stmt->execute(['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau]);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$moyennesGenerales = [];
if($idClasse){
    $stmt = $pdo->prepare("
        SELECT e.nom_etudiant AS nom, e.prenom_etudiant AS prenom,
               AVG(s.note) AS moyenne_generale
        FROM etudiant e
        LEFT JOIN suivi s ON s.id_etudiant=e.id_etudiant
        JOIN classe c ON c.id_classe=e.id_classe
        WHERE e.id_classe=:idClasse AND c.id_niveau=:idNiveau
        GROUP BY e.id_etudiant
        ORDER BY e.nom_etudiant, e.prenom_etudiant
    ");
    $stmt->execute(['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau]);
    $moyennesGenerales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
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
                            <label>Classe</label>
                            <select name="idClasse" onchange="this.form.submit()">
                                <option value="">-- Choisir --</option>
                                <?php foreach($classes as $c): ?>
                                    <option value="<?= $c['id_classe'] ?>" <?= ($idClasse==$c['id_classe']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nom_classe'].' — '.$c['nom_filiere']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label>Matière (pour liste des notes)</label>
                            <select name="idMatiere" <?= $idClasse ? '' : 'disabled' ?>>
                                <option value="">-- Choisir --</option>
                                <?php foreach($matieres as $m): ?>
                                    <option value="<?= $m['id_matiere'] ?>" <?= ($idMatiere==$m['id_matiere']) ? 'selected' : '' ?>><?= htmlspecialchars($m['nom_matiere']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <div class="auth-actions">
                                <button class="btn btn-primary" type="submit">Actualiser</button>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <h2>Liste des notes par matière</h2>
                        <?php if($idClasse && $idMatiere): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Valeur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($notes as $n): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($n['nom']) ?></td>
                                        <td><?= htmlspecialchars($n['prenom']) ?></td>
                                        <td><?= htmlspecialchars($n['note']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="auth-actions" style="justify-content:flex-end; margin-top:12px;">
                            <button class="btn btn-secondary" type="button" onclick="window.print()">Imprimer</button>
                        </div>
                        <?php else: ?>
                            <p>Choisis une classe et une matière.</p>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <h2>Résultats (toutes les matières)</h2>
                        <?php if($idClasse): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Matière</th>
                                    <th>Moyenne matière</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($resultats as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['nom']) ?></td>
                                        <td><?= htmlspecialchars($r['prenom']) ?></td>
                                        <td><?= htmlspecialchars($r['matiere']) ?></td>
                                        <td><?= $r['moyenne_matiere'] !== null ? round($r['moyenne_matiere'],2) : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                            <p>Choisis une classe.</p>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <h2>Moyenne générale</h2>
                        <?php if($idClasse): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Moyenne générale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($moyennesGenerales as $mg): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mg['nom']) ?></td>
                                        <td><?= htmlspecialchars($mg['prenom']) ?></td>
                                        <td><?= $mg['moyenne_generale'] !== null ? round($mg['moyenne_generale'],2) : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                            <p>Choisis une classe.</p>
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
