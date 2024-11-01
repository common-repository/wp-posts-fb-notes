<?php

require_once(WP_PLUGIN_DIR."/wp-posts-fb-notes/config.php");

// this function checks for admin pages
if (!function_exists('is_admin_page')) {
	function is_admin_page() {
		if (function_exists('is_admin')) {
			return is_admin();
		}
		if (function_exists('check_admin_referer')) {
			return true;
		} else {
			return false;
		}
	}
}

function wpfb_is_authorized() {
	global $user_level;
	if (function_exists("current_user_can")) {
		return current_user_can('activate_plugins');
	} else {
		return $user_level > 5;
	}
}

function wpfb_get_fbcommects($object_id) {
	global $wpfb;
	$commects = $wpfb->fb->api($object_id."/comments");
	return $commects['data'];
}

function wpfb_get_fbnote($note_id) {
	global $wpfb;
	try {
		$notes = $wpfb->fb->api($note_id);
		return $notes;
	} catch (FacebookApiException $e) {
		error_log($e);
	}      
}

function wpfb_get_fblink($link_id) {
	global $wpfb;
	try {
		$notes = $wpfb->fb->api($link_id);
		return $link;
	} catch (FacebookApiException $e) {
		error_log($e);
	}      
}

function wpfb_get_fbnotes() {
	global $wpfb;
        $notes = $wpfb->fb->api($wpfb->fbaid."/notes");
        return $notes['data'];
}

function wpfb_get_fblinks() {
	global $wpfb;
        $links = $wpfb->fb->api($wpfb->fbaid."/links");
        return $links['data'];
}

function wpfb_get_fbpages() {
	global $wpfb;
        $pages = $wpfb->fb->api($wpfb->fbuid."/accounts");
        return $pages['data'];
}

function wpfb_get_not_pairs_fb() {
	global $wpfb,$wpdb;
	$meta_key = 'note_id';
	$fbpairs =$wpdb->get_results($wpdb->prepare("SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = %s", $meta_key));
	$start=true;
	$notes_id;
	foreach($fbpairs as $fbpair)
		$notes_id[]=$fbpair->meta_value;
	$notes = wpfb_get_fbnotes();
	$npnotes;
	if(is_array($notes_id)){
		foreach($notes as $note){
			if(!in_array($note['id'],$notes_id))
				$npnotes[]=$note;
				
		}
	}else{
		$npnotes=$notes;
	}
	return $npnotes;
}

function wpfb_comment_exist_in_fb($wpcomment,$fbcomments) {
	$user= $wpcomment->comment_author;
	$find =false;
	if(is_array($fbcomments))
	foreach($fbcomments as $fcomment){

		$msg= get_option('wpfb_cfromblog');
		$from = array("{br}", "{user}", "{content}");
		$to   = array("\n", $wpcomment->comment_author, $wpcomment->comment_content);
		$msg = str_replace($from, $to, $msg);
		if( wpfb_clean_fb_comments($fcomment['message']) == wpfb_clean_fb_comments($wpcomment->comment_content)
		    || wpfb_clean_fb_comments($fcomment['message']) ==  wpfb_clean_fb_comments($msg) )
			return true;
	}
	return false;
}

function wpfb_clean_fb_comments($str){
	$nl = array(" ","\r", "\n", "\t", "\s");
	$str = clean_comment_tag($str); 
	$str = str_replace($nl,"",$str);
	$str = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|',"",$str);
	return $str;
}

/*
WP API Helper Methos
*/

function wpfb_get_pairs() {
	global $wpdb;
	$meta_key = 'note_id';
	return $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE meta_key = %s", $meta_key));
}

function wpfb_get_pairs_links() {
	global $wpdb;
	$meta_key = 'link_id';
	$result = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE meta_key = %s", $meta_key));
        $pairs= array();
        foreach($result as $row)
                $pairs[$row->post_id][]=$row->meta_value;
        return $pairs;
}

function wpfb_get_not_pairs_wp() {
	global $wpdb;
	$meta_key = 'note_id';
	return $wpdb->get_results($wpdb->prepare("SELECT * from $wpdb->posts WHERE post_status = 'publish' AND post_type = 'post' AND ID NOT IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s)", $meta_key));
}

