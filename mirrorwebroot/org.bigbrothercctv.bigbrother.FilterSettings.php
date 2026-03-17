<?php

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2026 Andrew Wood                                       #
#                                                                  #
# FilterSettings class represents the Event filter settings 	   #
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
# Museum Trust at www.communicationsmuseum.org.uk/donate           #
####################################################################







class FilterSettings
{

	// FilterSettings variables
	var $id="";
	var $filtercams=array();
	var $filtergroups=array();
	var $filtertimetype=NULL;
	var $filterdate=NULL;
	var $filtertime=NULL;
	var $valid=false;
	var $active=false;
	var $groupsByName=array();

	//Methods
	function __construct($id,$groupsByName)
	{
		$this->valid=false;
		$this->active=false;
		$this->id=$id;
		$this->groupsByName=$groupsByName;
	}
	function ValidCheck()
	{
		return $this->valid;
	}
	function ActiveCheck()
	{
		return $this->active;
	}

	function GetGroupForCamera($camname)
	{
		//return group name for $camname or null str if no group
		
		$groupnames=array_keys($this->groupsByName);
		$x=0;
		foreach ($this->groupsByName as $grp)
		{
			
			foreach ($grp as $camera)
			{
				
				if ($camera->GetCameraName()==$camname)
				{
					
					return $groupnames[$x];
				}
			}
			$x++;
		}
		return "";
	}



	function GetCameras()
	{
		return $this->filtercams;
	}
	function GetGroups()
	{
		return $this->filtergroups;
	}
	function GetFilterTimeType()
	{
		return $this->filtertimetype;
	}
	function GetDate()
	{
		return $this->filterdate;
	}
	function GetTime()
	{
		return $this->filtertime;
	}
	function AddCamera($cam)
	{
		$this->filtercams[]=$cam;
	}
	function AddGroup($grp)
	{
		$this->filtergroups[]=$grp;
	}
	function SetFilterTimeType($t)
	{
		$this->filtertimetype=$t;
	}
	function SetDate($d)
	{
		$this->filterdate=$d;
	}
	function SetTime($t)
	{
		$this->filtertime=$t;
	}
	function SetValid()
	{
		$this->valid=true;
	}
	function SetActive()
	{
		$this->active=true;
	}
	function PrintToJavascript()
	{
		if ($this->valid!=true)
		{
			return;
		}
		if ($this->active!=true)
		{
			return;
		}
		
		echo("<!Start of Javascript variable outpur from FilterSettings::PrintToJavascript()>");
		
		foreach ($this->filtercams as $cam)
		{	
			echo("<script language=Javascript>");
			echo("selectedcams.push('".$cam."');");
			echo("</script>");
		}
		
		foreach ($this->filtergroups as $group)
		{
			echo("<script language=Javascript>");
			echo("selectedgroups.push('".$group."');");
			echo("</script>");
		}
		
		if ($this->filtertimetype!=NULL)
		{
			echo("<script language=Javascript>");
			echo("filtertimetype='".$this->filtertimetype."';");
			echo("</script>");
		}
		if ($this->filtertime!=NULL)
		{
			echo("<script language=Javascript>");
			echo("timevalue='".$this->filtertime."';");
			echo("</script>");
			
		}
		if ($this->filterdate!=NULL)
		{
			echo("<script language=Javascript>");
			echo("datevalue='".$this->filterdate."';");
			echo("</script>");
			
		}
		echo("<!End of Javascript variable outpur from FilterSettings::PrintToJavascript()>");
	}
	
	function ShowEvent($logstr)
	{
		//check if this event log entry matches this filter and return true if it does otherwise false
		//$logstr will be in format YYYY-DD-MM HH:MM:SS CamName EVENTCODE 
		$components=explode(" ",$logstr);
		
		//print($this->id);
		
		if (count($this->filtercams)>0)
		{
			
			if (in_array($components[2],$this->filtercams)==false)
			{
				return false;
			}
		}
		
		
		if (count($this->filtergroups)>0)
		{
			
			$groupname=$this->GetGroupForCamera($components[2]);
			if ($groupname=="")
			{
				//this cam has no group
				return false;
			}
			if (in_array($groupname,$this->filtergroups)==false)
			{
				return false;
			}
		}
		
		
		
		if ($this->filtertimetype=="24h")
		{
			$nowts=time();
			$twentyfourhourago=$nowts-86400;
			$eventts=strtotime($components[0]." ".$components[1]);
			if ($eventts<$twentyfourhourago)
			{
				return false;
			}
		}
		
		if ($this->filtertimetype=="specificdatetime")
		{
			$specifiedts=strtotime($this->filterdate." ".$this->filtertime);
			$startts=$specifiedts-3600; //1hr before
			$endts=$specifiedts+3600; //1hr after
			$eventts=strtotime($components[0]." ".$components[1]);
			if ( ($eventts<$endts) && ($eventts>$startts) )
			{
				
			}
			else
			{
				return false;
			}
		}
			
		
		
		return true;
	}
}

?>
