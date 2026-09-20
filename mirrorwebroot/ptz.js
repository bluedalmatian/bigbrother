/**
 ************************************************************************ 								
 * BigBrother  CCTV Recording & Live Viewing (mirroring) software       *
 * www.bigbrothercctv.org                                               *
 *                                                                      *
 * ptz.js Copyright Andrew Wood 2025-2026	PTZ functions (CONT MOVE)   *
 *									*									*
 * Licensed under the GNU Public License v 3                            * 
 * The full license can be read at www.gnu.org/licenses/gpl-3.0.txt     *
 * and is included in the License.txt file included with this           *
 * software.                                                            *
 *                                                                      *
 * BigBrother is free open source software but if you find it           *
 * useful please consider making a donation to the Communications       *
 * Museum Trust at www.comms.org.uk/donate                              *
 *                                                                      *
 *                                                                      *
 ************************************************************************ 																		
 */


let ptzMovingCameraName=""; //will contain null str if no cam moving or camera name
let ptzInterval = null; //pointer to callback keepalive func when a cam is moving
let keepMovingPending=""; //will contain null str if no cam has a KEEPMOVING ajax request in flight or camera name if one does

var ptzBars=new Array(); //assoc array for each cam camName->ptz HTML str
var fullscreenStates = new Array(); //assoc array for each cam camName->bool true=fullscreen

function doCameraControl(cameraid,command)
{
	const slider = document.getElementById(cameraid+"_ptzspeedselector")
	const speed = slider.value;

 	  var ptz_xhr = new XMLHttpRequest();
    	  var url = "org.bigbrothercctv.bigbrother.cameraControlONVIFPTZ.php?cameraName="
              + encodeURIComponent(cameraid)
              + "&command="
              + encodeURIComponent(command)
			  + "&speed="
              + encodeURIComponent(speed);

   	 ptz_xhr.open('GET', url, true);
   	 ptz_xhr.onreadystatechange = function () {
         processPTZResponse(ptz_xhr);
   	 };
   	 ptz_xhr.send(null);
}

function doKeepMoving(cameraid)
{
	const slider = document.getElementById(cameraid+"_ptzspeedselector")
	const speed = slider.value;

	
	  if (keepMovingPending=="" && ptzMovingCameraName==cameraid)
      {
		  //only send another KEEPMOVING if the previous one isnt still in flight
		    keepMovingPending = cameraid;
			var ptz_xhr = new XMLHttpRequest();
			var url = "org.bigbrothercctv.bigbrother.cameraControlONVIFPTZ.php?cameraName="
              + encodeURIComponent(cameraid)
              + "&command="
              + encodeURIComponent("KEEPMOVING")
			  + "&speed="
              + encodeURIComponent(speed);

			ptz_xhr.open('GET', url, true);
			ptz_xhr.onreadystatechange = function () {processPTZKeepMovingResponse(ptz_xhr);};	
			ptz_xhr.send(null);
	 }
}


function doStepMove(cameraid,command)
{
	const slider = document.getElementById(cameraid+"_ptzspeedselector")
	const speed = slider.value;
	
	  if (keepMovingPending=="" && ptzMovingCameraName==cameraid)
      {
		  //only send another if the previous one isnt still in flight
		    keepMovingPending = cameraid;
			var ptz_xhr = new XMLHttpRequest();
			var url = "org.bigbrothercctv.bigbrother.cameraControlONVIFPTZ.php?cameraName="
              + encodeURIComponent(cameraid)
              + "&command="
              + encodeURIComponent(command)
			  + "&speed="
              + encodeURIComponent(speed);

			ptz_xhr.open('GET', url, true);
			ptz_xhr.onreadystatechange = function () {processPTZStepMoveResponse(ptz_xhr);};	
			ptz_xhr.send(null);
	 }
}


