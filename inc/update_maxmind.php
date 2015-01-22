<?php
# script to get latest Maxmind GeoLiteGeoip data and unzip  and notify success or failure by email
# associated article: 
/* create a cron job/schedule to automatically run this script once a month */

// +----------------------------------------------------------------------+
// | update_maxmind_dbfile.php
// | script to get latest Maxmind GeoLiteGeoip data and unzip
// |     and notify success or failure by email                                         |
// |
// | associated article: http://wptest.means.us.com
// | you will need add this script to your server's "scheduler" e.g. create a cron job
// +----------------------------------------------------------------------+
// | Copyright (c) 2013
// | Andy Wrigley 
// | http:// 
// | 
// | This program is free software: you can redistribute it and/or modify
// | it under the terms of the GNU General Public License as published by
// | the Free Software Foundation, either version 3 of the License, or
// | (at your option) any later version.
// | This program is distributed in the hope that it will be useful,
// | but WITHOUT ANY WARRANTY; without even the implied warranty of
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// | GNU General Public License for more details.
// |
// | You should have received a copy of the GNU General Public License
// | along with this program.  If not, see <http://www.gnu.org/licenses/>.
// +----------------------------------------------------------------------+

if ( ! defined( 'ABSPATH' ) ) exit;


function cca_3weekly( $schedules ) {
  $schedules['cca_3weekly'] = array( 'interval' => 3*604800, 'display' => __('Three Weeks') );
  return $schedules;
}
add_filter( 'cron_schedules', 'cca_3weekly'); 


add_action( CCA_X_MAX_CRON,  'cca_update_maxmind' );
function cca_update_maxmind() {
	$cronjob_name = 'cca_update_maxmind';

	$ccax_options = get_option( 'ccax_options' );
	$max_download_url = 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/';
	$max_ipv6download_url = 'http://geolite.maxmind.com/download/geoip/database/';
  $ipv4_gz = 'GeoIP.dat.gz';
  $ipv4_dat = 'GeoIP.dat';
  $ipv6_gz = 'GeoIPv6.dat.gz';
  $ipv6_dat = 'GeoIPv6.dat';
  $get_ipv4_url = $max_download_url . $ipv4_gz;
  $get_ipv6_url = $max_ipv6download_url . $ipv6_gz;
  $ipv4_result = 'done';
  $ipv6_result = 'done';


	$ccax_maxmind_status = get_option( 'ccax_maxmind_status' );
	if (! $ccax_maxmind_status) $ccax_maxmind_status = array();

	$subject = __("Error: site:") . get_bloginfo('url') . __(" unable to update your Maxmind GeoIP files");
  $error_prefix = __("Maxmind files have NOT been updated:\n\n");
  $error_suffix = "\n\n" . __('email sent  by Category Country Aware plugin ') . date(DATE_RFC2822);


	if ( $ccax_options && !empty($ccax_options['cca_maxmind_dir']) ):
	    $cca_maxmind_dir = $ccax_options['cca_maxmind_dir'];

    if ( validate_file($cca_maxmind_dir) === 0 && file_exists($cca_maxmind_dir) ) :  	


// 0.7.7
$ccax_maxmind_status['script'] = $cca_maxmind_dir;
$ccax_maxmind_status['data'] = $cca_maxmind_dir;
if (! $ftest = @fopen($cca_maxmind_dir . 'testwrite.txt', 'wb')) :
  require_once(ABSPATH.'/wp-admin/includes/file.php');
  $wp_upload_dir_info = wp_upload_dir();
  $cca_maxmind_dir = $wp_upload_dir_info['basedir'] . '/';
  $ccax_maxmind_status['data'] = $cca_maxmind_dir;
endif;
update_option('ccax_maxmind_status',$ccax_maxmind_status);
// 0.7.7 end



      $uploadedFile = $cca_maxmind_dir . $ipv4_gz;
      $extractedFile =  $cca_maxmind_dir . $ipv4_dat;
	  	$error_prefix = __("Error Maxmind IPv4 data file has NOT been updated:\n\n");
      $ipv4_result = cca_update_dat_file($get_ipv4_url, $uploadedFile, $extractedFile, $error_prefix);

      $uploadedFile = $cca_maxmind_dir . $ipv6_gz;
      $extractedFile =  $cca_maxmind_dir . $ipv6_dat;
			$error_prefix = __("Error Maxmind IPv6 data file has NOT been updated:\n\n");
      $ipv6_result = cca_update_dat_file($get_ipv6_url, $uploadedFile, $extractedFile, $error_prefix);
		else:
		  $ipv4_result = $error_prefix . __(' Maxmind directory is invalid or does not exist at the stated location ( ') . esc_html($cca_maxmind_dir) . ' ).';
			$ipv6_result = '';
    endif;
  
	else :
	  if ( ! $ccax_options) :
			wp_clear_scheduled_hook( $cronjob_name );
  	  $msg = __("Site: ") . get_bloginfo('url ') . __(". Unable to update your Maxmind GeoLocation files - could not find the Category Country Aware plugin settings. Attempting to stop scheduled process executing this job so you won't see this email again.");
		else:
		  $msg = __("Site: ") . get_bloginfo('url ') . __(". Unable to update your Maxmind GeoLocation files - No Wordpress constant for the path to your Maxmind directory.");
    	$ccax_maxmind_status['last_max_update_status'] = $msg;
      $result = update_option( 'ccax_maxmind_status', $ccax_maxmind_status ); 
		endif;
    $send_to = get_bloginfo('admin_email');
    if (!empty($send_to)):
      wp_mail( $send_to, $subject, $msg . $error_suffix );
    endif;
		return;
  endif;


	$do_mail = FALSE;
	$msg = '';
	
	if ( $ipv4_result == 'done' ) : 
	  $ccax_maxmind_status['ipv4_up_date'] = time();  // format on output e.g. date('j M y He', $this->options['ipv4_up_date'])
		$msg = __("IPv4 update - success. ");
	else :
		$do_mail = TRUE;
		$msg = $ipv4_result . "\n\n";
	endif;		

	if ( $ipv6_result == 'done' ) : 
	  $ccax_maxmind_status['ipv6_up_date'] = time();  // format on output e.g. date('j M y He', $this->options['ipv4_up_date'])
		$msg .= __("IPv6 update - success. ");
	else :
		$do_mail = TRUE;
		$msg .= $ipv6_result . "\n\n";
	endif;	

	$ccax_maxmind_status['last_max_update_status'] = $msg;
  $result = update_option( 'ccax_maxmind_status', $ccax_maxmind_status );  

	if ( $do_mail):
    @wp_mail( get_bloginfo('admin_email'), $subject, $msg . $error_suffix );
  endif;

}


