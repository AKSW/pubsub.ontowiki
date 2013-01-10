<?php

class PubsubController extends OntoWiki_Controller_Component
{   
    public function init()
    {
        parent::init();
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
        $subscriptionHelper->addModel();
        
    }
    
    /**
     * Index Action
     */
    public function indexAction()
    {
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();
        
        
    }
    
    /**
     * Subsription Action
     */
    public function subscriptionAction()
    {
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();
        
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        
        $subscriptionStorage = new PubSubHubbub_Subscription;
        
        $hubUrl = $this->getParam('hubUrl');
        $topicUrl = $this->getParam('topicUrl');
        $callBackUrl = $this->getParam('callBackUrl');
        $subscriptionMode = $this->getParam('subscriptionMode');
        
        if ("" != $hubUrl && "" != $topicUrl && "" != $callBackUrl)
        {
            $subscriber = new Zend_Feed_Pubsubhubbub_Subscriber;
            $subscriber->setStorage($subscriptionStorage);
            $subscriber->addHubUrl($hubUrl);
            $subscriber->setTopicUrl($topicUrl);
            $subscriber->setCallbackUrl($callBackUrl);

            // check subscribtion mode
            if ("subscribe" == $subscriptionMode)
                $subscriber->subscribeAll();
            else if ("unsubscribe" == $subscriptionMode)
                $subscriber->unsubscribeAll();
            else
            {
                echo 'FAILURE: missing parameter';
                $this->_response->setHttpResponseCode(500);
                return;
            }
            
            if ($subscriber->isSuccess() && 0 == count($subscriber->getErrors()))
                $this->_response->setBody('')
                     ->setHttpResponseCode(200);
            else {
                foreach ($subscriber->getErrors() as $error)
                {
                    $this->_response->appendBody(var_dump($error));
                }
                $this->_response->setHttpResponseCode(404);
                
            }
        } else {
            echo 'FAILURE: wrong Urls';
        }
    }
    
}
