<?php
/*
* CopyData supporting functions
*
* @original author Alexander Shereshevsky - Amdocs (shereshevsky@gmail.com)
* @created Mar 16,2013
* @last_modification Aug 16,2013
*/

/* copydata module functions */
function copydata_run_project ($tablesArr) {
	global $project;
	global $time_stamp;

	$str = "### COPYING DATA "; 
	if ($project) $str .= "FOR PROJECT \"".strtoupper($project)."\" ";
	$str = str_pad($str, 80, "#");
	copydata_logger($str, 'LOG', 5);

	if ($project && !strpos($project, 'RESTORE_')) {
		$restore_project = 'RESTORE_'.$time_stamp;
		$str = "For restore use project $restore_project. It will be deleted after 5 days.";
		copydata_logger($str, 'LOG', 6);
	}

	copydata_disable_all($tablesArr);

	foreach ($tablesArr as $table)
		copydata_single_table($table['PROJECT'], $table['SRC_OWNER'], $table['SRC_DB'], $table['SRC_TABLE_NAME'], $table['TRG_OWNER'], $table['TRG_DB'], $table['TRG_TABLE_NAME'], $table['SEQUENCE'], $table['TRUNCATE_TABLE'], $table['MISSING'], $table['CRE_BACKUP'], $table['LIKE_ST'], $table['WHERE_ST'], $table['SNAPSHOT']);

	copydata_enable_all($tablesArr);
	
	$str = "### DATA COPY "; 
	if ($project) $str .= "PROJECT \"".strtoupper($project)."\" ";
	$str .= "WAS FINISHED IN ".elapsed_time($time_stamp,'s')." s"; 
	$str = str_pad($str, 80, "#")."\n";
	copydata_logger($str, 'LOG', 5);

	$email = $_SESSION["oweb_user"]->email;
	$logFile = "/adba/rhd/adba/web_alex/htdocs/tmp/".$project.$time_stamp.".log";
	system("/usr/bin/mailx -r alexansh@amdocs.com -s \"Log\" $email, alexansh@amdocs.com < $logFile");
}

function copydata_get_tables ($con, $user, $all_tables, $like_st, $sequence) {
	if ($all_tables)
		$sql = "SELECT DISTINCT TABLE_NAME FROM ALL_TABLES WHERE OWNER = '$user'";
	elseif (!$sequence)
		$sql = "SELECT DISTINCT TABLE_NAME FROM ALL_TABLES WHERE OWNER = '$user' AND TABLE_NAME LIKE '$like_st'";
	else 
		$sql = "SELECT DISTINCT SEQUENCE_NAME TABLE_NAME FROM ALL_SEQUENCES WHERE SEQUENCE_OWNER = '$user' AND SEQUENCE_NAME LIKE '$like_st'";
	if (!$con->query($sql)) {
		return false;
	}

	$result_arr = array();
	while ($con->next_record()) {
		$result_arr[] = $con->Record['TABLE_NAME'];
	}
	return $result_arr;
}

