<?php
ob_start();
//ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);


//CONVENTION: Any variable name in $CAPITALS is declared in the mirror control file (org.bigbrothercctv.bigbrother.bigbrotherd.php)
	

	function controlFileIncludeFail($errno, $errstr, $errfile, $errline)
	{

				$nodaemonerrmsg="<body bgcolor=#000000><center><table cellspacing=0 cellpadding=0 border=0 height=100%><tr height=*><td colspan=2>&nbsp;</td></tr>";
                $nodaemonerrmsg=$nodaemonerrmsg."<tr><td><img src=bb.png width=100 valign=middle></td>";
                $nodaemonerrmsg=$nodaemonerrmsg."<td valign=middle><img src=transparent.png width=100% height=2><p class=statusmsg><h2><font color=#FFFFFF face='Arial'>CCTV not available, BigBrother is not running</font></h2></p></td></tr>";
                $nodaemonerrmsg=$nodaemonerrmsg."<tr height=*><td colspan=2>&nbsp;</td></tr></table></center>";
                $nodaemonerrmsg=$nodaemonerrmsg."<meta http-equiv='refresh' content='1'>";
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
	include_once("./org.bigbrothercctv.bigbrother.Camera.php");
	include_once("./org.bigbrothercctv.bigbrother.FilterSettings.php");
	set_error_handler(NULL);



global $DAEMONPID;




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
		else if ($elements[0]=="speakevents")
		{
			$elements[1]=strtolower($elements[1]);
			if ($elements[1]=="true\n")
			{
				$speak=true;
			}
			else
			{
				$speak=false;

			}
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
	}
	fclose($cameraconffile);

$getfilterconfig=new FilterSettings("GET",$groupsByName);
$cookiefilterconfig=new FilterSettings("COOKIE",$groupsByName);


function isCameraNameValid($name,$arr)
{
	if (in_array($name, $arr)) 
	{
		return true;
	}
	else
	{
		return false;
	}
}

function isGroupNameValid($name,$arr)
{
	
	if (in_array($name, $arr)) 
	{
		return true;
	}
	else
	{
		return false;
	}
}


function readFilterParamsGET()
{
	//read _GET vars and validate
	
	global $allCameras;
	global $groupsByName;
	global $getfilterconfig;
	$filtererrorflag=false;
	$filtererrorstr="";
	$nothingfound=true;
	
	//VALIDATE FILTER CAMERA NAMES
	if (    isset($_GET['filtercam'])         && $_GET['filtercam'] != '')
	{
		$nothingfound=false;
		$filtercamGET=$_GET['filtercam'];
		if (is_array($filtercamGET))
		{

			$cameraNames=array();
			foreach ($allCameras as $cam)
			{
				//$allCameras is array of Camera obj, extract the names as strings
				$cameraNames[]=$cam->GetCameraName();
			}
			
			
			foreach ($filtercamGET as $cam)
			{
				
				if (isCameraNameValid($cam,$cameraNames))
				{
					$getfilterconfig->AddCamera($cam);
				}
				else
				{
					$filtererrorflag=true;
					$filtererrorstr=$filtererrorstr." invalid camera name";
				}
			}
				
		}
		else
		{
			//one cam only
			if (isCameraNameValid($filtercamGET,$cameraNames))
			{
				$getfilterconfig->AddCamera($cam);
			}
			else
			{
				$filtererrorflag=true;
				$filtererrorstr=$filtererrorstr." invalid camera name";
			}
			
		}
	}
	
	//VALIDATE FILTER GROUP NAMES
	if (    isset($_GET['filtergroup'])         && $_GET['filtergroup'] != '')
	{
		$nothingfound=false;
		$filtergroupGET=$_GET['filtergroup'];
		if (is_array($filtergroupGET))
		{
			
			$groups=array_keys($groupsByName);
			foreach ($filtergroupGET as $group)
			{
				if (isGroupNameValid($group,$groups))
				{
					$getfilterconfig->AddGroup($group);
				}
				else
				{
					$filtererrorflag=true;
					$filtererrorstr=$filtererrorstr." invalid group name";
				}
			}
			
		}
		else
		{
			//one grp only
			
			
			$groups=array_keys($groupsByName);
			if (isGroupNameValid($group,$groups))
			{
				$getfilterconfig->AddGroup($group);
			}
			else
			{
				$filtererrorflag=true;
				$filtererrorstr=$filtererrorstr." invalid group name";
			}
			
		}
	}
	
	if (    isset($_GET['filtertimetype'])         && $_GET['filtertimetype'] != '')
	{
		$nothingfound=false;
		$filtertimetypeGET=$_GET['filtertimetype'];
		
		if ( ($filtertimetypeGET!="any") && ($filtertimetypeGET!="24h") && ($filtertimetypeGET!="specificdatetime") )
		{
			$filtererrorflag=true;
			$filtererrorstr=$filtererrorstr." invalid filtertimetype";
		}
		else
		{
			$getfilterconfig->SetFilterTimeType($filtertimetypeGET);
		}
		
		
		if ($filtertimetypeGET=="specificdatetime")
		{
			//date and time must be set and valid
			if (    isset($_GET['date'])    && ($_GET['date'] != '')   && (isDateFormat($_GET['date'])) )
			{
				
				$getfilterconfig->SetDate($_GET['date']);
			}	
			else
			{
				$filtererrorflag=true;
				$filtererrorstr=$filtererrorstr." no date specified";
			}
		
			if (    isset($_GET['time'])    && ($_GET['time'] != '') && (isHourMinuteFormat($_GET['time'])) )
			{
				
				$getfilterconfig->SetTime($_GET['time']);
			
			}
			else
			{
				$filtererrorflag=true;
				$filtererrorstr=$filtererrorstr." no time specified";
			}
		}
		
	}
	
	if ($filterrorflag)
	{
		exit($filtererrorstr);
	}
	else
	{
		if ($nothingfound==false)
		{
			$getfilterconfig->SetValid();
		}
	}

	

}




function readFilterParamsCOOKIE()
{
	//read _COOKIE vars and validate
	
	global $allCameras;
	global $groupsByName;
	global $cookiefilterconfig;
	$filtererrorflag=false;
	$filtererrorstr="";
	$nothingfound=true;
	
	//VALIDATE FILTER CAMERA NAMES
	if (    isset($_COOKIE['filterSelectedCams'])         && $_COOKIE['filterSelectedCams'] != '')
	{
		$nothingfound=false;
		$filtercamCOOKIE=$_COOKIE['filterSelectedCams'];
		$filtercamCOOKIE=explode("/",$filtercamCOOKIE);
		if (is_array($filtercamCOOKIE))
		{

			$cameraNames=array();
			foreach ($allCameras as $cam)
			{
				//$allCameras is array of Camera obj, extract the names as strings
				$cameraNames[]=$cam->GetCameraName();
			}
			
			
			foreach ($filtercamCOOKIE as $cam)
			{
				
				if (isCameraNameValid($cam,$cameraNames))
				{
					$cookiefilterconfig->AddCamera($cam);
				}
				else
				{
					$filtererrorflag=true;
					$filtererrorstr=$filtererrorstr." invalid camera name";
				}
			}
				
		}
		else
		{
			//one cam only
			if (isCameraNameValid($filtercamCOOKIE,$cameraNames))
			{
				$cookiefilterconfig->AddCamera($cam);
			}
			else
			{
				$filtererrorflag=true;
				$filtererrorstr=$filtererrorstr." invalid camera name";
			}
			
		}
	}
	
	//VALIDATE FILTER GROUP NAMES
	if (    isset($_COOKIE['filterSelectedGroups'])         && $_COOKIE['filterSelectedGroups'] != '')
	{
		$nothingfound=false;
		$filtergroupCOOKIE=$_COOKIE['filterSelectedGroups'];
		$filtergroupCOOKIE=explode("/",$filtergroupCOOKIE);
		if (is_array($filtergroupCOOKIE))
		{
			
			$groups=array_keys($groupsByName);
			foreach ($filtergroupCOOKIE as $group)
			{
				if (isGroupNameValid($group,$groups))
				{
					$cookiefilterconfig->AddGroup($group);
				}
				else
				{
					$filtererrorflag=true;
					$filtererrorstr=$filtererrorstr." invalid group name";
				}
			}
			
		}
		else
		{
			//one grp only
			
			
			$groups=array_keys($groupsByName);
			if (isGroupNameValid($group,$groups))
			{
				$cookiefilterconfig->AddGroup($group);
			}
			else
			{
				$filtererrorflag=true;
				$filtererrorstr=$filtererrorstr." invalid group name";
			}
			
		}
	}
	
	if (    isset($_COOKIE['filterTimeType'])         && $_COOKIE['filterTimeType'] != '')
	{
		$nothingfound=false;
		$filtertimetypeCOOKIE=$_COOKIE['filterTimeType'];
		
		if ( ($filtertimetypeCOOKIE!="any") && ($filtertimetypeCOOKIE!="24h") && ($filtertimetypeCOOKIE!="specificdatetime") )
		{
			$filtererrorflag=true;
			$filtererrorstr=$filtererrorstr." invalid filtertimetype";
		}
		else
		{
			$cookiefilterconfig->SetFilterTimeType($filtertimetypeCOOKIE);
		}
		
		
		if ($filtertimetypeCOOKIE=="specificdatetime")
		{
			//date and time must be set and valid
			if (    isset($_COOKIE['filterSelectedDate'])    && ($_COOKIE['filterSelectedDate'] != '')   && (isDateFormat($_COOKIE['filterSelectedDate'])) )
			{
				
				$cookiefilterconfig->SetDate($_COOKIE['filterSelectedDate']);
			}	
			else
			{
				$filtererrorflag=true;
				$filtererrorstr=$filtererrorstr." no date specified";
			}
		
			if (    isset($_COOKIE['filterSelectedTime'])    && ($_COOKIE['filterSelectedTime'] != '') && (isHourMinuteFormat($_COOKIE['filterSelectedTime'])) )
			{
				
				$cookiefilterconfig->SetTime($_COOKIE['filterSelectedTime']);
			
			}
			else
			{
				$filtererrorflag=true;
				$filtererrorstr=$filtererrorstr." no time specified";
			}
		}
		
	}
	
	if ($filterrorflag)
	{
		exit($filtererrorstr);
	}
	else
	{
		if ($nothingfound==false)
		{
			$cookiefilterconfig->SetValid();
		}
	}

	

}


?>
<script language=Javascript>
//Global vars to store filter criteria
var filtersvalid=true;
var datetimevalue="";
var datevalue="";
var timevalue="";
var filtertimetype="";
var selectedgroups=[];
var selectedcams=[];

</script>


<?php

readFilterParamsGET();
readFilterParamsCOOKIE();
if ($getfilterconfig->ValidCheck())
{
	$getfilterconfig->SetActive();
	
}
else if ($cookiefilterconfig->ValidCheck())
{
	$cookiefilterconfig->SetActive();
	
}
	



if ( ($cookiefilterconfig->ValidCheck()) && ($cookiefilterconfig->ActiveCheck()) )
{
	$cookiefilterconfig->PrintToJavascript();
}
if ( ($getfilterconfig->ValidCheck()) && ($getfilterconfig->ActiveCheck()) )
{
	$getfilterconfig->PrintToJavascript();
}

if (    isset($_GET['erase'])         && $_GET['erase'] != '')
{
	$erase=$_GET['erase'];
}

$erase=strtolower($erase ?? ''); //?? '' substitutes a null string if $_GET['erase'] is not set
if ($erase=="true")
{
	doErase();
}


function doErase()
{
	
	global $cookiefilterconfig;
	global $getfilterconfig;
	$activefilterconfig=NULL;
	
	if ( ($cookiefilterconfig->ValidCheck()) && ($cookiefilterconfig->ActiveCheck()) )
	{
			$activefilterconfig=$cookiefilterconfig;
	}
	if ( ($getfilterconfig->ValidCheck()) && ($getfilterconfig->ActiveCheck()) )
	{
		$activefilterconfig=$getfilterconfig;
	}
	
	
	
	//Erase everything
	if ($activefilterconfig==NULL)
	{
		$handle = fopen('org.bigbrothercctv.bigbrother.aieventlog.txt', 'w');
		if ($handle==false)
		{
			die("Error opening file\n");
		}
		ftruncate($handle, 0);
		fwrite($handle, "#THIS FILE IS GENERATED AUTOMATICALLY BY BIGBROTHER WHEN AI EVENT MONITORING IS ENABLED\n");
		fclose($handle);

		$files = glob("snapshots/*"); // get all file names
		error_log("showEvents delete all called");
		foreach($files as $file)
		{ 
			if(is_file($file)) 
			{
    			unlink($file); // delete file
			}
		}
	}
	else
	{
		//Erase according to filter
		error_log("showEvents delete filtered called");
                $allEvents=array();
                $elogfile = fopen("/usr/local/bigbrother/mirrorwebroot/org.bigbrothercctv.bigbrother.aieventlog.txt", "a+") or die("Unable to open event log file ");
                fseek($elogfile, 0);
                while(!feof($elogfile))
                {

                        $line=fgets($elogfile);
                        $allEvents[]=$line;
                }



                ftruncate($elogfile, 0);
                fwrite($elogfile, "#THIS FILE IS GENERATED AUTOMATICALLY BY BIGBROTHER WHEN AI EVENT MONITORING IS ENABLED\n");

                foreach ($allEvents as $line)
                {
                        if ( ($line[0]=="\n") || ($line[0]=="#") || ($line[0]=="") )
                        {
                                //blank line or comment line or blank line at EOF
                                continue;
                        }
                        if ($activefilterconfig->ShowEvent($line)==false)
                        {
                                //This event does not match the filter so we wont delete it, write it back to log
                                fwrite($elogfile,$line);
                        }
                        else
                        {
                                //This  event is being deleted from log so delete its snapshot too
                                //Format of $line is YYYY-MM-DD HH:MM:SS CamName EVENTCODE SNAPSHOTFILENAME.JPG
								$components=explode(" ",$line);
                                $path="snapshots/".$components[4];
								shell_exec("rm ".$path); //unlink() is not working here so using shell instead
                                
                        }
                }
                fclose($elogfile);

		
	}
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


.uipanel {
	background-color: #07119a;
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
		if (sizeof($elements) < 5) //at time of writing there are 5 mandatory params (more maybe added later), we only need to read upto 5 here.
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


$formattedversion=number_format($BBVERSION,1);
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
	td.style.backgroundColor="transparent";
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
	
	//at this point any URL GET params are already read, so check cookies for filter criteria, this function will return doing nothing if GET params were specified instead
	checkFilterCookies();
	
	var filterstr="";
	
	if (selectedcams.length>0)
	{
		filterstr=filterstr+" Showing Cameras:";
		for (x=0; x < selectedcams.length; x++)
		{
			filterstr=filterstr+" "+selectedcams[x];
		}
	}
	if (selectedgroups.length>0)
	{
		filterstr=filterstr+" Showing Groups:";
		for (x=0; x < selectedgroups.length; x++)
		{
			filterstr=filterstr+" "+selectedgroups[x];
		}
	}
	

		
	if (filtertimetype=="specificdatetime")
	{
		filterstr=filterstr+" Within 1 hour of "+datevalue+" "+timevalue;
	}
	
	if (filtertimetype=="24h")
	{
		filterstr=filterstr+" Within last 24 hours";
	}
	
	var filteralert=document.getElementById("filteralert");
	
	if (filterstr!="")
	{
		filteralert.innerHTML="FILTER APPLIED: "+filterstr+" only.";
	}
	
	
}


function checkFilterCookies()
{
	//Check the event filter cookies and set JS vars accordingly but ONLY if JS vars have not already been set by URL GET params, as GET params override any cookie settings
	//We can assume PHP has already validated them so we dont need to do that here
	if (selectedcams.length>0 || selectedgroups.length > 0 || filtertimetype!="")
	{
		console.log("filter criteria already set by URL GET parameters, ignoring cookies");
		console.log("validating GET parameters");
		return;
	}
	console.log("filter criteria not set by URL GET parameters, checking cookies");
	var selectedcamscookie=	getCookie("filterSelectedCams");
	var selectedgroupscookie=getCookie("filterSelectedGroups");
	
	if (selectedcamscookie.length>0)
	{
		selectedcams=selectedcamscookie.split("/");
	}
	if (selectedgroupscookie.length>0)
	{
		selectedgroups=selectedgroupscookie.split("/");
	}
	filtertimetype=getCookie("filterTimeType");
	datevalue=getCookie("filterSelectedDate");
	timevalue=getCookie("filterSelectedTime");
	console.log("read these values from cookies:"+filtertimetype+" "+datevalue+" "+timevalue+" "+selectedcamscookie+" "+selectedgroupscookie);
	console.log("validating cookie parameters");
	
}

function eraseLog()
{
	var alertstr="This will erase ALL events from the notification history, are you sure?";
	if (filtersvalid)
	{
		alertstr="This will erase the selected events from the notification history, are you sure?"		
	}
	
	
	var eraseok=confirm(alertstr);

	if (eraseok==true)
	{
			//OK button pressed
	
			var url = "org.bigbrothercctv.bigbrother.showEvents.php?erase=true";
			
			
			if (filtersvalid)
			{
				url=url+"&"
				var params=[];

				for (x=0; x < selectedcams.length; x++)
				{
					params.push("filtercam[]="+selectedcams[x]);
			
				}
				for (x=0; x < selectedgroups.length; x++)
				{
					params.push("filtergroup[]="+selectedgroups[x]);
			
				}
				if (filtertimetype!="")
				{
					params.push("filtertimetype="+filtertimetype);
			
					if (filtertimetype=="specificdatetime")
					{
						params.push("date="+datevalue);
						var timevalueencoded=timevalue[0]+timevalue[1]+"%3A"+timevalue[3]+timevalue[4];
						params.push("time="+timevalueencoded);
					}
				}
		
				for (x=0; x < params.length; x++)
				{
					url=url+params[x];
					if (x < (params.length-1))
					{
						url=url+"&";
					}
				}

			}
			
    		erase_xhr.open('GET',url,true);
    		erase_xhr.onreadystatechange=doReload; //callback when response comes
    		erase_xhr.send(null);
	}
			
	
}

function doReload()
{
	location.reload();
}


function toggleFilterPanel()
{
	var panel=document.getElementById('filterpanel');
	if (filterpanel.style.display == 'none')
	{
		filterpanel.style.display='inline';
	}
	else
	{
		filterpanel.style.display='none';
	} 
}


function applyFilterWithCookies()
{
	readFilterForm();
	if (filtersvalid)
	{
		var selectedcamsstr=""
		for (x=0; x < selectedcams.length; x++)
		{
			selectedcamsstr=selectedcamsstr+selectedcams[x];
			if (x<(selectedcams.length-1))
			{
					selectedcamsstr=selectedcamsstr+"/";
			}
		}
		if (selectedcamsstr!="")
		{
			setCookie("filterSelectedCams", selectedcamsstr, 365);
		}
		
		var selectedgroupsstr=""
		for (x=0; x < selectedgroups.length; x++)
		{
			selectedgroupsstr=selectedgroupsstr+selectedgroups[x];
			if (x<(selectedgroups.length-1))
			{
					selectedgroupsstr=selectedgroupsstr+"/";
			}
		}
		if (selectedgroupsstr!="")
		{
			setCookie("filterSelectedGroups", selectedgroupsstr, 365);
		}
		
		setCookie("filterTimeType", filtertimetype, 365);
		if (datevalue!="" && timevalue!="")
		{
			setCookie("filterSelectedTime", timevalue, 365);
			setCookie("filterSelectedDate", datevalue, 365);
		}
	
		var url=window.location.origin+window.location.pathname;
		window.location.replace(url);
	}
	
}

function applyFilter()
{
	readFilterForm();
	if (filtersvalid)
	{
		redirectToFilter();
	}
}


function readFilterForm()
{
	
	daetimevalue="";
	datevalue="";
	timevalue="";
	filtertime="";
	selectedgroups=[];
	selectedcams=[];
	
	filtersvalid=true;
	var camlist=document.getElementById("cameraselect");
	for (x=0; x < camlist.length;x++)
	{
		if (camlist[x].selected)
		{
			selectedcams.push(camlist[x].value);
		}
	}
	
	
	
	var grouplist=document.getElementById("groupselect");
	for (x=0; x < grouplist.length;x++)
	{
		if (grouplist[x].selected)
		{
			selectedgroups.push(grouplist[x].value);
		}
	}
	
	
	var filtertime=document.getElementsByName("timefilter");
	for (x=0; x < filtertime.length;x++)
	{
		if (filtertime[x].checked)
		{
			filtertimetype=filtertime[x].value;
		}
	}
	
	
	if (filtertimetype=="specificdatetime")
	{
		datetimevalue=document.getElementById("timeselector").value;
		//datetimevalue is string in format YYYY-MM-DDThh:mm
		var index=datetimevalue.search(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/); //index will be 0 if string is required format
		if (index==0)
		{
			var components=datetimevalue.split("T");
			datevalue=components[0];
			timevalue=components[1];
		}
		else
		{
			filtersvalid=false;
			alert("You have selected to filter by a specific date / time but have not selected a date or time");
		}
		
		
		
	}
	
}

function redirectToFilter()
{
	var params=[];
	if (filtersvalid)
	{
		var url=window.location.origin+window.location.pathname+"?";
		for (x=0; x < selectedcams.length; x++)
		{
			params.push("filtercam[]="+selectedcams[x]);
			
		}
		for (x=0; x < selectedgroups.length; x++)
		{
			params.push("filtergroup[]="+selectedgroups[x]);
			
		}
		if (filtertimetype!="")
		{
			params.push("filtertimetype="+filtertimetype);
			
			if (filtertimetype=="specificdatetime")
			{
				params.push("date="+datevalue);
				var timevalueencoded=timevalue[0]+timevalue[1]+"%3A"+timevalue[3]+timevalue[4];
				params.push("time="+timevalueencoded);
			}
		}
		
		for (x=0; x < params.length; x++)
		{
			url=url+params[x];
			if (x < (params.length-1))
			{
				url=url+"&";
			}
		}
		
		window.location.replace(url);
	}
}

function removeFilters()
{
	
	datetimevalue="";
	datevalue="";
	timevalue="";
	filtertimetype="";
	selectedcams=[];
	selectedgroups=[];
	deleteCookie("filterSelectedCams");
	deleteCookie("filterSelectedGroups");
	deleteCookie("filterTimeType");
	deleteCookie("filterSelectedDate");
	deleteCookie("filterSelectedTime");
	var url=window.location.origin+window.location.pathname;
	window.location.replace(url);
}





function deleteCookie(name)
{
  const d = new Date();
  d.setTime(d.getTime() -100 );
  var expires = "expires="+ d.toUTCString();
  document.cookie = name + "=" + "" + ";" + expires + ";path=/";
}

function setCookie(name, value, days) 
{
	//set a cookie with name and value expring in days time
  const d = new Date();
  d.setTime(d.getTime() + (days*24*60*60*1000));
  var expires = "expires="+ d.toUTCString();
  document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

function getCookie(cname) 
{
 //given a cookie name return its value
  var name = cname + "=";
  var decodedCookie = decodeURIComponent(document.cookie);
  var ca = decodedCookie.split(';');
  for(var i = 0; i <ca.length; i++) 
  {
    var c = ca[i];
    while (c.charAt(0) == ' ') 
    {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) 
    {
      return c.substring(name.length, c.length);
    }
  }
  return "";
}

</script>



</head>



<body style="margin: 0px;" onload='onLoad();'>








<table cellspacing=0 cellpadding=0 border=0 id=toolbar width=100%>
	<tr>
	    <td colspan=2>
		<table cellspacing=0 cellpadding=0 border=0 width=100%>
		 	<tr>
                        	<td>
					<p><font size=-1><img src=bb.png width=100 style="margin: 2px;" align=middle valign=middle>
					<?php  echo " BigBrother ".$formattedversion; $date = date('Y-m-d H:i:s'); echo " Page loaded: ".$date." (server time)"; ?>.
					This page will reload every 5 minutes 
					</p></font><div id=msgarea></div>
				</td>
                	</tr>
		</table>
	   </td>
	</tr>
	<tr>
		<td>
			<table cellspacing=0 cellpadding=20 border=0>
				<tr>
					<td valign=middle><h2>Detected Event Notification History</h2></td>
					<td valign=top><p>This shows up to the last 1000 events</p></td>
					<td valign=top><p class=supersmall id=eventcount>No. of events: <?php echo(sizeof($allEvents));?></p></td>
				</tr>
			</table>
		</td>
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

					<!Refresh button>
                                        <td class=toolbarbutton id=refreshlogbutton onclick='window.location.reload();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);'>
                                                <p>
                                                        <img src=refresh.png width=48 align=middle valign=middle>Refresh
                                                </p>
                                        </td>


					<!Filter button>
                                        <td class=toolbarbutton id=filterlogbutton onclick='toggleFilterPanel();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);'>
                                                <p>
                                                        <img src=filter.png width=48 align=middle valign=middle>Filter Events
                                                </p>
                                        </td>


			
				</tr>
			</table>
	   	</td>
	</tr>

	 <tr height=3 width=100%>
                <td colspan=2>&nbsp;</td>
        </tr>

	<tr height=1 width=100%>
                <td colspan=2 bgcolor="#666565"></td>
	</tr>
	 <tr height=10 width=100%>
                <td colspan=2>&nbsp;</td>
        </tr>


</table>
<p class=supersmall id=filteralert style="color:red;"></p>

<div id=filterpanel style='display:none;'>
	<table cellspacing=0 cellpadding=20 border=0 class=uipanel style='margin:10px;'>
	<tr>
		<td colspan=4><p>Show only events for:</p></td></tr>



	<tr style='vertical-align: top;'>
		<td width=200>
			<fieldset>
                                 <legend><p>Cameras</p></legend>
				 
				<select name="cameras" id="cameraselect" multiple size=5>
					<?php
						foreach ($allCameras as $camera)
						{
							echo("<option value='".$camera->GetCameraName()."'>".$camera->GetCameraName()."</option>");
						}
					?>
  					 
				</select>
			</fieldset>

		</td>


		<td width=200>
		
			 <fieldset>
                                 <legend><p>Groups</p></legend>
				<select name="groups" id="groupselect" multiple size=5>
				
				<?php 
						$groups=array_keys($groupsByName);
						foreach ($groups as $group)
						{
						
							echo("<option value='".$group."'>".$group."</option>");
						}
				?>
                                          
                                </select>

                        </fieldset>


                </td>



		<td width=200>


			<fieldset>
 				 <legend><p>Time period:</p></legend>

  				<div>
					<p>
    					<input type="radio" id="any" name="timefilter" value="any" checked />
    					<label for="any">Any</label>
					</p>
  				</div>

  				<div>
					<p>
    					<input type="radio" id="24h" name="timefilter" value="24h" />
    					<label for="24h">Within last 24 hours</label>
					</p>
  				</div>


				<div>

					<p>
                                        <input type="radio" id="specificdatetime" name="timefilter" value="specificdatetime" />
                                        <label for="24h">Within 1 hour of:</label>
                                        </p>

					<input type="datetime-local" id="timeselector" name="timeselect" />

				</div>

			</fieldset>




                </td>
		<td>&nbsp;</td>

	</tr>

	<tr>
	   <td colspan=3 align=right>
		<table cellspacing=0 cellpadding=0 border=0>
                   <tr>
                	<!Clear button>
                	<td width=135 class=toolbarbutton id=clearfilterlogbutton onclick='removeFilters();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);'>
                  		<center><p>Clear Filters</p></center>
                	</td>

                	<td width=5>&nbsp;</td>

                	<!Apply button>
                	<td width=90 class=toolbarbutton id=applyfilterlogbutton  onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);' onclick='applyFilter();'>
                  		<center><p>Apply</p></center>
                	</td>
			
			<td width=5>&nbsp;</td>


			<!Save button>
                        <td width=180 class=toolbarbutton id=savefilterlogbutton onclick='applyFilterWithCookies();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);'>
                                <center><p><img src=star.png width=20 align=top>Remember &amp; Apply</p></center>
                        </td>



                   </tr>
        	</table>
	   </td>
	   <td>&nbsp;</td>
	</tr>


	<tr><td colspan=4>&nbsp;</td></tr>
	</table>

	<br>
</div>


<table cellpadding=0 cellspacing=0 border=1 id=eventtable width=100%>
<tr>
	<td width=100><p><b>Date</b></p></td>
	<td width=100><p><b>Time</b></p></td>
	<td><p><b>Camera</b></p></td>
	<td width=200><p><b>Event</b></p></td>
	<td width=*><p><b>Snapshot</b></p></td>
</tr>

<?php

	if ($getfilterconfig->ActiveCheck())
	{
		$activefilter=$getfilterconfig;
		
	}
	else if ($cookiefilterconfig->ActiveCheck())
	{
		$activefilter=$cookiefilterconfig;
	}
	else
	{
		$activefilter=NULL;
	}
	
	if (sizeof($allEvents)==0)
	{
		echo("<td colspan=5><center><p>No Events Detected</p></center></td>");
	}
	
	$n=0;
	foreach ($allEvents as $event)
	{
		$signature=$event->getEventDate()." ".$event->getEventTime()." ".$event->getCameraName()." ".$event->GetTypeCode();
		if ($activefilter!=NULL)
		{
			if ($activefilter->ShowEvent($signature)==false)
			{
				continue;
			}
		}
		
		
		echo("<tr>");
		
		$n++;
		
		
		echo("<td width=100>");
		echo("<p align=center>".$event->getEventDate()."</p>");
		echo("</td>");
		
		echo("<td width=100>");
		echo("<p align=center>".$event->getEventTime()."</p>");
		echo("</td>");
		
		echo("<td>");
		echo("<p align=center>".$event->getCameraName()."</p>");
		echo("</td>");
		
		echo("<td width=200>");
		
		echo("<p align=center><img src=".$event->GetTypeCode()."-white.png width=32 height=24 valign=middle></img>");
		echo("".$event->getEventDesc()."</p>");
		echo("</td>");

		echo("<td width=*>");
		$eventtimecompressed=str_replace(":","",$event->getEventTime());
		echo("<img src=snapshots/".$event->GetFilename()." width=100% height=400 loading=lazy class=snapshotimage");
		echo("</td>");
		echo("</tr>");		


	}
	
	echo("<script language=Javascript>var div=document.getElementById('eventcount'); div.innerHTML='No. of events: ".$n."';</script>");
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
