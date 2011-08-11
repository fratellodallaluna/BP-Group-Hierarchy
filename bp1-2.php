<?php
/**
 * Functions for BuddyPress 1.2 compatibility
 */

/*************************************************************************
***********************PAGE ROUTING AND NAVIGATION************************
*************************************************************************/

/**
 * Catch requests for the groups component and find the requested group
 */
function bp_group_hierarchy_do_routing() {
	global $current_component, $current_action, $action_variables, $bp;

	if(defined('BP_VERSION') && floatval(BP_VERSION) < 1.3) {

		$groups_slug = bp_get_groups_hierarchy_root_slug();

		bp_group_hierarchy_debug('Routing requests for BP 1.2');
		bp_group_hierarchy_debug('Current component: ' . $current_component);
		bp_group_hierarchy_debug('Current action: ' . $current_action);
		bp_group_hierarchy_debug('Group slug: ' . $groups_slug);

		
		// BP Groups not instantiated yet, and running groups_setup_globals() prevents proper routing, so just make a best-effort copy of the forbidden names list
		if($current_component == $groups_slug && !in_array($current_action, apply_filters( 'groups_forbidden_names', array( 'my-groups', 'create', 'invites', 'send-invites', 'forum', 'delete', 'add', 'admin', 'request-membership', 'members', 'settings', 'avatar', $groups_slug, '' ) ) ) ) {
			
			$action_vars = $action_variables;
			
			$group = new BP_Groups_Hierarchy( $current_action );
			if(!$group->id) {
				$current_action = '';
				bp_core_redirect( $bp->root_domain . '/' . $groups_slug . '/');
			}
			if($group->has_children()) {
				$parent = $group;
				if(is_array($action_variables)) {
					foreach($action_variables as $action_var) {
						$subgroup_id = $parent->check_slug($action_var, $parent->id);
						if($subgroup_id) {
							$action_var = array_shift($action_vars);
							$subgroup = new BP_Groups_Hierarchy( $subgroup_id );
							$current_action = $subgroup->slug;
							$parent = $subgroup;
						} else {
							// once we find something that isn't a group, we're done
							break;
						}
					}
				}
			}
			
			bp_group_hierarchy_debug('Action changed to: ' . $current_action);
			
			$action_variables = $action_vars;
			add_action( 'bp_setup_nav', 'bp_group_hierarchy_setup_nav', 5 );
			remove_action( 'bp_setup_nav', 'groups_setup_nav' );
		} else {
			bp_group_hierarchy_debug('Not rewriting current action.');
		}
	}
}
add_action( 'bp_group_hierarchy_route_requests', 'bp_group_hierarchy_do_routing' );

/**
 * Copy of the BP 1.2.x stock groups_setup_nav function fetching BP_Groups_Hierarchy objects
 */
