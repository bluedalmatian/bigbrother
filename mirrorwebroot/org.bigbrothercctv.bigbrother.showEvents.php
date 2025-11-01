<?php
ob_start();
//CONVENTION: Any variable name in $CAPITALS is declared in the mirror control file (org.bigbrothercctv.bigbrother.bigbrotherd.php)
	

	function controlFileIncludeFail($errno, $errstr, $errfile, $errline)
	{
		$nodaemonerrmsg="<center><table cellspacing=0 cellpadding=0 border=0><tr><td><img src=bb.png width=64 valign=middle></td>";
		$nodaemonerrmsg=$nodaemonerrmsg."<td valign=middle><p><font face=face='Arial','Verdana'>CCTV not available,BigBrother is not running</font></p></td></tr></table></center>";
		exit($nodaemonerrmsg);
	}
	function requiredIncludeFail($errno, $errstr, $errfile, $errline)
	{
		$requiredincludefailerrmsg="<center><table cellspacing=0 cellpadding=0 border=0><tr><td><img src=bb.png width=64 valign=middle></td>";
                $requiredincludefailerrmsg=$requiredincludefailerrmsg."<td valign=middle><p><font face=face='Arial','Verdana'>Error: A required file is missing or could not be read</font></p></td></tr></table></center>";
		exit($requiredincludefailerrmsg);
	}

	set_error_handler("controlFileIncludeFail");
	include_once("./org.bigbrothercctv.bigbrother.bigbrotherd.php");

	set_error_handler("requiredIncludeFail");
	include_once("./org.bigbrothercctv.bigbrother.functions.php");
	set_error_handler(NULL); //clear custom error handler as it will cause a false failure, this is probably a bug in PHP???
	include_once("./org.bigbrothercctv.bigbrother.Event.php");
	set_error_handler(NULL);



global $DAEMONPID;

$erase=$_GET['erase'];
$erase=strtolower($erase);
if ($erase=="true")
{
	doErase();
}





function doErase()
{
	$handle = fopen('org.bigbrothercctv.bigbrother.aieventlog.txt', 'w');
	if ($handle==false)
	{
		die("Error opening file\n");
	}
	ftruncate($handle, 0);
	fwrite($handle, "#THIS FILE IS GENERATED AUTOMATICALLY BY BIGBROTHER WHEN AI EVENT MONITORING IS ENABLED\n");
	fclose($handle);
	exit();
}
?>
<html>
<head>
<link rel="icon" type="image/png" href="bbsimple.png">
<title>CCTV Detected Events (BigBrother)</title>
<style>



#toolbar{
	
}

.toolbarbutton{
	
	margin:4px;
	cursor:pointer;
	padding:4px;
	border-width: 1px;
	border-color: white;
	border-style: solid;
}

body {
  background-color: black;
  background-image: linear-gradient( rgba(255,0,0,0), rgba(71,71,71,1));
}

p,h1,h2,h3 {
	color: white;
	font-family: Arial, Verdana;
}

p.supersmall {
	font-size: 0.8em;
}


p.extremesmall {
        font-size: 0.6em;
}


pre {
    color: white;
}

#eventtable tr:nth-child(even) {
  background-color: #a3a3a3;
}

#eventtable tr:nth-child(odd) {
  background-color: #727272;
}

</style>

<meta http-equiv="refresh" content="300">

<?php 

function eventCompare($a,$b) 
{
	// array sorting function, takes two Event objects and must return 0 if these elements are considered equal,
    // a value lower than 0 if the first value is lower 
	//and a value higher than 0 if the first value is higher. 
	
    
    return $b->GetEventTimestamp() - $a->GetEventTimestamp();

}

function readFileBackwards($numlines, $path)
{
	$cmd= "tail -n ".$numlines." ".$path;
	$output = shell_exec($cmd);
	return $output;
}


	$allEvents=array(); 
	$output=readFileBackwards(1000,"/usr/local/bigbrother/mirrorwebroot/org.bigbrothercctv.bigbrother.aieventlog.txt");
	$lines=preg_split('/\n/', $output);
	
	foreach ($lines as $line)
	{
		if ( ($line[0]=="\n") || ($line[0]=="#") || ($line[0]=="") )
        {
             //blank line or comment line or blank line at EOF
              continue;
        }
		$elements=preg_split('/\s+/', $line);
		if (sizeof($elements) < 4) //at time of writing there are 4 mandatory params (more maybe added later), we only need to read upto 4 here.
		{
		
			exit("<p>Syntax error in AI Event Log on line ".$lineno." Too few parameters</p>");
		}
		$event=new Event($elements);
		if ($event->initCheck()) 
		{
			//we have a valid Event obj
			$allEvents[]=$event;
				
		}
		else
		{
			
			exit("<p>ERROR: Init of Event failed</p>");
		}
	}



	if (sizeof($allEvents)>0)
	{
		echo("<script language='Javascript'>var logentries=true;</script>");
	}
	else
	{
		echo("<script language='Javascript'>var logentries=false;</script>");
	}

	foreach ($allEvents as $event)
	{
		usort($allEvents, 'eventCompare');
	}
	
?>

<script language=Javascript>

var erase_xhr = new XMLHttpRequest();

