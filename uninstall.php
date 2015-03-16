<?php
if( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) 	exit();
delete_option('ccax_post_widgets');
delete_option( 'ccax_options' );
delete_option('CCA_WID_VERSION');
delete_option('ccax_maxmind_status');  // only created in older versions of plugin
// if you really and finally are removing the plugin then you may wish to delete these entries:
# delete_option('cca_widget_options');
# delete_option('cca_configcodes');  // this is actually a decryption key so it is probably best not to destroy in case of recovery/orphans