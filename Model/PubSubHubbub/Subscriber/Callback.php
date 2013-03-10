<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

 /**
 * OntoWiki Pubsub PubSubHubbub Subscriber Callback
 *
 * Handle the callback requests from the hub. Verify the sun/subsrcibing process and handle the
 * feed updates
 *
 * @category   OntoWiki
 * @package    Extensions_Pubsub_PubSubHubbub_Subscriber
 * @author     Konrad Abicht, Lars Eidam
 * @copyright  Copyright (c) 2006-2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class PubSubHubbub_Subscriber_Callback
    extends Zend_Feed_Pubsubhubbub_Subscriber_Callback
{
    /**
     * Handle any callback from a Hub Server responding to a subscription or
     * unsubscription request. This should be the Hub Server confirming the
     * the request prior to taking action on it.
     *
     * @param  array $httpGetData GET data if available and not in $_GET
     * @param  bool $sendResponseNow Whether to send response now or when asked
     * @return void
     */
    public function handle(array $httpGetData = null, $sendResponseNow = false)
    {
        if ($httpGetData === null) {
            $httpGetData = Zend_Controller_Front::getInstance()->getRequest();
        }

        /**
         * Handle any feed updates (sorry for the mess :P)
         *
         * This DOES NOT attempt to process a feed update. Feed updates
         * SHOULD be validated/processed by an asynchronous process so as
         * to avoid holding up responses to the Hub.
         */
        $contentType = $this->_getHeader('Content-Type');
        if (strtolower($_SERVER['REQUEST_METHOD']) == 'post'
            && $this->_hasValidVerifyToken(null, false)
            && (stripos($contentType, 'application/atom+xml') === 0
            || stripos($contentType, 'application/rss+xml') === 0
            || stripos($contentType, 'application/xml') === 0
            || stripos($contentType, 'text/xml') === 0
            || stripos($contentType, 'application/rdf+xml') === 0)
        ) {
            $this->setFeedUpdate($this->_getRawBody());
            $this->getHttpResponse()
                ->setHeader('X-Hub-On-Behalf-Of', $this->getSubscriberCount());
        /**
         * Handle any (un)subscribe confirmation requests
         */
        } elseif ($this->isValidHubVerification($httpGetData)) {
            $this->getHttpResponse()->setBody($httpGetData['hub_challenge']);

            if ($httpGetData['hub_mode'] == 'subscribe') {
                $data = $this->_currentSubscriptionData;
                $data['subscription_state'] = Zend_Feed_Pubsubhubbub::SUBSCRIPTION_VERIFIED;
                if (isset($httpGetData['hub_lease_seconds'])) {
                    $data['lease_seconds'] = $httpGetData['hub_lease_seconds'];
                }
                $this->getStorage()->setSubscription($data);
            } else {
                $verifyTokenKey = $this->_detectVerifyTokenKey($httpGetData);
                $this->getStorage()->deleteSubscription($verifyTokenKey);
            }
        /**
         * Response to any other request
         */
        } else {
            $this->getHttpResponse()->setHttpResponseCode(404);
        }
        if ($sendResponseNow) {
            $this->sendResponse();
        }
    }
}
