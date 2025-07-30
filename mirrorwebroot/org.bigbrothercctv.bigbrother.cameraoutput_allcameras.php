<?php

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016 Andrew Wood                                       #
#                                                                  #
# UI code to display all cameras defined for mirroring	           #
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








	set_error_handler(NULL);
        $camsprinted=0;
        foreach ($groupsByName as $groupName => $groupMembers)
        {
		
                  echo("<table cellspacing=10 cellpadding=0 border=0 bordercolor=black bgcolor=gray style='margin:5px;' id=cameragrid>");
                  echo("<tr><td colspan=".$camsPerTR."><p><b>".$groupName."</p></b></td></tr>");
                  foreach ($groupMembers as $camera)
                  {
                           if ($camsprinted==0)
                           {
                               echo("<tr>");
                           }


                           echo("<td valign=top width=640>");
                           $camera->GenerateHTML();
                           echo("</td>");

                                $camsprinted++;

                        if ($camsprinted==$camsPerTR)
                        {
                                echo("</tr>");
                                echo("<tr><td colspan=".$camsPerTR." height=10 width=100%>&nbsp;</td></tr>");

                                $camsprinted=0;
                        }

                  }
                  echo("</table>");
        }

   echo("<br>");
   
	if (sizeof($nullGroupCameras)>0)
        {
                echo("<table cellspacing=10 cellpadding=0 border=0 bordercolor=black  style='margin:5px;'>");
                 $camsprinted=0;
            
        }

        foreach ($nullGroupCameras as $camera)
        {

                if ($camsprinted==0)
                {
                        echo("<tr>");
                }
                echo("<td valign=top width=640>");
                $camera->GenerateHTML();
                echo("</td>");

                $camsprinted++;

                if ($camsprinted==$camsPerTR)
                {
                        echo("</tr>");
                        echo("<tr><td colspan=".$camsPerTR." height=10 width=100%>&nbsp;</td></tr>");

                        $camsprinted=0;
                }


        }
        if (sizeof($nullGroupCameras)>0)
        {
              echo("</table>");

        }

?>
