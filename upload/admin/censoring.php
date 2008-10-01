<?php
/***********************************************************************

  Copyright (C) 2002-2008  PunBB

  Partially based on code copyright (C) 2008  FluxBB.org

  This file is part of PunBB.

  PunBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  PunBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


if (!defined('FORUM_ROOT'))
	define('FORUM_ROOT', '../');
require FORUM_ROOT.'include/common.php';
require FORUM_ROOT.'include/common_admin.php';

($hook = get_hook('acs_start')) ? eval($hook) : null;

if (!$forum_user['is_admmod'])
	message($lang_common['No permission']);

// Load the admin.php language file
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_common.php';
require FORUM_ROOT.'lang/'.$forum_user['language'].'/admin_censoring.php';


// Add a censor word
if (isset($_POST['add_word']))
{
	$search_for = trim($_POST['new_search_for']);
	$replace_with = trim($_POST['new_replace_with']);

	if ($search_for == '' || $replace_with == '')
		message($lang_admin_censoring['Must enter text message']);

	($hook = get_hook('acs_add_word_form_submitted')) ? eval($hook) : null;

	$query = array(
		'INSERT'	=> 'search_for, replace_with',
		'INTO'		=> 'censoring',
		'VALUES'	=> '\''.$forum_db->escape($search_for).'\', \''.$forum_db->escape($replace_with).'\''
	);

	($hook = get_hook('acs_add_word_qr_add_censor')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the censor cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_censors_cache();

	($hook = get_hook('acs_add_word_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['admin_censoring']), $lang_admin_censoring['Censor word added'].' '.$lang_admin_common['Redirect']);
}


// Update a censor word
else if (isset($_POST['update']))
{
	$id = intval(key($_POST['update']));

	$search_for = trim($_POST['search_for'][$id]);
	$replace_with = trim($_POST['replace_with'][$id]);

	if ($search_for == '' || $replace_with == '')
		message($lang_admin_censoring['Must enter text message']);

	($hook = get_hook('acs_update_form_submitted')) ? eval($hook) : null;

	$query = array(
		'UPDATE'	=> 'censoring',
		'SET'		=> 'search_for=\''.$forum_db->escape($search_for).'\', replace_with=\''.$forum_db->escape($replace_with).'\'',
		'WHERE'		=> 'id='.$id
	);

	($hook = get_hook('acs_update_qr_update_censor')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the censor cache
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_censors_cache();

	($hook = get_hook('acs_update_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['admin_censoring']), $lang_admin_censoring['Censor word updated'].' '.$lang_admin_common['Redirect']);
}


// Remove a censor word
else if (isset($_POST['remove']))
{
	$id = intval(key($_POST['remove']));

	($hook = get_hook('acs_remove_form_submitted')) ? eval($hook) : null;

	$query = array(
		'DELETE'	=> 'censoring',
		'WHERE'		=> 'id='.$id
	);

	($hook = get_hook('acs_remove_qr_delete_censor')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	// Regenerate the censor cache
	require_once FORUM_ROOT.'include/cache.php';
	generate_censors_cache();

	($hook = get_hook('acs_remove_pre_redirect')) ? eval($hook) : null;

	redirect(forum_link($forum_url['admin_censoring']), $lang_admin_censoring['Censor word removed'].' '.$lang_admin_common['Redirect']);
}


// Load the cached censors
if (file_exists(FORUM_CACHE_DIR.'cache_censors.php'))
	include FORUM_CACHE_DIR.'cache_censors.php';

if (!defined('FORUM_CENSORS_LOADED'))
{
	require_once FORUM_ROOT.'include/cache.php';
	generate_censors_cache();
	require FORUM_CACHE_DIR.'cache_censors.php';
}


// Setup the form
$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;

// Setup breadcrumbs
$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	$lang_admin_common['Censoring']
);

($hook = get_hook('acs_pre_header_load')) ? eval($hook) : null;

define('FORUM_PAGE_SECTION', 'settings');
define('FORUM_PAGE', 'admin-censoring');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

($hook = get_hook('acs_main_output_start')) ? eval($hook) : null;

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_censoring['Censored word head'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_censoring']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_censoring']).'?action=foo') ?>" />
			</div>
			<div class="ct-box">
				<p><?php echo $lang_admin_censoring['Add censored word intro']; if ($forum_user['g_id'] == FORUM_ADMIN) printf(' '.$lang_admin_censoring['Add censored word extra'], '<strong><a href="'.forum_link($forum_url['admin_settings_features']).'">'.$lang_admin_common['Settings'].' - '.$lang_admin_common['Features'].'</a></strong>') ?></p>
			</div>
			<fieldset class="frm-group frm-hdgroup group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_censoring['Add censored word legend'] ?></span></legend>
<?php ($hook = get_hook('acs_pre_add_word_fieldset')) ? eval($hook) : null; ?>
				<fieldset class="mf-set set<?php echo ++$forum_page['item_count'] ?><?php echo ($forum_page['item_count'] == 1) ? ' mf-head' : ' mf-extra' ?>">
					<legend><span><?php echo $lang_admin_censoring['Add new word legend'] ?></span></legend>
					<div class="mf-box">
<?php ($hook = get_hook('acs_pre_add_search_for')) ? eval($hook) : null; ?>
						<div class="mf-field mf-field1">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin_censoring['Censored word label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_search_for" size="24" maxlength="60" /></span>
						</div>
<?php ($hook = get_hook('acs_pre_add_replace_with')) ? eval($hook) : null; ?>
						<div class="mf-field">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span class="fld-label"><?php echo $lang_admin_censoring['Replacement label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="new_replace_with" size="24" maxlength="60" /></span>
						</div>
<?php ($hook = get_hook('acs_pre_add_submit')) ? eval($hook) : null; ?>
						<div class="mf-field">
							<span class="submit"><input type="submit" class="button" name="add_word" value=" <?php echo $lang_admin_censoring['Add word'] ?> " /></span>
						</div>
					</div>
<?php ($hook = get_hook('acs_pre_add_word_fieldset_end')) ? eval($hook) : null; ?>
				</fieldset>
<?php ($hook = get_hook('acs_add_word_fieldset_end')) ? eval($hook) : null; ?>
			</fieldset>
		</form>
<?php

if (!empty($forum_censors))
{
	// Reset
	$forum_page['group_count'] = $forum_page['item_count'] = 0;

?>
		<form class="frm-form" method="post" accept-charset="utf-8" action="<?php echo forum_link($forum_url['admin_censoring']) ?>?action=foo">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token(forum_link($forum_url['admin_censoring']).'?action=foo') ?>" />
			</div>
			<fieldset class="frm-group frm-hdgroup group<?php echo ++$forum_page['group_count'] ?>">
				<legend class="group-legend"><span><?php echo $lang_admin_censoring['Edit censored word legend'] ?></span></legend>
<?php

	foreach ($forum_censors as $censor_key => $cur_word)
	{

	?>
<?php ($hook = get_hook('acs_pre_edit_word_fieldset')) ? eval($hook) : null; ?>
				<fieldset class="mf-set set<?php echo ++$forum_page['item_count'] ?><?php echo ($forum_page['item_count'] == 1) ? ' mf-head' : ' mf-extra' ?>">
					<legend><span><?php echo $lang_admin_censoring['Existing censored word legend'] ?></span></legend>
					<div class="mf-box">
<?php ($hook = get_hook('acs_pre_edit_search_for')) ? eval($hook) : null; ?>
						<div class="mf-field mf-field1">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_censoring['Censored word label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="search_for[<?php echo $cur_word['id'] ?>]" value="<?php echo forum_htmlencode($cur_word['search_for']) ?>" size="24" maxlength="60" /></span>
						</div>
<?php ($hook = get_hook('acs_pre_edit_replace_with')) ? eval($hook) : null; ?>
						<div class="mf-field">
							<label for="fld<?php echo ++$forum_page['fld_count'] ?>"><span><?php echo $lang_admin_censoring['Replacement label'] ?></span></label><br />
							<span class="fld-input"><input type="text" id="fld<?php echo $forum_page['fld_count'] ?>" name="replace_with[<?php echo $cur_word['id'] ?>]" value="<?php echo forum_htmlencode($cur_word['replace_with']) ?>" size="24" maxlength="60" /></span>
						</div>
<?php ($hook = get_hook('acs_pre_edit_submit')) ? eval($hook) : null; ?>
						<div class="mf-field">
							<span class="submit"><input type="submit" name="update[<?php echo $cur_word['id'] ?>]" value="<?php echo $lang_admin_common['Update'] ?>" /> <input type="submit" name="remove[<?php echo $cur_word['id'] ?>]" value="<?php echo $lang_admin_common['Remove'] ?>" /></span>
						</div>
					</div>
<?php ($hook = get_hook('acs_pre_edit_word_fieldset_end')) ? eval($hook) : null; ?>
				</fieldset>
<?php ($hook = get_hook('acs_edit_word_fieldset_end')) ? eval($hook) : null; ?>
<?php

	}

?>
			</fieldset>
		</form>
	</div>
<?php

}
else
{

?>
	<div class="main-subhead">
		<h2 class="hn"><span><?php echo $lang_admin_censoring['Edit censored word legend'] ?></span></h2>
	</div>
	<div class="main-content main-frm">
		<div class="frm-form">
			<div class="ct-box">
				<p><?php echo $lang_admin_censoring['No censored words'] ?></p>
			</div>
		</div>
	</div>
<?php

}

($hook = get_hook('acs_end')) ? eval($hook) : null;

$tpl_temp = trim(ob_get_contents());
$tpl_main = str_replace('<!-- forum_main -->', $tpl_temp, $tpl_main);
ob_end_clean();
// END SUBST - <!-- forum_main -->

require FORUM_ROOT.'footer.php';
