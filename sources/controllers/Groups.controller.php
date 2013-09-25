<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 *
 * This file currently just shows group info, and allows certain priviledged members to add/remove members.
 *
 */

if (!defined('ELK'))
	die('No access...');

class Groups_Controller extends Action_Controller
{
	/**
	 * Entry point to groups.
	 * It allows moderators and users to access the group showing functions.
	 *
	 * @see Action_Controller::action_index()
	 */
	function action_index()
	{
		// Default to listing the groups
		$this->action_list();
	}

	/**
	 * Set up templates and pre-requisites for any request processed by this class.
	 * Called automagically before any action_() call.
	 * It handles permission checks, and puts the moderation bar on as required.
	 */
	public function pre_dispatch()
	{
		global $context, $txt, $scripturl, $user_info;

		// Get the template stuff up and running.
		loadLanguage('ManageMembers');
		loadLanguage('ModerationCenter');
		loadTemplate('ManageMembergroups');

		// If we can see the moderation center, and this has a mod bar entry, add the mod center bar.
		if (allowedTo('access_mod_center') || $user_info['mod_cache']['bq'] != '0=1' || $user_info['mod_cache']['gq'] != '0=1' || allowedTo('manage_membergroups'))
		{
			require_once(CONTROLLERDIR . '/ModerationCenter.controller.php');
			$_GET['area'] = (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'requests') ? 'groups' : 'viewgroups';
			$controller = new ModerationCenter_Controller();
			$controller->prepareModcenter();
		}
		// Otherwise add something to the link tree, for normal people.
		else
		{
			isAllowedTo('view_mlist');

			$context['linktree'][] = array(
				'url' => $scripturl . '?action=groups',
				'name' => $txt['groups'],
			);
		}
	}

	/**
	 * This very simply lists the groups, nothing snazy.
	 */
	public function action_list()
	{
		global $txt, $context, $scripturl, $user_info;

		$context['page_title'] = $txt['viewing_groups'];
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_group_requests'],
		);

		// Making a list is not hard with this beauty.
		require_once(SUBSDIR . '/List.subs.php');

