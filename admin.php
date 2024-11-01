<?

require_once(WP_PLUGIN_DIR."/wp-posts-fb-notes/config.php");

/*
Create the administator menu
*/
add_action('admin_menu','wpfb_activate_menu');
function wpfb_activate_menu() {
	add_menu_page('WP-FB', 'WP-FB', 1, 'wpdb-admin', 'wpfb_options_menu','');
	add_submenu_page('wpdb-admin','WP-FB >> Options', 'Options', 1, 'wpdb-admin', 'wpfb_options_menu');
	add_submenu_page('wpdb-admin','WP-FB >> Manage pairs', 'Manage pairs', 1, 'wpdb-admin?manage_pairs', 'wpfb_manage_pairs_menu');
	add_submenu_page('wpdb-admin','WP-FB >> Blog only', 'Blog only', 1, 'wpdb-admin?blog_only', 'wpfb_manage_blog_only');
	add_submenu_page('wpdb-admin','WP-FB >> Facebook only', 'Facebook only', 1, 'wpdb-admin?facebook_only', 'wpfb_manage_facebook_only');
	add_submenu_page('wpdb-admin','WP-FB >> Facebook links', 'Facebook links', 1, 'wpdb-admin?facebook_links', 'wpfb_manage_facebook_links');

}


function wpfb_admin_connect(){
global $wpfb;
if(!$wpfb->fb)return;

if( get_option("wpfb_connected")){

if(! $wpfb->fbuid && $wpfb->session){
    update_option("wpfb_fbuid",$wpfb->fb->getUser());
    $wpfb->fbuid = get_option("wpfb_fbuid");
}
if(!$wpfb->session){
    $wpfb->session = json_decode(get_option("wpfb_session"),true);
    $wpfb->fb->setSession($wpfb->session);
}else{
    update_option("wpfb_session",json_encode($wpfb->session));
}

}else $wpfb->fbuid=null;

if ($wpfb->session) {
  try {
    $wpfb->me = $wpfb->fb->api('/me');
  } catch (FacebookApiException $e) {
    $wpfb->session="";
  }
}

if(! is_active_widget(false, false, 'wpfbwidget', true)){
	echo "<div id='message' class='updated fade'>You must <a href='widgets.php'>add the WBFBWidget</a> to your theme to allow facebook connect and get permission from your visitors...</div>\n";
}
}
function wpfb_options_menu() {
	//see if the user can see this page.
	if(wpfb_is_authorized()){
		global $wpdb,$error_flag,$wpfb;
                wpfb_admin_connect();

		if (isset($_POST['save'])) {
			wpfb_save_options();
			?><script>document.location= '<?=get_bloginfo('url');?>/wp-admin/admin.php?page=wpdb-admin'</script><?
			if(!$error_flag){
				echo "<div id='message' class='updated fade'><p>Options updated, ";
				echo('Now you can <a href="admin.php?page=wpdb-admin?manage_pairs">Manage your pairs</a> to begin the Synchronization</p></div>');
			}
		}
		if (isset($_POST['disconnect'])) {
			delete_option("wpfb_session");
			delete_option("wpfb_fbuid");
			delete_option("wpfb_fbpid");
                        update_option("wpfb_connected",false);
		}

		if (isset($_POST['connect'])) {
			update_option("wpfb_fbuid",$wpfb->fb->getUser());
                        update_option("wpfb_session",json_encode($wpfb->session));
                        update_option("wpfb_connected",true);
		}

		if (isset($_POST['manual_notes'])) {
			echo "<div id='message' class='updated fade'><p>Starting manual pull please be patient this may take time...</p></div>\n";
			wpfb_connect_notes();
			echo "<div id='message' class='updated fade'><p>Completed, check the comments section of Wordpress for any pending comments</p></div>\n";
		}
		if (isset($_POST['manual_links'])) {
			echo "<div id='message' class='updated fade'><p>Starting manual pull please be patient this may take time...</p></div>\n";
			wpfb_connect_links();
			echo "<div id='message' class='updated fade'><p>Completed, check the comments section of Wordpress for any pending comments</p></div>\n";
		}
		if (isset($_POST['auto_detect'])) {
			wpfb_detect_pairs();
			$pairs=wpfb_get_pairs();
			echo "<div id='message' class='updated fade'><p>".count($pairs)." Pairs detected you can <a href='admin.php?page=wpdb-admin?show_pairs'>see the pairs</a> or <a href='admin.php?page=wpdb-admin?edit_pairs'>manully edit them</a></p></div>\n";
		}
                wpfb_admin_connect();
		?>
				<div class="wrap">
				<h2>WP Posts Facebook Notes</h2>
				<?php 
				if($wpfb->session && $wpfb->fbaid)wpfb_sub_menu();
				?>
				<form method="POST">
				<h3>Facebook Application</h3>
				<? if($wpfb->fb){ ?>
				<div id="fb-root"></div>
				<script>
				window.fbAsyncInit = function() {
					FB.init({
						appId : '<?php echo $wpfb->fb->getAppId(); ?>',
						//session : '<?php echo json_encode($wpfb->session); ?>', // don't refetch the session when PHP already has it
						status : true, // check login status
						cookie : true, // enable cookies to allow the server to access the session
						xfbml : true // parse XFBML
					});

					// whenever the user logs in, we refresh the page
					FB.Event.subscribe('auth.login', function() {
						window.location.reload();
					});
				};

				(function() {
					var e = document.createElement('script');
					e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
					e.async = true;
					document.getElementById('fb-root').appendChild(e);
				}());
				</script>
				<? if(!$wpfb->session){  ?>
					<fb:login-button perms="publish_stream,offline_access,email,user_notes,manage_pages">Login & Allow for permission</fb:login-button>
				<? }else{ ?>
<p>
       <img style="float:left;width:40px;padding:5px;" src="https://graph.facebook.com/<?=$wpfb->me['id']?>/picture">
       <a href="http://www.facebook.com/orientps" rel="external nofollow" class="url"><?=$wpfb->me['name']?></a><br>
       <a href="<?php echo $wpfb->fb->getLogoutUrl(); ?>">
	  <img src="http://static.ak.fbcdn.net/rsrc.php/z2Y31/hash/cxrz4k7j.gif">
       </a>
       <? if($wpfb->me['id'] == $wpfb->fbuid){ ?>
       <input type="submit" name="disconnect" value="<?php _e('Disconnect this account','Localization name') ?>" class="button-primary" />
       <? } else { ?>
       <input type="submit" name="connect" value="<?php _e('Connect this account','Localization name') ?>" class="button-primary" />
       <? } ?>
</p>
				<? } ?>
				<? } ?>
				<fieldset name="fbuid">
					<legend> <?php _e('User id', 'Localization nae') ?>:</legend>
					<input type="text" name="fbuid" id="fbuid" size="20" value="<?php echo $wpfb->fbuid ?>" />
				</fieldset>

				<fieldset name="appid">
					<legend> <?php _e('APP ID', 'Localization nae') ?>:</legend>
					<input type="text" name="appid" id="appid" size="20" value="<?php echo $wpfb->appid?>" />
				</fieldset>

				<fieldset name="appsecret">
					<legend> <?php _e('APP Secret', 'Localization nae') ?>:</legend>
					<input type="text" name="appsecret" id="appsecret" size="20" value="<?php echo $wpfb->appsecret?>" />
				</fieldset>

                                <? if($wpfb->session && $wpfb->fbuid){ ?>

				<fieldset name="fbpid">
					<legend> <?php _e('Page id', 'Localization nae') ?>:</legend>
					<?php
					$pages = $wpfb->fb->api($wpfb->fbuid."/accounts");
					?>
					<select name="fbpid">
						<option value="">--------</option>
						<?php foreach($pages['data'] as $page){	?>
							<option <?php if($wpfb->fbpid  == $page['id']) echo 'selected="selected"' ?> value="<?=$page['id']?>"><?=$page['name']?></option>
						<?php } ?>
					</select>
				</fieldset>

				<h3>Customize the messages</h3>
				<fieldset name="fromblog"><legend><?php _e('From Blog', 'Localization name') ?>: 
				<br/><em>{permlink}: blog entry URL | {author} blog entry author URL | {content} blog entry content </em>
				</legend> <input type="text" name="fromblog" id="fromblog" size="90"
					value="<?php echo get_option('wpfb_fromblog') ?>" /></fieldset>
				<fieldset name="cfromblog"><legend><?php _e('Commment From Blog', 'Localization name') ?>:
				<br/><em>{br}: new line | {user} comment author | {content} comment content </em>
				</legend> <input type="text" name="cfromblog" id="cfromblog" size="90"
					value="<?php echo get_option('wpfb_cfromblog') ?>" /></fieldset>

				<h3>Timer Settings</h3>
				Automatic: <select name="timer">
					<option value="86400"
					<?php if (get_option('wpfb_timer') == 86400) { print " selected"; } ?>>Daily
					<option value="3600"
					<?php if (get_option('wpfb_timer') == 3600) { print " selected"; } ?>>Hourly
					<option value="0"
					<?php if (get_option('wpfb_timer') == 0) { print " selected"; } ?>>Never

				</select> <br />
				<?php 
				$diff = wp_next_scheduled('wpfb_connect_notes') - time();
				echo "Next sync after: " .round($diff/60). "minutes";
				?>
				<br/>
				Auto approve comments: <select name="auto">
					<option value="0"
					<?php if (get_option('wpfb_auto') == 0) { print " selected"; } ?>>No
					<option value="1"
					<?php if (get_option('wpfb_auto') == 1) { print " selected"; } ?>>Yes
				</select> <br />
				<? } ?>
				<br />
				
				<input type="submit" name="save"
					value="<?php _e('Save options','Localization name') ?>"
					class="button-primary" />
				<input
					type="submit" name="auto_detect"
					value="<?php _e('Auto detect pairs','Localization name') ?>" class="button" />
				<input type="submit" name="test"
					value="<?php _e('Test', 'Localization name') ?> " class="button" />
				<input
					type="submit" name="manual_notes"
					value="<?php _e('Manual Sync notes','Localization name') ?>" class="button" />
				<input
					type="submit" name="manual_links"
					value="<?php _e('Manual Sync links','Localization name') ?>" class="button" />
				</form>
				</div>
		<?php
		if (isset($_POST['test'])){
			echo "<div id='message' class='updated fade'>Test running please wait...<br/></div>\n";
			update_option('wpfb_test',"1");
			echo '<br/><br/>';
			wpfb_connect_notes();
			wpfb_connect_links();
			update_option('wpfb_test',"0");
			echo "<div id='message' class='updated fade'>Test complete you can see the report below<br/></div>\n";
		}
	} else {
		echo "<div id='message' class='error fade'><p>Sorry, you don't have permission to view this page.</div>\n";
	}
}

