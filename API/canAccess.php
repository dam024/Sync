<?php
/**
 *  
 *
 *  @file   canAccess.php
 *  @brief  Manage who can access to the API, i.e. check for a possible connexion
 *  @author Jaccoud Damien
 *  @date 10.09.22
 *
 *  
 ***********************************************/
namespace Coproman\canAccess;
require_once '../config.php';
//require_once API.'/CPError.php';
use Coproman\API\CPError;
use Coproman\API\SingleError;
include_once PDO;

/*
    Error codes : 
    - 405 : Access denied
    - 406 : Access denied (Cannot verify data access)
    - 407 : Impossible to get account
*/
header('Content-type:text/json');
header('Access-Control-Allow-Origin: *');

//On vérifie que l'on a une connexion

/*$data = json_decode(getValue('data',$_POST),$associative=true);
if(!is_array($data)) {
    $data = json_decode($data,$associative=true);
}*/
//var_dump($_POST);

$connexionInfos = json_decode(getValue('connexionInfos',$_POST),$associative=true);
if(!is_array($connexionInfos)) {
    $connexionInfos = json_decode($connexionInfos,$associative=true);
}

$userID = getValue('userID',$connexionInfos);
$deviceID = getValue('deviceID',$connexionInfos);
$privateKey = getValue('privateKey',$connexionInfos);

newSynchronisation($userID, $deviceID);

//On check la connexion
$user = $pdo->prepare("SELECT DEVICE_PRIVATEKEY FROM DEVICES left join Users on DEVICE_USER = USER_ID WHERE USER_ID = :userID and DEVICE_ID = :deviceID");
$user->execute(array('userID' => $userID, 'deviceID' => $deviceID));
if($user->errorCode() != "00000") {
//new CPError(407, 'Impossible to get account', $user->errorInfo());
    accessError(407, 'Impossible to get account');
}
if($user->rowCount() != 1) {
    accessError();
} else {
    $lign = $user->fetch();
    if(!password_verify($privateKey, $lign["DEVICE_PRIVATEKEY"])) {
        accessError();
    }
}


function accessError($nb = 405, $msg = 'Access denied',$debugInfos=[]) {
    global $pdo;
    $returnableObject = new SingleError();
    $returnableObject->error = new CPError($nb,$msg);
    /*echo json_encode(get_object_vars($ret));
    if($pdo->inTransaction()) {
        $pdo->rollback();
    }
    exit();*/
    $returnableObject->debug = $debugInfos;
    $returnableObject->exit();
}
function getValue($key,$array) {
    if(isset($array[$key])) {
        return $array[$key];
    } else {

        accessError(405,'Access denied',array('key' => $key,'array' => $array));
    }
}

?>
