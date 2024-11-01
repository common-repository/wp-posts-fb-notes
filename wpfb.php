<?php
/* Plugin Name: WP Posts-FB Notes
 Plugin URI: http://www.orient.ps
 Description: Facebook Connect & Facebook <> WordPress using Facebook Graph API ... more soon.
 Version: 1.2.6
 Author: Mohammed S Shurrab
 Author URI: http://www.orient.ps
*/

require_once(WP_PLUGIN_DIR."/wp-posts-fb-notes/config.php");
include(WP_PLUGIN_DIR."/wp-posts-fb-notes/helper.php");
include(WP_PLUGIN_DIR."/wp-posts-fb-notes/admin.php");
include(WP_PLUGIN_DIR."/wp-posts-fb-notes/connect.php");

get_currentuserinfo();


// just initilaize the meta data of the application
register_activation_hook( __FILE__, 'wpfb_activate');
function wpfb_activate() {
	add_option("wpfb_auto","0",'',"yes");
	add_option("wpfb_fbuid","",'',"yes");
	add_option("wpfb_appid","",'',"yes");
	add_option("wpfb_appsecret","",'',"yes");
	add_option("wpfb_cfromblog","This blog entry is written in {permlink} by {author}<br/><br/> {content}",'',"yes");
	add_option("wpfb_cfromblog","new replay from the blog post:{br} author:{user}{br}{content}",'',"yes");
}


/*
Common Helper Methos
*/
add_action('import_wpfb_comments_hook','wpfb_connect_notes');
function wpfb_connect_notes() {
	global $wpfb,$wpdb;
	$bp=0;
	$fc=0;
	$wpc=0;
	$new_com;
	$test=get_option('wpfb_test');
	$pairs=wpfb_get_pairs();
	$bp = count($pairs);
	foreach($pairs as $pair){
		$fbcomments= wpfb_get_fbcommects($pair->meta_value);
			
		$wpcomments= get_comments('post_id='.$pair->post_id.'&order=ASC');
		
		if(is_array($fbcomments))
		foreach($fbcomments as $fbcomment){
			if (!wpfb_comment_exist_in_wp($fbcomment,$wpcomments)) {
				if(!$test)
					wpfb_add_comment_in_wp($pair->post_id,$fbcomment);
				else{
					$new_com['fb'][$fc++]=$fbcomment;
				}
			}
		}

		$wpcomments= get_comments('status=approve&post_id='.$pair->post_id.'&order=ASC');
		if(is_array($wpcomments))
		foreach($wpcomments as $wpcomment){
			if (!wpfb_comment_exist_in_fb($wpcomment,$fbcomments)) {
				if(!$test){
					wpfb_add_comment_in_fb($pair->meta_value,$wpcomment);
				}
				else{
					$new_com['wp'][$wpc++]=$wpcomment;
				}
			}
		}
		//print_r($wpcomments);
		//print_r($fbcomments);
	}
	if($test){
		
		echo "<h1>Test mode</h1>";
		echo $bp." facebook note found in your blog<br/>";
		echo "<h3>New from facebook</h3>";
		echo $fc." new facebook comments found<br/>";
		if(is_array($new_com['fb']))
		foreach($new_com['fb'] as $fb)
			echo $fb['message']."<hr/>";
		
		echo "<h3>New from wordpress</h3>";
		echo $wpc." new wordpress comments found<br/>";
		if(is_array($new_com['wp']))
		foreach($new_com['wp'] as $wp)
			echo $wp->comment_content."<hr/>";
		echo "<br/>Test complete...<br/>";
	}
}


function wpfb_connect_links() {
    global $wpfb,$wpdb;
    $bp=0;
    $fc=0;
    $wpc=0;
    $new_com;
    $test=get_option('wpfb_test');
    $pairs=wpfb_get_pairs_links();
    $bp = count($pairs);
    foreach($pairs as $post => $links){
        foreach($links as $link){
        $pair->meta_value = $link;
        $pair->post_id = $post;
        $fbcomments= wpfb_get_fbcommects($pair->meta_value);
            
        $wpcomments= get_comments('post_id='.$pair->post_id.'&order=ASC');
        
        if(is_array($fbcomments))
        foreach($fbcomments as $fbcomment){
            if (!wpfb_comment_exist_in_wp($fbcomment,$wpcomments)) {
                if(!$test)
                    wpfb_add_comment_in_wp($pair->post_id,$fbcomment);
                else{
                    $new_com['fb'][$fc++]=$fbcomment;
                }
            }
        }

        $wpcomments= get_comments('status=approve&post_id='.$pair->post_id.'&order=ASC');
        if(is_array($wpcomments))
        foreach($wpcomments as $wpcomment){
            if (!wpfb_comment_exist_in_fb($wpcomment,$fbcomments)) {
                if(!$test){
                    wpfb_add_comment_in_fb($pair->meta_value,$wpcomment);
                }
                else{
                    $new_com['wp'][$wpc++]=$wpcomment;
                }
            }
        }
        //print_r($wpcomments);
        //print_r($fbcomments);
    }
    }
    if($test){
        
        echo "<h1>Test mode</h1>";
        echo $bp." facebook link found in your blog<br/>";
        echo "<h3>New from facebook</h3>";
        echo $fc." new facebook comments found<br/>";
        if(is_array($new_com['fb']))
        foreach($new_com['fb'] as $fb)
            echo $fb['message']."<hr/>";
        
        echo "<h3>New from wordpress</h3>";
        echo $wpc." new wordpress comments found<br/>";
        if(is_array($new_com['wp']))
        foreach($new_com['wp'] as $wp)
            echo $wp->comment_content."<hr/>";
        echo "<br/>Test complete...<br/>";
    }
}


