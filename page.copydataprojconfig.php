<?php
/*
* CopyData Project Config Page
*
* @original author Alexander Shereshevsky - Amdocs (shereshevsky@gmail.com)
* @created Mar 16,2013
* @last_modification Mar 16,2013
*/

$action = isset($_REQUEST['action'])?$_REQUEST['action']:false;
$postproc = isset($_REQUEST['postproc'])?$_REQUEST['postproc']:false;

if ($postproc && $display_section == "postproc") {
	$action = "$postproc";
}

//populate global variables from the request string
$set_globals = array("project", "src_owner", "src_db", "trg_owner", "trg_db", "trg_table_name", "like_st", "where_st", "new_project");
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

$projects = copydata_project_list();

switch ($action) {
	case "addtab":
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
		
		foreach ($tablesArr as $table)
			copydata_save_project($project, $src_owner, $src_db, $trg_owner, $trg_db, $table, $sequence, $where_st, $truncate_table, $missing, $cre_backup, $snapshot, $hash);
		
		redirect_standard('project', 'src_owner', 'trg_owner', 'truncate_table', 'cre_backup');
	break;
	
	case "delproj":
		copydata_delete_project($project);
		$project = ""; // go to "add" screen
		redirect_standard();
	break;
	
	case "deltab":
		copydata_delete_project_table($project, $src_owner, $src_db, $trg_owner, $trg_db, $trg_table_name);
		redirect_standard('project');
	break;

	case "copyproj":
		$proj_copy_details = copydata_proj_copy_details($project);
	break;

	case "creprojcopy":
		$proj_copy_details = copydata_proj_copy_details($project);
		
		$arrayParams = array();
		print $num_of_sets;
		foreach ($_REQUEST as $key => $value) {
			if (strpos($key, "00"))
				if (strpos($key, "db"))
					$arrayParams[substr($key, strpos($key, "00")-1,1)][substr($key,0,-3)] = stripslashes(trim(strip_tags($value)));
				else
					$arrayParams[substr($key, strpos($key, "00")-1,1)][substr($key,0,-3)] = strtoupper(stripslashes(trim(strip_tags($value))));
		}

		foreach ($arrayParams as $key => $value) {
			copydata_copy_project(	$project, $new_project,
									$value['old_src_owner'], $value['src_owner'], 
									$value['old_src_db'], $value['src_db'], 
									$value['old_trg_owner'], $value['trg_owner'],
									$value['old_trg_db'], $value['trg_db']);
		}
		redirect_standard();

	break;
}

if ($project) {
	echo "<h3>"._("Edit Project")." (".$project.")</h3>";
?>
	<input type="button" value="Delete Project" onclick="location.replace('oraweb.php?display=<?php echo urlencode($display) ?>&project=<?php echo urlencode($project) ?>&action=delproj');">
	<input type="button" value="Copy Project" onclick="location.replace('oraweb.php?display=<?php echo urlencode($display) ?>&project=<?php echo urlencode($project) ?>&action=copyproj');">

<?php
} else {
	echo "<h3>"._("Create Project")."</h3>";
}

?>

<div class="rnav">
<ul>
	<li><a <?php  echo ($project=='' ? 'class="current"':'') ?> href="oraweb.php?display=<?php echo urlencode($display)?>"><?php echo _("Add Project")?></a></li>

<?php 
foreach ($projects as $record)
	echo "\t<li><a ".($project==$record ? 'class="current"':'')." href=\"oraweb.php?display=".urlencode($display)."&amp;project=".urlencode($record)."\">".$record."</a></li>\n";
?>
</ul>
</div> <!-- /rnav -->

