<?php
session_start();
require '../bd.php';

$message = "";

$enseignants = $pdo->query("SELECT id_enseignant, nom_enseignant, prenom_enseignant FROM enseignant ORDER BY nom_enseignant, prenom_enseignant")->fetchAll(PDO::FETCH_ASSOC);

if(isset($_POST['connexion_enseignant'])){
    $idEnseignant = isset($_POST['id_enseignant']) ? (int)$_POST['id_enseignant'] : 0;
    $mdp = isset($_POST['motDePasse']) ? $_POST['motDePasse'] : '';

    if($idEnseignant > 0){
        $stmt = $pdo->prepare("SELECT id_enseignant, mdp_enseignant FROM enseignant WHERE id_enseignant=:id LIMIT 1");
        $stmt->execute(['id'=>$idEnseignant]);
        $e = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$e){
            $message = "Enseignant introuvable.";
        } else if($mdp === ''){
            $message = "Veuillez renseigner le mot de passe.";
        } else if(!password_verify($mdp, $e['mdp_enseignant'])){
            $message = "Mot de passe incorrect.";
        } else {
            $_SESSION['id'] = (int)$e['id_enseignant'];
            $_SESSION['role'] = 'ENSEIGNANT';
            header("Location: selection.php");
            exit();
        }
    } else {
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';

        if($nom === '' || $prenom === '' || $mdp === ''){
            $message = "Veuillez renseigner le nom, le prénom et le mot de passe.";
        } else {
            $stmt2 = $pdo->prepare("INSERT INTO enseignant (nom_enseignant, prenom_enseignant, mdp_enseignant) VALUES (:nom, :prenom, :mdp)");
            $stmt2->execute([
                'nom'=>$nom,
                'prenom'=>$prenom,
                'mdp'=>password_hash($mdp, PASSWORD_DEFAULT)
            ]);

            $_SESSION['id'] = (int)$pdo->lastInsertId();
            $_SESSION['role'] = 'ENSEIGNANT';
            header("Location: selection.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>ScolApp - Connexion Enseignant</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-panel">
            <h1>ScolApp</h1>
            <p class="auth-subtitle">Connexion / création d'un compte enseignant</p>

            <?php if($message!=""): ?>
                <div class="auth-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post">
                <label>Enseignant</label>
                <select name="id_enseignant">
                    <option value="0">-- Nouvel enseignant --</option>
                    <?php foreach($enseignants as $e): ?>
                        <option value="<?= (int)$e['id_enseignant'] ?>"><?= htmlspecialchars($e['nom_enseignant'].' '.$e['prenom_enseignant']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Création (si nouveau)</label>
                <div class="auth-row">
                    <input type="text" name="nom" placeholder="Nom">
                    <input type="text" name="prenom" placeholder="Prénom">
                </div>

                <label>Mot de passe</label>
                <input type="password" name="motDePasse" placeholder="Mot de passe" required>

                <div class="auth-actions">
                    <button class="btn btn-primary" name="connexion_enseignant" type="submit">Valider</button>
                    <a class="btn btn-secondary" href="../accueil.php">Retour</a>
                </div>
            </form>

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
