<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if (!defined('CCA_WID_PLUGIN_DIR'))define('CCA_WID_PLUGIN_DIR', plugin_dir_path(__FILE__));  // done here so also in scope for filters/actions
if (!defined('CCA_MAXMIND_DIR'))define('CCA_MAXMIND_DIR', CCA_WID_PLUGIN_DIR . 'maxmind/');
if (!defined('CCA_MAXMIND_DATA_DIR')) define('CCA_MAXMIND_DATA_DIR', WP_CONTENT_DIR . '/cca_maxmind_data/');
define('CCA_X_MAX_CRON','cca_update_maxmind' );
if (file_exists(CCA_WID_PLUGIN_DIR . 'inc/update_maxmind.php')) include_once(CCA_WID_PLUGIN_DIR . 'inc/update_maxmind.php');

add_action( 'admin_init', 'cca_version_mangement' );

function cca_version_mangement(){  // credit to "thenbrent" www.wpaustralia.org/wordpress-forums/topic/update-plugin-hook/
  $plugin_info = get_plugin_data( CCA_INIT_FILE , false, false );
	$last_script_ver = get_option('CCA_WID_VERSION');
	if (empty($last_script_ver)):
	  update_option('CCA_WID_VERSION', $plugin_info['Version']);
  else:
	   $new_ver = $plugin_info['Version'];
	   $version_status = version_compare( $new_ver , $last_script_ver );
     // can test if script is later {1}, or earlier {-1} than the previous installed e.g. if ($version_status > 0 &&  version_compare( "0.6.3" , $last_script_ver )  > 0) :
	   // do any upgrade action, then:
		 if ($version_status != 0): 
 		   if (version_compare( "0.8.4" , $last_script_ver )  >  0):
         if (class_exists('CCAmaxmindSave')):
				    $do_max = new CCAmaxmindSave();
						$do_max->save_maxmind(TRUE);
	 					unset($do_max);
						$ccax_options = get_option('ccax_options'); 
						if ($ccax_options):
						  $ccax_options['init_geoip']= TRUE;
							update_option('ccax_options',$ccax_options);
						endif;
				 endif;
				 // delete old unused option for maxmind
				 delete_option( 'ccax_maxmind_status' );
      endif;		 
		  update_option('CCA_WID_VERSION', $plugin_info['Version']);
    endif;
  endif;
}

register_activation_hook( CCA_INIT_FILE, 'CCA_main_activate' );
register_deactivation_hook(CCA_INIT_FILE, 'CCA_main_deactivate' );


function CCA_main_deactivate() {
  wp_clear_scheduled_hook( CCA_X_MAX_CRON );
}

function CCA_main_activate() {
  $ccax_options = get_option( 'ccax_options' );
  if (  (! $ccax_options || $ccax_options['update_maxmind']) && ! wp_next_scheduled( CCA_X_MAX_CRON ))	: // if option not set then first time install - set to update by default
     wp_schedule_event( time()+10, 'cca_3weekly', CCA_X_MAX_CRON );
	endif;
}


// Add settings link on Dashboard->plugins page
add_filter( 'plugin_action_links_' . plugin_basename( CCA_INIT_FILE ), 'cca_add_sitesettings_link' );
function cca_add_sitesettings_link( $links ) {
	return array_merge(
		array('settings' => '<a href="' . admin_url(CCA_X_ADMIN_URL) . '">Sitewide Settings</a>'),
		$links
	);
}


$cca_ISOcode = '';  //global variable holding visitors country code - saves repeated geoip look-ups

if( is_admin() ):
  define('CCA_X_SETTINGS_SLUG', 'cca-sitewide-settings');
  define('CCA_X_ADMIN_URL','options-general.php?page=' . CCA_X_SETTINGS_SLUG);
  define('CCA_SUPPORT_SITE','wptest.means.us.com');  // need to recreate this on extensions if not defined in case main plugin is removed before extension
	include_once(CCA_WID_PLUGIN_DIR . 'inc/sitewide_settings_form.php');
  include_once(CCA_WID_PLUGIN_DIR . 'inc/admin_only.php');
  include_once(CCA_WID_PLUGIN_DIR . 'inc/cca_rss_settings.php');
endif;


// **************************************************
// DISPLAY AD WITHIN POST CONTENT TO THE VISITOR
// **************************************************

// if CCA responsive option is set then include the necessary js with the page
add_action('wp_head', 'cca_responsive_js');
function cca_responsive_js() {
	if ( is_admin() ) return;
  $ccax_options = get_option( 'ccax_options');
  if ( $ccax_options  && $ccax_options['responsive_function'] && ctype_digit($ccax_options['responsive_px']) ) :

// maybe also add php mobile detect script http://detectmobilebrowsers.com/  - and add as ticj option - server version won't work with cache

?>
<script>
  window.addEventListener("resize", ccax_width_check);
	function ccax_width_check() {
    var e = window, a = 'inner';
    if (!('innerWidth' in window )) {a = 'client';e = document.documentElement || document.body;}
    if (e[ a+'Width' ] > <?php echo $ccax_options['responsive_px'] ?> ) {
	    jQuery('.cca-smaldev-only').css("display", "none");
		  jQuery('.cca-largedev-only').css("display", "");
	  } else {
	    jQuery('.cca-smaldev-only').css("display", "");
		  jQuery('.cca-largedev-only').css("display", "none");
	  }
  }
  jQuery(document).ready(function(){ccax_width_check();})
</script><?php
	endif;
}


// insert the advert/additional content at the top of the Post (below the post title)
add_filter( 'the_content', 'cca_insert_content_top',20 );
function cca_insert_content_top( $content ) {
  if (  ! is_single() || is_admin() ) return $content; //only on posts
  $before_content = ccax_prepare_additional_content( 'Top' );
  $before_content = wpautop( $before_content );
  return $before_content . $content;
}


// identify which Ads in Posts widget entry to display, get it, and format it
function ccax_prepare_additional_content( $widtype ) {

  $content_widgets = get_option( 'ccax_post_widgets');
  if ( !$content_widgets || empty($content_widgets[$widtype]) ) return '';
	if ( !empty($content_widgets[$widtype]['preview_mode']) && ! current_user_can( 'manage_options' ) )return '';

  $ccax_options = get_option( 'ccax_options' );
// 0.9.0
	$disable_geoip = empty($ccax_options['disable_geoip']) ? FALSE:TRUE;

  if ($disable_geoip) $ISOcode = '-anywhere-';
  if (empty($ISOcode)) $ISOcode = CCAgeoip::do_lookup('ccode');
  if (empty($ISOcode)) $ISOcode = '-anywhere-';

  $the_entry = ccax_get_additional_content($content_widgets[$widtype]['entry'],$ISOcode);
  if ( empty($the_entry['content']) ) return '';
  $additional_content = $the_entry['content'];

	if (apply_filters('cca_text_allow_php', FALSE)) :
	  ob_start();
    eval('?>' . $additional_content);  // do php
    $additional_content = ob_get_contents();
    ob_end_clean();
	endif;
	$additional_content = apply_filters('cca_text_process_content', $additional_content);  // execute shortcodes in post widget content

  if ( ! empty($the_entry['make_responsive']) ) :
    $prefix = '<div class="cca-smaldev-only">';
  	$additional_content = $prefix . $additional_content . '</div>';
  endif;

	return $additional_content;
}
  

//  get the relevant content from the "ad in post" entry
function ccax_get_additional_content($additional_content, $ISOcode) {
	$category_id = apply_filters('cca_override_category', '');
	$entry = $category_id . '_' . $ISOcode;
  if ( $category_id != '' && ! empty($additional_content[$entry]['content']) ):
    return $additional_content[$entry];
	endif;
  $entry = $category_id . '_-anywhere-';
  if ( $category_id != '' && ! empty($additional_content[$entry]['content']) ):
	  return $additional_content[$entry];
	endif;
	$categories = get_the_category();
  if($categories) :
      foreach($categories as $category) :
        $category_id = $category->term_id;
			  $entry = $category_id . '_' . $ISOcode;
        if ( $category_id != '' && ! empty($additional_content[$entry]['content']) ):
				  return $additional_content[$entry];
      	endif;
     	endforeach;
      foreach($categories as $category) :
        $category_id = $category->term_id;
			  $entry = $category_id . '_-anywhere-';
        if ( $category_id != '' && ! empty($additional_content[$entry]['content']) ):
				  return $additional_content[$entry];
      	endif;
      endforeach;
  endif;
	$entry = '0_' . $ISOcode;
// 0.9.0
	if (! empty($additional_content[$entry]['content']) ):
	  return $additional_content[$entry];
	endif;
// 0.9.0 end
  if (! empty($additional_content['0_-anywhere-']['content']) ):
	  return $additional_content['0_-anywhere-'];
	endif;

  return array();
}


// **************************************************
// REGISTER THE STANDARD "SIDEBAR" CCA WIDGET 
// **************************************************
function AW_CCA_widInit() { register_widget('CCAtextWidget'); }
add_action('widgets_init', 'AW_CCA_widInit');



// **************************************************
// GeoIP CLASS
// **************************************************