function wpfb_get_not_pairs_wp_links() {
	global $wpdb;
	$meta_key = 'link_id';
	return $wpdb->get_results($wpdb->prepare("SELECT * from $wpdb->posts WHERE post_status = 'publish' AND post_type = 'post' AND ID NOT IN (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s)", $meta_key));
}

function wpfb_detect_pairs() {
	global $wpfb,$wpdb;
	$notes= wpfb_get_fbnotes();
	foreach($notes as $note)
		if ($post_id = wpfb_isBlog($note['subject']))
			add_post_meta($post_id, 'note_id', $note['id'], true) or update_post_meta($post_id, 'note_id', $note['id']);
}

function wpfb_detect_pairs_links() {
	global $wpfb,$wpdb;
	$links = wpfb_get_fblinks();
	foreach($links as $link)
		if ($post_id = wpfb_isBlog_link($link['link']))
			add_post_meta($post_id, 'link_id', $link['id']);
}

function wpfb_reset_pairs() {
	global $wpdb;
	$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'note_id'");
}

function wpfb_reset_pairs_links() {
	global $wpdb;
	$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = 'link_id'");
}

function wpfb_comment_exist_in_wp($fbcomment,$wpcomments) {
	global $user_id;

	// ensure that this facebook comment doesnot come from blog
	if(wpfb_isFromBlog($fbcomment['message']))return true;

	$find =false;
	if(is_array($wpcomments))
	foreach($wpcomments as $wpcomment){
		if(comment_clean($fbcomment['message']) == comment_clean($wpcomment->comment_content))
			return true;
	}
	return false;
}

function wpfb_add_comment_in_wp($post_id,$fbcomment) {
	global $wpfb,$wpdb;
	
	$user = $wpfb->fb->api($fbcomment['from']['id']);
	/*if(! $user ){
		$user= $facebook->api_client->pages_getInfo($page_id,'name','','');
		$user[0]['email']="wpfb@orient.ps";
		$user[0]['uid']=$user_id;
	}*/
	$comment_data = array();
	$comment_data["comment_post_ID"] = $post_id;
	$comment_data["comment_author"] =  $user['name'];
	$comment_data["comment_author_email"] = $user['email'];
	$comment_data["comment_author_IP"] = "127.0.0.1";
	$comment_data["comment_author_url"] = "http://facebook.com/profile.php?id=".$user['id'];
	$time=date("Y-m-d H:i:s",strtotime($fbcomment['created_time']));

	$comment_data["comment_date"] = $time;
	$comment_data["comment_date_gmt"] = $time;
	$comment_data["comment_content"] = $fbcomment['message'];
	$comment_data["comment_approved"] = get_option('wpfb_auto');
	$comment_data["comment_agent"] = "Orient.ps FBNCom";

	$comment_id = wp_insert_comment($comment_data);
	update_comment_meta($comment_id, 'fbuid', $fbcomment['from']['id']);
}









function users_hasAppPermission($perms,$uid="",$access_token=""){
	global $wpfb;
    if(!$uid){ $uid = $wpfb->fbuid; $access_token=$wpfb->session['access_token']; }
    $api_call = array( 
	'method' => 'users.hasAppPermission', 
	'uid' => $uid, 
	'ext_perm' => $perms, 
	'access_token' => $access_token
    ); 
    return $wpfb->fb->api($api_call); 
}
function users_isAppUser($uid,$access_token){
	global $wpfb;
    $api_call = array( 
	'method' => 'users.isAppUser', 
	'uid' => $uid, 
        'access_token' => $access_token
    ); 
    return $wpfb->fb->api($api_call); 
}
function pages_isFan($uid,$access_token){
	global $wpfb;
    $api_call = array( 
	'method' => 'pages.isFan',
        'page_id' => $wpfb->fbpid, 
	'uid' => $uid, 
        'access_token' => $access_token
    ); 
    return $wpfb->fb->api($api_call); 
}

function notes_creat($subject,$message,$uid,$access_token){
	global $wpfb;
    $note = $wpfb->fb->api($uid.'/notes', 'post',
                        array('access_token' => $access_token, 'subject'=> $subject, 'message' => $message));  
    $note['id'] = number_format((float) $note['id'],0,'','');
return $note['id'];
}

function posts_addComment($post_id,$message,$uid,$access_token){
	global $wpfb;
//die("qweqwe".$access_token);
    $comment = $wpfb->fb->api('/'.$post_id.'/comments', 'post',
                            array('access_token' => $access_token, 'message' => $message));  
    return $comment['id'];
}


