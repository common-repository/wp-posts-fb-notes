<?php

require (ABSPATH.WPINC.'/pluggable.php');
require (WP_PLUGIN_DIR."/wp-posts-fb-notes/facebook.php");

global $wpfb;


function wpfb_connect(){
global $wpfb;
$wpfb->appid = get_option("wpfb_appid");
$wpfb->appsecret = get_option("wpfb_appsecret");

if($wpfb->appid && $wpfb->appsecret){
	$wpfb->fb = new Facebook(array(
	  'appId'  => $wpfb->appid ,
	  'secret' => $wpfb->appsecret,
	  'cookie' => true,
	));
	$wpfb->session = $wpfb->fb->getSession();

$wpfb->fbuid = get_option("wpfb_fbuid");
$wpfb->fbpid = get_option("wpfb_fbpid");
$wpfb->fbaid = ($wpfb->fbpid)? $wpfb->fbpid: $wpfb->fbuid;

if(! get_option("wpfb_fromblog"))update_option("wpfb_fromblog","This blog entry is written in {permlink} by {author}<br/><br/> {content}");
if(! get_option("wpfb_cfromblog"))update_option("wpfb_cfromblog","new replay from the blog post:{br} author:{user}{br}{content}");

if ($wpfb->session) {
  try {
    $wpfb->me = $wpfb->fb->api('/me');
  } catch (FacebookApiException $e) {
    $wpfb->session="";
  }
}

}
}

wpfb_connect();