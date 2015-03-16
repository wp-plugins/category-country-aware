<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ensure js & CSS for dashboard forms is sent to browser
function AW_CCA_load_admincssjs() {
  # could do more specific check e.g.  get_current_screen() and check if ['base'] or ['id'] == '...cca-sitewide-settings' but prob slower 
  if( $GLOBALS['pagenow'] == 'widgets.php' || $GLOBALS['pagenow'] == 'options-general.php' ) {
	  wp_enqueue_style( 'cca-textwidget-style', plugins_url( 'css/cca-textwidget.css' , __FILE__ ) );
  	wp_enqueue_script( 'cca-textwidget-js', plugins_url( 'js/cca-textwidget.js' , __FILE__ ), array('jquery') ); // js is not needed on dashboard->settings
  }
}
add_action('admin_enqueue_scripts', 'AW_CCA_load_admincssjs');

// make ready for language files
function AW_CCA_load_textdomain() {load_plugin_textdomain( 'aw_ccawidget', false, dirname(plugin_basename( __FILE__ )) . '/langs/' );}
add_action( 'init', 'AW_CCA_load_textdomain' );


//COMMON DATA SETTINGS, SHARED AND HELPER FUNCTIONS FOR EXTENSIONS

function cca_list_cron_jobs($highlight = '') {
  $cron = _get_cron_array();
  foreach( $cron as $time => $job )  :
	  $jobname = (key($job)==$highlight) ? '<b class="cca-highlight">' . key($job) . '</b>' : key($job);
    echo $jobname . ' (OS time: ' . $time . ')<br /> ';
    print_r($job[key($job)]);
		echo '<hr />';
  endforeach;
}


// skeleton for extension developer experimentation & testing
/*  
register_activation_hook( __FILE__, 'CCA_main_activate' );
register_deactivation_hook(__FILE__, 'CCA_main_deactivate');

function CCA_main_deactivate()   {
	$cca_dependent = array();
	$cca_dependent = apply_filters('cca_cascade_deactivation',$cca_dependent);
	// store $cca_dependent as option
	deactivate_plugins( $cca_dependent ); // you may have to additionally manually call the plugins' own deactivate functions
}
function CCA_main_activate() {
  // reactivate plugins in list - note activate_plugins sandbox has issues e.g. does not run plugins activation hooks? so:
  // http://wordpress.stackexchange.com/questions/4041/how-to-activate-plugins-via-code
}
*/



