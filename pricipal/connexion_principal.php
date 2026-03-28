<?php
session_start();
require '../bd.php';

$message = "";

// Fetch all responsables for the selection list
$responsables = $pdo->query("SELECT id_responsable, nom_responsable, prenom_responsable, id_niveau FROM responsable ORDER BY nom_responsable, prenom_responsable")->fetchAll(PDO::FETCH_ASSOC);

if(isset($_POST['connexion_principal'])){
    $idResponsable = isset($_POST['id_responsable']) ? (int)$_POST['id_responsable'] : 0;
    $mdp = isset($_POST['motDePasse']) ? $_POST['motDePasse'] : '';

    if($idResponsable > 0){
        // Login existing responsable
        $stmt = $pdo->prepare("SELECT id_responsable, mdp_responsable, id_niveau FROM responsable WHERE id_responsable=:id LIMIT 1");
        $stmt->execute(['id'=>$idResponsable]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$p){
            $message = "Responsable introuvable.";
        } else if($mdp === ''){
            $message = "Veuillez renseigner le mot de passe.";
        } else if(!password_verify($mdp, $p['mdp_responsable'])){
            $message = "Mot de passe incorrect.";
        } else {
            $_SESSION['id'] = (int)$p['id_responsable'];
            $_SESSION['role'] = 'RESPONSABLE';
            $_SESSION['id_niveau'] = $p['id_niveau'] !== null ? (int)$p['id_niveau'] : null;
            header("Location: tb_principal.php");
            exit();
        }
    } else {
        // Register new responsable
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
        $nouveauNiveau = isset($_POST['nouveau_niveau']) ? trim($_POST['nouveau_niveau']) : '';

        $idNiveau = 0;
        if($nouveauNiveau !== ''){
            $stmtFind = $pdo->prepare("SELECT id_niveau FROM niveau WHERE libelle_niveau=:libelle LIMIT 1");
            $stmtFind->execute(['libelle'=>$nouveauNiveau]);
            $found = $stmtFind->fetch(PDO::FETCH_ASSOC);
            if($found){
                $idNiveau = (int)$found['id_niveau'];
            } else {
                try {
                    $stmtN = $pdo->prepare("INSERT INTO niveau (libelle_niveau) VALUES (:libelle)");
                    $stmtN->execute(['libelle'=>$nouveauNiveau]);
                    $idNiveau = (int)$pdo->lastInsertId();
                } catch (PDOException $e){
                    $idNiveau = 0;
                }
            }
        }

        if($nom === '' || $prenom === '' || $mdp === '' || $idNiveau <= 0){
            $message = "Veuillez renseigner le nom, le prénom, le niveau et le mot de passe.";
        } else {
            try {
                $stmtExists = $pdo->prepare("SELECT id_responsable FROM responsable WHERE id_niveau=:idNiveau LIMIT 1");
                $stmtExists->execute(['idNiveau'=>$idNiveau]);
                $deja = $stmtExists->fetch(PDO::FETCH_ASSOC);

                if($deja){
                    $message = "Un responsable existe déjà pour cette filière.";
                } else {
                $stmt2 = $pdo->prepare("INSERT INTO responsable (nom_responsable, prenom_responsable, mdp_responsable, id_niveau) VALUES (:nom, :prenom, :mdp, :idNiveau)");
                $stmt2->execute([
                    'nom'=>$nom,
                    'prenom'=>$prenom,
                    'mdp'=>password_hash($mdp, PASSWORD_DEFAULT),
                    'idNiveau'=>$idNiveau
                ]);

                $_SESSION['id'] = (int)$pdo->lastInsertId();
                $_SESSION['role'] = 'RESPONSABLE';
                $_SESSION['id_niveau'] = $idNiveau;
                header("Location: tb_principal.php");
                exit();
                }
            } catch (PDOException $e){
                $message = "Impossible de créer ce responsable (niveau déjà attribué ?).";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>ScolApp - Connexion Responsable</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-panel">
            <h1>ScolApp</h1>
            <p class="auth-subtitle">Connexion / création d'un compte responsable</p>

            <?php if($message!=""): ?>
                <div class="auth-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post">
                <label>Responsable</label>
                <select name="id_responsable" id="id_responsable">
                    <option value="0">-- Nouveau responsable --</option>
                    <?php foreach($responsables as $r): ?>
                        <option value="<?= (int)$r['id_responsable'] ?>"><?= htmlspecialchars($r['nom_responsable'].' '.$r['prenom_responsable']) ?></option>
                    <?php endforeach; ?>
                </select>

                <div id="create_fields">
                    <label>Création (si nouveau)</label>
                    <div class="auth-row">
                        <input type="text" name="nom" id="create_nom" placeholder="Nom">
                        <input type="text" name="prenom" id="create_prenom" placeholder="Prénom">
                    </div>
                    <input type="text" name="nouveau_niveau" id="create_niveau" placeholder="Niveau">
                </div>

                <label>Mot de passe</label>
                <input type="password" name="motDePasse" placeholder="Mot de passe" required>

                <div class="auth-actions">
                    <button class="btn btn-primary" name="connexion_principal" type="submit">Se connecter</button>
                    <a class="btn btn-secondary" href="../accueil.php">Retour</a>
                </div>
            </form>

            <script>
                (function(){
                    var sel = document.getElementById('id_responsable');
                    var box = document.getElementById('create_fields');
                    var nom = document.getElementById('create_nom');
                    var prenom = document.getElementById('create_prenom');
                    var niveau = document.getElementById('create_niveau');

                    function sync(){
                        var isNew = !sel || sel.value === '0';
                        if(!box){
                            return;
                        }
                        box.style.display = isNew ? '' : 'none';
                        if(nom){ nom.required = isNew; nom.disabled = !isNew; }
                        if(prenom){ prenom.required = isNew; prenom.disabled = !isNew; }
                        if(niveau){ niveau.required = isNew; niveau.disabled = !isNew; }
                    }

                    if(sel){
                        sel.addEventListener('change', sync);
                    }
                    sync();
                })();
            </script>

            <p class="auth-back"><a href="../accueil.php">Retour à l'accueil</a></p>
        </div>

        <div class="auth-illustration">
            <div class="auth-art">
                <div class="auth-art-inner">
                    <img src="../image/login-illustration.jpg" alt="Illustration">
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