function bp_group_hierarchy_setup_nav() {
	global $bp;

	if ( $bp->current_component == $bp->groups->slug && $group_id = BP_Groups_Hierarchy::group_exists($bp->current_action) ) {

		/* This is a single group page. */
		$bp->is_single_item = true;
		$bp->groups->current_group = new BP_Groups_Hierarchy( $group_id );

		/* Using "item" not "group" for generic support in other components. */
		if ( is_super_admin() )
			$bp->is_item_admin = 1;
		else
			$bp->is_item_admin = groups_is_user_admin( $bp->loggedin_user->id, $bp->groups->current_group->id );

		/* If the user is not an admin, check if they are a moderator */
		if ( !$bp->is_item_admin )
			$bp->is_item_mod = groups_is_user_mod( $bp->loggedin_user->id, $bp->groups->current_group->id );

		/* Is the logged in user a member of the group? */
		$bp->groups->current_group->is_user_member = ( is_user_logged_in() && groups_is_user_member( $bp->loggedin_user->id, $bp->groups->current_group->id ) ) ? true : false;

		/* Should this group be visible to the logged in user? */
		$bp->groups->current_group->is_group_visible_to_member = ( 'public' == $bp->groups->current_group->status || $is_member ) ? true : false;
	}

	/* Add 'Groups' to the main navigation */
	bp_core_new_nav_item( array( 'name' => sprintf( __( 'Groups <span>(%d)</span>', 'buddypress' ), groups_total_groups_for_user() ), 'slug' => $bp->groups->slug, 'position' => 70, 'screen_function' => 'groups_screen_my_groups', 'default_subnav_slug' => 'my-groups', 'item_css_id' => $bp->groups->id ) );

	$groups_link = $bp->loggedin_user->domain . $bp->groups->slug . '/';

	/* Add the subnav items to the groups nav item */
	bp_core_new_subnav_item( array( 'name' => __( 'My Groups', 'buddypress' ), 'slug' => 'my-groups', 'parent_url' => $groups_link, 'parent_slug' => $bp->groups->slug, 'screen_function' => 'groups_screen_my_groups', 'position' => 10, 'item_css_id' => 'groups-my-groups' ) );
	bp_core_new_subnav_item( array( 'name' => __( 'Invites', 'buddypress' ), 'slug' => 'invites', 'parent_url' => $groups_link, 'parent_slug' => $bp->groups->slug, 'screen_function' => 'groups_screen_group_invites', 'position' => 30, 'user_has_access' => bp_is_my_profile() ) );

	if ( $bp->current_component == $bp->groups->slug ) {

		if ( bp_is_my_profile() && !$bp->is_single_item ) {

			$bp->bp_options_title = __( 'My Groups', 'buddypress' );

		} else if ( !bp_is_my_profile() && !$bp->is_single_item ) {

			$bp->bp_options_avatar = bp_core_fetch_avatar( array( 'item_id' => $bp->displayed_user->id, 'type' => 'thumb' ) );
			$bp->bp_options_title = $bp->displayed_user->fullname;

		} else if ( $bp->is_single_item ) {

			// We are viewing a single group, so set up the
			// group navigation menu using the $bp->groups->current_group global.

			/* When in a single group, the first action is bumped down one because of the
			   group name, so we need to adjust this and set the group name to current_item. */
			$bp->current_item = $bp->current_action;

			$bp->current_action = array_shift($bp->action_variables);

			$bp->bp_options_title = $bp->groups->current_group->name;

			if ( !$bp->bp_options_avatar = bp_core_fetch_avatar( array( 'item_id' => $bp->groups->current_group->id, 'object' => 'group', 'type' => 'thumb', 'avatar_dir' => 'group-avatars', 'alt' => __( 'Group Avatar', 'buddypress' ) ) ) )
				$bp->bp_options_avatar = '<img src="' . esc_attr( $group->avatar_full ) . '" class="avatar" alt="' . esc_attr( $group->name ) . '" />';

			$group_link = $bp->root_domain . '/' . $bp->groups->slug . '/' . $bp->groups->current_group->slug . '/';

			// If this is a private or hidden group, does the user have access?
			if ( 'private' == $bp->groups->current_group->status || 'hidden' == $bp->groups->current_group->status ) {
				if ( $bp->groups->current_group->is_user_member && is_user_logged_in() || is_super_admin() )
					$bp->groups->current_group->user_has_access = true;
				else
					$bp->groups->current_group->user_has_access = false;
			} else {
				$bp->groups->current_group->user_has_access = true;
			}

			/* Reset the existing subnav items */
			bp_core_reset_subnav_items($bp->groups->slug);

			/* Add a new default subnav item for when the groups nav is selected. */
			bp_core_new_nav_default( array( 'parent_slug' => $bp->groups->slug, 'screen_function' => 'groups_screen_group_home', 'subnav_slug' => 'home' ) );

			/* Add the "Home" subnav item, as this will always be present */
			bp_core_new_subnav_item( array( 'name' => __( 'Home', 'buddypress' ), 'slug' => 'home', 'parent_url' => $group_link, 'parent_slug' => $bp->groups->slug, 'screen_function' => 'groups_screen_group_home', 'position' => 10, 'item_css_id' => 'home' ) );

			/* If the user is a group mod or more, then show the group admin nav item */
			if ( $bp->is_item_mod || $bp->is_item_admin )
				bp_core_new_subnav_item( array( 'name' => __( 'Admin', 'buddypress' ), 'slug' => 'admin', 'parent_url' => $group_link, 'parent_slug' => $bp->groups->slug, 'screen_function' => 'groups_screen_group_admin', 'position' => 20, 'user_has_access' => ( $bp->is_item_admin + (int)$bp->is_item_mod ), 'item_css_id' => 'admin' ) );

			// If this is a private group, and the user is not a member, show a "Request Membership" nav item.
			if ( !is_super_admin() && is_user_logged_in() && !$bp->groups->current_group->is_user_member && !groups_check_for_membership_request( $bp->loggedin_user->id, $bp->groups->current_group->id ) && $bp->groups->current_group->status == 'private' )
				bp_core_new_subnav_item( array( 'name' => __( 'Request Membership', 'buddypress' ), 'slug' => 'request-membership', 'parent_url' => $group_link, 'parent_slug' => $bp->groups->slug, 'screen_function' => 'groups_screen_group_request_membership', 'position' => 30 ) );

			if ( $bp->groups->current_group->enable_forum && function_exists('bp_forums_setup') )
				bp_core_new_subnav_item( array( 'name' => __( 'Forum', 'buddypress' ), 'slug' => 'forum', 'parent_url' => $group_link, 'parent_slug' => $bp->groups->slug, 'screen_function' => 'groups_screen_group_forum', 'position' => 40, 'user_has_access' => $bp->groups->current_group->user_has_access, 'item_css_id' => 'forums' ) );

			bp_core_new_subnav_item( array( 'name' => sprintf( __( 'Members (%s)', 'buddypress' ), number_format( $bp->groups->current_group->total_member_count ) ), 'slug' => 'members', 'parent_url' => $group_link, 'parent_slug' => $bp->groups->slug, 'screen_function' => 'groups_screen_group_members', 'position' => 60, 'user_has_access' => $bp->groups->current_group->user_has_access, 'item_css_id' => 'members'  ) );

			if ( is_user_logged_in() && groups_is_user_member( $bp->loggedin_user->id, $bp->groups->current_group->id ) ) {
				if ( function_exists('friends_install') )
					bp_core_new_subnav_item( array( 'name' => __( 'Send Invites', 'buddypress' ), 'slug' => 'send-invites', 'parent_url' => $group_link, 'parent_slug' => $bp->groups->slug, 'screen_function' => 'groups_screen_group_invite', 'item_css_id' => 'invite', 'position' => 70, 'user_has_access' => $bp->groups->current_group->user_has_access ) );
			}
		}
	}

	do_action( 'groups_setup_nav', $bp->groups->current_group->user_has_access );
}

?>