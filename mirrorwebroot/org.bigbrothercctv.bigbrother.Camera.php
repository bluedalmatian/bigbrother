<?php

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2026 Andrew Wood                                  #
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
	
	function checkIfPTZ($filename)
	{
		//Check if an entry for $camname exists in conf file $filename, return true if so
		if ($filename=="")
		{
			return false;
		}
		
		$handle = fopen($filename, 'r');

		if ($handle === false) 
		{
			return false;
		}

		while (($line = fgets($handle)) !== false) 
		{
				$elements = explode(' ', $line);
				if (!empty($elements) && $elements[0] === $this->name) 
				{
					fclose($handle);
					return true;
				}
		}
		fclose($handle);
		return false;
	}


function generatePTZHTML()
{
    $camname = $this->name;

    echo("
    <div id='ptzcontrolbar_$camname' class='ptzcontrolbar'>
    <table cellspacing='0' cellpadding='0' border='0'>
        <tr>

            <td><img src='controlL.png' title='Pan Left' class='ptzcontrol'
                onmousedown=\"startMove('$camname','STARTL')\"
                onmouseup=\"stopMove('$camname')\"
                onmouseleave=\"stopMove('$camname')\"
                ontouchstart=\"startMove('$camname','STARTL')\"
                ontouchend=\"stopMove('$camname')\"></td>

            <td><img src='controlR.png' title='Pan Right' class='ptzcontrol'
                onmousedown=\"startMove('$camname','STARTR')\"
                onmouseup=\"stopMove('$camname')\"
                onmouseleave=\"stopMove('$camname')\"
                ontouchstart=\"startMove('$camname','STARTR')\"
                ontouchend=\"stopMove('$camname')\"></td>

            <td><img src='controlU.png' title='Tilt Up' class='ptzcontrol'
                onmousedown=\"startMove('$camname','STARTU')\"
                onmouseup=\"stopMove('$camname')\"
                onmouseleave=\"stopMove('$camname')\"
                ontouchstart=\"startMove('$camname','STARTU')\"
                ontouchend=\"stopMove('$camname')\"></td>

            <td><img src='controlD.png' title='Tilt Down' class='ptzcontrol'
                onmousedown=\"startMove('$camname','STARTD')\"
                onmouseup=\"stopMove('$camname')\"
                onmouseleave=\"stopMove('$camname')\"
                ontouchstart=\"startMove('$camname','STARTD')\"
                ontouchend=\"stopMove('$camname')\"></td>
				
				
			  <td><img src='controlUL.png' title='Pan Diagonal Up Left' class='ptzcontrol'
                onmousedown=\"startMove('$camname','STARTUL')\"
                onmouseup=\"stopMove('$camname')\"
                onmouseleave=\"stopMove('$camname')\"
                ontouchstart=\"startMove('$camname','STARTUL')\"
                ontouchend=\"stopMove('$camname')\"></td>
				
			  <td><img src='controlUR.png' title='Pan Diagonal Up Right' class='ptzcontrol'
                onmousedown=\"startMove('$camname','STARTUR')\"
                onmouseup=\"stopMove('$camname')\"
                onmouseleave=\"stopMove('$camname')\"
                ontouchstart=\"startMove('$camname','STARTUR')\"
                ontouchend=\"stopMove('$camname')\"></td>
				
			  <td><img src='controlDL.png' title='Pan Diagonal Down Left' class='ptzcontrol'
                onmousedown=\"startMove('$camname','STARTDL')\"
                onmouseup=\"stopMove('$camname')\"
                onmouseleave=\"stopMove('$camname')\"
                ontouchstart=\"startMove('$camname','STARTDL')\"
                ontouchend=\"stopMove('$camname')\"></td>
			
			  <td><img src='controlDR.png' title='Pan Diagonal Down Right' class='ptzcontrol'
                onmousedown=\"startMove('$camname','STARTDR')\"
                onmouseup=\"stopMove('$camname')\"
                onmouseleave=\"stopMove('$camname')\"
                ontouchstart=\"startMove('$camname','STARTDR')\"
                ontouchend=\"stopMove('$camname')\"></td>

			<td><img src='controlIN.png' title='Zoom In' class='ptzcontrol'
        				onmousedown=\"startZoom('$camname','IN')\"
        				onmouseup='stopZoom()'
        				onmouseleave='stopZoom()'
        				ontouchstart=\"startZoom('$camname','IN')\"
        				ontouchend='stopZoom()'></td>

			<td><img src='controlOUT.png' title='Zoom Out' class='ptzcontrol'
        				onmousedown=\"startZoom('$camname','OUT')\"
        				onmouseup='stopZoom()'
        				onmouseleave='stopZoom()'
        				ontouchstart=\"startZoom('$camname','OUT')\"
        				ontouchend='stopZoom()'></td>

          

            <td><img src='controlRESET.png' title='Reset to Home Position'
                class='ptzcontrol'
                onclick=\"doCameraControl('$camname','RESET');\"></td>

            <td><img src='controlSETHOME.png' title='Set current position as Home Position'
                class='ptzcontrol'
                onclick=\"doCameraControl('$camname','SETHOME');\"></td>
				
			<td valign=middle>
    <div class='ptzspeedgroup'>
        <span class='ptzcontroltext'>Slow</span>
        <div class='slider'>
            <input type='range'
                min='-2'
                max='2'
                step='0.5'
                value='0'
                id='{$camname}_ptzspeedselector'>
        </div>
        <span class='ptzcontroltext'>Fast</span>
    </div>
</td>
			
        </tr>
    </table>
    </div>");
}

	
	function GenerateHTML()
	{
		if ( ($this->IsMirroringRequired()==false) || $this->initOK==false)
		{
			return;
		}
	
		if (  (strlen($this->mirror)>2) && (substr($this->mirror,0,3)=="HLS")  )
		{
			
			echo("<div class='player-container' id='".$this->name."_playercontainer'>");
				echo("<div id=".$this->name."_pauseoverlay style=\"display:none; position:relative; background-image: url(pausedOverlayBackground.png);\"><img src=pausedOverlay.gif width=64 height=64></div>");
					echo("<video class='video-js vjs-default-skin' width=640 height=480 controls autoplay muted data-setup='{\"liveui\": true, \"html5\": {\"vhs\": {\"lowLatencyMode\": true, \"liveSyncDuration\": 0.5, \"liveMaxLatencyDuration\": 1.5}}, \"userActions\": {\"click\": false}}' id=".$this->name.">");
	
							echo("<source src='".$this->name.".m3u8' type='application/x-mpegURL'>");
				
					echo("</video>");
			echo("</div>");
			
			
			echo("<p class=cameraname>".$this->name."</p>");
			echo("<script>videotype_extn['".$this->name."']='m3u8';</script>");
			echo("<script>videotype_mime['".$this->name."']='application/x-mpegURL';</script>");
			
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