function copydata_single_table ($project, $src_owner, $src_db, $src_table_name, $trg_owner, $trg_db, $trg_table_name, $sequence, $truncate_table, $missing, $cre_backup, $like_st, $where_st, $snapshot) {
	global $trg_conn;
	global $src_conn;
	global $time_stamp;

	$dblink = substr($src_db.$time_stamp ,0,26)."_TRG";
	$hash = strtoupper(substr(hash("md5",$trg_table_name), 0, 16));
	$bck_name = "BCK_".$time_stamp.$hash;

	// Get target DB connection details and connect to target database
	$dbc_trg = core_dbc_get($trg_db);

	$trg_conn = new Oracle();
	if (!$trg_conn->connect($dbc_trg['DB_NAME'],$dbc_trg['DB_USER'],$dbc_trg['DB_PASSWORD'])) {
		$str = "ERROR: Unable to connect to database (".$dbc_trg['DB_NAME']."). Check the DB connection setup under \"System Administration -> DB Connections\"";
		copydata_logger($str, 'ERROR');
		exit;
	}

	// Get source DB details and connect to source database
	$dbc_src = core_dbc_get($src_db);

	$src_conn = new Oracle();
	if (!$src_conn->connect($dbc_src['DB_NAME'],$dbc_src['DB_USER'],$dbc_src['DB_PASSWORD'])) {
		$str = "ERROR: Unable to connect to database (".$dbc_src['DB_NAME'].")<br>Check the DB connection setup under \"System Administration -> DB Connections\"";
		copydata_logger($str, 'ERROR');
		exit;
	}

	// Check if sorce table exists
	if (copydata_check_table('src', $src_owner, $src_table_name, $sequence)) {
		$str = "- Source "; 
		if ($sequence) $str .= "sequence"; else $str .= "table"; 
		$str .= " $src_owner.$src_table_name.";
		copydata_logger($str);

		// If project, save to projects table.
		if ($project) 
			copydata_save_project($project, $src_owner, $src_db, $trg_owner, $trg_db, $trg_table_name, $sequence, $where_st, $truncate_table, $missing, $cre_backup, $snapshot, $hash);

		// Create database link from target to source
		copydata_create_db_link($dblink, $dbc_src['DB_USER'], $dbc_src['DB_PASSWORD'], $dbc_src['DB_NAME']);

		if (copydata_check_table('trg', $trg_owner, $trg_table_name, $sequence)) {
			if($sequence) {
				if ($cre_backup) {
					copydata_create_backup($trg_owner, $trg_table_name, $trg_db, $bck_name, false);
				}
				copydata_copy_sequence($src_owner, $src_table_name, $trg_owner, $trg_table_name);
			}
			else {
				$source_columns = copydata_get_erd('src', $src_owner, $src_table_name);
				$target_columns = copydata_get_erd('trg', $trg_owner, $trg_table_name);
				$diff = array_diff($source_columns, $target_columns);	
				if (empty($diff)) {
					$locks = copydata_get_locks($trg_owner, $trg_table_name);
					if (empty($locks)) {
						if ($cre_backup) {
							copydata_create_backup($trg_owner, $trg_table_name, $trg_db, $bck_name, true);
						}

						if ($truncate_table)
							copydata_truncate($trg_owner, $trg_table_name);

						if ($where_st) {
							copydata_delete($trg_owner, $trg_table_name, $where_st);
						}

						copydata_copy($trg_owner, $trg_table_name, $src_owner, $src_table_name, $dblink, $where_st);
						if ($snapshot) 
							copydata_refresh_snapshot($trg_owner, $trg_table_name);
					} else
					$err = "- ERROR: The target table is locked by ".$locks[0]['OS_USER_NAME']." connected from schema ".$locks[0]['ORACLE_USERNAME']." and can't be truncated.";
				} else
					$err = "- ERROR: Target ERD is different and will be skipped, please align it and copy again.";
			}
		} else
			$err = "- ERROR: Target table/sequence is not exists and will be skipped.";
	} else
		$err = "- ERROR: Source table or view does not exist (".$src_owner.".".$src_table_name."@".$src_db.")";
	if ($err)
		copydata_logger($err, 'ERROR');
	echo "<br>";
	flush();

	copydata_drop_db_link($dblink);
	$src_conn->disconnect;
	$trg_conn->disconnect;
}

