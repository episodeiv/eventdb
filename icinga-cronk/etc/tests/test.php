#!/usr/bin/php
<?php

$ip = "::ffff:160.139.144.10";
$packed = inet_pton($ip);
print_r(unpack("C*",$packed));
echo inet_ntop($packed);
