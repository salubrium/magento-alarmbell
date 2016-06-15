<?php


class Nexcessnet_Alarmbell_Helper_Api extends Mage_Core_Helper_Abstract
{
    public function sendSlack($data)
    {
        $slackEnabled = Mage::getStoreConfig('alarmbell_options/general/slack_enable');

        if(!$slackEnabled){
            return;
        }

        $webhookUrl = Mage::getStoreConfig('alarmbell_options/general/webhook_url');

        if (!$webhookUrl) {
            throw new Exception($this->__('Slack Webhook URL is not defined'));
        }

        if (!isset($data['channel']) || !$data['channel']) {
            $data['channel'] = Mage::getStoreConfig('alarmbell_options/general/default_channel');
        }

        if (!$data['channel']) {
            throw new Exception($this->__('Slack Default Channel is not defined'));
        }

        $postData = array(
            'payload' => json_encode($data),
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec ($ch);

        curl_close ($ch);

        if (trim($server_output) == 'ok') {
            return true;
        }

        return $server_output;
    }
}
