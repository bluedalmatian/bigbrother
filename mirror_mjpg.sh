#!/bin/sh

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016 Andrew Wood                                       #
#                                                                  #
# mirror_mjpg.sh Bourne shell script to perform mirroring for each  #
# camera. Launched by bigbrotherd                                  #
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



usagestring="Syntax error.  Usage: $0 -cam proto://camera/url:port -name CameraName -log /path/to/log/file -cmd /path/to/ffmpeg -webroot /path/to/webroot"
copyrightstring="Simple BigBrother Copyright Andrew Wood 2016"


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



if [ $# -ne 10 ]
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

parseParams()
{
	if [ $1 == '-cam' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] &&  [ $2 != '-webroot' ]
	then
		sourceurl=$2
		return 0
		
	elif [ $1 == '-name' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] &&  [ $2 != '-webroot' ]
	then
		camname=$2
		return 0
	elif [ $1 == '-log' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] &&  [ $2 != '-webroot' ]
        then
                logfile=$2
                return 0
	elif [ $1 == '-cmd' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] &&  [ $2 != '-cmd' ] &&  [ $2 != '-log' ] &&  [ $2 != '-webroot' ]
        then
                ffmpegcommand=$2
                return 0
	elif [ $1 == '-webroot' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] &&  [ $2 != '-cmd' ] &&  [ $2 != '-log' ] &&  [ $2 != '-webroot' ]
	then	
		webroot=$2
		return 0

        fi

	
	#invalid params
	return 1
}

#read params and if sane set the corresponding vars

parseParams $1 $2

if [ $? -ne 0 ]
then
	echoUsage
	echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
	exit 1
fi

parseParams $3 $4

if [ $? -ne 0 ]
then
        echoUsage
	echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
        exit 1
fi

parseParams $5 $6

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
        exit 1
fi

parseParams $7 $8

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
        exit 1
fi


parseParams $9 ${10}

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
        exit 1
fi




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



#all params ok


pid=0 # dont match anything at startup
trap processSIGINT INT
trap processSIGTERM TERM
trap processSIGQUIT QUIT
keepGoing=1;

echo "$0 $1 $2 $3 $4 $5 $6 $7 $8 $9 ${10} started" | $bblogger $logfile


while [ $keepGoing -ne 0 ]
do
	pid=0 #temp reset to nothing to reduce risk of PID recycling problems

	###2>&1 redirect std error to std output so both can be piped as one (not used as it doesnt work well)


	$ffmpegcommand -y -loglevel fatal -i  $sourceurl -vcodec mjpeg  -s 640x480 -metadata title="$camname" -framerate 10  $webroot/$camname.mjpg  &


	pid=$!
	echo "$0 started ffmpeg OS PID $pid" | $bblogger $logfile
        wait $pid
	echo "$0 woke up because ffmpeg OS PID $pid exited or a signal was received" | $bblogger $logfile
done
exit 0