function mouseOverButton(obj)
{
	var td=document.getElementById(obj.id);
	td.style.backgroundColor="#cfcdcd";
}

function mouseOutButton(obj)
{
	var td=document.getElementById(obj.id);
	td.style.backgroundColor="#000000";
}

function onLoad()
{
	
	var eraselogbutton=document.getElementById("eraselogbutton");
	
	//emulate a static var using function property (funcs are objs)
	if (eraselogbutton.onmouseover!=null)
	{
		//save existing mousoverhandler to a static var so it will persist across multiple invocs of this func
		onLoad.mouseOverHandler= eraselogbutton.onmouseover;
	}


	if (eraselogbutton.onclick!=null)
        {
                //save existing onclickhandler to a static var so it will persist across multiple invocs of this func
                onLoad.onClickHandler= eraselogbutton.onclick;
        }



	
	if (logentries==false)
	{
		//remove mouseoverhandler
		eraselogbutton.onmouseover=null;
		eraselogbutton.style.opacity = "0.3";
		eraselogbutton.style.cursor="default";


		//remove onclickhandler
		 eraselogbutton.onclick=null;

	}
	else
	{
		//reset mouseoverhandler back from static var
		eraselogbutton.onmouseover=onLoad.mouseOverHandler;
        	
		//reset onclickhandler back from static var
                eraselogbutton.onclick=onLoad.onClickHandler;
		
		eraselogbutton.style.opacity = "1.0";
		eraselogbutton.style.cursor="pointer";
	}
}

function eraseLog()
{
	
	var eraseok=confirm("This will erase ALL events from the notification history, are you sure?")

	if (eraseok==true)
	{
			//OK button pressed
	
			var url = "org.bigbrothercctv.bigbrother.showEvents.php?erase=true";
    		erase_xhr.open('GET',url,true);
    		erase_xhr.onreadystatechange=doReload; //callback when response comes
    		erase_xhr.send(null);
	}
			
	
}

function doReload()
{
	location.reload();
}

</script>



</head>



<body style="margin: 0px;" onload='onLoad();'>








<table cellspacing=0 cellpadding=5 border=0 id=toolbar width=100%>
	<tr>
	    <td>
		<table cellspacing=0 cellpadding=0 border=0 width=100%>
		 <tr>
                        <td>
				<p><font size=-1><img src=bb.png width=100 style="margin: 2px;" align=middle valign=middle>
				<?php  echo " BigBrother ".$formattedversion; $date = date('Y-m-d H:i:s'); echo " Page loaded: ".$date." (server time)"; ?> 
				</p></font><div id=msgarea></div>
			</td>
                </tr>
		<tr height=1 width=100%>
			<td bgcolor="#666565"></td>
		</tr>
		</table>
	   </td>
	</tr>
	<tr>
	  <td>
		<table cellspacing=4 cellpadding=0 border=0>
		<tr>
			
			<!Erase log button>
			<td class=toolbarbutton id=eraselogbutton onclick='eraseLog();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);'>
			<p>
				<img src=trash.png width=48 align=middle valign=middle>Erase All Notifications
			</p>
			<p class=extremesmall>This will NOT remove snapshot images</p>
			</td>
			
		</tr>
		</table>
	   </td>
	</tr>
</table>

<h2>Detected Event Notification History:</h2>
<p>This shows up to the last 1000 events</p>
<p class=supersmall>No. of events: <?php echo(sizeof($allEvents));?></p>


<table cellspacing=0 cellpadding=0 border=0 width=100% height=100%>
<tr height=1 width=100%>
			<td bgcolor="#666565"></td>
</tr>

<tr>
<td width=100% height=100% valign=top>
<br>
<table cellpadding=0 cellspacing=0 border=1 id=eventtable width=100%>
<tr>
	<td width=100><p><b>Date</b></p></td>
	<td width=100><p><b>Time</b></p></td>
	<td><p><b>Camera</b></p></td>
	<td width=*><p><b>Event</b></p></td>
</tr>

<?php
	if (sizeof($allEvents)==0)
	{
		echo("<td colspan=4><center><p>No Events Detected</p></center></td>");
	}
	foreach ($allEvents as $event)
	{
		echo("<tr>");
		
	
		
		
		echo("<td width=100>");
		echo("<p>".$event->getEventDate()."</p>");
		echo("</td>");
		
		echo("<td width=100>");
		echo("<p>".$event->getEventTime()."</p>");
		echo("</td>");
		
		echo("<td>");
		echo("<p>".$event->getCameraName()."</p>");
		echo("</td>");
		
		echo("<td width=*>");
		
		echo("<p><img src=".$event->GetTypeCode()."-white.png width=24 height=16 align=middle></img>");
		echo("".$event->getEventDesc()."</p>");
		echo("</td>");
		echo("</tr>");
	}
?>




</table>
</td>
</tr>

<tr height=1 width=100%>
			<td bgcolor="#666565"></td>
		</tr>
</table>

<center>
<table cellspacing=0 cellpadding=0 border=0>
<tr>
	<td align=center><font size=-1><p>BigBrother &copy; Copyright Andrew Wood 2016-<?php printCurrentYear();?>. Licensed under the GNU Public License 3</p></font></td>
</tr>
<tr>
        <td align=center><font size=-1><p></p></font></td>
</tr>
</table>
</center>

</body>
</html>