function copydata_copy_sequence($src_owner, $src_sequence, $trg_owner, $trg_sequence) {
	global $trg_conn;
	global $src_conn;

	$getSourceValueSql = "select LAST_NUMBER+CACHE_SIZE*INCREMENT_BY VAL from all_sequences where sequence_owner = '$src_owner' and sequence_name = '$src_sequence'";

	if (!$src_conn->query($getSourceValueSql))
		copydata_logger("- ERROR: Unable to get sequence details for $src_owner.$src_sequence. ".$src_conn->Error, 'ERROR');
	while ($src_conn->next_record())
		$source_value = $src_conn->Record['VAL'];
	copydata_logger("- Value on source: $source_value.");

	$getTargetValueSql = "select $trg_owner.$trg_sequence.nextval VAL from dual";

	if (!$trg_conn->query($getTargetValueSql))
		copydata_logger("- ERROR: Unable to get sequence details for $trg_owner.$trg_sequence. ".$src_conn->Error, 'ERROR');
	while ($trg_conn->next_record())
		$target_value = $trg_conn->Record['VAL'];
	copydata_logger("- Value on target: $target_value.");

	if ($source_value != $target_value) {
		$getTargetIncrementSql = "SELECT INCREMENT_BY INC FROM all_sequences WHERE sequence_owner = '$trg_owner' AND sequence_name = '$trg_sequence'";

		if (!$trg_conn->query($getTargetIncrementSql))
			copydata_logger("- ERROR: Unable to get sequence increment details for $trg_owner.$trg_sequence. ".$src_conn->Error, 'ERROR');
		while ($trg_conn->next_record())
			$target_increment = $trg_conn->Record['INC'];
		copydata_logger("- Increment by: $target_increment.");

		$new_val = $source_value - $target_value;
		$alter_val_sql = "alter sequence $trg_owner.$trg_sequence increment by $new_val";
		$fetch_next_sql = "select $trg_owner.$trg_sequence.nextval VAL from dual";
		$increment_sql = "alter sequence $trg_owner.$trg_sequence increment by $target_increment";

		$trg_conn->query($alter_val_sql);
		$trg_conn->query($fetch_next_sql);
		$trg_conn->query($increment_sql);
	}
}


function copydata_create_db_link ($dbLink, $user, $password, $instance) {
	global $trg_conn;

	$sqlCreateDblink = "DECLARE ";
	$sqlCreateDblink .= "DblinkExists EXCEPTION; ";
	$sqlCreateDblink .= "PRAGMA exception_init(DblinkExists,-2011); ";
	$sqlCreateDblink .= "sql_stmt varchar2(200) := 'create database link $dbLink connect to $user identified by $password using ''$instance'''; ";
	$sqlCreateDblink .= "BEGIN ";
	$sqlCreateDblink .= "execute immediate sql_stmt; ";
	$sqlCreateDblink .= "EXCEPTION WHEN DblinkExists THEN NULL; ";
	$sqlCreateDblink .= "END; ";

	$trg_conn -> query($sqlCreateDblink);
	
	if ($trg_conn->Error)
		return false;
}

function copydata_drop_db_link($dblink) {
	global $trg_conn;

	$sqlDropDblink = "DECLARE ";
	$sqlDropDblink .= "sql_stmt varchar2(200) := 'drop database link $dblink'; ";
	$sqlDropDblink .= "BEGIN ";
	$sqlDropDblink .= "execute immediate sql_stmt; ";
	$sqlDropDblink .= "EXCEPTION WHEN OTHERS THEN NULL; ";
	$sqlDropDblink .= "END; ";

	$trg_conn->query($sqlDropDblink);
}

function copydata_check_table($db, $user, $object, $sequence) {
	if (!$sequence)
		$sql = "select 1 from all_tables where owner = '$user' and table_name = '$object'";
	else
		$sql = "select 1 from all_sequences where sequence_owner = '$user' and sequence_name = '$object'";

	switch ($db) {
		case "src":
			global $src_conn;
			$conn = $src_conn;
			break;
		case "trg":
			global $trg_conn;
			$conn = $trg_conn;
			break;
	}

	$result_arr = $conn->build_results($sql);
	if ($conn->Error || empty($result_arr))
		return false;
	else 
		return true;
}