function wpfb_add_comment_in_fb($post_id,$wpcomment)  {
	global $wpfb,$wpdb;
	$message="";

        $cuser_id= get_user_meta( $wpcomment->user_id, 'fbuid', true);
        $access_token=get_user_meta( $wpcomment->user_id, 'access_token', true);
	if(!$cuser_id || !$access_token){

	        $cuser_id = get_comment_meta($wpcomment->comment_ID, 'fbuid', true);
                $access_token=get_comment_meta($wpcomment->comment_ID, 'access_token', true);
//die($wpcomment->comment_ID." ".$cuser_id." ".$access_token);
                if($cuser_id && !$access_token){
                        // get access token from user if connected
                        $meta_key = 'fbuid';
                        $user_id = $wpdb->get_var($wpdb->prepare("SELECT ID from $wpdb->users WHERE ID IN (SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s and meta_value= %s)", $meta_key,$cuser_id));
                        $access_token = get_user_meta( $user_id, 'access_token', true);
                }
        }
	if($cuser_id && $access_token){
		$can= users_isAppUser($cuser_id,$access_token);
                //echo "app_user ".$can."| ";
		if($can && $wpfb->fbpid){
			$can= pages_isFan($cuser_id,$access_token);
			//echo "publish_stream ".$can."| ";
		}
                if($can){
                    $can=users_hasAppPermission('publish_stream',$cuser_id,$access_token);
                    //echo "publish_stream ".$can."| ";
                }
		if($can){
			$can= users_hasAppPermission('offline_access',$cuser_id,$access_token);
			//echo "offline_access ".$can."| ";
		} 
		
		if(! $can){
			$temp = $cuser_id;
			$cuser_id = $wpfb->fbuid;
			$access_token = $wpfb->session['access_token'];
			$message=get_option('wpfb_cfromblog');
			$from = array("{br}", "{user}", "{content}");
			$to   = array("\n", $wpcomment->comment_author, $wpcomment->comment_content);
			$message = str_replace($from, $to, $message);
		}
		else
			$message= $wpcomment->comment_content;
	}else{
		$cuser_id = $wpfb->fbuid;
                $access_token = $wpfb->session['access_token'];
		$message  = get_option('wpfb_cfromblog');
		$from = array("{br}", "{user}", "{content}");
		$to   = array("\n", $wpcomment->comment_author, $wpcomment->comment_content);
		$message = str_replace($from, $to, $message);
	}
	$message= strip_tags($message);
        posts_addComment($post_id,$message,$cuser_id,$access_token);
}



function wpfb_isFromBlog($text) {
    $str = get_option('wpfb_cfromblog');
    $n = stripos($str,'{');
    $str = substr($str,1,$n-1);
    return stripos($text,$str);

}

function wpfb_isFromFacebook($agent) {
	return ($agent == "Orient.ps FBNCom");
}

function wpfb_parse_query($var)
 {
  $var  = parse_url($var, PHP_URL_QUERY);
  $var  = html_entity_decode($var);
  $var  = explode('&', $var);
  $arr  = array();

  foreach($var as $val)
   {
    $x          = explode('=', $val);
    $arr[$x[0]] = $x[1];
   }
  unset($val, $x, $var);
  return $arr;
 }

function wpfb_isBlog($title) {
	global $wpdb;
	return $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_status = 'publish'",$title));
}

function wpfb_isBlog_link($link) {
	global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid = %s AND post_status = 'publish'",$link));
if($post_id) return $post_id;
else{
$todel=array("http://","www.");
$blog = str_replace($todel,"",get_bloginfo('url'));
if(stripos($link,$blog)){
$q = wpfb_parse_query($link);
$p = $q['p'];
return $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE ID = %s AND post_status = 'publish'",$p));
}
else return;
}
}

function wpfb_getTitle($id) {
	$post = get_post($id);
	return $post->post_title;
}



function comment_clean($str){
	$nl = array("\r", "\n", "\t"," ");
        return str_replace($nl,"",$str);
}

function clean_comment_tag($text) {
	return strip_tags($text);
}

function clean_post_tag($text) {
	return strip_tags($text, '<br/><img/><b><i><u><s><big><small><a><ul><ol><li><blockquote><h1><h2><h3>');
}