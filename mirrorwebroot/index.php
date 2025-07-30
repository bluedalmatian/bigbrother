

<?php
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
	include_once("./org.bigbrothercctv.bigbrother.Camera.php");
	set_error_handler(NULL);

	//System wide knobs to be read from global config file
	$allownewfilefromweb=false;
	$cameraconffilepath="";


	//Webpage display specific knobs which can (optionally) be tweaked by a GET variable
		//Init the default values
		$camsPerTR=2;
		$camnamesToShow=array();
		$groupnamesToShow=array();

		//Then see if any GET values were given and if so & valid, override the defaults
		$perRowGET=$_GET['perRow'];
		$cameraNameGET=$_GET['cameraName'];
		$groupNameGET=$_GET['groupName'];
		if (is_array($cameraNameGET))
		{
			//multiple values selected so $cameraNameGET is an array of strings
			foreach ( $cameraNameGET as $camName)
			{
				if (cameraNameIsValid($camName))
        	                {
	                                $camnamesToShow[]=$camName;
                	        }
				else
				{
					dieWithMessage("You requested to view an invalid camera name, camera names only contain letters or numbers");
				}

			}
		}
		else
		{

			//just one value selected so $cameraNameGET is a string not an array
			if (  (is_null($cameraNameGET)==false) && (strlen($cameraNameGET)>0)  )
			{
				if (cameraNameIsValid($cameraNameGET))
				{
					$camnamesToShow[]=$cameraNameGET;
				}
				else
				{
					 dieWithMessage("You requested to view an invalid camera name, camera names only contain letters or numbers");
				}
			}
		}

		if (is_array($groupNameGET))
                {
                        //multiple values selected so $groupNameGET is an array of strings
                        foreach ($groupNameGET as $groupName)
                        {
                                if (groupNameIsValid($groupName))
                                {
                                        $groupnamesToShow[]=$groupName;
                                }
                                else
                                {
                                        dieWithMessage("You requested to view an invalid group name, group names only contain letters or numbers");
                                }

                        }
                }
                else
                {
                        //just one value selected so $groupNameGET is a string not an array
                        
  			if (  (is_null($groupNameGET)==false) && (strlen($groupNameGET)>0)  )
			{
				if (groupNameIsValid($groupNameGET))
                        	{
                                	$groupnamesToShow[]=$groupNameGET; 
                        	}
				else
				{
					   dieWithMessage("You requested to view an invalid group name, group names only contain letters or numbers");
				}
			}
                }

		if (onlyContainsNumbers(NULL,NULL,NULL,$perRowGET))
		{
			$camsPerTR=$perRowGET;		
		}



	$globalconffile = fopen($GLOBALCONFFILEPATH, "r") or die("Unable to open global config file");
	$lineno=0;
	while(!feof($globalconffile)) 
	{
		$lineno++;
  		$line=fgets($globalconffile);
		if ( ($line[0]=="\n") || ($line[0]=="#") || ($line[0]=="") )
		{
			//blank line or comment line or blank line at EOF
			continue;
		}
		$elements=explode(" ",$line);
		if (sizeof($elements)!=2)
		{
			exit("Syntax error in ".$GLOBALCONFFILEPATH." on line ".$lineno.". Each line must be in format <b>keyword</b> <i>value</i> but found ".$line);
		}

		else if ($elements[0]=="allownewfilefromweb")
		{
			$elements[1]=strtolower($elements[1]);
			if ($elements[1]=="true\n")
			{
				$allownewfilefromweb=true;
			}
			else
			{
				$allownewfilefromweb=false;

			}
		}
		else if ($elements[0]=="cameraconf")
		{
			$elements[1]=stripBackslashN($elements[1]);
			$cameraconffilepath=$elements[1];
		}
	}
	fclose($globalconffile);

	if  ($cameraconffilepath=="")
	{
		exit("Syntax error in ".$GLOBAlCONFFILEAPTH." unable to find mandatory value for  <b>cameraconf</b>");
	}


	//read camera conf and parse into array
	$cameraconffile = fopen($cameraconffilepath, "r") or die("Unable to open camera config file ".$cameraconffilepath);
        $lineno=0;
	$allCameras=array(); //indexed array of all Camera obj, in groups and not
	$nullGroupCameras=array(); //indexed array of Camera objs which are not in any group
	$groupsByName=array(); //assoc array of group names, each ele is indexed array of Camera objs
        while(!feof($cameraconffile))
        {
                $lineno++;
                $line=fgets($cameraconffile);
		if ( ($line[0]=="\n") || ($line[0]=="#") || ($line[0]=="") )
                {
                        //blank line or comment line or blank line at EOF
                        continue;
                }
                $elements=preg_split('/\s+/', $line);
		if (sizeof($elements) < 5) //at time of writing there are 6 mandatory params (more maybe added later), we only need to read upto 5 here.
		{
			exit("Syntax error in ".$cameraconffilepath." on line ".$lineno." Too few parameters");
		}
		$camera=new Camera($elements);
		if ($camera->initCheck()) 
		{
			//we have a valid camera obj
			if ($camera->IsMirroringRequired())
			{
				$allCameras[]=$camera;
				if ($camera->GetGroupName()==NULL)
				{
					//camera has no group so put it in $nullGroupCameras
					$nullGroupCameras[]=$camera;
				}
				else
				{
					//camera has a group so put it in $groupsByName
					if (array_key_exists($camera->GetGroupName(),$groupsByName)==false)                
			                {
                        			$groupsByName[$camera->GetGroupName()]=array();
                			}

			                ($groupsByName[$camera->GetGroupName()])[]=$camera;

				}
			}
		}
		else
		{
			echo("<p>ERROR: Camera->InitCheck failed reading line {$lineno} from camera config file. This camera will not be displayed</p>");
		}
	}
	fclose($cameraconffile);
