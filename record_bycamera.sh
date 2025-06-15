#!/bin/sh
###! /usr/bin/env bash

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016-2017 Andrew Wood                                  #
#                                                                  #
# record_bycamera.sh Bourne shell script to perform recording for  #
# each camera. Launched by bigbrotherd                             #
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


usagestring="Syntax error.  Usage: $0 -cam proto://camera/url:port -name CameraName -group GroupName -folder /path/to/ -log /path/to/log/file -cmd /path/to/ffmpeg -container TypeCode"
copyrightstring="Simple BigBrother Copyright Andrew Wood 2016-2017"


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


processSIGHUP()
{
        echo "Got SIGHUP"
        echo "$0 Got SIGHUP forwarding SIGINT to OS PID $pid" | $bblogger $logfile
        kill -INT $pid
}


if [ $# -ne 14 ]
then
	echoUsage
	echo "$0 started with incorrect number of arguments, cannot continue" | $bblogger $logfile
	exit 1
fi

SCRIPT=$(readlink -f "$0")
SCRIPTPATH=$(dirname "$SCRIPT")

sourceurl="" #safety default init
camname="" #safety default init
folder="" #safety default init
group="" #safety default init
logfile="/dev/null" #safety default init
ffmpegcommand="" #default init
bblogger="$SCRIPTPATH/bblogger"
container="" #default safety init


parseParams()
{
	if [ $1 == '-cam' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] && [ $2 != '-folder' ] && [ $2 != '-group' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] && [ $2 != '-container' ]
	then
		sourceurl=$2
		return 0
		
	elif [ $1 == '-name' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] && [ $2 != '-folder' ]  && [ $2 != '-group' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] && [ $2 != '-container' ]
	then
		camname=$2
		return 0
	elif [ $1 == '-folder' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] && [ $2 != '-folder' ] && [ $2 != '-group' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] && [ $2 != '-container' ]
	then
		folder=$2
		return 0
	elif  [ $1 == '-group' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] && [ $2 != '-folder' ] && [ $2 != '-group' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] && [ $2 != '-container' ]
	then
		group=$2
		return 0
	elif [ $1 == '-log' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] && [ $2 != '-folder' ] && [ $2 != '-group' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] && [ $2 != '-container' ]
        then
                logfile=$2
                return 0
	elif [ $1 == '-cmd' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] && [ $2 != '-folder' ] && [ $2 != '-group' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] && [ $2 != '-container' ]
        then
                ffmpegcommand=$2
                return 0
	elif [ $1 == '-container' ] && [ $2 != '-cam' ] && [ $2 != '-name' ] && [ $2 != '-folder' ] && [ $2 != '-group' ] &&  [ $2 != '-log' ] &&  [ $2 != '-cmd' ] && [ $2 != '-container' ]
	then
		container=$2
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

parseParams ${11} ${12}

if [ $? -ne 0 ]
then
        echoUsage
	echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
        exit 1
fi

parseParams ${13} ${14}

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
        exit 1
fi




#check all params have been initialised, they are not still the default null str
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
if [ $folder == "" ]
then
	 echoUsage
	 echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
         exit 1
fi
if [ $group == "" ]
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
if [ $ffmpegcommand = "" ]
then
         echoUsage
         echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
         exit 1
fi
if [ $container = "" ]
then	
	echoUsage
	echo "$0 started with incorrect arguments, cannot continue" | $bblogger $logfile
        exit 1
fi


#because $group is a folder we need to check it exists, and it must be just the dir name
#no forward slashes or dots allowed in it

groupvalidchars=$(echo "$group" | grep -c -E '\.|\/')
if [ $groupvalidchars -ne 0 ]
then
	echo "Error: Group Name $group must not contain slashes or dots, it must be the name of a directory under $folder/bycamera"
	echoUsage
	exit 1
fi 


if [ -d $folder/bycamera/$group ]
then
	#do nothing, dir exists
	echo "$group exists as a directory under $folder/bycamera so all OK" | $bblogger $logfile
else
	echo "Error: Group Name $group must exist as a directory under $folder/bycamera"
	echo "Error: Group Name $group must exist as a directory under $folder/bycamera" | $bblogger $logfile
	echoUsage
	exit 1
fi


#because $container is used as filename ext we have to ensure it does not contain invalid chars
containervalidchars=$(echo "$group" | grep -c -E '\.|\/|\*|\||>|<|\s')
if [ $containervalidchars -ne 0 ]
then
        echo "Error: Container Format  $container must not contain slashes,dots,asterisks,pipes,angle brackets or spaces"
        echoUsage
        exit 1
fi


#all params ok

previousDayValue="NoDay" #dont match any day at startup
pid=0 #dont match anything at startup
trap processSIGINT INT
trap processSIGTERM TERM
trap processSIGQUIT QUIT
trap processSIGHUP HUP
keepGoing=1;

echo "$0 $1 $2 $3 $4 $5 $6 $7 $8 $9 ${10} ${11} ${12} ${13} ${14} started" | $bblogger $logfile


while [ $keepGoing -ne 0 ]
do
	pid=0 #temp reset to nothing to reduce risk of PID recycling problems
	timenow=$(date +%H%M) #gives HHMM  - avoid colon in time to allow Windows compat with filenames
	day=$(date +%A) #gives Monday Tuesday etc
	todaysdate=$(date +%Y-%m-%d) #gives YYYY-MM-DD
	minspast=$(date +%M)


	if [ $minspast -eq 00 ]
	then
		secs=3600
	else
		sixty=60
		mins2run=$(expr $sixty \- $minspast)
		secs=$(expr $mins2run \* $sixty)
	fi
	echo "It is $minspast minutes past the hour, recording for $secs seconds to align with next hour"

	#check if $folder/bycamera/$group/$camname--$day--$todaysdate--$timenow.$container exits
        #if so it could be because clock has gone back for DST or multiple HUPs recently, in which case append -1,-2 etc afer time

	dstadjustment=""
        dstadjustmentno=0
        while [ -f $folder/bycamera/$group/$camname--$day--$todaysdate--$timenow$dstadjustment.$container ]
        do
                dstadjustmentno=$(expr $dstadjustmentno \+ 1)
                dstadjustment="-"$dstadjustmentno
                echo "$0 has detected clock may have gone back or HUP signalled, testing for presence of file "$folder/bycamera/$group/$camname--$day--$todaysdate--$timenow$dstadjustment.$container | $bblogger $logfile
        done

	
	$ffmpegcommand -loglevel fatal -y -t $secs -i $sourceurl -acodec copy -vcodec copy -metadata title="$camname--$todaysdate--$timenow" $folder/bycamera/$group/$camname--$day--$todaysdate--$timenow$dstadjustment.$container  &
	pid=$!
	echo "$0 started ffmpeg OS PID $pid" | $bblogger $logfile
        wait $pid
        echo "$0 woke up because ffmpeg OS PID $pid exited or a signal was received" | $bblogger $logfile


done
exit 0


