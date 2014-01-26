#!/bin/bash
if [ $# -ne 2 ]
then
	echo "usage: sh stop.sh <channel_address> <interval>"
	exit
fi

channel=$1
interval=$2

from="netmonitor@kuaidingyue.com"
to="merkerxu@163.com"

#send mail function
function send_mail()
{
    email_subject='NetMonitor'
    email_date=$(date "+%Y-%m-%d %H:%M:%S")
    email_subject=$email_subject"_"$email_date"_"$channel

    tail -n 1 "$1" | formail -I "From: $from" -I "MIME-Version:1.0" -I "Content-type: text/plain;charset=utf-8" -I "Subject: $email_subject" -I "To: $to" | /usr/sbin/sendmail -oi $to
}

#file moinitor
while true
do
  files=`ls *.${channel}.net.data`
  for file in $files
  do
    sys_ts=`date +%s`
    file_ts=`stat -c %Y $file`
    lag=$(($sys_ts-$file_ts))
    if [ $lag -gt 10 ]
    then
      send_mail $file
    fi
  done
  sleep 5
done
