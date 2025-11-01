<?php
ob_start();
//CONVENTION: Any variable name in $CAPITALS is declared in the mirror control file (org.bigbrothercctv.bigbrother.bigbrotherd.php)
	
//Return all Events in last 15 seconds, if none  that recent just return the most recent one with HTTP Status 200
//If no Events in log return empty body and HTTP Status 204 (No Content)


	function controlFileIncludeFail($errno, $errstr, $errfile, $errline)
	{
		
		$nodaemonerrmsg=$nodaemonerrmsg."<p class=statusmsg><font face=face='Arial','Verdana'>CCTV not available,BigBrother is not running</font></p>";
		$nodaemonerrmsg=$nodaemonerrmsg."<meta http-equiv='refresh' content='1'>";
		do412($nodaemonerrmsg);
		exit($nodaemonerrmsg);
	}
	function requiredIncludeFail($errno, $errstr, $errfile, $errline)
	{
		
        $requiredincludefailerrmsg=$requiredincludefailerrmsg."<p class=statusmsg><font face=face='Arial','Verdana'>Error: A required file is missing or could not be read</font></p>";
		$requiredincludefailerrmsg=$requiredincludefailerrmsg."<meta http-equiv='refresh' content='1'>";
		do412($requiredincludefailerrmsg);
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
	$output=readFileBackwards(100,"/usr/local/bigbrother/mirrorwebroot/org.bigbrothercctv.bigbrother.aieventlog.txt");
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
			do412("Syntax error in AI Event Log on line ".$lineno." Too few parameters");
			exit("Syntax error in AI Event Log on line ".$lineno." Too few parameters");
		}
		$event=new Event($elements);
		if ($event->initCheck()) 
		{
			//we have a valid Event obj
			$allEvents[]=$event;
				
		}
		else
		{
			do412("<p>ERROR: Init of Event failed</p>");
			exit("<p>ERROR: Init of Event failed</p>");
		}
	}

	if (sizeof($allEvents)==0)
	{
		do204();
		exit();
	}



	foreach ($allEvents as $event)
	{
		usort($allEvents, 'eventCompare'); //sort events in to most recent first
	}

	error_log('allEvents='.sizeof($allEvents));

	
	$notifyEvents=array();
	foreach ($allEvents as $event)
	{
		
		$now=time();
		$timedifference=$now - ($event->GetEventTimestamp());
		if ($timedifference < 15) //add event to list to send to client if less than 15 sec old
		{
			$notifyEvents[]=$event;
			error_log('adding event to notfyEvents:'.$event->GetEventTimestamp().' now:'.$now.' diff:'.$timedifference);
			
		}
		else
		{
			break; //once we get to first one > 15 sec old, we dont need to look at any more
		}
	}
	error_log('notifyEvents='.sizeof($notifyEvents));
	if (sizeof($notifyEvents)==0)
	{
			//no events in last 15 sec so add the most recent one
			$notifyEvents[]=$allEvents[0];
	}
	
	$jsonoutput=json_encode($notifyEvents);
	echo($jsonoutput);
	ob_end_flush();
?>
