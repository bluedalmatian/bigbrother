#! /usr/bin/env bash

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2025 Andrew Wood                                  #
#                                                                  #
# bbptzcameracontrollerd_onvif.sh Bourne shell script to launch    #
# PTZ control daemon. Launched by bigbrotherd               	   #  
# 		    			                                           #
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


usagestring="Syntax error.  Usage: $0 -conf /path/to/ptz.conf -log /path/to/log/file"
copyrightstring="BigBrother Copyright Andrew Wood 2016-2026"

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


if [ $# -lt 4 ]
then
	echo "Too few arguments given"
	echo "Got {$#}"
	echo "$0 started with incorrect number of arguments, cannot continue" | $bblogger $logfile
	echoUsage
	exit 1
fi


SCRIPT=$(readlink -f "$0")
SCRIPTPATH=$(dirname "$SCRIPT")


logfile="/dev/null" #safety default init
conffile="/dev/null" #safety default init
bblogger="$SCRIPTPATH/bblogger"
bbptzd="$SCRIPTPATH/bbptzcameracontrollerd_onvif"

for (( x=1; x<=$#; x++ ))
do
    y=$((x + 1))
    #echo "Argument $x is: ${!x}"
    if [ ${!x} == "-log" ]
    then
        logfile=${!y}
    elif [ ${!x} == "-conf" ]  
	then
		conffile=${!y}
    fi
      
done



echo $logfile
echo $conffile


#check all params have been initialised, they are not still the default 0


if [ $logfile == "/dev/null" ]
then
	echoUsage
	echo "logfile param not initialised"
	exit 1
fi

if [ $conffile == "/dev/null" ]
then
	echoUsage
	echo "conffile param not initialised"
	exit 1
fi


#all params ok


pid=0 # dont match anything at startup
trap processSIGINT INT
trap processSIGTERM TERM
trap processSIGQUIT QUIT
keepGoing=1;


echo "$0 $1 $2 $3 $4  started" | $bblogger $logfile
        
while [ $keepGoing -ne 0 ]
do
	pid=0 #temp reset to nothing to reduce risk of PID recycling problems
    
        #bbptzcameracontrollerd_onvif /path/to/bigbrother_ptz.conf /path/to/logfile.log
    
        $bbptzd $conffile $logfile &

	pid=$!
	echo "$0 started bbptzcameracontrollerd OS PID $pid" | $bblogger $logfile
        wait $pid
	echo "$0 woke up because bbptzcameracontrollerd OS PID $pid exited or a signal was received" | $bblogger $logfile
done
exit 0


