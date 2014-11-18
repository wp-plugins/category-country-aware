<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// ************************************************~*****
// RSS EXTENSION BACKEND ADMIN (use as example for your own extensions)
// ******************************************************
// the extension "glue" is the string 'rss' - 'rss' is a necessary component of the filter and action names below, and is used in field values, and 
// rss specific settings are stored in an associative array "rss_settings" which forms part of the parent array for the widget instance

// widget display of RSS is handled by main plugin script

function cca_add_widget_types_rss($radio_buttons, $fieldId_prefix, $name_prefix, $instance, $entry_key, $ccax_options) {
  $the_entry = $instance[$entry_key];
	$content_type = $the_entry['content_type'];
  if (empty($ccax_options['rss_function']) && $content_type != 'rss') return $radio_buttons;
  $widtype_groupname = $name_prefix . '[content_type]';
  $radio_buttons .= '<input type="radio" id="' . $fieldId_prefix . 'rsswid" class="cca-radio_widtype" name="' . $widtype_groupname . '" value="rss" ';
  $checked = ($content_type=='rss')?'checked':'';
  $radio_buttons .= $checked . '><label for="' . $fieldId_prefix . 'rsswid">' . __('RSS feed', 'aw_ccawidget') . '</label>';
  return $radio_buttons;
}
add_filter('cca_add_widget_types', 'cca_add_widget_types_rss',10,6);


	// adds a panel to enable input of RSS news feed settings to the CCA widget settings form
	// pretty similar to WP core default RSS widget logic plus additional options for length of excerpts and to open RSS links in new tab
  function cca_add_widget_type_panels_rss($fieldId_prefix, $widName, $instance, $entry_key) {
    $the_entry = $instance[$entry_key];
  	$content_type = strip_tags($the_entry['content_type']);
  	$rss_settings = $the_entry['rss_settings'] = empty($the_entry['rss_settings'])? array('rss_url'=>'','rss_items'=>10,'rss_error'=>false,'rss_show_summary'=>0,'rss_summary_chars'=>200,'rss_show_author'=>0,'rss_show_date'=>0,'rss_link_target'=>0) : $the_entry['rss_settings'];  // + adds element if not already exists
    $rss_url = esc_url($rss_settings['rss_url']);
    $rss_items = (int) $rss_settings['rss_items'];
    if ( $rss_items < 1 || 20 < $rss_items ) $rss_items = 10;
    $rss_show_summary  = (int) $rss_settings['rss_show_summary'];
  	$rss_summary_chars = (int) $rss_settings['rss_summary_chars'];
    if ( $rss_summary_chars < 1 || 1500 < $rss_summary_chars ) $rss_summary_chars = 200;
    $rss_show_author    = (int) $rss_settings['rss_show_author'];
    $rss_show_date  = (int) $rss_settings['rss_show_date'];
  	$rss_link_target = (int) $rss_settings['rss_link_target'];
?>
    <div id="<?php echo $fieldId_prefix; ?>rsswid_div"  class="cca-widget-entry-div<?php echo ($content_type=='rss')? ' cca-widget-entry-div-active':'' ;?>"><hr />
<?php
    if ( !empty($error) ) echo '<p class="widget-error"><strong>' . sprintf( __('RSS Error: %s', 'aw_ccawidget'), $error) . '</strong></p>';
?>
		<p><label for="<?php echo $fieldId_prefix;?>url"><?php _e('Enter the RSS feed URL here:', 'aw_ccawidget'); ?></label>
		<input class="widefat" id="<?php echo $fieldId_prefix;?>url" name="<?php echo $widName;?>[rss_url]" type="text" value="<?php echo $rss_url; ?>" /></p>
		<p><label for="<?php echo $fieldId_prefix;?>items"><?php _e('How many items would you like to display?', 'aw_ccawidget'); ?></label>
		  <select id="<?php echo $fieldId_prefix; ?>items" name="<?php echo $widName;?>[rss_items]">
			  <?php for ( $i = 1; $i <= 20; ++$i ) echo "<option value='$i' " . selected( $rss_items, $i, false ) . ">$i</option>";	?>
		</select></p>
		<p><input id="<?php echo $fieldId_prefix;?>show-summary" name="<?php echo $widName;?>[rss_show_summary]" type="checkbox" value="1" <?php if ( $rss_show_summary ) echo 'checked="checked"'; ?>/>
		  <label for="<?php echo $fieldId_prefix;?>show-summary"><?php _e('Display item content?', 'aw_ccawidget'); ?></label> ( <input style="width: 40px;" id="<?php echo $fieldId_prefix;?>rssnumchars"
    	name="<?php echo $widName;?>[rss_summary_chars]" type="text" value="<?php echo $rss_summary_chars; ?>" /> <?php _e('characters', 'aw_ccawidget'); ?>)
		</p>
		<p><input id="<?php echo $fieldId_prefix;?>show-author" name="<?php echo $widName;?>[rss_show_author]" type="checkbox" value="1" <?php if ( $rss_show_author ) echo 'checked="checked"'; ?>/>
		<label for="<?php echo $fieldId_prefix;?>show-author"><?php _e('Display item author if available?', 'aw_ccawidget'); ?></label></p>
		<p><input id="<?php echo $fieldId_prefix;?>show-date" name="<?php echo $widName;?>[rss_show_date]" type="checkbox" value="1" <?php if ( $rss_show_date ) echo 'checked="checked"'; ?>/>
		<label for="<?php echo $fieldId_prefix;?>show-date"><?php _e('Display item date?', 'aw_ccawidget'); ?></label></p>
		<p><input id="<?php echo $fieldId_prefix;?>link-target" name="<?php echo $widName;?>[rss_link_target]" type="checkbox" value="1" <?php if ( $rss_link_target ) echo 'checked="checked"'; ?>/>
		<label for="<?php echo $fieldId_prefix;?>link-target"><?php _e('If a visitor clicks a RSS link, open link in new tab (this option also "nofollows" for SEO)', 'aw_ccawidget'); ?></label></p>
		</div><!-- end id rsswid_div -->
<?php
  }
  add_action('cca_add_widget_type_panels', 'cca_add_widget_type_panels_rss',10,4);


	// this filter is "called" from CCA widget update method it returns RSS settings which are saved for the widget instance 
  function cca_rss_save_func($old_extension_content, $new_instance, $entry_key) {
    $rss_items = (int) $new_instance['rss_items'];
    if ( $rss_items < 1 || 20 < $rss_items )$rss_items = 10;
    $rss_show_summary  = isset( $new_instance['rss_show_summary'] ) ? (int) $new_instance['rss_show_summary'] : 0;
  	$rss_summary_chars = (int) $new_instance['rss_summary_chars'];
    if ( $rss_summary_chars < 1 || 1500 < $rss_summary_chars ) $rss_summary_chars = 200;
    $rss_url           = esc_url_raw( strip_tags( $new_instance['rss_url'] ) );
    $rss_show_author   = isset( $new_instance['rss_show_author'] ) ? (int) $new_instance['rss_show_author'] :0;
    $rss_show_date     = isset( $new_instance['rss_show_date'] ) ? (int) $new_instance['rss_show_date'] : 0;
  	$rss_link_target = isset( $new_instance['rss_link_target'] ) ? (int) $new_instance['rss_link_target'] : 0;
    // if a new url check that feed exists
    if ( isset( $new_instance['rss_url'] ) && ( !isset( $old_instance['rss_url'] ) || ( $new_instance['rss_url'] != $old_instance['rss_url'] ) ) ):
      $rss = fetch_feed($rss_url);
      $rss_error = false;
      $link = '';
      if ( is_wp_error($rss) ) :
        $rss_error = $rss->get_error_message();
  			$error_msg = 'NOT SAVED ' . $rss_error;
      else :
        $link = esc_url(strip_tags($rss->get_permalink()));
        while ( stristr($link, 'http') != $link ) $link = substr($link, 1);
        $rss->__destruct();
        unset($rss);
      endif;
    endif;
  	return compact('error_msg', 'rss_error', 'rss_url', 'link', 'rss_items', 'rss_show_summary', 'rss_summary_chars', 'rss_show_author', 'rss_show_date', 'rss_link_target' );
  }
  add_filter('cca_rss_save', 'cca_rss_save_func', 10, 3);
