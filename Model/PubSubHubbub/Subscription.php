<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Pubsub PubSubHubbub Subscription
 *
 * Handle all datastore actions
 * Represent the storage of the subscriper and implments all needed function to save, delete and
 * change subscriptions in the erfurt store
 *
 * @category   OntoWiki
 * @package    Extensions_Pubsub_PubSubHubbub
 * @author     Konrad Abicht, Lars Eidam
 * @copyright  Copyright (c) 2006-2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class PubSubHubbub_Subscription
    extends Zend_Feed_Pubsubhubbub_Model_ModelAbstract
    implements Zend_Feed_Pubsubhubbub_Model_SubscriptionInterface
{
    /**
     * Versioning type codes, using in history feed for rollback
     */
    const VERSIONING_SUBSCRIPTION_ADD_ACTION_TYPE      = 3110;
    const VERSIONING_SUBSCRIPTION_UPDATE_ACTION_TYPE   = 3120;
    const VERSIONING_SUBSCRIPTION_DELETE_ACTION_TYPE   = 3130;

    /**
     * Reference to Erfurt Versioning
     */
    protected $_versioning;

    /**
     * Instance of the selected model
     */
    protected $_selectedModelInstance;

    /**
     * Configuration from doap.n3
     */
    protected $_subscriptionConfig;

    /**
     * Array containing matchings for keys between the subscriber data array and the doap
     * config array
     */
    protected $_propertyMatching = array(
        'id' => 'id',
        'topic_url' => 'topicUrl',
        'hub_url' => 'hubUrl',
        'created_time' => 'createdTime',
        'lease_seconds' => 'leaseSeconds',
        'verify_token' => 'verifyToken',
        'secret' => 'secret',
        'expiration_time' => 'expirationTime',
        'subscription_state' => 'subscriptionState'
    );

    /**
     * Constructor
     *
     * @param $selectedModelInstance Selected model instance
     * @param $subscriptionConfig Configuration from doap.n3
     */
    public function __construct ($selectedModelInstance, $subscriptionConfig)
    {
        $this->_subscriptionConfig = $subscriptionConfig;
        $this->_selectedModelInstance = $selectedModelInstance;

        $this->_subscriptionsModel = new Erfurt_Rdf_Model(
            $this->_subscriptionConfig->get('modelUri')
        );

        // get one versioning instance for all store transactions
        $this->_versioning = Erfurt_App::getInstance()->getVersioning();
    }

    /**
     * Function generate subscription resource uri from the md5 hash over topic url and hub url
     *
     * @param $classUri
     * @param $id
     * @return string generated uri
     */
    private function _generateSubscriptionResourceUri($classUri, $id)
    {
        return $classUri . substr($id, 0, 10);
    }

    /**
     * Generate the statements array from the subscriber data array
     *
     * @param $subscriptionResourceUri
     * @param $data subscriber data array
     * @return array Statements Array
     */
    private function _generateStatements($subscriptionResourceUri, $data, $subscriptionResourceProperties = null)
    {
        // init statement arrays
        $addStatements = array();
        $deleteStatements = array();
        $addStatements[$subscriptionResourceUri] = array();

        // add the type statement
        if (!isset($subscriptionResourceProperties)) {
            $typePropertyUri = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';
            $addStatements[$subscriptionResourceUri][$typePropertyUri] = array();
            $addStatements[$subscriptionResourceUri][$typePropertyUri][]
                = array(
                    'value' => $this->_subscriptionConfig->get('classUri'),
                    'type' => 'uri'
                );
        }

        // go through data array elements and generate the resultiung statements
        foreach ($data as $dataKey => $dataValue) {
            // get the current property uri
            $propertyUri = "";
            if ('resourceProperties' != $dataKey) {
                $propertyUri = $this->_subscriptionConfig->get($this->_propertyMatching[$dataKey]);
            }

            // check if a add statement is needed
            if (('resourceProperties' != $dataKey)
                && isset($dataValue)
                && (!isset($subscriptionResourceProperties)
                || (isset($subscriptionResourceProperties[$propertyUri][0]['content'])
                && $dataValue != $subscriptionResourceProperties[$propertyUri][0]['content']))
            ) {
                $addStatements[$subscriptionResourceUri][$propertyUri] = array(
                        array(
                        'value' => $dataValue,
                        'type' => 'literal'
                        )
                    );
                // check if a delete statment is needed
                if (isset($subscriptionResourceProperties)
                    && (isset($subscriptionResourceProperties[$propertyUri][0]['content'])
                    && $dataValue != $subscriptionResourceProperties[$propertyUri][0]['content'])
                ) {
                    if (0 == count($deleteStatements)) {
                        $deleteStatements[$subscriptionResourceUri] = array();
                    }
                    $deleteStatements[$subscriptionResourceUri][$propertyUri] = array(
                        array(
                        'value' => $subscriptionResourceProperties[$propertyUri][0]['content'],
                        'type' => 'literal'
                        )
                    );
                }
            }
        }

        // setup return array
        $returnArray = array();
        $returnArray['addStatements'] = $addStatements;
        $returnArray['deleteStatements'] = $deleteStatements;
        return $returnArray;
    }

    /**
     * Save the subscribing user uri to the subscription
     *
     * @param $key subscription id
     * @param $subscribingUserUri uri of the user, which was subscribing
     * @return void
     */
    public function addSubscribingUserUri($key, $subscribingUserUri)
    {
        // generate uri with subscription id
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );

        // init and fill the statements array
        $addStatements = array();
        $subscribingUserPropertyUri = $this->_subscriptionConfig->get('subscribingUser');
        $addStatements[$subscriptionResourceUri] = array();
        $addStatements[$subscriptionResourceUri][$subscribingUserPropertyUri] = array();
        $addStatements[$subscriptionResourceUri][$subscribingUserPropertyUri][]
            = array(
                'value' => $subscribingUserUri,
                'type' => 'uri'
            );

        // setup the versioning options
        $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_UPDATE_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );

        // start versioning
        $this->_versioning->startAction($versioningActionSpec);

        // write the statements to the store
        $this->_selectedModelInstance->addMultipleStatements($addStatements);

        // end versioning
        $this->_versioning->endAction();
    }

    /**
     * Save IRI of the model where the resource for this subscription is located in
     *
     * @param $key subscription id
     * @param $modelIri URI of the model, where the resource for this subscription is located in
     * @return void
     */
    public function addModelIri($key, $modelIri)
    {
        // generate uri with subscription id
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );

        // init and fill the statements array
        $addStatements = array();
        $modelIriPropertyUri = $this->_subscriptionConfig->get('modelIri');
        $addStatements[$subscriptionResourceUri] = array();
        $addStatements[$subscriptionResourceUri][$modelIriPropertyUri] = array();
        $addStatements[$subscriptionResourceUri][$modelIriPropertyUri][]
            = array(
                'value' => $modelIri,
                'type' => 'uri'
            );

        // setup the versioning options
        $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_UPDATE_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );

        // start versioning
        $this->_versioning->startAction($versioningActionSpec);

        // write the statements to the store
        $this->_selectedModelInstance->addMultipleStatements($addStatements);

        // end versioning
        $this->_versioning->endAction();
    }

    /**
     * Function save the resource uri to the subscription
     *
     * @param $key subscription id
     * @param $resourceUri URI of the subscription resource
     * @return void
     *
     */
    public function addSourceResourceUri($key, $resourceUri)
    {
        // generate uri with subscription id
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );

        // init and fill the statements array
        $addStatements = array();
        $sourceResourcePropertyUri = $this->_subscriptionConfig->get('sourceResource');
        $addStatements[$subscriptionResourceUri] = array();
        $addStatements[$subscriptionResourceUri][$sourceResourcePropertyUri] = array();
        $addStatements[$subscriptionResourceUri][$sourceResourcePropertyUri][]
            = array(
                'value' => $resourceUri,
                'type' => 'uri'
            );

        // setup the versioning options
        $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_UPDATE_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );

        // start versioning
        $this->_versioning->startAction($versioningActionSpec);

        // write the statements to the store
        $this->_selectedModelInstance->addMultipleStatements($addStatements);

        // end versioning
        $this->_versioning->endAction();
    }

    /**
     * Check if there exists a resource for the given URL.
     *
     * @param $resourceUri URI of the resource to chck for
     * @return bool true if resource is in selected model, else false
     */
    public function existsResourceInModel($resourceUri)
    {
        return 0 == count(
            $this->_selectedModelInstance->sparqlQuery(
                'SELECT ?o WHERE { <'. $resourceUri .'> ?p ?o. } LIMIT 1;'
            )
        ) ? false : true;
    }

    /**
     * Get a list of filenames related to any resource of the selected model.
     *
     * @param $cacheDir Erfurt cache dir with update feed files
     * @return array List of filenames
     */
    public function getFilesForFeedUpdates($cacheDir)
    {
        $files = scandir($cacheDir);

        $result = array ();

        // go through all files in the cache folder
        foreach ($files as $cacheFile) {
            // if current file is a pubsub feed update file
            if ('pubsub_' === substr($cacheFile, 0, 7)) {

                // extract hash from filename
                $subscriptionHash = substr($cacheFile, 7, 32);

                $sourceResource = $this->getSourceResource($subscriptionHash);

                // there are feed updates for at least one resource in this model
                if (true === $this->existsResourceInModel($sourceResource)) {
                    $result[] = $cacheFile;
                }
            }
        }

        return $result;
    }

    /**
     * Get subscription by ID/key
     *
     * @param string $key subscription id
     * @return array/bool array with subscription data, if subscription exists, else false
     */
    public function getSubscription($key)
    {
        // check if key is string and not empty
        if (empty($key) || !is_string($key)) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception(
                'Invalid parameter "key"' .
                ' of "' .
                $key .
                '" must be a non-empty string'
            );
        }

        // generate uri with subscription id
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );

        // get the subscription as OntoWiki resource
        $subscriptionResource = new OntoWiki_Model_Resource(
            $this->_selectedModelInstance->getStore(),
            $this->_selectedModelInstance,
            $subscriptionResourceUri
        );

        // get all properties of the subscription
        $subscriptionResourceProperties = $subscriptionResource->getValues();

        // if the subscription had proberties
        if (0 < count($subscriptionResourceProperties)) {
            $modelIri = $this->_selectedModelInstance->getModelIri();
            $subscriptionResourceProperties = $subscriptionResourceProperties[$modelIri];

            // init subscriber data array an save the proberties plain
            $data = array();
            $data['resourceProperties'] = $subscriptionResourceProperties;
            // fill the subscriber data array with the subscription resource content
            foreach ($this->_propertyMatching as $dataKey => $propertyKey) {
                // get property content
                $propertyUri = $this->_subscriptionConfig->get($propertyKey);
                $propertyArray = isset($subscriptionResourceProperties[$propertyUri]) ?
                    $subscriptionResourceProperties[$propertyUri] :
                    null;

                // if property content exists write it to the subscriber data array
                if (isset($propertyArray) && "" != $propertyArray[0]['content']) {
                    $data[$dataKey] = $propertyArray[0]['content'];
                } else {
                    $data[$dataKey] = null;
                }
            }
            return $data;
        }
        return false;
    }

    /**
     * Delete a subscription
     *
     * @param string $key subscription id
     * @return bool true if subscription deleted, false otherwise
     */
    public function deleteSubscription($key)
    {
        // check if key is string and not empty
        if (empty($key) || !is_string($key)) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception(
                'Invalid parameter "key"' .
                ' of "' .
                $key .
                '" must be a non-empty string'
            );
        }

        // if subscription exists
        if (true == $this->hasSubscription($key)) {
            // generate uri with subscription id
            $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
                $this->_subscriptionConfig->get('classUri'),
                $key
            );

            // get the subscription as OntoWiki resource
            $subscriptionResource = new OntoWiki_Model_Resource(
                $this->_selectedModelInstance->getStore(),
                $this->_selectedModelInstance,
                $subscriptionResourceUri
            );

            // get all properties of the subscription
            $modelUri = $this->_selectedModelInstance->getBaseUri();
            $subscriptionResourceProperties = $subscriptionResource->getValues();
            $subscriptionResourceProperties = $subscriptionResourceProperties[$modelUri];

            // generate the delete statements
            foreach ($subscriptionResourceProperties as $property => $objects) {
                 foreach ($objects as $objectNumber => $object) {
                    if (isset($object['content']) && null !== $object['content']) {
                       $subscriptionResourceProperties[$property][$objectNumber]['type'] = 'literal';
                       $subscriptionResourceProperties[$property][$objectNumber]['value'] = $object['content'];
                    }
                    if (isset($object['uri']) && null !== $object['uri']) {
                       $subscriptionResourceProperties[$property][$objectNumber]['type'] = 'uri';
                       $subscriptionResourceProperties[$property][$objectNumber]['value'] = $object['uri'];
                    }
                 }

            }
            $deleteStatements = array(
                $subscriptionResourceUri => $subscriptionResourceProperties
            );

            // setup the versioning options
            $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_DELETE_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );

            // start versioning
            $this->_versioning->startAction($versioningActionSpec);

            // delete the statements in the store
            $this->_selectedModelInstance->deleteMultipleStatements($deleteStatements);

            // end versioning
            $this->_versioning->endAction();

            return true;
        }
        return false;
    }

    /**
     * Determine if a subscription matching the key exists
     *
     * @param string $key subscription id
     * @return bool true if subscription exists, else false
     */
    public function hasSubscription($key)
    {
        // check if key is string and not empty
        if (empty($key) || !is_string($key)) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception(
                'Invalid parameter "key"' .
                ' of "' .
                $key .
                '" must be a non-empty string'
            );
        }

        // generate uri with subscription id
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );

        // get the subscription as OntoWiki resource
        $subscriptionResource = new OntoWiki_Model_Resource(
            $this->_selectedModelInstance->getStore(),
            $this->_selectedModelInstance,
            $subscriptionResourceUri
        );

        // get all properties of the subscription
        $subscriptionResourceProperties = $subscriptionResource->getValues();

        if (0 < count($subscriptionResourceProperties)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the source resource by subscription id
     *
     * @param $key subscription id
     * @return string|null resource uri if resource exists , else null
     */
    public function getSourceResource($key)
    {
        $tmp = $this->_subscriptionsModel->sparqlQuery(
            'SELECT ?sourceResource
            WHERE {
              ?subscriptionUri <' . $this->_subscriptionConfig->get('id') . '> "'. $key .'" .
              ?subscriptionUri <' . $this->_subscriptionConfig->get('sourceResource') . '> ?sourceResource .
            }
            LIMIT 1;'
        );

        foreach ($tmp as $entry) {
            return $entry['sourceResource'];
        }

        return null;
    }

    /**
     * Get the subscription id id for a given resource uri
     *
     * @param $resourceUri URI of the resource the subscription id is searching for
     * @return string/bool subscription id if resource exists, else false
     */
    public function getSubscriptionIdByResourceUri($resourceUri)
    {
        $results = $this->_selectedModelInstance->sparqlQuery(
            'SELECT ?subscriptionId
            WHERE {
              ?subscriptionUri <' . $this->_subscriptionConfig->get('sourceResource') . '> <' . $resourceUri . '> .
              ?subscriptionUri <' . $this->_subscriptionConfig->get('id') . '> ?subscriptionId .
            }'
        );
        if (0 < count($results)) {
            return $results[0]['subscriptionId'];
        } else {
            return false;
        }
    }

    /**
     * Get the topic url for a given resource uri
     *
     * @param $resourceUri URI of the resource the topic URL is searching for
     * @return string/bool topic url if resource exists, else false
     */
    public function getTopicByResourceUri($resourceUri)
    {
        $results = $this->_selectedModelInstance->sparqlQuery(
            'SELECT ?topicUrl
            WHERE {
              ?subscriptionUri <' . $this->_subscriptionConfig->get('sourceResource') . '> <' . $resourceUri . '> .
              ?subscriptionUri <' . $this->_subscriptionConfig->get('topicUrl') . '> ?topicUrl .
            }'
        );
        if (0 < count($results)) {
            return $results[0]['topicUrl'];
        } else {
            return false;
        }
    }

    /**
     * Create a new subscription
     *
     * @param array $data subscriber data array of values for the new subscription,
     *                    subscriber data array
     * @return bool true if creation went fine, false otherwise.
     */
    public function setSubscription(array $data)
    {
        $returnValue = false;

        // check if subscription id exists
        if (!isset($data['id'])) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception(
                'ID must be set before attempting a save'
            );
        }

        // generate uri with subscription id
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $data['id']
        );

        // get the subscription as OntoWiki resource
        $subscriptionResource = new OntoWiki_Model_Resource(
            $this->_selectedModelInstance->getStore(),
            $this->_selectedModelInstance,
            $subscriptionResourceUri
        );

        // get all properties of the subscription
        $subscriptionResourceProperties = $subscriptionResource->getValues();

        // if the subscription has properties update the changed one's
        if (0 < count($subscriptionResourceProperties)) {
            $modelIri = $this->_selectedModelInstance->getModelIri();
            $subscriptionResourceProperties = $subscriptionResourceProperties[$modelIri];
            $createdTimePropertyUri = $this->_subscriptionConfig->get($this->_propertyMatching['created_time']);
            $data['created_time'] = $subscriptionResourceProperties[$createdTimePropertyUri][0]['content'];

            // get the current time
            $now = new Zend_Date;

            // if lease seconds is set to the subscription, calculate a new expiration time
            if (isset($data['lease_seconds'])) {
                $data['expiration_time'] = $now
                    ->add($data['lease_seconds'], Zend_Date::SECOND)
                    ->get('yyyy-MM-dd HH:mms');
            }

            // generate new add and delete statements from subscriber data array
            $statements = $this->_generateStatements(
                $subscriptionResourceUri,
                $data,
                $subscriptionResourceProperties
            );

            // setup the versioning options
            $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_UPDATE_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );

            // start versioning
            $this->_versioning->startAction($versioningActionSpec);

            // add and delete the statements to the store
            $this->_selectedModelInstance->addMultipleStatements($statements['addStatements']);
            $this->_selectedModelInstance->deleteMultipleStatements($statements['deleteStatements']);

            $returnValue = false;
        // if the subscription has now proberties only add the properties
        } else {
            // generate the add statements
            $statements = $this->_generateStatements($subscriptionResourceUri, $data);

            // setup the versioning options
            $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_ADD_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );

            // start versioning
            $this->_versioning->startAction($versioningActionSpec);

            // add the statements to the store
            $this->_selectedModelInstance->addMultipleStatements($statements['addStatements']);

            $returnValue = true;
        }

        // end versioning
        $this->_versioning->endAction();

        return $returnValue;
    }
}
