<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki module – model overview
 *
 * Provides functions to model for instance, handle model wide feed updates
 *
 * @category   OntoWiki
 * @package    Extensions_pubsub
 */
class ModelOverviewModule extends OntoWiki_Module
{
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
    }

    public function getTitle()
    {
        return "Feed updates";
    }

    public function shouldShow()
    {
        return $this->hasModelFeedUpdates();
    }

    public function getContents()
    {
        return $this->render('pubsub/modeloverview');
    }
    
    /**
     *
     */
    public function hasModelFeedUpdates() 
    {
        $files = scandir($this->_owApp->erfurt->getCacheDir());
        $result = array ();
        $subscription = new PubSubHubbub_Subscription(
            new Erfurt_Rdf_Model($this->_privateConfig->get('subscriptions')->get('modelUri')),
            $this->_privateConfig->get('subscriptions')
        );
        
        foreach ($files as $cacheFile) {
            // if current file is a pubsub feed update file
            if('pubsub_' === substr($cacheFile, 0, 7)) {
               
               // extract hash from filename
               $subscriptionHash = substr($cacheFile, 7, 32);
               
               $sourceResource = $subscription->getSourceResource($subscriptionHash);
               
               // there are feed updates for at least one resource in this model
               if (true === $this->existsResourceInModel($sourceResource)) {
                   return true;
               }
            }
        }
        
        return false;
    }
    
    /**
     *
     */
    public function existsResourceInModel($sourceResource) 
    {
        return 0 == count($this->_owApp->selectedModel->sparqlQuery(
            'SELECT ?o WHERE { <'. $sourceResource .'> ?p ?o. } LIMIT 1;')
        ) ? false : true;
    }
}


