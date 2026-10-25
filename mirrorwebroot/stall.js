/**
 * ***********************************************************************
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
var lastTime = new Array();
//players contains result of video.js(id) where id is cameraname, indexed by CamName str, this is because player = videojs(id); should only be called ONCE on entire page to stop video.js shitting itself
var players = new Array();
//containers contains container for each video indexed by id
var containers = new Array();
//pausedPlayers contains id of any player currently paused by user action to distinguish from those paused due to error
var pausedPlayers = new Array();
//reloadedAt contains timestamp (same units as getTimestampNow) of the last reloadStream() for each cam
var reloadedAt = new Array();
//reloadPending is true for a cam while a reload has been scheduled but not yet run
var reloadPending = new Array();

var videotype_extn = new Array(); //ele for each cam indexed by cam name containing a string giving stream filename extn e.g "m3u8"
var videotype_mime = new Array(); //ele for each cam indexed by cam name containing a string giving MIME e.g "application/x-mpegURL"


function jumpToLiveEdge(player, id)
{
	//This is a level 1 reset of a stalled player
	console.log("jumpToLiveEdge() called for " + id);
	player = players[id];

	if (!player) return;
	if (player.error()) return; // nothing to seek in an errored player, reload will handle it

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

	console.log("jumpToLiveEdge() for " + id + " done");
}


var debugIntervals = new Array();

function debugBufferDelay(player, id)
{
	if (debugIntervals[id]) clearInterval(debugIntervals[id]); // never stack up more than one per cam
	debugIntervals[id] = setInterval(() => {
		const p = players[id]; // look up each time, the player may have been replaced by reloadStream
		if (!p || p.isDisposed()) return;
		const seekable = p.seekable();
		if (seekable.length)
		{
			const liveEdge = seekable.end(seekable.length - 1);
			console.log("debugBufferDelay Latency for " + id + ":", liveEdge - p.currentTime());
		}
	}, 2000);
}


//Decide whether a 'pause' event was really the user pausing, rather than a stall / error / reload
function isGenuineUserPause(id)
{
	var p = players[id];
	if (!p) return false;
	if (p.error()) return false;                                        // errors aren't user pauses
	if (stallSeconds[id] >= 1) return false;                            // already stalling
	if (p.readyState() < 3) return false;                               // ran out of data, not a click
	if (getTimestampNow() - pageLoadedTimestamp < 3) return false;      // grace period after page load
	if (getTimestampNow() - (reloadedAt[id] || 0) < 5) return false;    // grace period after a reload
	return p.userActive();
}


//reloadFailures counts consecutive reloads for each cam that haven't resulted in playback
var reloadFailures = new Array();

//Reload a player shortly, at most once at a time per cam.
//Backs off while a stream keeps failing (2s, 4s, 8s ... max 60s) so a dead camera
//isn't torn down and rebuilt every couple of seconds forever.
function scheduleReload(id)
{
	if (reloadPending[id]) return;
	reloadPending[id] = true;

	var failures = reloadFailures[id] || 0;
	var delay = Math.min(2000 * Math.pow(2, failures), 60000);
	console.log("scheduleReload() for " + id + " in " + delay + "ms (failures: " + failures + ")");

	// deferred so we never touch a player from inside its own event handler
	setTimeout(function () {
		reloadPending[id] = false;
		reloadFailures[id] = failures + 1; // reset to 0 by the 'playing' handler once it works
		try
		{
			reloadStream(players[id], id);
		}
		catch (ex)
		{
			console.error("reloadStream failed for " + id, ex);
		}
	}, delay);
}


//Attach pause/play/error handlers - used for both the initial player and every reloaded player
function attachPlayerHandlers(player, id)
{
	player.on('pause', function () {
		if (isGenuineUserPause(id))
		{
			setPausedFlag(id);
		}
	});

	player.on('play', function () {
		clearPausedFlag(id);
	});

	player.on('playing', function () {
		reloadFailures[id] = 0; // stream is working again, reset the backoff
	});

	player.on('error', function () {
		var e = player.error();
		console.warn(id + " player error: " + (e ? e.code + " " + e.message : "unknown"));
		clearPausedFlag(id);
		scheduleReload(id);
	});
}


function reloadStream(player, id)
{
	//This is a level 2 reset of a stalled player.
	//Normally we just give the existing player its source again: video.js tears down the old
	//HLS engine (buffers, MediaSource, segment loaders) and starts a new one, but the player,
	//its DOM and all its event handlers are reused, so nothing accumulates.
	//Only after several soft reloads in a row have failed do we dispose and rebuild (level 3).
	var p = players[id];

	if (p && !p.isDisposed() && (reloadFailures[id] || 0) < 4)
	{
		console.log("reloadStream (soft) called for " + id);

		stallSeconds[id] = 0;
		lastTime[id] = 0;
		reloadedAt[id] = getTimestampNow();
		clearPausedFlag(id);

		p.error(null); // clear error state/overlay
		p.src({ src: id + "." + videotype_extn[id], type: videotype_mime[id] });
		var playPromise = p.play();
		if (playPromise && playPromise.catch) playPromise.catch(function () {});
		return;
	}

	rebuildPlayer(id);
}


function rebuildPlayer(id)
{
	//This is a level 3 reset: completely destroy and recreate the player
	console.log("rebuildPlayer called for " + id);

	var oldPlayer = players[id];

	if (oldPlayer && !oldPlayer.isDisposed())
	{
		oldPlayer.dispose(); // completely destroys player
	}

	var container = containers[id];
	if (!container)
	{
		console.error("reloadStream: no container for " + id);
		return;
	}

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

	// recreate it fresh (like page load)
	var newPlayer = videojs(id);
	players[id] = newPlayer;

	stallSeconds[id] = 0;
	lastTime[id] = 0;
	reloadedAt[id] = getTimestampNow();
	clearPausedFlag(id);

	attachPlayerHandlers(newPlayer, id);

	initFullscreenHandler();
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
	return pausedPlayers.indexOf(id) !== -1;
}


function handleStall(player, id)
{
	if (isPausedFlagSet(id)) return;

	scheduleReload(id); // goes through the backoff like error-triggered reloads
}


//videotag here is the native <video> element (CAMNAME_html5_api), so currentTime/readyState are properties
//fatal video.js errors are handled in checkPaused() and the 'error' handler, not here
function isStalled(videotag, id)
{
	if (isPausedFlagSet(id))
	{
		lastTime[id] = videotag.currentTime;
		stallSeconds[id] = 0;
		return;
	}

	var currentTime = videotag.currentTime;

	if (currentTime <= lastTime[id])
	{
		stallSeconds[id]++;
	}
	else if (videotag.readyState != 4)
	{
		stallSeconds[id]++;
	}
	else
	{
		stallSeconds[id] = 0;
	}

	if (stallSeconds[id] >= 5)
	{
		console.log(id + " stalled for " + stallSeconds[id] + "s, reloading");
		handleStall(players[id], id);
		stallSeconds[id] = 0;
	}

	lastTime[id] = currentTime;
}


function initStalledHandler()
{
	var videoeles = document.getElementsByClassName("video-js");
	for (let i = 0; i < videoeles.length; i++)
	{
		let id = videoeles[i].getAttribute('id');
		//NOTE you would expect videoeles[i] to be the <video> tag with id=camname BUT IT IS NOT!!!!
		//video.js has altered the document tree at run time and wrapped the <video> in a <div>
		//the <div> now has the id=camname and the <video> is within the <div> with id=camname_html5_api

		stallSeconds[id] = 0;
		lastTime[id] = 0;
		reloadedAt[id] = 0;
		reloadPending[id] = false;

		let player = videojs(id);
		players[id] = player;
		containers[id] = document.getElementById(id + "_playercontainer");

		attachPlayerHandlers(player, id);
	}
}


function checkPaused()
{
	var videoeles = document.getElementsByClassName("video-js");
	for (var i = 0; i < videoeles.length; i++)
	{
		var id = videoeles[i].getAttribute('id');
		//NOTE videoeles[i] is a <div> generated by video.js even though in HTML source it is a <video>!
		//the actual <video> has been moved down within the div and has an id of CAMERANAME_html5_api

		try
		{
			var videotag = document.getElementById(id + "_html5_api");
			var player = players[id];

			if (!videotag || !player) continue; // mid-reload

			//fatal video.js error (e.g. 404s) - check BEFORE the paused flag so it can't be skipped
			if (player.error())
			{
				console.log(id + " is in error state (" + player.error().code + "), reloading");
				clearPausedFlag(id);
				scheduleReload(id);
				continue;
			}

			console.log("Checking if " + id + " is paused: " + videotag.paused + " isPausedFlagSet:" + isPausedFlagSet(id));

			if (videotag.paused)
			{
				pause(id, true);
			}
			else
			{
				pause(id, false);
				jumpToLiveEdge(videotag, id);
			}

			if (isPausedFlagSet(id))
			{
				continue;
			}

			isStalled(videotag, id);

			console.log("readyState for " + id + " is :" + videotag.readyState);
		}
		catch (ex)
		{
			console.error("checkPaused failed for " + id, ex);
		}
	}
}
