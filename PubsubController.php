<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
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
        if (!defined('OW_SHOW_MAX')) {
            define('OW_SHOW_MAX', 5);
        }

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

        // get the subscription helper
        $subscriptionHelper = new PubSubHubbub_ModelHelper(
            $this->_privateConfig->get('subscriptions')->get('modelUri'),
            $this->_owApp->erfurt->getStore()
        );
        // get model instance and add model if it's not exists
        $this->_subscriptionModelInstance = $subscriptionHelper->addModel();

    }

    /**
     * Index Action
     * do nothing
     */
    public function indexAction()
    {
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();
    }

    /**
     * Subsription Action
     * run the subscription of a feed on a spezific hub
     *
     * POST parameter:
     *  - hubUrl           - URL of the hub that should used
     *  - topicUrl         - URL of the topic feed to subscribe to
     *  - callBackUrl      - Callback URL for the hub
     *  - subscriptionMode - 'subscripe' or 'unsubscribe'
     *  - verifyMode       - 'sync' or 'async' mode of the hub synchronisation
     *  - sourceResource   - the source resource which has linked to the feed
     */
    public function subscriptionAction()
    {
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();

        // get the subscription storage
        $subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );

        // get the POST parameter
        $hubUrl = $this->getParam('hubUrl');
        $topicUrl = $this->getParam('topicUrl');
        $callBackUrl = $this->getParam('callBackUrl');
        $subscriptionMode = $this->getParam('subscriptionMode');
        $verifyMode = $this->getParam('verifyMode');
        $sourceResource = $this->getParam('sourceResource');

        // get the current user and model URI
        $subscribingUserUri = $this->_owApp->getUser()->getUri();
        $subscriptionModelIri = $this->_owApp->selectedModel->getModelIri();

        // check if required urls are filled
        if ("" != $hubUrl && "" != $topicUrl && "" != $callBackUrl) {
            // get the Subscriber and hand over the parameter
            $subscriber = new PubSubHubbub_Subscriber;
            $subscriber->setStorage($subscriptionStorage);
            $subscriber->addHubUrl($hubUrl);
            $subscriber->setTopicUrl($topicUrl);
            $subscriber->setCallbackUrl($callBackUrl);
            $subscriber->setPreferredVerificationMode($verifyMode);

            /**
             * start the subscribing process
             */
            if ("subscribe" == $subscriptionMode) {
                $subscriber->subscribeAll();
                if ("" != $sourceResource) {
                    $subscriber->addSourceResourceUri($sourceResource);
                }
                if ("" != $subscribingUserUri) {
                    $subscriber->addSubscribingUserUri($subscribingUserUri);
                }

                // add model iri to the subscription
                $subscriber->addModelIri($subscriptionModelIri);

            /**
             * start the unsubscribing process
             */
            } else if ("unsubscribe" == $subscriptionMode) {
                $subscriber->unsubscribeAll();

            /**
             * if no 'subscriptionMode' were given
             */
            } else {
                echo 'FAILURE: missing parameter';
                $this->_response->setHttpResponseCode(500);
                return;
            }

            // check the subscriber, if the subscribtion was successful, else output the errors
            if ($subscriber->isSuccess() && 0 == count($subscriber->getErrors())) {
                $this->_response->setBody('')->setHttpResponseCode(200);
            } else {
                foreach ($subscriber->getErrors() as $error) {
                    $this->_response->appendBody($error);
                }
                $this->_response->setHttpResponseCode(404);
            }

        // if the required urls are wrong or empty
        } else {
            echo 'FAILURE: wrong Urls';
        }
    }

    /**
     * Callback Action
     * Verify the un/subscribing process and handle feed updates
     *
     * POST paramter:
     *  - xhub_subscription - subscription hash to verify the correct subscription
     */
    public function callbackAction()
    {
        // set needed request method parameter
        if ($this->_request->isPost()) {
            $_SERVER['REQUEST_METHOD'] = 'post';
        } else {
            $_SERVER['REQUEST_METHOD'] = 'get';
        }

        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();

        // get the subscription storage
        $subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );

        //get the Callback instance and hond over the storage
        $callback = new PubSubHubbub_Subscriber_Callback;
        $callback->setStorage($subscriptionStorage);

        // handle request and immediatly send response, to avoid blocking the hub
        $callback->handle($this->_request->getParams(), true);

        // ######## Feed Update Handling ########
        // if hub sends you a couple of feed updates
        if (true === $callback->hasFeedUpdate()) {
            //get filepath for the feed update files
            $filePath = $this->_owApp->erfurt->getCacheDir() .
                        "pubsub_" .
                        $this->_request->getParam('xhub_subscription') .
                        "_" .
                        time() .
                        ".xml";

            // if filepath is not writable
            if ( false === ( $fh = fopen($filePath, 'w') ) ) {
                // can't open the file
                $m = "No write permissions for ". $filePath;
                throw new CubeViz_Exception($m);
            }

            // write the hole feed update to the file
            fwrite($fh, $callback->getFeedUpdate() . "\n");
            // set file mode
            chmod($filePath, 0755);
            // clsoe file
            fclose($fh);

            // collect all subscripton properties
            $subscriptionResourceData = $subscriptionStorage->getSubscription(
                $this->_request->getParam('xhub_subscription')
            );

            // create erfurt event
            $event = new Erfurt_Event('onFeedUpdate');

            // attach some information to the event
            $event->autoInsertFeedUpdates = 'true' == $this->_privateConfig
                ->get('subscriptions')
                ->get('autoInsertFeedUpdates') ? true : false;
            $event->feedUpdateFilePath = $filePath;
            $event->feedUpdates = $callback->getFeedUpdate();

            // extract model iri from subscription entry in subscriptions model
            $modelIriProperty = $this->_privateConfig->get('subscriptions')->get('modelIri');
            $modelIri = $subscriptionResourceData['resourceProperties'][$modelIriProperty][0]['uri'];
            $event->modelInstance = new Erfurt_Rdf_Model($modelIri);

            // add source resource to the event
            $event->sourceResource = $subscriptionStorage->getSourceResource(
                $this->_request->getParam('xhub_subscription')
            );

            // add subscripton properties to the event
            $event->subscriptionResourceProperties = $subscriptionResourceData['resourceProperties'];

            // trigger the event
            $event->trigger();
        }
    }
    /**
     * Publish Action
     * Subscribe to a specific feed with use of a specific hub
     *
     * GET/POST parameter:
     *  - hubUrl           - URL of the hub that should used
     *  - topicUrl         - URL of the topic feed to subscribe to
     */
    public function publishAction()
    {
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();

        // get the GET/POST paramter
        $hubUrl = $this->getParam('hubUrl');
        $topicUrl = $this->getParam('topicUrl');

        // check if the url are not empty
        if ("" != $hubUrl && "" != $topicUrl) {
            // get the zend pubplisher
            $publisher = new Zend_Feed_Pubsubhubbub_Publisher;

            // hand over the paramter
            $publisher->addHubUrl($hubUrl);
            $publisher->addUpdatedTopicUrl($topicUrl);

            // start the publishing process
            $publisher->notifyAll();

            // check the publisher, if the publishing was correct, else output the errors
            if ($publisher->isSuccess() && 0 == count($publisher->getErrors())) {
                $this->_response->setBody('')->setHttpResponseCode(200);
            } else {
                foreach ($publisher->getErrors() as $error) {
                    $this->_response->appendBody($error);
                }
                $this->_response->setHttpResponseCode(404);
            }
        // if the url's are empty
        } else {
            echo 'FAILURE: missing parameter';
            $this->_response->setHttpResponseCode(500);
            return;
        }
    }

    /*
     * Rresource check
     * Check if an uri is LinkedData using Erfurt_Wrapper_LinkeddataWrapper
     *
     * GET/POST parameter:
     *  - r      - uri of the resource to check
     */
    public function checkresourceAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // get GET/POST paramter
        $resourceUri = $this->getParam('r', '');

        // setup the standard result array
        $result = array(
            'isLinkedData' => false,
            'isLocalResource' => false,
            'hasError' => false,
            'errorMessage' => ''
        );

        // check if resource uri is empty
        if ("" != $resourceUri) {
            // check if local namespace is include in resource uri
            if (false === strpos($resourceUri, $this->_owApp->getUrlBase())) {
                // if resource is not local

                //get the resource instance
                $resource = new Erfurt_Rdf_Resource($resourceUri);
                // get the LinkedDataWrapper
                $wrapper = new Erfurt_Wrapper_LinkeddataWrapper();

                try {
                    // check for LinkedData
                    $result['isLinkedData'] = $wrapper->isAvailable($resource, '');
                }
                // if resource uri is not reachable
                catch (Exception $e){
                    $result['hasError'] = true;
                    $result['errorMessage'] = $e->getMessage();
                    $this->_response->setHttpResponseCode(404);
                }

            // if resource is local
            } else {
                $result['isLocalResource'] = true;
            }
        }

        // response result array in json format
        $this->_response->appendBody(json_encode($result));
    }

    /**
     * Feed update check
     * Check if a given resource has feed updates.
     *
     * GET/POST parameter:
     *  - r      - uri of the resource to check
     */
    public function existsfeedupdatesAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // get GET/POST paramter
        $r = $this->_request->getParam('r');

        // get the subscription storage
        $subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );

        // get the subscription id from storage with use of the resource uri
        $subscriptionId = $subscriptionStorage->getSubscriptionIdByResourceUri($r);

        // if the subscription id isn't empty
        if (false !== $subscriptionId) {
            //get the erfurt cache dir
            $cacheFiles = scandir($this->_owApp->erfurt->getCacheDir());

            // search thrue the cahce dir to find filename with the subscription id
            foreach ($cacheFiles as $filename) {
                if (false !== strpos($filename, 'pubsub_'.$subscriptionId .'_')) {
                    // if filename found output 'true' end return
                    echo "true";
                    return;
                }
            }
        }

        // if no filname with the subscrition id found or no subscription id was found
        // make output false
        echo "false";
    }

    /**
     * Import feed updates for a resource
     * Get all feed update files for the resource and write the resulting statements to the store
     *
     * GET/POST parameter:
     *  - r      - uri of the resource to check
     */
    public function importfeedupdatesAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // get GET/POST paramter
        $r = $this->_request->getParam('r');

        //get the current selected model
        $model = $this->_owApp->selectedModel;

        // get the subscription storage
        $subscriptionStorage = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance,
            $this->_privateConfig->get('subscriptions')
        );

        // get the subscription id from storage with use of the resource uri
        $subscriptionId = $subscriptionStorage->getSubscriptionIdByResourceUri($r);

        // init array for add and delete statements
        $statements = array();

        // if subscription id was found
        if (false !== $subscriptionId) {

            // get and read cache dir
            $cacheFolder = $this->_owApp->erfurt->getCacheDir();
            $cacheFiles = scandir($cacheFolder);

            // go through all cachedir files
            foreach ($cacheFiles as $filename) {
                // if its a pubsub file containg feed updates for $subscriptionId
                if (false !== strpos($filename, 'pubsub_'.$subscriptionId .'_')) {
                    // get the add and delete statments from all files
                    $statements = array_merge(
                        $statements,
                        PubSubHubbub_FeedUpdate::getStatementListOutOfFeedUpdateFile(
                            $cacheFolder .'/'. $filename
                        )
                    );
                }
            }

            // write statements to the store
            PubSubHubbub_FeedUpdate::importFeedUpdates($statements, $r, $model);

            // delete the processed files
            PubSubHubbub_FeedUpdate::removeFeedUpdateFiles(
                $cacheFiles, $subscriptionId, $cacheFolder
            );

            // return an json encode 'true' if proccess is finish
            echo json_encode("true");
        } else {
            // return an json encode 'flase' if no subscription id was found
            echo json_encode("false");
        }
    }

    /**
     * Import feed updates for model
     * Get all feed update files for specific model and write the resulting statements to the stor
     */
    public function importfeedupdatesformodelresourcesAction()
    {
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        // disable rendering
        $this->_helper->viewRenderer->setNoRender();

        // save subscriptions config
        $subscriptionConfig = $this->_privateConfig->get('subscriptions');

        // Subscription instance of model for subscriptions
        $subscriptionsModel = new PubSubHubbub_Subscription(
            $this->_subscriptionModelInstance, $subscriptionConfig
        );

// TODO: schlechte Lösung aktuelles Model hat nichts mit einem subscription model zutun, eher model helper benutzen
        // Subscription instance of selected model
        $subscription = new PubSubHubbub_Subscription(
            $this->_owApp->selectedModel, $subscriptionConfig
        );

        // get cache dir
        $cacheFolder = $this->_owApp->erfurt->getCacheDir();

        // get all related feed update files for the selected model
        $feedUpdateFiles = $subscription->getFilesForFeedUpdates(
            $cacheFolder
        );

        // go through all feed update files (starting with pubsub_)
        foreach ( $feedUpdateFiles as $filename ) {

            // read file and generate add and delete statements
            $statements = PubSubHubbub_FeedUpdate::getStatementListOutOfFeedUpdateFile(
                $cacheFolder .'/'. $filename
            );

            // get the subscription id
            $subscriptionId = PubSubHubbub_FeedUpdate::getSubscriptionIdOutOfFilename($filename);

            // get all subscription properties
            $subscriptionProperties = $subscriptionsModel->getSubscription($subscriptionId);

            // get the subscription source resource
            $sourceResourceProperty = $subscriptionConfig->get('sourceResource');
            $resourceUri = $subscriptionProperties['resourceProperties'][$sourceResourceProperty][0]['uri'];

            // execute statements in selected model
            PubSubHubbub_FeedUpdate::importFeedUpdates($statements, $resourceUri, $this->_owApp->selectedModel);
        }

        // remove all used feed update files
        foreach ($feedUpdateFiles as $filename) {
            unlink($cacheFolder .'/'. $filename);
        }
    }
}
