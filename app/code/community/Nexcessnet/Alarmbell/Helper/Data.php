<?php

/**
 * Nexcess.net AlarmBell Extension for Magento
 * Copyright (C) 2015  Nexcess.net L.L.C.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class Nexcessnet_Alarmbell_Helper_Data extends Mage_Core_Helper_Abstract {

    // URL for the API call
    static protected $geoUrl          = 'http://freegeoip.net/json/%s';
    // CURL timeout in seconds
    static protected $curlTimeout     = 10;

	/**
     * Formats and logs a message through Magento's logging facility
     *
     * @param  string $message
     */
    function log($message) {
    	// get the client IP
		$remoteIp = Mage::helper('core/http')->getRemoteAddr();

		// get user's admin username (if logged-in)
		$adminUsername = '';
		$admin = Mage::getSingleton('admin/session')->getUser();
        if (is_object($admin)) { 
            if ($admin->getId()) { $adminUsername = $admin->getUsername(); }
        }
        else { $adminusername = ''; }

        // construct log message, and log it
        $country = self::getGeoip($remoteIp);

        $logMessage = 'ALARMBELL ('. $remoteIp . ' - ' . htmlspecialchars($country) . ') ';
        if (!empty($adminUsername)) { $logMessage .= " [$adminUsername]"; }
        $logMessage .= ': ' . $message;
        Mage::log($logMessage);

        return $logMessage;
    }


	/**
     * Sends an email via Zend framework - bypassing Magento's cron
     * to avoid mail delays, problems with cron, etc
     *
     * @param string $message
     * @param string $subject 
     */
    function email($message, $subject = '', $emailAddress) { 
        if (empty($subject)) { $subject = $message; }

    	// check for blank/invalid email
		$fromEmailAddress = Mage::getStoreConfig('alarmbell_options/admin_user_monitoring/alarmbell_admin_user_email_from');

        if (!empty($emailAddress)){ 
        	$validator = new Zend_Validate_EmailAddress();
	        if ($validator->isValid($emailAddress)) { // check for valid 'to' email address
	        	$emailSubjectPrefix = Mage::getStoreConfig('alarmbell_options/admin_user_monitoring/alarmbell_admin_user_email_subject');
	        	
	        	if ((empty($fromEmailAddress)) || (!$validator->isValid($fromEmailAddress))) { 
		        	// use current admin user's email address for 'from' address
		        	$fromEmailAddress = Mage::getSingleton('admin/session')->getUser()->getEmail();
		        }

			try {
				// send it
				$mail = new Zend_Mail();
				$mail->setBodyText($message)
					->setFrom($fromEmailAddress)
					->addTo($emailAddress)
					->setSubject($emailSubjectPrefix . ' ' . $subject)
					->send();
				return true;
			} catch (Exception $e) {
				Mage::Log('ALARMBELL' . $e->getMessage());
				return false;
			}
		}
	}
    return false;
    }


   /**
    * Send Slack request
    *
    * @return null
    */
    public function sendSlack($message, $logMessage)
    {
        $helper = Mage::helper('alarmbell/api');
        try {
            $icon = $data['icon_url'] = Mage::getBaseUrl('media') . 'nexcess/alarmbell' . Mage::getStoreConfig('alarmbell_options/general/icon');
            $result = $helper->sendSlack(array('title' => $message, 'text'=>$logMessage, 'icon_url' => $data['icon_url'] = $icon));
            Mage::Log($result);
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
    }



   /**
    * Make the cURL call to the FreeGeoIP.net API
    *
    * @return country code
    */
   public function getGeoip($ip = null)
   {
      // Construct the URL for the call
      $curlURL = sprintf(self::$geoUrl,$ip);
	
      // Init curl
      $ch = curl_init();
      // Set the options for curl
      curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);                  // Return the actual result
      curl_setopt($ch,CURLOPT_URL,$curlURL);                      // Use the URL constructed previously
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,self::$curlTimeout); // Set the timeout so we don't take forever to load the page
      $data = curl_exec($ch);                                     // Execute the call
      curl_close($ch);
      // The call returns JSON, convert it to a stdClass object
      $geo = json_decode($data);
      if($geo){
          return $geo->country_code;
      } else {
          return "Geoip Timeout";
      }

    }

}
