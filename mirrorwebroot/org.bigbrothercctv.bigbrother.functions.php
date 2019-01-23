<?php
	function printCurrentYear()
	{
		$date = getdate();
		$year = $date['year'];
		print($year);
	}

	function groupNameIsValid($str)
	{
		if ( (doesNotContainSpaces($str)) &&  (onlyContainsLettersOrNumbers($str)) && (is_null($str)==false) && (strlen($str)>0) )
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	function cameraNameIsValid($str)
	{
		//currently criteria are same so we can use same code:
		return groupNameIsValid($str);
	}

	function dieWithMessage($str)
	{
		$errmsg="<center><table cellspacing=0 cellpadding=0 border=0><tr><td><img src=bb.png width=64 valign=middle></td>";
                $errmsg=$errmsg."<td valign=middle><p><font face=face='Arial','Verdana'>".$str."</font></p></td></tr></table></center>";
                exit($errmsg);

	}

	function doesNotContainSpaces($str)
	{
		if (preg_match("/ /",$str))
		{
			//space found
			return false;
		}
		else
		{
			return true;
		}
	}

	function onlyContainsLettersOrNumbers($str)
	{
		if (preg_match("/^[a-zA-Z0-9]+$/",$str))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function stripBackslashN($str)
	{
		$len=strlen($str);
		if ( ($str[$len-1])=="\n")
		{
			$str=substr($str,0,$len-1);
		}
		return $str;
	}

	function onlyContainsNumbers($digits,$min,$max,$str)
	{	
		if ( (strlen($str)!=$digits) && ($digits>0)  )
		{
			//if required num of $digits was specified check it matches length
			return false;
		}
	

		if ( preg_match("/^([0-9])+$/",$str)==false)
		{
			//non digit found		
			return false;
		}

		$num=intval($str);

		if (is_null($min)==false)
		{
			//$min numeric value was specified
			if ($num < $min)
			{
				return false;
			}
		}
		if (is_null($max)==false)
		{
			//$max numeric value was specified
			if ($num > $max)
			{
				return false;
			}
		}
		//passed all tests
		return true;
	}


?>
