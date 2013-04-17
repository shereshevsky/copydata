<?php
/*
* CopyData Manual Copy Page
*
* @original author Alexander Shereshevsky - Amdocs (shereshevsky@gmail.com)
* @created Mar 16,2013
* @last_modification Mar 16,2013
*/

$action = isset($_REQUEST['action'])?$_REQUEST['action']:false;
$postproc = isset($_REQUEST['postproc'])?$_REQUEST['postproc']:false;

// If post processing was requested and we're in the appropriate
// display section set the action to the requested postproc
if ($postproc && $display_section == "postproc") {
	$action = "$postproc";
}

// populate global variables from the request string
$set_globals = array("project", "src_owner", "src_db", "trg_owner", "trg_db", "trg_table_name", "like_st", "where_st");
foreach ($set_globals as $var) {
	if (isset($_REQUEST[$var])) {
		if ($var == 'src_db' || $var == 'trg_db' || $var == 'where_st')
			$$var = stripslashes($_REQUEST[$var]);
		else 
			$$var = clearData( $_REQUEST[$var] );
	}
}

$checkboxes = array("all_tables", "truncate_table", "missing", "cre_backup", "snapshot", "sequence");

foreach ($checkboxes as $var) {
	if (($_REQUEST[$var] == "on") || ($_REQUEST[$var] == 1)) {
		$$var = 1;
	} else {
		$$var = 0;
	}
}

// get list of defined DB Connections
$dbconnArr = core_dbc_list();
$dbconn = '<option>';
foreach ($dbconnArr as $record) {
	$dbconn .= '<option>'.$record;
}

$time_stamp = time();

//if submitting form process requested action
switch ($action) {
	case "copydata":
		echo "<div id=\"contentBox\">";
		
?>
		<table height="35" width="100%">
		<tr>
			<td width="100px"><a href="#" onclick="document.execCommand('Stop');"><img src="images/cancel.gif" height="16" width="16" border="0" alt="Stop Refresh">&nbsp;Stop</a></td>
			<td width="100px"><a href="javascript:history.go(-1)"><img src="images/arrow_rotate_clockwise.gif" height="16" width="16" border="0" alt="Back">&nbsp;Back</a></td>
			<td align="right" style="padding-right:40px;"><table id="progressbar"><tr><td align="center" style="font-size:10px;"><i>Loading... Please Wait</i></td></tr><tr><td><script>var bar1=createBar(200,10,'white',0,'black','#c6c65a',70,7,3,"","visible");</script></td></tr></table></td>
		</tr>
		</table>

		<script language="JavaScript">
		<!--
		$(document).ready(function(){
		  $("#progressbar").fadeOut("normal");
		});
		
		-->
		</script>
<?php

		if ($like_st || $all_tables) {
			$dbc_src = core_dbc_get($src_db);
			$con = new Oracle();
			if (!$con->connect($dbc_src['DB_NAME'],$dbc_src['DB_USER'],$dbc_src['DB_PASSWORD'])) {
				echo "<div class=\"warning\"><p>"._("ERROR: Unable to connect to database (".$dbc_src['DB_NAME'].")<br>Check the DB connection setup under \"System Administration -> DB Connections\")</p></div>");
				exit;
			}
			$tablesArr = copydata_get_tables($con, $src_owner, $all_tables, $like_st, $sequence);
			$con->disconnect;			
		} else {
			$tablesArr = array();
			$tablesArr = preg_split("/\r\n/",$trg_table_name,-1,PREG_SPLIT_NO_EMPTY);
			$tablesArr = array_unique($tablesArr);
		}

		foreach ($tablesArr as $key=>$value)
		 	$tablesArr[$key] = array('PROJECT' => $project, 'SRC_OWNER' => $src_owner, 'SRC_DB' => $src_db, 
		 							'SRC_TABLE_NAME' => $value, 'TRG_OWNER' => $trg_owner, 'TRG_DB' => $trg_db, 
		 							'TRG_TABLE_NAME' => $value, 'SEQUENCE' => $sequence, 'TRUNCATE_TABLE' => $truncate_table, 
		 							'MISSING' => $missing, 'CRE_BACKUP' => $cre_backup, 'LIKE_ST' => $like_st, 
		 							'WHERE_ST' => $where_st, 'SNAPSHOT' => $snapshot);
		
		copydata_run_project ($tablesArr);

		echo "</div>";
		exit;
	break;
}

