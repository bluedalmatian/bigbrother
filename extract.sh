#!/bin/sh
####! /usr/bin/env bash

####################################################################
# BigBrother  CCTV Recording & Live Viewing (mirroring) software   #
# Copyright 2016 Andrew Wood                                       #
#                                                                  #
# extract.sh Bourne shell script to extract a segment from a	   #
# recording      						   #
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
# Museum Trust at www.communicationsmuseum.org.uk/donate           #
####################################################################


usagestring="Syntax error.  Usage: $0 -in /path/to/inputfile -out /path/to/outputfile -start HH:MM:SS -duration HH:MM:SS"
copyrightstring="Simple BigBrother Copyright Andrew Wood 2016"


echoUsage()
{
        echo " "
        echo $usagestring
        echo " "
        echo $copyrightstring
        echo " "
}


if [ $# -ne 8 ]
then
        echoUsage
        echo "$0 started with incorrect number of arguments, cannot continue"
        exit 1
fi



inputfile="" #safety default init
outputfile="" #safety default init
start="" #safety default init
duration="" #safety default init


parseParams()
{
        if [ $1 == '-in' ] && [ $2 != '-in' ] && [ $2 != '-out' ] && [ $2 != '-start' ] &&  [ $2 != '-duration' ]
        then
                inputfile=$2
                return 0

        elif [ $1 == '-out' ] && [ $2 != '-out' ] && [ $2 != '-in' ] && [ $2 != '-start' ]  &&  [ $2 != '-duration' ]
        then
                outputfile=$2
                return 0
        elif [ $1 == '-start' ] && [ $2 != '-start' ] && [ $2 != '-in' ] && [ $2 != '-out' ]  &&  [ $2 != '-duration' ]
        then
                start=$2
                return 0
        elif [ $1 == '-duration' ] && [ $2 != '-duration' ] && [ $2 != '-in' ] && [ $2 != '-out' ]  &&  [ $2 != '-start' ]
        then
                duration=$2
                return 0
        fi
        #invalid params
        return 1
}

parseParams $1 $2

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue"
        exit 1
fi

parseParams $3 $4

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue"
        exit 1
fi

parseParams $1 $2

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue"
        exit 1
fi

parseParams $5 $6

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue"
        exit 1
fi

parseParams $1 $2

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue"
        exit 1
fi

parseParams $7 $8

if [ $? -ne 0 ]
then
        echoUsage
        echo "$0 started with incorrect arguments, cannot continue"
        exit 1
fi




#check all params have been initialised, they are not still the default
if [ $inputfile == "" ]
then
         echoUsage
         echo "$0 started with incorrect arguments, cannot continue"
         exit 1
fi

if [ $outputfile == "" ]
then
         echoUsage
         echo "$0 started with incorrect arguments, cannot continue"
         exit 1
fi

if [ $start == "" ]
then
         echoUsage
         echo "$0 started with incorrect arguments, cannot continue"
         exit 1
fi

if [ $duration == "" ]
then
         echoUsage
         echo "$0 started with incorrect arguments, cannot continue"
         exit 1
fi



#start must be hh:mm:ss (start time)
#duration must be hh:mm:ss (time to run for)

matchinglines=$(echo "$start" | grep -c -E '^[0-9]{2}:[0-9]{2}:[0-9]{2}$')
if [ $matchinglines -ne 1 ]
then
        echo "Error: -start must be in format HH:MM:SS e.g 00:10:00 to start extracting 10 minutes in"
        echoUsage
        exit 1
fi

matchinglines=$(echo "$duration" | grep -c -E '^[0-9]{2}:[0-9]{2}:[0-9]{2}$')
if [ $matchinglines -ne 1 ]
then
        echo "Error: -duration must be in format HH:MM:SS e.g 00:00:30 to extract 30 seconds of video"
        echoUsage
        exit 1
fi


#to prevent accidental overwriting of input, check input & output files are not same
if [ $inputfile == $outputfile ]
then
	echo "Error: Input file and output file must not be the same"
	echoUsage
	exit 1
fi


ffmpeg -loglevel fatal -ss $start -i $inputfile -t $duration -vcodec copy -acodec copy $outputfile
