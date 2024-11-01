<?

if(isset($_GET['connect'])){
	global $current_user;

	require_once("../../../wp-config.php");
	require_once(ABSPATH . WPINC . '/registration.php'); 
	require_once(WP_PLUGIN_DIR."/wp-posts-fb-notes/config.php");

	get_currentuserinfo();
	$me=$wpfb->fb->api('/me');
	$exist = username_exists("fb_".$me['id']);
	if($exist){
		add_user_meta($exist , 'access_token', $wpfb->session['access_token'],true);
		header( 'Location: '.get_option('home').'/wp-login.php?redirect_to='.get_option('home') ) ;
		die('found_single');
	}
	$exist = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'fbuid' AND meta_value = %s", $wpfb->fb->getUser()) );
	if($exist){
		add_user_meta($exist , 'access_token', $wpfb->session['access_token'],true);
		header( 'Location: '.get_option('home').'/wp-login.php?redirect_to='.get_option('home') ) ;
		die('found_connected');
	}
	if(!$current_user->id && $me['id']){
		
		$random_password = wp_generate_password( 12, false );
		$user_id = wp_create_user( "fb_".$me['id'], $random_password, $me['email'] );
		$userdata = array (  'ID' => $user_id,
				'display_name' => $me['name'],
				'user_url' => $me['link'],
				'first_name' => $me['first_name'],
				'last_name' => $me['last_name'],
				'nickname' => $me['name'],
				'fbuid' => $me['id']
					 );
		add_user_meta($user_id , 'fbuid', $me['id'],true);
		add_user_meta($user_id , 'access_token', $wpfb->session['access_token'],true);
		wp_update_user($userdata);
		header( 'Location: '.get_option('home').'/wp-login.php?redirect_to='.get_option('home') ) ;
		die('created');
	}else if ($current_user->id && !$exist){
		$userdata = array (  'ID' => $current_user->id,
				'display_name' => $me['name'],
				'user_url' => $me['link'],
				'first_name' => $me['first_name'],
				'last_name' => $me['last_name'],
				'nickname' => $me['name']
				);
		wp_update_user($userdata);
		add_user_meta($current_user->id, 'fbuid', $me['id'],true);
		add_user_meta($current_user->id , 'access_token', $wpfb->session['access_token'],true);
		header( 'Location: '.get_option('home').'		'.get_option('home') ) ;
		die('connected');
	}
}else{
require_once(WP_PLUGIN_DIR."/wp-posts-fb-notes/config.php");

/**
 * WPFBWidget Class
 */
class WPFBWidget extends WP_Widget {
    /** constructor */
    function WPFBWidget() {
        parent::WP_Widget(false, $name = 'WPFBWidget');	
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {
        global $current_user,$wpdb,$wpfb;
        extract( $args );
        $title = apply_filters('widget_title', $instance['title']);

		if ($wpfb->session) {
		  $logoutUrl = $wpfb->fb->getLogoutUrl();
		} else {
		  $loginUrl = $wpfb->fb->getLoginUrl();
		}
        ?>
		<div id="fb-root"></div>
		<script>
		window.fbAsyncInit = function() {
		FB.init({
		appId : '<?php echo $wpfb->fb->getAppId(); ?>',
		session : <?php echo json_encode($wpfb->session); ?>, // don't refetch the session when PHP already has it
		status : true, // check login status
		cookie : true, // enable cookies to allow the server to access the session
		xfbml : true // parse XFBML
		});

		// whenever the user logs in, we refresh the page
		FB.Event.subscribe('auth.login', function() {
	            jQuery(document).ready(function($) {
$('.fb_button').css('background','none');
$('.fb_button').html('<img src="http://static.ak.fbcdn.net/rsrc.php/z5R48/hash/ejut8v2y.gif" />');
$.ajax({
  url: '<?=WP_PLUGIN_URL?>/wp-posts-fb-notes/connect.php?connect=1',
  success: function(data) {
     document.location.reload();
  }
});

                    });
		});
		};

		(function() {
		var e = document.createElement('script');
		e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
		e.async = true;
		document.getElementById('fb-root').appendChild(e);
		}());
		</script>
		<?php echo $before_widget; ?>
<div id="wpfbwidget_main">
		<?php if ( $title )
			echo $before_title . $title . $after_title; 
			get_currentuserinfo();
			$result = $wpdb->get_results("SELECT * FROM $wpdb->usermeta WHERE meta_key = 'fbuid' AND user_id = {$current_user->ID}");
		if ($wpfb->session): ?>
			<? $me = $wpfb->fb->api('/me'); ?>
                        <p>
                              <img style="float:left;width:40px;padding:5px;" src="https://graph.facebook.com/<?=$wpfb->fb->getUser();?>/picture" />
                              <a href="<?=$me["link"];?>" rel="external nofollow" class="url"><?=$me["name"];?></a><br/>
                              <a href="<?php echo $logoutUrl; ?>">
                                    <img src="http://static.ak.fbcdn.net/rsrc.php/z2Y31/hash/cxrz4k7j.gif">
                              </a><div style="clear:both"></div>
                        </p>
<script>
var form_elem = document.getElementById('commentform');
if(form_elem)
form_elem.innerHTML='<p><img style="float:left;width:40px;padding:5px;" src="https://graph.facebook.com/<?=$wpfb->fb->getUser();?>/picture" /><a href="<?=$me["link"];?>" rel="external nofollow" class="url"><?=$me["name"];?></a><br/><a href="<?php echo $logoutUrl; ?>"><img src="http://static.ak.fbcdn.net/rsrc.php/z2Y31/hash/cxrz4k7j.gif"></a><div style="clear:both"></div></p><input type="hidden" name="fbuid" value="<?=$wpfb->fb->getUser()?>" /><input type="hidden" name="access_token" value="<?=$wpfb->session['access_token']?>" /><input id="author" name="author" value="<?=$me['name'];?>" type="hidden"><input id="email" name="email" type="hidden" value="<?=$me['email'];?>"><input id="url" name="url" type="hidden" value="<?=$me['link'];?>"><p class="comment-form-comment"><label for="comment">Comment</label><textarea id="comment" name="comment" cols="45" rows="8" aria-required="true"></textarea></p><p class="form-allowed-tags">You may use these <abbr title="HyperText Markup Language">HTML</abbr> tags and attributes:  <code>&lt;a href="" title=""&gt; &lt;abbr title=""&gt; &lt;acronym title=""&gt; &lt;b&gt; &lt;blockquote cite=""&gt; &lt;cite&gt; &lt;code&gt; &lt;del datetime=""&gt; &lt;em&gt; &lt;i&gt; &lt;q cite=""&gt; &lt;strike&gt; &lt;strong&gt; </code></p><p class="form-submit"><input name="submit" type="submit" id="submit" value="Post Comment"><input type="hidden" name="comment_post_ID" value="'+form_elem.comment_post_ID.value+'" id="comment_post_ID"><input type="hidden" name="comment_parent" id="comment_parent" value="'+form_elem.comment_parent.value+'"></p>';
</script>
		<?php else: ?>
			<div>
				<fb:login-button perms="email,publish_stream,offline_access">Connect with Facebook</fb:login-button>
			</div>
<script>
var form_elem = document.getElementById('commentform');
form_elem.innerHTML='<p><fb:login-button perms="email,publish_stream,offline_access">Connect with Facebook</fb:login-button></p>'+form_elem.innerHTML;
</script>
		<?php endif ?>
</div>
		<?php echo $after_widget;
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {				
	$instance = $old_instance;
	$instance['title'] = strip_tags($new_instance['title']);
        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {				
        $title = esc_attr($instance['title']);
        ?>
            <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
        <?php 
    }

} // class WPFBWidget
// register WPFBWidget widget
add_action('widgets_init', create_function('', 'return register_widget("WPFBWidget");'));



add_action('comment_post', 'wpfb_comment_post');
function wpfb_comment_post($comment_id)  {
    global $wpfb;
    add_comment_meta($comment_id, 'fbuid', $_POST['fbuid'], true);
    add_comment_meta($comment_id, 'access_token', $_POST['access_token'], true);
}

add_filter('get_avatar', 'wpfb_comment_avatar', 1, 3);
function wpfb_comment_avatar($avatar, $comment, $size){
        global $wpfb;
        $fbuid = get_comment_meta($comment->comment_ID,'fbuid',true);
        if($fbuid){
                $avatar = "<img src='https://graph.facebook.com/".$fbuid."/picture' class='avatar avatar-40 photo' style='height:".$size."px;width:".$size."px;' />";
	        return $avatar;
        }
        return $avatar;
}


function wpfb_init() {
    wp_enqueue_script('jquery');
}    
add_action('init', 'wpfb_init');

add_filter('authenticate','wpfb_authenticate',90);
function wpfb_authenticate($user) {
	global $wpdb,$wpfb;
	if ( is_a($user, 'WP_User') ) { return $user; }	

	if(! $wpfb->fb )return $user;	
	try{
		$user_id = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'fbuid' AND meta_value = %s", $wpfb->fb->getUser()) );
	
		if ($user_id) {
			$user = new WP_User($user_id);
		}
	} catch (Exception $ex) {
		$fb->clear_cookie_state();
	}  
	return $user;	
}

function wpfb_logout() {
    global $wpfb;
    
}    
add_action('wp_logout', 'wpfb_logout'); 
}
?>