<?php
session_start();
require_once("../fonctions.php");

$mysqli = db_connexion();

include ('../nb_online.php');
include ('../forum/config.php');

// recupération config jeu
$dispo = config_dispo_jeu($mysqli);
$admin = admin_perso($mysqli, $_SESSION["id_perso"]);

if($dispo || $admin){
	
	if (isset($_SESSION["id_perso"])) {
		
		//recuperation des variables de sessions
		$id = $_SESSION["id_perso"];
		
		// Le perso est-il membre de l'etat major
		$sql = "SELECT camp_em FROM perso_in_em WHERE id_perso='$id'";
		$res = $mysqli->query($sql);
		$t = $res->fetch_assoc();
		$verif = $res->num_rows;
		
		if ($verif) {
		
			$camp_em = $t['camp_em'];
			
			if ($camp_em == 1) {
				$image_em = "em_nord.png";
				$forum_parent_id = '7';
			} else {
				$image_em = "em_sud.png";
				$forum_parent_id = '8';
			}
			
			// Récupération du nombre de personnes dans l'etat major de ce camp
			$sql = "SELECT count(id_perso) as nb_persos_em FROM perso_in_em WHERE camp_em='$camp_em'";
			$res = $mysqli->query($sql);
			$t = $res->fetch_assoc();
			
			$nb_persos_em = $t["nb_persos_em"];
			
			$majorite_em = ceil($nb_persos_em / 2);
			
			// On est pour la création d'une compagnie
			if (isset($_POST['pour'])) {
				
				$id_em_creer_comp = $_POST['pour'];
				
				$sql = "INSERT INTO em_vote_creer_compagnie (id_em_creer_compagnie, id_em_perso, vote) VALUES ('$id_em_creer_comp', '$id', 1)";
				$mysqli->query($sql);
				
			}
			
			// On est contre la création d'une compagnie
			if (isset($_POST['contre'])) {
				
				$id_em_creer_comp = $_POST['contre'];
				
				$sql = "INSERT INTO em_vote_creer_compagnie (id_em_creer_compagnie, id_em_perso, vote) VALUES ('$id_em_creer_comp', '$id', 0)";
				$mysqli->query($sql);
			}
			
			// On valide la creation de la compagnie
			if (isset($_POST['creer_comp'])) {
				
				$id_em_creer_comp = $_POST['creer_comp'];
				
				$sql = "SELECT nom_compagnie, description_compagnie, id_perso FROM em_creer_compagnie WHERE id_em_creer_compagnie = '$id_em_creer_comp' ";
				$res = $mysqli->query($sql);
				$t = $res->fetch_assoc();
				
				$id_perso_comp 	= $t["id_perso"];
				$nom_comp		= addslashes($t["nom_compagnie"]);
				$desc_comp		= addslashes($t["description_compagnie"]);
				
				$lock = "LOCK TABLE (compagnies) WRITE";
				$mysqli->query($lock);
				
				// creation compagnie
				$sql = "INSERT INTO compagnies (nom_compagnie, resume_compagnie, description_compagnie, id_clan) VALUES ('$nom_comp', '', '$desc_comp', '$camp_em')";
				$mysqli->query($sql);
				
				$id_new_comp = $mysqli->insert_id;
				
				$unlock = "UNLOCK TABLES";
				$mysqli->query($unlock);
				
				// Insertion compagnie_as_contraintes
				$sql = "INSERT INTO compagnie_as_contraintes VALUES ('$id_new_comp', '1')";
				$mysqli->query($sql);
				$sql = "INSERT INTO compagnie_as_contraintes VALUES ('$id_new_comp', '2')";
				$mysqli->query($sql);
				$sql = "INSERT INTO compagnie_as_contraintes VALUES ('$id_new_comp', '3')";
				$mysqli->query($sql);
				$sql = "INSERT INTO compagnie_as_contraintes VALUES ('$id_new_comp', '4')";
				$mysqli->query($sql);
				$sql = "INSERT INTO compagnie_as_contraintes VALUES ('$id_new_comp', '5')";
				$mysqli->query($sql);
				
				// Insertion de perso dans la compagnie en tant que chef
				$sql = "INSERT INTO perso_in_compagnie (id_perso, id_compagnie, poste_compagnie, attenteValidation_compagnie) VALUES ('$id_perso_comp', '$id_new_comp', '1', '0')";
				$mysqli->query($sql);
				
				// Creation de la banque de la compagnie
				$sql = "INSERT INTO banque_as_compagnie (id_compagnie, montant) VALUES ('$id_new_comp', 0)";
				$mysqli->query($sql);
				
				// Insertion du perso dans la banque de la compagnie 
				$sql = "INSERT INTO `banque_compagnie` (`id_perso`, `montant`, `demande_emprunt`, `montant_emprunt`) VALUES ('$id_perso_comp', '0', '0', '0')";
				$mysqli->query($sql);
				
				// Suppression de la demande
				$sql = "DELETE FROM em_creer_compagnie WHERE id_em_creer_compagnie = '$id_em_creer_comp'";
				$mysqli->query($sql);
				
				//--------------------
				// -- Partie FORUM --
				//--------------------
				// Récupération de l'id perso du chef
				$sql = "SELECT id_perso FROM perso WHERE idJoueur_perso = (SELECT idJoueur_perso FROM perso WHERE id_perso='$id_perso_comp') AND chef=1";
				$res = $mysqli->query($sql);
				$t = $res->fetch_assoc();
				
				$id_perso_chef = $t['id_perso'];
				
				// Récupération de l'id de l'utilisateur sur le forum 
				$sql = "SELECT user_id FROM ".$table_prefix."users WHERE id_perso='$id_perso_chef'";
				$res = $mysqli->query($sql);
				$t = $res->fetch_assoc();
				
				$id_user_forum = $t['user_id'];
				
				$lock = "LOCK TABLE (".$table_prefix."groups) WRITE";
				$mysqli->query($lock);
				
				// Insertion group compagnie forum
				// phpbb_groups
				$sql = "INSERT INTO ".$table_prefix."groups (group_name, group_type, group_founder_manage, group_colour, group_legend, group_avatar, group_desc, group_desc_uid, group_max_recipients) 
						VALUES ('$nom_comp', 0, 0, '', 0, '', '', '', 0);";
				$mysqli->query($sql);
				
				$id_new_group = $mysqli->insert_id;
				
				$unlock = "UNLOCK TABLES";
				$mysqli->query($unlock);
				
				$lock = "LOCK TABLE (".$table_prefix."forums) WRITE";
				$mysqli->query($lock);
				
				// Insertion forum compagnie dans catégorie du camp
				// phpbb_forums
				$sql = "SELECT right_id FROM ".$table_prefix."forums where forum_id='$forum_parent_id';";
				$res = $mysqli->query($sql);
				$t = $res->fetch_assoc();
				
				$next_leftid 	= $t['right_id'];
				$next_rightid 	= $next_leftid+1;
				
				$sql = "UPDATE phpbb_forums SET left_id = left_id + 2 where left_id >= ".$next_leftid.";";
				$mysqli->query($sql);
				
				$sql = "UPDATE phpbb_forums SET right_id = right_id + 2 where right_id >= ".$next_leftid.";";
				$mysqli->query($sql);
				
				$sql = "INSERT INTO ".$table_prefix."forums (forum_name, forum_desc, left_id, right_id, parent_id, forum_type, forum_posts_approved, forum_posts_unapproved, forum_posts_softdeleted, forum_topics_approved, forum_topics_unapproved, forum_topics_softdeleted, forum_last_post_id, forum_last_poster_id, forum_last_poster_name, forum_last_poster_colour, forum_last_post_subject, forum_link, forum_password, forum_image, forum_rules, forum_rules_link, forum_rules_uid, forum_desc_uid, prune_freq, prune_days, prune_viewed, forum_parents, forum_flags) 
						VALUES ('$nom_comp', '', '$next_leftid', '$next_rightid', '$forum_parent_id', 1, 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', '', '', '', '', '', 1, 7, 7, 'a:0:{}', 48);";
				$mysqli->query($sql);
				
				$id_new_forum = $mysqli->insert_id;
				
				$unlock = "UNLOCK TABLES";
				$mysqli->query($unlock);
				
				// Seul les membres du group de la compagnie peuvent voir et utiliser ce nouveau forum
				// phpbb_acl_groups
				$sql = "INSERT INTO ".$table_prefix."acl_groups (group_id, forum_id, auth_option_id, auth_role_id, auth_setting) VALUES ('$id_new_group', '$id_new_forum', '0', '14', '0')";
				$mysqli->query($sql);
				
				// Ajout chef compagnie dans group compagnie forum
				$sql = "INSERT INTO ".$table_prefix."user_group (group_id, user_id, user_pending, group_leader) VALUES ('$id_new_group', '$id_user_forum', 0, 1);";
				$mysqli->query($sql);
				
				echo "<center><font color='blue'>Vous avez validé la création de la compagnie $nom_comp</font></center>";
				
			}
			
			// On refuse la creation de la compagnie
			if (isset($_POST['refuser_comp'])) {
				
				$id_em_creer_comp = $_POST['refuser_comp'];
				
				$sql = "SELECT nom_compagnie, id_perso FROM em_creer_compagnie WHERE id_em_creer_compagnie = '$id_em_creer_comp' ";
				$res = $mysqli->query($sql);
				$t = $res->fetch_assoc();
				
				$id_perso_comp 	= $t["id_perso"];
				$nom_comp		= addslashes($t["nom_compagnie"]);
				
				// Suppression de la demande
				$sql = "DELETE FROM em_creer_compagnie WHERE id_em_creer_compagnie = '$id_em_creer_comp'";
				$mysqli->query($sql);
				
				// TODO - envoyer un MP de refus de création
				
				echo "<center><font color='blue'>Vous avez refusé la création de la compagnie $nom_comp</font></center>";
				
			}
		
?>
		
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<title>Nord VS Sud - Etat Major</title>
		
		<!-- Required meta tags -->
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		
		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	</head>
	<body>
		<div class="container">
		
			<nav class="navbar navbar-expand-lg navbar-light bg-light">
				<a class="navbar-brand" href="#"><img src='../images/<?php echo $image_em; ?>' width="80" height="60" alt=""></a>
				<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				<div class="collapse navbar-collapse" id="navbarNavDropdown">
					<div class="navbar-nav">
						<ul class="navbar-nav">
							<li class="nav-item active">
								<a class="nav-link" href="#">Validation compagnies</a>
							</li>
						</ul>
						<ul class="navbar-nav">
							<li class="nav-item dropdown">
								<a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									Messages
								</a>
								<div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
									<a class="dropdown-item" href="em_message.php?cible=camp">Message à son camp</a>
									<a class="dropdown-item" href="em_message.php?cible=compagnie">Message aux chefs de compagnie / section</a>
									<a class="dropdown-item" href="em_message.php?cible=em">Messages aux autres membres de l'EM</a>
								</div>
							</li>
						</ul>
					</div>
				</div>
			</nav>
		
			<p align="center"><input type="button" value="Fermer cette fenêtre" onclick="window.close()"></p>
			
			<center>Nombre de membres dans l'état major : <?php echo $nb_persos_em; ?></center>
			
			<center><h2>Validation des créations de compagnie</h2></center>
			<br />
		
			<form method="post" name="creer_comp" action="etat_major.php">
		
				<table class="table">
					<thead>
						<tr>
							<th scope="col">createur</th>
							<th scope="col">nom compagnie</th>
							<th scope="col">Description compagnie</th>
							<th scope="col">Action</th>
						</tr>
					</thead>
		
		<?php
		
			// Récupération des demande de creation de compagnie
			$sql = "SELECT * FROM em_creer_compagnie WHERE camp = '$camp_em'";
			$res = $mysqli->query($sql);
			
			while ($t_c = $res->fetch_assoc()) {
				
				$id_em_creer_comp		= $t_c["id_em_creer_compagnie"];
				$id_perso_creer_comp 	= $t_c["id_perso"];
				$nom_creer_comp			= $t_c["nom_compagnie"];
				$desc_creer_comp		= nl2br($t_c["description_compagnie"]);
				
				// Récupération des infos du perso
				$sql_p = "SELECT nom_perso FROM perso WHERE id_perso='$id_perso_creer_comp'";
				$res_p = $mysqli->query($sql_p);
				$t_p = $res_p->fetch_assoc();
				
				$nom_perso_creer_comp = $t_p["nom_perso"];
				
				// Récupération des infos du vote si déjà réalisés
				$sql_v = "SELECT * FROM em_vote_creer_compagnie WHERE id_em_perso='$id' AND id_em_creer_compagnie='$id_em_creer_comp'";
				$res_v = $mysqli->query($sql_v);
				$t_v = $res_v->fetch_assoc();
				
				$vote_creer_comp = $t_v["vote"];
				
				echo "<tr>";
				echo "	<td>$nom_perso_creer_comp [$id_perso_creer_comp]</td>";
				echo "	<td>$nom_creer_comp</td>";
				echo "	<td>$desc_creer_comp</td>";
				
				if (isset($vote_creer_comp)) {
					
					$nb_pour 	= 0;
					$nb_contre 	= 0;
					
					// Récupération de tous les votes
					$sql_av = "SELECT * FROM em_vote_creer_compagnie WHERE id_em_creer_compagnie='$id_em_creer_comp'";
					$res_av = $mysqli->query($sql_av);
					while ($t_av = $res_av->fetch_assoc()) {
						
						$vote_av = $t_av["vote"];
						
						if ($vote_av == 1) {
							$nb_pour++;
						} else {
							$nb_contre++;
						}
					}
					
					// On affiche son vote
					$vote_creer_comp == 1 ? $text_vote = "<font color='blue'><b>Pour</b></font>":$text_vote = "<font color='red'><b>Contre</b></font>";
					$nb_vote_tot = $nb_pour + $nb_contre;
					
					echo "<td>";
					echo "<u>Votre vote</u> : $text_vote<br /><u>Résultat des votes existant</u> : $nb_pour Pour / $nb_contre contre";
					if ($nb_pour >= $majorite_em || $nb_contre >= $majorite_em) {
						
						// On a atteint la majorite pour les votes
						echo "<br />Vous avez atteind la majorité : ";
						
						// Majorité de pour
						if ($nb_pour >= $majorite_em) {
							echo "<button type=\"submit\" name=\"creer_comp\" value=\"$id_em_creer_comp\" class=\"btn btn-success btn-sm\">Creer la compagnie</button>";
						}
						
						// Majorité de pour
						if ($nb_contre >= $majorite_em) {
							echo "<button type=\"submit\" name=\"refuser_comp\" value=\"$id_em_creer_comp\" class=\"btn btn-danger btn-sm\">Refuser la compagnie</button>";
						}
						
					}
					echo "</td>";
				}
				else {
					// On peut voter
					echo "	<td><button type=\"submit\" name=\"pour\" value=\"$id_em_creer_comp\" class=\"btn btn-success btn-sm\">Pour</button> <button type=\"submit\" name=\"contre\" value=\"$id_em_creer_comp\" class=\"btn btn-danger btn-sm\">Contre</button></td>";
				}
				echo "</tr>";
				
			}
		?>
				
				</table>
			</form>
		</div>
		
		<!-- Optional JavaScript -->
		<!-- jQuery first, then Popper.js, then Bootstrap JS -->
		<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	</body>
</html>
<?php
		}
		else {
			// Un joueur essaye d'acceder à la page sans être de l'état major
			$text_triche = "Tentative accés page etat major sans y avoir les droits";
			
			$sql = "INSERT INTO tentative_triche (id_perso, texte_tentative) VALUES ('$id', '$text_triche')";
			$mysqli->query($sql);
			
			header("Location:jouer.php");
		}
	}
	else{
		echo "<center><font color='red'>Vous ne pouvez pas accéder à cette page, veuillez vous loguer.</font></center>";
	}
}
else {
	// logout
	$_SESSION = array(); // On écrase le tableau de session
	session_destroy(); // On détruit la session
	
	header("Location:../index2.php");
}
?>