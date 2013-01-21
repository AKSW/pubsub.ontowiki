<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

class PubsubController extends OntoWiki_Controller_Component
{
    protected $_subscriptionModelInstance;

    public function init()
    {
        parent::init();

        //ToDo: Quickfix because of Error:
        //Use of undefined constant OW_SHOW_MAX - assumed 'OW_SHOW_MAX'
        if (!defined('OW_SHOW_MAX'))
            define('OW_SHOW_MAX', 5);

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

        $subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );

        $hubUrl = $this->getParam('hubUrl');
        $topicUrl = $this->getParam('topicUrl');
        $callBackUrl = $this->getParam('callBackUrl');
        $subscriptionMode = $this->getParam('subscriptionMode');
        $verifyMode = $this->getParam('verifyMode');
        $sourceResource = $this->getParam('sourceResource');

        if ("" != $hubUrl && "" != $topicUrl && "" != $callBackUrl) {
            $subscriber = new PubSubHubbub_Subscriber;
            $subscriber->setStorage($subscriptionStorage);
            $subscriber->addHubUrl($hubUrl);
            $subscriber->setTopicUrl($topicUrl);
            $subscriber->setCallbackUrl($callBackUrl);
            $subscriber->setPreferredVerificationMode($verifyMode);

            // check subscribtion mode
            if ("subscribe" == $subscriptionMode) {
                $subscriber->subscribeAll();
                if ("" != $sourceResource)
                    $subscriber->addSourceResourceUri($sourceResource);
            } else if ("unsubscribe" == $subscriptionMode)
                $subscriber->unsubscribeAll();
            else {
                echo 'FAILURE: missing parameter';
                $this->_response->setHttpResponseCode(500);
                return;
            }

            if ($subscriber->isSuccess() && 0 == count($subscriber->getErrors()))
                $this->_response->setBody('')
                     ->setHttpResponseCode(200);
            else {
                foreach ($subscriber->getErrors() as $error) {
                    $this->_response->appendBody($error);
                }
                $this->_response->setHttpResponseCode(404);

            }
        } else {
            echo 'FAILURE: wrong Urls';
        }
    }

    /**
     * Callback Action
     */
    public function callbackAction()
    {
        if ($this->_request->isPost())
            $_SERVER['REQUEST_METHOD'] = 'post';
        else
            $_SERVER['REQUEST_METHOD'] = 'get';

        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();

        $subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );
        $callback = new PubSubHubbub_Subscriber_Callback;
        $callback->setStorage($subscriptionStorage);

        $callback->handle($this->_request->getParams());

        $callback->sendResponse();
    }
    
    /*
     * check if a uri is LinkedData
     */
    
    public function islinkeddataAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();
        
        $resourceUri = $this->getParam('r', '');
        $result = false;
        
        if ("" != $resourceUri)
        {
            $resource = new Erfurt_Rdf_Resource($resourceUri);
            // check for LinkedData
            $wrapper = new Erfurt_Wrapper_LinkeddataWrapper();
            try {
                $result = $wrapper->isAvailable($resource, '');
            }
            catch (Exception $e){
                $this->_response->appendBody(json_encode($e->getMessage()));
                $this->_response->setHttpResponseCode(404);
                return;
            }
        }
        
        if ($result === true)
            $this->_response->appendBody(json_encode($result));
        else
            $this->_response->appendBody(json_encode(false));
    }
}
