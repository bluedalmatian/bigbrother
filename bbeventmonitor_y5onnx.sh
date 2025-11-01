#! /usr/bin/env bash

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2025 Andrew Wood                                  #
#                                                                  #
# bbeventmonitor_y5onnx.sh Bourne shell script to perform event    #
# detection for each camera. Launched by bigbrotherd               #  
# 										                           #
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


#Note: Using $10 doesn’t work – it’s interpreted as $1 concatenated with a 0
#so use ${10} instead.Likewise for $11 $12 etc


usagestring="Syntax error.  Usage: $0 -cam proto://camera/url:port -name CameraName -group GroupName -log /path/to/log/file -elog /path/to/event/log/file -elogby TYPE -events CODE [IGNORETIMES] CODE [IGNORETIMES]"
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


if [ $# -lt 15 ]
then
	echo "Too few arguments given"
	echo "Got {$#}"
	echo "$0 started with incorrect number of arguments, cannot continue" | $bblogger $logfile
	echoUsage
	exit 1
fi

if [ $# -gt 15 ]
then
	if [ $# -ne 17 ]
    then  
		echo "Too few arguments given, -events must consist of pairs of event codes and ignore times"
	   echo "$0 started with incorrect number of arguments, cannot continue" | $bblogger $logfile
	   echoUsage
	   exit 1
    fi
fi

SCRIPT=$(readlink -f "$0")
SCRIPTPATH=$(dirname "$SCRIPT")


sourceurl="" #safety default init
camname="" #safety default init
logfile="/dev/null" #safety default init
elogfile="/dev/null" #safety default init
elogby="" #safety default init
groupname="" #safety default init
eventstr="" #safety default init
eventstrinit=0 #safety default init
bblogger="$SCRIPTPATH/bblogger"
bbeventmonitor="$SCRIPTPATH/bbeventmonitor_y5onnx"

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
    elif [ ${!x} == "-elog" ]
    then
        elogfile=${!y}
	elif [ ${!x} == "-elogby" ]
	then
		elogby=${!y}
	elif [ ${!x} == "-group" ]
	then
		groupname=${!y}
    elif [ ${!x} == "-events" ]
    then
        if [ $# -eq 11 ]
        then
            end=$((y + 1))
        else
            end=$((y + 3))
        fi
        for (( z=$y; z<=end; z++ ))
        do
            eventstr="${eventstr} ${!z}"
            eventstrinit=1
        done
        
    fi
      
done


echo $sourceurl
echo $camname
echo $logfile
echo $elogfile
echo $eventstr
echo $groupname
echo $elogby

#check all params have been initialised, they are not still the default 0
if [ $sourceurl == "" ]
then
	 echoUsage
	echo "camurl param not initialised"
	 echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
     exit 1
fi

if [ $camname == "" ]
then
	 echoUsage
	 echo "camname param not initialised"
	 echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
      exit 1
fi

if [ $logfile == "/dev/null" ]
then
	echoUsage
	echo "logfile param not initialised"
	exit 1
fi

if [ $elogfile == "/dev/null" ]
then
	echoUsage
	echo "elogfile param not initialised"
	exit 1
fi

if [ $elogby == "" ]
then
	echo "elogby param not initialised"
	echoUsage
	exit 1
fi

if [ $elogby != "D" ] && [ $elogby != "C" ]
then
	echo "elogby invalid"
	echoUsage
	exit 1
fi

if [ $groupname == "" ]
then
	echo "groupname param not initialised"
	echoUsage
	exit 1
fi

if [ $eventstrinit -ne 1 ]
then
	echo "events param not initialised"
	echoUsage
	exit 1
fi

#all params ok


pid=0 # dont match anything at startup
trap processSIGINT INT
trap processSIGTERM TERM
trap processSIGQUIT QUIT
keepGoing=1;

if [ $# -eq 13 ]
        then
            echo "$0 $1 $2 $3 $4 $5 $6 $7 $8 $9 ${10} ${11} ${12} ${13} started" | $bblogger $logfile
        else
            echo "$0 $1 $2 $3 $4 $5 $6 $7 $8 $9 ${10} ${11} ${12} ${13} ${14} ${15}  started" | $bblogger $logfile
        fi

while [ $keepGoing -ne 0 ]
do
	pid=0 #temp reset to nothing to reduce risk of PID recycling problems
    
    #bbeventmonitor /path/log /path/eventlog CamX rtsp://x.x.x.x P [*]
    
    $bbeventmonitor $logfile $elogfile $elogby $camname $groupname $sourceurl $eventstr &

	pid=$!
	echo "$0 started bbeventmonitor OS PID $pid" | $bblogger $logfile
        wait $pid
	echo "$0 woke up because bbeventmonitor OS PID $pid exited or a signal was received" | $bblogger $logfile
done
exit 0


