<?php
session_start(); // On appelle la session
$_SESSION = array(); // On �crase le tableau de session
session_destroy(); // On d�truit la session

header ("Location:index.php");
?>
