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

if(isset($_POST['supprimer_etudiant'])){
    $id = isset($_POST['id_etudiant']) ? (int)$_POST['id_etudiant'] : 0;
    if($id > 0){
        try {
            $idNiveau = isset($_SESSION['id_niveau']) ? (int)$_SESSION['id_niveau'] : 0;
            if($idNiveau <= 0){
                $stmtN = $pdo->prepare("SELECT id_niveau FROM responsable WHERE id_responsable=:id LIMIT 1");
                $stmtN->execute(['id'=>$_SESSION['id']]);
                $r = $stmtN->fetch(PDO::FETCH_ASSOC);
                $idNiveau = $r && $r['id_niveau'] !== null ? (int)$r['id_niveau'] : 0;
                $_SESSION['id_niveau'] = $idNiveau > 0 ? $idNiveau : null;
            }

            $stmt = $pdo->prepare("
                DELETE e
                FROM etudiant e
                JOIN classe c ON c.id_classe=e.id_classe
                WHERE e.id_etudiant=:id AND c.id_niveau=:idNiveau
            ");
            $stmt->execute(['id'=>$id, 'idNiveau'=>$idNiveau]);
            $message_action = "Étudiant supprimé.";
        } catch (PDOException $e){
            $message_action = "Impossible de supprimer cet étudiant (il a des notes/absences).";
        }
    }
}

if(isset($_POST['modifier_etudiant'])){
    $id = isset($_POST['id_etudiant']) ? (int)$_POST['id_etudiant'] : 0;
    $matricule = isset($_POST['matricule']) ? trim($_POST['matricule']) : '';
    $nom = isset($_POST['nom_etudiant']) ? trim($_POST['nom_etudiant']) : '';
    $prenom = isset($_POST['prenom_etudiant']) ? trim($_POST['prenom_etudiant']) : '';
    $idClasse = isset($_POST['id_classe']) ? (int)$_POST['id_classe'] : 0;
    if($id > 0 && $nom !== '' && $prenom !== '' && $idClasse > 0 && $matricule !== ''){
        $idNiveau = isset($_SESSION['id_niveau']) ? (int)$_SESSION['id_niveau'] : 0;
        if($idNiveau <= 0){
            $stmtN = $pdo->prepare("SELECT id_niveau FROM responsable WHERE id_responsable=:id LIMIT 1");
            $stmtN->execute(['id'=>$_SESSION['id']]);
            $r = $stmtN->fetch(PDO::FETCH_ASSOC);
            $idNiveau = $r && $r['id_niveau'] !== null ? (int)$r['id_niveau'] : 0;
            $_SESSION['id_niveau'] = $idNiveau > 0 ? $idNiveau : null;
        }

        $stmtChk = $pdo->prepare("SELECT id_classe FROM classe WHERE id_classe=:idClasse AND id_niveau=:idNiveau LIMIT 1");
        $stmtChk->execute(['idClasse'=>$idClasse, 'idNiveau'=>$idNiveau]);
        $classeOk = $stmtChk->fetch(PDO::FETCH_ASSOC);

        if(!$classeOk){
            $message_action = "Classe non autorisée.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE etudiant e
                JOIN classe c ON c.id_classe=e.id_classe
                SET e.matricule=:matricule, e.nom_etudiant=:nom, e.prenom_etudiant=:prenom, e.id_classe=:idClasse
                WHERE e.id_etudiant=:id AND c.id_niveau=:idNiveau
            ");
            $stmt->execute([
                'matricule'=>$matricule,
                'nom'=>$nom,
                'prenom'=>$prenom,
                'idClasse'=>$idClasse,
                'id'=>$id,
                'idNiveau'=>$idNiveau
            ]);
            $message_action = "Étudiant modifié.";
        }
    } else {
        $message_action = "Veuillez renseigner des informations valides.";
    }
}

if(isset($_POST['creer_etudiant'])){
    $stmt = $pdo->prepare("INSERT INTO etudiant (matricule, nom_etudiant, prenom_etudiant, id_classe) VALUES (:matricule, :nom, :prenom, :idClasse)");
    $stmt->execute([
        'matricule'=>$_POST['matricule'],
        'nom'=>$_POST['nom_etudiant'],
        'prenom'=>$_POST['prenom_etudiant'],
        'idClasse'=>$_POST['id_classe']
    ]);
}

$idNiveau = isset($_SESSION['id_niveau']) ? (int)$_SESSION['id_niveau'] : 0;
if($idNiveau <= 0){
    $stmtN = $pdo->prepare("SELECT id_niveau FROM responsable WHERE id_responsable=:id LIMIT 1");
    $stmtN->execute(['id'=>$_SESSION['id']]);
    $r = $stmtN->fetch(PDO::FETCH_ASSOC);
    $idNiveau = $r && $r['id_niveau'] !== null ? (int)$r['id_niveau'] : 0;
    $_SESSION['id_niveau'] = $idNiveau > 0 ? $idNiveau : null;
}

$stmtClasses = $pdo->prepare("\n    SELECT c.*, f.nom_filiere\n    FROM classe c\n    LEFT JOIN filiere f ON f.id_filiere=c.id_filiere\n    WHERE c.id_niveau=:idNiveau\n    ORDER BY f.nom_filiere, c.nom_classe\n");
$stmtClasses->execute(['idNiveau'=>$idNiveau]);
$classes = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);
$idClasseFiltre = isset($_GET['id_classe']) ? (int)$_GET['id_classe'] : 0;
$classeFiltreInfo = null;
if($idClasseFiltre > 0){
    $stmt = $pdo->prepare("\n        SELECT c.*, f.nom_filiere\n        FROM classe c\n        LEFT JOIN filiere f ON f.id_filiere=c.id_filiere\n        WHERE c.id_classe=:id AND c.id_niveau=:idNiveau\n        LIMIT 1\n    ");
    $stmt->execute(['id'=>$idClasseFiltre, 'idNiveau'=>$idNiveau]);
    $classeFiltreInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$classeFiltreInfo){
        $idClasseFiltre = 0;
    }
}
$etudiantEdit = null;
if(isset($_GET['edit'])){
    $idEdit = (int)$_GET['edit'];
    if($idEdit > 0){
        $stmt = $pdo->prepare("
            SELECT e.*
            FROM etudiant e
            JOIN classe c ON c.id_classe=e.id_classe
            WHERE e.id_etudiant=:id AND c.id_niveau=:idNiveau
            LIMIT 1
        ");
        $stmt->execute(['id'=>$idEdit, 'idNiveau'=>$idNiveau]);
        $etudiantEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
$etudiants = [];
if($idClasseFiltre > 0){
    $stmt = $pdo->prepare("
        SELECT e.*, c.nom_classe AS classe
        FROM etudiant e
        JOIN classe c ON e.id_classe=c.id_classe
        WHERE e.id_classe=:idClasse AND c.id_niveau=:idNiveau
        ORDER BY e.nom_etudiant, e.prenom_etudiant
    ");
    $stmt->execute(['idClasse'=>$idClasseFiltre, 'idNiveau'=>$idNiveau]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT e.*, c.nom_classe AS classe
        FROM etudiant e
        JOIN classe c ON e.id_classe=c.id_classe
        WHERE c.id_niveau=:idNiveau
        ORDER BY e.nom_etudiant, e.prenom_etudiant
    ");
    $stmt->execute(['idNiveau'=>$idNiveau]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$classesIndex = [];
foreach($classes as $cl){
    $classesIndex[(int)$cl['id_classe']] = $cl;
}

$etudiantsParClasse = [];
foreach($etudiants as $e){
    $idCl = isset($e['id_classe']) ? (int)$e['id_classe'] : 0;
    if($idCl <= 0){
        continue;
    }
    if(!isset($etudiantsParClasse[$idCl])){
        $etudiantsParClasse[$idCl] = [];
    }
    $etudiantsParClasse[$idCl][] = $e;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>ScolApp - Responsable - Étudiants</title>
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
                    <h1>Étudiants</h1>
                    <p>Gestion des étudiants<?= ($idClasseFiltre > 0 && $classeFiltreInfo) ? ' — '.htmlspecialchars($classeFiltreInfo['nom_classe']) : '' ?></p>
                </div>
                <div class="dash-actions">
                    <a class="btn btn-secondary" href="parametres.php">Retour Paramètres</a>
                    <span class="dash-pill">Total: <?= is_array($etudiants) ? count($etudiants) : 0 ?></span>
                </div>
            </div>

            <?php if($message_action !== ''): ?>
                <div class="card" style="margin-top:16px;">
                    <p><?= htmlspecialchars($message_action) ?></p>
                </div>
            <?php endif; ?>

            <div class="dash-grid" style="grid-template-columns: 1fr;">
                <div class="dash-col">
                    <?php if($etudiantEdit): ?>
                    <div class="card">
                        <h2>Modifier un étudiant</h2>
                        <form method="post">
                            <input type="hidden" name="id_etudiant" value="<?= (int)$etudiantEdit['id_etudiant'] ?>">
                            <input type="text" name="matricule" value="<?= htmlspecialchars($etudiantEdit['matricule']) ?>" required placeholder="Matricule">
                            <input type="text" name="nom_etudiant" value="<?= htmlspecialchars($etudiantEdit['nom_etudiant'] ?? '') ?>" required>
                            <input type="text" name="prenom_etudiant" value="<?= htmlspecialchars($etudiantEdit['prenom_etudiant'] ?? '') ?>" required>
                            <select name="id_classe" required>
                                <?php foreach($classes as $cl): ?>
                                    <option value="<?= (int)$cl['id_classe'] ?>" <?= ((int)$etudiantEdit['id_classe']===(int)$cl['id_classe']) ? 'selected' : '' ?>><?= htmlspecialchars($cl['nom_classe'].($cl['nom_filiere'] ? ' - '.$cl['nom_filiere'] : '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="auth-actions">
                                <button class="btn btn-primary" name="modifier_etudiant" type="submit">Enregistrer</button>
                                <a class="btn btn-secondary" href="etudiant.php">Annuler</a>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <h2 style="margin:0;">Liste des étudiants</h2>
                            <button class="btn btn-primary" type="button" id="btnAddEtudiant">Ajouter</button>
                        </div>

                        <div id="boxAddEtudiant" style="display:none; margin-top:12px;">
                            <form method="post">
                                <input type="text" name="matricule" placeholder="Matricule" required>
                                <input type="text" name="nom_etudiant" placeholder="Nom" required>
                                <input type="text" name="prenom_etudiant" placeholder="Prénom">
                                <select name="id_classe">
                                    <?php foreach($classes as $cl): ?>
                                        <option value="<?= $cl['id_classe'] ?>" <?= ($idClasseFiltre>0 && (int)$cl['id_classe']===(int)$idClasseFiltre) ? 'selected' : '' ?>><?= htmlspecialchars($cl['nom_classe'].($cl['nom_filiere'] ? ' - '.$cl['nom_filiere'] : '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="auth-actions">
                                    <button class="btn btn-primary" name="creer_etudiant" type="submit">Ajouter</button>
                                </div>
                            </form>
                        </div>

                        <?php if($idClasseFiltre > 0): ?>
                            <?php
                                $cl = $classeFiltreInfo;
                                $labelClasse = $cl ? ($cl['nom_classe'].(!empty($cl['nom_filiere']) ? ' - '.$cl['nom_filiere'] : '')) : 'Classe';
                                $liste = isset($etudiantsParClasse[$idClasseFiltre]) ? $etudiantsParClasse[$idClasseFiltre] : [];
                            ?>
                            <h3 style="margin-top:10px;">Classe : <?= htmlspecialchars($labelClasse) ?></h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($liste as $e): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($e['matricule']) ?></td>
                                            <td><?= htmlspecialchars($e['nom_etudiant'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($e['prenom_etudiant'] ?? '') ?></td>
                                            <td>
                                                <a class="btn btn-secondary" href="etudiant.php?edit=<?= (int)$e['id_etudiant'] ?>">Modifier</a>
                                                <form method="post" style="display:inline-block;" onsubmit="return confirm('Supprimer cet étudiant ?');">
                                                    <input type="hidden" name="id_etudiant" value="<?= (int)$e['id_etudiant'] ?>">
                                                    <button class="btn btn-danger" name="supprimer_etudiant" type="submit">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div class="auth-actions" style="margin-top:12px;">
                                <a class="btn btn-secondary" href="etudiant.php">Fermer</a>
                            </div>
                        <?php else: ?>
                            <p style="margin-top:10px;">Choisissez une classe puis cliquez sur <strong>Voir</strong> pour afficher la liste des étudiants.</p>
                            <div class="grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                                <?php foreach($classes as $cl): ?>
                                    <?php $labelClasse = $cl['nom_classe'].(!empty($cl['nom_filiere']) ? ' - '.$cl['nom_filiere'] : ''); ?>
                                    <div class="section" style="margin:0;">
                                        <h3 style="margin:0 0 10px 0;"><?= htmlspecialchars($labelClasse) ?></h3>
                                        <a class="btn btn-secondary" href="etudiant.php?id_classe=<?= (int)$cl['id_classe'] ?>">Voir</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
        var btn = document.getElementById('btnAddEtudiant');
        var box = document.getElementById('boxAddEtudiant');
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
