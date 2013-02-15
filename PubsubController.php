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

        $subscribingUserUri = $this->_owApp->getUser()->getUri();

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
                if ("" != $subscribingUserUri)
                    $subscriber->addSubscribingUserUri($subscribingUserUri);
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

        // handle request and immediatly send response, to avoid blocking the hub
        $callback->handle($this->_request->getParams(), true);
        
        if (true === $callback->hasFeedUpdate()) {
            $filePath = $this->_owApp->erfurt->getCacheDir() .
                        "pubsub_" .
                        $this->_request->getParam('xhub_subscription') .
                        "_" .
                        time() .
                        ".xml";

            if ( false === ( $fh = fopen($filePath, 'w') ) ) {
                // can't open the file
                $m = "No write permissions for ". $filePath;
                throw new CubeViz_Exception($m);
                return $m;
            }

            // write all parameters line by line
            fwrite($fh, $callback->getFeedUpdate() . "\n");
            chmod($filePath, 0755);
            fclose($fh);

            // collect all resource properties and attach it to the event object
            $subscriptionResourceData = $subscriptionStorage->getSubscription(
                $this->_request->getParam('xhub_subscription')
            );
            $event = new Erfurt_Event('onFeedUpdate');
            $event->subscriptionResourceProperties = $subscriptionResourceData['resourceProperties'];
            $event->trigger();
        }
    }
    /**
     * Publish Action
     */
    public function publishAction()
    {
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();

        $hubUrl = $this->getParam('hubUrl');
        $topicUrl = $this->getParam('topicUrl');

        if ("" != $hubUrl && "" != $topicUrl) {
            $publisher = new Zend_Feed_Pubsubhubbub_Publisher;

            $publisher->addHubUrl($hubUrl);
            $publisher->addUpdatedTopicUrl($topicUrl);

            $publisher->notifyAll();

            if ($publisher->isSuccess() && 0 == count($publisher->getErrors())) {
                $this->_response->setBody('')
                         ->setHttpResponseCode(200);
            } else {
                foreach ($publisher->getErrors() as $error) {
                    $this->_response->appendBody($error);
                }
                $this->_response->setHttpResponseCode(404);
            }
        } else {
            echo 'FAILURE: missing parameter';
            $this->_response->setHttpResponseCode(500);
            return;
        }
    }

    /*
     * Check if an uri is LinkedData using Erfurt_Wrapper_LinkeddataWrapper
     */
    public function checkresourceAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        $resourceUri = $this->getParam('r', '');
        $result = array(
            'isLinkedData' => false,
            'isLocalResource' => false,
            'hasError' => false,
            'errorMessage' => ''
        );

        if ("" != $resourceUri) {
            if (false === strpos($resourceUri, $this->_owApp->getUrlBase())) {
                $resource = new Erfurt_Rdf_Resource($resourceUri);
                // check for LinkedData
                $wrapper = new Erfurt_Wrapper_LinkeddataWrapper();
                try {
                    $result['isLinkedData'] = $wrapper->isAvailable($resource, '');
                }
                catch (Exception $e){
                    $result['hasError'] = true;
                    $result['errorMessage'] = $e->getMessage();
                    $this->_response->setHttpResponseCode(404);
                }
            }
            else
                $result['isLocalResource'] = true;
        }

        $this->_response->appendBody(json_encode($result));
    }

    /**
     * Check if a given resource (r) has feed updates.
     */
    public function existsfeedupdatesAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        $r = $this->_request->getParam('r');

        $subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );

        $subscriptionId = $subscriptionStorage->getSubscriptionIdByResourceUri($r);

        if (false !== $subscriptionId) {
            $cacheFiles = scandir($this->_owApp->erfurt->getCacheDir());

            foreach ($cacheFiles as $filename) {
                if (false !== strpos($filename, 'pubsub_'.$subscriptionId .'_')) {
                    echo "true";
                    return;
                }
            }
        }

        echo "false";
    }

    /**
     * Import feed updates for a given resource (r)
     */
    public function importfeedupdatesAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        $r = $this->_request->getParam('r');
        $model = $this->_owApp->selectedModel;
        $subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );

        $subscriptionId = $subscriptionStorage->getSubscriptionIdByResourceUri($r);
        $statements = array();
        
        if (false !== $subscriptionId) {
            
            // get and read cache dir
            $cacheFolder = $this->_owApp->erfurt->getCacheDir();
            $cacheFiles = scandir($cacheFolder);

            // go through all cachedir files
            foreach ($cacheFiles as $filename) {
                // if its a pubsub file containg feed updates for $subscriptionId
                if (false !== strpos($filename, 'pubsub_'.$subscriptionId .'_')) {
                    $statements = array_merge(
                        $statements, 
                        PubSubHubbub_FeedUpdate::getStatementListOutOfFeedUpdateFile(
                            $cacheFolder .'/'. $filename
                        )
                    );
                }
            }
            
            PubSubHubbub_FeedUpdate::importFeedUpdates($statements, $r, $model);
            
            PubSubHubbub_FeedUpdate::removeFeedUpdateFiles(
                $cacheFiles, $subscriptionId, $cacheFolder
            );
        }
        echo json_encode("true");
    }

    /**
     * Resource Action
     */
    public function testAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        $subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );
        $subscriptionStorage->deleteSubscription('22fee68d4206198fa6d983c25db0578d');
    }
}
