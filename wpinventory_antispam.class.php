<?php

// No direct access allowed.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tiny class just for internationalization purposes.
 */
abstract class WPIMAntiSpamCore extends WPIMCore {

	const LANG = 'wpinventory_antispam';

	const NONCE_ACTION = 'wpinventory_reserve_antispam';

	/**
	 * Abstraction of the WP language function.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public static function __( $text ) {
		return __( $text, self::LANG );
	}

	/**
	 * Abstraction of the WP language function (echo)
	 *
	 * @param string $text
	 */
	public static function _e( $text ) {
		echo self::__( $text );
	}
}

/**
 * Class WPIMAntiSpam
 */
Class WPIMAntiSpam extends WPIMAntiSpamCore {

	private static $reserve_args;

	private static $item_key = 'anti_spam';

	/**
	 * Set everything up.
	 */
	public static function initialize() {
		add_filter( 'wpim_add_ons_list', array( __CLASS__, 'wpim_add_ons_list' ) );

//		if ( ! parent::validate( self::$item_key ) ) {
//			return;
//		}

		add_filter( 'wpim_default_config', [ __CLASS__, 'wpim_default_config' ] );
		add_action( 'wpim_edit_settings_reserve', [ __CLASS__, 'wpim_edit_settings' ] );
		add_filter( 'wpim_reserve_config', [ __CLASS__, 'wpim_reserve_config' ] );
		add_action( 'wpim_reserve_form', [ __CLASS__, 'wpim_reserve_form' ] );
		add_action( 'wpim_checkout_form', [ __CLASS__, 'wpim_reserve_form' ] );
		add_filter( 'wpim_reserve_form_errors', [ __CLASS__, 'wpim_reserve_form_errors' ] );
		add_action( 'init', [ __CLASS__, 'init' ] );
	}

	/**
	 * WordPress init action.
	 * Sets up internationalization
	 */
	public static function init() {
		// Enable internationalization
		if ( ! load_plugin_textdomain( 'wpinventory_antispam', FALSE, '/wp-content/languages/' ) ) {
			load_plugin_textdomain( 'wpinventory_antispam', FALSE, basename( dirname( __FILE__ ) ) . "/languages/" );
		}
	}

	/**
	 * Adds the breadcrumb name field to the default config
	 *
	 * @param array $default
	 *
	 * @return array
	 */
	public static function wpim_default_config( $default ) {
		$default['antispam_verify_email']           = 1;
		$default['antispam_nonce']                  = 1;
		$default['antispam_honeypot']               = 1;
		$default['antispam_recaptcha']              = 0;
		$default['antispam_recaptcha_site_key']     = '';
		$default['antispam_recaptcha_secret_key']   = '';
		$default['antispam_max_links_in_message']   = '0';
		$default['antispam_max_domains_in_message'] = '2';
		$default['license_key_' . self::$item_key] = '';

		return $default;
	}

	/**
	 * Displays the WPIM Admin Settings (selecting the breadcrumb name field)
	 */
	public static function wpim_edit_settings( $settings ) {

		$antispam_verify_email           = self::_get( $settings, 'antispam_verify_email', 1 );
		$antispam_nonce                  = self::_get( $settings, 'antispam_nonce', 1 );
		$antispam_honeypot               = self::_get( $settings, 'antispam_honeypot', 1 );
		$antispam_recaptcha              = self::_get( $settings, 'antispam_recaptcha', 0 );
		$antispam_recaptcha_site_key     = self::_get( $settings, 'antispam_recaptcha_site_key', '' );
		$antispam_recaptcha_secret_key   = self::_get( $settings, 'antispam_recaptcha_secret_key', '' );
		$antispam_max_links_in_message   = self::_get( $settings, 'antispam_max_links_in_message', 0 );
		$antispam_max_domains_in_message = self::_get( $settings, 'antispam_max_domains_in_message', 2 );

		$recaptcha_options = [
			0 => self::__( 'No reCAPTCHA' ),
			1 => self::__( 'Invisible reCAPTCHA' ),
			2 => self::__( 'Visible reCAPTCHA' )
		];

		$max_list = array_combine( range( 0, 11 ), range( 0, 11 ) );

		$max_list[11] = self::__( 'No Limit' );

		echo '<tr class="subtab"><th colspan="2"><h4>' . self::__( 'Anti-Spam' ) . '</h4></th></tr>';

		echo '<tr><th>' . self::__( 'Verify Email Address' ) . '</th>';
		echo '<td>';
		echo WPIMAdmin::dropdown_yesno( 'antispam_verify_email', $antispam_verify_email );
		echo '<p class="description">' . self::__( 'Recommended: "Yes".<br>Checks that the email address provided appears to be a legitimate address.' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th>' . self::__( 'Use NONCE' ) . '</th>';
		echo '<td>';
		echo WPIMAdmin::dropdown_yesno( 'antispam_nonce', $antispam_nonce );
		echo '<p class="description">' . self::__( 'Recommended: "Yes".<br>Sets a secret code on the form that would be difficult for spammers to guess.' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th>' . self::__( 'Use Honeypot' ) . '</th>';
		echo '<td>';
		echo WPIMAdmin::dropdown_yesno( 'antispam_honeypot', $antispam_honeypot );
		echo '<p class="description">' . self::__( 'Recommended: "Yes".<br>Honeypots are a technique that detect spam-bots by tricking them into completing form fields that should not be completed.' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th>' . self::__( 'Use reCAPTCHA' ) . '</th>';
		echo '<td>';
		echo WPIMAdmin::dropdown_array( 'antispam_recaptcha', $antispam_recaptcha, $recaptcha_options );
		echo '<p class="description">' . sprintf( self::__( 'Recommended: "No", or "Invisible".<br>Utilizes %sGoogle reCAPTCHA%s technology.  You will need to %sregister for a reCAPTCHA key%s and enter it below.' ), '<a href="https://www.google.com/recaptcha/intro/" target="_blank">', '</a>', '<a href="https://www.google.com/recaptcha/admin" target="_blank">', '</a>' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th>' . self::__( 'reCAPTCHA Site Key' ) . '</th>';
		echo '<td>';
		echo '<input type="text" class="widefat" name="antispam_recaptcha_site_key" value="' . $antispam_recaptcha_site_key . '" />';
		echo '<p class="description">' . sprintf( self::__( 'Only required if using %s reCAPTCHA %s.  %s How to get your site keys.%s' ), '<a href="https://www.google.com/recaptcha/intro/" target="_blank">', '</a>', '<a href="https://www.google.com/recaptcha/admin" target="_blank">', '</a>' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th>' . self::__( 'reCAPTCHA Secret Key' ) . '</th>';
		echo '<td>';
		echo '<input type="text" class="widefat" name="antispam_recaptcha_secret_key" value="' . $antispam_recaptcha_secret_key . '" />';
		echo '<p class="description">' . sprintf( self::__( 'Only required if using %s reCAPTCHA %s.  %s How to get your keys.%s' ), '<a href="https://www.google.com/recaptcha/intro/" target="_blank">', '</a>', '<a href="https://www.google.com/recaptcha/admin" target="_blank">', '</a>' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th>' . self::__( 'Maximum Links in Message' ) . '</th>';
		echo '<td>';
		echo WPIMAdmin::dropdown_array( 'antispam_max_links_in_message', $antispam_max_links_in_message, $max_list );
		echo '<p class="description">' . self::__( 'Recommended: 0 or 1.<br>Links in a message are typical of spam. Keep in mind that some users may want to provide links in their message to you.  Set this to be the maximum number of links that will be permitted.' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th>' . self::__( 'Maximum Domains in Message' ) . '</th>';
		echo '<td>';
		echo WPIMAdmin::dropdown_array( 'antispam_max_domains_in_message', $antispam_max_domains_in_message, $max_list );
		echo '<p class="description">' . self::__( 'Recommended: 2 to 4.<br>Domain names (for example, www.google.com) in a message are also typical of spam.  All email addresses contain a domain name, since all e-mail addresses contain a domain name.<br><strong>Important: This should be greater than the "Maximum Links" setting above.</strong>' ) . '</p>';
		echo '</td>';
		echo '</tr>';
	}

	public static function wpim_reserve_config( $args ) {
		self::$reserve_args = $args;

		return $args;
	}

	public static function wpim_reserve_form( $args ) {
		self::nonce_field();
		self::honeypot_field();
		self::recaptcha_field();
	}

	/**
	 * Render the anti-spam nonce field, if the user has selected "use NONCE"
	 */
	public static function nonce_field() {
		if ( ! self::$config->get( 'antispam_nonce' ) ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, '_wpim_anti_spam_nonce', FALSE, TRUE );
	}

	/**
	 * Render the honeypot field, if the user has selected "use Honeypot"
	 */
	public static function honeypot_field() {
		if ( ! self::$config->get( 'antispam_honeypot' ) ) {
			return;
		}

		echo '<div class="wpim_honey_message"><label>Leave Empty:</label><input type="text" name="message" value="" /></div>';
		echo '<style>.wpim_honey_message {left: -999em; position: absolute;}</style>';
	}

	/**
	 * Render the reCAPTCHA challenge, if the user has selected "user reCAPTCHA"
	 */
	public static function recaptcha_field() {
		$recaptcha = ( int) self::$config->get( 'antispam_recaptcha' );
		if ( ! $recaptcha ) {
			return;
		}

		$data_bind = ( 1 == $recaptcha ) ? ' data-bind="wpim_reserve_submit" data-callback="submitReserveForm"' : '';

		$recaptcha_site_key = self::$config->get( 'antispam_recaptcha_site_key' );

		if ( ! $recaptcha_site_key ) {
			self::_e( 'The reCAPTCHA key has not been configured, therefore CAPTCHA cannot work.' );

			return;
		} ?>
        <script src='https://www.google.com/recaptcha/api.js'></script>
        <div
                class="g-recaptcha"
			<?php echo $data_bind; ?>
                data-sitekey="<?php echo $recaptcha_site_key; ?>"></div>
		<?php if ( 1 == $recaptcha ) { ?>
            <script>
              function submitReserveForm( el ) {
                var $form = jQuery( 'form.wpinventory_reserve' );
                if ( validate_form( $form ) ) {
                  $form.submit();
                } else {
                  grecaptcha.reset();
                }

                function validate_form( $form ) {
                  jQuery( 'input[name="message"]', $form ).val( '' );
                  return true;
                }
              }
            </script>
		<?php }
	}

	/**
	 * Performs all of the anti-spam checks the user has specified in settings.
	 *
	 * @param string $form_errors
	 *
	 * @return string
	 */
	public static function wpim_reserve_form_errors( $form_errors ) {
		$errors   = [];
		$errors[] = self::nonce_validate();
		$errors[] = self::email_validate();
		$errors[] = self::honeypot_validate();
		$errors[] = self::recaptcha_validate();
		$errors[] = self::max_links_validate();
		$errors[] = self::max_domains_validate();

		$errors = implode( '<br>', array_filter( $errors ) );

		if ( $form_errors && $errors ) {
			$form_errors .= '<br>';
		}

		$form_errors .= $errors;

		return $form_errors;
	}

	/**
	 * Validates nonce on the form if "use nonce" is turned on.
	 *
	 * @return string
	 */
	public static function nonce_validate() {
		if ( ! (int) self::$config->get( 'antispam_nonce' ) ) {
			return '';
		}

		$nonce = self::request( '_wpim_anti_spam_nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return self::__( 'Security Error.  Please try again.' );
		}
	}

	/**
	 * Applies extra validation to the email address if "verify email" is turned on.
	 *
	 * @return string
	 */
	public static function email_validate() {
		if ( ! (int) self::$config->get( 'antispam_verify_email' ) ) {
			return '';
		}

		$email = self::request( 'wpinventory_reserve_email' );

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return self::__( 'Email address is not valid.' );
		}
	}

	/**
	 * Verifies the honeypot field is submitted but empty if the "use honeypot" is turned on.
	 *
	 * @return string
	 */
	public static function honeypot_validate() {
		if ( ! (int) self::$config->get( 'antispam_honeypot' ) ) {
			return '';
		}

		// Message is a honeypot field.  It MUST be submitted, but MUST be empty
		if ( ! array_key_exists( 'message', $_POST ) || '' != $_POST['message'] ) {
			return self::__( 'This appears to be spam.  Please contact us if you are receiving this message.' );
		}
	}

	/**
	 * Validates the reCAPTCHA solution if "use reCAPTCHA" is turned on.
	 *
	 * @return string
	 */
	public static function recaptcha_validate() {
		if ( ! (int) self::$config->get( 'antispam_recaptcha' ) ) {
			return '';
		}

		$secret_key = self::$config->get( 'antispam_recaptcha_secret_key' );
		$response   = self::request( 'g-recaptcha-response' );

		if ( ! $response ) {
			return self::__( 'Please complete the "I am not a robot" challenge.' );
		}

		$ip = $_SERVER['REMOTE_ADDR'];

		$url = "https://www.google.com/recaptcha/api/siteverify?secret={$secret_key}&response={$response}&remoteip={$ip}";

		$response = json_decode( file_get_contents( $url ), TRUE );

		if ( empty( $response['success'] ) ) {
			return self::__( 'The "I am not not a robot" challenge was not completed properly.' );
		}

		return '';
	}

	/**
	 * Checks the "message" field for the max number if links set in the settings.
	 *
	 * @return string
	 */
	public static function max_links_validate() {
		$pattern = '/(<a.+?href=".+?")/';
		$message = self::request( 'wpinventory_reserve_message' );

		if ( ! trim( $message ) ) {
			return '';
		}

		$domains   = preg_match_all( $pattern, stripslashes( $message ) );
		$max_links = (int) self::$config->get( 'antispam_max_links_in_message' );

		// "11" is the "no limit" value
		if ( $max_links < 11 && $domains > $max_links ) {
			return sprintf( self::__( 'You may only include up to %d links in your message.' ), $max_links );
		}

		return '';
	}

	/**
	 * Checks the "message" field for the max number of domains set in the settings.
	 *
	 * @return string
	 */
	public static function max_domains_validate() {
		$pattern = '/(www\.)?[^ ]+?\.(aero|asia|biz|cat|com|coop|info|int|jobs|mobi|museum|name|net|org|post|pro|tel|travel|mlcee|xxx|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bl|bm|bn|bo|bq|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cw|cx|cy|cz|de|dj|dk|dm|do|dz|ec|ee|eg|eh|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mf|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|st|su|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|za|zm|zw)/';
		$message = self::request( 'wpinventory_reserve_message' );

		if ( ! trim( $message ) ) {
			return '';
		}

		$domains     = preg_match_all( $pattern, $message );
		$max_domains = (int) self::$config->get( 'antispam_max_domains_in_message' );

		// "11" is the "no limit" value
		if ( $max_domains < 11 && $domains > $max_domains ) {
			return sprintf( self::__( 'You may only include up to %d domain names in your message.' ), $max_domains );
		}

		return '';
	}


	/**
	 * Filter for add-ons to indicate that Advanced User is installed
	 *
	 * @param array $add_ons
	 *
	 * @return false|array
	 */
	public static function wpim_add_ons_list( $add_ons ) {
		if ( ! $add_ons ) {
			return FALSE;
		}

		foreach ( $add_ons AS $index => $add_on ) {
			if ( stripos( $add_on->title, 'anti-spam' ) !== FALSE ) {
				$add_ons[ $index ]->installed = TRUE;
				self::$item_key    = $add_on->key;
			}
		}

		return $add_ons;
	}
}

WPIMAntiSpam::initialize();
