<?php

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2025 Andrew Wood                                  #
#                                                                  #
# Camera class represents a camera as defined in the config file   #
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







class Camera
{

	//Camera variables
	var $name=NULL;
	var $url=NULL;
	var $groupname=NULL;
	var $mirror=NULL;
	var $initOK=false;

	//Methods
	function __construct($elements)
	{
		$len=sizeof($elements);
		if ($len< 5)
		{
			

			$this->initOK=false;
			return;
		}
		$this->name=$elements[0];
		$this->url=$elements[1];
		if ($elements[2]!="*")
		{

			$this->groupname=$elements[2];
		}
		$this->mirror=$elements[4];

		$this->initOK=true;
		

	}
	function initCheck()
	{
		return $this->initOK;
	}
	function IsMirroringRequired()
	{
		if ($this->mirror=="*")
		{
			return false;
		}
		else
		{
			return true;
		}
	}
	function GenerateHTML()
	{
		if ( ($this->IsMirroringRequired()==false) || $this->initOK==false)
		{
			return;
		}
	
		if (  (strlen($this->mirror)>2) && (substr($this->mirror,0,3)=="HLS")  )
		{
			echo("<video class='video-js vjs-default-skin' width=640 height=480 controls autoplay muted data-setup='{}'>");

    			echo("<source src='".$this->name.".m3u8' type='application/x-mpegURL'>");

			echo("</video>");
			
			echo("<p>".$this->name."</p>");
		}
		
	}

	function GetCameraName()
	{
		return $this->name;
	}
	function GetGroupName()
	{
		return $this->groupname;
	}
	function IsInGroup($grp)
	{
		if ($grp==$this->groupname)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

?>
