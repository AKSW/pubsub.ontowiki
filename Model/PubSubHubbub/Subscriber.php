<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

 /**
 * OntoWiki Pubsub PubSubHubbub Subscriber
 *
 * Handle the subscribing process and extend the Zend Feed Pubsubhubbub Subscriber
 *
 * @category   OntoWiki
 * @package    Extensions_Pubsub_PubSubHubbub
 * @author     Konrad Abicht, Lars Eidam
 * @copyright  Copyright (c) 2006-2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class PubSubHubbub_Subscriber
    extends Zend_Feed_Pubsubhubbub_Subscriber
{
    /**
     * Return a list of standard protocol/optional parameters for addition to
     * client's POST body that are specific to the current Hub Server URL
     *
     * @param  string $hubUrl hub URL
     * @param  mode   $mode   subscribe or unsubscribe
     * @return string
     */
    protected function _getRequestParameters($hubUrl, $mode)
    {
        if (!in_array($mode, array('subscribe', 'unsubscribe'))) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception(
                'Invalid mode specified: "' .
                $mode .
                '" which should have been "subscribe" or "unsubscribe"'
            );
        }

        $params = array(
            'hub.mode'  => $mode,
            'hub.topic' => $this->getTopicUrl(),
        );

        if ($this->getPreferredVerificationMode() == Zend_Feed_Pubsubhubbub::VERIFICATION_MODE_SYNC) {
            $vmodes = array(
                Zend_Feed_Pubsubhubbub::VERIFICATION_MODE_SYNC,
                Zend_Feed_Pubsubhubbub::VERIFICATION_MODE_ASYNC,
            );
        } else {
            $vmodes = array(
                Zend_Feed_Pubsubhubbub::VERIFICATION_MODE_ASYNC,
                Zend_Feed_Pubsubhubbub::VERIFICATION_MODE_SYNC,
            );
        }
        $params['hub.verify'] = array();
        foreach ($vmodes as $vmode) {
            $params['hub.verify'][] = $vmode;
        }

        /**
         * Establish a persistent verify_token and attach key to callback
         * URL's path/querystring
         */
        $key   = $this->_generateSubscriptionKey($params, $hubUrl);
        $token = $this->_generateVerifyToken();
        $params['hub.verify_token'] = $token;

        // Note: query string only usable with PuSH 0.2 Hubs
        if (!$this->_usePathParameter) {
            $params['hub.callback'] = $this->getCallbackUrl()
                . '?xhub.subscription=' . Zend_Feed_Pubsubhubbub::urlencode($key);
        } else {
            $params['hub.callback'] = rtrim($this->getCallbackUrl(), '/')
                . '/' . Zend_Feed_Pubsubhubbub::urlencode($key);
        }
        if ($mode == 'subscribe' && $this->getLeaseSeconds() !== null) {
            $params['hub.lease_seconds'] = $this->getLeaseSeconds();
        }

        // hub.secret not currently supported
        $optParams = $this->getParameters();
        foreach ($optParams as $name => $value) {
            $params[$name] = $value;
        }

        // store subscription to storage
        $now = new Zend_Date;
        $expires = null;
        if (isset($params['hub.lease_seconds'])) {
            $expires = $now->add($params['hub.lease_seconds'], Zend_Date::SECOND)
                ->get('yyyy-MM-dd HH:mm:ss');
        }
        $data = array(
            'id'                 => $key,
            'topic_url'          => $params['hub.topic'],
            'hub_url'            => $hubUrl,
            'created_time'       => $now->get('yyyy-MM-dd HH:mm:ss'),
            'lease_seconds'      => $expires,
            'verify_token'       => hash('sha256', $params['hub.verify_token']),
            'secret'             => null,
            'expiration_time'    => $expires,
            'subscription_state' => ($mode == 'unsubscribe')?
                                    Zend_Feed_Pubsubhubbub::SUBSCRIPTION_TODELETE :
                                    Zend_Feed_Pubsubhubbub::SUBSCRIPTION_NOTVERIFIED,
        );
        $this->getStorage()->setSubscription($data);

        return $this->_toByteValueOrderedString(
            $this->_urlEncode($params)
        );
    }

    /**
     * Function save the resource uri to the subscription
     *
     * @param $resourceUri URI of the source resource
     * @return void
     */
    public function addSourceResourceUri($resourceUri)
    {
        $hubs = $this->getHubUrls();

        foreach ($hubs as $hubUrl) {
            $params = array(
                'hub.topic' => $this->getTopicUrl(),
            );
            $key = $this->_generateSubscriptionKey($params, $hubUrl);
            $this->_storage->addSourceResourceUri($key, $resourceUri);
        }
    }

    /**
     * Function save the subscribing user uri to the subscription
     *
     * @param $subscribingUserUri URI of the subscribing user
     * @return void
     */
    public function addSubscribingUserUri($subscribingUserUri)
    {
        $hubs = $this->getHubUrls();

        foreach ($hubs as $hubUrl) {
            $params = array(
                'hub.topic' => $this->getTopicUrl(),
            );
            $key = $this->_generateSubscriptionKey($params, $hubUrl);
            $this->_storage->addSubscribingUserUri($key, $subscribingUserUri);
        }
    }

    /**
     * Save iri of that model where the current resource is located in
     *
     * @param $modelIri URI of the model where the current resource is located in
     * @return void
     */
    public function addModelIri($modelIri)
    {
        $hubs = $this->getHubUrls();

        foreach ($hubs as $hubUrl) {
            $params = array(
                'hub.topic' => $this->getTopicUrl(),
            );
            $key = $this->_generateSubscriptionKey($params, $hubUrl);
            $this->_storage->addModelIri($key, $modelIri);
        }
    }

}
