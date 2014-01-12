<?php
//namespace Habari;

class SocialAdmin extends Plugin
{
	/**
	* Add additional controls to the User page
	*
	* @param FormUI $form The form that is used on the User page
	* @param Post $post The user being edited
	**/
	public function action_form_user( $form, $edit_user )
	{
		$services = Plugins::filter( 'socialauth_services', array() );
		if( count($services) ) {
			$socials = $form->append( 'wrapper', 'linked_socialnets_wrapper', 'Linked Social Nets');
			$socials->class = 'container settings';
			$socials->append( 'static', 'linked_socialnets', _t( '<h2>Linked Social Nets</h2>', __CLASS__ ) );
			
			foreach( $services as $service ) {
				$fieldname = "servicelink_$service";
				$fieldcaption = isset( $edit_user->info->{$fieldname} ) ? _t( 'Linked to %s account', array( $service ), __CLASS__ ) : '<a href="' . $form->get_theme()->socialauth_link( $service, array( 'state' => 'userinfo' ) ) . '">' . _t( 'Link %s account', array( $service ), __CLASS__ ) . '</a>';
				$servicefield = $socials->append( 'text', $fieldname, 'null:null', $fieldcaption );
				$servicefield->value = isset( $edit_user->info->{$fieldname} ) ? $edit_user->info->{$fieldname} : '';
				$servicefield->class[] = 'item clear';
				$servicefield->template = "optionscontrol_text";
			}
			
			$form->move_after( $socials, $form->user_info );
		}
	}
	
	/**
	 * Add the Additional User Fields to the list of valid field names.
	 * This causes adminhandler to recognize the fields and
	 * to set the userinfo record appropriately
	**/
	public function filter_adminhandler_post_user_fields( $fields )
	{
		$services = Plugins::filter( 'socialauth_services', array() );
		if ( !is_array($services) || count( $services ) == 0 ) {
			return;
		}

		foreach($services as $service) {
			$fields[ $service ] = "servicelink_$service";
		}
		return $fields;
	}

	/**
	 * Filter for fetching a user from a particular service
	 */
	public function filter_socialauth_user( $service, $id )
	{
		$fieldname = "servicelink_$service";

		$users = Users::get( array( 'info' => array( $fieldname => $id ) ) );

		if ( count ( $users ) >= 1 ) {
			// just return the first one
			return $users[0];
		} else {
			return false;
		}
	}
	
	/*
	 * Handle the result when a user identified himself
	 */
	public function action_socialauth_identified( $service, $userdata, $state = '' )
	{
		$fieldname = "servicelink_$service";

		switch($state) {
			case 'usercreate':
				// make sure we don't already have one
				$users = Users::get( array( 'info' => array( "servicelink_$service" => $userdata['id'] ) ) );

				if ( count( $users ) < 1 ) {
					// Generate a unique password
					$password = UUID::get();

					$user = new User( array(
						'username' => $userdata['username'],
						'email' => $userdata['email'],
						'password' => Utils::crypt($password)
					));

					$user->info->{$fieldname} = $userdata['id'];
					$user->info->displayname = $userdata['name'];
					$user->info->imageurl = $userdata['portrait_url'];

					$user->insert();
				}

				break;
			case 'userinfo':
				$user = User::identify();
				$user->info->{$fieldname} = $userdata['id'];
				$user->update();
				Utils::redirect( URL::get( 'admin', array( 'page' => 'user', 'user' => $user->username ) ) );
				break;
			case 'loginform':
				$users = Users::get( array( 'info' => array( $fieldname => $userdata['id'] ) ) );
				if( count( $users ) > 1 ) {
					// TODO: Handle multiple linked accounts
				}
				else {
					$user = $users[0];
					$user->remember();
					Eventlog::log( _t( 'Successful %1$s login for %2$s', array( $service, $user->username ), __CLASS__ ), 'info', 'authentication' );
					Utils::redirect( URL::get( 'admin' ) );
				}
				break;
		}
	}
	
	/**
	 * function action_theme_loginform_controls
	 * add a checkbox to the login screen to control our cookie
	**/
	public function action_form_login($form)
	{
		$services = Plugins::filter( 'socialauth_services', array() );
		$html = '';
		foreach( $services as $service ) {
			$html .= '<p><a href="' . $form->get_theme()->socialauth_link($service, array('state' => 'loginform')) . '">' . _t( 'Login with %s', array ( $service ), __CLASS__ ) . '</a></p>';
		}
		$form->append('static', 'socialadmin', $html);
	}
	
	/*
	 * Habari 0.9 style form editing, does the same as the above function
	 */
	public function action_theme_loginform_controls()
	{
		$services = Plugins::filter( 'socialauth_services', array() );
		$html = '';
		$theme = Themes::create();
		foreach( $services as $service ) {
			$html .= '<p class="social ' . $service . '"><a href="' . $theme->socialauth_link($service, array('state' => 'loginform')) . '">' . _t( 'Login with %s', array ( $service ), __CLASS__ ) . '</a></p>';
		}
		echo $html;
	}
}
?>