function wpfb_manage_pairs_menu() {
	//see if the user can see this page.
	if(wpfb_is_authorized()){
		global $wpdb,$error_flag,$wpfb;
                wpfb_admin_connect();
                if(! get_option("wpfb_connected")) die('You must <a href="admin.php?page=wpdb-admin">connect to facebook</a> before you can manage your pairs');
		if (isset($_POST['unlink'])) {                        
			$keys =array_keys($_POST['unlink']);
			delete_post_meta($keys[0], 'note_id');
		}
		
		$pairs=wpfb_get_pairs();
		$notpairs=wpfb_get_not_pairs_wp();
		$fbnotpairs=wpfb_get_not_pairs_fb();
		
		if (isset($_POST['save'])) {
			foreach($pairs as $pair){
			    if(!empty($_POST[$pair->post_id]))
					update_post_meta($pair->post_id, 'note_id', $_POST[$pair->post_id]);
				else
					delete_post_meta($pair->post_id, 'note_id');
					
			}
			foreach($notpairs as $post){
			    if(!empty($_POST[$post->ID]))
					update_post_meta($post->ID, 'note_id', $_POST[$post->ID]);
				else 
					delete_post_meta($post->ID, 'note_id');
			}
		}
		if (isset($_POST['auto'])) {
			wpfb_detect_pairs();
			$pairs=wpfb_get_pairs();
		}
		if (isset($_POST['reset'])) {
			wpfb_reset_pairs();
			$pairs=wpfb_get_pairs();
		}
		?>
		<div class="wrap">
		<h2>Edit Posts/Notes pairs</h2>
		<?php wpfb_sub_menu();?>
		<form method="POST">
		<table class="widefat post fixed">
		<thead><tr><th class="manage-column" width="60px">Unlink</th><th class="manage-column">worpress</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th><th class="manage-column">facebook</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th></tr><thead>
		<tfoot><tr><th class="manage-column" width="60px">Unlink</th><th class="manage-column">worpress</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th><th class="manage-column">facebook</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th></tr><tfoot>
		<tbody>
		
		<?php 
		$pairs=wpfb_get_pairs();
		$notes=wpfb_get_fbnotes();
		if(is_array($pairs))
		foreach($pairs as $pair){
			$post=get_post($pair->post_id);
			$cnote=wpfb_get_fbnote($pair->meta_value);
			if(empty($cnote)){
				delete_post_meta($post->ID, 'note_id');
				continue;
			} 
			?>
			<tr>
			<td><input type="submit" value="unlink" name="unlink[<?=$post->ID?>]"></td>
			<td><a href="<?=get_bloginfo('url')?>?p=<?=$post->ID?>"><?=$post->post_title?></a></td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count"><?=count(get_comments('post_id='.$post->ID))?></span></a>
			</div></td>
			<td><a href="http://www.facebook.com/note.php?note_id=<?=$cnote['id']?>"><?=$cnote['subject']?></a></td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count">
				<? $coms=wpfb_get_fbcommects($pair->meta_value);
					echo (is_array($coms))?count($coms):'0'?></span></a>
			</div></td>
			<?php 
		}
		?>
		</tbody>
		</table>
		<br/>
		<input type="submit" name="auto" value="Auto detect pairs">
		<input type="submit" name="reset" value="Reset pairs">
		</form>
		<?php 
	}
}