<?php
if ($action != "copyproj") {
?>

	<form autocomplete="off" name="AddTab" action="oraweb.php" method="post" style="padding-top:20px;">
		<input type="hidden" name="display" value="<?php echo $display?>">
		<input type="hidden" name="action" value="addtab">
		<input type="hidden" name="project" value="<?php echo $project?>">
		<table width="60%">
		<tr >
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
				<h5><input name="AddTab" type="submit" tabindex=15 value="<?php echo _("Add Table")?>" onclick="return checkCopyParam(AddTab);"></h5>
			</td>
		</tr>
		</table>
	</form>


<?php
} else {  //start if ($action == "copyproj")
?>

	<form autocomplete="off" name="creprojcopy" action="oraweb.php" method="post">
		<input type="hidden" name="display" value="<?php echo $display?>">
		<input type="hidden" name="project" value="<?php echo $project?>">
		<input type="hidden" name="action" value="creprojcopy">
		<table width="70%">
			<tr>
				<td style="padding-top:20px;"><a href=# class="info"><?php echo $project;?><span>Define new project name.</span></a></td>
				<td style="padding-top:20px;"><input type="text" maxlength="30" size="20" name="new_project" id="new_project" tabindex=1 value="<?php echo $new_project;?>"></td>
			</tr>
<?php		
		$cnt=1;
		foreach ($proj_copy_details as $key => $value) { //start foreach
			echo "<input type=\"hidden\" name=old_src_owner".$cnt."00 value=".$value['SRC_OWNER'].">";
			echo"<tr>";
				echo "<td style=\"padding-top:20px;\"><a href=# class=\"info\">".$value['SRC_OWNER']."<span>Define new source owner.</span></a></td>";
				echo "<td style=\"padding-top:20px;\"><input type=\"text\" maxlength=\"30\" size=\"20\" tabindex=".$cnt."00 name=\"src_owner".$cnt."00\" id=\"src_owner".$cnt."00\"></td>";
			echo"</tr>";
			echo "<input type=\"hidden\" name=old_src_db".$cnt."00 value=".$value['SRC_DB'].">";
			echo"<tr>";
				echo "<td><a href=# class=\"info\">".$value['SRC_DB']."<span>Define new source DB.</span></a></td>";
				echo "<td><input type=\"text\" maxlength=\"30\" size=\"20\" tabindex=".$cnt."01 name=\"src_db".$cnt."00\" id=\"src_db".$cnt."00\"></td>";
			echo"</tr>";
			echo "<input type=\"hidden\" name=old_trg_owner".$cnt."00 value=".$value['TRG_OWNER'].">";
			echo"<tr>";
				echo "<td><a href=# class=\"info\">".$value['TRG_OWNER']."<span>Define new target owner.</span></a></td>";
				echo "<td><input type=\"text\" maxlength=\"30\" size=\"20\" tabindex=".$cnt."02 name=\"trg_owner".$cnt."00\" id=\"trg_owner".$cnt."00\"></td>";
			echo"</tr>";
			echo "<input type=\"hidden\" name=old_trg_db".$cnt."00 value=".$value['TRG_DB'].">";
			echo"<tr>";
				echo "<td><a href=# class=\"info\">".$value['TRG_DB']."<span>Define new target DB.</span></a></td>";
				echo "<td><input type=\"text\" maxlength=\"30\" size=\"20\" tabindex=".$cnt."03 name=\"trg_db".$cnt."00\" id=\"trg_db".$cnt."00\"></td>";
			echo"</tr>";

			$cnt++;
			} 
			//end foreach
?>	
			<tr>
				<td style="padding-top:20px;"><h5><input name="Submit" type="submit" tabindex=1000 value="<?php echo _("Submit")?>" onclick="return checkCopyParam(creprojcopy);"></h5></td>
			</tr>
		</table>
	</form>
<?php

}//end if ($action == "copyproj")
if ($project) { //start if ($project)
	$tables = copydata_project_details($project);

	echo "<table width=\"80%\">";
		echo "<tr><th>Table Name</th><th>Source</th><th style=\"padding-right:20px;\">Target</th></tr>";
		foreach ($tables as $key => $value) {
			echo "<tr>
				<td style=\"padding-right:10px;\">".$value['TRG_TABLE_NAME']."</td>
				<td style=\"padding-right:10px;\">".$value['SRC_OWNER']."@".$value['SRC_DB']."</td>
				<td style=\"padding-right:10px;\">".$value['TRG_OWNER']."@".$value['TRG_DB']."</td>
				<td class=\"gray\">
					<a href=\"oraweb.php?display=".urlencode($display).
						"&amp;project=".urlencode($project).
						"&amp;src_owner=".urlencode($value['SRC_OWNER']).
						"&amp;src_db=".urlencode($value['SRC_DB']).
						"&amp;trg_owner=".urlencode($value['TRG_OWNER']).
						"&amp;trg_db=".urlencode($value['TRG_DB']).
						"&amp;trg_table_name=".urlencode($value['TRG_TABLE_NAME']).
						"&amp;action=deltab\" onclick=\"return confirmDelete('".$value['TRG_TABLE_NAME']."');\">
						<img src=\"images/delete.gif\" border=none alt=\"Delete\">
					</a>
				</td>
			</tr>\n";
			}
	echo "</table>";
} //end if ($project)


?>