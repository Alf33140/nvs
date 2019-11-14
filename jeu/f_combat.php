<?php
/**
  * Fonction de combat d'un pnj
  * @param $de_pnj	: Le nombre de d�s du pnj
  * @param $de_pj	: Le nombre de d�s du pj
  * @return bool	  	: Si le pnj a touch� le pj
  */
function combat_pnj($de_pnj, $de_pj){
	// Score du pj
	srand((double) microtime() * 1000000);
	$score_pj = rand($de_pj, $de_pj*3);
	echo "score joueur : <b>".$score_pj."</b>";
	
	// Score du pnj
	srand((double) microtime() * 1000000);
	$score_pnj = rand($de_pnj, $de_pnj*3);
	echo "<br>score pnj : <b>".$score_pnj."</b><br>";
	
	// Si le score du pnj est sup�rieur au score du pj
	if ($score_pnj > $score_pj) { // touch�
		return 1;
	}
	else
		return 0;
}

/**
  * Fonction qui v�rifie si le perso a d�j� tu� le type de pnj pass� en param�tre et qui en retourne le nombre
  * @param $id_perso	: L'identifiant du perso
  * @param $id_pnj	: L'identifiant du pnj
  * @return int		: Le nombre de pnj du type demand� d�j� tu�
  */
function is_deja_tue_pnj($mysqli, $id_perso, $id_pnj){
	$sql = "SELECT nb_pnj FROM perso_as_killpnj WHERE id_perso='$id_perso' and id_pnj='$id_pnj'";
	$res = $mysqli->query($sql);
	$t_verif_t = $res->fetch_assoc();
	return $t_verif_t["nb_pnj"];
}

/**
  * Fonction qui r�cup�re la couleur associ�e au clan du perso
  * @param $clan_perso	: L'identifiant du clan du perso
  * @return String		: La couleur associ�e au clan du perso
  */
function couleur_clan($clan_perso){
	if($clan_perso == '1'){
		$couleur_clan_perso = 'blue';
	}
	if($clan_perso == '2'){
		$couleur_clan_perso = 'red';
	}
	if($clan_perso == '3'){
		$couleur_clan_perso = 'green';
	}
	return $couleur_clan_perso;
}

/**
  * Fonction qui r�cup�re l'identifiant de l'arme �quip�e sur la main principale perso
  * @param $id_perso	: L'identifiant du perso
  * @return int		: L'identifiant de l'arme �quip�e, 0 si pas d'arme �quip�e
  */
function id_arme_equipee($mysqli, $id_perso){
	
	// R�cup�ration de la main principale du perso
	$main_principale = recherche_main_principale($id_perso);

	$sql_arme_equipee = "SELECT perso_as_arme.id_arme FROM perso_as_arme WHERE id_perso='$id_perso' AND est_portee='1' AND (mains='$main_principale' OR mains='2')";
	$res_a = $mysqli->query($sql_arme_equipee);
	$num_a = $res_a->num_rows;
	if($num_a){
		$t_a = $res_a->fetch_assoc();
		return $t_a["id_arme"];
	}
	return 0;
	
}

/**
  * Fonction qui permet de supprimer l'arme principale du perso (par exemple quand elle se casse definitivement)
  * @param $id_perso	: L'identifiant du perso
  * @return Void
  */
function supprime_arme_principale($mysqli, $id_perso){
	// r�cup�ration de l'id de l'arme sur la main principale
	$id_arme = id_arme_equipee($id_perso);
	
	// R�cup�ration du poid de l'arme
	$sql = "SELECT poids_arme FROM arme WHERE id_arme='$id_arme'";
	$res = $mysqli->query($sql);
	$t = $res->fetch_assoc();
	$poids_arme = $t["poids_arme"];
	
	// R�cup�ration de la main principale du perso
	$main_principale = recherche_main_principale($id_perso);
	
	// Suppression de l'arme
	$sql = "DELETE FROM perso_as_arme WHERE id_perso='$id_perso' AND id_arme='$id_arme' AND est_portee='1' AND (mains='$main_principale' OR mains='2')";
	$mysqli->query($sql);
	
	// Mise � jour du poid du perso
	$sql = "UPDATE perso SET charge_perso=charge_perso - $poids_arme WHERE id_perso='$id_perso'";
	$mysqli->query($sql);
}

/**
  * Fonction qui permet de r�cup�rer la main principale du personnage
  * @param $id_perso	: L'identifiant du perso
  * @return Int		: L'identifiant de la main (0 => main gauche, 1 => main droite)
  */
