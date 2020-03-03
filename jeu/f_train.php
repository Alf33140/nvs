<?php

/**
 * Fonction permettant de déplacer le train sur une case x/y
 */
function deplacement_train($mysqli, $id_instance_train, $x_train, $y_train, $image_train) {
	
	// Modification carte 
	$sql_c1 = "UPDATE carte SET idPerso_carte=NULL, occupee_carte='0', image_carte=NULL WHERE idPerso_carte='$id_instance_train'";
	$mysqli->query($sql_c1);
	
	$sql_c2 = "UPDATE carte SET idPerso_carte='$id_instance_train', occupee_carte='1', image_carte='$image_train' WHERE x_carte='$x_train' AND y_carte='$y_train'";
	$mysqli->query($sql_c2);
	
	// MAJ coordonnées persos dans le train
	$sql_u_perso = "UPDATE perso SET x_perso='$x_train', y_perso='$y_train' WHERE id_perso IN (SELECT id_perso FROM perso_in_train WHERE id_train='$id_instance_train')";
	$mysqli->query($sql_u_perso);
}

/**
 * Fonction permettant de determiner si un train est arrivée à destination
 *
 */
function est_arrivee($mysqli, $x_train, $y_train, $gare_arrivee) {
	
	$sql = "SELECT count(*) as cases_gare_arrivee FROM carte 
			WHERE x_carte>=$x_train-1 AND x_carte<=$x_train+1 AND y_carte>=$y_train-1 AND y_carte<=$y_train+1 
			AND idPerso_carte='$gare_arrivee'";
	$res = $mysqli->query($sql);
	$t = $res->fetch_assoc();
	
	$nb_case_gare_arrivee = $t['cases_gare_arrivee'];
	
	if ($nb_case_gare_arrivee > 0) {
		return 1;
	} else {
		return 0;
	}	
}

/**
 * Fonction permettant de décharger les persos d'un train dans une gare
 */
function dechargement_persos_train($mysqli, $id_instance_train, $gare_arrivee, $x_gare_arrivee, $y_gare_arrivee) {
	
	// Récupération des persos dans le train 
	$sql_pt = "SELECT id_perso FROM perso_in_train WHERE id_train='$id_instance_train'";
	$res_pt = $mysqli->query($sql_pt);
	
	while ($t_pt = $res_pt->fetch_assoc()) {
		
		$id_perso_dechargement = $t_pt['id_perso'];
		
		dechargement_perso_train($mysqli, $id_perso_dechargement, $gare_arrivee, $x_gare_arrivee, $y_gare_arrivee);
		
	}
}

/**
 * Fonction permettant de décharger un perso d'un train dans une gare
 */
function dechargement_perso_train($mysqli, $id_perso_dechargement, $gare_arrivee, $x_gare_arrivee, $y_gare_arrivee) {
	
	// On le supprime du train
	$sql_dt = "DELETE FROM perso_in_train WHERE id_perso='$id_perso_dechargement'";
	$mysqli->query($sql_dt);
	
	// On décharge le perso dans la gare
	$sql_pg = "INSERT INTO perso_in_batiment VALUES ('$id_perso_dechargement','$gare_arrivee')";
	$mysqli->query($sql_pg);
	
	$sql_p = "UPDATE perso SET x_perso='$x_gare_arrivee', y_perso='$y_gare_arrivee' WHERE id_perso='$id_perso_dechargement'";
	$mysqli->query($sql_p);
}


/**
 * Fonction permettant de charger les persos dans le train si ils ont un ticket
 */
function chargement_persos_train($mysqli, $id_instance_train, $x_train, $y_train, $nouvelle_direction, $gare_arrivee, $camp_train) {
	
	// récupération des persos dans cette gare ayant un ticket pour la nouvelle direction
	$sql_perso_ticket_dest = "SELECT id_perso FROM perso_as_objet 
								WHERE id_objet='1' 
								AND capacite_objet='$nouvelle_direction' 
								AND id_perso IN (SELECT perso_in_batiment.id_perso 
												FROM perso_in_batiment, perso 
												WHERE perso.id_perso = perso_in_batiment.id_perso 
												AND id_instanceBat = '$gare_arrivee' 
												AND clan=$camp_train)";
	$res_perso_ticket_dest = $mysqli->query($sql_perso_ticket_dest);
		
	while ($t_perso_ticket_dest = $res_perso_ticket_dest->fetch_assoc()) {
			
		$id_perso_chargement = $t_perso_ticket_dest['id_perso'];
		
		chargement_perso_train($mysqli, $id_perso_chargement, $id_instance_train, $x_train, $y_train, $nouvelle_direction);
	}	
}

/**
 * Fonction permettant de charger un perso dans le train
 */
function chargement_perso_train($mysqli, $id_perso_chargement, $id_instance_train, $x_train, $y_train, $nouvelle_direction) {
	
	// On supprime le ticket de l'inventaire
	$sql_delete_ticket = "DELETE FROM perso_as_objet WHERE id_perso='$id_perso_chargement' AND id_objet='1' AND capacite_objet='$nouvelle_direction' LIMIT 1";
	$mysqli->query($sql_delete_ticket);
	
	// On supprime le perso du batiment
	$sql_delete_bat = "DELETE FROM perso_in_batiment WHERE id_perso='$id_perso_chargement'";
	$mysqli->query($sql_delete_bat);
	
	// On charge les persos dans le train
	$sql_chargement_train = "INSERT INTO perso_in_train VALUES ('$id_instance_train','$id_perso_chargement')";
	$mysqli->query($sql_chargement_train);
	
	// MAJ coordonnées perso chargés sur les coordonnées du train 
	$sql_maj_perso = "UPDATE perso SET x_perso='$x_train', y_perso='$y_train' WHERE id_perso='$id_perso_chargement'";
	$mysqli->query($sql_maj_perso);
}
?>