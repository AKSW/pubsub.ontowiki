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
        $subscriptionModelIri = $this->_owApp->selectedModel->getModelIri();

        if ("" != $hubUrl && "" != $topicUrl && "" != $callBackUrl) {
            $subscriber = new PubSubHubbub_Subscriber;
            $subscriber->setStorage($subscriptionStorage);
            $subscriber->addHubUrl($hubUrl);
            $subscriber->setTopicUrl($topicUrl);
            $subscriber->setCallbackUrl($callBackUrl);
            $subscriber->setPreferredVerificationMode($verifyMode);

            /**
             * Subscribe
             */
            if ("subscribe" == $subscriptionMode) {
                $subscriber->subscribeAll();
                if ("" != $sourceResource)
                    $subscriber->addSourceResourceUri($sourceResource);
                if ("" != $subscribingUserUri)
                    $subscriber->addSubscribingUserUri($subscribingUserUri);

                // add model iri to the subscription
                $subscriber->addModelIri($subscriptionModelIri);

            /**
             * Unsubscribe
             */
            } else if ("unsubscribe" == $subscriptionMode) {
                $subscriber->unsubscribeAll();

            /**
             * Something went wrong!
             */
            } else {
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

        // if hub sends you a couple of feed updates
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

            /**
             * Throw Erfurt_Event
             */
            $event = new Erfurt_Event('onFeedUpdate');

            // attach some information to the event
            $event->autoInsertFeedUpdates = 'true' == $this->_privateConfig
                ->get('subscriptions')
                ->get('autoInsertFeedUpdates') ? true : false;
            $event->feedUpdateFilePath = $filePath;
            $event->feedUpdates = $callback->getFeedUpdate();

            // extract model iri from subscription entry in subscriptions model
            $modelIri = $subscriptionResourceData
                ['resourceProperties']
                [$this->_privateConfig->get('subscriptions')->get('modelIri')]
                [0]['uri'];
            $event->modelInstance = new Erfurt_Rdf_Model($modelIri);

            $event->sourceResource = $subscriptionStorage->getSourceResource(
                $this->_request->getParam('xhub_subscription')
            );
            $event->subscriptionResourceProperties = $subscriptionResourceData['resourceProperties'];

            // trigger the event
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
     *
     */
    public function importfeedupdatesformodelresourcesAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // Subscription instance of model for subscriptions
        $subscriptionsModel = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance, $this->_privateConfig->get('subscriptions')
        );

        // Subscription instance of selected model
        $subscription = new PubSubHubbub_Subscription(
            $this->_owApp->selectedModel, $this->_privateConfig->get('subscriptions')
        );

        // get all related feed update files for the selected model
        $feedUpdateFiles = $subscription->getFilesForFeedUpdates(
            $this->_owApp->erfurt->getCacheDir()
        );

        // save subscriptions config
        $config = $this->_privateConfig->get('subscriptions');

        // cache folder
        $cacheFolder = $this->_owApp->erfurt->getCacheDir();
        $cacheFiles = scandir($cacheFolder);

        // go through all feed update files (starting with pubsub_)
        foreach ( $feedUpdateFiles as $filename ) {

            // read file and generate add and delete statements
            $statements = PubSubHubbub_FeedUpdate::getStatementListOutOfFeedUpdateFile(
                $cacheFolder .'/'. $filename
            );

            $subscriptionId = PubSubHubbub_FeedUpdate::getSubscriptionIdOutOfFilename($filename);

            $subscriptionProperties = $subscriptionsModel->getSubscription($subscriptionId);

            $resourceUri = $subscriptionProperties['resourceProperties'][$config->get('sourceResource')][0]['uri'];

            // execute statements in selected model
            PubSubHubbub_FeedUpdate::importFeedUpdates($statements, $resourceUri, $this->_owApp->selectedModel);
        }

        // remove all used feed update files
        foreach ($feedUpdateFiles as $filename) {
            unlink($cacheFolder .'/'. $filename);
        }
    }
}
