<?php

	include_once '../config.php';

	if($_SERVER['HTTP_HOST'] == "localhost:8888") {
		if(isset($_POST['test']) && $_POST['test']) {
			$pdo = new PDO('mysql:host=localhost;port=8888;dbname='.$conf_localTestDB.';charset=utf8;', $conf_localhostUser, $conf_localhostPassword);
		} else {
			$pdo = new PDO('mysql:host=localhost;port=8888;dbname='.$conf_localDB.';charset=utf8;', 'root', 'root');
		}
	} else {
		if(isset($_POST['test']) && $_POST['test']) {
			$pdo = new PDO('mysql:host=localhost;dbname='.$conf_serverTestDB.';charset=utf8;', $conf_serverTestUser, $conf_serverTestPassword);
		} else {
			$pdo = new PDO('mysql:host=localhost;dbname='.$conf_serverDB.';charset=utf8;', $conf_serverUser, $conf_serverPassword);
		}
	}
	$pdo->setAttribute(PDO::ATTR_CASE,PDO::CASE_UPPER);//Ensure that all returned table names have upper case
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
?>