// If postproc is set to refresh snap skip display of env menu
if (!$postproc == "copydata") {
	echo "<h3>"._("Data Copy")."</h3><br>\n";
	echo "<br><br>\n";
?>
	<form autocomplete="off" name="CopyParam" action="oraweb.php" method="post">
		<input type="hidden" name="display" value="<?php echo $display?>">
		<input type="hidden" name="postproc" value="copydata">
		<table width="70%">
		<tr>
			<td>
				<a href=# class="info"><?php echo _("Source Schema<span>Enter the source user id.</span>")?></a>: 
			</td><td>
				<input type="text" maxlength="30" size="20" name="src_owner" id="src_owner" tabindex=1 value="<?php echo $src_owner;?>">
			</td>
		</tr>
		<tr>
			<td>
				<a href=# class="info">Database Connection<span>Select a database connection associated with this schema.  New database connections are defined under "System Administration -> DB Connections" .</span></a>: 
			</td>
			<td>
				<select name="src_db" id="src_db" width="15" tabindex=2 style="font-size:10pt;"><?php echo "$dbconn"; ?>
			</td>
		</tr>
		<tr>
			<td>
				<a href=# class="info"><?php echo _("Target Schema<span>Enter the target user id.</span>")?></a>: 
			</td><td>
				<input type="text" maxlength="30" size="20" name="trg_owner" id="trg_owner" tabindex=3 value="<?php echo $trg_owner;?>">
			</td>
		</tr>
		<tr>
			<td>
				<a href=# class="info">Database Connection<span>Select a database connection associated with this schema.  New database connections are defined under "System Administration -> DB Connections" .</span></a>: 
			</td>
			<td>
				<select name="trg_db" id="trg_db" width="15" tabindex=4 style="font-size:10pt;"><?php echo "$dbconn"; ?>
			</td>
		</tr>
		<tr>
			<td>
				<a href=# class="info"><?php echo _("Table(s) Name<span>Enter the table name you wish to copy.</span>")?></a>: 
			</td><td style="padding-top:20px;">
				<textarea name="trg_table_name" id="trg_table_name" rows="4" cols="30" tabindex=5 ></textarea>
			</td>
		</tr>
		<tr>
			<td>
				<a href=# class="info"><?php echo _("Like condition<span>Define like condition to copy only tables %LIKE%.</span>")?></a>: 
			</td><td>
				<input type="text" maxlength="30" size="20" name="like_st" id="like_st" tabindex=6 value="<?php echo $like_st;?>">
			</td>
		</tr>
		<tr>
			<td>
				<a href=# class="info"><?php echo _("Project<span>Enter project name if you want to save it for later use.</span>")?></a>: 
			</td><td>
				<input type="text" maxlength="30" size="20" name="project" id="project" tabindex=7 value="<?php echo $project;?>">
			</td>
		</tr>
		<tr>
			<td>
				<a href=# class="info"><?php echo _("Where Condition<span>Enter the where condition for copied table. The complining rows will be deleted from target table. Example: OBJECT_NAME LIKE 'AD_IMAGE_%' </span>")?></a>: 
			</td><td style="padding-top:20px;">
				<textarea name="where_st" id="where_st" rows="4" cols="30" tabindex=8 ></textarea>
			</td>
		</tr>
		<tr><td><input type="checkbox" tabindex=9 name="all_tables" id="all_tables" style="border:none;">&nbsp;<a href=# class="info"><?php echo _("Copy All Tables<span>Copy ALL tables from source schema.</span>")?></a></td></tr>
		<tr><td><input type="checkbox" tabindex=10 name="truncate_table" id="truncate_table" style="border:none;">&nbsp;<a href=# class="info"><?php echo _("Truncate<span>TRUNCATE target before coping.</span>")?></a></td></tr>
		<tr><td><input type="checkbox" tabindex=11 name="missing" id="missing" style="border:none;" disabled>&nbsp;<a href=# class="info"><?php echo _("Missing only<span>Copy only records MISSING in target (by Primay Key).</span>")?></a></td></tr>
		<tr><td><input type="checkbox" tabindex=12 name="cre_backup" id="cre_backup" style="border:none;" >&nbsp;<a href=# class="info"><?php echo _("Create backup<span>Create _BCK for target table. Table will be created in target schema. Check the name in logs.</span>")?></a></td></tr>
		<tr><td><input type="checkbox" tabindex=13 name="snapshot" id="snapshot" style="border:none;" >&nbsp;<a href=# class="info"><?php echo _("Refresh snapshot.")?></a></td></tr>
		<tr><td><input type="checkbox" tabindex=14 name="sequence" id="sequence" style="border:none;" >&nbsp;<a href=# class="info"><?php echo _("Sequence.")?></a></td></tr>		
		<tr>
			<td style="padding-top:20px;">
				<h5><input name="Submit" type="submit" tabindex=15 value="<?php echo _("Submit")?>" onclick="return checkCopyParam(CopyParam);"></h5>
			</td>
		</tr>
		</table>
	</form>
<?php 
}
?>

<script language="javascript">
<!-- 

function checkCopyParam(theForm) {
	$src_owner = theForm.src_owner.value;
	$src_db = theForm.src_db.value;
	$trg_owner = theForm.trg_owner.value;
	$trg_db = theForm.trg_db.value;
	$table_name = theForm.trg_table_name.value;
	$like_st = theForm.like_st.value;
	$all_tables = theForm.all_tables.value;
	$where_st = theForm.where_st.value;

	if ($src_owner == "" || $src_db == "" || $trg_owner == "" || $trg_db == "") {
		<?php echo "alert('"._("You must specify a valid database user / password.")."')"?>;
		return false;
	} else if (($trg_table_name == "" && $like_st == "") || ($trg_table_name == "" && $all_tables == "")) {
		<?php echo "alert('"._("Enter valid table name or provide like condition.")."')"?>;
		return false;
	} else if ($like_st != "" && $all_tables != "") {
			<?php echo "alert('"._("All tables or like pattern???.")."')"?>;
		return false;
	} else if (($where_st != "" && $all_tables != "") || ($where_st != "" && $like_st != "")) {
		<?php echo "alert('"._("Can't apply where on multiple tables.")."')"?>;
		return false;
	}
}
-->
</script>