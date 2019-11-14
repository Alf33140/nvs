<?php
// on d�finit le nombre de secondes d�finissant l'intervalle de temps au cours duquel 
// on consid�re qu'un client est toujours en ligne (ici 3 minutes = 180 secondes)
$tps_max_connex = 180;

// on r�cup�re le nombre de secondes �coul�es depuis le 1er janvier 1970
$temps_actuel = date("U");

// on pr�pare une requ�te SQL permettant de rechercher cette adresse IP dans notre table, 
// afin de voir si le client qui charge la page n'est pas d�j� comptabiliser 
// (en clair : si on trouve l'adresse IP, cela veut dire que le client ne charge pas pour la premi�re fois une page du site, 
// et que donc, nous n'aurons juste � modifier le champs time du tuple le concernant ; 
// si l'on ne trouve pas cette adresse IP dans la table, cela veut dire que soit le client n'a jamais charg� une page du site, 
// soit il l'a fait, mais il y a plus de 3 minutes, ce qui implique qu'il a �t� supprim� de la table : et dans ces deux cas, 
// il faudra l'ins�rer dans la table pour le comptabiliser comme �tant un nouveau connect�).
$sql = 'SELECT count(*) FROM nb_online WHERE ip= "'.$_SERVER['REMOTE_ADDR'].'"';

// on lance la requ�te SQL (mysqli_query) et on affiche un message d'erreur si la requ�te ne se passait pas bien (or die)
$res = $mysqli->query($sql) or die('Erreur SQL !<br />'.$sql.'<br />'.mysql_error());

// on comptabilise le nombre de r�sultats obtenus : soit 1, soit aucun (attention, aucun est diff�rent de 0)
$data = $res->fetch_array(MYSQLI_NUM);

if ($data[0]) {
    // si on a trouv� un r�sultat, on modifie le temps du tuple du client en cons�quence : en effet, le client vient juste de charger une page WEB, on modifie alors le temps de son tuple par la date actuelle (en fait le nombre de secondes separant le 1er janvier 1970 de la date actuelle).
    $sql = 'UPDATE nb_online SET time = "'.$temps_actuel.'" WHERE ip = "'.$_SERVER['REMOTE_ADDR'].'"';

    // on lance la requ�te SQL (mysqli_query) et on affiche un message d'erreur si la requ�te ne se passait pas bien (or die)
    $res = $mysqli->query($sql) or die ('Erreur SQL !<br />'.$sql.'<br />'.mysql_error());
}
else {
    // on entre dans ce cas si le client n'a jamais charg� de page (il est inconnu dans la table SQL car son IP y est absente). Dans ce cas, on ins�re alors dans la table SQL un nouveau tuple comprenant l'adresse IP de ce client ainsi que la date actuelle (le nombre de secondes entre le 1er janvier 1970 et la date actuelle).
    $sql = 'INSERT INTO nb_online VALUES("'.$_SERVER['REMOTE_ADDR']. '", "'.$temps_actuel.'")';

    // on lance la requ�te SQL (mysqli_query) et on affiche un message d'erreur si la requ�te ne se passait pas bien (or die)
    $res = $mysqli->query($sql) or die ('Erreur SQL !<br />'.$sql.'<br />'.mysql_error());
}

// on calcule le temps imparti pour comptabiliser les connect�s au site (en fait, cela correspond � notre soustraction de tout � l'heure : on calcule la date limite pour que l'on consid�re que les clients soient encore connect�s). 
$heure_max = $temps_actuel - $tps_max_connex;

// on pr�pare une requ�te SQL permettant de supprimer les clients que l'on consid�re comme n'�tant plus connect�s (c'est � dire ayant expir� leur temps de 3 minutes d�fini comme �tant le temps moyen de lecture d'une page WEB).
$sql2 = 'DELETE FROM nb_online where time < "'.$heure_max.'"';

// on lance la requ�te SQL (mysqli_query) et on affiche un message d'erreur si la requ�te ne se passait pas bien (or die)
$res2 = $mysqli->query($sql2) or die ('Erreur SQL !<br />'.$sql2.'<br />'.mysql_error());
?>