function recherche_main_principale($mysqli, $id_perso){
	$sql_main = "SELECT mainPrincipale_perso FROM perso WHERE id_perso='$id_perso'";
	$res_main = $mysqli->query($sql_main);
	$t_main = $res_main->fetch_assoc();
	return $t_main["mainPrincipale_perso"];
}

/**
  * Fonction qui v�rifie si un pnj ou un pj est bien � port�e d'attaque sur la carte
  * @param $carte	: La carte sur laquelle se trouve le pj qui attaque
  * @param $id_perso	: L'identifiant du perso qui attaque
  * @param $id_cible	: L'identifiant du pj ou pnj cible (attaqu�)
  * @param $portee_min	: La port�e minimale de l'attaquant
  * @param $portee_max: La portee maximale de l'attaquant
  * @param $per_perso	: La perception du perso qui attaque
  * @return bool		: Si le pj ou le pnj est bien � portee d'attaque
  */
function is_a_portee_attaque($mysqli, $carte, $id_perso, $id_cible, $portee_min, $portee_max, $per_perso){

	if($per_perso < $portee_max){
		$portee_max = $per_perso;
	}
	
	if($portee_max < $portee_min){
		return 0;
	}

	// Requ�te qui r�cup�re les cases � port�e d'attaque
	$sql = "(SELECT idPerso_carte, occupee_carte 
			FROM $carte, perso 
			WHERE id_perso='$id_perso'
			AND x_carte>=x_perso+$portee_min AND x_carte<=x_perso+$portee_max
			AND y_carte>=y_perso-$portee_max AND y_carte<=y_perso+$portee_max)
			UNION (
				SELECT idPerso_carte, occupee_carte 
				FROM $carte, perso 
				WHERE id_perso='$id_perso'
				AND x_carte>=x_perso-$portee_max AND x_carte<=x_perso-$portee_min
				AND y_carte>=y_perso-$portee_max AND y_carte<=y_perso+$portee_max
			)
			UNION (
				SELECT idPerso_carte, occupee_carte 
				FROM $carte, perso 
				WHERE id_perso='$id_perso'
				AND y_carte>=y_perso+$portee_min AND y_carte<=y_perso+$portee_max
				AND x_carte>=x_perso-$portee_max AND x_carte<=x_perso+$portee_max
			)
			UNION (
				SELECT idPerso_carte, occupee_carte 
				FROM $carte, perso 
				WHERE id_perso='$id_perso'
				AND y_carte>=y_perso-$portee_max AND y_carte<=y_perso-$portee_min
				AND x_carte>=x_perso-$portee_max AND x_carte<=x_perso+$portee_max
			)";
	$res = $mysqli->query($sql);
	
	// On parcours ces cases
	while ($t_coor_p = $res->fetch_assoc()){
		$oc_t = $t_coor_p["occupee_carte"];
		$id_t = $t_coor_p["idPerso_carte"];
		// Si la case est occup�e
		if($oc_t) {
			// Si c'est notre cible
			if($id_t == $id_cible)
				return 1;
		}
	}
	
	return 0;
}

/**
  * Fonction qui renvoie le gain d'xp lors d'une attaque en fonction des levels des deux protagonistes (attaquant et cible)
  * @param $lvl_perso	: Le level du perso attaquant
  * @param $lvl_cible	: Le level du perso cible de l'attaque
  * @param $clan_perso	: Le clan du perso
  * @param $clan_cible	: Le clan de la cible
  * @return int		: Le nombre d'xp gagn� par l'attaquant
  */
function gain_xp_level($lvl_perso, $lvl_cible, $clan_perso, $clan_cible){
	$dif_lvl = $lvl_cible - $lvl_perso;
	if($dif_lvl <= 0){
		if($clan_cible != $clan_perso){
			$gain_xp = 2;
		}
		else {
			$gain_xp = 1;
		}
	}
	else {
		if($clan_cible != $clan_perso){
			$gain_xp = 2 + $dif_lvl;
		}
		else {
			$gain_xp = floor((2 + $dif_lvl)/2);
		}
	}
	return $gain_xp;
}

/**
  * Fonction qui r�cup�re le total de defense qu'apporte les armures sur un perso
  * @param $id_perso	: L'identifiant du perso
  * @return Int		: Le nombre correspondant au total des defenses des armures que le perso � d'�quip� sur lui, 0 si pas d'armure
  */