function copydata_copy($target_user, $trg_table_name, $source_user, $src_table_name, $dblink, $where_st) {
	global $trg_conn;

	$sql = "insert /*+ append */ into $target_user.$trg_table_name select * from $source_user.$src_table_name@$dblink";
	copydata_logger("- Inserting data into $target_user.$trg_table_name.");
	flush();
	if ($where_st)
		$sql = $sql." where ".$where_st;
	
	$affected = $trg_conn->query_num_rows($sql);
	copydata_logger("- $affected Rows Affected.");
}

function copydata_truncate($target_user, $table_name) {
	global $trg_conn;

	$sql = "truncate table $target_user.$table_name";

	if (!$trg_conn->query($sql)) {
		copydata_logger("- Warning: Unable to truncate target table. ".$trg_conn->Error, 'WARNING');
	} else {
		copydata_logger("- $target_user.$table_name was successfully truncated.");
	}
}

function copydata_save_project($project, $src_owner, $src_db, $trg_owner, $trg_db, $trg_table_name, $sequence, $where_st, $truncate_table, $missing, $cre_backup, $snapshot, $hash) {
	global $owebdb;

	$where_st = str_replace("'", "''", $where_st);

	$project_owner = $_SESSION["oweb_user"]->username;
	$sql = "merge into COPYDATA_PROJECTS m using dual 
			on (PROJECT_OWNER = '$project_owner' and PROJECT = '$project' and SRC_OWNER = '$src_owner' and SRC_DB = '$src_db' 
				and TRG_OWNER = '$trg_owner' and TRG_DB = '$trg_db' and TRG_TABLE_NAME = '$trg_table_name')
			when not matched then insert 
			(PROJECT_OWNER, 	PROJECT, 	SRC_OWNER, 		SRC_DB, 	SRC_TABLE_NAME, 	TRG_OWNER, 		TRG_DB, 	TRG_TABLE_NAME, 	SEQUENCE, 	WHERE_ST, 		TRUNCATE_TABLE, 	MISSING, 	CRE_BACKUP, 	SNAPSHOT, 	HASH) 
	 values ('$project_owner', '$project', '$src_owner', 	'$src_db', '$trg_table_name', 	'$trg_owner', 	'$trg_db', 	'$trg_table_name', '$sequence', '$where_st', 	'$truncate_table', '$missing', '$cre_backup', 	'$snapshot', '$hash')
			when matched then update 
			set WHERE_ST = '$where_st', TRUNCATE_TABLE = '$truncate_table', MISSING = '$missing', CRE_BACKUP = '$cre_backup', SNAPSHOT = '$snapshot'" ;

	if (!$owebdb->query($sql))
		copydata_logger("- Warning: Failed to save project. ".$owebdb->Error, 'WARNING');
}

function copydata_project_list() {
	global $owebdb;
	$user = $_SESSION["oweb_user"]->username;

	$sql = "SELECT DISTINCT PROJECT, RESTORE FROM COPYDATA_PROJECTS 
		WHERE PROJECT_OWNER = '$user' OR PROJECT_OWNER = '*' ORDER BY RESTORE DESC, PROJECT ASC";

	if (!$owebdb->query($sql)) {
		copydata_logger("- Warning: Unable to get project data. ".$owebdb->Error, 'WARNING');
	}

	$result_arr = array();
	while ($owebdb->next_record()) {
		$result_arr[] = $owebdb->Record['PROJECT'];
	}
	return $result_arr;
}

