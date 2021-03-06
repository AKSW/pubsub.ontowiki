<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki module – model overview
 *
 * Provides functions to model for instance, handle model wide feed updates
 *
 * @category   OntoWiki
 * @package    Extensions_Pubsub
 * @author     Konrad Abicht, Lars Eidam
 * @copyright  Copyright (c) 2006-2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class ModelOverviewModule extends OntoWiki_Module
{

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

        // include javascript files
        $basePath = $this->_config->staticUrlBase . 'extensions/pubsub/';
        $baseJavascriptPath = $basePath .'public/javascript/';

        $this->view->headScript()
            ->prependFile($baseJavascriptPath. 'functions.js', 'text/javascript')
            ->prependFile($baseJavascriptPath. 'modeloverview.js', 'text/javascript');
    }

    /**
     * Set module title
     *
     * @return string title of the modul
     */
    public function getTitle()
    {
        return "Feed updates";
    }

    /**
     * Decide if the modul should be shown
     *
     * @return true if the modul should shown, else false
     */
    public function shouldShow()
    {
        // stop if model is not editable
        if (false == OntoWiki::getInstance()->selectedModel->isEditable()) {
            return false;
        }

        // Check if resources from selected model have feed updates, only show
        // this module if there are feed updates.
        $subscription = new PubSubHubbub_Subscription(
            $this->_owApp->selectedModel, $this->_privateConfig->get('subscriptions')
        );

        return 0 == count(
            $subscription->getFilesForFeedUpdates(
                $this->_owApp->erfurt->getTmpDir()
            )
        ) ? false : true;
    }

    /**
     * Render the template
     */
    public function getContents()
    {
        return $this->render('pubsub/modeloverview');
    }
}