function processPTZResponse(xhr)
{
	
	if (xhr.readyState!=4)
	{
		return;
	}
    
   	 if (xhr.status==418)
   	 {
		appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error controlling camera: Server shutdown</p>");   
		playPTZErrorAudio()
		
    	}
   	if (xhr.status==422)
    	{
       //PHP script will return HTTP 422 if error message needs to be given to user
	   //extract JSON from body to get ptzReturnCode and ptzResponseText
	   
	   var data = JSON.parse(xhr.responseText);
	   var ptzReturnCode=data.code;
	   var ptzResponseText=data.description;
	   appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error controlling camera: "+ptzReturnCode+" ("+ptzResponseText+")</p>");   
	   playPTZErrorAudio()

		
		
    	}
	else if (xhr.status==204)
    	{
       //PHP script will only return 204 if it has processed everyting OK 
	   
	  
    	}
	else if (xhr.status==0)
    	{
       		//0 indicates timeout
	   appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error controlling camera: Connection to server timed out</p>");
	   playPTZErrorAudio()   
    	}
    	else
    	{
		appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error controlling camera, server returned HTTP status: "+xhr.status+"</p>");   
		playPTZErrorAudio()
		
		
    	}
	 
}



function processPTZStepMoveResponse(xhr)
{
	
	if (xhr.readyState!=4)
	{
		return;
	}
    
	keepMovingPending="";
   	 if (xhr.status==418)
   	 {
		appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error controlling camera: Server shutdown</p>");   
		playPTZErrorAudio()
		
    	}
   	if (xhr.status==422)
    	{
       //PHP script will return HTTP 422 if error message needs to be given to user
	   //extract JSON from body to get ptzReturnCode and ptzResponseText
	   
	   var data = JSON.parse(xhr.responseText);
	   var ptzReturnCode=data.code;
	   var ptzResponseText=data.description;
	   appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error controlling camera: "+ptzReturnCode+" ("+ptzResponseText+")</p>");   
	   playPTZErrorAudio()

		
		
    	}
	else if (xhr.status==204)
    	{
       //PHP script will only return 204 if it has processed everyting OK 
	   
	  
    	}
	else if (xhr.status==0)
    	{
       		//0 indicates timeout
	   appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error controlling camera: Connection to server timed out</p>");
	   playPTZErrorAudio()   
    	}
    	else
    	{
		appendMsg("<p class=statusmsg><img src=i-red.png width=32 height=32 align=middle valign=middle class=statusicon>"+getDateTime()+" Error controlling camera, server returned HTTP status: "+xhr.status+"</p>");   
		playPTZErrorAudio()
		
		
    	}
	 
}

function processPTZKeepMovingResponse(xhr)
{
	if (xhr.readyState!=4)
	{
		return;
	}
	keepMovingPending="";
}


function startMove(cameraid, cmd)
{
	if (ptzMovingCameraName=="")
	{
		ptzMovingCameraName=cameraid;
		doCameraControl(cameraid, cmd);
		 ptzInterval = setInterval(function ()
			{
				doKeepMoving(cameraid);
			}, 1000);
	}
}

function stopMove(cameraid)
{
	if (ptzMovingCameraName=="")
	{
		return;
	}
	clearInterval(ptzInterval);
    ptzInterval = null;
	ptzMovingCameraName="";
	keepMovingPending="";
    doCameraControl(cameraid, "STOPALL");
}


function startZoom(cameraid, direction)
{
	if (ptzMovingCameraName=="")
	{
		ptzMovingCameraName=cameraid;
		// prevent duplicate timers
		if (ptzInterval !== null)
			return;

		// send immediately
		doCameraControl(cameraid, direction);

		// continue sending while held
		ptzInterval = setInterval(function ()
		{
			doStepMove(cameraid, direction);

		}, 500); // movement repeat speed
	}
}

function stopZoom()
{
	if (ptzMovingCameraName=="")
	{
		return;
	}
	
    clearInterval(ptzInterval);
    ptzInterval = null;
	ptzMovingCameraName="";
	keepMovingPending="";
}




function playPTZErrorAudio()
{

	audio = document.getElementById("ptzerroraudio");
  	audio.play();
 }