?>
<html>
<head>
<link rel="icon" type="image/png" href="bbsimple.png">
<title>CCTV Cameras Live (BigBrother)</title>
<style>

p {
	font-family: Arial, Verdana;
}

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
}

#cameragrid {
	background-color:#484848;
}

</style>


</head>
<script language=JavaScript>

var msgareastring = "";
var groupNotFound = false;
var cameraNotFound = false;


var hup_xhr = new XMLHttpRequest();


function detectBrowser()
{
	//Display a msg if browser is not one of the following:
	//Safari Mac OS X
	//Safari iOS
	//MS Edge
	//Chrome Android
	
	var supportedbrowser=false;
	
	var ua=navigator.userAgent;
	ua=ua.toLowerCase();

	//need to do ios first as it will also id as mac os 
	if ( (ua.indexOf("safari")>0) && ((ua.indexOf("ios")>0) || (ua.indexOf("iphone")>0) || (ua.indexOf("ipad")>0))  )
        {
		if ( (ua.indexOf("chrome")<0) && (ua.indexOf("vivaldi")<0) )
		{
                	//Safari on iOS
                	supportedbrowser=true
		}
        }
	else  if ( (ua.indexOf("safari")>0) && ((ua.indexOf("mac")>0) || (ua.indexOf("os x")>0) || (ua.indexOf("os 10")>0)) )
        {
		if ( (ua.indexOf("chrome")<0) && (ua.indexOf("vivaldi")<0) )
		{
                	//Safari on Mac OS X
                	supportedbrowser=true
		}
        }


	if ( (ua.indexOf("windows")>0) && (ua.indexOf("edge")>0) )
        {
                //MS Edge
                supportedbrowser=true
        }


        if ( (ua.indexOf("chrome")>0) && (ua.indexOf("android")>0) )
        {
                //Chrome on Android
                supportedbrowser=true
        }


	var msg="<p><b><img src=i-red.png width=32 height=32 align=middle valign=middle>The browser you are using may not support HLS video</b></p>";

	if (supportedbrowser==false)
	{
		//Disabled as now using video.js all browsers should work
		//appendMsg(msg);
	}
}	

function clearMsg()
{
	msgareastring="";
	displayPendingMessages();
}


function appendMsg(msg)
{
	msgareastring=msgareastring+"<td>"+msg+"</td>";
	displayPendingMessages();
}

function clobberMsg(msg)
{
	msgareastring="<td>"+msg+"</td>";
	displayPendingMessages();
}
function displayPendingMessages()
{
	var msgarea=document.getElementById("msgarea");
        msgarea.innerHTML="<table cellspacing=5 cellpadding=0 border=0><tr>"+msgareastring+"</tr></table>";
	var clearmsgbutton=document.getElementById("clearmsgbutton");
	
	//emulate a static var using function property (funcs are objs)
	if (clearmsgbutton.onmouseover!=null)
	{
		displayPendingMessages.mouseOverHandler= clearmsgbutton.onmouseover;
	}

	if (msgareastring=="")
	{
		clearmsgbutton.onmouseover=null;
		clearmsgbutton.style.opacity = "0.3";
		clearmsgbutton.style.cursor="default";

	}
	else
	{
		clearmsgbutton.onmouseover=displayPendingMessages.mouseOverHandler;
                clearmsgbutton.style.opacity = "1.0";
	        clearmsgbutton.style.cursor="pointer";
	}
}

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

function handleHUPRequest()
{
	var hupok=confirm("This will terminate all current recording for all cameras and start new ones")

	if (hupok==true)
	{
		//OK button pressed
		clobberMsg("<p><img src=i-orange.png width=32 height=32 align=middle valign=middle>Processing please wait...</p>");
		var url = "org.bigbrothercctv.bigbrother.hup.php";
    		hup_xhr.open('GET',url,true);
    		hup_xhr.onreadystatechange=processHUPResponse; //callback when response comes
    		hup_xhr.send(null);
	}
	
}

