<?php
/**
 *  
 *
 *  @file   checkTables.php
 *  @brief  Computes differences between this table and the main table
 *  @author Jaccoud Damien
 *  @date 20.11.23
 *
 *  
 ***********************************************/
/*
	run on local system: 
	`<MAMP DIRECTORY>/bin/php/php<8.2.0>/bin/php`
	Remplace the two <...> parameters in the query
*/
//Set certain variables to make the application work
if(isset($_SERVER['HTTP_HOST'])) {
	echo "Access denied";
	exit();
}
$IS_TEST_SYSTEM = true;
$IS_LOCAL_SYSTEM = true;
require_once 'config.php';
require_once PDO;

$nb_actions = 0;
$nb_error = 0;
$file = fopen('databaseUpdates.sql','w');
fwrite($file,"
--
--Update the database structure
--
	");
//-----------------------   CHANGES IN TABLES  -----------------------------
$tablesChanges = getTableChanges();

//Go over every table
foreach ($tablesChanges as $key => $value) {
	$table = $value['TABLE_NAME'];
	if($value['TABLE_SCHEMA'] == \Config\conf_localTestDB) {
		echo "New table: ".$table."\n";
		$cmd = \Config\CONF_DB_EXPORT_COMMAND." -u ".$dbUser." -p".$dbPassword." --no-data --compact ".\Config\conf_localTestDB." {$table} 2> /dev/null";
		exec($cmd,$output,$resultCode);
		fwrite($file,"\n\n--\n-- Create new table {$table}\n--\n");
		fwrite($file,implode("\n",$output));
	} else if($value['TABLE_SCHEMA'] == \Config\conf_localDB) {
		echo "Delete table: ".$table." -> attention required!\n";
		$cmd = "


--[Action required] 
--
-- Treat Data of {$table}:
--

--
-- Delete table {$table}
--
DROP TABLE {$table};
		";
		fwrite($file, $cmd);
		$nb_actions++;
	} else {
		echo "Unhandled case:\n";
		var_dump($value);
		echo "\n";
		$nb_error++;
	}
}

//----------------------------  CHANGES IN COLUMNS  ----------------------------
$columnsChanges = getColumnChanges();

foreach ($columnsChanges as $key => $col) {
	if(!is_null($col['TABLE_NAME_TEST']) && is_null($col['TABLE_NAME_PROD'])) {//Case were we added a column

	} else if(!is_null($col['TABLE_NAME_TEST']) && is_null($col['TABLE_NAME_PROD'])) {//Case were we deleted a column
		$nb_actions++;
	} else if($col['TABLE_NAME_TEST'] == $col['TABLE_NAME_PROD']) {//Case were we modified a column
		echo "Modified column: ".$col['TABLE_NAME_TEST'].".".$col['COLUMN_NAME_TEST']."\n";
		$cmd = "


--
-- Modify data type of {$col['TABLE_NAME_TEST']}.{$col['COLUMN_NAME_TEST']}
--
ALTER TABLE {$col['TABLE_NAME_TEST']}
MODIFY COLUMN {$col['COLUMN_NAME_TEST']} {$col['COLUMN_TYPE_TEST']};
		";
		fwrite($file, $cmd);
	} else {
		echo "Unhandled case:\n";
		var_dump($col);
		echo "\n";
		$nb_error++;
	}
}


fclose($file);
if($nb_actions > 0) {
	echo "\e[1;31;40mYour attenion is required for ".$nb_actions." action(s)!\e[0m\n";
}
if($nb_error > 0) {
	echo "\e[1;31;40mWe encountered ".$nb_error." error(s) during the comparison. Please, interprete carefully!\e[0m\n";
}

function getTableChanges() {
	global $pdo;
	$sql = "
		SELECT B.* FROM (
	        SELECT table_name, COUNT(1) match_count FROM information_schema.TABLES WHERE table_schema in (:productDB,:testDB)
	        GROUP BY table_name
	        HAVING COUNT(1) = 1
		) A
		INNER JOIN (
		    SELECT * FROM information_schema.TABLES WHERE table_schema in (:productDB,:testDB)
		) B USING (table_name)
	";
	$tables = $pdo->prepare($sql);
	$tables->execute(array('testDB' => \Config\conf_localTestDB,'productDB' => \Config\conf_localDB));
	return $tables->fetchAll();
}

function getColumnChanges() {
	global $pdo;
	$sql = "
		    SELECT B.*,C.* FROM (
		    SELECT DISTINCT table_name,COLUMN_NAME FROM (
		        SELECT table_name,column_name,ordinal_position,data_type,column_type,COUNT(1) match_count
		        FROM information_schema.columns WHERE table_schema IN (:productDB,:testDB)
                    AND table_name NOT IN (
                        SELECT DISTINCT table_name FROM (
                            SELECT table_name, COUNT(1) match_count FROM information_schema.TABLES WHERE table_schema in (:productDB,:testDB)
                            GROUP BY table_name
                            HAVING COUNT(1) = 1    
                        ) TT
                    )
		        GROUP BY table_name,column_name,ordinal_position,data_type,column_type
		        HAVING COUNT(1) = 1
		    ) AA
		) A 
		LEFT JOIN (
		    SELECT table_schema as table_schema_test,table_name as table_name_test,column_name as column_name_test,ordinal_position as ordinal_position_test,data_type as data_type_test,column_type as column_type_test
		    FROM information_schema.columns WHERE table_schema IN (:testDB)
		) B ON B.table_name_test = A.table_name and B.column_name_test = A.column_name
		LEFT JOIN (
		    SELECT table_schema as table_schema_prod,table_name as table_name_prod,column_name as column_name_prod,ordinal_position as ordinal_position_prod,data_type as data_type_prod,column_type as column_type_prod
		    FROM information_schema.columns WHERE table_schema IN (:productDB)
		) C ON C.table_name_prod = A.table_name and C.column_name_prod = A.column_name
        /*ORDER BY B.table_name,B.column_name,B.table_schema*/
        ;
	";
	$columns = $pdo->prepare($sql);
	$columns->execute(array('testDB' => \Config\conf_localTestDB,'productDB' => \Config\conf_localDB));
	return $columns->fetchAll();
}

?>