function wpfb_manage_blog_only() {
	//see if the user can see this page.
	if(wpfb_is_authorized()){
		global $wpdb,$error_flag,$wpfb,$current_user;
                wpfb_admin_connect();
		
		$pairs=wpfb_get_pairs();
		$notpairs=wpfb_get_not_pairs_wp();
		$fbnotpairs=wpfb_get_not_pairs_fb();
		
		if (isset($_POST['create_fb'])) {
			$keys =array_keys($_POST['create_fb']);
			$post = get_post($keys[0]);
			$user = get_userdata($post->post_author);

			$content=get_option('wpfb_fromblog');
			$from = array("{permlink}", "{author}", "{content}");
			$to   = array(
				'<a href="'.$post->guid.'">'.get_bloginfo('name').'</a>',
				'<a href="'.get_bloginfo('url').'?author='.$post->post_author.'">'.$user->display_name.'</a>', 
				$post->post_content);
			$content= str_replace($from, $to, $content);
			$content= clean_post_tag($content);
			$can_post = users_hasAppPermission('publish_stream');
			if($can_post){
				$page = $wpfb->fb->api($wpfb->fbuid."/accounts");
				foreach($page['data'] as $p){
					if($p['id'] == $wpfb->fbpid)
						$acc = $p['access_token'];
				}
				$note = $wpfb->fb->api($wpfb->fbaid.'/notes', 'post', array('access_token' => $acc, 'subject'=> $post->post_title, 'message' => $content));  
				$note['id'] = number_format((float) $note['id'],0,'','');
			} else { 
				die('Permissions required!');  
			}
			
			add_post_meta($post->ID, 'note_id', $note['id'], true) or update_post_meta($post->ID, 'note_id', $note['id']);			
		}
		
		if (isset($_POST['save'])) {
			foreach($notpairs as $post){
			    if(!empty($_POST[$post->ID]))
					add_post_meta($post->ID, 'note_id', $_POST[$post->ID]);
			}
		}
		if (isset($_POST['auto'])) {
			wpfb_detect_pairs();
			$pairs=wpfb_get_pairs();
		}
		if (isset($_POST['reset'])) {
			wpfb_reset_pairs();
			$pairs=wpfb_get_pairs();
		}
		?>
		<div class="wrap">
		<h2>From Blog to Facebook</h2>
		<?php wpfb_sub_menu();?>
		<form method="POST">
		<table class="widefat post fixed">
		<thead><tr><th class="manage-column">worpress</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th><th class="manage-column">facebook</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th></tr><thead>
		<tfoot><tr><th class="manage-column">worpress</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th><th class="manage-column">facebook</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th></tr><tfoot>
		<tbody>
		
		<?php 
		$notpairs=wpfb_get_not_pairs_wp();
		$notes=wpfb_get_not_pairs_fb();
		echo '<tr><th colspan="4" class="manage-column">worpress only</th></tr>';
		if(!is_array($notpairs))
			echo '<tr><td colspan="4" class="manage-column">all your worpress posts sync with facebook</td></tr>';
		else
		foreach($notpairs as $post){
			
			?>
			<tr>
			<td><a href="<?=get_bloginfo('url')?>?p=<?=$post->ID?>"><?=$post->post_title?></a></td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count"><?=count(get_comments('post_id='.$post->ID))?></span></a>
			</div></td>
			<td>
				<select name="<?=$post->ID?>">
				<option value="" selected="selected" >none</option>
				<?php 
				foreach($notes as $note){
				?>
					<option value="<?=$note['id']?>"><?=$note['subject']?></option>
				<?php }?>
				</select>
				OR
				<input type="submit" name="create_fb[<?=$post->ID?>]" value="Create Note">
			</td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count">0</span></a>
			</div></td>
			<?php 
		}
		?>
		</tbody>
		</table>
		<br/>
		<input type="submit" name="save" value="Save" class="button-primary">
		<input type="submit" name="auto" value="Auto detect pairs">
		<input type="submit" name="reset" value="Reset pairs">
		</form>
		<?php 
	}
}


