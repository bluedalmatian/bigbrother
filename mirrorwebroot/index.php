

<?php
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
                $requiredincludefailerrmsg=$requiredincludefailerrmsg."<td valign=middle><p class=statusmsg><font face='Arial'>Error: A required file is missing or could not be read</font></p></td></tr></table></center><meta http-equiv='refresh' content='1'>";
		exit($requiredincludefailerrmsg);
	}

	set_error_handler("controlFileIncludeFail");
	include_once("./org.bigbrothercctv.bigbrother.bigbrotherd.php");


	if (time()-$STARTTIMESTAMP<10)
	{
		//allow 10 seconds after startup for mirror streams to get going before loading page
		controlFileIncludeFail(NULL, NULL, NULL, NULL);
	}

	set_error_handler("requiredIncludeFail");
	include_once("./org.bigbrothercctv.bigbrother.functions.php");
	set_error_handler(NULL); //clear custom error handler as it will cause a false failure, this is probably a bug in PHP???
	include_once("./org.bigbrothercctv.bigbrother.Camera.php");
	set_error_handler(NULL);

	//System wide knobs to be read from global config file
	$allownewfilefromweb=false;
	$cameraconffilepath="";
	$speak=false;


	//Master control for if AI events are shown
	$aieventmonitoring=true;


	//Webpage display specific knobs which can (optionally) be tweaked by a GET variable
		//Init the default values
		$minimalUI=false;
		$miniStatus=false;
		$camsPerTR=2;
		$camnamesToShow=array();
		$groupnamesToShow=array();

		//Then see if any GET values were given and if so & valid, override the defaults
		$perRowGET=$_GET['perRow'];
		$minimalUIGET=$_GET['minimalUI'];
		$cameraNameGET=$_GET['cameraName'];
		$groupNameGET=$_GET['groupName'];
		$miniStatusGET=$_GET['miniStatus'];
		$ignoreEventsGET=$_GET['ignoreEvents'];
		
		if ($ignoreEventsGET=="true")
		{
			$aieventmonitoring=false;
		}
		
		$minimalUIGET=strtolower($minimalUIGET);
		if ($minimalUIGET=="true")
		{
			$minimalUI=true;
		}
		
		$miniStatusGET=strtolower($miniStatusGET);
		if ( ($miniStatusGET=="true") && ($minimalUI) )
		{
			//miniStatus should only be effective when minimalUI enabled
			$miniStatus=true;
		}
		
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
?>
<html>
<head>
<link rel="icon" type="image/png" href="bbsimple.png">
<title>CCTV Cameras Live (BigBrother)</title>
<style>

p {
	font-family: Arial, Verdana;
}

p.small {
	font-family: Arial, Verdana;
	font-size: small;
}

