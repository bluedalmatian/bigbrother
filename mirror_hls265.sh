#! /usr/bin/env bash

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2026 Andrew Wood                                  #
#                                                                  #
# mirror_hls265.sh Bourne shell script to perform mirroring for    #
# each camera. Launched by bigbrotherd. 			   #
# H.265/NVENC enabled version.     				   #
#                                                                  #
# www.bigbrothercctv.org       	                                   #
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



usagestring="Syntax error.  Usage: $0 -cam proto://camera/url:port -name CameraName -log /path/to/log/file -cmd /path/to/ffmpeg -webroot /path/to/webroot -mbits 0.5M -fps 20 -res widthxheight"
copyrightstring="BigBrother Copyright Andrew Wood 2016-2026"

# Note the difference in the files this produces compared to the H.264 version:
#===============================================================================
# H.264 is a .m3u8 playlist plus a rolling set of .ts (MPEG transport stream) segments.
# Each .ts segment is self-contained. It carries its own codec description, so a player can start decoding from any segment without needing anything else.
# H.265 in HLS only plays reliably with fragmented MP4 (fMP4) segments stored in .m4s files. Segments are now CamName00001.m4s plus a per-camera CamName_init.mp4.
# HLS spec requires fMP4 (fragmented MP4) for H.265 not .ts . Browsers that play HLS through Media Source Extensions also handle fMP4 far better than H.265 inside .ts. 
# ffmpeg can technically write H.265 into .ts, but many players won’t play it.
# With fMP4 the codec description is split out into a separate init segment, written once.
# The .m4s fragments contain only the media data and can’t be decoded without the init segment. ffmpeg adds a line like #EXT-X-MAP:URI="Cam1_init.mp4" to the playlist, 
# and the player fetches the init file first and then the fragments.



echoUsage()
{
        echo " "
        echo $usagestring
        echo " "
        echo $copyrightstring
        echo " "
}

processSIGINT()
{
        echo "$0 Got SIGINT forwarding to $pid"
		echo "$0 Got SIGINT forwarding to OS PID $pid" | $bblogger $logfile
        keepGoing=0
        kill -INT $pid
}

processSIGTERM()
{
        echo "$0 Got SIGTERM forwarding to $pid"
        echo "$0 Got SIGTERM forwarding to OS PID $pid" | $bblogger $logfile

	keepGoing=0
        kill -TERM $pid

}

processSIGQUIT()
{
        echo "$0 Got SIGQUIT forwarding to $pid"
        echo "$0 Got SIGQUIT forwarding to OS PID $pid" | $bblogger $logfile
		keepGoing=0
        kill -QUIT $pid

}



