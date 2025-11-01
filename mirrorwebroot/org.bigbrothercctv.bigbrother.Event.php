<?php

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2025 Andrew Wood                                  #
#                                                                  #
# Event class represents an AI auto detected event such as a       #
# person or vehicle presence                                       #
#																   #
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







class Event
{

	//Event variables
	var $eventdate=NULL;
	var $eventtime=NULL;
	var $unixtimestamp=NULL;
	var $cameraname=NULL;
	var $typecode=NULL;
	var $initOK=false;

	//Methods
	function __construct($elements)
	{
		error_log('creating Event obj');
		
		$len=sizeof($elements);
		if ($len< 4)
		{
			$this->initOK=false;
			
			return;
		}
		
		
		$this->eventdate=$elements[0];
		$this->eventtime=$elements[1];
		$this->cameraname=$elements[2];
		$this->typecode=$elements[3];
		
		$datecomponents=preg_split('/-/', $this->eventdate);
		$timecomponents=preg_split('/:/', $this->eventtime);
		
		
		 
		if ( sizeof($datecomponents)!=3)
		{
			$this->initOK=false;
			
			return;
		}
		if ( sizeof($datecomponents)!=3)
		{
			$this->initOK=false;
			
			return;
		}
		
		
									//hr min sec mon day yr
		$this->unixtimestamp=mktime($timecomponents[0],$timecomponents[1],$timecomponents[2],$datecomponents[1],$datecomponents[2],$datecomponents[0]);
		error_log('Event generating unix time stamp:'.$this->unixtimestamp." from HH MM SS MON DAY YR".$timecomponents[0].' '.$timecomponents[1].' '.$timecomponents[2].' '.$datecomponents[1].' '.$datecomponents[2].' '.$datecomponents[0]);
		
		$this->initOK=true;


	}
	function initCheck()
	{
		return $this->initOK;
	}
	

	function GetCameraName()
	{
		return $this->cameraname;
	}
	function GetEventDate()
	{
		return $this->eventdate;
	}
	function GetEventTime()
	{
		return $this->eventtime;
	}
	function GetEventTimestamp()
	{
		return $this->unixtimestamp;
	}
	function GetTypeCode()
	{
		return $this->typecode;
	}
	function GetEventDesc()
	{
		if ($this->typecode=="P")
		{
			return "Person detected";
		}
		else if ($this->typecode=="V")
		{
			return "Vehicle detected";
		}
		else
		{
			return "Unknown event";
		}
	}
	
}

?>