function getDateTime()
{
	var today = new Date();
	var dd = String(today.getDate()).padStart(2, '0');
	var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0 so we add 1
	var yyyy = today.getFullYear();
	var hr = String(today.getHours()).padStart(2, '0');
	var min = String(today.getMinutes()).padStart(2, '0');
	var sec = String(today.getSeconds()).padStart(2, '0');
	var ms = String(today.getMilliseconds()).padStart(3, '0');
	
	today = yyyy + '-' + dd + '-' + mm + ' '+ hr+ ':'+ min+ ':'+sec+ ':'+ms;
	return today;
}


function initFullscreenHandler()
{
	setInterval(pollFullscreen, 1000);
	

		 var videoeles=document.getElementsByClassName("video-js");
                for (let i=0; i < videoeles.length; i++)
                {
                        let id=videoeles[i].getAttribute('id');
                        //NOTE you would expect videoeles[i] to be the <video> tag with id=camname BUT IT IS NOT!!!!
                        //video.js has altered the document tree at run time and wrapped the <video> in a <div>
                        //the <div> now has the id=camname and the <video> is within the <div> with id=camname_html5_api

                        //videoeles[i] is a <div> generated by video.js even though in HTML source it is a <video>!
                        //the actual <video> has been moved down within the div and has an id of CAMERANAME_html5_api
                        //this is contrary to the video.js documentation (what limited docs there is)

                        var videotag=document.getElementById(id+"_html5_api");
							
						var toolbar   = document.getElementById('ptzcontrolbar_'+id);
						if (toolbar)
						{
							ptzBars[id] = toolbar.outerHTML;
						}
                       
						
						let player=videojs(id);
						players[id]=player;
						containers[id] = document.getElementById(id + "_playercontainer");
						
						


				}

}







function pollFullscreen()
{
	

		 var videoeles=document.getElementsByClassName("video-js");
                for (let i=0; i < videoeles.length; i++)
                {
                        let id=videoeles[i].getAttribute('id');
                        //NOTE you would expect videoeles[i] to be the <video> tag with id=camname BUT IT IS NOT!!!!
                        //video.js has altered the document tree at run time and wrapped the <video> in a <div>
                        //the <div> now has the id=camname and the <video> is within the <div> with id=camname_html5_api

                        //videoeles[i] is a <div> generated by video.js even though in HTML source it is a <video>!
                        //the actual <video> has been moved down within the div and has an id of CAMERANAME_html5_api
                        //this is contrary to the video.js documentation (what limited docs there is)

                        var videotag=document.getElementById(id+"_html5_api");
						
                       
				
						let player = players[id];

					
							if (!ptzBars[id]) 
							{ 
								continue;
							}
							
							var fullscreen = player.isFullscreen();
							
							
							 if (fullscreenStates[id] === undefined)
							{
									fullscreenStates[id] = fullscreen;
									continue;
							}
							
							
							 if (fullscreenStates[id] === fullscreen)
							{
									//state not changed
									continue;
							}
							
							 // Remember new state
							fullscreenStates[id] = fullscreen;

							
							
							if (fullscreen) 
							{ 
								// Remove existing toolbar if one is present 
								var toolbar = document.getElementById('ptzcontrolbar_' + id);
								
								if (toolbar)
								{ 
									toolbar.remove(); 
								}
								
								// Recreate it inside the fullscreen player 
								player.el().insertAdjacentHTML('beforeend', ptzBars[id]);
							}
							else
							{
								// Remove toolbar from fullscreen player 
								var toolbar = document.getElementById('ptzcontrolbar_' + id);
								if (toolbar) 
								{ 
									toolbar.remove(); 
								}
								
								// Recreate it in its normal location 
								var container = document.getElementById(id + '_playercontainer');
								
								if (container) 
								{ 
									container.parentNode.insertAdjacentHTML( 'beforeend', ptzBars[id] ); 
								}
							}



				}

}