		// Use the standard templates for showing this.
		$listOptions = array(
			'id' => 'group_lists',
			'base_href' => $scripturl . '?action=moderate;area=viewgroups;sa=view',
			'default_sort_col' => 'group',
			'get_items' => array(
				'file' => SUBSDIR . '/Membergroups.subs.php',
				'function' => 'list_getMembergroups',
				'params' => array(
					'regular',
					$user_info['id'],
					allowedTo('manage_membergroups'),
					allowedTo('admin_forum'),
				),
			),
			'columns' => array(
				'group' => array(
					'header' => array(
						'value' => $txt['name'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $scripturl;

							// Since the moderator group has no explicit members, no link is needed.
							if ($rowData[\'id_group\'] == 3)
								$group_name = $rowData[\'group_name\'];
							else
							{
								$color_style = empty($rowData[\'online_color\']) ? \'\' : sprintf(\' style="color: %1$s;"\', $rowData[\'online_color\']);
								$group_name = sprintf(\'<a href="%1$s?action=admin;area=membergroups;sa=members;group=%2$d"%3$s>%4$s</a>\', $scripturl, $rowData[\'id_group\'], $color_style, $rowData[\'group_name\']);
							}

							// Add a help option for moderator and administrator.
							if ($rowData[\'id_group\'] == 1)
								$group_name .= sprintf(\' (<a href="%1$s?action=quickhelp;help=membergroup_administrator" onclick="return reqOverlayDiv(this.href);">?</a>)\', $scripturl);
							elseif ($rowData[\'id_group\'] == 3)
								$group_name .= sprintf(\' (<a href="%1$s?action=quickhelp;help=membergroup_moderator" onclick="return reqOverlayDiv(this.href);">?</a>)\', $scripturl);

							return $group_name;
						'),
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, mg.group_name DESC',
					),
				),
				'icons' => array(
					'header' => array(
						'value' => $txt['membergroups_icons'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $settings;

							if (!empty($rowData[\'icons\'][0]) && !empty($rowData[\'icons\'][1]))
								return str_repeat(\'<img src="\' . $settings[\'images_url\'] . \'/group_icons/\' . $rowData[\'icons\'][1] . \'" alt="*" />\', $rowData[\'icons\'][0]);
							else
								return \'\';
						'),
					),
					'sort' => array(
						'default' => 'mg.icons',
						'reverse' => 'mg.icons DESC',
					)
				),
				'moderators' => array(
					'header' => array(
						'value' => $txt['moderators'],
					),
					'data' => array(
						'function' => create_function('$group', '
							global $txt;

							return empty($group[\'moderators\']) ? \'<em>\' . $txt[\'membergroups_new_copy_none\'] . \'</em>\' : implode(\', \', $group[\'moderators\']);
						'),
					),
				),
				'members' => array(
					'header' => array(
						'value' => $txt['membergroups_members_top'],
					),
					'data' => array(
						'function' => create_function('$rowData', '
							global $txt;

							// No explicit members for the moderator group.
							return $rowData[\'id_group\'] == 3 ? $txt[\'membergroups_guests_na\'] : comma_format($rowData[\'num_members\']);
						'),
						'class' => 'centertext',
					),
					'sort' => array(
						'default' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1',
						'reverse' => 'CASE WHEN mg.id_group < 4 THEN mg.id_group ELSE 4 END, 1 DESC',
					),
				),
			),
		);

		// Create the request list.
		createList($listOptions);

		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'group_lists';
	}

	/**
	 * Display members of a group, and allow adding of members to a group.
	 * It can be called from ManageMembergroups if it needs templating within the admin environment.
	 * It shows a list of members that are part of a given membergroup.
	 * It is called by ?action=moderate;area=viewgroups;sa=members;group=x
	 * It requires the manage_membergroups permission.
	 * It allows to add and remove members from the selected membergroup.
	 * It allows sorting on several columns.
	 * It redirects to itself.
	 * @uses ManageMembergroups template, group_members sub template.
	 */
	public function action_members()
	{
		global $txt, $scripturl, $context, $modSettings, $user_info, $settings;

		$current_group = isset($_REQUEST['group']) ? (int) $_REQUEST['group'] : 0;

		// No browsing of guests, membergroup 0 or moderators.
		if (in_array($current_group, array(-1, 0, 3)))
			fatal_lang_error('membergroup_does_not_exist', false);

		require_once(SUBSDIR . '/Membergroups.subs.php');

		// Load up the group details.
		$context['group'] = membergroupById($current_group, true, true);

		// Doesn't exist?
		if (!allowedTo('admin_forum') && $context['group']['group_type'] == 1)
			fatal_lang_error('membergroup_does_not_exist', false);

		// @todo should we change id => id_group and name => name_group?
		$context['group']['id'] = $context['group']['id_group'];
		$context['group']['name'] = $context['group']['group_name'];

		// Fix the membergroup icons.
		$context['group']['icons'] = explode('#', $context['group']['icons']);
		$context['group']['icons'] = !empty($context['group']['icons'][0]) && !empty($context['group']['icons'][1]) ? str_repeat('<img src="' . $settings['images_url'] . '/group_icons/' . $context['group']['icons'][1] . '" alt="*" />', $context['group']['icons'][0]) : '';
		$context['group']['can_moderate'] = allowedTo('manage_membergroups') && (allowedTo('admin_forum') || $context['group']['group_type'] != 1);

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=groups;sa=members;group=' . $context['group']['id'],
			'name' => $context['group']['name'],
		);
		$context['can_send_email'] = allowedTo('send_email_to_members');

		// @todo: use createList

		// Load all the group moderators, for fun.
		require_once(SUBSDIR . '/Membergroups.subs.php');
		$context['group']['moderators'] = array();

		$moderators = getGroupModerators($current_group);
		foreach ($moderators as $id_member => $name)
		{
			$context['group']['moderators'][] = array(
				'id' => $id_member,
				'name' => $name
			);

			if ($user_info['id'] == $id_member && $context['group']['group_type'] != 1)
				$context['group']['can_moderate'] = true;
		}

		// If this group is hidden then it can only "exists" if the user can moderate it!
		if ($context['group']['hidden'] && !$context['group']['can_moderate'])
			fatal_lang_error('membergroup_does_not_exist', false);

		// You can only assign membership if you are the moderator and/or can manage groups!
		if (!$context['group']['can_moderate'])
			$context['group']['assignable'] = 0;
		// Non-admins cannot assign admins.
		elseif ($context['group']['id'] == 1 && !allowedTo('admin_forum'))
			$context['group']['assignable'] = 0;

		// Removing member from group?
		if (isset($_POST['remove']) && !empty($_REQUEST['rem']) && is_array($_REQUEST['rem']) && $context['group']['assignable'])
		{
			checkSession();
			validateToken('mod-mgm');

			// Make sure we're dealing with integers only.
			foreach ($_REQUEST['rem'] as $key => $group)
				$_REQUEST['rem'][$key] = (int) $group;

			removeMembersFromGroups($_REQUEST['rem'], $current_group, true);
		}
		// Must be adding new members to the group...
		elseif (isset($_REQUEST['add']) && (!empty($_REQUEST['toAdd']) || !empty($_REQUEST['member_add'])) && $context['group']['assignable'])
		{
			checkSession();
			validateToken('mod-mgm');

			$member_query = array('and' => array('not_in_group'), 'or' => array());
			$member_parameters = array('not_in_group' => $current_group);

			// Get all the members to be added... taking into account names can be quoted ;)
			$_REQUEST['toAdd'] = strtr(Util::htmlspecialchars($_REQUEST['toAdd'], ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $_REQUEST['toAdd'], $matches);
			$member_names = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_REQUEST['toAdd']))));

			foreach ($member_names as $index => $member_name)
			{
				$member_names[$index] = trim(Util::strtolower($member_names[$index]));

				if (strlen($member_names[$index]) == 0)
					unset($member_names[$index]);
			}

			// Any passed by ID?
			$member_ids = array();
			if (!empty($_REQUEST['member_add']))
			{
				foreach ($_REQUEST['member_add'] as $id)
				{
					if ($id > 0)
						$member_ids[] = (int) $id;
				}
			}

			// Construct the query pelements.
			if (!empty($member_ids))
			{
				$member_query['or'][] = 'member_ids';
				$member_parameters['member_ids'] = $member_ids;
			}

			if (!empty($member_names))
			{
				$member_query['or'][] = 'member_names';
				$member_parameters['member_names'] = $member_names;
			}

			require_once(SUBSDIR . '/Members.subs.php');
			$members = membersBy($member_query, $member_parameters);

			// @todo Add $_POST['additional'] to templates!

			// Do the updates...
			if (!empty($members))
			{
				require_once(SUBSDIR . '/Membergroups.subs.php');
				addMembersToGroup($members, $current_group, isset($_POST['additional']) || $context['group']['hidden'] ? 'only_additional' : 'auto', true);
			}
		}

		// Sort out the sorting!
		$sort_methods = array(
			'name' => 'real_name',
			'email' => allowedTo('moderate_forum') ? 'email_address' : 'hide_email ' . (isset($_REQUEST['desc']) ? 'DESC' : 'ASC') . ', email_address',
			'active' => 'last_login',
			'registered' => 'date_registered',
			'posts' => 'posts',
		);

		// They didn't pick one, default to by name..
		if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
		{
			$context['sort_by'] = 'name';
			$querySort = 'real_name';
		}
		// Otherwise default to ascending.
		else
		{
			$context['sort_by'] = $_REQUEST['sort'];
			$querySort = $sort_methods[$_REQUEST['sort']];
		}

		$context['sort_direction'] = isset($_REQUEST['desc']) ? 'down' : 'up';

		require_once(SUBSDIR . '/Members.subs.php');

		// The where on the query is interesting. Non-moderators should only see people who are in this group as primary.
		if ($context['group']['can_moderate'])
			$where = $context['group']['is_post_group'] ? 'in_post_group' : 'in_group';
		else
			$where = $context['group']['is_post_group'] ? 'in_post_group' : 'in_group_no_add';

		// Count members of the group.
		$context['total_members'] = countMembersBy(array('or' => array($where)), array($where => $current_group));
		$context['total_members'] = comma_format($context['total_members']);

		// Create the page index.
		$context['page_index'] = constructPageIndex($scripturl . '?action=' . ($context['group']['can_moderate'] ? 'moderate;area=viewgroups' : 'groups') . ';sa=members;group=' . $current_group . ';sort=' . $context['sort_by'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], $context['total_members'], $modSettings['defaultMaxMembers']);
		$context['start'] = $_REQUEST['start'];
		$context['can_moderate_forum'] = allowedTo('moderate_forum');

		$context['members'] = membersBy(array('or' => array($where)), array($where => $current_group), true);
		foreach ($context['members'] as $id => $row)
		{
			$last_online = empty($row['last_login']) ? $txt['never'] : standardTime($row['last_login']);

			// Italicize the online note if they aren't activated.
			if ($row['is_activated'] % 10 != 1)
				$last_online = '<em title="' . $txt['not_activated'] . '">' . $last_online . '</em>';

			$context['members'][$id] = array(
				'id' => $row['id_member'],
				'name' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'email' => $row['email_address'],
				'show_email' => showEmailAddress(!empty($row['hide_email']), $row['id_member']),
				'ip' => '<a href="' . $scripturl . '?action=trackip;searchip=' . $row['member_ip'] . '">' . $row['member_ip'] . '</a>',
				'registered' => standardTime($row['date_registered']),
				'last_online' => $last_online,
				'posts' => comma_format($row['posts']),
				'is_activated' => $row['is_activated'] % 10 == 1,
			);
		}

		// Select the template.
		$context['sub_template'] = 'group_members';
		$context['page_title'] = $txt['membergroups_members_title'] . ': ' . $context['group']['name'];
		createToken('mod-mgm');
	}

	/**
	 * Show and manage all group requests.
	 */
	public function action_requests()
	{
		global $txt, $context, $scripturl, $user_info, $modSettings, $language;

		$db = database();

		// Set up the template stuff...
		$context['page_title'] = $txt['mc_group_requests'];
		$context['sub_template'] = 'show_list';
		$context[$context['moderation_menu_name']]['tab_data'] = array(
			'title' => $txt['mc_group_requests'],
		);

		// Verify we can be here.
		if ($user_info['mod_cache']['gq'] == '0=1')
			isAllowedTo('manage_membergroups');

		// Normally, we act normally...
		$where = $user_info['mod_cache']['gq'] == '1=1' || $user_info['mod_cache']['gq'] == '0=1' ? $user_info['mod_cache']['gq'] : 'lgr.' . $user_info['mod_cache']['gq'];
		$where_parameters = array();

		// We've submitted?
		if (isset($_POST[$context['session_var']]) && !empty($_POST['groupr']) && !empty($_POST['req_action']))
		{
			checkSession('post');
			validateToken('mod-gr');

			// Clean the values.
			foreach ($_POST['groupr'] as $k => $request)
				$_POST['groupr'][$k] = (int) $request;

			// If we are giving a reason (And why shouldn't we?), then we don't actually do much.
			if ($_POST['req_action'] == 'reason')
			{
				// Different sub template...
				$context['sub_template'] = 'group_request_reason';

				// And a limitation. We don't care that the page number bit makes no sense, as we don't need it!
				$where .= ' AND lgr.id_request IN ({array_int:request_ids})';
				$where_parameters['request_ids'] = $_POST['groupr'];

				$context['group_requests'] = list_getGroupRequests(0, $modSettings['defaultMaxMessages'], 'lgr.id_request', $where, $where_parameters);

				// Let obExit etc sort things out.
				obExit();
			}
			// Otherwise we do something!
			else
			{
				// Get the details of all the members concerned...
				$request = $db->query('', '
					SELECT lgr.id_request, lgr.id_member, lgr.id_group, mem.email_address, mem.id_group AS primary_group,
						mem.additional_groups AS additional_groups, mem.lngfile, mem.member_name, mem.notify_types,
						mg.hidden, mg.group_name
					FROM {db_prefix}log_group_requests AS lgr
						INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
						INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
					WHERE ' . $where . '
						AND lgr.id_request IN ({array_int:request_list})
					ORDER BY mem.lngfile',
					array(
						'request_list' => $_POST['groupr'],
					)
				);
				$email_details = array();
				$group_changes = array();
				while ($row = $db->fetch_assoc($request))
				{
					$row['lngfile'] = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

					// If we are approving work out what their new group is.
					if ($_POST['req_action'] == 'approve')
					{
						// For people with more than one request at once.
						if (isset($group_changes[$row['id_member']]))
						{
							$row['additional_groups'] = $group_changes[$row['id_member']]['add'];
							$row['primary_group'] = $group_changes[$row['id_member']]['primary'];
						}
						else
							$row['additional_groups'] = explode(',', $row['additional_groups']);

						// Don't have it already?
						if ($row['primary_group'] == $row['id_group'] || in_array($row['id_group'], $row['additional_groups']))
							continue;

						// Should it become their primary?
						if ($row['primary_group'] == 0 && $row['hidden'] == 0)
							$row['primary_group'] = $row['id_group'];
						else
							$row['additional_groups'][] = $row['id_group'];

						// Add them to the group master list.
						$group_changes[$row['id_member']] = array(
							'primary' => $row['primary_group'],
							'add' => $row['additional_groups'],
						);
					}

					// Add required information to email them.
					if ($row['notify_types'] != 4)
						$email_details[] = array(
							'rid' => $row['id_request'],
							'member_id' => $row['id_member'],
							'member_name' => $row['member_name'],
							'group_id' => $row['id_group'],
							'group_name' => $row['group_name'],
							'email' => $row['email_address'],
							'language' => $row['lngfile'],
						);
				}
				$db->free_result($request);

				// Remove the evidence...
				$db->query('', '
					DELETE FROM {db_prefix}log_group_requests
					WHERE id_request IN ({array_int:request_list})',
					array(
						'request_list' => $_POST['groupr'],
					)
				);

				// Ensure everyone who is online gets their changes right away.
				updateSettings(array('settings_updated' => time()));

				if (!empty($email_details))
				{
					require_once(SUBSDIR . '/Mail.subs.php');

					// They are being approved?
					if ($_POST['req_action'] == 'approve')
					{
						// Make the group changes.
						foreach ($group_changes as $id => $groups)
						{
							// Sanity check!
							foreach ($groups['add'] as $key => $value)
								if ($value == 0 || trim($value) == '')
									unset($groups['add'][$key]);

							$db->query('', '
								UPDATE {db_prefix}members
								SET id_group = {int:primary_group}, additional_groups = {string:additional_groups}
								WHERE id_member = {int:selected_member}',
								array(
									'primary_group' => $groups['primary'],
									'selected_member' => $id,
									'additional_groups' => implode(',', $groups['add']),
								)
							);
						}

						$lastLng = $user_info['language'];
						foreach ($email_details as $email)
						{
							$replacements = array(
								'USERNAME' => $email['member_name'],
								'GROUPNAME' => $email['group_name'],
							);

							$emaildata = loadEmailTemplate('mc_group_approve', $replacements, $email['language']);

							sendmail($email['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
						}
					}
					// Otherwise, they are getting rejected (With or without a reason).
					else
					{
						// Same as for approving, kind of.
						$lastLng = $user_info['language'];
						foreach ($email_details as $email)
						{
							$custom_reason = isset($_POST['groupreason']) && isset($_POST['groupreason'][$email['rid']]) ? $_POST['groupreason'][$email['rid']] : '';

							$replacements = array(
								'USERNAME' => $email['member_name'],
								'GROUPNAME' => $email['group_name'],
							);

							if (!empty($custom_reason))
								$replacements['REASON'] = $custom_reason;

							$emaildata = loadEmailTemplate(empty($custom_reason) ? 'mc_group_reject' : 'mc_group_reject_reason', $replacements, $email['language']);

							sendmail($email['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
						}
					}
				}

				// Restore the current language.
				loadLanguage('ModerationCenter');
			}
		}

		// We're going to want this for making our list.
		require_once(SUBSDIR . '/List.subs.php');

		// This is all the information required for a group listing.
		$listOptions = array(
			'id' => 'group_request_list',
			'width' => '100%',
			'items_per_page' => $modSettings['defaultMaxMessages'],
			'no_items_label' => $txt['mc_groupr_none_found'],
			'base_href' => $scripturl . '?action=groups;sa=requests',
			'default_sort_col' => 'member',
			'get_items' => array(
				'function' => 'list_getGroupRequests',
				'params' => array(
					$where,
					$where_parameters,
				),
			),
			'get_count' => array(
				'function' => 'list_getGroupRequestCount',
				'params' => array(
					$where,
					$where_parameters,
				),
			),
			'columns' => array(
				'member' => array(
					'header' => array(
						'value' => $txt['mc_groupr_member'],
					),
					'data' => array(
						'db' => 'member_link',
					),
					'sort' => array(
						'default' => 'mem.member_name',
						'reverse' => 'mem.member_name DESC',
					),
				),
				'group' => array(
					'header' => array(
						'value' => $txt['mc_groupr_group'],
					),
					'data' => array(
						'db' => 'group_link',
					),
					'sort' => array(
						'default' => 'mg.group_name',
						'reverse' => 'mg.group_name DESC',
					),
				),
				'reason' => array(
					'header' => array(
						'value' => $txt['mc_groupr_reason'],
					),
					'data' => array(
						'db' => 'reason',
					),
				),
				'date' => array(
					'header' => array(
						'value' => $txt['date'],
						'style' => 'width: 18%; white-space:nowrap;',
					),
					'data' => array(
						'db' => 'time_submitted',
					),
				),
				'action' => array(
					'header' => array(
						'value' => '<input type="checkbox" class="input_check" onclick="invertAll(this, this.form);" />',
						'style' => 'width: 4%;text-align: center;',
					),
					'data' => array(
						'sprintf' => array(
							'format' => '<input type="checkbox" name="groupr[]" value="%1$d" class="input_check" />',
							'params' => array(
								'id' => false,
							),
						),
						'class' => 'centertext',
					),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=groups;sa=requests',
				'include_sort' => true,
				'include_start' => true,
				'hidden_fields' => array(
					$context['session_var'] => $context['session_id'],
				),
				'token' => 'mod-gr',
			),
			'additional_rows' => array(
				array(
					'position' => 'bottom_of_list',
					'value' => '
						<select name="req_action" onchange="if (this.value != 0 &amp;&amp; (this.value == \'reason\' || confirm(\'' . $txt['mc_groupr_warning'] . '\'))) this.form.submit();">
							<option value="0">' . $txt['with_selected'] . ':</option>
							<option value="0">---------------------</option>
							<option value="approve">' . $txt['mc_groupr_approve'] . '</option>
							<option value="reject">' . $txt['mc_groupr_reject'] . '</option>
							<option value="reason">' . $txt['mc_groupr_reject_w_reason'] . '</option>
						</select>
						<input type="submit" name="go" value="' . $txt['go'] . '" onclick="var sel = document.getElementById(\'req_action\'); if (sel.value != 0 &amp;&amp; sel.value != \'reason\' &amp;&amp; !confirm(\'' . $txt['mc_groupr_warning'] . '\')) return false;" class="right_submit" />',
					'class' => 'floatright',
				),
			),
		);

		// Create the request list.
		createToken('mod-gr');
		createList($listOptions);

		$context['default_list'] = 'group_request_list';
	}
}

/**
 * Callback function for createList().
 *
 * @param string $where
 * @param string $where_parameters
 * @return int, the count of group requests
 */
function list_getGroupRequestCount($where, $where_parameters)
{
	$db = database();

	$request = $db->query('', '
		SELECT COUNT(*)
		FROM {db_prefix}log_group_requests AS lgr
		WHERE ' . $where,
		array_merge($where_parameters, array(
		))
	);
	list ($totalRequests) = $db->fetch_row($request);
	$db->free_result($request);

	return $totalRequests;
}

/**
 * Callback function for createList()
 *
 * @param int $start
 * @param int $items_per_page
 * @param string $sort
 * @param string $where
 * @param string $where_parameters
 * @return array, an array of group requests
 * Each group request has:
 * 		'id'
 * 		'member_link'
 * 		'group_link'
 * 		'reason'
 * 		'time_submitted'
 */
function list_getGroupRequests($start, $items_per_page, $sort, $where, $where_parameters)
{
	global $scripturl;

	$db = database();

	$request = $db->query('', '
		SELECT lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, lgr.reason,
			mem.member_name, mg.group_name, mg.online_color, mem.real_name
		FROM {db_prefix}log_group_requests AS lgr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
		WHERE ' . $where . '
		ORDER BY {raw:sort}
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($where_parameters, array(
			'sort' => $sort,
		))
	);
	$group_requests = array();
	while ($row = $db->fetch_assoc($request))
	{
		$group_requests[] = array(
			'id' => $row['id_request'],
			'member_link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'group_link' => '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>',
			'reason' => censorText($row['reason']),
			'time_submitted' => standardTime($row['time_applied']),
		);
	}
	$db->free_result($request);

	return $group_requests;
}

/**
 * Act as an entrance for all group related activity.
 *
 * @todo Where is this used? Did a function name get missed in a refactoring?
 */
function ModerateGroups()
{
	global $context, $user_info;

	// You need to be allowed to moderate groups...
	if ($user_info['mod_cache']['gq'] == '0=1')
		isAllowedTo('manage_membergroups');

	// Load the group templates.
	loadTemplate('ModerationCenter');

	// Setup the subactions...
	$subactions = array(
		'requests' => 'action_requests',
		'view' => 'action_members',
	);

	if (!isset($_GET['sa']) || !isset($subactions[$_GET['sa']]))
		$_GET['sa'] = 'view';
	$context['sub_action'] = $_GET['sa'];

	// Call the relevant method.
	$controller = new Groups_Controller();
	$controller->subactions[$context['sub_action']]();
}