function copydata_copy_project ($old_project, $project, $old_src_owner, $src_owner, $old_src_db, $src_db, $old_trg_owner, $trg_owner, $old_trg_db, $trg_db) {
	global $owebdb;

	$sql = "INSERT INTO COPYDATA_PROJECTS (PROJECT, SRC_OWNER, SRC_DB, TRG_OWNER, TRG_DB, TRG_TABLE_NAME, WHERE_ST, TRUNCATE_TABLE, MISSING, CRE_BACKUP, PROJECT_OWNER, SNAPSHOT, HASH, SEQUENCE, RESTORE, SRC_TABLE_NAME)
			SELECT '$project', '$src_owner', '$src_db', '$trg_owner', '$trg_db', TRG_TABLE_NAME, WHERE_ST, TRUNCATE_TABLE, MISSING, CRE_BACKUP, PROJECT_OWNER, SNAPSHOT, HASH, SEQUENCE, RESTORE, SRC_TABLE_NAME
			FROM COPYDATA_PROJECTS
			WHERE PROJECT = '$old_project'
			AND SRC_OWNER = '$old_src_owner'
			AND SRC_DB = '$old_src_db'
			AND TRG_OWNER = '$old_trg_owner'
			AND TRG_DB = '$old_trg_db'";

	if (!$owebdb->query($sql))
		copydata_logger("- Warning: Failed to copy project data. ".$owebdb->Error, 'WARNING');
}

function copydata_project_details($project) {
	global $owebdb;
	$user = $_SESSION["oweb_user"]->username;

	$sql = "SELECT SRC_OWNER, SRC_DB, SRC_TABLE_NAME, TRG_OWNER, TRG_DB, TRG_TABLE_NAME, SEQUENCE, WHERE_ST, TRUNCATE_TABLE, MISSING, CRE_BACKUP, SNAPSHOT
			FROM COPYDATA_PROJECTS 
			WHERE ( PROJECT_OWNER = '".addslashes($user)."' OR PROJECT_OWNER = '*' )
			AND PROJECT = '".addslashes($project)."' 
			ORDER BY SEQUENCE ASC, SRC_OWNER ASC, TRG_TABLE_NAME";

	$result_arr=$owebdb->build_results($sql);
	
	if ($owebdb->Error)
		copydata_logger("- Warning: Unable to get project data. ".$owebdb->Error, 'WARNING');

	if (count($result_arr) > 0) {
		return $result_arr;
	} else {
		return false;
	}
}

function copydata_delete_project($project) {
	global $owebdb;
	$user = $_SESSION["oweb_user"]->username;

	$sql = "DELETE FROM COPYDATA_PROJECTS WHERE PROJECT_OWNER = '$user' AND PROJECT = '$project'";

	if (!$owebdb->query($sql))
		copydata_logger("- Warning: Unable to get project data. ".$owebdb->Error, 'WARNING');

	$result_arr = array();
	while ($owebdb->next_record())
		$result_arr[] = $owebdb->Record['PROJECT'];
	return $result_arr;
}

function copydata_delete_project_table($project, $src_owner, $src_db, $trg_owner, $trg_db, $table_name) {
	global $owebdb;
	$user = $_SESSION["oweb_user"]->username;

	$sql = "DELETE FROM COPYDATA_PROJECTS WHERE ";
	if ($user == 'admin')
		$sql .= " (PROJECT_OWNER = '$user' OR PROJECT_OWNER = '*') ";
	else
		$sql .= " PROJECT_OWNER = '$user' ";
	$sql .=	"AND PROJECT = '$project'
			AND SRC_OWNER = '$src_owner' AND SRC_DB ='$src_db' AND TRG_OWNER = '$trg_owner' 
			AND TRG_DB = '$trg_db' AND TRG_TABLE_NAME = '$table_name'";

	if (!$owebdb->query($sql))
		copydata_logger("- Warning: Unable to delete table from project. ".$owebdb->Error, 'WARNING');
}

