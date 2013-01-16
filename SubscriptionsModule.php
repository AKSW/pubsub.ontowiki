<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki module – subscriptions
 *
 * Add instance properties to the list view
 *
 * @category   OntoWiki
 * @package    Extensions_pubsub
 */
class SubscriptionsModule extends OntoWiki_Module
{
    protected $_subscriptionModelInstance;
    protected $_subscriptionStorage;
    
    /**
     * Constructor
     */
    public function init()
    {
        // Zend_Loader for class autoloading
        $loader = Zend_Loader_Autoloader::getInstance();
        $loader->registerNamespace('PubSubHubbub_');

        $path = __DIR__;
		set_include_path(
            get_include_path() .
            PATH_SEPARATOR .
            $path .
            DIRECTORY_SEPARATOR .
            'Model' .
            DIRECTORY_SEPARATOR .
            PATH_SEPARATOR
        );
        // create model if it does not exist
        $subscriptionHelper = new PubSubHubbub_ModelHelper(
            $this->_privateConfig->get('subscriptions')->get('modelUri'),
            $this->_owApp->erfurt->getStore()
        );
        $this->_subscriptionModelInstance = $subscriptionHelper->addModel();
        
        $this->_subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );
    }

    public function getTitle()
    {
        return "Updates and Subscriptions";
    }

    public function shouldShow()
    {
        return true;
    }

    public function getContents()
    {
        $headerFeedTags = $this->_privateConfig->get('subscriptions')->get('headerFeedTags');
        if (is_object($headerFeedTags))
            $headerFeedTags = $headerFeedTags->toArray();
        else
            $headerFeedTags = array($headerFeedTags);
        $this->view->headerFeedTags = $headerFeedTags;
        
        $this->view->topicUrl = $this->_subscriptionStorage->getTopicByResourceUri($this->_owApp->selectedResource);
        
        $this->view->standardHubUrl = $this->_privateConfig->get('subscriptions')->get('standardHubUrl');
        $this->view->callbackUrl = $this->_privateConfig->get('subscriptions')->get('callbackUrl');

        return $this->render('pubsub/subscriptions');
    }
}


