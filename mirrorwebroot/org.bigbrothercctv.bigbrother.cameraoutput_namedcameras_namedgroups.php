<?php

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016 Andrew Wood                                       #
#                                                                  #
# UI code to display named cameras & cameras in named groups       #
# defined for mirroring  as specified in an HTTP GET variable      #
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




	include("org.bigbrothercctv.bigbrother.cameraoutput_namedgroups.php");
	set_error_handler(NULL);

    	if (sizeof($camnamesToShow)>0)
        {
                echo("<table cellspacing=10 cellpadding=0 border=0 bordercolor=black bgcolor=gray style='margin:5px;'>");
                 $camsprinted=0;

        }



        foreach ($camnamesToShow as $camname)
        {
		$camDone=False;

		foreach ($groupnamesToShow as $groupName)
		{
			if (array_key_exists($groupName,$groupsByName))
			{
				$groupCameras=$groupsByName[$groupName];
			

				foreach($groupCameras as $candidateCam)
				{
					if ($candidateCam->GetCameraName()==$camname)
					{	
						$camDone=True;
					}
				}
			}

		}



		if ($camDone)
		{
			continue;
		}

		$camera=NULL;
                foreach ($allCameras as $candidateCam)
                {
                        if ($candidateCam->GetCameraName()==$camname)
                        {
                                $camera=$candidateCam;
                        }
                }

                if ($camera==NULL)
                {
                        echo("<script language=JavaScript>cameraNotFound=true;</script>");
                        continue;
                }


                if ($camsprinted==0)
                {
                        echo("<tr>");
                }
                echo("<td valign=top bgcolor=white width=640>");
                $camera->GenerateHTML();
                echo("</td>");

                $camsprinted++;

		if ($camsprinted==$camsPerTR)
                {
                        echo("</tr>");
                        echo("<tr><td colspan=".$camsPerTR." bgcolor=gray height=10 width=100%>&nbsp;</td></tr>");

                        $camsprinted=0;
                }


        }
        if (sizeof($camnamesToShow)>0)
        {
              echo("</table>");

        }

?>