function wpfb_manage_facebook_links() {
	//see if the user can see this page.
	if(wpfb_is_authorized()){
		global $wpdb,$error_flag,$wpfb,$current_user;
                wpfb_admin_connect();
		
		$pairs=wpfb_get_pairs_links();
		$notpairs=wpfb_get_not_pairs_wp_links();
                if (isset($_POST['unlink'])) {                        
                    $keys =array_keys($_POST['unlink']);
                    $key =explode("_",$keys[0]);
//print_r($key);
                    delete_post_meta($key[0], 'link_id',$key[1]);
                }		
		if (isset($_POST['create_fb'])) {
			$keys =array_keys($_POST['create_fb']);
			$post = get_post($keys[0]);
			$user = get_userdata($post->post_author);

			$content= $post->post_content;
			$content= clean_post_tag($content);
			$can_post = users_hasAppPermission('publish_stream');
			if($can_post){
				$page = $wpfb->fb->api($wpfb->fbuid."/accounts");
				foreach($page['data'] as $p){
					if($p['id'] == $wpfb->fbpid)
						$acc = $p['access_token'];
				}
				$link = $wpfb->fb->api($wpfb->fbaid.'/links', 'post', array('access_token' => $acc, 'name'=> $post->post_title, 'caption' => "caption", 'description' => $content , 'link' => $post->guid));  
				$link['id'] = number_format((float) $link['id'],0,'','');
			} else { 
				die('Permissions required!');  
			}
			
			add_post_meta($post->ID, 'link_id', $link['id'], true) or update_post_meta($post->ID, 'link_id', $link['id']);			
		}
		
		if (isset($_POST['auto'])) {
			wpfb_detect_pairs_links();
			$pairs=wpfb_get_pairs_links();
		}
		if (isset($_POST['reset'])) {
			wpfb_reset_pairs_links();
			$pairs=wpfb_get_pairs_links();
		}
		?>
		<div class="wrap">
		<h2>Blog Posts and Facebook links pairs</h2>
		<?php wpfb_sub_menu();?>
		<form method="POST">
		<table class="widefat post fixed">
		<thead><tr><th class="manage-column">worpress</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th><th class="manage-column">facebook</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th></tr><thead>
		<tfoot><tr><th class="manage-column">worpress</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th><th class="manage-column">facebook</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th></tr><tfoot>
		<tbody>
		
		<?php 
		$pairs=wpfb_get_pairs_links();
		echo '<tr><th colspan="4" class="manage-column">Has link</th></tr>';
		if(!is_array($pairs))
			echo '<tr><td colspan="4" class="manage-column">No worpress posts sync with facebook yet</td></tr>';
		else
		foreach($pairs as $post_id => $links){
                        $post = get_post($post_id);
			?>
			<tr>
			<td><a href="<?=get_bloginfo('url')?>?p=<?=$post->ID?>"><?=$post->post_title?></a></td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count"><?=count(get_comments('post_id='.$post->ID))?></span></a>
			</div></td>
                        <td colspan="2">
<table>
<? foreach($links as $link_id) { ?>
                        <tr>
                        <td><a href="http://www.facebook.com/profile.php?id=<?=$wpfb->fbaid?>&v=wall&story_fbid=<?=$link_id?>">link</a> <input type="submit" value="unlink" name="unlink[<?=$post_id?>_<?=$link_id?>]">
                        </td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count">
				<? $coms=wpfb_get_fbcommects($link_id);
					echo (is_array($coms))?count($coms):'0'?></span></a>
			</div></td></tr>
<? } ?>
</table><input type="submit" name="create_fb[<?=$post->ID?>]" value="Post Link"></td>
			<?php 
		}


		$notpairs=wpfb_get_not_pairs_wp_links();
		echo '<tr><th colspan="4" class="manage-column">Has not link</th></tr>';
		if(!is_array($notpairs))
			echo '<tr><td colspan="4" class="manage-column">all your worpress posts sync with facebook</td></tr>';
		else
		foreach($notpairs as $post){
			
			?>
			<tr>
			<td><a href="<?=get_bloginfo('url')?>?p=<?=$post->ID?>"><?=$post->post_title?></a></td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count"><?=count(get_comments('post_id='.$post->ID))?></span></a>
			</div></td>
			<td>
				<input type="submit" name="create_fb[<?=$post->ID?>]" value="Post Link">
			</td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count">0</span></a>
			</div></td>
			<?php 
		}
		?>
		</tbody>
		</table>
		<br/>
		<input type="submit" name="save" value="Save" class="button-primary">
		<input type="submit" name="auto" value="Auto detect pairs">
		<input type="submit" name="reset" value="Reset pairs">
		</form>
		<?php 
	}
}


