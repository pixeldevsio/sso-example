
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'pt/user/v1',
			'login',
			array(
				'method'   => WP_REST_Server::EDITABLE,
				'callback' => 'user_maybe_create_callback',
				'args'     => array(
					'id'         => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'      => array(
						'required'          => true,
						'validate_callback' => 'is_email',
					),
					'first_name' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'  => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}
);

function user_maybe_create_callback( $args ) {
	$pt_user_id = $args['id'];
	$email      = $args['email'];
	$first_name = $args['first_name'];
	$last_name  = $args['last_name'];
	( ! empty( $first_name ) && ! empty( $last_name ) ) ? $username = sanitize_text_field( strtolower( $first_name . '' . $last_name ) ) : $username = 'ptuser_' . rand( 10, 99999999 );

	$user_query = new WP_User_Query(
		array(
			'number'     => 1,
			'meta_key'   => 'pt_id',
			'meta_value' => $pt_user_id,
			'fields'     => array( 'ID', 'user_login' ),
		)
	);

	$user_query_id = $user_query->get_results();

	if ( empty( $user_query_id ) ) {
		$userdata = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'user_login' => $username,
			'user_pass'  => null, // When creating an user, `user_pass` is expected.
			'user_email' => $email,
		);

		$user_id = wp_insert_user( $userdata );

		// On success.
		if ( ! is_wp_error( $user_id ) ) {
			update_user_meta( $user_id, 'pt_id', $pt_user_id );
			update_user_meta( $user_id, 'pt_email', $email );
			return array(
				'status'     => 'User Created Successfully',
				'ID'         => $user_id,
				'username'   => $username,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'email'      => $email,
			);
		} else {
			return new WP_Error(
				'rest_forbidden',
				__( $user_id->get_error_message() ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
	} else {
		return array(
			'status'   => 'User Found',
			'ID'       => $user_query_id[0]->ID,
			'username' => $user_query_id[0]->user_login,
		);
	}

	return new WP_Error(
		'rest_forbidden',
		__( 'Invalid Request' ),
		array( 'status' => rest_authorization_required_code() )
	);
}


add_action( 'init', 'rewrite_tag_example_init' );
add_action( 'template_redirect', 'rewrite_tag_example_template_redirect' );
/**
 * Add rewrite rule and tag to WP
 */
function rewrite_tag_example_init() {
	// rewrite tag adds the matches found in the pattern to the global $wp_query
	add_rewrite_tag( '%ptloginid%', '(.*)' );
	add_rewrite_tag( '%pthash%', '(.*)' );
}
/**
 * Modify the query based on our rewrite tag
 */
function rewrite_tag_example_template_redirect() {
	if( ! get_query_var('pthash') ) {
		return;
	}

	if ( is_user_logged_in() && ( get_query_var( 'pthash' ) && get_query_var( 'ptloginid' ) )) {
		nocache_headers();
		wp_safe_redirect('/#courses');
		exit;
	}
	$salt = PHY_KEY;
	$loginID = get_query_var( 'ptloginid' );
	$pthash = get_query_var( 'pthash' );
	$hashCheck = hash( 'sha512', $salt . $loginID );
	
	if ( $hashCheck == $pthash ) {
		var_dump('Hash check verified: ' . $hashCheck);
	}

	$hashCheck = hash( 'sha512', $salt . get_query_var( 'ptloginid' ) );

	if ( $hashCheck == get_query_var( 'pthash' ) ) {
		$user = get_user_by( 'id', $loginID );
		if ( $user ) {
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID );
			do_action( 'wp_login', $user->user_login, $user );
			wp_redirect( '/#courses' );
			exit;
		} else {
			wp_redirect( '/?login=0' );
			exit;
		}
	} else {
		print_r( 'WebExercises Hash: ' . $hashCheck . ' <br /> PhysioTech Hash:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . get_query_var( 'pthash' ) );
		exit;
		wp_redirect( '/user-error' );
		exit;
	}
}