p.xsmall {
	font-family: Arial, Verdana;
	font-size: x-small;
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

p.eventalert {
	color: orange;
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



<?php
	
	if ($speak)
	{
		echo("var speak=true;");
	}
	else
	{
		echo("var speak=false;");
	}

	if ($minimalUI)
	{
		echo("var minimalUI=true;");
	}
	else
	{
		echo("var minimalUI=false;");
	}
	
	if ($miniStatus)
	{
		echo("var miniStatus=true;");
	}
	else
	{
		echo("var miniStatus=false;");
	}
		
	echo("var perRow=".$camsPerTR.";");
	
	if ($aieventmonitoring)
	{
		echo("var aieventmonitoring=true");
	}
	else
	{
		echo("var aieventmonitoring=false");
	}
		
?>




var hup_xhr = new XMLHttpRequest();
var aipoll_xhr = new XMLHttpRequest();

var aieventsdetected=true;  //are there any events in log - determines if button enabled (updated by ajax call)
var aieventsviewed=true;  //has user viewed all events that have occured - determines if button orange (updated by ajax call)
var eventsnotified=[]; //array will hold events that this client session has been sent over ajax and which we have notified user of

var eventMsgsShown=0; //used to scroll display

var eventAudio = new Audio('event.mp3');

var speechSynth = window.speechSynthesis;


function detectBrowser()
{
	
	
	
	var ua=navigator.userAgent;
	ua=ua.toLowerCase();

	//need to do ios first as it will also id as mac os 
	if ( (ua.indexOf("safari")>0) && ((ua.indexOf("ios")>0) || (ua.indexOf("iphone")>0) || (ua.indexOf("ipad")>0))  )
     {
		if ( (ua.indexOf("chrome")<0) && (ua.indexOf("vivaldi")<0) )
		{
                	//Safari on iOS
                
		}
    }
	else  if ( (ua.indexOf("safari")>0) && ((ua.indexOf("mac")>0) || (ua.indexOf("os x")>0) || (ua.indexOf("os 10")>0)) )
    {
		if ( (ua.indexOf("chrome")<0) && (ua.indexOf("vivaldi")<0) )
		{
                	//Safari on Mac OS X
                	
		}
    }


	if ( (ua.indexOf("windows")>0) && (ua.indexOf("edge")>0) )
        {
                //MS Edge
              
        }


    if ( (ua.indexOf("chrome")>0) && (ua.indexOf("android")>0) )
       {
                //Chrome on Android
               
       }

	  if ( (ua.indexOf("firefox")>0) && (ua.indexOf("gecko")>0) )
        {
                //Firefox
				
					var body=document.getElementById("body");
					body.style.background="black";
					body.innerHTML="<center><h2><font color=white face='arial'>Firefox browser is not supported. It is recommended to use Chromium, Chrome, Edge or Safari</h2></center>";
					aieventmonitoring=false;
  
        }

}	


function clearMsg()
{
	msgareastring="";
	displayPendingMessages();
}


function appendMsg(msg)
{
	
	if (miniStatus)
	{
		var msgareadiv=document.getElementById("minimsgarea");
		msgareadiv.innerHTML=msg;
		
		var msgps=document.getElementsByClassName("statusmsg");
		var msgimgs=document.getElementsByClassName("statusicon");
		
		for (var i=0; i <msgps.length; i++)
		{
				msgps[i].style.fontSize="x-small";
				
		}
			
		for (var i=0; i <msgimgs.length; i++)
		{
				msgimgs[i].style.height="16px";
				msgimgs[i].style.width="20px";
				
		}
		
		
		return;
	}
	
	msgareastring=msgareastring+"<td>"+msg+"</td>";
	displayPendingMessages();
}

function clobberMsg(msg)
{
	if (miniStatus)
	{
		var msgareadiv=document.getElementById("minimsgarea");
		msgareadiv.innerHTML=msg;
		
		var msgps=document.getElementsByClassName("statusmsg");
		var msgimgs=document.getElementsByClassName("statusicon");
		
		for (var i=0; i <msgps.length; i++)
		{
				msgps[i].style.fontSize="x-small";
				
		}
			
		for (var i=0; i <msgimgs.length; i++)
		{
				msgimgs[i].style.height="16px";
				msgimgs[i].style.width="20px";
				
		}
		
		
		return;
	}
	
	eventmsgareastring="<td>"+msg+"</td>";
	displayPendingMessages();
}

function scrollEventMsg(msg)
{
	if (miniStatus)
	{
		var msgareadiv=document.getElementById("minimsgarea");
		msgareadiv.innerHTML=msg;
		var alertps=document.getElementsByClassName("eventalert");
		var alertimgs=document.getElementsByClassName("eventicon");
		
		
		for (var i=0; i <alertps.length; i++)
		{
				alertps[i].style.fontSize="x-small";
				
		}
			
		for (var i=0; i <alertimgs.length; i++)
		{
				alertimgs[i].style.height="16px";
				alertimgs[i].style.width="20px";
				
		}
		
		
		
		return;
	}
	
	var msgareadiv=document.getElementById("eventmsgarea");
	var tr1=document.getElementById("eventTR1");
	var tr2=document.getElementById("eventTR2");
	
	if (eventMsgsShown==0)
	{
		tr1.innerHTML="<td>"+msg+"</td>";
		eventMsgsShown=1;
	}
	else if (eventMsgsShown==1)
	{
		tr2.innerHTML=tr1.innerHTML;
		tr1.innerHTML="<td>"+msg+"</td>";
		eventMsgsShown=2;
	}
	else
	{
		tr2.innerHTML=tr1.innerHTML;
		tr1.innerHTML="<td>"+msg+"</td>";
		eventMsgsShown=2;
	}
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

function checkPendingEvents()
{
	//check if there are pending AI events and enable/disable button accordingly
	
	if(aieventmonitoring==false)
	{
		return;
	}
	
	var aieventbutton=document.getElementById("aieventbutton");
	
	
	//emulate a static var using function property (funcs are objs)
	if (aieventbutton.onmouseover!=null)
	{
		checkPendingEvents.mouseOverHandler= aieventbutton.onmouseover;
	}
	if (aieventbutton.onmouseout!=null)
	{
		checkPendingEvents.mouseOutHandler= aieventbutton.onmouseout;
	}
	
	if (aieventbutton.onclick!=null)
	{
		checkPendingEvents.clickHandler= aieventbutton.onclick;
	}
	
	aieventbutton.style.background="#000000";
	
	
	if (aieventsdetected)
	{
		aieventbutton.onmouseover=checkPendingEvents.mouseOverHandler;
		aieventbutton.onmouseout=checkPendingEvents.mouseOutHandler;
		aieventbutton.onclick=checkPendingEvents.clickHandler;
	    aieventbutton.style.opacity = "1.0";
		aieventbutton.style.cursor="pointer";

	}
	else
	{
		aieventbutton.onmouseover=null;
		aieventbutton.onmouseout=null;
		aieventbutton.onclick=null;
		aieventbutton.style.opacity = "0.3";
		aieventbutton.style.cursor="default";
		aieventbutton.style.background="#000000";
	}	
	
	if ( (aieventsviewed) && (aieventsdetected) )
	{
		aieventbutton.style.background="#000000";
	}
	
	if ( (aieventsviewed==false) && (aieventsdetected) )
	{
		aieventbutton.style.background="#da912b";
	}
	checkPendingEvents2();
}


function checkPendingEvents2()
{
	//check if there are pending AI events and enable/disable button accordingly
	
	if(aieventmonitoring==false)
	{
		return;
	}
	
	
	var aieventbutton=document.getElementById("aieventbuttonmini");
	
	
	//emulate a static var using function property (funcs are objs)
	if (aieventbutton.onmouseover!=null)
	{
		checkPendingEvents.mouseOverHandler= aieventbutton.onmouseover;
	}
	if (aieventbutton.onmouseout!=null)
	{
		checkPendingEvents.mouseOutHandler= aieventbutton.onmouseout;
	}
	
	if (aieventbutton.onclick!=null)
	{
		checkPendingEvents.clickHandler= aieventbutton.onclick;
	}
	
	aieventbutton.style.background="#000000";
	
	
	if (aieventsdetected)
	{
		aieventbutton.onmouseover=checkPendingEvents.mouseOverHandler;
		aieventbutton.onmouseout=checkPendingEvents.mouseOutHandler;
		aieventbutton.onclick=checkPendingEvents.clickHandler;
	    aieventbutton.style.opacity = "1.0";
		aieventbutton.style.cursor="pointer";

	}
	else
	{
		aieventbutton.onmouseover=null;
		aieventbutton.onmouseout=null;
		aieventbutton.onclick=null;
		aieventbutton.style.opacity = "0.3";
		aieventbutton.style.cursor="default";
		aieventbutton.style.background="#000000";
	}	
	
	if ( (aieventsviewed) && (aieventsdetected) )
	{
		aieventbutton.style.background="#000000";
	}
	
	if ( (aieventsviewed==false) && (aieventsdetected) )
	{
		aieventbutton.style.background="#da912b";
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
	if ( (obj.id=="aieventbutton") || (obj.id=="aieventbuttonmini") )
	{
		if ( (aieventsviewed) && (aieventsdetected) )
		{
			aieventbutton.style.background="#000000";
			aieventbuttonmini.style.background="#000000";
		}
		else if (aieventsdetected)
		{
			td.style.backgroundColor="#da912b";
		}
	}
	else
	{
        td.style.backgroundColor="#000000";
	}
}

function handleHUPRequest()
{
	var hupok=confirm("This will terminate all current recording for all cameras and start new ones")

	if (hupok==true)
	{
		//OK button pressed
		clobberMsg("<p class=statusmsg><img src=i-orange.png width=32 height=32 align=middle valign=middle class=statusicon>Processing please wait...</p>");
		var url = "org.bigbrothercctv.bigbrother.hup.php";
    		hup_xhr.open('GET',url,true);
    		hup_xhr.onreadystatechange=processHUPResponse; //callback when response comes
    		hup_xhr.send(null);
	}
	
}

function processHUPResponse()
{
    
	hup_xhr.responseText=hup_xhr.responseText.replace(/\n/g, '');
	
    if (hup_xhr.status==418)
    {
       //PHP script will only return 418 if it needs to give an error msg to user
	 clobberMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>Error creating new recordings, error was: "+hup_xhr.responseText+"</p>");
    }
    else if (hup_xhr.status==201)
    {
       //PHP script will only return 201 if it has processed everyting OK. There is no response body and JavaScript is resposnsible for generating the OK msg to the user
       clobberMsg("<p class=statusmsg><img src=i-green.png width=32 height=32 align=middle valign=middle class=statusicon>All recordings restarted</p>");    
    }
    else
    {
	clobberMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>Error creating new recordings, error was: "+hup_xhr.responseText+"</p>");       
    }

}


function isCameraShown(name)
{
	var cam=document.getElementById(name);
	if (cam==null)
	{
		return false;
	}
	else
	{
		return true;
	}
}


function checkAIEvents()
{
	if(aieventmonitoring==false)
	{
		return;
	}
	
	
			var url = "org.bigbrothercctv.bigbrother.pollEvents.php";
    		aipoll_xhr.open('GET',url,true);
    		aipoll_xhr.onreadystatechange=processAIEventResponse; //callback when response comes
    		aipoll_xhr.send(null);
}


function processAIEventResponse()
{
	
	if (aipoll_xhr.readyState!=4)
	{
		return;
	}
    
    if (aipoll_xhr.status==412)
    {
       //PHP script will only return 412 if it needs to give an error msg to user
		appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error checking for monitored events, server returned HTTP status: "+aipoll_xhr.status+" ("+aipoll_xhr.responseText+")</p>");   

		
		
    }
    else if (aipoll_xhr.status==204)
    {
       //PHP script will only return 204 if it has processed everyting OK. There is no response body ie no events
	   
	   //set flags and call checkPendingEvents() to update button state
	   aieventsdetected=false;
	   aieventsviewed=false;
	   checkPendingEvents();
       
    }
	else if (aipoll_xhr.status==200)
    {
       //PHP script will only return 200 if it has processed everyting OK and there are events in response body
	   
	   console.log("processing 200 result from Event AJAX");
	   
	   var events=JSON.parse(aipoll_xhr.responseText); //array of Event objects [0] is most recent
	   var foundindexes=[]; //indexes of events array (newly sent events) which are already in eventsnotified
	   
	   for (var x = 0; x < events.length; x++) 
	   {
			for (var y=0; y < eventsnotified.length; y++)
			{
				if (JSON.stringify(events[x])==JSON.stringify(eventsnotified[y]))
				{
                //found
                //record its index so we can ignore it later
				console.log("Event on server result list index "+x+" already exists in cache, ignoring");
                foundindexes.push(x);
  
				}
			}
	   }
	   
	   for (var x=events.length-1; x >=0; x--) //go through loop in reverse so we deal with newest event last
	   {
		
			if (foundindexes.includes(x)==false)
			{
				//new event
				
				console.log("Event on server list at index "+x+" is not in cache so adding, checking what action to take...");
				
				eventsnotified.unshift(events[x]); //copy to head of cache
				
				var cookietime=getCookie("eventschecked");
			
				console.log("cookie ts: "+cookietime);
				console.log("event ts: "+events[x].unixtimestamp);
				console.log("now ts: "+getTimestampNow());
				
				
				//dont give alert if this event is for a camera we are not currently showing
				if  (isCameraShown(events[x].cameraname)==false)
				{
					continue;
				}
				
				
				
				
				
					
				if (  (cookietime=="") || (cookietime<events[x].unixtimestamp) )
				{
					//cookie not set or cookie time older than this event
					//button orange
					//set flags and call checkPendingEvents() to update button state
					aieventsdetected=true;
					aieventsviewed=false;
					checkPendingEvents();
					console.log("cookie not set or cookie time older than this event, button orange");
				}
				else
				{
					//cookie newer than this event
					//button black
					//set flags and call checkPendingEvents() to update button state
					aieventsdetected=true;
					aieventsviewed=true;
					checkPendingEvents();
					console.log("cookie newer than this event, button black");
				}
				
				if  ((getTimestampNow())-(events[x].unixtimestamp)<10) 
				{
					//event less than 10 seconds old so give alert
					console.log("event less than 10 seconds old so give alert");
					var cam=document.getElementById(events[x].cameraname);
					if(cam!=null)
					{
						cam.style.border="5px solid orange";
						setTimeout(clearCameraHighlight,5000,cam);
						scrollEventMsg("<p class=eventalert>"+getEventIcon(events[x].typecode)+" "+events[x].eventdate+" "+events[x].eventtime+" "+events[x].cameraname+" "+eventDesc(events[x].typecode)+"</p>");
						eventAudio.play();
						if (speak)
						{
							var txttospeak=events[x].cameraname+","+eventDesc(events[x].typecode);
							var newUtter =new SpeechSynthesisUtterance(txttospeak);
							speechSynth.speak(newUtter);
						}
					}
					
				}
				
			}
	   }  
    }
	else if (aipoll_xhr.status==0)
    {
       //0 indicates timeout
    }
    else
    {
		appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error checking for monitored events, server returned HTTP status: "+aipoll_xhr.status+"</p>");   
		
		
    }

}

function getTimestampNow()
{
	//Javascript stupidly gives it in millisec so we need to convert to sec!!!!!!!!!!!
	return Math.round(Date.now() / 1000);
}

function eventDesc(code)
{
    if (code=="P") 
    {
        return "Person Detected";
    }
                                                    
    if (code=="V") 
    {
        return "Vehicle Detected";
    }
                                                    
    return "Unknown Event Detected";
}

function getEventIcon(code)
{
    if (code=="P") 
    {
        return "<img src=P.png height=24 width=48 align=top valign=top class=eventicon>";
    }
                                                    
    if (code=="V") 
    {
        return "<img src=V.png height=24 width=48 align=top valign=top class=eventicon>";
    }
                                                    
    return "<img src=U.png height=24 width=48 align=top valign=top class=eventicon>";
}

function hideEventLogOverlay()
{
	var overlay=document.getElementById("eventlogoverlay");
	overlay.style.display="none";
}

function showAIEventLog()
{
	setCookie("eventschecked",getTimestampNow(),365);	
	

	if (miniStatus)
	{
		var overlay=document.getElementById("eventlogoverlay");
		overlay.innerHTML="<table bgcolor=blue width=100% height=10 border=2 bordercolor=blue><tr><td width=*>&nbsp;</td><td bgcolor=gray width=20><center><p class=small onclick='hideEventLogOverlay();' style='cursor:pointer;'>X</p></center></td></tr>";
		overlay.innerHTML=overlay.innerHTML+"<tr><td colspan=2><iframe src='org.bigbrothercctv.bigbrother.showEvents.php' width=100% height=100%></iframe></td></tr>";
		overlay.innerHTML=overlay.innerHTML+"</table>";
		overlay.style.display="block";
		overlay.style.position="absolute";
		overlay.style.zIndex=3000;
		overlay.style.left="10px";
		overlay.style.top="10px";
		overlay.style.width=(window.innerWidth-50)+"px";
		overlay.style.height=(window.innerHeight-50)+"px";
	}
	else
	{
		window.open('org.bigbrothercctv.bigbrother.showEvents.php');
	}
	
	aieventsviewed=true;
	checkPendingEvents();
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


function getDateTime()
{
	var today = new Date();
	var dd = String(today.getDate()).padStart(2, '0');
	var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0 so we add 1
	var yyyy = today.getFullYear();
	var hr = String(today.getHours()).padStart(2, '0');
	var min = String(today.getMinutes()).padStart(2, '0');
	var sec = String(today.getSeconds()).padStart(2, '0');
	
	today = yyyy + '-' + dd + '-' + dd + ' '+ hr+ ':'+ min+ ':'+sec;
	return today;
}

function clearCameraHighlight(ele)
{
	if(ele!=null)
	{
		ele.style.border="none";
	}
}

function showParamHelp()
{
	var msg="<p><b><img src=i-blue.png width=32 height=32 align=middle valign=middle>Optional parameters which can be passed to webpage using using ? at end of URL, separate parameters with &</b></p>";
	msg=msg+"<p>perRow=n (show n cameras per horizonal row)</p>";
	msg=msg+"<p>cameraName=xyz (show named cameras only, this parameter can be specified multiple times to display a selection of cameras, if specifying multiple times it must be written as cameraName[])</p>";
	msg=msg+"<p>groupName=xyz (show named group only, this parameter can be specified multiple times to display a selection of groups, if specifying mutliple times it must be written as groupName[])</p>";
	msg=msg+"<p>minimalUI=true (hide toolbar, footer, group &amp; camera names to allow video display to take up all available screen space)</p>";
	msg=msg+"<p>miniStatus=true (show a mini status bar at bottom of screen when minimalUI is enabled)</p>";
	msg=msg+"<p>ignoreEvents=true (do not give notifcations of events, events will still be logged but not shown)</p>";
	msg=msg+"<p>&nbsp;</p>"
	msg=msg+"<p>Example: "+window.location.origin+"?cameraName[]=Camera1&cameraName[]=Camera2&minimalUI=true&miniStatus=true</p>"
	
	
	if (miniStatus)
		
		{
			var overlay=document.getElementById("helpoverlay");
			
		
			overlay.innerHTML="<table bgcolor=blue width=100% height=10 border=2 bordercolor=blue><tr><td width=*>&nbsp;</td><td bgcolor=gray width=20><center><p class=small onclick='hideHelpOverlay();' style='cursor:pointer;'>X</p></center></td></tr>";
			overlay.innerHTML=overlay.innerHTML+"<tr><td colspan=2>"+msg+"</td></tr>";
			overlay.innerHTML=overlay.innerHTML+"</table>";
			overlay.style.display="block";
			overlay.style.position="absolute";
			overlay.style.zIndex=3000;
			overlay.style.left="10px";
			overlay.style.top="10px";
			overlay.style.width=(window.innerWidt-20)+"px";
			overlay.style.height=(window.innerHeight-20)+"px";
			
			return;
		}
	
	
	appendMsg(msg);
}


function hideHelpOverlay()
{
	var overlay=document.getElementById("helpoverlay");
	overlay.style.display="none";
}

function pause(id,show)
{
	//given an element ID of a video show its pause overlay
	//show=true if we are pausing and false if playing
	var pauseoverlay=document.getElementById(id+'_pauseoverlay');
	var containerdiv=document.getElementById(id);
	
	
	if (show)
	{
		pauseoverlay.style.zIndex="2000";
		pauseoverlay.style.display="flex";
		pauseoverlay.style.position="absolute";
		pauseoverlay.style.top="35%";
		pauseoverlay.style.left="45%";
		containerdiv.appendChild(pauseoverlay);
	}
	else
	{
		pauseoverlay.style.display="none";
	}
}




function onLoad()
{
		detectBrowser();
	    checkPendingEvents();
		setInterval(checkAIEvents, 5000)
		displayPendingMessages();
		checkAIEvents();
		windowResized(true);
		
		
		//event listeners for video.js dont work, the events never fire, so we have to poll
		setInterval(checkPaused,1000);
		
		if(aieventmonitoring==false)
		{
			appendMsg("<p class=statusmsg><b><img src=i-orange.png width=32 height=32 align=middle valign=middle class=statusicon>Event notifications disabled</b></p>"); 
		}

}

function checkPaused()
{
	

	
	
		var videoeles=document.getElementsByClassName("video-js");
		for (var i=0; i < videoeles.length; i++)
		{
			var id=videoeles[i].getAttribute('id');
			//NOTE you would expect videoeles[i] to be the <video> tag with id=camname BUT IT IS NOT!!!!
			//video.js has altered the document tree at run time and wrapped the <video> in a <div>
			//the <div> now has the id=camname and the <video> is within the <div> with id=camname_html5_api
			
			//videoeles[i] is a <div> generated by video.js even though in HTML source it is a <video>!
			//the actual <video> has been moved down within the div and has an id of CAMERANAME_html5_api
			//this is contrary to the video.js documentation (what limited docs there is)
			
			var videotag=document.getElementById(id+"_html5_api");
	
			
			console.log("Checking if "+id+" is paused: "+videotag.paused);
			
			
			
			
			if (videotag.paused)
			{
				pause(id,true);
			}
			else
			{
				pause(id,false);
			}
			
	
	
		}
}


function windowResized(calledFromOnLoad=false)
{
	var videoeles=document.getElementsByClassName("video-js");
	var numCams=videoeles.length;
	
	var viewportW=window.innerWidth;
    var viewportH=window.innerHeight;
	
	if (miniStatus)
	{
		viewportH=viewportH-24;
	}
	
	var videoWidth=Math.ceil(viewportW/perRow)-(perRow*2);
	var numRows=Math.ceil((numCams/perRow));
	var videoHeight=Math.floor(viewportH/numRows)-(numRows*2);	
	
	if(numCams < perRow)
	{
		//If we have less cams to dsiplay than perRow value
		//we need to divide width by numCams instead
		videoWidth=Math.ceil(viewportW/numCams)-(numCams*2);
	}
	
		if (minimalUI)
		{
			//Hide toolbar
			var tbdiv=document.getElementById('toolbardiv');
			tbdiv.style.display='none';
			
			//Adjust size of video output
			var camgridtable=document.getElementById('cameragrid');
			camgridtable.style.width='100%';
			camgridtable.style.height='100%';
			camgridtable.style.borderSpacing="0";
			var videotds=document.getElementsByClassName("videotd");
		
			
			
			for (var i=0; i < videotds.length; i++)
			{
				videotds[i].style.width=videoWidth+"px";
				videotds[i].style.border = "1px solid #0732ff";
				videotds[i].style.height=videoHeight+"px";
				
			}
			
			
			for (var i=0; i < videoeles.length; i++)
			{
				videoeles[i].style.width=videoWidth+"px";
				videoeles[i].style.height=videoHeight+"px";
			}
			
			
			var groupnametrs=document.getElementsByClassName("groupnametr");
			for (var i=0; i <groupnametrs.length; i++)
			{
				groupnametrs[i].style.display='none';
				
			}


			var spacertrs=document.getElementsByClassName("spacertr");
			for (var i=0; i <spacertrs.length; i++)
			{
				spacertrs[i].style.display='none';
				
			}
			
			var camnameps=document.getElementsByClassName("cameraname");
			for (var i=0; i <camnameps.length; i++)
			{
				camnameps[i].style.display='none';
				
			}
			
			//Hide footer
			var footerdiv=document.getElementById('footer');
			footerdiv.style.display='none';
			
			
			
			if(miniStatus)
			{
				showMiniStatus();
			}
			
		}
		else
		{
			//Emulate cellspacing=10 HTML attribure on cameragrid
			var camgridtable=document.getElementById('cameragrid');
			camgridtable.style.borderSpacing="10px";
		}
	
	
		var ua=navigator.userAgent;
		ua=ua.toLowerCase();
		if ( (ua.indexOf("firefox")>0) && (ua.indexOf("gecko")>0) )
		{
			if (calledFromOnLoad==false)
			{
				//window.location.reload();
			}

		}


}

window.addEventListener('resize', function(event) {
    windowResized();
}, true);




function showMiniStatus()
{
	var statusbar=document.getElementById("ministatusbar");
	statusbar.style.display="block";
	statusbar.style.position="absolute";
	statusbar.style.top=(window.innerHeight-24)+"px";
	statusbar.style.width="100%";
	var cameragrid=document.getElementById("cameragrid");
	cameragrid.style.height=(window.innerHeight-24)+"px";
	
	if (aieventmonitoring)
	{
		var aibutton=document.getElementById("aieventbuttonmini");
		aibutton.style.margin="0px";
	}
	
}



</script>


<link href="video-js.css" rel="stylesheet">
<script src="video.js"></script>





<body onload='onLoad();' style="margin: 0px;" id=body>


<?php $formattedversion=number_format($BBVERSION,1);?>
<div id=toolbardiv>
<table cellspacing=0 cellpadding=5 border=0 id=toolbar width=100%>
	<tr>
	    <td>
		<table cellspacing=0 cellpadding=0 border=0 width=100%>
		 <tr>
                        <td>
				<p><font size=-1><img src=bb.png width=100 style="margin: 2px;" align=middle valign=middle>
				<?php  echo " BigBrother ".$formattedversion; $date = date('Y-m-d H:i:s'); echo " Page loaded: ".$date." (server time)";?> 
				</p></font>
				
				<div id=msgarea></div>
				<div id=eventmsgarea>
					<table cellpadding=2 cellspacing=0 border=0>
						<tr id=eventTR1><td></td></tr>
						<tr id=eventTR2><td></td></tr>
					</table>
				</div>
				
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
		<table cellspacing=4 cellpadding=0 border=0 id=toolbarbuttons>
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
			
			//AI Event log Button
			 //only display UI for showing AI events if configured to do so
	                if ($aieventmonitoring)
        	        {
						echo("<td class=toolbarbutton id=aieventbutton bgcolor=#da912b onclick='showAIEventLog();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);'>");
						echo("<p><img src=aieventicon.png width=48 align=middle valign=middle>Show detected events</p></td>");
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
</div>

<center>

<!Start of camera output>
<?php 



	if ( (sizeof($camnamesToShow)==0)  &&  (sizeof($groupnamesToShow)==0) )
	{
		 set_error_handler("requiredIncludeFail");
		include("org.bigbrothercctv.bigbrother.cameraoutput_allcameras.php");
	}
	else if ((sizeof($camnamesToShow)>0)  &&  (sizeof($groupnamesToShow)>0))
	{
		   set_error_handler("requiredIncludeFail");
		  include("org.bigbrothercctv.bigbrother.cameraoutput_namedcameras_namedgroups.php"); 
	} 
	else if ( (sizeof($camnamesToShow)==0)  &&  (sizeof($groupnamesToShow)>0) )
	{
		   set_error_handler("requiredIncludeFail");
		  include("org.bigbrothercctv.bigbrother.cameraoutput_namedgroups.php"); 
	}
	else
	{
		   set_error_handler("requiredIncludeFail");
		  include("org.bigbrothercctv.bigbrother.cameraoutput_namedcameras.php"); 
	}


?>
<!End of camera output>


</center>
<div id=footer>
<br><br>
<center>
<table cellspacing=0 cellpadding=0 border=0>
<tr>
	<td colspan=8><center><p>Powered by:</p></center></td>
</tr>
<tr>
	<td><img src=ffmpeg.jpg width=50></td>
	<td valign=center><p>FFMPEG</p></td>
	<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>

	<td><img src=bb.png width=100></td>
        <td valign=center><p>BigBrother</p></td>
	<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>

	<td><img src=opencv.png width=50></td>
        <td valign=center><p>OpenCV</p></td>
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
</div>




<div id=ministatusbar style="display:none;">
	<table cellspacing=0 cellpadding=0 border=0 width=100% height=24 background=minimalUIstatusbar.png>
		<tr width=100% height=100%>
			<td width=48><img src=bb.png height=20 width=40></td>
			<td align=left width=230><p class=small><?php  echo " BigBrother ".$formattedversion;?> &copy; Copyright 2016-<?php printCurrentYear();?> </td>
			
			
			
				<!Param help button>
					<td width=20 class=toolbarbutton id=paramhelpbuttonmini onclick='showParamHelp();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);' style='margin:0px;'>
					<center><p class=xsmall style='color:#00f3ff;'>?</p></center>
					</td>
			
				<td width=5>&nbsp;</td>
				
				
				<?php
						//HUP Button
						//only display UI for sending HUP if configured to do so
							if ($allownewfilefromweb)
							{
								echo("<td width=250 class=toolbarbutton id=hupbuttonmini onclick='handleHUPRequest();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);' style=\"margin:'0px';\">");
								echo("<p class=xsmall><img src=hup.png width=20 height=10 align=middle valign=middle>Start a new file for all current recordings</p></td>");
								echo("<td width=5>&nbsp;</td>");
							}
			
				?>
				
			
				<?php

				
	                if ($aieventmonitoring)
        	        {
						echo("<td width=150 class=toolbarbutton id=aieventbuttonmini bgcolor=#da912b onclick='showAIEventLog();' onmouseover='mouseOverButton(this);' onmouseout='mouseOutButton(this);' style=\"margin:'0px';\">");
						echo("<p class=xsmall><img src=aieventicon.png width=30 height=10 align=middle valign=middle>Show detected events</p></td>");
					}
					
					
					
				?>
			
			
			<td width=5>&nbsp;</td>
			<td><div id=minimsgarea></div></td>
		</tr>
	</table>
</div>


<div id=eventlogoverlay style="display:none;  background-color: #7c7c7b; padding:5px;">
<!Will contain contents of org.bigbrothercctv.bigbrother.showEvents.php when mini status bar is in use>
</div>
<div id=helpoverlay style="display:none;  background-color: #7c7c7b; padding:5px;">
<!Will contain help contents when mini status bar is in use>
</div>


<script language=JavaScript>
 if (cameraNotFound)
 {
	appendMsg("<p class=statusmsg><b><img src=i-orange.png width=32 height=32 align=middle valign=middle class=statusicon>You requested a camera which is not defined for mirroring</p></b>");
 }

 if (groupNotFound)
 {
	appendMsg("<p class=statusmsg><b><img src=i-orange.png width=32 height=32 align=middle valign=middle class=statusicon>You requested an non-existent group name, or there are no cameras in the group</p></b>");
 }
</script>

</body>
</html>




