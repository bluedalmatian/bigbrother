#! /usr/bin/env bash

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2025 Andrew Wood                                  #
#                                                                  #
# mirror_hls.sh Bourne shell script to perform mirroring for each  #
# camera. Launched by bigbrotherd                                  #
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
copyrightstring="BigBrother Copyright Andrew Wood 2016-2025"


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






#check all params have been initialised, they are not still the default 0
if [ $sourceurl == "" ]
then
	 echoUsage
	 echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
         exit 1
fi

if [ $camname == "" ]
then
	 echoUsage
	 echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
         exit 1
fi

if [ $logfile == "/dev/null" ]
then
	echoUsage
	exit 1
fi

if [ $ffmpegcommand == "" ]
then
        echoUsage
        exit 1
fi

if [ $webroot == "" ]
then
        echoUsage
        exit 1
fi
if [ $bitrate == "" ]
then
        echoUsage
        exit 1
fi
if [ $framerate == "" ]
then
        echoUsage
        exit 1
fi

if [ $resolution == "" ]
then
        echoUsage
        exit 1
fi

#all params ok


pid=0 # dont match anything at startup
trap processSIGINT INT
trap processSIGTERM TERM
trap processSIGQUIT QUIT
keepGoing=1;

echo "$0 $1 $2 $3 $4 $5 $6 $7 $8 $9 ${10} ${11} ${12} ${13} ${14} ${15} ${16} started" | $bblogger $logfile


while [ $keepGoing -ne 0 ]
do
	pid=0 #temp reset to nothing to reduce risk of PID recycling problems

	###2>&1 redirect std error to std output so both can be piped as one (not used as it doesnt work well)

	###ffmpeg options:
	###	 -strict -2	 (needed on some versions of ffmpeg to use aac audio codec)
	###	 -b:a		 (audio bitrate)
	###	 -preset ultrafast (prioritise fast processing over compression)
	###	 -framerate 10   (frames per sec)
	###	 -s 640x480	 (resolution)
	###	 -b:v 1000M	 (try to maintain this average bit rate)
	###	 -bufsize 1000M  (checks average bit rate when buff is full,should be set to same value as -b:v)
	###	 -g 20		 (GOP size - i.e a keyframe every 20 frames)
	###	 -segment_list_size 10 (will not write anything to m3u8 file unless you tell it how many segments to write to m3u8 file)
	###	 -hls_wrap 10  		(delete old segement .ts files after 10 have been created)
	###  -f hls		(output format)

	### See https://trac.ffmpeg.org/wiki/Limiting%20the%20output%20bitrate for -b:v and -bufsize parameters 

	$ffmpegcommand -loglevel fatal -i $sourceurl -vcodec libx264 -preset ultrafast  -tune zerolatency -acodec aac -strict -2 -b:a 16k -framerate $framerate -s $resolution -b:v $bitrate -bufsize $bitrate -g 10 -segment_list_size 10 -segment_wrap 10 -hls_flags delete_segments -f hls -metadata title="$camname" $webroot/$camname.m3u8  &
	

	pid=$!
	echo "$0 started ffmpeg OS PID $pid" | $bblogger $logfile
        wait $pid
	echo "$0 woke up because ffmpeg OS PID $pid exited or a signal was received" | $bblogger $logfile
done
exit 0