if [ $# -ne 16 ]
then
	echoUsage
	echo "$0 started with incorrect number of arguments, cannot continue" | $bblogger $logfile
	exit 1
fi

SCRIPT=$(readlink -f "$0")
SCRIPTPATH=$(dirname "$SCRIPT")



sourceurl="" #safety default init
camname="" #safety default init
logfile="/dev/null" #safety default init
ffmpegcommand="" #default init
webroot="" #default init
bblogger="$SCRIPTPATH/bblogger"
bitrate="" #safety default init
framerate="" #default safety init
resolution="" #default safety init



for (( x=1; x<=$#; x++ ))
do
    y=$((x + 1))
    #echo "Argument $x is: ${!x}"
    if [ ${!x} == "-cam" ]
    then
        sourceurl=${!y}
    elif [ ${!x} == "-name" ]
    then
        camname=${!y}
    elif [ ${!x} == "-log" ]
    then
        logfile=${!y}
    elif [ ${!x} == "-cmd" ]
    then
        ffmpegcommand=${!y}
	elif [ ${!x} == "-webroot" ]
	then
		webroot=${!y}
	elif [ ${!x} == "-fps" ]
	then
		framerate=${!y}
	elif [ ${!x} == "-res" ]
	then
		resolution=${!y}
	elif [ ${!x} == "-mbits" ]
	then
		bitrate=${!y}
    fi
      
done




# Check all params have been initialised

if [ -z "$sourceurl" ]
then
    echoUsage
    echo "$0 started with incorrect arguments, cannot continue" | "$bblogger" "$logfile"
    exit 1
fi

if [ -z "$camname" ]
then
    echoUsage
    echo "$0 started with incorrect arguments, cannot continue" | "$bblogger" "$logfile"
    exit 1
fi

if [ "$logfile" == "/dev/null" ]
then
    echoUsage
    exit 1
fi

if [ -z "$ffmpegcommand" ]
then
    echoUsage
    exit 1
fi

if [ -z "$webroot" ]
then
    echoUsage
    exit 1
fi

if [ -z "$bitrate" ]
then
    echoUsage
    exit 1
fi

if [ -z "$framerate" ]
then
    echoUsage
    exit 1
fi

if [ -z "$resolution" ]
then
    echoUsage
    exit 1
fi

# All params ok




pid=0 # dont match anything at startup
trap processSIGINT INT
trap processSIGTERM TERM
trap processSIGQUIT QUIT
keepGoing=1;

echo "$0 $1 $2 $3 $4 $5 $6 $7 $8 $9 ${10} ${11} ${12} ${13} ${14} ${15} ${16} started" | $bblogger $logfile


while [ $keepGoing -ne 0 ]
do
	pid=0 #temp reset to nothing to reduce risk of PID recycling problems

	### HEVC notes:
	###  -tag:v hvc1             (needed for Safari/iOS/Apple playback of HEVC in HLS)
	###  -hls_segment_type fmp4  (HLS HEVC needs fragmented MP4 segments, not MPEG-TS, for browsers/hls.js/Apple)
	###  libx265 needs -x265-params for keyint and quiet logging, as -g alone is not always honoured

	#Probe NVENC encoder for outputting H265
	$ffmpegcommand -y -hide_banner -loglevel error \
		-f lavfi -i testsrc2=size=1280x720:rate=30 \
		-f lavfi -i sine=frequency=1000:sample_rate=48000 \
		-t 2 \
		-c:v hevc_nvenc \
		-c:a aac \
		-f mpegts /dev/null
	nvencprobe_rc=$? #0 if ffmpeg exited OK indicating HEVC NVENC available

	if [ $nvencprobe_rc -eq 0 ]
	then
		echo "$camname $0 NVENC HEVC available, using GPU to encode output" | "$bblogger" "$logfile"
		videocodec="hevc_nvenc"
		codecopts="-preset p1 -tune ll"
	else
		#Probe that this ffmpeg build actually has libx265 before relying on it
		$ffmpegcommand -y -hide_banner -loglevel error \
			-f lavfi -i testsrc2=size=320x240:rate=10 \
			-t 1 -c:v libx265 -f null /dev/null
		x265probe_rc=$?

		if [ $x265probe_rc -eq 0 ]
		then
			echo "$camname $0 NVENC not available, using CPU (libx265) to encode output" | "$bblogger" "$logfile"
			videocodec="libx265"
			codecopts="-preset ultrafast -tune zerolatency -x265-params log-level=error:keyint=4:min-keyint=4"
		else
			echo "$camname $0 libx265 not available either, falling back to libx264 (H.264)" | "$bblogger" "$logfile"
			videocodec="libx264"
			codecopts="-preset ultrafast -tune zerolatency"
		fi
	fi
	#End probe

	# Only tag as hvc1 / use fMP4 when actually producing HEVC
	if [ "$videocodec" == "libx264" ]
	then
		tagargs=""
		segargs="-hls_segment_filename ${webroot}/${camname}%05d.ts"
	else
		tagargs="-tag:v hvc1"
		segargs="-hls_segment_type fmp4 -hls_fmp4_init_filename ${camname}_init.mp4 -hls_segment_filename ${webroot}/${camname}%05d.m4s"
	fi

	hwdecode_args="" #default if using CPU

	#Probe NVDEC decoder, but only if we are also using NVENC
	if [ $nvencprobe_rc -eq 0 ]
	then
		 $ffmpegcommand -hide_banner -loglevel error -hwaccel cuda -hwaccel_output_format cuda -i "$sourceurl" -map 0:v:0 -frames:v 30 -f null /dev/null
		 nvdecprobe_rc=$?
		 if [ "$nvdecprobe_rc" -eq 0 ]
		 then
			echo "$camname $0 NVDEC available, using GPU to decode input" | "$bblogger" "$logfile"
			hwdecode_args="-hwaccel cuda -hwaccel_output_format cuda"
		 else
			echo "$camname $0 NVDEC not available, using CPU to decode input, GPU will still be used for encoding output" | "$bblogger" "$logfile"
			hwdecode_args=""
		 fi
	fi

	$ffmpegcommand $hwdecode_args -timeout 10000000 -y -loglevel fatal -i $sourceurl \
		-vcodec $videocodec $tagargs $codecopts \
		-acodec aac -strict -2 -b:a 16k \
		-framerate $framerate -s $resolution -b:v $bitrate -bufsize $bitrate \
		-g 4 -force_key_frames "expr:gte(t,n_forced*2)" \
		-hls_list_size 3 -hls_init_time 0.5 \
		-hls_flags delete_segments+program_date_time+temp_file+independent_segments \
		-hls_time 0.5 -hls_allow_cache 0 -f hls \
		$segargs \
		-metadata title="$camname" $webroot/$camname.m3u8 &

	pid=$!
	echo "$0 started ffmpeg OS PID $pid (encoder: $videocodec)" | $bblogger $logfile
	wait $pid
	echo "$0 woke up because ffmpeg OS PID $pid exited or a signal was received" | $bblogger $logfile
done
exit 0


