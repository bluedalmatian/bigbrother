<?php
ob_start();

include_once("./org.bigbrothercctv.bigbrother.bigbrotherd.php");
global $DAEMONPID;
global $ALLOWNEWFILEFROMWEB;



if ($ALLOWNEWFILEFROMWEB==False)
{
	do412();
	die();
}



$sigstatus=-100;
$sigstdout=Array();
exec( "../sendSIG -s HUP ".$DAEMONPID." 2>&1", $sigstdout, $sigstatus);

if ($sigstatus==0)
{

        do201();
}
else
{
        do418($sigstdout,$sigstatus);
}


function do418($sigstdout,$sigstatus)
{	
	header('HTTP/1.1 418 Kill Failed', true, 418);
	foreach ($sigstdout as $line)
	{
		echo("<p>");
		echo($line);
		echo("</p>");
	}
	echo("<p>Return code: ");
	echo($sigstatus);
	echo("</p>");
	ob_end_flush();
	
}


function do201()
{
        header('HTTP/1.1 201 Created', true, 201);
        ob_end_flush();

}

function do412()
{
        header('HTTP/1.1 412 Precondition Failed', true, 412);
                echo("<p>");
                echo("allownewfilefromweb is not set to True in config file");
                echo("</p>");
        ob_end_flush();

}







