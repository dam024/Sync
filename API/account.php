<?php
/**
 *
 *
 *  @file   canAccess.php
 *  @brief  Manage who the accounts
 *  @author Jaccoud Damien
 *  @date 10.09.22
 *
 *
 ***********************************************/

 /**
Error codes : 
    - 0 : no error
    - 405 : When informations are missing
    - 406 : Wrong information
    - 407 : Data not allowed
    - 408 : Invalid data
    - 410 : SQL Error
 */ 

/*
For all : 
    - Action (new if new account, connect if connection)

New account : 
    - firstName
    - lastName
    - email
    - password
    - UUID
    - Name

Connection : 
    - username (email)
    - password
    - UUID
    - Name
*/
require_once '../config.php';
require_once CLASSES.'/Account/Account.php';
use Coproman\API\Account\Account;
use Coproman\API\CPError;
use Coproman\API\Account\User;
use Coproman\API\Account\Device;

include_once PDO;
include_once FUNCTIONS;

header('Content-type:text/json');

$account = new Account();

if(isset($_POST['Action'])) {
    switch($_POST['Action']) {
        case "new":
            //Verification of infos : 

            //Verification of the infos for the account
            $userFirstName = getValue('firstName','First name is required');
            $userLastName = getValue('lastName','Last name is required');
            $userEmail = getValue('email','Email is required');
            $userPassword = getValue('password','Password is required');
            if(!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                $account->error = new CPError(408, 'Email adress incorrect');
                $account->exit();
            }

            $account->exitIfError();
            //Email should be unique
            $emailUnique = $pdo->prepare("SELECT * FROM Users WHERE USER_EMAIL = :email");
            $emailUnique->execute(array('email' => $userEmail));
            if($l = $emailUnique->fetch()) {
                $account->error = new CPError(407, 'Email adress already exists');
                $account->exit();
            }

            //Get new primary key
            $getPK = $pdo->query("SELECT Max(USER_ID)+1 as user_id FROM Users");
            if($l = $getPK->fetch()) {
                $key = $l["USER_ID"];
            }
            if(!isset($key) || is_null($key)) {
                $key = 1;
            }
            //Insert
            $insert = $pdo->prepare("INSERT INTO Users (USER_ID, USER_FIRSTNAME, USER_LASTNAME, USER_EMAIL, USER_PASSWORD) VALUES (:key,:firstName, :lastName,:email,:password)");
            $insert->execute(array(
                    'key' => $key,
                    'firstName' => $userFirstName, 
                    'lastName' => $userLastName, 
                    'email' => $userEmail, 
                    'password' => password_hash($userPassword, PASSWORD_DEFAULT)
                ));
            if($insert->errorCode() != "00000") {
                $account->error = new CPError(410, 'Impossible to create account', $insert->errorInfo());
                $account->exit();
            } else {
                //Fill the return
                $user = new User();
                $user->userID = $key;
                $user->firstName = $userFirstName;
                $user->lastName = $userLastName;
                $user->email = $userEmail;

                $account->user = $user;
                //Connect to the account
                connectDevice($key);
            }
            break;
        case "connect":
            //Create new connection for a new device (It allows a user to connect his account to a new device)

            //Verify if it can connect (Get user infos). We use both the email and the password. 
            $userEmail = getValue('username','Wrong username or password',406);
            $userPassword = getValue('password','Wrong username or password',406);
            if(!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                $account->error = new CPError(408, 'Not an email address');
                $account->exit();
            }

            $account->exitIfError();

            $user = $pdo->prepare("SELECT * FROM Users WHERE USER_EMAIL = :email");
            $user->execute(array('email' => $userEmail));
            if($lign = $user->fetch()) {
                if(!password_verify($userPassword, $lign["USER_PASSWORD"])) {
                    $account->debug['wrongPassword'] = true;
                    $account->error = new CPError(406,'Wrong username or password');
                    $account->exit();
                }
                $user = new User();
                $user->userID = intval($lign["USER_ID"]);
                $user->firstName = $lign["USER_FIRSTNAME"];
                $user->lastName = $lign["USER_LASTNAME"];
                $user->email = $userEmail;

                $account->user = $user;

                //Create the connection
                connectDevice($user->userID);
            } else {
                $account->debug['sqlGetUser'] = $user->queryString;
                $account->error = new CPError(406,'Wrong username or password');
                $account->exit();
            }
            break;
        case "disconnect":
            //$account->error = new CPError(400, 'Unimplemented part');
            //check if the user is connected
            include('canAccess.php');
            //set disconnected
            $disconncet = $pdo->prepare("UPDATE DEVICES SET DEVICE_ISCONNECTED=0 WHERE DEVICE_ID = :device");
            $disconncet->execute(array('device' => $deviceID));
            break;
        case "testAccess":
            include('canAccess.php');
            break;
        default:
            $account->error = new CPError(406,'Access denied', array());
            break;
    }
} else {
    $account->error = new CPError(405,'Access denied');
}
$account->exit();

