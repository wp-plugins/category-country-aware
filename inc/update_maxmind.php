<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (! function_exists('cca_3weekly') ):
  function cca_3weekly( $schedules ) {
    $schedules['cca_3weekly'] = array( 'interval' => 3*604800, 'display' => __('Three Weeks') );
    return $schedules;
  }
  add_filter( 'cron_schedules', 'cca_3weekly'); 
endif;

// return permissions of a directory or file as a 4 character "octal" string
function cca_return_permissions($item) {
 clearstatcache(true, $item);
 $item_perms = @fileperms($item);
return empty($item_perms) ? '' : substr(sprintf('%o', $item_perms), -4);	
}

add_action( CCA_X_MAX_CRON,  'cca_update_maxmind' );

function cca_update_maxmind() {
   if (!defined('CCA_MAXMIND_DATA_DIR')) define('CCA_MAXMIND_DATA_DIR', WP_CONTENT_DIR . '/cca_maxmind_data/');
   $cca_max_update = new CCAmaxmindSave();
   $cca_max_update->save_maxmind(TRUE);
	 unset($cca_max_update);
}

class CCAmaxmindSave {
  protected $max_status = array();
  public function __construct() {
		$this->max_status = get_option('cc_maxmind_status', array());
 }

public function save_maxmind($do_email = FALSE) {
  	// initialize
	 $max_ipv4download_url = 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz';
	 $max_ipv6download_url = 'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz';
   $ipv4_gz = 'GeoIP.dat.gz';
   $ipv4_dat = 'GeoIP.dat';
   $cca_ipv4_file = CCA_MAXMIND_DATA_DIR . $ipv4_dat;
   $ipv6_gz = 'GeoIPv6.dat.gz';
   $ipv6_dat = 'GeoIPv6.dat';
   $cca_ipv6_file = CCA_MAXMIND_DATA_DIR . $ipv6_dat;
   $error_prefix = __("Error: Maxmind files are NOT installed. ");

	 if(empty( $this->max_status['ipv4_file_date'])) :  $this->max_status['ipv4_file_date'] = 0; endif;
	 if(empty( $this->max_status['ipv6_file_date']) ):  $this->max_status['ipv6_file_date'] = 0; endif;
	 $original_health =  $this->max_status['health'] = empty( $this->max_status['health']) ? 'ok' :  $this->max_status['health'];
	 $files_written_ok = FALSE;

   clearstatcache();

	 if (file_exists($cca_ipv4_file) && file_exists($cca_ipv6_file) && filesize($cca_ipv4_file) > 131072 && filesize($cca_ipv6_file) > 131072) :
     // return if an install/update is not necessary (another plugin may have recently done an update)
     if ($original_health == 'ok' && ! empty( $this->max_status['ipv4_file_date']) &&  $this->max_status['ipv4_file_date'] > (time() - 3600 * 24 * 10) ): return TRUE; endif;
		 $original_health = 'ok';
  else:
	   $original_health = 'fail';
	endif;

	// re-initialize status msg
	 $this->max_status['result_msg'] = '';
   $this->max_status['health'] = 'ok';
	 
	// create Maxmind directory if necessary
  if ( validate_file(CCA_MAXMIND_DATA_DIR) != 0 ) :  	// 0 means a valid format for a directory path
	    $this->max_status['health'] = 'fail';
	    $this->max_status['result_msg'] = $error_prefix . __('Constant CCA_MAXMIND_DATA_DIR contains an invalid value: "') . esc_html(CCA_MAXMIND_DATA_DIR) . '"';
	elseif ( ! file_exists(CCA_MAXMIND_DATA_DIR) ): 
	    // then this is the first download, or a new directory location has been defined
      $item_perms = cca_return_permissions(dirname(__FILE__));  // determine required folder permissions (e.g. for shared or dedicated server)
      if (strlen($item_perms) == 4 && substr($item_perms, 2, 1) == '7') :
          $cca_perms = 0775;
      else:
          $cca_perms = 0755;
      endif;							
  		if ( ! @mkdir(CCA_MAXMIND_DATA_DIR, $cca_perms, true) ): 
			     $this->max_status['health'] = 'fail';
				   $this->max_status['result_msg'] = $error_prefix . __('Unable to create directory "') . CCA_MAXMIND_DATA_DIR . __('" This may be due to your server permission settings. See the Country Caching "Support" tab for more information.');
		  endif;
	endif;

	// if the Maxmind directory exists
	if ( $this->max_status['health'] == 'ok') :

    	// get and write the IPv4 Maxmind data
      if ($original_health == 'fail') : 
         $error_prefix = __("Error; unable to install the Maxmind IPv4 data file:\n");
      else:
    		 $error_prefix = __("Warning; unable to update the Maxmind IPv4 data file:\n");
    	endif;
      $ipv4_result = $this->update_dat_file($max_ipv4download_url, $ipv4_gz, $ipv4_dat, $error_prefix);
	    $temp_health =  $this->max_status['health']; 
			if ($ipv4_result == 'done') :
			  $ipv4_result =  'IPv4 file updated successfully.';
				$files_written_ok = TRUE;
				 $this->max_status['ipv4_file_date'] = time(); 
			endif;
 
       // get and write the IPv6 Maxmind data
      if ($original_health == 'fail') : 
         $error_prefix = __("Error; unable to install the Maxmind IPv6 data file:\n");
      else:
    		 $error_prefix = __("Warning; unable to update the Maxmind IPv6 data file:\n");
    	endif;
      $ipv6_result = $this->update_dat_file($max_ipv6download_url, $ipv6_gz, $ipv6_dat, $error_prefix);
 			if ($ipv6_result == 'done') :
			  $ipv6_result =  'IPv6 file updated successfully.';
				 $this->max_status['ipv6_file_date'] = time(); 
			else:
			  $files_written_ok = FALSE;  // overrides TRUE set by IPv4 success
			endif;
			 
			// ensure health status is set to the most critical of IP4 & IP6 file updates
			if ($temp_health == 'fail' ||  $this->max_status['health'] == 'fail'):
			    $this->max_status['health'] = 'fail';
			elseif ($temp_health == 'warn' ||  $this->max_status['health'] == 'warn') :
			    $this->max_status['health'] = 'warn';
			endif;

			 $this->max_status['result_msg'] = $ipv4_result . "<br />\n" . $ipv6_result;

  endif;

	if ( $this->max_status['health'] == 'warn' && $original_health == 'fail') :  $this->max_status['health'] = 'fail'; endif;
  if ( $this->max_status['health'] == 'ok') :  $this->max_status['result_msg'] .= __(" The last update was successful"); endif;
  update_option( 'cc_maxmind_status',  $this->max_status );
 
 
  // this function was called on plugin update the user might not open the settings form, so we'll report errors by email
  if ($do_email  &&  $this->max_status['health'] == 'fail'):
     $subject = __("Error: site:") . get_bloginfo('url') . __(" unable to install Maxmind GeoIP files");
     $msg = str_replace('<br />', '' ,  $this->max_status['result_msg']) . "\n\n" . __('Email sent by the Country Caching plugin ') . date(DATE_RFC2822);	
	  @wp_mail( get_bloginfo('admin_email'), $subject, $msg );
  endif;

return $files_written_ok;



}  // END save_maxmind_data() 


//  This method retreives the "zips" from Maxmind and then calls other methods to do the rest of the work
protected function update_dat_file($file_to_upload, $zip_name, $extracted_name, $error_prefix) {

	$uploadedFile = CCA_MAXMIND_DATA_DIR . $zip_name;
	$extractedFile = CCA_MAXMIND_DATA_DIR . $extracted_name;

  // open file on server for overwrite by CURL
  if (! $fh = fopen($uploadedFile, 'wb')) :
		  $this->max_status['health'] = 'warn';
		 return $error_prefix . __("Failed to fopen ") . $uploadedFile . __(" for writing: ") .  implode(' | ',error_get_last()) . "\n<br />";
  endif;
  // Get the "file" from Maxmind
  $ch = curl_init($file_to_upload);
  curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);  // identify as error if http status code >= 400
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_USERAGENT, 'a UA string'); // some servers require a non empty or specific UA
  if( !curl_setopt($ch, CURLOPT_FILE, $fh) ):
		  $this->max_status['health'] = 'warn';
		 return $error_prefix . __('curl_setopt(CURLOPT_FILE) fail for: "') . $uploadedFile . '"<br /><br />' . "\n\n";
	endif;
  curl_exec($ch);
  if(curl_errno($ch) || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200 ) :
  	fclose($fh);
		$curl_err = $error_prefix . __('File transfer (CURL) error: ') . curl_error($ch) . __(' for ') . $file_to_upload . ' (HTTP status ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ')';
    curl_close($ch);
		 $this->max_status['health'] = 'warn';
		return $curl_err;
  endif;
  curl_close($ch);
  fflush($fh);
  fclose($fh);

  if(filesize($uploadedFile) < 1) :
		 $this->max_status['health'] = 'warn';
		return $error_prefix . __("CURL file transfer completed but we have an empty or non-existent file to uncompress. (") . $uploadedFile . ').<br /><br />' . "\n\n";
  endif;


	$function_result = $this->gzExtractMax($uploadedFile, $extractedFile);

	if ($function_result != 'done'):  return $error_prefix . $function_result; endif;
    //  update appears to have been successful
    $this->max_status['health'] = 'ok';
    return 'done';
  }  // END  update_dat_file()