function copydata_create_backup($owner, $table, $trg_db, $bck_name, $is_table) {
	global $trg_conn;

	if ($is_table) {
		$exists = copydata_check_table('trg', $owner, $bck_name, false);
	
		if ($exists)
			copydata_drop_table('trg', $owner, $bck_name);

		$sql = "CREATE TABLE $owner.$bck_name COMPRESS AS SELECT * FROM $owner.$table";

		if (!$trg_conn->query($sql))
			copydata_logger("- Warning: Unable create backup table. ".$trg_conn->Error, 'WARNING');
		else { 
			copydata_logger("- Backup table $owner.$bck_name was created successfully.");
			copydata_add_restore_project($owner, $table, $trg_db, $bck_name);
		}

	} else {
		$cre_bck_sql = "select 'create sequence $owner.$bck_name increment by '||
				INCREMENT_BY||' MINVALUE '||MIN_VALUE||
				' MAXVALUE '||MAX_VALUE||' CACHE '||CACHE_SIZE||' START WITH '||
				LAST_NUMBER|| decode(CYCLE_FLAG,'N',' NOCYCLE ',' CYCLE ')||
				decode(ORDER_FLAG,'N',' NOORDER ',' ORDER ') STMT
				from all_sequences where sequence_owner = '$owner' and sequence_name = '$table'";
	
		if (!$trg_conn->query($cre_bck_sql))
			copydata_logger("- Warning: Failed to generate sequence backup. ".$trg_conn->Error, 'WARNING');

		$cre_bck_seq_sql = array();
		while ($trg_conn->next_record()) {
			$cre_bck_seq_sql[] = $trg_conn->Record['STMT'];
		}

		foreach ($cre_bck_seq_sql as $sql) {
			if(!$trg_conn->query($sql))
				copydata_logger("- Warning: Failed to create backup sequence. ".$trg_conn->Error, 'WARNING');
			else {
				copydata_logger("- Backup sequence $owner.$bck_name was created successfully.");
				copydata_add_restore_project($owner, $table, $trg_db, $bck_name);
			}
		}
	}
}

function copydata_add_restore_project($owner, $table, $db, $bck_name) {
	global $owebdb;
	global $project;
	global $time_stamp;

	$restore_project = 'RESTORE_'.$time_stamp;

	$sql = "INSERT INTO COPYDATA_PROJECTS
			(PROJECT, SRC_OWNER, SRC_DB, SRC_TABLE_NAME, TRG_OWNER, TRG_DB, TRG_TABLE_NAME, WHERE_ST, TRUNCATE_TABLE, MISSING, 
				CRE_BACKUP, PROJECT_OWNER, SNAPSHOT, HASH, SEQUENCE, RESTORE)
			SELECT 
			'$restore_project', TRG_OWNER, TRG_DB, '$bck_name', TRG_OWNER, TRG_DB, TRG_TABLE_NAME, NULL, 1, NULL, 
				0, PROJECT_OWNER, SNAPSHOT, NULL, SEQUENCE, 1
			FROM COPYDATA_PROJECTS 
			WHERE PROJECT = '$project' and TRG_OWNER = '$owner' and TRG_DB = '$db' and TRG_TABLE_NAME = '$table'";

	if (!$owebdb->query($sql)) {
		copydata_logger("- Warning: Unable to add restore project. ".$owebdb->Error, 'WARNING');
	}
}

function copydata_drop_table($db, $user, $table) {

	$sql = "DROP TABLE $user.$table CASCADE CONSTRAINTS";

	switch ($db) {
		case "src":
			global $src_conn;
			$conn = $src_conn;
			break;
		case "trg":
			global $trg_conn;
			$conn = $trg_conn;
			break;
	}

	if (!$conn->query($sql)) {
		copydata_logger("- Warning: Unable to drop table. ".$conn->Error, 'WARNING');
	}
}

function copydata_get_locks ($user, $table) {
	global $trg_conn;

	$sql = "select l.oracle_username, l.os_user_name from all_objects a, v\$locked_object l
			where l.object_id=a.object_id
			and owner = '$user'
			and object_name = '$table'";

	$result_arr=$trg_conn->build_results($sql);

	return $result_arr;
}

