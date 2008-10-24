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


// The contents of this file are very much inspired by the file functions_search.php
// from the phpBB Group forum software phpBB2 (http://www.phpbb.com). 


// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
	exit;


//
// "Cleans up" a text string and returns an array of unique words
// This function depends on the current locale setting
//
function split_words($text)
{
	global $forum_user;
	static $noise_match, $noise_replace, $stopwords;

	$return = ($hook = get_hook('si_fn_split_words_start')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	if (empty($noise_match))
	{
		$noise_match = 		array('[quote', '[code', '[url', '[img', '[email', '[color', '[colour', 'quote]', 'code]', 'url]', 'img]', 'email]', 'color]', 'colour]', '^', '$', '&', '(', ')', '<', '>', '`', '\'', '"', '|', ',', '@', '_', '?', '%', '~', '+', '[', ']', '{', '}', ':', '\\', '/', '=', '#', ';', '!', '*');
		$noise_replace =	array('',       '',      '',     '',     '',       '',       '',        '',       '',      '',     '',     '',       '',       '',        ' ', ' ', ' ', ' ', ' ', ' ', ' ', '',  '',   ' ', ' ', ' ', ' ', '',  ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '' ,  ' ', ' ', ' ', ' ', ' ', ' ');

		$stopwords = (array)@file(FORUM_ROOT.'lang/'.$forum_user['language'].'/stopwords.txt');
		$stopwords = array_map('trim', $stopwords);

		($hook = get_hook('si_fn_split_words_modify_noise_matches')) ? eval($hook) : null;
	}

	// Clean up
	$patterns[] = '#&[\#a-z0-9]+?;#i';
	$patterns[] = '#\b[\w]+:\/\/[a-z0-9\.\-]+(\/[a-z0-9\?\.%_\-\+=&\/~]+)?#';
	$patterns[] = '#\[\/?[a-z\*=\+\-]+(\:?[0-9a-z]+)?:[a-z0-9]{10,}(\:[a-z0-9]+)?=?.*?\]#';
	$text = preg_replace($patterns, ' ', ' '.utf8_strtolower($text).' ');

	// Filter out junk
	$text = str_replace($noise_match, $noise_replace, $text);

	// Strip out extra whitespace between words
	$text = forum_trim(preg_replace('#\s+#', ' ', $text));

	// Fill an array with all the words
	$words = explode(' ', $text);

	if (!empty($words))
	{
		while (list($i, $word) = @each($words))
		{
			$words[$i] = forum_trim($word, '.');
			$num_chars = utf8_strlen($word);

			if ($num_chars < 3 || $num_chars > 20 || in_array($words[$i], $stopwords))
				unset($words[$i]);
		}
	}

	$return = ($hook = get_hook('si_fn_split_words_end')) ? eval($hook) : null;
	if ($return != null)
		return $return;

	return array_unique($words);
}


//
// Updates the search index with the contents of $post_id (and $subject)
//
function update_search_index($mode, $post_id, $message, $subject = null)
{
	global $db_type, $forum_db;

	$return = ($hook = get_hook('si_fn_update_search_index_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	// Split old and new post/subject to obtain array of 'words'
	$words_message = split_words($message);
	$words_subject = ($subject) ? split_words($subject) : array();

	if ($mode == 'edit')
	{
		$query = array(
			'SELECT'	=> 'w.id, w.word, m.subject_match',
			'FROM'		=> 'search_words AS w',
			'JOINS'		=> array(
				array(
					'INNER JOIN'	=> 'search_matches AS m',
					'ON'			=> 'w.id=m.word_id'
				)
			),
			'WHERE'		=> 'm.post_id='.$post_id
		);

		($hook = get_hook('si_fn_update_search_index_qr_get_current_words')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		// Declare here to stop array_keys() and array_diff() from complaining if not set
		$cur_words['post'] = array();
		$cur_words['subject'] = array();

		while ($row = $forum_db->fetch_row($result))
		{
			$match_in = ($row[2]) ? 'subject' : 'post';
			$cur_words[$match_in][$row[1]] = $row[0];
		}

		$forum_db->free_result($result);

		$words['add']['post'] = array_diff($words_message, array_keys($cur_words['post']));
		$words['add']['subject'] = array_diff($words_subject, array_keys($cur_words['subject']));
		$words['del']['post'] = array_diff(array_keys($cur_words['post']), $words_message);
		$words['del']['subject'] = array_diff(array_keys($cur_words['subject']), $words_subject);
	}
	else
	{
		$words['add']['post'] = $words_message;
		$words['add']['subject'] = $words_subject;
		$words['del']['post'] = array();
		$words['del']['subject'] = array();
	}

	unset($words_message);
	unset($words_subject);

	// Get unique words from the above arrays
	$unique_words = array_unique(array_merge($words['add']['post'], $words['add']['subject']));

	if (!empty($unique_words))
	{
		$query = array(
			'SELECT'	=> 'id, word',
			'FROM'		=> 'search_words',
			'WHERE'		=> 'word IN('.implode(',', preg_replace('#^(.*)$#', '\'\1\'', $unique_words)).')'
		);

		($hook = get_hook('si_fn_update_search_index_qr_get_existing_words')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		$word_ids = array();
		while ($row = $forum_db->fetch_row($result))
			$word_ids[$row[1]] = $row[0];

		$forum_db->free_result($result);

		$new_words = array_diff($unique_words, array_keys($word_ids));
		unset($unique_words);

		if (!empty($new_words))
		{
			$query = array(
				'INSERT'	=> 'word',
				'INTO'		=> 'search_words',
				'VALUES'	=> preg_replace('#^(.*)$#', '\'\1\'', $new_words)
			);

			($hook = get_hook('si_fn_update_search_index_qr_insert_words')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
		unset($new_words);
	}

	// Delete matches (only if editing a post)
	while (list($match_in, $wordlist) = @each($words['del']))
	{
		$subject_match = ($match_in == 'subject') ? 1 : 0;

		if (!empty($wordlist))
		{
			$sql = '';
			while (list(, $word) = @each($wordlist))
				$sql .= (($sql != '') ? ',' : '').$cur_words[$match_in][$word];

			$query = array(
				'DELETE'	=> 'search_matches',
				'WHERE'		=> 'word_id IN('.$sql.') AND post_id='.$post_id.' AND subject_match='.$subject_match
			);

			($hook = get_hook('si_fn_update_search_index_qr_delete_matches')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	// Add new matches
	while (list($match_in, $wordlist) = @each($words['add']))
	{
		$subject_match = ($match_in == 'subject') ? 1 : 0;

		if (!empty($wordlist))
		{
			$sql = 'INSERT INTO '.$forum_db->prefix.'search_matches (post_id, word_id, subject_match) SELECT '.$post_id.', id, '.$subject_match.' FROM '.$forum_db->prefix.'search_words WHERE word IN('.implode(',', preg_replace('#^(.*)$#', '\'\1\'', $wordlist)).')';
			($hook = get_hook('si_fn_update_search_index_qr_delete_matches')) ? eval($hook) : null;
			$forum_db->query($sql) or error(__FILE__, __LINE__);
		}
	}

	unset($words);
}


//
// Strip search index of indexed words in $post_ids
//
function strip_search_index($post_ids)
{
	global $db_type, $forum_db;

	$return = ($hook = get_hook('si_fn_strip_search_index_start')) ? eval($hook) : null;
	if ($return != null)
		return;

	$query = array(
		'SELECT'	=> 'word_id',
		'FROM'		=> 'search_matches',
		'WHERE'		=> 'post_id IN('.$post_ids.')',
		'GROUP BY'	=> 'word_id'
	);

	($hook = get_hook('si_fn_strip_search_index_qr_get_post_words')) ? eval($hook) : null;
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	if ($forum_db->num_rows($result))
	{
		$word_ids = '';
		while ($row = $forum_db->fetch_row($result))
			$word_ids .= ($word_ids != '') ? ','.$row[0] : $row[0];

		$query = array(
			'SELECT'	=> 'word_id',
			'FROM'		=> 'search_matches',
			'WHERE'		=> 'word_id IN('.$word_ids.')',
			'GROUP BY'	=> 'word_id, subject_match',
			'HAVING'	=> 'COUNT(word_id)=1'
		);

		($hook = get_hook('si_fn_strip_search_index_qr_get_removable_words')) ? eval($hook) : null;
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

		if ($forum_db->num_rows($result))
		{
			$word_ids = '';
			while ($row = $forum_db->fetch_row($result))
				$word_ids .= ($word_ids != '') ? ','.$row[0] : $row[0];

			$query = array(
				'DELETE'	=> 'search_words',
				'WHERE'		=> 'id IN('.$word_ids.')'
			);

			($hook = get_hook('si_fn_strip_search_index_qr_delete_words')) ? eval($hook) : null;
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}

	$query = array(
		'DELETE'	=> 'search_matches',
		'WHERE'		=> 'post_id IN('.$post_ids.')'
	);
	($hook = get_hook('si_fn_strip_search_index_qr_delete_matches')) ? eval($hook) : null;
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	($hook = get_hook('si_fn_strip_search_index_end')) ? eval($hook) : null;
}

define('FORUM_SEARCH_IDX_FUNCTIONS_LOADED', 1);
