<?php
// $Id$
function url_forum_forum($parms)
{
	$amp = isset($parms['raw']) ? '&' : '&amp;';
	switch($parms['func'])
	{
		case 'view':
			$page = (varset($parms['page']) ? $amp.'p='.$parms['page'] : '');
			return e_PLUGIN_ABS."forum/forum_viewforum.php?id={$parms['id']}{$page}";
			break;

		case 'track':
			return e_PLUGIN_ABS.'forum/forum.php?track';
			break;

		case 'main':
			return e_PLUGIN_ABS.'forum/forum.php';
			break;

		case 'post':
			return e_PLUGIN_ABS."forum/forum_post.php?f={$parms['type']}}id={$parms['id']}";
			break;

		case 'rules':
			return e_PLUGIN_ABS.'forum/forum.php?f=rules';
			break;

		case 'mfar':
			return e_PLUGIN_ABS.'forum/forum.php?f=mfar'.$amp.'id='.$parms['id'];
			break;

	}
}
