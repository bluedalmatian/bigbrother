/**
 ************************************************************************ 								
 * BigBrother  CCTV Recording & Live Viewing (mirroring) software       *
 * www.bigbrothercctv.org                                               *
 *                                                                      *
 * stall.js Copyright Andrew Wood 2025-2026	stall and pause handling	*
 *																		*
 * Licensed under the GNU Public License v 3                            * 
 * The full license can be read at www.gnu.org/licenses/gpl-3.0.txt     *
 * and is included in the License.txt file included with this           *
 * software.                                                            *
 *                                                                       *
 * BigBrother is free open source software but if you find it           *
 * useful please consider making a donation to the Communications       *
 * Museum Trust at www.comms.org.uk/donate                              *
 *                                                                      *
 *                                                                      *
 ************************************************************************ 																		
 */

//stallSeconds contains ele for each cam giving indication of how many seconds it has been stalled for
var stallSeconds = new Array();
//lastTime contains ele for each cam giving its last playback time position when we polled it to check for stalls
var lastTime=new Array();
//players contains result of video.js(id) where id is cameraname, indexed by CamName str,  this is because player = videojs(id); should only be called ONCE on entire page to stop video.js shitting itself
var players=new Array();
//containers contains container for each video indexed by id
var containers=new Array();
//pausedPlayers contains id of any player currently paued by user action to distinguish from those paused due to error
var pausedPlayers=new Array();


var videotype_extn=new Array(); //ele for each cam indexed by cam name containing a string giving stream filename extn e.g "m3u8"
var videotype_mime=new Array(); //ele for each cam indexed by cam name containing a string giving MIME e.g "application/x-mpegURL"

function jumpToLiveEdge(player,id) 
{
  //This is a level 1 reset of a stalled player
  console.log("jumpToLiveEdge() called for "+id);
  player = players[id];
  
  if (!player) return;
  
   const seekable = player.seekable();

	if (seekable && seekable.length) 
	{
		const liveEdge = seekable.end(seekable.length - 1);
		const current = player.currentTime();
		const latency = liveEdge - current;

		if (latency > 1.0) 
		{
			player.currentTime(liveEdge - 0.2); // smoother than jumping to exact edge
		}
	}
  
    console.log("jumpToLiveEdge() for "+id+" done");
  
}


function debugBufferDelay(player,id)
{
		 player = players[id];
		setInterval(() => {
  const seekable = player.seekable();
  if (seekable.length) {
    const liveEdge = seekable.end(seekable.length - 1);
    console.log("debugBufferDelay Latency for "+id+":", liveEdge - player.currentTime());
  }
}, 2000);
}

function reloadStream(player,id) 
{
	//This is a level 2 reset of a stalled player
	console.log("reloadStream called for "+id);
	
	var oldPlayer = players[id];

	if (oldPlayer) 
	{
		oldPlayer.dispose(); // completely destroys player
	}


	

	var container = containers[id];
	container.innerHTML = `
    <video 
      id="${id}" 
      class="video-js vjs-default-skin" 
      autoplay 
      muted 
      controls
      width="640" 
      height="480">
	  <source src="${id}.${videotype_extn[id]}" type="${videotype_mime[id]}">
    </video>
	 <div id="${id}_pauseoverlay" class="pause-overlay" style="display:none; position:relative; background-image: url(pausedOverlayBackground.png);"><img src=pausedOverlay.gif width=64 height=64></div>
  `;
  
	var newPlayer = videojs(id);
	
	// recreate it fresh (like page load)
	players[id]=newPlayer;
	
	newPlayer.on('pause', () => {
													// userActive() is true when user recently interacted (mouse/touch/keyboard)
													
													
													
													let useractive=players[id].userActive();
													if (getTimestampNow()-pageLoadedTimestamp<3)
													{
														useractive=false;
													}
													if ((stallSeconds[id]<1) && useractive) 
													{
														setPausedFlag(id);
														
													}

													
												});
												
						newPlayer.on('play', () => {
													if (isPausedFlagSet(id)) 
													{
														clearPausedFlag(id);
														
														
													}
												});
}

function clearPausedFlag(id)
{
	var index = pausedPlayers.indexOf(id);
	if (index !== -1)
	{
		pausedPlayers.splice(index, 1);
	}
}

function setPausedFlag(id)
{
	var index = pausedPlayers.indexOf(id);
	if (index == -1)
	{
		pausedPlayers.push(id);
	}
}


function isPausedFlagSet(id)
{
	var index = pausedPlayers.indexOf(id);
	if (index == -1)
	{
		return false;
	}
	return true;
}



function handleStall(player,id)
{
	  if (isPausedFlagSet(id)) return;

    reloadStream(player,id);
}



function isStalled(player,id)
{
  
   //handle fatal media error separately
    if (player.error) 
	{
        reloadStream(player, id);
        stallSeconds[id] = 0;
        return;
	}
  
  
  
  
    if (isPausedFlagSet(id))
    {
        lastTime[id] = player.currentTime;
        stallSeconds[id] = 0;
        return;
    }

    var currentTime = player.currentTime;

    if (currentTime <= lastTime[id])
    {
        stallSeconds[id]++;
    }
    else if (player.readyState != 4)
    {
        stallSeconds[id]++;
    }
    else
    {
        stallSeconds[id] = 0;
    }

    if (stallSeconds[id] >= 5)
    {
        handleStall(player,id);
        stallSeconds[id] = 0;
    }

    lastTime[id] = currentTime;
}

function initStalledHandler()
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
							

                        stallSeconds[id]=0;
						lastTime[id]=0;
						
						let player=videojs(id);
						players[id]=player;
						containers[id] = document.getElementById(id).parentNode;
					
						player.on('pause', () => {
													let useractive=players[id].userActive();
													if (getTimestampNow()-pageLoadedTimestamp<3)
													{
														useractive=false;
													}
													if ((stallSeconds[id]<1) && useractive) 
													{
														setPausedFlag(id);
														
													}
														
														
												});
												
						player.on('play', () => {
														clearPausedFlag(id);
												});


                }

}


function checkPaused()
{
	

	
	
		var videoeles=document.getElementsByClassName("video-js");
		for (var i=0; i < videoeles.length; i++)
		{
			var id=videoeles[i].getAttribute('id');
			//NOTE you would expect videoeles[i] to be the <video> tag with id=camname BUT IT IS NOT!!!!
			//video.js has altered the document tree at run time and wrapped the <video> in a <div>
			//the <div> now has the id=camname and the <video> is within the <div> with id=camname_html5_api
			
			//videoeles[i] is a <div> generated by video.js even though in HTML source it is a <video>!
			//the actual <video> has been moved down within the div and has an id of CAMERANAME_html5_api
			//this is contrary to the video.js documentation (what limited docs there is)
			
			var videotag=document.getElementById(id+"_html5_api");
	
			
			console.log("Checking if "+id+" is paused: "+videotag.paused);
			var player = players[id];

		
			if (videotag.paused)  
			{
				
				pause(id,true);
			}
			else
			{
				pause(id,false);
				jumpToLiveEdge(videotag,id);
				
				
			}


			if (isPausedFlagSet(id))
			{
				continue;
			}
			console.log(id+" isPausedFlagSet:"+isPausedFlagSet(id));
			
			isStalled(videotag,id);
			

			console.log("readyState for "+id+" is :"+videotag.readyState)
			
			

		}
}