function defense_armure($mysqli, $id_perso){
	
	// malus
	$malus = 0;
	
	// On fait la somme des bonus en defense apport� par les armures que porte le perso
	$sql = "SELECT SUM(bonusDefense_armure) as sum_armure FROM armure, perso_as_armure
			WHERE armure.id_armure = perso_as_armure.id_armure
			AND est_portee='1' AND id_perso='$id_perso'";
	$res = $mysqli->query($sql);
	$t = $res->fetch_assoc();
	$total_armure = $t["sum_armure"];
	
	// On v�rifie s'il soufre ou non de malus s'il est nu
	if(!possede_comp_nu($id_perso)){
		// On verifie s'il porte un casque
		$sql_c = "SELECT id_armure FROM perso_as_armure WHERE id_perso='$id_perso' AND corps_armure='1' AND est_portee='1'";
		$res_c = $mysqli->query($sql_c);
		$ok_c = $res_c->num_rows;
		if($ok_c == 0)
			$malus = $malus - 1;
			
		// On verifie s'il porte une armure de corps
		$sql_co = "SELECT id_armure FROM perso_as_armure WHERE id_perso='$id_perso' AND corps_armure='3' AND est_portee='1'";
		$res_co = $mysqli->query($sql_co);
		$ok_co = $res_co->num_rows;
		if($ok_co == 0)
			$malus = $malus - 1;
			
		// On verifie s'il porte un pantalon
		$sql_p = "SELECT id_armure FROM perso_as_armure WHERE id_perso='$id_perso' AND corps_armure='8' AND est_portee='1'";
		$res_p = $mysqli->query($sql_p);
		$ok_p = $res_p->num_rows;
		if($ok_p == 0)
			$malus = $malus - 1;
			
		// On verifie s'il porte des bottes
		$sql_c = "SELECT id_armure FROM perso_as_armure WHERE id_perso='$id_perso' AND corps_armure='9' AND est_portee='1'";
		$res_c = $mysqli->query($sql_c);
		$ok_c = $res_c->num_rows;
		if($ok_c == 0)
			$malus = $malus - 1;
	}
	
	if($total_armure == null)
		$total_armure = 0;
		
	$total_armure = $total_armure - $malus;	
	return $total_armure;
}

/**
  * Fonction qui permet de v�rifier si un perso poss�de la comp�tence defense d'armure
  * @param $id_perso			: L'identifiant du perso
  * @return $nb_point			: Le nombre de point dans la comp�tence, 0 si pas poss�d�
  */
function possede_defense_armure($mysqli, $id_perso){
	$sql = "SELECT nb_points FROM perso_as_competence WHERE id_perso='$id_perso' AND id_competence='57'";
	$res = $mysqli->query($sql);
	$t = $res->fetch_assoc();
	$nb = $res->num_rows;
	
	if($nb){
		return $t['nb_points'];
	}

	return 0;
}

/**
  * Fonction qui permet de v�rifier si un perso poss�de la comp�tence nudiste inv�t�r�
  * @param $id_perso			: L'identifiant du perso
  * @return $nb_point			: Le nombre de point dans la comp�tence, 0 si pas poss�d�
  */
function possede_comp_nu($mysqli, $id_perso){
	$sql = "SELECT nb_points FROM perso_as_competence WHERE id_perso='$id_perso' AND id_competence='58'";
	$res = $mysqli->query($sql);
	$t = $res->fetch_assoc();
	$nb = $res->num_rows;
	
	if($nb){
		return $t['nb_points'];
	}

	return 0;
}  

/**
  * Fonction qui permet de v�rifier et de mettre � jour le niveau d'un perso
  * @param $id_perso			: L'identifiant du perso
  * @param $lvl_perso			: Le niveau actuel du perso
  * @param $nom_perrso		: Le nom du perso
  * @param $couleur_clan_perso	: La couleur associ�e au clan du perso
  * @return Void
  */
function maj_niveau_perso($mysqli, $id_perso, $lvl_perso, $nom_perso, $couleur_clan_perso){
	//verification si perso a assez d'xp pour changer de niveau
	// recuperation du nombre d'xp du perso apr�s attaque
	$sql2 = "SELECT xp_perso, pi_perso FROM perso WHERE id_perso='$id_perso'";
	$res2 = $mysqli->query($sql2);
	$t_perso2 = $res2->fetch_assoc();
	$xp_per = $t_perso2["xp_perso"];
	$pi_per = $t_perso2["pi_perso"];
							
	//recuperation du nombre d'xp pour le passage au grade suivant
	$sqlg = "SELECT xpDebut_niveau FROM niveau WHERE id_niveau=$lvl_perso+1";
	$resg = $mysqli->query($sqlg);
	$t_persog = $resg->fetch_assoc();
	$xpNext_lvl = $t_persog["xpDebut_niveau"];
	
	// Si son nombre d'xp est sup�rieur ou �gal au nombre d'xp n�cessaire pour atteindre le niveau suivant
	// il passe au niveau suivant
	if($xp_per >= $xpNext_lvl) {
		if($xpNext_lvl != 0){
			// maj niveau perso
			$sql = "UPDATE perso SET niveau_perso=niveau_perso+1, pi_perso=pi_perso+5 WHERE id_perso='$id_perso'";
			$mysqli->query($sql);
										
			$lvl_perso = $lvl_perso+1;
			$pi_tmp = $pi_per+5;
			
			// affichage message
			echo "<br><br>Vous �tes pass� niveau <b>".$lvl_perso."</b> !<br/><font color=red>FELICITATION !</font><br/>";
			echo "Vous avez <b>".$pi_tmp."</b> points � r�partir dans vos caract�ristiques.<br>";
										
			// maj evenement
			$sql = "INSERT INTO `evenement` VALUES ('',$id_perso,'<font color=$couleur_clan_perso>$nom_perso</font>','est pass� niveau ','','','$lvl_perso',NOW(),'0')";
			$mysqli->query($sql);
		}
		else {
			echo "<br/>Vous ne pouvez plus gagner de niveau (<b>niveau max atteint</b>).<br/>";
		}
	}
}

