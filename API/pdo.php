<?php
	/**
	 * Independance of the Unit Tests
	 * 
	 * Having independant unit tests is important to ensure that they will allways produce the same result. An important composant of that is the independance of the DataBase. That point can be realized using a different temporary data set for each unit test. Those data sets are initialized from the initial data set, modified by the unit test and saved accross sessions to ensure we keep the same data. 
	 * 
	 * @Important It is important to make sure that the test data set stays as small as possible, because it will be duplicated a couple of time and it can be resources and space consuming. 
	 * 
	 * Workflow: 
	 * 	1. Load the previous version of the DB for the current testt. The version will be found as an SQL file, containing the commands to create the DB. So we will erase the DB and recreate it using the commands contained in the SQL file. 
	 * 2. Do all other initializations required for the test DB
	 * 3. Execute the program we want to test like if it was in production mode
	 * 4. At the end, right before exiting, export the DB using the function bellow. This will be done in the GeneralReturnable class (not sure about the name)
	 * 
	 */



	/**
	 * Parameters:
	 * - Optionals: (used only when test=true)
	 * 		- test: Bool -> Indicate if we are on the test system or the production system
	 * 		- initializeTestSystem: Bool -> Initialize the testSystem. 
	 * 		- sessionName: String -> The name of the test session. This is to rebuild the DB correctly for the tests
	 * 		- cleanUp: Bool -> Indicate if we need the clean up the test or not. This will only clean up!
	 * 		- sessionLifetime: Int = 10 -> Lifetime of the current session. So that the data do not interfere. The value is in seconds. 
	 */
	//echo __DIR__;
	require_once __DIR__ . '/../config.php';
	require_once CLASSES.'/TestSystemManager.php';
	require_once CLASSES.'/CPError.php';
	use \Coproman\API\CPError;

	$GENERAL_DEBUG = array();
	if(isset($_SERVER['HTTP_HOST']) && !isset($IS_LOCAL_SYSTEM)) {
		$IS_LOCAL_SYSTEM = ($_SERVER['HTTP_HOST'] == "localhost:8888");
	}
	if(!isset($IS_TEST_SYSTEM)) {
		$IS_TEST_SYSTEM = (isset($_POST['test']) && $_POST['test']);
	}

	if($IS_LOCAL_SYSTEM) {
		if($IS_TEST_SYSTEM) {
			$pdo = new PDO('mysql:host=localhost;port=8888;dbname='.\Config\conf_localTestDB.';charset=utf8;', \Config\conf_localhostUser, \Config\conf_localhostPassword);
			//$IS_TEST_SYSTEM = true;
			$dbName = Config\conf_localTestDB;
			$dbUser = Config\conf_localhostUser;
			$dbPassword = Config\conf_localhostPassword;
		} else {
			$pdo = new PDO('mysql:host=localhost;port=8888;dbname='.\Config\conf_localDB.';charset=utf8;', 'root', 'root');
			//$IS_TEST_SYSTEM = false;
		}
	} else {
		if($IS_TEST_SYSTEM) {
			$pdo = new PDO('mysql:host=localhost;dbname='.\Config\conf_serverTestDB.';charset=utf8;', \Config\conf_serverTestUser, \Config\conf_serverTestPassword);
			//$IS_TEST_SYSTEM = true;
		} else {
			$pdo = new PDO('mysql:host=localhost;dbname='.\Config\conf_serverDB.';charset=utf8;', \Config\conf_serverUser, \Config\conf_serverPassword);
			//$IS_TEST_SYSTEM = false;
		}
	}
	$pdo->setAttribute(PDO::ATTR_CASE,PDO::CASE_UPPER);//Ensure that all returned table names have upper case
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
	$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);//So that PDO will throw an exception in case of an SQL error


	//test DataBase initialization
	if($IS_TEST_SYSTEM) {
		$testSystemManager = new Coproman\System\PDO\TestSystemManager();

		//Clean up
		if(isset($_POST['cleanUp']) && $_POST['cleanUp'] == true) {
			if(\Config\CONF_CLEAN_UP_AT_TEST_SESSION_CLOSURE) {
				$dirPath = TEST_DATA_INIT."/tmp";
				//Empty the directory
				foreach( new DirectoryIterator($dirPath) as $fileInfo) {
					if($fileInfo->isDot()) continue;
					unlink($dirPath."/".$fileInfo->getFileName());
				}
				//Delete the directory
				rmdir($dirPath);

				//unset($TEST_INIT_FILE);//The file doesn't exist anymore
			}

			$testSystemManager->status = 0;
			$testSystemManager->message = "Clean up complete"; 
			$testSystemManager->exit();
		}
		//Load the data from the previous session or create a subDB for the current session
		if(isset($_POST['sessionName']) && $_POST['sessionName'] != '') {
			$sessionName = $_POST['sessionName'];

			session_id(reduceSessionNameForId($sessionName));
			session_start();

			$GENERAL_DEBUG['session'] = $_SESSION;

			$sessionLifeTime = (isset($_POST['sessionLifetime']))? $_POST['sessionLifetime'] : 2;
			$lastSessionTime = isset($_SESSION['sessionTime']) ? $_SESSION['sessionTime'] : 0;

			//Check if the data are still valid
			$forceReinit = (time() - $lastSessionTime > $sessionLifeTime);
			$_SESSION['sessionTime'] = time();
			$GENERAL_DEBUG['forceReinit'] = $forceReinit;
			$GENERAL_DEBUG['sessionLifetime'] = $sessionLifeTime;
			$GENERAL_DEBUG['currentTime'] = time();
			if($forceReinit /*&& $lastSessionTime != 0*/) {
				$GENERAL_DEBUG['message'] = "The data was reloaded. If you see an error, this may be the cause. To solve this, set the POST parameter `sessionLifetime` to extend the availability of the data";
			}

			session_write_close();// They say to use that as soon as possible when we have a lot of ajax calls https://www.php.net/manual/en/session.examples.basic.php
			//Get the file containing instructions
			$TEST_INIT_FILE = getFilePathFor($sessionName,$forceReinit);

			if(is_null($TEST_INIT_FILE)) {
				$testSystemManager->error = new CPError(400, "Impossible to initialize DB for session ".$sessionName);
				$testSystemManager->exit();
			}

			//We start by dropping all the tables
			$all_tables = $pdo->query("SHOW TABLES");

			if($all_tables->rowCount() > 0) {
				$sql = "";//"TRUNCATE ";
				while($lign = $all_tables->fetch()) {
					$sql .= "TRUNCATE ".$lign["TABLES_IN_TESTBASEAPIS"];
					$sql .= "; ";
				}
				//$sql = substr($sql, 0,-2);
				//echo 'Clean up for '.$sql.'<br/>';
				$cleanRequest = $pdo->query($sql);
				$cleanRequest->closeCursor();
			}
			//Execute all code form the initialization file
			$sqlInit  = file_get_contents($TEST_INIT_FILE);
			if($sqlInit != "") {
				$initRequest = $pdo->query($sqlInit);
				$initRequest->closeCursor();
				//echo 'Add data '.$sessionName.'<br/>';
			} else {
				$testSystemManager->status = 0;
				$testSystemManager->message = "Initialization file `".$TEST_INIT_FILE."`is empty. Impossible to initialize the database";
				$testSystemManager->error = new CPError(400,$testSystemManager->message);
				$testSystemManager->exit();
			}

		}
		if(isset($_POST['initializeTestSystem']) && $_POST['initializeTestSystem'] == true) {
			$testSystemManager->status = 1;
			$testSystemManager->message = "Initialization complete"; 
			$testSystemManager->exit();
		}
	}

	/*if($IS_TEST_SYSTEM) {
		// !!!! I need to get the value of this variable first and then set it back to its original value at the end!!
		//Turn off logging of requests before we restore the dataBase
		$pdo->query("SET GLOBAL general_log = 'OFF'");


		if(isset($_POST['initializeTestSystem']) && $_POST['initializeTestSystem'] == true) {//Initialize the test system
			$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
			if($conf_shouldInitializeTestDB) {
				//We start by dropping all the tables
				$all_tables = $pdo->query("SHOW TABLES");

				if($all_tables->rowCount() > 0) {
					$sql = "DROP TABLE ";
					while($lign = $all_tables->fetch()) {
						$sql .= $lign["TABLES_IN_TESTBASEAPIS"];
						$sql .= ", ";
					}
					$sql = substr($sql, 0,-2);

					$cleanRequest = $pdo->query($sql);
				}
				//Execute all code form the initialization file
				$sqlInit  = file_get_contents($conf_testDBPath);

				$initRequest = $pdo->query($sqlInit);
			}


			$result = array(
				'error' => array(),
				'message' => "Initialization succeed"
			);
			echo json_encode($result);
			exit();
		}


		//Manage the session
		if(isset($_POST['sessionName']) && $_POST['sessionName'] != '') {
			$sessionName = $_POST['sessionName'];
			session_name($sessionName);
			session_start();
			//Manage the lifetime of the session
			if(!isset($_SESSION['lastAccess'])) {
				$_SESSION['lastAccess'] = time();
				$diff = 0;
			} else {
				$diff = time() - $_SESSION['lastAccess'];
				if($diff > 10) {
					session_unset();
				}
				$_SESSION['lastAccess'] = time();
			}
			//Ensure we have an array of threads IDs
			if(!isset($_SESSION['thread_ids'])) {
				$_SESSION['thread_ids'] = array();
			}


			//Get all the executed requests in the past threads
			$sqlRequests = array();
			foreach ($_SESSION['thread_ids'] as $key => $value) {
				$requests = $pdo->prepare("SELECT * FROM mysql.general_log WHERE command_type = 'Query' AND thread_id = :thread_id");
				$requests->execute(array('thread_id' => $value));
				while($lign = $requests->fetch()) {
					$argument = $lign['ARGUMENT'];
					if(shouldKeepRequest($argument)) {
						array_push($sqlRequests,$argument);
					}
				}
			}


			//Append the current SQL thread_id to the array of requests
			$thread_id_request = $pdo->query("SELECT CONNECTION_ID() as ID");
			$thread_id = $thread_id_request->fetch()['ID'];
			array_push($_SESSION['thread_ids'], $thread_id);

			//Re-execute the previous queries (!! I should execute them before I use inTransaction() !! I have to think about when to execute that code...). In fact, I should only execute the queries that were executed when the DB was in transaction!!!

			//Set back the logs
			$pdo->query("SET GLOBAL general_log = 'ON'");



			$pdo->query("UPDATE DEVICES SET DEVICE_USER = DEVICE_USER + 1 WHERE DEVICE_ID = 3;");
			$result = $pdo->query("SELECT DEVICE_USER FROM DEVICES WHERE DEVICE_ID = 3;");
			$lign = $result->fetch();
			$val = $lign['DEVICE_USER'];

			echo json_encode(array('thread_ids' => $_SESSION['thread_ids'], 'queries' => $sqlRequests, 'diff' => $diff, 'val' => $val));
			exit();
		}
	}*/

	/**
	 * Return the file used to initialize the test DB. If this file does not exists, it is created from the upper level. 
	 * @param sessionName {String} The name of the session for which we want the file containing the commands to initialize the DB
	 */
	function getFilePathFor($sessionName,$forceRecreate) {
		// check if the file is alread created
		/*if(file_exists(TEST_DATA_INIT."/".$sessionName.".sql")) {
			return TEST_DATA_INIT."/".$sessionName.".sql";
		}*/
		//Create the temporary folder if needed
		if(!file_exists(TEST_DATA_INIT."/tmp")) {
			mkdir(TEST_DATA_INIT."/tmp");
		}
		if(file_exists(TEST_DATA_INIT."/tmp/".$sessionName.".sql") && !$forceRecreate) {
			return TEST_DATA_INIT."/tmp/".$sessionName.".sql";
		}
	
		$arr = explode('_',$sessionName);
		while(count($arr) > 0) {
			$savedElement = array_pop($arr);
			$path = "";
			if(count($arr) == 0) {
				$path = TEST_DATA_INIT."/";
				array_push($arr, $savedElement);
			} else {
				$path = TEST_DATA_INIT."/tmp/";
			}
			$path .= implode('_',$arr).".sql";
			//echo 'Path `'.$path.'`<br/>';
			if(file_exists($path)) {
				//We create the new file
				$newPath = TEST_DATA_INIT."/tmp/".$sessionName.".sql";
				copy($path, $newPath);
				//echo 'Copy `'.$path.'`to '.$newPath.'`<br/>';
				return $newPath;
			}
		}
		return null;
	}

	function saveDBState($path,&$output) {
		global $dbName, $dbUser, $dbPassword;
		$command = \Config\CONF_DB_EXPORT_COMMAND." -u ".$dbUser." -p".$dbPassword." --no-create-info ".$dbName." 2>&1";
		//Remove warnigs
		exec($command,$output,$resultCode);
		//echo trim($output[0])." -> ".count($output)." <br/> ".(str_contains("Warning", $output[0])) ? "true" : "false"."<br/>";
		//echo trim($output[0])." -> ".count($output)."<br/>".((str_contains($output[0], "Warning")) ? "true" : "false")."<br/>";
		while(count($output) > 0 && (str_contains($output[0], "Warning"))) {
			array_shift($output);
			//echo "Shift<br/>";
		}
		//var_dump($output);
		if($resultCode != 0) {
			return $output;
		} else {
			return file_put_contents($path, implode(PHP_EOL,$output));
		}
	}

	function reduceSessionNameForId($sessionName) {
		$str = str_replace(['.','_','$'],'',$sessionName);
		while(strlen($str) > 26) {
			$str = substr($str,1);
		}
		return $str;
	}
	/**
	 * Filter the requests to indicate if we need to keep them
	 * 
	 * We keep only UPDATE, INSERT and DELETE
	 * 
	 */
	/*function shouldKeepRequest($request) {
		//Get the first keyword
		$arr = explode(' ',trim($request));
		$keyword = $arr[0];

		if($keyword == "UPDATE" || $keyword == "INSERT" || $keyword == "DELETE") {
			return true;
		}
		return false;
	}*/
?>