function processHUPResponse()
{
    
    if (hup_xhr.status==418)
    {
       //PHP script will only return 418 if it needs to give an error msg to user
	 clobberMsg("<p><img src=i-red.png width=32 height=32 align=middle valign=middle>Error creating new recordings, error was: "+hup_xhr.responseText+"</p>");
    }
    else if (hup_xhr.status==201)
    {
       //PHP script will only return 201 if it has processed everyting OK. There is no response body and JavaScript is resposnsible for generating the OK msg to the user
       clobberMsg("<p><img src=i-green.png width=32 height=32 align=middle valign=middle>All recordings restarted</p>");    
    }
    else
    {
	clobberMsg("<p><img src=i-red.png width=32 height=32 align=middle valign=middle>Error creating new recordings, error was: "+hup_xhr.responseText+"</p>");       
    }

}

function showParamHelp()
{
	var msg="<p><b><img src=i-blue.png width=32 height=32 align=middle valign=middle>Optional parameters which can be passed to webpage using using ? at end of URL, separate parameters with &</b></p>";
	msg=msg+"<p>perRow=n (show n cameras per horizonal row)</p>";
	msg=msg+"<p>cameraName=xyz (show named cameras only, this parameter can be specified multiple times to display a selection of cameras, if specifying multiple times it must be written as cameraName[])</p>";
	msg=msg+"<p>groupName=xyz (show named group only, this parameter can be specified multiple times to display a selection of groups, if specifying mutliple times it must be written as groupName[])</p>";
	appendMsg(msg);
}

function onLoad()
{
        detectBrowser();
		displayPendingMessages();
}


</script>


<link href="video-js.css" rel="stylesheet">
<script src="video.js"></script>





<body onload='onLoad();' style="margin: 0px;">

<?php $formattedversion=number_format($BBVERSION,1);?>

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
			<!Param help button>
			<td class=toolbarbutton id=paramhelpbutton onclick='showParamHelp();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);'>
			<p><img src=questionmark-blue.png width=32 align=middle valign=middle>Show optional parameters</p></td>
			
		
		<?php
		
		
			//HUP Button
			 //only display UI for sending HUP if configured to do so
	                if ($allownewfilefromweb)
        	        {
				echo("<td class=toolbarbutton id=hupbutton onclick='handleHUPRequest();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);'>");
				echo("<p><img src=hup.png width=48 align=middle valign=middle>Start a new file for all current recordings</p></td>");
			}

		?>
			<!Clear msg button>
			<td class=toolbarbutton id=clearmsgbutton onclick='clearMsg();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);'>
			<p><img src=clearmsg.png width=48 align=middle valign=middle>Clear all messages</p></td>
			
		</tr>
		</table>
	   </td>
	</tr>
</table>

<br>

<center>

<!Start of camera output>
<?php 



	if ( (sizeof($camnamesToShow)==0)  &&  (sizeof($groupnamesToShow)==0) )
	{
		 set_error_handler("requiredIncludeFail");
		 echo("<!allcameras.php>");
		include("org.bigbrothercctv.bigbrother.cameraoutput_allcameras.php");
	}
	else if ((sizeof($camnamesToShow)>0)  &&  (sizeof($groupnamesToShow)>0))
	{
		   set_error_handler("requiredIncludeFail");
		   echo("<!namedcameras_namedgroups.phpp>");
		  include("org.bigbrothercctv.bigbrother.cameraoutput_namedcameras_namedgroups.php"); 
	} 
	else if ( (sizeof($camnamesToShow)==0)  &&  (sizeof($groupnamesToShow)>0) )
	{
		   set_error_handler("requiredIncludeFail");
		   echo("<!namedgroups.phpp>");
		  include("org.bigbrothercctv.bigbrother.cameraoutput_namedgroups.php"); 
	}
	else
	{
		   set_error_handler("requiredIncludeFail");
		   echo("<!namedcameras.phpp>");
		  include("org.bigbrothercctv.bigbrother.cameraoutput_namedcameras.php"); 
	}


?>
<!End of camera output>

</center>

<br><br>
<center>
<table cellspacing=0 cellpadding=0 border=0>
<tr>
	<td colspan=5><center><p>Powered by:</p></center></td>
</tr>
<tr>
	<td><img src=ffmpeg.jpg width=50></td>
	<td valign=center><p>FFMPEG</p></td>
	<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>

	<td><img src=bb.png width=100></td>
        <td valign=center><p>BigBrother</p></td>


</tr>
</table>
<table cellspacing=0 cellpadding=0 border=0>
<tr>
	<td align=center><font size=-1><p>BigBrother &copy; Copyright Andrew Wood 2016-<?php printCurrentYear();?>. Licensed under the GNU Public License 3</p></font></td>
</tr>
<tr>
        <td align=center><font size=-1><p>FFMPEG &copy; Copyright The FFMPEG Developers 2000-<?php printCurrentYear();?>. FFMPEG 
is a trademark of Fabrice Bellard</p></font></td>
</tr>

</table>
</center>



<script language=JavaScript>
 if (cameraNotFound)
 {
	appendMsg("<b><img src=i-orange.png width=32 height=32 align=middle valign=middle>You requsted a camera which is not defined for mirroring</b>");
 }

 if (groupNotFound)
 {
	appendMsg("<b><img src=i-orange.png width=32 height=32 align=middle valign=middle>You requested an non-existent group name, or there are no cameras in the group</b>");
 }
</script>



</body>
</html>