if ( ! class_exists( 'CCAgeoip' ) ) : 
  class CCAgeoip {

    protected static $countriesToISO = array(
      'AF' => 'Afghanistan','AL' => 'Albania','DZ' => 'Algeria','AO' => 'Angola','AR' => 'Argentina','AM' => 'Armenia',
      'AU' => 'Australia','AT' => 'Austria','AZ' => 'Azerbaijan','BH' => 'Bahrain','BD' => 'Bangladesh','BY' => 'Belarus',
      'BE' => 'Belgium','BJ' => 'Benin','BO' => 'Bolivia','BA' => 'Bosnia &amp; Herzegovina','BW' => 'Botswana','BR' => 'Brazil',
      'BG' => 'Bulgaria','BF' => 'Burkina Faso','BI' => 'Burundi','KH' => 'Cambodia','CM' => 'Cameroon','CA' => 'Canada',
      'CF' => 'Central African Rep','TD' => 'Chad','CL' => 'Chile','CN' => 'China','CO' => 'Colombia',
      'CG' => 'Congo','CD' => 'Congo, Democ Rep','CR' => 'Costa Rica','CI' => 'Cote D\'Ivoire',
      'HR' => 'Croatia','CU' => 'Cuba','CY' => 'Cyprus','CZ' => 'Czech Republic','DK' => 'Denmark','DJ' => 'Djibouti',
      'DO' => 'Dominican Republic','EC' => 'Ecuador','EG' => 'Egypt','SV' => 'El Salvador','ER' => 'Eritrea','EE' => 'Estonia',
      'ET' => 'Ethiopia','FI' => 'Finland','FR' => 'France','GA' => 'Gabon','GM' => 'Gambia','GE' => 'Georgia','DE' => 'Germany',
      'GH' => 'Ghana','GR' => 'Greece','GL' => 'Greenland','GT' => 'Guatemala','GN' => 'Guinea','GW' => 'Guinea-Bissau','HT' => 'Haiti',
      'HN' => 'Honduras','HK' => 'Hong Kong','HU' => 'Hungary','IS' => 'Iceland','IN' => 'India','ID' => 'Indonesia','IR' => 'Iran',
      'IQ' => 'Iraq','IE' => 'Ireland','IL' => 'Israel','IT' => 'Italy','JM' => 'Jamaica','JP' => 'Japan','JO' => 'Jordan',
      'KZ' => 'Kazakhstan','KE' => 'Kenya','KR' => 'Korea, South','KW' => 'Kuwait','KG' => 'Kyrgyzstan','LA' => 'Lao','LV' => 'Latvia',
      'LB' => 'Lebanon','LS' => 'Lesotho','LR' => 'Liberia','LY' => 'Libya','LT' => 'Lithuania','MK' => 'Macedonia','MG' => 'Madagascar',
      'MW' => 'Malawi','MY' => 'Malaysia','MV' => 'Maldives','ML' => 'Mali','MX' => 'Mexico','MD' => 'Moldova','MN' => 'Mongolia','MA' => 'Morocco',
      'MZ' => 'Mozambique','NA' => 'Namibia','NP' => 'Nepal','NL' => 'Netherlands','NZ' => 'New Zealand','NI' => 'Nicaragua','NE' => 'Niger',
      'NG' => 'Nigeria','NO' => 'Norway','PK' => 'Pakistan','PS' => 'Palestine','PA' => 'Panama','PG' => 'Papua New Guinea','PY' => 'Paraguay',
      'PE' => 'Peru','PH' => 'Philippines','PL' => 'Poland','PT' => 'Portugal','PR' => 'Puerto Rico','QA' => 'Qatar','RO' => 'Romania',
      'RU' => 'Russia','RW' => 'Rwanda','SA' => 'Saudi Arabia','SN' => 'Senegal','RS' => 'Serbia','SL' => 'Sierra Leone','SG' => 'Singapore',
      'SK' => 'Slovakia','SI' => 'Slovenia','SO' => 'Somalia','ZA' => 'South Africa','ES' => 'Spain','LK' => 'Sri Lanka','SD' => 'Sudan',
      'SZ' => 'Swaziland','SE' => 'Sweden','CH' => 'Switzerland','SY' => 'Syria','TW' => 'Taiwan','TJ' => 'Tajikistan','TZ' => 'Tanzania',
      'TH' => 'Thailand','TL' => 'Timor-Leste','TG' => 'Togo','TT' => 'Trinidad And Tobago','TN' => 'Tunisia','TR' => 'Turkey','TM' => 'Turkmenistan',
      'UG' => 'Uganda','UA' => 'Ukraine','AE' => 'United Arab Emirates','GB' => 'United Kingdom','US' => 'United States','UY' => 'Uruguay',
      'UZ' => 'Uzbekistan','VE' => 'Venezuela','VN' => 'Vietnam','YE' => 'Yemen','ZM' => 'Zambia','ZW' => 'Zimbabwe'
    );

 
 		protected static $theEU = "BE,BG,CZ,DK,DE,EE,IE,GR,ES,FR,HR,IT,CY,LV,LT,LU,HU,MT,NL,AT,PL,PT,RO,SI,SK,FI,SE,GB";
 
    static function get_country_array() {
       return self::$countriesToISO;
    }

		static function get_EU_ISOs() {
       return self::$theEU;
    }


  // **************************************************
  //IDENTIFY VISITORS COUNTRY
  // **************************************************
    static function get_visitor_country_code($case='upper') {
      $ISOcode = self::do_lookup('ccode');
			if ( is_array($case) ):  // it's a shortcode request
			  $case = reset($case);  // gives value of first element
			endif;
			if ( strtolower($case) == 'lower' ) $ISOcode = strtolower($ISOcode);
			return $ISOcode;
    }

    static function get_visitor_country_name() {
		  global $cca_ISOcode;
			if (empty($cca_ISOcode)) $cca_ISOcode = self::do_lookup('ccode');
			if (empty($cca_ISOcode)) return '';
		  $cca_ISOcode = strtoupper($cca_ISOcode);
		  if ( ctype_alpha($cca_ISOcode) && array_key_exists($cca_ISOcode, self::$countriesToISO) ) return self::$countriesToISO[$cca_ISOcode];
			return '';	
    }

		static function do_lookup($type) {
		  // if ($type == 'ccode') // redundant conditional, but may be needed if function extended to do other types of look-up
		  global $cca_ISOcode;
				
			if (!empty($cca_ISOcode)) return $cca_ISOcode; // only look-up once
      if( current_user_can( 'manage_options' )) $cca_ISOcode = apply_filters('cca_use_admin_country_code', $cca_ISOcode);  
      if ($cca_ISOcode != '') return $cca_ISOcode;
      $cca_ISOcode = apply_filters('cca_country_code_from_other_geoip', ''); // if used filter must return 2 char ISO code for visitors IP, or '' if ip lookup unsuccesful
			if  ( ctype_alpha($cca_ISOcode) && strlen($cca_ISOcode) == 2 ) return strtoupper($cca_ISOcode);
      // from Cloudflare
      if ( ! empty($_SERVER["HTTP_CF_IPCOUNTRY"])) $cca_ISOcode = $_SERVER["HTTP_CF_IPCOUNTRY"];  //Cloudflare GEOIP is enabled
      if ($cca_ISOcode != 'XX' && ctype_alpha($cca_ISOcode) && strlen($cca_ISOcode) == 2 ) return strtoupper($cca_ISOcode);  // otherwise IP location not recognised - try Maxmind below

      // country details not found by Cloudflare or other Geoip so use Maxmind			
      $visitorIP = $_SERVER['REMOTE_ADDR'];
      if( filter_var($visitorIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) {  //then server is IPV4
        $geoIPdb = 'GeoIP.dat';
      } elseif ( filter_var($visitorIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) {$geoIPdb = 'GeoIPv6.dat';}
    	else return '';


  if ( ! file_exists(CCA_MAXMIND_DIR . 'cc_geoip.inc') || ! file_exists(CCA_MAXMIND_DATA_DIR . $geoIPdb) ) : return ''; endif;
  include_once(CCA_MAXMIND_DIR. 'cc_geoip.inc');
	$gi = \cc_max\geoip_open(CCA_MAXMIND_DATA_DIR . $geoIPdb, GEOIP_STANDARD);
  if($geoIPdb == 'GeoIP.dat') :
	   $cca_ISOcode = \cc_max\geoip_country_code_by_addr($gi, $visitorIP);
  else:
	   $cca_ISOcode = \cc_max\geoip_country_code_by_addr_v6($gi, $visitorIP);
  endif;
  \cc_max\geoip_close($gi);

      if (ctype_alpha($cca_ISOcode)) return strtoupper($cca_ISOcode);
			return '';

    }  // end do_lookup()


		// FUNCTIONS FOR SHORTCODES 

		static function convert_unit($attributes) {
			if (empty($attributes)) return;
		  $ISOcode = self::do_lookup('ccode');
			$attributes = array_change_key_case($attributes, CASE_LOWER);
      $number = reset($attributes);  // gives value of first element
      $unit = key($attributes);  // after reset this returns first key in array
			switch ($unit) :
			  case 'f':
    			if (  ! in_array($ISOcode, array('US','BS','BZ','KY'))  ) :
    			  return (string) round(($number - 32) * 5/9) . '&deg;C';
    			endif;
					return $number . '&deg;F';
				break;
        case 'c':
    			if ( in_array($ISOcode, array('US','BS','BZ','KY'))  ) :
    			  return (string) round($number*9/5+32) . '&deg;F';
    			endif;
    			return $number . '&deg;C';
				default:
				  return apply_filters('cca_convert_' . $unit, '', $ISOcode, $number);
			endswitch;
			return '';
		}

		static function show_content($attributes,$content='') {
			if (empty($attributes) || $content=='') return $content;
		  $ISOcode = self::do_lookup('ccode');
			$attributes = array_change_key_case($attributes, CASE_LOWER);
			$att_0 = str_replace(' ', '',reset($attributes)); // reset gives value of first element
      $selection = strtolower(key($attributes));  // after reset this returns first key in array			
      $ccodes = explode(",",strtoupper($att_0));
			if ( empty($ccodes) || empty($selection) ) return do_shortcode( $content );
			if ($selection == 'not') :  // e.g. for request [cca_display not=us,gb]
			  if ( in_array($ISOcode, $ccodes)  ) : 
				  return '';
				endif;
				return do_shortcode( $content );
//  0.9.0
		  elseif ($selection == 'only'):
		     if ( ! in_array($ISOcode, $ccodes)  ) : return ''; endif;
	  	   return do_shortcode( $content );
		  endif;
//			$content = apply_filters('cca_display_' . $selection, $content, $ccodes, $ISOcode);	// for extension options
			return $content;
		}

    function get_name_for_isocode($code) {
		  $code = strtoupper($code);
		  if ( ctype_alpha($code) && array_key_exists($code, self::$countriesToISO) ) return self::$countriesToISO[$code];
			return '';	
    }

  }  // end CCAgeoip class
endif; // end class exist check


add_filter('cca_is_EU', 'cca_is_from_EU',10,2);
function cca_is_from_EU($override_list, $EU_list="") {
   $cca_ISOcode =  CCAgeoip::do_lookup('ccode');
	 if ($EU_list == '' || $override_list === FALSE):
		   $EU_list = CCAgeoip::get_EU_ISOs();
   endif;
 	 if ( stristr($EU_list, $cca_ISOcode) === FALSE) return FALSE;
	 return TRUE;
}

add_filter('cca_geoip_lookup', array('CCAgeoip','do_lookup'));
add_shortcode( 'cca_countrycode', array( 'CCAgeoip', 'get_visitor_country_code' ) );
add_shortcode( 'cca_countryname', array( 'CCAgeoip', 'get_visitor_country_name' ) );
add_shortcode( 'cca_convert', array( 'CCAgeoip', 'convert_unit' ) );
add_shortcode( 'cca_display', array( 'CCAgeoip', 'show_content' ) );
add_shortcode( 'cca_iso_to_county', array( 'CCAgeoip','get_name_for_isocode' ) );


//  geo enable cookie_notice plugin
// Display notice on home page only
// function cca_cookie_notice_eu_only( $output ) {
function cca_cookie_notice_EUonly($output) {
  $ccax_options = get_option( 'ccax_options' );
  if (  ! $ccax_options || empty($ccax_options['only_EU_cookie'])  || empty($ccax_options['EU_ccodes']) ):
	  return $output;
	endif;
  $cca_ISOcode =  CCAgeoip::do_lookup('ccode');
  $EU_list = $ccax_options['EU_ccodes'];
   if ( stristr($EU_list, $cca_ISOcode) === FALSE) :
     return '';   
   endif;
   return $output;
}
add_filter( 'cn_cookie_notice_output', 'cca_cookie_notice_EUonly',99);


// **************************************************
// CCA WIDGET
// **************************************************
class CCAtextWidget extends WP_Widget {

  protected static $default_entry_array = array('name'=>'"All" / Default', 'country' => '-anywhere-', 'term_id' => '0', 'parent' => '0', 'content'=>'', 'content_type'=> 'text');
  protected static $default_cca_widget_settings_array = array('title_type'=>'default', 'title'=>'','action'=>'new','selected_category'=>'0','selected_country' =>'-anywhere-', /* 'selected_entry'=>'cat_0_-anywhere-', */
		  'above_widget'=>'', 'above_widget_unit'=>'', 'below_widget'=>'','below_widget_unit'=>'','pad_title'=>'','pad_title_unit'=>'','preview_mode'=>FALSE,
			'status_msg'=>'','current_panel'=>'contentTab');
	
	protected $diagnostics;
  protected $ccax_options;
  protected static $default_ccax_options = array( 
    'disable_geoip' => false, 'display_function' => false, 'responsive_function' => false, 'responsive_px' => '', 'cca_maxmind_dir'=> CCA_MAXMIND_DIR, 'update_maxmind'=>FALSE,
  	'rss_function' => false,   'current_action' => '', 'widtype' => '', 'selected_widget_entry' => '0_-anywhere-', 'init_geoip'=>FALSE
  );
  public static function set_ccax_defaults() {
    if ( get_option( 'ccax_options' ) ) :
      $ccax_options = wp_parse_args(get_option( 'ccax_options' ), self::$default_ccax_options );
    else :
  	  $ccax_options = self::$default_ccax_options;
    endif;
    $ccax_options = apply_filters('ccax_option_defaults', $ccax_options );  // let extension defaults take precedence
    update_option( 'ccax_options', $ccax_options );
    return $ccax_options;
  }


  // **************************************************
  // WIDGET CONSTRUCTOR
  // **************************************************
//  function CCAtextWidget() {
  function __construct() {
     $widget_ops = array('classname' => 'cca_textwidget', 'description' => __( 'Sidebar content by category and visitor locale (GeoIP)'), 'aw_ccawidget' );
		 $this->classname = $widget_ops['classname'];  //css class
     $control_ops = array('width' => 400, 'height' => 650,  'id_base' => 'ccatextwid');
//     $this->WP_Widget('ccatextwid', 'CCA WIDGET', $widget_ops, $control_ops);   // text on dashboard widget page
		 parent::__construct('ccatextwid', 'CCA WIDGET', $widget_ops, $control_ops);   // text on dashboard widget page
		 $this->default_cca_widget_settings = apply_filters('cca_default_form_settings', self::$default_cca_widget_settings_array);
		 $this->default_entry = apply_filters('cca_default_entry', self::$default_entry_array);
		 $this->default_cca_widget_settings['cat_0_-anywhere-'] = $this->default_entry; 
$this->diagnostics = FALSE;
#		 $this->diagnostics = apply_filters('cca_widget_diagnostics',FALSE);
     $this->ccax_options = self::set_ccax_defaults();
	}


  // **************************************************
  // DISPLAY WIDGET CONTENT
  // **************************************************
	function widget($args, $instance) {

    if(! current_user_can( 'manage_options' )) :  // don't display widget to non admin users if in preview mode
		  if (apply_filters('cca_preview_mode',$instance['preview_mode'])) : return; endif;
		  ini_set('display_errors',0);
    elseif (! empty($this->ccax_options['diagnostics'])): 
		   $this->diagnostics = TRUE;
		   ini_set('display_errors',1);  
			echo '<p>' . __('PHP Warning and Diagnostic mode ONLY VISIBLE TO ADMIN USER. If you are using a caching plugin or service, make sure it does not cache the admin user', 'aw_ccawidget')  . ':<br /><br />' . __('Searching for relevant category/country entry....', 'aw_ccawidget') . '</p>';
		endif;

		// get the ISO country code for the visitor's location
    $ISOcode = '';

// 0.9.0
		$disable_geoip = empty($this->ccax_options['disable_geoip']) ? FALSE:TRUE;		

		if ($disable_geoip) $ISOcode = '-anywhere-';
		if ($ISOcode == '') $ISOcode = CCAgeoip::do_lookup('ccode');	 // or apply_filters('cca_geoip_lookup','ccode'); OR do_shortcode('[cca_countrycode]);
		if ($ISOcode == '') $ISOcode = '-anywhere-';

    // (pages do not have categories, posts can have multiple categories)
    // hook to allow association of specific pages with categories or to override/prioritise a post's category
    $special_cat = apply_filters('cca_override_category', 'cca_standard');
    if ( $special_cat != 'cca_standard' && $this->widget_content_found_and_echoed($special_cat, $ISOcode, $args, $instance) ) return;
    if ($special_cat != 'cca_standard' && $ISOcode != '-anywhere-' &&  $this->widget_content_found_and_echoed($special_cat, '-anywhere-', $args, $instance) ) return;

		if (! is_front_page() ) :  // front page does not have a category
  		$categories = get_the_category();
      if($categories) :
        foreach($categories as $category) :
         $category_id = $category->term_id;
          if ( $this->widget_content_found_and_echoed($category_id, $ISOcode, $args, $instance) ) return;
       	endforeach;
        foreach($categories as $category) :
         $category_id = $category->term_id;
          if ( $ISOcode != '-anywhere-' && $this->widget_content_found_and_echoed($category_id, '-anywhere-' , $args, $instance) ) return;
        endforeach;
      endif;
		endif;

		// no specific content found for this page/posts category - so use default content 
    if ( $this->widget_content_found_and_echoed('0', $ISOcode, $args, $instance) ) return;
    if ( $ISOcode != '-anywhere-' &&  $this->widget_content_found_and_echoed('0', '-anywhere-', $args, $instance) ) return;
		$this->widget_content_found_and_echoed('no recognised content type', '', $args, $instance);
    return; // widget not rendered - there is no default content to display
	}

// ****************************************************************************************************
// FIND & ECHO CONTENT ENTRY FOR CATEGORY & VISITOR LOCALE called by function "widget"
// ****************************************************************************************************

  function widget_content_found_and_echoed($category_id, $ISOcode, $args, $instance) {  // won't display without $args
		extract( $instance ); extract( $args,EXTR_SKIP );
    if ($category_id != 'no recognised content type') {
    	// check if there is a content entry for this page's category and visitor locale
      $entry_key = 'cat_' . $category_id . '_' . $ISOcode;
			if ( $this->diagnostics ) echo '<span class="cca-brown">' . esc_html($entry_key) . '</span><br />';
      if ( empty($instance[$entry_key]) ) return FALSE; // no entry for this category/country
      $entry=$instance[$entry_key];
			if ($this->diagnostics) cca_echo_settings($entry, $instance);
      $content_type = $entry['content_type'];
 
 // 0.9.0
 			if  ($content_type == 'none')  return TRUE;  // this content_type e.g. 'none' should not be displayed
	
			if ( $content_type != 'text' && ! has_filter('cca_' . $content_type . '_process_content') ) return FALSE; // the extension for this type of content has been disabled
      $content = $entry['content'];
      if ( $content_type == 'text' && empty($content) ) { return FALSE; }
    } else {  // cater for a deactivated extension
		  if (empty($instance['cat_0_-anywhere-'])) return FALSE;  // do not display widget there must always be a default entry
     	$content_type = 'text'; 
     	$entry=$instance['cat_0_-anywhere-'];
    }

// Build and Render

    // prepare widget Title
		$title =  str_replace ( "[br]" , "<br/>" , apply_filters('widget_title', str_ireplace(array("<br>","<br/>","<br />"), "[br]",$title))); /* allow <br> linebreaks */
		$title =  apply_filters('cca_modify_title', $title, $entry);

		// prepare widget Content
		if ($content_type == 'text') :
			if (apply_filters('cca_text_allow_php', FALSE)) :
			  ob_start();
        eval('?>' . $content);  // do php
        $content = ob_get_contents();
        ob_end_clean();
			endif;
		  $content = apply_filters('cca_text_process_content', $content, $entry);
		  $content = apply_filters( 'widget_text', $content );
			$content = !empty( $entry['filter'] ) ? wpautop( $content ) : $content;

		else:  // use extension to prepare content output
			$entry_settings = $entry[$content_type . '_settings'];
			$entry_settings['modified_title'] = $title;
		  $extension_output = apply_filters('cca_' . $content_type . '_process_content', $entry_settings);
			if (empty($extension_output) || empty($extension_output['content']) ) return FALSE;
			$content = $extension_output['content'];
			$title = empty($extension_output['title']) ? '' : $extension_output['title'];
		endif;

	//add customizations and widget styles 
		
		// build styles from settings
    $widget_style = ''; $widget_img_style = '';
		if ($this->ccax_options['display_function']):
  		if ($content_type == 'text') :  // && $this->ccax_options['display_function']
        if ( ! empty($no_border) ) : $widget_style = 'border:none !important;'; endif;
        if ( ! empty($no_margin) ) : $widget_style .= 'margin-left:0 !important;margin-right:0 !important;'; endif;
        if ( ! empty($no_padding) ) : $widget_style .= 'padding-left:0 !important;padding-right:0 !important;'; endif;
        if ( ! empty($no_background)) : $widget_style .= 'background:transparent !important;'; endif;
  			if ( ! empty($full_width_img)) : $widget_img_style = ' #' . $this->id  . ' img{width: auto !important; width: 100%;max-width: 100%;min-width: 100%}'; endif;
  		endif;

#      if ($above_widget == '999') $above_widget = '';  // 999 is diagnostics easter egg - no good for display of widget
      if (is_numeric($above_widget) && $above_widget < 0 && ctype_alpha($above_widget_unit) ) :
  		  $widget_style .=   'margin-top:' . $above_widget . $above_widget_unit .' !important;';
  		endif;
			if ( is_numeric($pad_title) && $pad_title > 0 && ctype_alpha($pad_title_unit) ) :
  		  $before_title =  '<div style="margin:0px ' . $pad_title . $pad_title_unit . ' !important;">' . $before_title;
  		  $after_title .= '</div>';   
  		endif;
		endif;
		// output style
    if (! has_filter('cca_' . $content_type . '_custom_styles')) {
       if ($widget_style != '' || $widget_img_style !='') echo '<style scoped>#' . $this->id . '{'. $widget_style . '}' . $widget_img_style . '</style>';
    } else echo apply_filters('cca_' . $content_type . '_custom_styles',$widget_style, $this->id);

	// render widget
    if ( $this->ccax_options['display_function'] && $above_widget > 0 && ctype_alpha($above_widget_unit) ) echo '<div style="height:' . $above_widget . $above_widget_unit .' !important">&nbsp;</div>';
		if ( ! empty($instance['ccawid_responsive']) ) $before_widget = str_ireplace('class="', 'class="cca-largedev-only ', $before_widget);
		echo $before_widget;
		if ( !empty( $title ) ) { echo $before_title . $title . $after_title; }
		echo $content;
    echo $after_widget;
    if ( $this->ccax_options['display_function'] && isset($below_widget) && is_numeric($below_widget) && ctype_alpha($below_widget_unit) ) echo '<div style="height:' . $below_widget . $below_widget_unit .'">&nbsp;</div>';

		return TRUE;
  }  // end function widget_content_found_and_echoed


  // ***************************************************************************
  // SAVE WIDGET SETTINGS
  // ***************************************************************************

	function update($new_instance, $old_instance) {

		$instance = wp_parse_args( $old_instance, $this->default_cca_widget_settings );  // initially populate with existing values & defaults for missing values

		// vars for setting form control/info; some may be 'transient'
		$instance['current_panel'] = esc_attr($new_instance['current_panel']);
    $instance['status_msg'] = ''; // reset result message to display on form
		$instance['msg_type'] = 'ok';

		// APPLICABLE TO ALL ENTRIES - ALWAYS UPDATE THESE FROM SETTING'S FORM VALUES
		$instance['title'] = wp_kses($new_instance['title'], array('br'=>array() ), array() );
		// extension management
		$instance['extension_version'] = apply_filters('cca_extension_version','');  // maybe make this an array of extension name/version

    $instance = apply_filters('cca_add_settings_variables', $instance, $new_instance); // extension may add/change/unset element "variables"

    // ************************************************
    // HANDLE REQUESTED ACTION
    // ************************************************

	 	if ($new_instance['action'] == 'display') :  // text widget display + widget config settings
 			$instance['action'] = 'display';

 			$instance['preview_mode'] = isset($new_instance['preview_mode']);
  	 	if ($this->ccax_options['display_function']) :  // only update if display options can be changed via display tabs panel
    		$instance['no_border'] = isset($new_instance['no_border']);
    		$instance['no_margin'] = isset($new_instance['no_margin']);
    		$instance['no_padding'] = isset($new_instance['no_padding']);
    		$instance['no_background'] = isset($new_instance['no_background']);
  			$instance['full_width_img'] = isset($new_instance['full_width_img']);
  			$instance['above_widget'] = (empty($new_instance['above_widget']) || ! is_numeric($new_instance['above_widget']) ) ? '' : $new_instance['above_widget'];
  			$instance['above_widget_unit'] = ($new_instance['above_widget_unit'] == 'em') ? 'em' : 'px';
        $instance['below_widget'] = strip_tags($new_instance['below_widget']);
  			$instance['below_widget_unit'] = ($new_instance['below_widget_unit'] == 'em') ? 'em' : 'px';
        $instance['pad_title'] = strip_tags($new_instance['pad_title']);
  			$instance['pad_title_unit'] = ($new_instance['pad_title_unit'] == 'em') ? 'em' : 'px';
			endif;

			if ($this->ccax_options['responsive_function']) :
				$instance['ccawid_responsive'] = isset($new_instance['ccawid_responsive']);
			endif;

			$instance['status_msg'] = __('Display settings have been updated', 'aw_ccawidget');
			return $instance;
		endif;


		// DELETE request: remove entry and return
    if ($new_instance['action'] == 'delete') :
			if ($new_instance['selected_entry'] == 'cat_0_-anywhere-') :
// 0.9.0
				 $instance['status_msg'] = __('Widget entry for the <i>default</i> entry "all/anywhere" has been set to widget type "None".');
				 $instance['msg_type'] = 'warn';
				 $instance['cat_0_-anywhere-'] = $this->default_entry;
//0.9.0
				 $instance['cat_0_-anywhere-']['content_type'] = 'none';

	 			 $instance['current_panel'] = 'contentTab';
      else :
				 $instance['status_msg'] = __('Entry for ', 'aw_ccawidget') . $instance[$new_instance['selected_entry']]['name'] . ', ' . $instance[$new_instance['selected_entry']]['country'] . __(' deleted', 'aw_ccawidget');
         unset($instance[$new_instance['selected_entry']]);
			endif;
			$instance['action'] = 'new';
      $instance['selected_category'] = '0';
      $instance['selected_country'] = '-anywhere-';
      return $instance;
    endif;

		// EDIT request: provide data to populate form and return
    if ($new_instance['action'] == 'edit') :
		  $the_entry = $instance[$new_instance['selected_entry']];
  	  $try_again = FALSE;

      if ($disable_geoip && $the_entry['country'] != '-anywhere-') :
     	  $instance['status_msg'] = __('You cannot use or EDIT entries for specific country whilst GeoIP is disabled. If you are permanently disabling GeoIP it is probably best to DELETE this entry', 'aw_ccawidget');
				$instance['msg_type'] = 'warn';
				$the_entry = $instance['cat_0_-anywhere-'];
				$try_again = TRUE;
      endif;
      if ( $the_entry['term_id'] != '0' &&  $the_entry['term_id'] != '-1') :
        $cat_still_exists = term_exists((int)$the_entry['term_id'], 'category');
        if (empty($cat_still_exists) || $cat_still_exists == 0) :
				// category associated with entry no longer exists so user will have to select another
        	  $instance['status_msg'] .= __('Category ', 'aw_ccawidget') . $the_entry['term_id'] . ' ' .$the_entry['name'] . __(' no longer exists - you may wish to delete your entries for this category.', 'aw_ccawidget');
						$instance['msg_type'] = 'warn';
						$the_entry = $instance['cat_0_-anywhere-'];
						$try_again = TRUE;
        endif;
      endif;

      $instance['selected_category'] = $the_entry['term_id'];
      $instance['selected_country'] = $the_entry['country'];
			if (!$try_again) $instance['current_panel'] = 'contentTab';
			$instance['action'] = 'new';
			return $instance;
    endif;

		// NEW/REPLACE: save the new category/country entry
    $selected_country = $instance['selected_country'] = esc_attr($new_instance['selected_country']);
    $selected_category = $instance['selected_category'] = esc_attr($new_instance['selected_category']);
		// transients (used by settings form but not by function to display widget)
    $content_type = isset($new_instance['content_type']) ? esc_attr($new_instance['content_type']) : 'text';
		$instance['content_type'] = $content_type;
		$title_type = $instance['title_type'] = isset($new_instance['title_type']) ? esc_attr($new_instance['title_type']) : 'default';

		$entry_key = 'cat_' . $selected_category . '_' . $selected_country;

    $default_error = FALSE;
    if ( $entry_key != 'cat_0_-anywhere-') :
      if ( empty($instance['cat_0_-anywhere-']) || ($instance['cat_0_-anywhere-']['content_type'] == 'text' && empty($instance['cat_0_-anywhere-']['content']) ) ):
    	  $default_error = TRUE;
    	elseif ( $content_type == 'text' && empty($new_instance['new_content']) ):
    	  $default_error = TRUE;
    	endif;
    endif;
    if ($default_error):
// 0.9.0
      $instance['status_msg'] = __('You have NOT saved a default entry for category/country ("All"/"Any"). You MUST enter content for this entry, or set to "None", and "Save Entry" before creating any other category/country entries.');
      $instance['msg_type'] = 'warn';
      $instance['selected_category'] = '0';
      $instance['selected_country'] = '-anywhere-';
    	$instance['action'] = 'new';
      return $instance;
    endif;

    // initialise new_entry
		$new_entry = $this->default_entry;
    // build new_entry
		if ($selected_category == '-1') :
			$new_entry['name'] = __('"Home" Page', 'aw_ccawidget');
			$new_entry['term_id'] = '-1';
			$new_entry['parent'] ='0';
		elseif ($selected_category > '0') :
      $catInfo = get_category((int)$selected_category,'ARRAY_A' ); # (int)$selected_category
      $new_entry['name'] = $catInfo['name'];
      $new_entry['term_id'] = $selected_category;  // $catInfo['term_id'];
      $new_entry['parent'] = $catInfo['parent'];
		endif;

  	$new_entry['country'] = $selected_country;
		$new_entry['content_type'] = $content_type;
		$new_entry['title_type'] = $title_type;
		if ($title_type != 'default'): $new_entry = apply_filters('cca_title_entry', $new_entry, $new_instance); endif;
		$new_entry['content'] = empty($new_instance['new_content']) ? '' : $new_instance['new_content'];
//0.9.0
		if ( $content_type != 'text' && $content_type != 'none' && ! has_filter('cca_' . $content_type . '_save') ) :	

      $instance['status_msg'] .= __('Warning content type "', 'aw_ccawidget') . $content_type . __('" not recognised - treating as a text widget. ', 'aw_ccawidget');
			$instance['msg_type'] = 'warn';
    	$content_type = $new_entry['content_type'] = 'text';
    endif;
		if ($content_type == 'text') :
			$new_entry['filter'] = ! empty($new_instance['filter']);  // whether to apply text widget add paragraphs filter
		  if (! empty($new_entry['content']) ):  // || ! empty($new_instance['title']
			  if ( ! current_user_can('unfiltered_html') ) : $new_entry['content'] = stripslashes( wp_filter_post_kses( addslashes($new_entry['content']) ) ); endif;
				$instance[$entry_key] = $new_entry;
				$instance['status_msg'] .= __('Entry for "', 'aw_ccawidget') . $new_entry['name'] . '", "' . $new_entry['country'] . '" saved' . '.';
			else:
        $instance['status_msg'] .= __('!!! NOT SAVED: you have specified a <u>Text</u> Widget but did not enter any content for it. ', 'aw_ccawidget');
// 0.9.0
			  $instance['status_msg'] .= __('To hide widget for this category/country select widget type "None". If you were intending to remove this entry click the List Tab above and then use the delete tool. ', 'aw_ccawidget');
				$instance['msg_type'] = 'warn';
				$instance['content'] = '';
			endif;

// 0.9.0
		elseif ($content_type == 'none') :
			  	$instance[$entry_key] = $new_entry;
					$instance['status_msg'] .= __('Entry for "', 'aw_ccawidget') . $new_entry['name'] . '", "' . $new_entry['country'] . '" saved.';

		else:
			$extension_content_key = $content_type . '_settings';
		  if ( !empty($instance[$entry_key]) && !empty($instance[$entry_key][$extension_content_key])) :
			  $old_extension_content = $instance[$entry_key][$extension_content_key];
			 else: $old_extension_content = array();
			endif;
			$extension_content = apply_filters('cca_' . $content_type . '_save', $old_extension_content, $new_instance, $entry_key);
			if (!empty($extension_content)) :
			  if (empty($extension_content['error_msg'])):
				  $instance[$entry_key] = $new_entry;
					$instance[$entry_key][$extension_content_key] = $extension_content;
					$instance['status_msg'] .= __('Entry for "', 'aw_ccawidget') . $new_entry['name'] . '", "' . $new_entry['country'] . __('" saved', 'aw_ccawidget') . '.';
				else:
				  $instance['status_msg'] .= $extension_content['error_msg'];
					$instance['msg_type'] = 'warn';
					unset($extension_content['error_msg']);
				endif;
			endif;
    endif;

    if ($disable_geoip && $instance['selected_country'] != '-anywhere-') {
			$instance['status_msg'] .= __('You have disabled Country Detection. From now on, unless you re-enable, you are only able to change content for the default "Anywhere".', 'aw_ccawidget'); 
      $instance['selected_category'] = '0';
      $instance['selected_country'] = '-anywhere-';
			if ($instance['current_panel'] = 'contentTab') $instance['current_panel'] = 'listTab' ;
    }

 		$instance['action'] = 'new';
    return $instance;

  }  // end update function


  // **************************************************
  // WIDGET SETTINGS FORM
  // **************************************************

  function form($instance) {

    // used by id/name fields in action hook for this specific instance of widget
    $widget_number = $this->number;
    $fieldId_prefix = 'widget-ccatextwid-' . $widget_number . '-';
    $name_prefix = 'widget-ccatextwid[' . $widget_number . ']'; 

		$instance = wp_parse_args( (array) $instance, $this->default_cca_widget_settings );  // populate with existing values & defaults for missing values
	 	$action = strip_tags($instance['action']);
		$status_msg = wp_kses( $instance['status_msg'], array('a' => array( 'href' => array(),'title' => array() ), 'br'=>array() ), array('http','https') );

		$current_panel = strip_tags($instance['current_panel']);
    $selected_category = strip_tags($instance['selected_category']);
    $selected_country = strip_tags($instance['selected_country']);
// 0.9.0
	 $disable_geoip = empty($this->ccax_options['disable_geoip']) ? FALSE:TRUE;

		$above_widget = strip_tags($instance['above_widget']);
		$above_widget_unit = ($instance['above_widget_unit'] == 'em') ? 'em' : 'px';
		$below_widget = strip_tags($instance['below_widget']);
    $below_widget_unit = ($instance['below_widget_unit'] == 'em') ? 'em' : 'px';
		$pad_title = strip_tags($instance['pad_title']);
		$pad_title_unit = ($instance['pad_title_unit'] == 'em') ? 'em' : 'px';

    $instance = apply_filters('cca_alter_form_values', $instance);

    if ($GLOBALS['pagenow'] == 'customize.php' ) :
		  echo '<h3>The customizer is NOT Category Country Aware and won\'t edit or display correctly. Please use the standard Dashboard->Appearence->Widgets editor to add or edit your CCA widgets</h3>';
			echo '<h3><a href="http://wptest.means.us.com/2015/03/cca-goodies-extension/">A free add-on to the CCA plugin is available that enables you to preview CCA content</a> for any page(Category) as a visitor from any specified country.</h3>';
			return;
		endif;
		
		$title_type = strip_tags($instance['title_type']);
		if (!empty($this->ccax_options['diagnostics']) ): 
			echo '<p class="cca-brown">' . __('Diagnostic mode (set in CCA Site Settings) is ON; see bottom of this form for values set.', 'aw_ccawidget') . '</p>';
			echo '<p class="cca-brown">' . __('If you are using a caching plugin or service, make sure it is not caching dashboard pages/admin user', 'aw_ccawidget') . '.</p>';
		endif;
?>	
		<p><a href="<?php echo CCA_X_ADMIN_URL; ?>&tab=general#resp">CCA site-wide settings</a> &nbsp; <?php _e('Link for', 'aw_ccawidget'); ?>: 
		<a href="http://<?php echo CCA_SUPPORT_SITE ?>/2014/11/category-country-aware-wordpress/#tabs-1-2" target="_blank"><?php _e('User Guide', 'aw_ccawidget'); ?></a>
<?php

		$no_geoip = FALSE;


   if ( empty($_SERVER["HTTP_CF_IPCOUNTRY"])  && ! $disable_geoip && ! file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIP.dat') ) :
 		  if (empty($this->ccax_options['init_geoip']) ):
        $geoip_warn = __('You have not set your GeoIP options. Either check the option to "Initalize GeoIP" or the option to "Disable GeoIP" on the');
    	  $geoip_warn .= ' <a href="' . CCA_X_ADMIN_URL . '&tab=general#resp">CCA Site Settings form</a>.';
			else:
				$geoip_warn = __('The Maxmind country lookup files are missing - see the ');
				$geoip_warn .= '<a href="http://' . CCA_SUPPORT_SITE . '/2014/11/category-country-aware-wordpress/#tabs-1-2" target="_blank">' . _e('User Guide', 'aw_ccawidget') . '</a> ' . __('for the solution.');
			endif;
      if (! empty($instance['status_msg'])) : $geoip_warn .= '<br /><br />'; endif;
      $status_msg = $geoip_warn . $status_msg;
			$instance['msg_type'] = 'warn';
			$no_geoip = TRUE;
    endif;
    if (! empty($status_msg)) : 
      echo '<div class="cca-msg cca-msg-' . $instance['msg_type'] . '">' . $status_msg . '</div><br />';
    endif;
?>
	  <div class="cca-widget-settings-container">

  <!--  TAB MENU -->
      <div class="cca-widget-admin-tab">
        <ul>
          <li id="<?php echo $fieldId_prefix; ?>contentTab"
					  class="ccax-tabs cca-widget-admin-tab<?php if ($current_panel == 'contentTab' ) echo ' cca-tab-active'; 
						 echo '">' . __('Add Entry or', 'aw_ccawidget') . '<br>' . __('Replace Entry', 'aw_ccawidget'); ?></li>
          <li id="<?php echo $fieldId_prefix ?>listTab"
					  class="ccax-tabs cca-widget-admin-tab<?php if ($current_panel == 'listTab') echo ' cca-tab-active'; echo '">' . __('List / Edit', 'aw_ccawidget') . '<br>' . __('Existing Entry', 'aw_ccawidget'); ?></li>
<?php
		if ( $this->ccax_options['responsive_function'] || $this->ccax_options['display_function'] || has_filter('cca_show_displaytab') ) : ?>
          <li id="<?php echo $fieldId_prefix; ?>displayTab"
					  class="ccax-tabs cca-widget-admin-tab 
						<?php if ($current_panel == 'displayTab') echo ' cca-tab-active'; echo '"';
						  if(has_action('cca_tab_bar')) echo '>' . __('Display', 'aw_ccawidget');
						  else echo '>' . __('Widget', 'aw_ccawidget'); 
							echo '<br />' . __('Settings', 'aw_ccawidget'); ?>
					</li>
<?php
		endif;
		do_action('cca_tab_bar', $fieldId_prefix, $name_prefix, $instance);?>
        </ul>
      </div><!-- end cca-widget-tab-bar --><hr />

<?php	do_action('cca_tab_panel', $fieldId_prefix, $name_prefix, $instance);?>

  <!-- PART OF FORM FOR CREATING NEW CONTENT ENTRIES for a selected category and country -->
			<div id="<?php echo $fieldId_prefix; ?>contentTabPanel" class="cca-widget-panel-container<?php if ($current_panel == 'contentTab') echo ' cca-panel-active'; ?>">
        <div id="<?php echo $fieldId_prefix; ?>titlediv">
				 <p><?php _e('Widget Title (if required)', 'aw_ccawidget'); ?>:<br />
    		 <input class="widefat" id="<?php echo $fieldId_prefix; ?>title" name="<?php echo $name_prefix; ?>[title]"
				   type="text" value="<?php echo str_replace( "[br]" , "<br/>" , strip_tags(str_ireplace(array("<br>","<br/>","<br />"), "[br]",$instance['title']))); ?>" /></p><hr />
				</div>

			  <!-- Category Selector -->
    		<?php $dropdown_args = array('id' => $fieldId_prefix . 'selected_category', 'name' => $name_prefix . '[selected_category]', 'selected' => $selected_category, 'orderby'=>'NAME', 'hide_empty'=>FALSE, 'show_option_all' => '"all" / "other" (Default)');
				$dropdown_args = apply_filters('cca_category_dropdown', $dropdown_args);
    	  echo '<p class="cca-brown"><b>' . __('Widget Content for this', 'aw_ccawidget') . ':</b></p><p class="cca-txtleft"> ' . __('Category: ', 'aw_ccawidget'); wp_dropdown_categories($dropdown_args);
    		// country dropdown box	
    	  echo '<br />' . __('AND', 'aw_ccawidget') . '<br /><span title="Use CCA Site Settings to enable Country functionality (Dashboard->Settings->Category Country Goodies"> ' . __("Visitor's country", 'aw_ccawidget') . ': ';

    	  if ($disable_geoip || $no_geoip) :  ?>
    			  
						<select id="<?php echo $fieldId_prefix; ?>selected_country" disabled="disabled">
    				  <option value="-anywhere-" selected>"any" / "other" (Default)</option>
    				</select></span></p>  				
						<input type="hidden" name="<?php echo $name_prefix; ?>[selected_country]" value="-anywhere-" />
    		<?php else:	
      			echo '<select id="' . $fieldId_prefix .'selected_country" name="' . $name_prefix . '[selected_country]">';
            $aw_cca_country_array = array('-anywhere-' => '"any" / "other" (Default)') + apply_filters( 'cca_widget_country_list', CCAgeoip::get_country_array() );
						if (! array_key_exists($selected_country,$aw_cca_country_array)) $selected_country = '-anywhere-';
            foreach($aw_cca_country_array as $iso=>$country_name ) :
              $display_country = ' (' . $iso . ')';
            	if ($iso == '-anywhere-') $display_country = '';
            	echo '<option value="' . $iso . '" ' . selected( $selected_country, $iso, FALSE ) .  '>' . $country_name . $display_country . '</option>';
            endforeach;
            echo '</select></p>';
    	  endif;

        $entry_key = 'cat_' . $instance['selected_category'] . '_' . $instance['selected_country'];
    		$the_entry = empty($instance[$entry_key]) ? array() : $instance[$entry_key];
        do_action('cca_add_entry_options', $fieldId_prefix, $name_prefix, $instance, $entry_key);
    		$content_type='';
    		if (! empty($the_entry) ) $content_type = esc_textarea($the_entry['content_type']);

        if ( ! empty($the_entry) && ! empty($the_entry['content']) ) $contentValue = esc_textarea($the_entry['content']); 
        else $contentValue = '';

// 0.9.0
        $more_radio = '';
        $more_radio .= apply_filters( 'cca_add_widget_types', $more_radio, $fieldId_prefix, $name_prefix, $instance, $entry_key, $this->ccax_options);
?>
    		  <p><?php _e('Widget Type', 'aw_ccawidget'); ?>:
      		&nbsp; <input type="radio" id="<?php echo $fieldId_prefix;?>textwid" class="cca-radio_widtype"	name="<?php echo $name_prefix; ?>[content_type]" value="text"
      		<?php echo ($content_type=='' || $content_type=='text')?'checked':'' ?>><label for="<?php echo $fieldId_prefix;?>textwid"><?php _e('Text (normal)', 'aw_ccawidget'); ?></label> &nbsp;   
          <?php echo '<input type="radio" id="' . $fieldId_prefix . 'nowid" class="cca-radio_widtype" name="' . $name_prefix . '[content_type]"  value="none" ';
          echo ($content_type=='none')?'checked':'';
          echo '><label for="' . $fieldId_prefix . 'nowid">' . __('NONE (hide)', 'aw_ccawidget') . ' </label>';
		 			echo $more_radio;
          echo '</p>';
// 0.9.0 end 
    		do_action('cca_add_widget_type_panels', $fieldId_prefix, $name_prefix, $instance, $entry_key);
        echo '<div id="' . $fieldId_prefix .'textwid_div" class="cca-widget-entry-div';

				if ( empty($more_radio) || $content_type=='text') echo ' cca-widget-entry-div-active">';
				else echo '">';
  			echo '<p>' . __('Enter/Edit Custom Content', 'aw_ccawidget') . ' <textarea rows="10" cols="20" class="widefat cca-content" id="' . $fieldId_prefix .'new_content"';
  			echo ' name="' . $name_prefix . '[new_content]">' . $contentValue . '</textarea></p>';
?>    		<p><input id="<?php echo $fieldId_prefix; ?>filter" name="<?php echo $name_prefix; ?>[filter]" type="checkbox"
				  <?php checked( ! empty($the_entry['filter']),TRUE); ?> />&nbsp;<label for="<?php echo $fieldId_prefix; ?>filter"><?php _e('Automatically add paragraphs (<i>for plain text</i>)', 'aw_ccawidget'); ?></label></p>
			<hr />
<?php
        echo '<p><i>' . __('Remember to save your content before choosing a new category or country', 'aw_ccawidget') . '.</i></p>';
  		echo '</div><!-- end id entry_text -->';
?>
		</div><!-- end contentTabPanel -->

  <!-- PART OF FORM FOR List/Edit/Delete -->
		<div id="<?php echo $fieldId_prefix; ?>listTabPanel" class="cca-widget-panel-container<?php if ($current_panel == 'listTab') echo ' cca-panel-active'; ?>">
      <p class="cca-brown"><b><?php _e('Manage an existing entry', 'aw_ccawidget'); ?></b></p>
		  <p class="cca-brown"><i><?php _e('List / edit / delete custom content for Category/Country', 'aw_ccawidget'); ?></i></p>
<?php
			// identify and list all existing content entries 
			$cat_content_array = array();
      foreach( $instance as $key => $value) :
      	if (strpos($key, 'cat_') === 0) :
          $cat_content_array[$key]=$value;
      	endif;
      endforeach;
      //sort by category name + country
      asort($cat_content_array);
      echo '<p>Select a Category (Country):</p><p><select id="' . $fieldId_prefix .'selected_entry" name="' . $name_prefix . '[selected_entry]">';
      foreach( $cat_content_array as $contentEntry => $value ) :
        if ($contentEntry == 'cat-0_-anywhere-') : $setSelected = ' selected="selected"';
         else : $setSelected = '';
        endif;
        echo '<option value="' . $contentEntry . '"' . $setSelected .  '>' . $value['name'] . ' (' . $value['country'] . ')</option>';
      endforeach;
?>
      </select></p>
      <p>and click</p>
      <button type="button" id="<?php echo $fieldId_prefix; ?>editbutton" name="<?php echo $name_prefix; ?>[editbutton]"
        value="edit" class="cca-button button button-primary"><?php _e('Edit', 'aw_ccawidget'); ?></button>
      <button type="button" id="<?php echo $fieldId_prefix; ?>delbutton" name="<?php echo $name_prefix; ?>[delbutton]"
        value="Delete" class="cca-button button button-primary"><?php _e('Delete', 'aw_ccawidget'); ?></button>
      <div class="cca-add-panel-height">&nbsp;</div>
		</div><!-- end listTabPanel -->

  <!-- part of form for DISPLAY/WIDGET SETTINGS -->

		<div id="<?php echo $fieldId_prefix; ?>displayTabPanel" class="cca-widget-panel-container<?php if ($current_panel == 'displayTab') echo ' cca-panel-active'; ?>">

<?php
do_action('cca_display_panel_top', $fieldId_prefix, $name_prefix, $instance, $entry_key);

if ( $this->ccax_options['responsive_function'] ) :
  $responsive_px = empty($this->ccax_options['responsive_px']) ? 'this width setting' : $this->ccax_options['responsive_px'] . ' px'; ?>
	<p><input id="<?php echo $fieldId_prefix;?>ccawid_responsive" name="<?php echo $name_prefix;?>[ccawid_responsive]" type="checkbox" <?php checked(empty($instance['ccawid_responsive']) ? 0 : 1);?>/>
    <label for="<?php echo $fieldId_prefix;?>ccawid_responsive"><?php _e('"Responsive+"');?></label>
		<span class="cca-brown"><?php echo __('(hide if browser width < ') . '</span><a href="' . CCA_X_ADMIN_URL . '&tab=general#resp">' . $responsive_px;?></a><span class="cca-brown">)</span>
	</p><hr /><?php

endif;
if ( $this->ccax_options['display_function'] ) : 

    $above_widgetName = $name_prefix . '[above_widget]';
    $above_widget_unitName = $name_prefix . '[above_widget_unit]';
    $below_widgetName = $name_prefix . '[below_widget]';
    $below_widget_unitName = $name_prefix . '[below_widget_unit]';
    $pad_titleName = $name_prefix . '[pad_title]';
    $pad_title_unitName = $name_prefix . '[pad_title_unit]';
?>
			<p class="cca-brown"><?php _e('Change look of <u>text</u> widget (if required)', 'aw_ccawidget'); ?>:</p>
			<div class="cca-txtright">
    			<p><label for="<?php echo $fieldId_prefix; ?>no_margin"><?php _e('No Margin', 'aw_ccawidget'); ?></label>&nbsp;<input id="<?php echo $fieldId_prefix; ?>no_margin" name="<?php echo $name_prefix; ?>[no_margin]" type="checkbox" <?php checked(isset($instance['no_margin']) ? $instance['no_margin'] : 0); ?> />
						&nbsp; <label for="<?php echo $fieldId_prefix; ?>no_padding"><?php _e('No Padding', 'aw_ccawidget'); ?></label>&nbsp;<input id="<?php echo $fieldId_prefix; ?>no_padding" name="<?php echo $name_prefix; ?>[no_padding]" type="checkbox" <?php checked(isset($instance['no_padding']) ? $instance['no_padding'] : 0); ?> />
					  &nbsp; <label for="<?php echo $fieldId_prefix; ?>no_border"><?php _e('No Border', 'aw_ccawidget'); ?></label>&nbsp;
    			  <input id="<?php echo $fieldId_prefix; ?>no_border" name="<?php echo $name_prefix; ?>[no_border]"
						  type="checkbox" <?php checked(isset($instance['no_border']) ? $instance['no_border'] : 0); ?> />
    			  <br /><label for="<?php echo $fieldId_prefix; ?>no_background"><?php _e('No Background:', 'aw_ccawidget'); ?></label>&nbsp;<input id="<?php echo $fieldId_prefix; ?>no_background" name="<?php echo $name_prefix; ?>[no_background]" type="checkbox" <?php checked(isset($instance['no_background']) ? $instance['no_background'] : 0); ?> />
						&nbsp; <label for="<?php echo $fieldId_prefix; ?>full_width_img"><?php _e('Fit images to width', 'aw_ccawidget'); ?></label>&nbsp;<input id="<?php echo $fieldId_prefix; ?>full_width_img" name="<?php echo $name_prefix; ?>[full_width_img]" type="checkbox" <?php checked(isset($instance['full_width_img']) ? $instance['full_width_img'] : 0); ?> />
    			</p><i><?php _e("May not work on some themes");?></i>
      </div><hr />
			<span class="cca-brown"><?php _e('Fine tune widget alignment', 'aw_ccawidget'); ?>:</span>
			<div class="cca-txtright">
			  <p><?php _e('Increase/Reduce(-) space above Widget:', 'aw_ccawidget'); ?> <input style="width: 40px;" name="<?php echo  $above_widgetName; ?>" type="text" value="<?php echo $above_widget; ?>" />
    			 <select name="<?php echo $above_widget_unitName; ?>">
    				 <option value="px" <?php selected( $above_widget_unit, 'px' ); ?>>px</option><option value="em" <?php selected( $above_widget_unit, 'em' ); ?>>em</option></select>
    		</p>
        <p><?php _e('Extra space below Widget: ', 'aw_ccawidget'); ?><input style="width: 40px;" name="<?php echo $below_widgetName; ?>" type="text" value="<?php echo $below_widget; ?>" />
    			 <select name="<?php echo $below_widget_unitName; ?>">
    				<option value="px" <?php selected( $below_widget_unit, 'px' ); ?>>px</option><option value="em" <?php selected( $below_widget_unit, 'em' ); ?>>em</option></select>
    		</p>
        <p><?php _e('Title: left &amp; right margins: ', 'aw_ccawidget'); ?><input style="width: 40px;" name="<?php echo $pad_titleName; ?>" type="text" value="<?php echo $pad_title; ?>" />
    			  <select name="<?php echo $pad_title_unitName; ?>">
    				<option value="px" <?php selected( $pad_title_unit, 'px' ); ?>>px</option><option value="em" <?php selected( $pad_title_unit, 'em' ); ?>>em</option></select>
    		</p><i><?php _e("May not work on some themes");?></i>
    	</div><hr />
<?php

endif;

      if(has_action('cca_display_panel_bottom')) :
        do_action('cca_display_panel_bottom', $fieldId_prefix, $name_prefix, $instance, $entry_key);
			endif;?>
</div> <!-- end display-tab-panel -->

      <?php do_action('cca_add_panel_for_tab', $fieldId_prefix, $name_prefix, $instance, $entry_key); ?>
			<input type="hidden" id="<?php echo $fieldId_prefix; ?>current_panel" name="<?php echo $name_prefix; ?>[current_panel]" value="<?php echo $current_panel; ?>" />
			<input type="hidden" id="<?php echo $fieldId_prefix; ?>action" name="<?php echo $name_prefix; ?>[action]" value="<?php echo $action; ?>" />
	 </div> <!-- end aw_cca-widget-settings-container -->
<?php
      if (! empty($status_msg)) : 
        echo '<br /><div class="cca-msg cca-msg-' . $instance['msg_type'] . '">' . $status_msg . '</div>';
      endif;
		  if (!empty($this->ccax_options['diagnostics'])  ): cca_echo_settings($the_entry, $instance); endif;
			echo '<br /><br />';
    }  // end form function

} // end widget class



//COMMON DATA SETTINGS, SHARED AND HELPER FUNCTIONS FOR EXTENSIONS

// for diagnostics
function cca_echo_settings($cca_entry, $instance) {
  $cca_entry_values = esc_html(print_r($cca_entry, TRUE ));
	$instance = esc_html(print_r($instance, TRUE ));
  echo '<br ><div class="cca-brown"><p>' . __('Relevant Widget Entry Settings found:') . '<br />' . str_replace( '[' , '<br /> [' , $cca_entry_values) . '</p>';
	echo '<br /><p>' . __('FULL Widget Settings:') . '<br />' . $instance . '</p>';
  echo '<br /><p>Maxmind Data Directory: ' . CCA_MAXMIND_DATA_DIR . '</p>';
  if (! file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIP.dat') || ! file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIPv6.dat') ) : 
	  echo '<p class="cca-red">' . __("Warning the Maxmind directory is invalid, or one or more Maxmind files are missing") . '</p>';
  endif;
  echo '<br /><p>' .  __('CCA Site Settings') . ':<br />';
  $ccax_values = esc_html(print_r(get_option( 'ccax_options' ), TRUE ));
	echo str_replace( '[' , '<br /> [' , $ccax_values) . '</p></div><br />';
  $ccax_values = esc_html(print_r(get_option( 'CCA_VERSION_INFO' ), TRUE ));
	echo str_replace( '[' , '<br /> [' , $ccax_values) . '</p>';
}


function cca_encrypt_decrypt($action, $string, $secret_key, $secret_iv) {  // credit: http://naveensnayak.wordpress.com/
  $output = false;
  $encrypt_method = "AES-256-CBC";
  // hash
  $key = hash('sha256', $secret_key);
  // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
  $iv = substr(hash('sha256', $secret_iv), 0, 16);
  if( $action == 'encrypt' ) {
    $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
    $output = base64_encode($output);
  }
  else if( $action == 'decrypt' ){
    $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
  }
  return $output;
}

function cca_decrypt_emailaddress($encrypted_email, $crypt_key, $default_email='' ) {  
  if (!empty($encrypted_email) ) {
	  $key2 = get_option( 'cca_configcodes' );  
		if (! $key2) {  // create and store second key on first ever use of this function
		  $key2 = base64_encode(uniqid());
		  update_option( 'cca_configcodes', $key2);
		}
		$email_address = cca_encrypt_decrypt('decrypt', $encrypted_email, $crypt_key, $key2);
    if ( ! filter_var($email_address, FILTER_VALIDATE_EMAIL) ) $email_address = $default_email;
	}
	else $email_address = $default_email;
	return $email_address;
}


// define additional time spans for use in scheduling by wp cron
if (! function_exists('cca_weekly') ):
  function cca_weekly( $schedules ) {
    $schedules['cca_weekly'] = array( 'interval' => 604800, 'display' => __('Weekly') );
    return $schedules;
  }
  add_filter( 'cron_schedules', 'cca_weekly'); 
endif; 


// ************************************************~*****
// RSS EXTENSION (use as example for your own extensions)
// ******************************************************

// the extension "glue" is the string 'rss' - 'rss' is a necessary component of the filter and action names below, and is used in field values, and 
// rss specific settings are stored in an associative array "rss_settings" which forms part of the parent array for the widget instance

// widget RSS settings form and update are handled via a script in the /inc folder

	// display News Feed to visitor
	// much of code logic is from WP core default RSS widget
  function cca_rss_process_content_func($rss_settings) {
    if (empty($rss_settings) || ! empty($rss_settings['rss_error']) ) return FALSE;
    $rss_defaults = array( 'rss_show_author' => 0, 'rss_show_date' => 0, 'rss_show_summary' => 0, 'rss_link_target' => 0 );
    $rss_settings = wp_parse_args( $rss_settings, $rss_defaults );
    extract($rss_settings,EXTR_SKIP);
    $rss_url = ! empty( $rss_url ) ? filter_var($rss_url, FILTER_SANITIZE_URL) : '';
  	while ( stristr($rss_url, 'http') != $rss_url ) $rss_url = substr($rss_url, 1);
  	if ( empty($rss_url) ) return FALSE;
    // self-url destruction sequence
  	if ( in_array( untrailingslashit( $rss_url ), array( site_url(), home_url() ) ) ) return FALSE;
  	$rss = fetch_feed($rss_url);
    $title = $modified_title;
    $desc = '';
    $link = '';

		if ( is_wp_error($rss) ) return FALSE;
    $desc = esc_attr(strip_tags(@html_entity_decode($rss->get_description(), ENT_QUOTES, get_option('blog_charset'))));
    if ( empty($title) ) : $title = esc_html(strip_tags($rss->get_title())); endif;
    $link = esc_url(strip_tags($rss->get_permalink()));
    while ( stristr($link, 'http') != $link ) $link = substr($link, 1);

    $rss_items = (int) $rss_items;
    if ( $rss_items < 1 || 20 < $rss_items ) $rss_items = 10;
    $rss_show_summary  = (int) $rss_show_summary;
    $rss_summary_chars = (int) $rss_summary_chars;
    if ( $rss_summary_chars < 1 || 1500 < $rss_summary_chars ) $rss_summary_chars = 200;
    $rss_show_author   = (int) $rss_show_author;
    $rss_show_date     = (int) $rss_show_date;
    $rss_link_target = $rss_link_target ? ' target="_blank" rel="nofollow" ' : '';
    
    
    if ( empty($title) ) :
    	 $title = empty($desc) ? __('Unknown Feed', 'aw_ccawidget') : $desc;
    	 $title = apply_filters( 'widget_title', $title);
    endif;
    $rss_url = esc_url(strip_tags($rss_url));
  	$icon = includes_url('images/rss.png');
  	if ( $title ) : 
    	 $title = "<a class='rsswidget' href='$rss_url' title='" . esc_attr__( 'Syndicate this content', 'aw_ccawidget' ) . "'><img style='border:0' width='14' height='14' src='$icon' alt='RSS' /></a> <a class='rsswidget' href='$link' $rss_link_target title='$desc'>$title</a>";
    endif;

    if ($rss->get_item_quantity() ) :
    	$content = '<ul>';
    	foreach ( $rss->get_items(0, $rss_items) as $item ) :
    	  $link = $item->get_link();
    		while ( stristr($link, 'http') != $link ) $link = substr($link, 1);
    		$link = esc_url(strip_tags($link));
    		$item_title = esc_attr(strip_tags($item->get_title()));
    		if ( empty($item_title) ) : $item_title = __('Untitled', 'aw_ccawidget'); endif;
    
    		$desc = @html_entity_decode( $item->get_description(), ENT_QUOTES, get_option( 'blog_charset' ) );
    		$desc = esc_attr( strip_tags( $desc ) );
    		$desc = trim( str_replace( array( "\n", "\r" ), ' ', $desc ) );
    		$desc = wp_html_excerpt( $desc, $rss_summary_chars, '...' );
    
    		$summary = '';
    		if ( $rss_show_summary ) :
    			$summary = '<div class="rssSummary">' . esc_html( $desc ) . '</div>';
    		endif;
    
    		$date = '';
    		if ( $rss_show_date ) :
    		  $date = $item->get_date( 'U' );
    			if ( $date ) : $date = ' <span class="rss-date">' . date_i18n( get_option( 'date_format' ), $date ) . '</span>'; endif;
    		endif;
    
    		$author = '';
    		if ( $rss_show_author ) :
    		  $author = $item->get_author();
    			if ( is_object($author) ) :
    			  $author = $author->get_name();
    				$author = ' <cite>' . esc_html( strip_tags( $author ) ) . '</cite>';
    			endif;
    		endif;
    
    		if ( $link == '' ) :
    		  $content = '<li>' . $item_title . '{' . $date . '}{' . $summary . '}{' . $author . '}</li>';
    		elseif ( $rss_show_summary ) :
    		  $content .= "<li><a class='rsswidget' href='$link' $rss_link_target>$item_title</a>{$date}{$summary}{$author}</li>";
    		else :
    		  $content .= "<li><a class='rsswidget' href='$link' $rss_link_target title='$desc'>$item_title</a>{$date}{$author}</li>";
    		endif;
    	endforeach;
    	$content .= '</ul>';
    
    else :
     $content .= '<ul><li>' . __( 'An error has occurred, which probably means the feed is down. Try again later.', 'aw_ccawidget' ) . '</li></ul>';
    endif;

    $rss->__destruct();
    unset($rss);
		return array('content'=>$content,'title'=>$title);
  }
  add_filter('cca_rss_process_content', 'cca_rss_process_content_func' );