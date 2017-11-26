<?php

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016 Andrew Wood                                       #
#                                                                  #
# UI code to display cameras in named groups defined for mirroring #
# as specified in an HTTP GET variable                             #
#                                                                  #
# www.simple.org/bigbrother                                        #
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



	set_error_handler(NULL);
        $camsprinted=0;
        

	 foreach ($groupnamesToShow as $requestedGroupName)
	 {
	
		if (array_key_exists ( $requestedGroupName , $groupsByName )==false)
         	{
               		echo("<script language=JavaScript>groupNotFound=true;</script>");
               		continue;                 
         	}
	 }

	//this page could be made more efficient by putting group code below into above loop, but for now this will do
	//the tradeoff between cpu efficiency & programmer time efficiency is not worth it


	foreach ($groupsByName as $groupName => $groupMembers)
        {
                //Display $groupName IFF it is also in $groupnamesToShow

		if ( in_array($groupName, $groupnamesToShow)==false )
		{
			continue;
		}
	          echo("<table cellspacing=10 cellpadding=0 border=0 bordercolor=black bgcolor=gray style='margin:5px;'>");
                  echo("<tr><td colspan=".$camsPerTR."><p><b>".$groupName."</p></b></td></tr>");
                  foreach ($groupMembers as $camera)
                  {
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
                  echo("</table>");
        }

?>