/** 
  * Fonction qui v�rifie si un perso est chanceux et retourne le nombre de points de chance
  * @param $id_perso	: L'identifiant du personnage
  * @return Int		: Le nombre de points dans la comp�tence chance, 0 si non chanceux
  */
function est_chanceux($mysqli, $id_perso){
	$sql = "SELECT nb_points FROM perso_as_competence WHERE id_perso='$id_perso' AND id_competence='31'";
	$res = $mysqli->query($sql);
	$t = $res->fetch_assoc();
	
	return $t['nb_points'];
}

/** 
  * Fonction qui v�rifie si un perso poss�de la comp�tence de port d'armes lourdes
  * @param $id_perso	: L'identifiant du personnage
  * @return Int			: 1 si poss�de, 0 si non
  */
function port_armes_lourdes($mysqli, $id_perso){
	$sql = "SELECT nb_points FROM perso_as_competence WHERE id_perso='$id_perso' AND id_competence='60'";
	$res = $mysqli->query($sql);
	$t = $res->fetch_assoc();
	
	return $t['nb_points'];
}

/** 
  * Fonction qui v�rifie si un perso poss�de la comp�tence de port d'armures lourdes
  * @param $id_perso	: L'identifiant du personnage
  * @return Int			: 1 si poss�de, 0 si non
  */
function port_armures_lourdes($mysqli, $id_perso){
	$sql = "SELECT nb_points FROM perso_as_competence WHERE id_perso='$id_perso' AND id_competence='61'";
	$res = $mysqli->query($sql);
	$t = $res->fetch_assoc();
	
	return $t['nb_points'];
}

/**
  * Fonction qui v�rifie si le joueur � coch� l'envoi de mail lors d'une attaque
  * @param $id_joueur	: L'identifiant du joueur
  * @return bool		: Si le joueur � coch� ou non l'envoi de mail
  */
function verif_coche_mail($mysqli, $id_joueur){
	$sql_i = "select mail_info from joueur WHERE id_joueur ='".$id_joueur."'";
	$res_i = $mysqli->query($sql_i, __LINE__, __FILE__);
	$tabAttr_i = $res_i->fetch_assoc();
	return $tabAttr_i["mail_info"];
}

/**
  * Fonction qui envoi un mail au perso qui est attaqu�
  * @param $nom_attaquant	: Nom du pj ou pnj attaquant
  * @param $id_cible		: identifiant du pj cible de l'attaque
  * @ return void
  */
function mail_attaque($mysqli, $nom_attaquant, $id_cible){
	
	// Recup�ration du mail de la cible
	$sql = "SELECT email_joueur, nom_perso FROM joueur, perso WHERE id_perso='$id_cible' AND id_joueur=idJoueur_perso";
	$res = $mysqli->query($sql);
	$t = $res->fetch_assoc();

	// Headers mail
	$headers ='From: "NAOnline"<naonline@no-reply.fr>'."\n";
	$headers .='Reply-To: naonline@no-reply.fr'."\n";
	$headers .='Content-Type: text/plain; charset="iso-8859-1"'."\n";
	$headers .='Content-Transfer-Encoding: 8bit';
	
	// Destinataire du mail
	$destinataire = $t['email_joueur'];
	$nom_cible = $t['nom_perso'];
	
	// Titre du mail
	$titre = 'Attaque re�ue';
	
	// Contenu du mail
	$message = "Votre personnage $nom_cible a re�u une attaque de $nom_attaquant";
	
	// Envoie du mail
	mail($destinataire, $titre, $message, $headers);
}
?>