// extract file from gzip and write to folder
protected function gzExtractMax($uploadedFile, $extractedFile) {

  $buffer_size = 4096; // memory friendly bytes read at a time - good for most servers

  $fhIn = gzopen($uploadedFile, 'rb');
  if (is_resource($fhIn)) {
    $function_result = $this->backupMaxFile($extractedFile);
		if ($function_result != 'done' ) {
			 $this->max_status['health'] = 'warn';
			return $function_result;
    }
		$fhOut = fopen($extractedFile, 'wb');
    $writeSucceeded = TRUE;
    while(!gzeof($fhIn)) :
       $writeSucceeded = fwrite($fhOut, gzread($fhIn, $buffer_size)); // write from buffer
       if ($writeSucceeded === FALSE) break;
    endwhile;
    @fclose($fhOut);

    if ( ! $writeSucceeded ) {
			 $this->max_status['health'] = 'fail';
			$function_result = __('Error writing "') .  $extractedFile . '"<br />' ."\n" . __('Last reported error: ') .  implode(' | ',error_get_last());
			$function_result .= "<br />\n" . $this->revertToOld($extractedFile);
			return $function_result;
		}

  } else { 
	     $this->max_status['health'] = 'warn';
		  $function_result = __('Unable to extract the file from the Maxmind gzip: ( ') . $uploadedFile . ")<br />\n" . __("Your existing data file has not been changed.");
		  return $function_result;
		}
  gzclose($fhIn);

  clearstatcache();
  if(filesize($extractedFile) < 1) {
	   $this->max_status['health'] = 'fail';
	  $recoveryStatus = $this->revertToOld($extractedFile);
		$function_result = __('Failed to create a valid data file - it appears to be empty. Trying to revert to old version: ') . $recoveryStatus;
		return $function_result;
	}

  $this->max_status['health'] = 'ok';
  return 'done';
}


// used to create a copy of the file (in same dir) before it is updated (replaces previous back-up)
protected function backupMaxFile($fileToBackup) {
  if (! file_exists($fileToBackup) || @copy($fileToBackup, $fileToBackup . '.bak') ) return 'done';
  return __('ABORTED - failed to back-up ') . $fileToBackup . __(' before replacing Maxmind file: ') .  implode(' | ',error_get_last()) . "\n" . __("<br />Your existing data file has not been changed.");
}


protected function revertToOld($fileToRollBack){
  $theBackup = $fileToRollBack . '.bak';
  if (! file_exists($theBackup) || filesize($theBackup) < 131072 || ! @copy($theBackup, $fileToRollBack) ) return __("NOTE: unable to revert to a previous version of ") . $fileToRollBack . ".<br />\n\n";
   $this->max_status['health'] = 'warn';
  return __('It looks like we were able to revert to an old copy of the file.<br />');
}

}  // end class