/*//add_action('comment_post', 'wpfb_comment_post_publish');
function wpfb_comment_post_publish($comment_id)  {
	global $facebook,$wpdb,$user_id,$api_id;

	if(wpfb_isFromFacebook($comment->comment_agent)) return;
	$wpcomment= get_comment($comment_id);
	$post = get_post($wpcomment->comment_post_ID);

	wpfb_connect();
	$notes=$facebook->api_client->notes_get($api_id,'');

	$note_id=0;
	foreach($notes as $note)
	if ($post->post_title == $note['title'])
	$note_id=$note['note_id'];

	$fbcomments= $fbcomments=$facebook->api_client->fql_query('SELECT post_id, object_id, fromid, text, time FROM comment WHERE object_id ='.$note_id);
	if(!wpfb_comment_exist_in_fb($wpcomment,$fbcomments))
	wpfb_add_comment_in_fb($note_id,$wpcomment);
}

//add_action('wp_set_comment_status', 'wpfb_comment_status_publish',10,2);
function wpfb_comment_status_publish($comment_id,$comment_status)  {
	if($comment_status != 'approve') return;
	wpfb_comment_post_publish($comment_id);
} 

add_action('wpfb_permissions','wpfb_permissions');
function wpfb_permissions() {
	echo '<script src="http://static.ak.connect.facebook.com/connect.php/en_US" type="text/javascript"></script>
			<script type="text/javascript">
				 FB.init("<?=get_option("wpfb_apikey");?>");
				 function why_dlg(){
					  var dialog = FB.UI.FBMLPopupDialog("Why we ask for these permission", '');
					  var fbml="\<div id="fb_dialog_content" class="fb_dialog_content">\
						   <div class="fb_confirmation_stripes"></div>\
					   <div class="fb_confirmation_content"><h1>email</h1><p>we requires permission to get your email address for you to leave a comment.</p> <h1>publish_stream</h1><p>we requires permission to auto share your comment in Facebook without prompting you every time you leave a comment. </p><h1>offline_access</h1><p>This site requires offline access to auto share your comment in Facebook when it approve by the blog admin.</p></div>\</div>";
					  dialog.setFBMLContent(fbml);
					  dialog.setContentWidth(540);
					  dialog.set_placement(FB.UI.PopupPlacement.topCenter);
					  dialog.show();
			}
			</script>
			<fb:prompt-permission perms="email,publish_stream,offline_access">
				<font color="red">Please add these permission to our blog</font>, Why? <b><a
					href="javascript:why_dlg()">Click here</a></b>
			</fb:prompt-permission>';
}

add_action('profile_personal_options','wpfb_ppermissions');
function wpfb_ppermissions() {
	global $facebook,$user_id;
	echo '<script src="http://static.ak.connect.facebook.com/connect.php/en_US" type="text/javascript"></script>
		<script type="text/javascript">
			 FB.init("<?=get_option("wpfb_apikey");?>");
			 function why_dlg(){
				  var dialog = FB.UI.FBMLPopupDialog("Why we ask for these permission", "");
				  var fbml="\<div id="fb_dialog_content" class="fb_dialog_content">\
					   <div class="fb_confirmation_stripes"></div>\
				   <div class="fb_confirmation_content"><h1>email</h1><p>we requires permission to get your email address for you to leave a comment.</p> <h1>publish_stream</h1><p>we requires permission to auto share your comment in Facebook without prompting you every time you leave a comment. </p><h1>offline_access</h1><p>This site requires offline access to auto share your comment in Facebook when it approve by the blog admin.</p></div>\</div>";
				  dialog.setFBMLContent(fbml);
				  dialog.setContentWidth(540);
				  dialog.set_placement(FB.UI.PopupPlacement.topCenter);
				  dialog.show();
		}
		</script>
		<table class="form-table">
				<tbody><tr>
					<th><label>Facebook Permissions</label></th>
				<td>';
 
	wpfb_connect();
	if(has_perm($user_id) != "connect" && has_perm($user_id) != "full") echo ("<b>you must connect to facebook first.</b>\n");
	else {
	$can=	$facebook->api_client->users_hasAppPermission('email',$user_id)
		&& $facebook->api_client->users_hasAppPermission('offline_access',$user_id)
		&& $facebook->api_client->users_hasAppPermission('user_notes',$user_id)
		&& $facebook->api_client->users_hasAppPermission('publish_stream',$user_id)
		&& $facebook->api_client->users_hasAppPermission('manage_pages',$user_id);
	if(!$can){ ?>
		<fb:prompt-permission perms="email,offline_access,publish_stream,user_notes,manage_pages">
			<font color="red">You must add these permission first to allow post and comment synchronization</font>
		</fb:prompt-permission></fieldset>
	<?php }else echo "<font color='green'>Permissions Granted successfully</font>"; 
	}
?>
	echo '</td>
		</tr>
	</tbody></table>';
}
 */