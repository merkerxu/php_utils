#!/bin/bash
if [ $# -ne 1 ]
then
	echo "usage: sh stop.sh <channel_address>"
	exit
fi
channel=$1
#stop monitor first
ps aux | grep "monitor.sh $channel" | grep  -v "grep" | awk '{print $2}' | xargs kill
#stop channel ping
ps aux | grep "ping $channel" | grep -v "grep" | awk '{print $2}' | xargs kill