function wpfb_manage_facebook_only() {
	//see if the user can see this page.
	if(wpfb_is_authorized()){
		global $wpdb,$wpfb,$current_user;
                wpfb_admin_connect();
		
		$pairs=wpfb_get_pairs();
		$notpairs=wpfb_get_not_pairs_wp();
		$fbnotpairs=wpfb_get_not_pairs_fb();
		
		if (isset($_POST['create_wp'])) {
			$keys =array_keys($_POST['create_wp']);
			$note = wpfb_get_fbnote($keys[0]);
			$publish =$_POST['create_wp_publish'][$keys[0]];
			// Create post object
			$my_post = array();
			$my_post['post_title'] = $note['subject'];
			$my_post['post_date'] = date("Y-m-d H:i:s",strtotime($note['created_time']));
			$my_post['post_content'] = $note['message'];
			$my_post['post_status'] = $publish;
			$my_post['post_author'] = 1;
			
			// Insert the post into the database
			$post_id = wp_insert_post( $my_post );
			if($post_id)
				add_post_meta($post_id, 'note_id', $note['id'] , true) or update_post_meta($post_id, 'note_id', $note['id']);
			
		}
		if (isset($_POST['save'])) {
			foreach($fbnotpairs as $note){
			    if(!empty($_POST[$note['id']]))
					add_post_meta($_POST[$note['id']], 'note_id', $note['id']);
			}
		}
		if (isset($_POST['auto'])) {
			wpfb_detect_pairs();
			$pairs=wpfb_get_pairs();
		}
		if (isset($_POST['reset'])) {
			wpfb_reset_pairs();
			$pairs=wpfb_get_pairs();
		}
		?>
		<div class="wrap">
		<h2>From Facebook to Blog</h2>
		<?php wpfb_sub_menu();?>
		<form method="POST">
		<table class="widefat post fixed">
		<thead><tr><th class="manage-column">worpress</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th><th class="manage-column">facebook</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th></tr><thead>
		<tfoot><tr><th class="manage-column">worpress</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th><th class="manage-column">facebook</th><th scope="col" id="comments" class="manage-column column-comments num" style=""><div class="vers"><img alt="Comments" src="images/comment-grey-bubble.png"></div></th></tr><tfoot>
		<tbody>
		
		<?php 
		$notpairs_fb=wpfb_get_not_pairs_fb();
		$posts=wpfb_get_not_pairs_wp();
		echo '<tr><th colspan="4" class="manage-column">facebook only</th></tr>';
		if(!is_array($notpairs_fb))
			echo '<tr><td colspan="4" class="manage-column">all your facebook notes sync with wordpress</td></tr>';
		else
		foreach($notpairs_fb as $note){
			?>
			<tr>
			<td>
				<select name="<?=$note['id']?>">
				<option value="" selected="selected" >none</option>
				<?php 
				foreach($posts as $post){
				?>
					<option value="<?=$post->ID?>"><?=$post->post_title?></option>
				<?php }?>
				</select>
				OR
				<input type="submit" name="create_wp[<?=$note['id']?>]" value="Create Post">
				 publish? <input type="checkbox" name="create_wp_publish[<?=$note['id']?>]" value="publish"/>
			</td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count">0</span></a>
			</div></td>
			<td><a href="http://www.facebook.com/note.php?note_id=<?=$note['id']?>"><?=$note['subject']?></a></td>
			<td class="comments column-comments"><div class="post-com-count-wrapper">
				<a href="#" class="post-com-count"><span class="comment-count">
				<? $coms=wpfb_get_fbcommects($note['id']);
					echo (is_array($coms))?count($coms):'0'?></span></a>
			</div></td>
			<?php 
		}
		?>
		</tbody>
		</table>
		<br/>
		<input type="submit" name="save" value="Save" class="button-primary">
		<input type="submit" name="auto" value="Auto detect pairs">
		<input type="submit" name="reset" value="Reset pairs">
		</form>
		<?php 
	}
}

