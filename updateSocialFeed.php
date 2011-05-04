<?php

/**
 * This is the file that is called via ajax 
 */

session_start();
require_once 'SocialFeed.class.php';

$feed = new SocialFeed('localhost','username','password','db_name');
$result = $feed->getdata($_REQUEST['limit'], $_REQUEST['since']);

echo json_encode($result);
