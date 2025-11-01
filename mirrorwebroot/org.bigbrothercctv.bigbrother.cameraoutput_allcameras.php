<?php

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2025 Andrew Wood                                  #
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
	echo("<table cellspacing=0 cellpadding=0  bgcolor=gray  id=cameragrid>");
        $camsprinted=0;
        foreach ($groupsByName as $groupName => $groupMembers)
        {
                  if ($minimalUI==false)
		  {
                  	echo("<tr class=groupnametr><td colspan=".$camsPerTR."><p><b>".$groupName."</p></b></td></tr>");
		  }
		  foreach ($groupMembers as $camera)
                  {
                           if ($camsprinted==0)
                           {
                               echo("<tr>");
                           }


                           echo("<td valign=top width=640 class=videotd style=\"position:relative;\">");
                           $camera->GenerateHTML();
                           echo("</td>");

                                $camsprinted++;
                        if ($camsprinted==$camsPerTR)  
                        {
                                echo("</tr>");
                                echo("<tr class=spacertr><td colspan=".$camsPerTR." height=10 width=100%>&nbsp;</td></tr>");

                                $camsprinted=0;
                        }
			

                  }
                  
        }


   echo("<div class=spacertr><br></div>");

   
	if (sizeof($nullGroupCameras)>0)
        {
                if ($minimalUI==false)
		{
				echo("<tr class=groupnametr><td colspan=".$camsPerTR."><p><b>Other</p></b></td></tr>");
		}
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
        
              echo("</table>");

       

?>
