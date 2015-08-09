<?php
if ( ! defined( 'ABSPATH' ) ) exit;

//==================================================================================
if ( ! class_exists( 'CCAXSettingsPage' ) ) { 
class CCAXSettingsPage {
//======================

  private $ccax_options = array();
	private $ccax_post_widgets = array();
	private $cca_plugin_ver; 

  public static function remove_extension_options($elements) {
    $ccax_options = CCAtextWidget::set_ccax_defaults();
    foreach($elements as $element) :
      unset($ccax_options[$element]);
    endforeach;
    update_option( 'ccax_options', $ccax_options );
  }


  public function __construct() {
    add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
    add_action( 'admin_init', array( $this, 'page_init' ) );

		$this->ccax_options = CCAtextWidget::set_ccax_defaults();

  	if ( get_option ( 'ccax_post_widgets' ) ) :
  	  $this->ccax_post_widgets = get_option( 'ccax_post_widgets' );
			$this->ccax_post_widgets = apply_filters('ccax_post_widget_initialise', $this->ccax_post_widgets);  // for future use
  	else :
			$this->ccax_post_widgets = apply_filters('ccax_post_widget_initialise', $this->ccax_post_widgets);  // filter for future use
  	  update_option( 'ccax_post_widgets', $this->ccax_post_widgets );
  	endif;
  }


  // Add CCA options page to Dashboard->Settings
  public function add_plugin_page()
  {
      add_options_page(
          'Category Country Settings',  // html title tag
          'Category Country Aware goodies',  // title (shown in dash->Settings).
          'manage_options',  // min user authority
					CCA_X_SETTINGS_SLUG,
          array( $this, 'create_cca_site_admin_page' )  //  function/method to display settings menu
      );
  }

  // Register and add settings
  public function page_init() {        
    // future??? allow a series of register_settings associating groups with different options to "isolate" options used by different extensions
    register_setting(
      'cca_group', // group the field is part of 
    	'ccax_options',  // option name
			array( $this, 'sanitize_ccax')
    );
  }

  // callback func specified in add_options_page func
  public function create_cca_site_admin_page() {
  	  if (empty( $this->ccax_options['init_geoip']) ):
			  // the Maxmind files only have to be initialised once cater for individual blog settings on multisite
  		  if (file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIP.dat') ): 
  		    $this->ccax_options['init_geoip'] = TRUE;
  				update_option( 'ccax_options', $this->ccax_options);
#				  $max_installed = TRUE;
  			else:
  			  echo '<div class="cca-msg cca-msg-warn"><p>' . __('You have not set your GeoIP options. Either check the option to "Initalize" or the option to "Disable" GeoIP') . '</p></div>';
  			endif;
 			elseif (empty( $this->ccax_options['disable_geoip']) && empty($_SERVER["HTTP_CF_IPCOUNTRY"]) && ! file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIPv6.dat') ) :
				  // something wrong, although "init" is set the Maxmind files don't exist
					echo '<div class="cca-msg cca-msg-warn"><p>' . __('The IP look-up files are missing - Maxmind Country Geolocation will not work. ');
					echo __('An uncheck/save, then check and save, of the"Update box" on the "Countries" tab might solve this.') . '</p></div>';
  		endif;
?>

    <div class="wrap">  
      <div id="icon-themes" class="icon32"></div> 
      <h2>Site-wide CCA settings</h2>  
		  <?php $active_tab = ( isset( $_GET[ 'tab' ]) && ctype_alpha($_GET[ 'tab' ]) ) ? $_GET[ 'tab' ] : 'general'; ?>  
      <h2 class="nav-tab-wrapper">  
				<a href="?page=<?php echo CCA_X_SETTINGS_SLUG;?>&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General'); ?></a>
				<a href="?page=<?php echo CCA_X_SETTINGS_SLUG;?>&tab=postwids&subtab=Top" class="nav-tab <?php echo $active_tab == 'postwids' ? 'nav-tab-active' : ''; ?>"><?php _e('Ads within Posts widget'); ?></a>
				<a href="?page=<?php echo CCA_X_SETTINGS_SLUG;?>&tab=country" class="nav-tab <?php echo $active_tab == 'country' ? 'nav-tab-active' : ''; ?>"><?php _e('Countries'); ?></a>
				<a href="?page=<?php echo CCA_X_SETTINGS_SLUG;?>&tab=test" class="nav-tab <?php echo $active_tab == 'test' ? 'nav-tab-active' : ''; ?>"><?php _e('Testing'); ?></a>
				<!-- // action hook using array of action_type=>tab label -->
				<?php do_action('ccax_add_tabs', $active_tab);?>
      </h2>  
      <form method="post" action="options.php">  

		<?php 
   	settings_fields( 'cca_group' );
    switch ( $active_tab ) :
      case 'general':
			  $this->render_general_panel();
				submit_button('Activate/de-activate these settings');
			 break;
      case 'postwids':
  			$this->render_postwids_panel();
        if ($_GET[ 'subtab' ] != 'select'):
				  submit_button();
				else: echo '<div class="cca-hidden">';submit_button();echo '</div>';
				endif;
       break;

      case 'country':
			  $this->render_country_panel();
				submit_button('Activate/de-activate these settings');
			 break;

      case 'test':
			  $this->render_test_panel();
				submit_button('Activate/de-activate these settings');
			 break;

      default:
			  if( has_action('ccax_render_' . $active_tab)) :
				  do_action('ccax_render_' . $active_tab, $this->ccax_options);
					submit_button('Activate/de-activate these settings');
				else :
				  $settings_msg = __("Error: not updated - unable to identify Tab for which data was sent. Tab=") . $active_tab;
				  $msg_type = 'error';
          add_settings_error('cca_group',esc_attr( 'settings_updated' ), __("Error: not updated - unable to identify Tab panel to display"),	'errorr'	);
        endif;
    endswitch;
		
		// return previous state - needed for knowing if action/non action required on multisite
	#	if (!empty($max_installed)): echo '<input type="hidden" name="ccax_options[max_installed]" value="1" /><br />'; endif;
		?>
				<input type="hidden" name="ccax_options[action]" value="<?php echo $active_tab; ?>" />
      </form> 
    </div> 

	<?php
  }


// ******************************************************************
// SETTINGS FORM - SITE WIDE WIDGET CONFIGURATION SETTINGS
// ******************************************************************

public function render_general_panel() { ?>
	<h3><?php _e("GeoLocation", 'aw_ccawidget');?></h3>
	<div class="cca-indent20 cca-bold">
    <p><input id="ccax_init_geoip" name="ccax_options[init_geoip]" type="checkbox" <?php checked($this->ccax_options['init_geoip']); ?> />
        <label for="ccax_init_geoip"><?php _e('Initialize GeoIP', 'aw_ccawidget'); ?>
  	   <i> <?php _e("checking this will do the initial install of Country/IP look up files from Maxmind (it takes < 1 second).", 'aw_ccawidget');?></i></label></p>
		<p><input id="ccax_disable_geoip" name="ccax_options[disable_geoip]" type="checkbox" <?php checked($this->ccax_options['disable_geoip']); // !empty($this->ccax_options['disable_geoip']) ?> />
        <label for="ccax_disable_geoip"><?php _e('Disable country selection on <u>all</u> CCA widgets.', 'aw_ccawidget'); ?>
				  <i class="cca-brown"><?php _e('Tick this setting if you\'re using a caching plugin (or service) that is unable to manage geo-location content', 'aw_ccawidget'); ?> (
		        <?php _e('see ');?> <a class="cca-bold" href="http://<?php echo CCA_SUPPORT_SITE; ?>/2014/11/plugin-and-geoLocation"><?php _e('configuring caching plugins for GeoLocation', 'aw_ccawidget');?></a>) :
		     </i></label>
		 </p>
	</div><hr /><?php

  do_action('ccax_render_general',$this->ccax_options);

  if ($this->ccax_options['responsive_function']):
		echo '<div class="cca-bold"><a name="resp"></a><h3>' . __("CCA Responsive") . '</h3>';
		echo __('Set "small device" width to: ');
?>
		<input name="ccax_options[responsive_px]" type="text" size="6" value="<?php echo $this->ccax_options['responsive_px'] ?>" /><b>px</b> 
		<div class="cca-indent20"><?php 
			echo __("<u>If</u> a widget's responsive option is checked:") . '<div class="cca-indent20">';
		  echo __("only display it <i>within</i> posts when browser width is < small device width; and") . '<br />';
		  echo  __('only display it in standard widget areas if browser width is > small device width.') . '<br />';?>
		</div></div></div><hr />  <?
	else: ?>
	  <input type="hidden" name="ccax_options[responsive_px]" value="<?php echo $this->ccax_options['responsive_px'] ?>" /><?php
  endif;
	echo '<h3>' . __("De-clutter: only include the options you need in plugin/widget settings:", 'aw_ccawidget');?></h3>
	<p><b><?php echo __('Functionality for both ', 'aw_ccawidget') . '<a href="?page=' . CCA_X_SETTINGS_SLUG . '&tab=postwids&subtab=Top">"Ad/post Widgets"</a>';
	  echo __(' and standard  ', 'aw_ccawidget') . '<a href="' . admin_url() . 'widgets.php">CCA widgets</a>';?>:</b></p>
	<div class="cca-indent20 cca-bold">
        <p><input id="ccax_responsive_function" name="ccax_options[responsive_function]" type="checkbox" <?php checked($this->ccax_options['responsive_function']) ?> />
        <label for="ccax_responsive_function"><?php _e("Add 'Responsive' option to each widget's settings.", 'aw_ccawidget'); ?></label></p>
  </div>
<h4><?php _e('CCA widgets only:', 'aw_ccawidget');?></h4>
	<div class="cca-indent20 cca-bold">
        <p><input id="ccax_display_function" name="ccax_options[display_function]" type="checkbox" <?php checked($this->ccax_options['display_function']) ?> />
        <label for="ccax_display_function"><?php _e("Add display options to each widget's settings (allows override of theme styles).", 'aw_ccawidget'); ?></label></p>
        <p><input id="ccax_rss_function" name="ccax_options[rss_function]" type="checkbox" <?php checked($this->ccax_options['rss_function']) ?> />
        <label for="ccax_rss_function"><?php _e("Add RSS/news feed capability to widget", 'aw_ccawidget'); ?></label></p>
	</div><hr /><?php

}  // END function render_general_panel



public function render_country_panel() {
   do_action('ccax_render_country_top', $this->ccax_options);
   if ( ! empty($_SERVER["HTTP_CF_IPCOUNTRY"])):
     echo __('It looks like site is using Cloudflare is enabled for GeoLocation. ');
  	 echo __(' Although not essential, it is worth "initializing and updating" Maxmind files so they can then be used by the CCA plugin as a Fallback, if Cloudflare Geolocation has a problem.') . '<br /><br />';
  endif;
 ?>
		<h3>Automatically update Maxmind GeoLocation IP database</h3>
		<p><input id="ccax_update_maxmind" name="ccax_options[update_maxmind]" type="checkbox" <?php checked(!empty($this->ccax_options['update_maxmind']));?>/>
      <label for="ccax_update_maxmind"><?php _e('Update data files now, and add to WP scheduler for auto update every 3 weeks');?></label></p>
<?php 
      if (!empty($this->ccax_options['update_maxmind']) && ! wp_next_scheduled( CCA_X_MAX_CRON ) ): 
#			  cca_update_maxmind();
			  wp_schedule_event( time()+864000, 'cca_3weekly', CCA_X_MAX_CRON );
  	  endif;

			if (is_multisite()):
			  _e("<b>You are using MultiSite WordPress</b>. Unfortunately WP scheduled jobs are run <i>by individual blog</i> this will result in unnecessary attempts to update Maxmind and display of out of date warnings.");
				echo '<br />' . __(" To avoid this <b>make sure the above option is only checked on one blog</b>, and use the settings on that blog to check the health of your GeoIP.") . '<br /><br />';
			endif;

			clearstatcache();
			$both_good = $ipv6_exists = $ipv4_exists = TRUE;
			if (file_exists(CCA_MAXMIND_DATA_DIR)):
				 $dir_exists = TRUE;
				 echo 'Maxmind Directory: "' . CCA_MAXMIND_DATA_DIR . '"<br />';
				 if (file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIP.dat')): 
				    echo __('File "GeoIP.dat" last successfully updated : ') . date("F d Y H:i:s.",filemtime(CCA_MAXMIND_DATA_DIR . 'GeoIP.dat')) . '<br />';
				 else: 
					  echo '<span class="cca-brown">' . __('File "GeoIP.dat" could not be found. Maxmind IPv4 geolocation will not be functioning.') . '</span><br />';
					  $both_good = $ipv4_exists = FALSE;
				 endif;
				 if (file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIPv6.dat')): 
				    echo  __('File "GeoIPv6.dat" last successfully  updated : ') . date("F d Y H:i:s.",filemtime(CCA_MAXMIND_DATA_DIR . 'GeoIPv6.dat')) . '<br />';
				 else:
					  echo '<span class="cca-brown">' . __('File "GeoIPv6.dat" could not be found. Maxmind IPv6 geolocation will not be functioning. ') . '</span><br />';
					  $both_good = $ipv6_exists = FALSE;
				 endif;
			 else:
			 		$dir_exists = $both_good = $ipv6_exists = $ipv4_exists = FALSE;
					echo '<span class="cca-brown">' . __('The MAxmind Directory ("') . CCA_MAXMIND_DATA_DIR . '") ' . __('does not exist. Maxmind Country GeoLocation will not be working.')  . '</span><br />';
			 endif;

			 $cc_maxmind_status = get_option( 'cc_maxmind_status' );
			 if ($cc_maxmind_status && ! empty($cc_maxmind_status['health'] ) && $cc_maxmind_status['health'] != 'ok'):
			 		echo '<p>' . __('The last update process reported a problem: "') . ' <span class="cca-brown">' . $cc_maxmind_status['result_msg'] . '</span>"</p>';
			 endif; 
			 echo '<hr />';

echo '<h3>Make Cookie_Notice plugin only display cookie law bar to visitors from the EU</h3>';
echo '<p><a href="https://www.google.com/about/company/user-consent-policy.html" target="_blank">Google now requires sites using Adsense</a> obtain cookie consent from their "European end users".';
echo ' Even EU visitors find "cookie consent bars" irritating so most sites will not want them displayed for non-EU visitors.</p>';
echo '<p>If you are using the <i><a href="https://wordpress.org/plugins/cookie-notice/" target="_blank">Cookie Notice</a> plugin (one of the most popular) you can use these settings so its cookie bar is only displayed to EU visitors.</p>';
if (empty($this->ccax_options['EU_ccodes'])):
   $this->ccax_options['EU_ccodes'] = CCAgeoip::get_EU_ISOs();
endif;
?><p><input id="ccax_only_EU_cookie" name="ccax_options[only_EU_cookie]" type="checkbox" <?php checked(!empty($this->ccax_options['only_EU_cookie']));?>/>
      <label for="ccax_only_EU_cookie"><?php _e('Check this box to set <i>Cookie Notice</i> to only display its cookie bar for these countries:');?></label></p>
		  <div class="cca-indent20">
  		  <input id="ccax_EU_ccodes" name="ccax_options[EU_ccodes]" type="text" style="width:600px !important" value="<?php echo $this->ccax_options['EU_ccodes']; ?>" />
  		  <br><i>(<?php _e('You should check, and if necessary edit this list - no warranty is give that it is complete and up to date.');?></i>
      </div>

<?php

			do_action('ccax_render_country_bottom',$this->ccax_options);
}   //  END render_country_panel()


public function render_test_panel() { 
		// render content from extension
		do_action('ccax_render_test_top', $this->ccax_options);
?>
<h3>Diagnostics</h3>
  <p><i>Only enable these options if you need to provide additional information for support questions, or if you are a developer writing CCA extensions.</i></p>
  <?php 
    echo '<p><input id="ccax_show_ccax_vars" name="ccax_options[show_ccax_vars]" type="checkbox" />';
    echo '<label for="ccax_show_ccax_vars">' . __("Display CCA Site Settings variables below") . '</label></p>';
    if (!empty($this->ccax_options['show_ccax_vars']) ) :
  	  if (! defined('CCA_MAXMIND_DATA_DIR') || ! file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIP.dat') || ! file_exists(CCA_MAXMIND_DATA_DIR . 'GeoIPv6.dat') ) : 
  			echo '<p class="cca-red">' . __("Warning the Maxmind directory is invalid, or one or more Maxmind files are missing") . '</p>';
				unset($this->ccax_options['show_ccax_vars']);
  		endif;
  	  $ccax_values = esc_html(print_r($this->ccax_options, TRUE ));
      echo '<p>' . __("Setting values") . ' =</p>' . str_replace ( '[' , '<br /> [' , print_r($ccax_values, TRUE )) . '</p><hr />';
    endif;
  	echo '<p><input type="checkbox" id="ccax_list_jobs" name="ccax_options[list_jobs]" />';
  	echo '<label for="ccax_list_jobs">' . __('List scheduled WP jobs for this blog') . '</label></p>';
    if ( ! empty($this->ccax_options['list_jobs'])) :
      _e("This is the list of jobs scheduled on your site (it does NOT mean your WP scheduler/cron is actually working).<br />");
     	_e("If update of Maxmind GeoIP files is enabled then the job 'cca_update_maxmind' (yellow highlight) should be listed ONCE below. ");
 			_e("Otherwise the job should not be listed.<hr /><br />");
      cca_list_cron_jobs(CCA_X_MAX_CRON);
    endif;
    echo '<p><b>' . __('Only visible when logged in <u>as "Admin"</u>') . '</b>:</p><div class="cca-indent20">';
    echo '<p><input id="ccax_show_widget_vars" name="ccax_options[diagnostics]" type="checkbox" '; checked(!empty($this->ccax_options['diagnostics'])); echo '/>';
    echo '<label for="ccax_show_widget_vars">' . __('Display settings variables with each widget (on Dashboard->Widgets &amp; actual posts)') . '</label></p>';
    echo '</div><hr />';
		do_action('ccax_render_test_bottom', $this->ccax_options);
}   //  END render_test_panel()


// Render Post Widgets Settings Panel
  public function render_postwids_panel() {
	  $active_subtab = ( isset( $_GET[ 'subtab' ]) && ctype_alpha($_GET[ 'subtab' ]) ) ? $_GET[ 'subtab' ] : 'Top';
		?><br />
    <div class="ccax-tabs"><ul>
      <?php $subtab_class =  ($active_subtab == 'Top') ? ' class="cca-tab-active"' : ''; ?>
      <li<?php echo $subtab_class . '><a href="?page=' . CCA_X_SETTINGS_SLUG . '&tab=postwids&subtab=Top" title="Top of Post">' . __("&nbsp; Top <br>of Post");?></a></li>
  		<?php do_action('ccax_add_postwidget_tabs', $active_subtab);
      $subtab_class =  ($active_subtab == 'select') ? ' class="cca-tab-active"' : '';
  		?>
      <li<?php echo $subtab_class. '><a href="?page=' . CCA_X_SETTINGS_SLUG . '&tab=postwids&subtab=select" title="List Widget entries">' . __("List/Edit existing<br />Widget entries");?></a></li>
    </ul></div><hr />
		<?php 
    if ($active_subtab == 'select'):
      $this->ccax_options_widget_select();
			return;
    elseif ($active_subtab == 'Top'):
		 _e("<h3>Insert advert/banner/whatever at the TOP of ALL your posts</h3>", 'aw_ccawidget');
		else:
		  echo apply_filters('ccax_postwid_heading', $active_subtab );
		endif;
		$entry_for_postwid =  empty($this->ccax_post_widgets[$active_subtab]) ? array() : $this->ccax_post_widgets[$active_subtab];
		if (has_action('ccax_postwidget_more_options') /* && !empty($this->ccax_post_widgets[$active_subtab] )*/ ):
		  do_action('ccax_postwidget_more_options', $active_subtab, $entry_for_postwid);
		endif;
		?>

    <p><?php _e("Content to display when category is:");?></p>
    <div class="cca-indent20">
<?php
       $selected_category = (! empty($this->ccax_post_widgets[$active_subtab]['selected_category']) && ctype_digit($this->ccax_post_widgets[$active_subtab]['selected_category'])) ? $this->ccax_post_widgets[$active_subtab]['selected_category'] :  '0';
       $dropdown_args = array('id' => 'ccax_selected_category', 'name' => 'ccax_options[selected_category]', 'selected' => $selected_category, 'orderby'=>'NAME', 'hide_empty'=>FALSE, 'show_option_all' => '"all" / "other" (Default)');
?>
     	<p><?php wp_dropdown_categories($dropdown_args); # _e('Category: ', 'aw_ccawidget'); ?></p>
    </div>
      AND visitor is from: 
    <div class="cca-indent20">
		  <?php 
// 0.9.0			
			$disable_geoip = empty($this->ccax_options['disable_geoip']) ? FALSE:TRUE;
			?>
      <p><select name="ccax_options[selected_country]"<?php if ($disable_geoip) echo ' disabled="disabled"'; ?>><?php
         $country_array = array('-anywhere-' => '"any"/"other" (Default)') + apply_filters( 'cca_widget_country_list', CCAgeoip::get_country_array() );
    		 $selected_country = empty($this->ccax_post_widgets[$active_subtab]['selected_country']) ? '-anywhere-' : $this->ccax_post_widgets[$active_subtab]['selected_country'];
			 if (! array_key_exists($selected_country,$country_array) || $disable_geoip ) $selected_country = '-anywhere-';
         foreach($country_array as $iso=>$country_name ) :
           $display_country = ' (' . $iso . ')';
           if ($iso == '-anywhere-') $display_country = '';
           echo '<option value="' . $iso . '" ' . selected( $selected_country, $iso, FALSE ) .  '>' . $country_name . $display_country . '</option>';
         endforeach; ?>
       </select></p>
    </div><hr />

		<div style="padding-left:40px">

<?php $selected_content = $selected_category . '_' . $selected_country;
		$make_responsive = empty($this->ccax_post_widgets[$active_subtab]['entry'][$selected_content]['make_responsive']) ? '' : '1';
 		if ($this->ccax_options['responsive_function']):
		  $responsive_px = empty($this->ccax_options['responsive_px']) ? 'this width setting' : $this->ccax_options['responsive_px'] . ' px';
?>
		  <p><input id="ccax_resp" name="ccax_options[make_responsive]" type="checkbox" <?php checked($make_responsive);?>/>
			 <label for="ccax_resp"> <?php echo __('"Responsive+"') . '</label> ' . __('(only display this entry if screen width < ') . '<a href="?page=' . CCA_X_SETTINGS_SLUG . '&tab=general#resp">' . $responsive_px . '</a> )';?>
			</p>
<?php
		else: ?>
		  <input type="hidden" id="ccax_resp" name="ccax_options[make_responsive]" value="<?php echo $make_responsive; ?>" />
<?php
		endif;
?>
<?php
	 $contentValue = empty($this->ccax_post_widgets[$active_subtab]['entry'][$selected_content]['content']) ? '' : esc_html($this->ccax_post_widgets[$active_subtab]['entry'][$selected_content]['content']);
?>
    	<p><?php _e('Enter/Edit Content');?> <textarea rows="10" cols="20" class="widefat" name="ccax_options[content]"><?php echo $contentValue;?></textarea></p>
			<input type="hidden" id="ccax_sub_action" name="ccax_options[sub_action]" value="<?php echo $active_subtab; ?>" />
		</div><hr />
<?php
	}


  // render post widget list/edit panel
  function ccax_options_widget_select() { ?>
    <p class="cca-brown"><b><?php _e('Manage an existing entry', 'aw_ccawidget'); ?></b><br />
     <i><?php _e('List/edit/delete custom content for Category/Country', 'aw_ccawidget'); ?></i></p>
  	<div style="float:left;width:75px"><u>Widget Type</u></div><div style="float:left;margin-left:81px"><u>Entry to edit</u></div><div class="cca-clearfix"></div><br /><br />

  <?php
    foreach( $this->ccax_post_widgets as $type => $type_array ):
  	  echo '<div style="float:left;width:75px"><b>' . $type . ':</b></div>';
    	// identify and list all existing content entries 
    	$cat_content_array = array();
      $cat_content_array = $type_array['entry'];
      $cat_details = array();
      //sort by category id + country
      asort($cat_content_array);
      echo '<div style="float:left;margin-left:81px"><select id="ccax_list_' . $type .'" name="ccax_options[selected_' . $type . ']">';
      foreach( $cat_content_array as $contentEntry => $avalue ) :
        $term_id = (int) $avalue['term_id'];
        if ($term_id > 0) :
          $cat_details = get_category(  $term_id,'ARRAY_A' );
          elseif ($term_id == 0) :
          $cat_details['name']  = '"all"';
        endif;
  
        if ($contentEntry == $this->ccax_options['selected_widget_entry']) : $setSelected = ' selected="selected"';
        else : $setSelected = '';
        endif;
        if ($term_id > -1):
          echo '<option value="' . $contentEntry . '"' . $setSelected .  '>' . $cat_details['name'] . ' (' . $avalue['country'] . ')</option>';
        endif;
      endforeach;
  ?>
        </select> &nbsp; 
        <button type="button" id="ccaxwid_<?php echo $type; ?>_edit" name="ccax_<?php echo $type; ?>_widget_edit"
          value="edit" class="ccax-button button button-primary"><?php _e('Edit', 'aw_ccawidget'); ?></button>
        <button type="button" id="ccaxwid_<?php echo $type; ?>_delete" name="ccax_<?php echo $type; ?>_widget_del"
          value="Delete" class="ccax-button button button-primary"><?php _e('Delete', 'aw_ccawidget'); ?></button></div><div class="cca-clearfix"></div><br /><br />
  <?php  
  	  if ( $this->ccax_options['widtype'] == $type && $this->ccax_options['current_action'] == 'edit_widget' && ! empty($this->ccax_options['selected_widget_entry']) ) :
  			  $entry = $this->ccax_options['selected_widget_entry'];
  			  $term_id =  $this->ccax_post_widgets[$type]['entry'][$entry]['term_id'];
  			  if ( ctype_digit($term_id) && (int) $term_id > 0):
  				  $catdetails = get_category( (int) $term_id,'ARRAY_A' );
  				  echo 'Category: ' . $catdetails['name'] . ' | Country: ' . $this->ccax_post_widgets[$type]['entry'][$entry]['country'];
  				elseif ($term_id == "0"):
  				  echo 'Category: "all"/default' . ' | Country: ' . $this->ccax_post_widgets[$type]['entry'][$entry]['country'];
  				endif;
  ?>

<?php
      		$make_responsive = empty($this->ccax_post_widgets[$type]['entry'][$entry]['make_responsive']) ? '' : '1';
       		if ($this->ccax_options['responsive_function']):
?>
      		 | Responsive: <input id="ccax_resp" name="ccax_options[make_responsive]" type="checkbox" <?php checked($make_responsive);?>/>
<?php
      		else: ?>
      		  <input type="hidden" id="ccax_resp" name="ccax_options[make_responsive]" value="<?php echo $make_responsive; ?>" />
<?php
      		endif;
?>

      	<p><?php _e('Enter/Edit Content');?> <textarea rows="10" cols="20" class="widefat" name="ccax_options[content]"><?php echo $this->ccax_post_widgets[$type]['entry'][$entry]['content'];?></textarea></p>
        <button type="button" id="ccaxwid_<?php echo $type; ?>_save" name="ccax_<?php echo $type; ?>_widget_save"
          value="Save" class="ccax-button button button-primary"><?php _e('Save Entry', 'aw_ccawidget'); ?></button>
  <?php
  		endif;
      echo '<br /><hr /><br /><br />';
    endforeach;
  	$this->ccax_options['widtype'] = '';
    $this->ccax_options['current_action'] = '';
  	$this->ccax_options['selected_widget_entry'] = '';
  ?>
  			<input type="hidden" id="ccax_sub_action" name="ccax_options[sub_action]" value="select" />
  			<input type="hidden" id="ccax_widtype" name="ccax_options[widtype]" value="" />
  			<input type="hidden" id="ccax_button_action" name="ccax_options[button_action]" value="" />
  <?php
  }   // END ccax_options_widget_select()


  // validate and save settings fields changes
  public function sanitize_ccax( $input ) {
		$settings_msg = ''; $this->ccax_options['temp_msg'] = '';
		$msg_type = 'updated';
    switch ($input['action']) :
			case 'postwids':
			  if ($input['sub_action'] != 'select' ) $this->sanitize_widget($input);
				else $this->sanitize_widget_select($input);
				return $this->ccax_options;  // although not updating this we still need to return it
			 break;
			case 'general':
			  $this->sanitize_general($input);
			 break;
      case 'country':
        $this->sanitize_country($input);
       break;
      case 'test':
        $this->sanitize_test($input);
       break;
      default:
			  if( has_filter('ccax_sanitize_' . $input['action']) ) :
					$this->ccax_options = apply_filters('ccax_sanitize_' . $input['action'], $this->ccax_options, $input);
				else :
  				$settings_msg = implode("|",$input) . __("Error: not updated - unable to identify Tab from which input was sent. Tab = ") . $input['action'];
  				$msg_type = 'error';
        endif;
    endswitch;
		
    if ( !empty($this->ccax_options['temp_msg']) ) :
    		$settings_msg .= $this->ccax_options['temp_msg'];
    		$msg_type = 'error';					  
    		unset($this->ccax_options['temp_msg']);
    endif;

    if ($settings_msg != '') :
      add_settings_error('cca_group',esc_attr( 'settings_updated' ), __($settings_msg),	$msg_type	);
    endif;

		return $this->ccax_options;   
  }  // end santize func


  function sanitize_general($input) {

		$this->ccax_options['disable_geoip'] = empty($input['disable_geoip']) ? FALSE : TRUE;
		$this->ccax_options['responsive_function'] = empty($input['responsive_function']) ? FALSE : TRUE;
		$this->ccax_options['display_function'] = empty($input['display_function']) ? FALSE : TRUE;
		$this->ccax_options['rss_function'] = empty($input['rss_function']) ? FALSE : TRUE;

    if ( ! empty($input['init_geoip'] ) && empty($this->ccax_options['init_geoip']) ):
#			if ( empty($input['max_installed']) ) :
					// the user is initializing maxmind for the first time, we'll switch auto update on for them, they can switch it off later if they want
				 $do_max = new CCAmaxmindSave();
				 $do_max->save_maxmind(TRUE);
				 if (! $do_max):
				   $this->ccax_options['temp_msg'] .= __('There was a problem installing the Maxmind files, see the "Countries" tab for more info, and the CCA guide for a solution.<br />');
				 endif;
   			 unset($do_max);
  			 $this->ccax_options['update_maxmind'] = TRUE;
         if ( ! wp_next_scheduled( CCA_X_MAX_CRON ) ): 
      	    wp_schedule_event( time()+864000, 'cca_3weekly', CCA_X_MAX_CRON );
         endif;
# 		  endif;
		  $this->ccax_options['init_geoip'] = TRUE;
    endif;

    $responsive_px = empty($input['responsive_px'] ) ? '' : $input['responsive_px'];
    if ( $responsive_px == '' || ctype_digit($responsive_px) ) :
      $this->ccax_options['responsive_px'] = $responsive_px;
    else:
      $this->ccax_options['temp_msg'] .= __('Responsive Width has not been updated; it must be either an integer or empty.<br />');
    endif;

	  $this->ccax_options = apply_filters('ccax_sanitize_general',$this->ccax_options, $input);
  }


  function sanitize_country($input) {
    if ( empty($input['update_maxmind']) ):
      $this->ccax_options['update_maxmind'] = FALSE;
     	wp_clear_scheduled_hook( CCA_X_MAX_CRON);
    else:
     	$this->ccax_options['update_maxmind'] = TRUE;

				 $do_max = new CCAmaxmindSave();
				 $do_max->save_maxmind(TRUE);
				 if (! $do_max):
				   $this->ccax_options['temp_msg'] .= __('There was a problem installing the Maxmind files, see the "Countries" tab for more info, and the CCA guide for a solution.<br />');
				 endif;
   			 unset($do_max);

      if ( ! wp_next_scheduled( CCA_X_MAX_CRON ) ): 
			  wp_schedule_event( time(), 'cca_3weekly', CCA_X_MAX_CRON );    
  	  endif;
    endif;
  
	  $this->ccax_options['only_EU_cookie'] = empty($input['only_EU_cookie'] ) ? FALSE : TRUE;
    $this->ccax_options['EU_ccodes'] = $input['EU_ccodes'];

  	$this->ccax_options = apply_filters('ccax_sanitize_country', $this->ccax_options, $input);
  }

  function sanitize_test($input) {
    $this->ccax_options['diagnostics'] = empty($input['diagnostics'] ) ? FALSE : TRUE;
    $this->ccax_options['show_ccax_vars'] = empty($input['show_ccax_vars']) ? FALSE : TRUE;
    $this->ccax_options['list_jobs'] = empty($input['list_jobs']) ? FALSE : TRUE;
  
  	$this->ccax_options = apply_filters('ccax_sanitize_test', $this->ccax_options, $input);
  }


  function sanitize_widget($input) {
  	$widtype = $input['sub_action'];
    $this->ccax_post_widgets[$widtype]['activated'] = empty($input['activated']) ? FALSE : TRUE;
		if (empty($input['selected_country'])) $input['selected_country'] = '-anywhere-';

    if ( !ctype_digit($input['selected_category']) && ($input['selected_country'] != '-anywhere-' || ! ctype_alpha($input['selected_country'])) ):
  		$this->ccax_post_widgets[$widtype]['selected_category'] = '0';
  		$this->ccax_post_widgets[$widtype]['selected_country'] = '-anywhere-';
      $settings_msg = __("Selected category/country not recognised");
      $msg_type = 'error';
    else:
  	  $entry = $input['selected_category']. '_' . $input['selected_country']; 
      if ( ! current_user_can('unfiltered_html') ) : $input['content'] = stripslashes( wp_filter_post_kses( addslashes($input['content']) ) ); endif;
      $this->ccax_post_widgets[$widtype]['entry'][$entry]['term_id'] = $input['selected_category'];
      $this->ccax_post_widgets[$widtype]['entry'][$entry]['country'] = $input['selected_country'];
			$this->ccax_post_widgets[$widtype]['entry'][$entry]['make_responsive'] = empty($input['make_responsive']) ? FALSE : TRUE;
      $this->ccax_post_widgets[$widtype]['entry'][$entry]['content'] = $input['content'];
  		$this->ccax_post_widgets[$widtype]['selected_category'] = $input['selected_category'];
  		$this->ccax_post_widgets[$widtype]['selected_country'] = $input['selected_country'];

			$this->ccax_post_widgets[$widtype] = apply_filters('ccax_sanitize_postwid_more',$this->ccax_post_widgets[$widtype], $widtype, $input);

      update_option( 'ccax_post_widgets', $this->ccax_post_widgets );
    endif;
  }

  function sanitize_widget_select($input) {
    $widtype = $input['widtype'];
    $entry = 'selected_' . $widtype;
  	$entry = $input[$entry];
  	if ($input['button_action'] == 'save') :
  	  $this->ccax_post_widgets[$widtype]['entry'][$entry]['content'] = $input['content'];
			$this->ccax_post_widgets[$widtype]['entry'][$entry]['make_responsive']= empty($input['make_responsive']) ? FALSE : TRUE;
    elseif ($input['button_action'] == 'delete') :
  	  unset($this->ccax_post_widgets[$widtype]['entry'][$entry]);
  	  update_option( 'ccax_post_widgets', $this->ccax_post_widgets );
  	  $this->ccax_options['widtype'] = '';
  	  $this->ccax_options['current_action'] = '';
  	  $this->ccax_options['selected_widget_entry'] = '';
  	else:
  	  $this->ccax_options['widtype'] = $widtype;
  	  $this->ccax_options['current_action'] = 'edit_widget';
  	  $this->ccax_options['selected_widget_entry'] = $entry;
  	endif;
    update_option( 'ccax_post_widgets', $this->ccax_post_widgets );
  }

} // end class CCAXSettingsPage
} // end if ! class exists


// instantiate
if(is_admin()) $ccax_settings_page = new CCAXSettingsPage();