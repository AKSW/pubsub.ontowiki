<?php
require_once 'OntoWiki/Plugin.php';

class PubsubPlugin extends OntoWiki_Plugin
{
//    public function __construct($root, $config = null){
//        parent::__construct($root, $config);
//        $this->_log('construct pubsubplugin');
//    }
    
    /*
     * This method loads a huburl from the configuration. If no huburl is avaiable,
     * the hub from the pubsub-extension is used.
     */
    private function _getHubUrl(){
        if(isset($this->_privateConfig->hubUrl) && $this->_privateConfig->hubUrl !== null && $this->_privateConfig->hubUrl !== '')
            return $this->_privateConfig->hubUrl;
        else
            return OntoWiki::getInstance()->getUrlBase().'pubsub/hubbub/';
    }
    
    /*
     * This method is triggerd when a local feed is created.
     * It adds the locally saved huburl to the feed.
     */
    public function onCreateInternalFeed($event){
        $event->feed->addHub($this->_getHubUrl());
    }
    
    public function onInternalFeedDidChange($event)
    {
        $feedUrl = $event->feedUrl;
        $this->_notify($feedUrl);
    }
    
    private function _notify($feedUrl)
    {
        // Only notify if hub is set.
        $hubUrl = $this->_getHubUrl();
        if (null == $hubUrl) {
            $this->_log('No hub configured');
            return;
        }
        $this->_log('Using hub: ' . $hubUrl);
        
        // Only notify for resources that are owned by the ow instance.
        $owApp = OntoWiki::getInstance();
        $urlBase = $owApp->getUrlBase();
        if (!(substr($feedUrl, 0, strlen($urlBase)) === $urlBase)) {
            $this->_log('Pubsub notifications only supported for internal resources');
            return;
        }
        
        $this->_log('Will notify hub for feed: ' . $feedUrl);
        
        // Execute the notification
        require_once "lib/publisher.php";
        try {
            
            ob_start();
            $p = new Publisher($hubUrl);
            $success = $p->publish_update($feedUrl);
            $result = ob_get_clean();
            
            if ($success) {
                $this->_log('Successfully notified hubs (' . $hubUrl. ') for topic (' . $feedUrl . ').' . $result);
            } else {
                $this->_log('Failed to notify hubs (' . $hubUrl . ') for topics (' . $feedUrl . '): ' . $result);
            }
        } catch (Exception $e) {
            $this->_log('Exception: ' . $e->getMessage());
        }
    }
    
    private function _log($msg)
    {
        $logger = OntoWiki::getInstance()->getCustomLogger('pubsub');
        $logger->debug($msg);        
    }
}