function copydata_delete($user, $table, $where_st) {
	global $trg_conn;

	$sql = "DELETE $user.$table WHERE $where_st";
	
	if (!$trg_conn->query($sql))
		copydata_logger("- Warning: Unable to delete $user.$table.".$trg_conn->Error, 'WARNING');
	else 
		copydata_logger("- Data was successfully deleted from $user.$table.");
}

function copydata_refresh_snapshot($user, $table) {
	global $trg_conn;

	$sql = "SELECT owner, name FROM all_snapshots 
	WHERE master_owner = '$user' AND master = '$table'";

	$result_arr=$trg_conn->build_results($sql);

	if ((!count($result_arr) > 0) || ($trg_conn->Error)) {
		copydata_logger("- Warning: Materialized view ".strtoupper($table)." not found ....", 'WARNING');
		return false;
	}

	$sql = "BEGIN DBMS_SNAPSHOT.REFRESH('REFREAD.$table','C'); END;";
	
	if (!$trg_conn->query($sql))
	{
		$trg_conn->disconnect();
		return false;
	}
	else {
		copydata_logger("- Materialized view ".strtoupper($table)." was refreshed successful.");
	}
}

function copydata_get_erd($db, $owner, $table) {
	$sql = "SELECT COLUMN_NAME FROM all_tab_columns
			WHERE owner = '$owner' AND table_name = '$table'";
	
	switch ($db) {
		case "src":
			global $src_conn;
			$conn = $src_conn;
			break;
		case "trg":
			global $trg_conn;
			$conn = $trg_conn;
			break;
	}

	if (!$conn->query($sql))
		copydata_logger("- Warning: Unable to get ERD. ".$conn->Error, 'WARNING');

	$result_arr = array();
	while ($conn->next_record()) {
		$result_arr[] = $conn->Record['COLUMN_NAME'];
	}
	return $result_arr;
}

