<?php
/*
* CopyData Project Copy Page
*
* @original author Alexander Shereshevsky - Amdocs (shereshevsky@gmail.com)
* @created Mar 16,2013
* @last_modification Mar 16,2013
*/

$project = stripslashes($_REQUEST['project']);
$action = isset($_REQUEST['action'])?$_REQUEST['action']:false;
$postproc = isset($_REQUEST['postproc'])?$_REQUEST['postproc']:false;

// If post processing was requested and we're in the appropriate
// display section set the action to the requested postproc
if ($postproc && $display_section == "postproc") {
	$action = "$postproc";
}

$time_stamp = time();

//if submitting form process requested action
switch ($action) {
	case "copydata":

		if ($buffered) {
			ob_end_flush();
			flush();
			$buffered=false;
		}
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
		if (empty($_REQUEST['tables']))
			$tablesArr = copydata_project_details($project);
		else {
			$tempArr = copydata_project_details($project);
			$tablesArr = Array();

			foreach ($tempArr as $key => $value) {
				if (in_array($key, $_REQUEST['tables']))
					$tablesArr[] = $value;
			}
		}

		copydata_run_project ($tablesArr);

		echo "</div>";
		exit;
	break;

	case "chosen_proj":
		echo "<div id=\"contentBox\">";
		$tablesArr = copydata_project_details($project);

		echo "<h3>Select tables to copy from project \"".strtoupper($project)."\"</h3>\n";
?>
		<form autocomplete="off" name="Resubmit" action="oraweb.php" method="post">
		<input type="hidden" name="project" value="<?php echo $project?>">
		<input type="hidden" name="display" value="<?php echo $display?>">
		<input type="hidden" name="postproc" value="copydata">
		<input style="margin:10px;" type='button' name='Check_All' value='Check All' onClick='$(":checkbox").attr("checked",true);'>
		<input style="margin:10px;" type='button' name='UnCheck_All' value='Uncheck All' onClick='$(":checkbox").attr("checked",false);'><br>
		<table width="90%">
			<tr><th>Table Name</th><th>Source</th><th>Target</th></tr>
<?php
		foreach ($tablesArr as $key => $value) {
			echo "<tr><td style=\"padding-right:10px;\"><input type=\"checkbox\" tabindex=$key checked name=tables[] 
			value=".$key." style=\"border:none;\" />".$value['TRG_TABLE_NAME']."</td>
			<td style=\"padding-right:10px;\">".$value['SRC_OWNER']."@".$value['SRC_DB']."</td>
			<td>".$value['TRG_OWNER']."@".$value['TRG_DB']."</td></tr>";
		}
?>
		<tr>
			<td style="padding-top:20px;">
				<h5><input name="Submit" type="submit" tabindex=15 value="<?php echo _("Submit")?>" onclick="return checkCopyParam(Resubmit);"></h5>
			</td>
		</tr>
		</table>
		</form>
		</div>
<?php
}

// If postproc is set to copydata skip display of env menu
if (!$postproc == "copydata") {

	//get existing environment definitions
	$projArr = copydata_project_list();

?>

	<div class="rnav">
	<ul>
		<li><a class="current" href="#"><?php echo _("Projects List")?></a></li>
		
<?php 
	foreach ($projArr as $record) {
	    echo "\t<li><a ".($project==$record ? 'class="current"':'')." href=\"oraweb.php?display=".urlencode($display)."&amp;project=".urlencode($record)."&amp;postproc=chosen_proj\">".$record."</a></li>\n";
	}
?>
	</ul>
	</div> 

<!-- /rnav -->

<?php
	if ($project) {
		
		echo "<h3>"._("Copy Data By Predefined Project")." (".$project.")</h3><br>\n";
		echo "<br><br>\n";
?>

<?php 
	} else {
		echo "<br><h3>Copy Data by Predefined Project</h3><br><i>- Select a project from the list to the right to begin.<br>\n";	
	}
}
?>

<script language="javascript">
<!-- 

function checkCopyParam(form) {
	for (var i = 0; i < form.length; i++) {
		if (form[i].type == 'checkbox' && form[i].checked == true) {
			// At least one checkbox IS checked
			return true;
		}
	}

	// Nothing has been checked
	alert("Please select at least one table from project:");
	return false;
}
-->
</script>