function cca_update_dat_file($file_to_upload, $uploadedFile, $extractedFile, $error_prefix) {
  // open file on server for overwrite by CURL
  if (! $fh = fopen($uploadedFile, 'wb')) :
 		return $error_prefix . __("Failed to fopen ") . $uploadedFile . __(" for writing: ") .  implode(' | ',error_get_last());
  endif;
  // Get the "file" from Maxmind
  $ch = curl_init($file_to_upload);
  curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);  // identify as error if http status code >= 400
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_USERAGENT, 'a UA string'); // some servers require a non empty or specific UA
  if( !curl_setopt($ch, CURLOPT_FILE, $fh) ) return __('FAIL!!! ABORTED - curl_setopt(CURLOPT_FILE) fail: ') . $uploadedFile;
  curl_exec($ch);
  if(curl_errno($ch)|| curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200 ) :
  	fclose($fh);
    $function_result = __('File transfer (CURL) error: ') . curl_error($ch) . __(' for ') . $file_to_upload . ' (HTTP status ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ')';
    curl_close($ch);
  	return $error_prefix . $function_result;
  endif;
  curl_close($ch);
  fflush($fh);
  fclose($fh);

  if(filesize($uploadedFile) < 1) :
	  $error_prefix .= __("CURL file transfer completed but we have an empty or non-existent file to uncompress. Your current data file has NOT been updated.\n\n");
		return  $error_prefix . __("Problem file path = ") . $uploadedFile;
  endif;
  $function_result = cca_gzExtractMax($uploadedFile, $extractedFile);
	if ($function_result != 'done')  return $error_prefix . $function_result;
  // END (update appears to have been successful)
  @unlink($uploadedFile . '.bak.old'); // delete the old back-ups
  @unlink($extractedFile . '.bak.old');
  return 'done';
}

// FUNCTIONS
//==========

// used to create a copy of the file (in same dir) before it is updated (replaces previous back-up)

function cca_backupMaxFile($fileToBackup) {
  if (! file_exists($fileToBackup) || copy($fileToBackup, $fileToBackup . '.bak') ) return 'done';
  return __('ABORTED - failed to back-up ') . $fileToBackup . __(' before replacing Maxmind file: ') .  implode(' | ',error_get_last()) . __("\nYour existing data file has not been changed.");
}

// extract file from gzip
function cca_gzExtractMax($uploadedFile, $extractedFile) {
  $buffer_size = 4096; // memory friendly bytes read at a time - good for most servers

  $fhIn = gzopen($uploadedFile, 'rb');
  if (is_resource($fhIn)) {
    $function_result = cca_backupMaxFile($extractedFile);
		if ($function_result != 'done') return $function_result;
    $fhOut = fopen($extractedFile, 'wb');
    $writeSucceeded = TRUE;
    while(!gzeof($fhIn)) :
     $writeSucceeded = fwrite($fhOut, gzread($fhIn, $buffer_size)); // write from buffer
     if ($writeSucceeded === FALSE) break;
    endwhile;
    @fclose($fhOut);
    if ( ! $writeSucceeded ) {
			$function_result = __('Error writing the new Maxmind data file ') . $extractedFile . __('Last reported error: ') .  implode(' | ',error_get_last()) . "\n\n";
		  $recoveryStatus = revertToPrevious($extractedFile);
      return $function_result  . __(" Attempting to recover previous data file: ") . $recoveryStatus;
		}
  } else { return __('Unable to extract the data file from the Maxmind gzip file: ( ') . $uploadedFile . __(" )\n\nYour existing data file has not been changed.");  }
  gzclose($fhIn);

  if(filesize($extractedFile) < 1) {
	  $recoveryStatus = revertToPrevious($extractedFile);
	  return __('The new uncompressed data file appears to be empty. Trying to revert to old version: ') . $recoveryStatus;
	}
	return 'done';
}

function revertToPrevious($fileToRollBack){
  $theBackup = $fileToRollBack . '.bak';
  if (! file_exists($currentBackup) || ! copy($theBackup, $fileToRollBack) ) return __(" NOTE: unable to revert to a previous version of ") . $fileToRollBack . '. ';
  return __(' It looks like we were able to revert to your old file.');
}