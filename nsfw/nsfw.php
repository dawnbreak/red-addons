<?php


/**
 * Name: NSFW
 * Description: Collapse posts with inappropriate content
 * Version: 1.0
 * Author: Mike Macgirvin <http://macgirvin.com/profile/mike>
 * 
 */

function nsfw_install() {
	register_hook('prepare_body', 'addon/nsfw/nsfw.php', 'nsfw_prepare_body', 10);
	register_hook('feature_settings', 'addon/nsfw/nsfw.php', 'nsfw_addon_settings');
	register_hook('feature_settings_post', 'addon/nsfw/nsfw.php', 'nsfw_addon_settings_post');

}


function nsfw_uninstall() {
	unregister_hook('prepare_body', 'addon/nsfw/nsfw.php', 'nsfw_prepare_body');
	unregister_hook('feature_settings', 'addon/nsfw/nsfw.php', 'nsfw_addon_settings');
	unregister_hook('feature_settings_post', 'addon/nsfw/nsfw.php', 'nsfw_addon_settings_post');

}

// This function isn't perfect and isn't trying to preserve the html structure - it's just a 
// quick and dirty filter to pull out embedded photo blobs because 'nsfw' seems to come up 
// inside them quite often. We don't need anything fancy, just pull out the data blob so we can
// check against the rest of the body. 
 
function nsfw_extract_photos($body) {

	$new_body = '';
	
	$img_start = strpos($body,'src="data:');
	if(! $img_start)
		return $body;

	$img_end = (($img_start !== false) ? strpos(substr($body,$img_start),'>') : false);

	$cnt = 0;

	while($img_end !== false) {
		$img_end += $img_start;
		$new_body = $new_body . substr($body,0,$img_start);
	
		$cnt ++;
		$body = substr($body,0,$img_end);

		$img_start = strpos($body,'src="data:');
		$img_end = (($img_start !== false) ? strpos(substr($body,$img_start),'>') : false);

	}

	if(! $cnt)
		return $body;

	return $new_body;
}




function nsfw_addon_settings(&$a,&$s) {


	if(! local_user())
		return;

    /* Add our stylesheet to the page so we can make our settings look nice */

	head_add_css('/addon/nsfw/nsfw.css');

	$enable_checked = (intval(get_pconfig(local_user(),'nsfw','disable')) ? '' : ' checked="checked" ');
	$words = get_pconfig(local_user(),'nsfw','words');
	if(! $words)
		$words = 'nsfw,';
		
    $s .= '<div class="settings-block">';
    $s .= '<button class="btn btn-default" data-target="#settings-nsfw-wrapper" data-toggle="collapse" type="button">' . t('Not Safe For Work (General Purpose Content Filter) Settings') . '</button>';
    $s .= '<div id="settings-nsfw-wrapper" class="collapse well">';
    
    $s .= '<div id="nsfw-wrapper">';
    $s .= '<p>' . t ('This plugin looks in posts for the words/text you specify below, and collapses any content containing those keywords so it is not displayed at inappropriate times, such as sexual innuendo that may be improper in a work setting. It is polite and recommended to tag any content containing nudity with #NSFW.  This filter can also match any other word/text you specify, and can thereby be used as a general purpose content filter.') . '</p>';
    $s .= '<label id="nsfw-enable-label" for="nsfw-enable">' . t('Enable Content filter') . ' </label>';
    $s .= '<input id="nsfw-enable" type="checkbox" name="nsfw-enable" value="1"' . $enable_checked . ' />';
	$s .= '<div class="clear"></div>';
    $s .= '<label id="nsfw-label" for="nsfw-words">' . t('Comma separated list of keywords to hide') . ' </label>';
    $s .= '<input id="nsfw-words" type="text" name="nsfw-words" value="' . $words .'" /><div class="nsfw-desc">&nbsp;&nbsp;&nbsp;&nbsp;' . t('Use /expression/ to provide regular expressions') . '</div>';
    $s .= '</div><div class="clear"></div>';

    $s .= '<div class="settings-submit-wrapper" ><input type="submit" id="nsfw-submit" name="nsfw-submit" class="settings-submit" value="' . t('Submit Not Safe For Work Settings') . '" /></div>';
	$s .= '</div></div>';

	return;

}

function nsfw_addon_settings_post(&$a,&$b) {

	if(! local_user())
		return;

	if($_POST['nsfw-submit']) {
		set_pconfig(local_user(),'nsfw','words',trim($_POST['nsfw-words']));
		$enable = ((x($_POST,'nsfw-enable')) ? intval($_POST['nsfw-enable']) : 0);
		$disable = 1-$enable;
		set_pconfig(local_user(),'nsfw','disable', $disable);
		info( t('NSFW Settings saved.') . EOL);
	}
}

function nsfw_prepare_body(&$a,&$b) {


	$words = null;
	if(get_pconfig(local_user(),'nsfw','disable'))
		return;

	if(local_user()) {
		$words = get_pconfig(local_user(),'nsfw','words');
	}
	if($words) {
		$arr = explode(',',$words);
	}
	else {
		$arr = array('nsfw');
	}

	$found = false;
	if(count($arr)) {

		$body = nsfw_extract_photos($b['html']);

		foreach($arr as $word) {
			$word = trim($word);
			$author = '';

			if(! strlen($word)) {
				continue;
			}

			$orig_word = $word;

			if(strpos($word,'::') !== false) {
				$author = substr($word,0,strpos($word,'::'));
				$word = substr($word,strpos($word,'::')+2);
			}			
			if($author && stripos($b['item']['author']['xchan_name'],$author) === false)
				continue;

			if(! $word)
				$found = true;

			if(strpos($word,'/') === 0) {
				if(preg_match($word,$body)) {
					$found = true;
					break;
				}
			}
			else {
				if(stristr($body,$word)) {
					$found = true;
					break;
				}
				if($b['item']['term']) {
					foreach($b['item']['term'] as $t) {
						if(stristr($t['term'],$word )) {
							$found = true;
							break;
						}
					}
				}
				if($found)
					break; 
			}
		}
	}
	if($found) {
		$rnd = random_string(8);
		$b['html'] = '<div id="nsfw-wrap-' . $rnd . '" class="fakelink" onclick=openClose(\'nsfw-' . $rnd . '\'); >' . sprintf( t('%s - Click to open/close'),$orig_word ) . '</div><div id="nsfw-' . $rnd . '" style="display: none; " >' . $b['html'] . '</div>';  
	}
}
