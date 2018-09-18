<?php

$PRODUCTION = false;

if ($PRODUCTION) {
    $dbAddress = "127.0.0.1";
    $dbUserName = "yourusername";
    $dbPassword = "yourpassword";
} else {
    $dbAddress = "127.0.0.1";
    $dbUserName = "yourpassword";
    $dbPassword = "";
}

?>