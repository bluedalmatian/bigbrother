<?php


####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2026 Andrew Wood                                  #
#                                                                  #
#  UI back end code to handle PTZ AJAX request                     #
#                                                                  #
#                                                                  #
# www.bigbrothercctv.org                                           #
#                                                                  #
# Licensed under the GNU Public License v 3                        #
# The full license can be read at www.gnu.org/licenses/gpl-3.0.txt #
# and is included in the License.txt file included with this       #
# software.                                                        #
#                                                                  #
# BigBrother is free open source software but if you find it       #
# useful please consider making a donation to the Communications   #
# Museum Trust at www.comms.org.uk/donate                          #
####################################################################


ob_start();
//CONVENTION: Any variable name in $CAPITALS is declared in the mirror control file (org.bigbrothercctv.bigbrother.bigbrotherd.php)


/*
JSON error codes returned in JSON body of HTTP 422 response when an error occurs
================================================================================
JSON returned in this format:
{
    "code": XXX,
    "description": "Error message which can be shown to user"
}

Code      Meaning
----------------------------------------
1		cameraName or command GET parameter errror
2		ONVIF error
3		General error
4		socket error
12		command not recognised
13		no PTZ config for specified camera
14		DNS failure resolving camera IP
15		cameras task queue full

It may also return HTTP 204 with no body if executed command OK
or it may return 200 with body "INFO XXX..." if SHOWFUNCS command called
*/






function controlFileIncludeFail($errno, $errstr, $errfile, $errline)
{
		
	$nodaemonerrmsg="<p class=statusmsg><font face=face='Arial','Verdana'>CCTV not available,BigBrother is not running</font></p>";
	$nodaemonerrmsg=$nodaemonerrmsg."<meta http-equiv='refresh' content='1'><script>window.location.reload();</script>";
	do418($nodaemonerrmsg);
	exit($nodaemonerrmsg);
}
function requiredIncludeFail($errno, $errstr, $errfile, $errline)
{
		
    $requiredincludefailerrmsg="<p class=statusmsg><font face=face='Arial','Verdana'>Error: A required file is missing or could not be read</font></p>";
	$requiredincludefailerrmsg=$requiredincludefailerrmsg."<meta http-equiv='refresh' content='1'>";
	do412($requiredincludefailerrmsg);
	exit($requiredincludefailerrmsg);
}

	set_error_handler("controlFileIncludeFail");
	include_once("./org.bigbrothercctv.bigbrother.bigbrotherd.php");

	set_error_handler("requiredIncludeFail");
	include_once("./org.bigbrothercctv.bigbrother.functions.php");
	set_error_handler(NULL); //clear custom error handler as it will cause a false failure, this is probably a bug in PHP???
	



global $DAEMONPID;





$server_address = '../org.bigbrothercctv.bigbrother.ptz.onvif.sock';





function do418($err)
{
        header('HTTP/1.1 418 Precondition Failed', true, 418);
               
                echo($err);
               
        ob_end_flush();

}

function do412($err)
{
        header('HTTP/1.1 412 Precondition Failed', true, 412);
               
                echo($err);
               
        ob_end_flush();

}


function do204()
{
        header('HTTP/1.1 204 No Content', true, 204);
               
        
               
        ob_end_flush();

}

function do422($errcode,$errmsg)
{
        header('HTTP/1.1 422 Unprocessable Content', true, 422);
             
			echo('{"code": '.$errcode.',"description": "'.$errmsg.'"}');
      
        ob_end_flush();

}

function do200($msg)
{
        header('HTTP/1.1 200 OK', true, 200);
             
			echo($msg);
      
        ob_end_flush();

}










$speedrequired=false;

if (  isset($_GET["command"]) && isset($_GET["cameraName"])  )
{
	$cmd=$_GET["command"];
	$camName=$_GET["cameraName"];
	
	if ( $cmd=="STARTL" || $cmd=="STARTR" || $cmd=="STARTU" || $cmd=="STARTD" || $cmd=="STARTUL" || $cmd=="STARTUR" || $cmd=="STARTDL" || $cmd=="STARTDR" || $cmd=="STARTIN" || $cmd=="STARTOUT" || $cmd=="IN" || $cmd=="OUT" || $cmd=="L" || $cmd=="R" || $cmd=="U" || $cmd=="D")
	{
		//Requires speed param
		$speedrequired=true;
		if (isset($_GET["speed"]) )
		{
			$speed=$_GET["speed"];
		}
		else
		{
			do422(1,"GET param error. You must specify speed on URL e.g: ?cameraName=camera1&command=L&speed=0");
			die("");
		}
	}
	
	
}
else
{
	do422(1,"GET param error. You must specify cameraName command and if needed, speed on URL e.g: ?cameraName=camera1&command=L&speed=0");
	die("");
}

if (cameraNameIsValid($camName)==false)
{
	
	do422(1,"Invalid camera name");
	die("");
}

if ($cmd!="KEEPMOVING" && $cmd!="L" && $cmd!="R" && $cmd!="U" && $cmd!="D" && $cmd!="IN" && $cmd!="OUT" && $cmd!="RESET" && $cmd!="SETHOME" && $cmd!="SHOWFUNCS" && $cmd!="STARTL" && $cmd!="STARTR" && $cmd!="STARTU" && $cmd!="STARTD" && $cmd!="STARTIN" && $cmd!="STARTOUT" && $cmd!="STARTUL" && $cmd!="STARTUR" && $cmd!="STARTDL" && $cmd!="STARTDR" && $cmd!="STOPALL")
{
	do422(1,"Invalid command");
	die("");
}

if ($speedrequired==true)
{
	if ($speed!="-2" && $speed!="-1.5" && $speed!="-1" && $speed!="-0.5" && $speed!="0" && $speed!="0.5" && $speed!="1" && $speed!="1.5" && $speed!="2")
	{
		do422(1,"Invalid speed");
		die("");
	}
}
else
{
	$speed="0"; //default as a speed is required on socket even if not needed for cmd
}

$sock = fsockopen("unix://" . $server_address, 0, $errno, $errstr);
if (!$sock) 
{
	do422(4,"Socket error: ".$errno." ".$errstr);
    die("");
   
}

fwrite($sock, $camName." ".$cmd." ".$speed); //socket format is fixed: "CAMNAME COMMAND SPEED", a speed positional value must be sent even if not needed
$response = fread($sock, 4096);
fclose($sock);

if (substr($response,0,4)=="INFO")
{
	do200($response);
	die("");
}

if (intval($response)==0)
{
	do204();
	die("");
}
else if (intval($response)==1)
{
	do422(1,"Response error. You must specify cameraName and command on URL e.g: ?cameraName=camera1&command=L");
	die("");
}
else if (intval($response)==2)
{
	do422(2,"ONVIF Error");
	die("");
}
else if (intval($response)==3)
{
	do422(3,"General error");
	die("");
}
else if (intval($response)==12)
{
       do422(12,"Command not supported by camera");
       die("");
}
else if (intval($response)==13)
{
    do422(13,"No PTZ config for this camera");
    die("");
}
else if (intval($response)==14)
{
    do422(14,"DNS lookup for this camera failed");
    die("");
}
else if (intval($response)==15)
{
    do422(15,"Camera task queue full");
    die("");
}



?>
