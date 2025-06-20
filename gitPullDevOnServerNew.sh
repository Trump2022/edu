#!/usr/bin/expect
set username [lindex $argv 0]
set password [lindex $argv 1]
set timeout 20
cd /www/wwwroot/lbkirin.com/edu_dev
spawn git checkout dev
spawn git pull origin dev
expect "Username*"
send  "$username\r"
expect  "Password*"
send  "$password\r"
interact