function copydata_disable_all($tables) {
	
	global $time_stamp;

	$dbs = array();

	foreach ($tables as $key=>$value)
		if (!$value['SEQUENCE'])
			$dbs[$value['TRG_DB']] = $value;

	if (empty($dbs))
		return;

	foreach ($dbs as $key=>$value) {
		$dbc = core_dbc_get($key);
		$conn = new Oracle();
		if (!$conn->connect($dbc['DB_NAME'],$dbc['DB_USER'],$dbc['DB_PASSWORD'])) {
			copydata_logger("ERROR: Unable to connect to database (".$dbc['DB_NAME'].")<br>Check the DB connection setup under \"System Administration -> DB Connections\"", 'ERROR');
			exit;
		}
		
		$table_con = "CON_".$time_stamp.strtoupper(substr(hash("md5",$dbc['DB_NAME']), 0, 16));

		$owners = array();

		foreach ($tables as $table) {
			if ($key == $table['TRG_DB'])
				$owners[$table['TRG_OWNER']] = 1;
		}

		$disable_sql = "CREATE TABLE $table_con AS SELECT owner, table_name, constraint_name, 'CONST' type
						FROM  all_constraints WHERE status = 'ENABLED' AND constraint_type = 'R' AND owner IN (";			

		foreach ($owners as $key => $value) {
			$disable_sql .= " '".$key."', ";
		}

		$disable_sql = substr($disable_sql, 0, -2).")";

		$disable_sql .= " UNION SELECT DISTINCT owner, table_name, trigger_name, 'TRIGGER' type
							FROM all_triggers WHERE status = 'ENABLED' AND owner IN (";

		foreach ($owners as $key => $value) {
			$disable_sql .= " '".$key."', ";
		}

		$disable_sql = substr($disable_sql, 0, -2).")";

		if (!$conn->query($disable_sql))
			copydata_logger("- Warning: Failed to create constraints table. ".$conn->Error, 'WARNING');

		$generate_sql = "SELECT CASE type WHEN 'CONST' THEN 'alter table '||owner||'.'||table_name||' disable constraint '||constraint_name
						WHEN 'TRIGGER' THEN 'ALTER TRIGGER '||owner||'.'||constraint_name||' disable' END STMT
						FROM $table_con";

		if (!$conn->query($generate_sql))
			copydata_logger("- Warning: Failed to generate disable SQLs. ".$conn->Error, 'WARNING');

		$disable_stmts = array();
		while ($conn->next_record()) {
			$disable_stmts[] = $conn->Record['STMT'];
		}

		foreach ($disable_stmts as $sql) {
			if(!$conn->query($sql))
				copydata_logger("- Warning: Failed to disable constraints. ".$conn->Error, 'WARNING');
		}
	}

	$conn->disconnect;
}

function copydata_enable_all($tables) {

	global $time_stamp;

	$dbs = array();

	foreach ($tables as $key=>$value)
		if (!$value['SEQUENCE'])
			$dbs[$value['TRG_DB']] = $value;

	if (empty($dbs))
		return;

	foreach ($dbs as $key=>$value) {
		$dbc = core_dbc_get($key);
		$conn = new Oracle();
		if (!$conn->connect($dbc['DB_NAME'],$dbc['DB_USER'],$dbc['DB_PASSWORD'])) {
			copydata_logger("ERROR: Unable to connect to database (".$dbc['DB_NAME'].")<br>Check the DB connection setup under \"System Administration -> DB Connections\"", 'ERROR');
			exit;
		}
		
		$table_con = "CON_".$time_stamp.strtoupper(substr(hash("md5",$dbc['DB_NAME']), 0, 16));

		$generate_sql = "SELECT CASE type WHEN 'CONST' THEN 'alter table '||owner||'.'||table_name||' enable constraint '||constraint_name
						WHEN 'TRIGGER' THEN 'alter trigger '||owner||'.'||constraint_name||' enable ' END STMT
						FROM $table_con";

		if (!$conn->query($generate_sql))
			copydata_logger("- Warning: Failed to generate enable SQLs. ".$conn->Error, 'WARNING');

		$enable_stmts = array();
		while ($conn->next_record()) {
			$enable_stmts[] = $conn->Record['STMT'];
		}

		foreach ($enable_stmts as $sql) {
			if(!$conn->query($sql))
				copydata_logger("- Warning: Failed to enable constraints. ".$conn->Error, 'WARNING');
		}

		$drop_sql = "DROP TABLE $table_con";
		$conn->query($drop_sql);

	$conn->disconnect;
	
	}
}

function copydata_proj_copy_details($project) {
	global $owebdb;

	$sql = "select distinct src_owner, src_db, trg_owner, trg_db from COPYDATA_PROJECTS where project = '$project' and nvl(restore,0) = 0";

	$result_arr = $owebdb->build_results($sql);
	if ($owebdb->Error || empty($result_arr))
		return false;
	return $result_arr;
}


function copydata_logger($msg, $type = 'LOG', $severity = 6) {
	global $time_stamp;
	global $project;
	
	
	
	// $msgArr = array('Time'		=>	$time_stamp, 
	// 				'Project'	=>	$project,
	// 				'User'		=>	$_SERVER['PHP_AUTH_USER'],
	// 				'IP'		=>	$_SERVER['REMOTE_ADDR'],
	// 				'Message'	=>	$msg);
	
	//$msg = serialize($msgArr);
	
	$logFile = "./tmp/".$project.$time_stamp.".log";

	file_put_contents($logFile, $msg."\n", FILE_APPEND | LOCK_EX);

	switch ($type) { 
		case "LOG":
			echo "<h".$severity.">".$msg."</h".$severity.">\n";
		break;
		case "WARNING":
			echo "<h6 style=\"color:red\">".$msg."</h6>\n";
		break;
		case "ERROR":
			echo "<h6 style=\"color:red\">".$msg."</h6>\n";
		break;
		default:
	}
}

/* end copydata functions */
?>