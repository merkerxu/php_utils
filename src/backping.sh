#!/bin/bash
ping www.mo9.com.cn | while read pong; do record=$(date +%Y"-"%m"-"%d" "%H":"%M":"%S); echo "$record: $pong"; done