function wpfb_sub_menu() {
	$pairs=wpfb_get_pairs();
	$pairs_links=wpfb_get_pairs_links();
	$notpairs=wpfb_get_not_pairs_wp();
	$fbnotpairs=wpfb_get_not_pairs_fb();
	$count =(is_array($fbnotpairs))? count($fbnotpairs):0;
	?>
		<ul class="subsubsub">
		<li><a href="admin.php?page=wpdb-admin">Option</a> |</li>
		<li><a href="admin.php?page=wpdb-admin?manage_pairs">Manage pairs</a><span class="count">(<?=count($pairs)?>)</span> |</li>
		<li><a href="admin.php?page=wpdb-admin?blog_only">Blog only</a><span class="count">(<?=count($notpairs)?>)</span> |</li>
		<li><a href="admin.php?page=wpdb-admin?facebook_only">Facebook only</a><span class="count">(<?=$count?>)</span> |</li>
		<li><a href="admin.php?page=wpdb-admin?facebook_links">Facebook links</a><span class="count">(<?=count($pairs_links)?>)</span> </li>
		</ul>
		<div style="clear: both;"></div>
	<?php 	
}


function wpfb_save_options() {
	global $error_flag;

	if(wpfb_is_authorized()) {
		
		$timer = $_POST['timer'];
		if ($timer != get_option('wpfb_timer')) {
			wp_clear_scheduled_hook('wpfb_connect_notes');
			if ($timer == 86400) {	 // Daily
				wp_schedule_event( time(), 'daily', 'wpfb_connect_notes' );
			} elseif ($timer == 3600) {	 // Hourly
				wp_schedule_event( time(), 'hourly', 'wpfb_connect_notes' );
			}
		}
		
		foreach($_POST as $key => $value){
			if($key != "save")
				update_option("wpfb_$key",$value);
		}
                wpfb_connect();
			
	} else {
		echo "<div id='message' class='error fade'><p>Sorry, you don't have permission to view this page.</div>\n";
	}
}