function getValue($key,$msg,$code = 405) {
    global $account, $pdo;
    if(isset($_POST[$key])) {
        return $_POST[$key];
    } else {
        $account->error = new CPError($code,$msg);
        return null;
    }
}
/**
 * @brief Create a new connection for a new device.
 */
function connectDevice($userId) {
    global $account, $pdo;

    //Verification of the infos of the device
    $deviceUUID = getValue('UUID','Incomplete request 1');
    $deviceName = getValue('Name','Incomplete request 2');
    $devicePrivateKey = bin2hex(random_bytes(32));

    $account->exitIfError();

    //Verify if the device already exists
    $exists = $pdo->prepare("SELECT count(*) as nb FROM DEVICES WHERE DEVICE_UUID = :uuid");
    $exists->execute(array('uuid' => $deviceUUID));
    if($l = $exists->fetch()) {
        if(intval($l["NB"]) > 0) {//It already exists, so we update it
            //$rm = $pdo->prepare("DELETE FROM DEVICES WHERE DEVICE_UUID = :uuid");
            //$rm->execute(array('uuid' => $deviceUUID));

            $update = $pdo->prepare("UPDATE DEVICES SET DEVICE_ISCONNECTED=1, DEVICE_PRIVATEKEY=:privateKey WHERE DEVICE_UUID = :id");
            $update->execute(array('id' => $deviceUUID, 'privateKey' => password_hash($devicePrivateKey, PASSWORD_DEFAULT)));

            //Then, we collect the necessary informations
            $select = $pdo->prepare("SELECT * FROM DEVICES WHERE DEVICE_UUID = :uuid");
            $select->execute(array('uuid' => $deviceUUID));

            if($lign = $select->fetch()) {
                $key = $lign['DEVICE_ID'];
            }
        } else {//If it didn't exist, we create it
            //Get new primary key
            $getPK = $pdo->query("SELECT Max(DEVICE_ID)+1 as device_id FROM DEVICES");
            if($l = $getPK->fetch()) {
                $key = $l["DEVICE_ID"];
            }
            if(!isset($key) || is_null($key)) {
                $key = 1;
            }

            $insert = $pdo->prepare("INSERT INTO DEVICES (DEVICE_ID,DEVICE_UUID, DEVICE_NAME, DEVICE_USER, DEVICE_PRIVATEKEY,DEVICE_ISCONNECTED) VALUES (:id,:uuid, :name, :user, :privateKey, 1)");
            $insert->execute(array(
                'id' => $key,
                'uuid' => $deviceUUID, 
                'name' => $deviceName, 
                'user' => $userId, 
                'privateKey' => password_hash($devicePrivateKey, PASSWORD_DEFAULT)
            ));

            if($insert->errorCode() != "00000") {
                $account->error = new CPError(410, 'Impossible to connect to account', $insert->errorInfo());
                $account->exit();
            }
        }

        $device = new Device();
        $device->deviceID = intval($key);
        $device->name = $deviceName;
        $device->userID = $userId;
        $device->privateKey = $devicePrivateKey;

        $account->device = $device;
    }

    
    
}

?>