<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki module – subscriptions
 *
 * Add instance properties to the list view
 *
 * @category   OntoWiki
 * @package    Extensions_Pubsub
 * @author     Konrad Abicht, Lars Eidam
 * @copyright  Copyright (c) 2006-2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class SubscriptionsModule extends OntoWiki_Module
{
    protected $_subscriptionModelInstance;
    protected $_subscriptionStorage;
    protected $_headerFeedTags;

    /**
     * Constructor
     *
     * Setup all needed parameter
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

        // get header feed tags from config
        $headerFeedTags = $this->_privateConfig->get('subscriptions')->get('headerFeedTags');

        // handle multiple header tags
        if (is_object($headerFeedTags)) {
            $headerFeedTags = $headerFeedTags->toArray();
        } else {
            $headerFeedTags = array($headerFeedTags);
        }
        $this->_headerFeedTags = $headerFeedTags;

        // create model if it does not exist
        $modelHelper = new PubSubHubbub_ModelHelper(
            $this->_privateConfig->get('subscriptions')->get('modelUri'),
            $this->_owApp->erfurt->getStore()
        );
        $this->_subscriptionModelInstance = $modelHelper->addModel();

        // get the subscription storage
        $this->_subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );

        // include javascript files
        $basePath = $this->_config->staticUrlBase . 'extensions/pubsub/';
        $baseJavascriptPath = $basePath .'public/javascript/';

        $this->view->headScript()
            ->prependFile($baseJavascriptPath. 'functions.js', 'text/javascript')
            ->prependFile($baseJavascriptPath. 'subscriptions.js', 'text/javascript');
    }

    /**
     * Set module title
     *
     * @return string title of the modul
     */
    public function getTitle()
    {
        return "Updates and Subscriptions";
    }

    /**
     * Decide if the modul should be shown
     *
     * @return true if the modul should shown, else false
     */
    public function shouldShow()
    {
        // stop if model is not editable
        return OntoWiki::getInstance()->selectedModel->isEditable();
    }

    /**
     * Render the template
     */
    public function getContents()
    {
        $this->view->selectedResourceUri = $this->_owApp->selectedResource->getUri();
        $this->view->topicUrl = $this->_subscriptionStorage->getTopicByResourceUri(
            $this->_owApp->selectedResource->getUri()
        );
        $this->view->headerFeedTags = $this->_headerFeedTags;
        $this->view->standardHubUrl = $this->_privateConfig->get('subscriptions')->get('standardHubUrl');

        $this->view->callbackUrl = trim($this->_privateConfig->get('subscriptions')->get('callbackUrl'));

        // if callbackUrl is empty string, use the baseUrl + path to pubsub
        if ('' == $this->view->callbackUrl) {
            $this->view->callbackUrl = $this->_config->staticUrlBase . 'pubsub/callback';
        }

        $this->view->standardPublishHubUrl = $this->_privateConfig->get('publish')->get('standardHubUrl');

        return $this->render('pubsub/subscriptions');
    }
}


