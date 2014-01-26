#!/bin/bash
if [ $# -ne 2 ]
then
	echo "usage: sh start.sh <channel_address> <interval>"
	exit
fi
channel=$1
interval=$2
#channel ping

server_list="127.0.0.1"
IFS=,
servers=($server_list)
for idx in "${!servers[@]}";
do
    nohup ssh ${servers[$idx]} ping $channel | while read pong; do record=$(date +%Y"-"%m"-"%d" "%H":"%M":"%S); echo "[${servers[$idx]}] [$record] $pong"; done >>${servers[$idx]}.$channel.net.data &
done

nohup sh monitor.sh $channel &
