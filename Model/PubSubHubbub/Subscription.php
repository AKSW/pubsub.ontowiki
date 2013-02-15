<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
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
     * Array containing matchings for keys
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
     * @param $selectedModelInstance Selected model instance
     * @param $subscriptionConfig Configuration from doap.n3
     */
    public function __construct ($selectedModelInstance, $subscriptionConfig)
    {
        // avoid Zend table init
        $this->_subscriptionConfig = $subscriptionConfig;
        $this->_selectedModelInstance = $selectedModelInstance;
        $this->_subscriptionsModel = new Erfurt_Rdf_Model (
            $this->_subscriptionConfig->get('modelUri')
        );

        $this->_versioning = Erfurt_App::getInstance()->getVersioning();
    }

    /**
     * function generate subscription resource uri with topic url and hub url
     * @param $classUri
     * @param $id
     * @return string generated uri
     */
    private function _generateSubscriptionResourceUri($classUri, $id)
    {
        return $classUri . substr($id, 0, 10);
    }

    /**
     * Generate the statements array
     * @param $subscriptionResourceUri
     * @param $data data array
     * @return array Statements Array
     */
    private function _generateStatements($subscriptionResourceUri, $data, $subscriptionResourceProperties = null)
    {
        $addStatements = array();
        $deleteStatements = array();
        $addStatements[$subscriptionResourceUri] = array();
        if (!isset($subscriptionResourceProperties)) {
            $addStatements[$subscriptionResourceUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] = array();
            $addStatements[$subscriptionResourceUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][]
                = array(
                    'value' => $this->_subscriptionConfig->get('classUri'),
                    'type' => 'uri'
                );
        }
        foreach ($data as $dataKey => $dataValue) {
            
            // set conditions
            $dataKeyNotEqualToResourceProperties = 'resourceProperties' != $dataKey;
            $subResPropertiesSet = isset($subscriptionResourceProperties);
            
            $propertyMatching = isset($subscriptionResourceProperties[
                $this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])
            ][0]['content']);
            
            $dataValEqualToMatchedProperty = $dataValue != $subscriptionResourceProperties
                [$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])]
                [0]
                ['content'];
            
            // check conditions
            if ( $dataKeyNotEqualToResourceProperties 
                 && true == isset($dataValue) 
                 &&
                 (!$subResPropertiesSet 
                   || (true == $propertyMatching && false == $dataValEqualToMatchedProperty))) {
                       
                $addStatements[$subscriptionResourceUri]
                    [$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])] = array(
                        array(
                        'value' => $dataValue,
                        'type' => 'literal'
                        )
                    );
                if (isset($subscriptionResourceProperties) &&
                    (
                        isset(
                            $subscriptionResourceProperties
                                [$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])]
                                [0]
                                ['content']
                            ) &&
                        $dataValue != $subscriptionResourceProperties
                            [$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])]
                            [0]
                            ['content']
                    )
                ) {
                    if (0 == count($deleteStatements))
                        $deleteStatements[$subscriptionResourceUri] = array();
                    $deleteStatements[$subscriptionResourceUri]
                    [$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])] = array(
                        array(
                        'value' => $subscriptionResourceProperties
                            [$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])]
                            [0]
                            ['content'],
                        'type' => 'literal'
                        )
                    );
                }
            }
        }

        $returnArray = array();
        $returnArray['addStatements'] = $addStatements;
        $returnArray['deleteStatements'] = $deleteStatements;
        return $returnArray;
    }
    
    /**
     * function save the subscribing user uri to the subscription
     */
    public function addSubscribingUserUri($key, $subscribingUserUri)
    {
        // generate uri with topic_url and hub_url
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );
        $addStatements = array();
        $addStatements[$subscriptionResourceUri] = array();
        $addStatements[$subscriptionResourceUri][$this->_subscriptionConfig->get('subscribingUser')] = array();
        $addStatements[$subscriptionResourceUri][$this->_subscriptionConfig->get('subscribingUser')][]
            = array(
                'value' => $subscribingUserUri,
                'type' => 'uri'
            );

        $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_UPDATE_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );

        // Start action
        $this->_versioning->startAction($versioningActionSpec);

        $this->_selectedModelInstance->addMultipleStatements($addStatements);

        $this->_versioning->endAction();
    }

    /**
     * function save the resource uri to the subscription
     */
    public function addSourceResourceUri($key, $resourceUri)
    {
        // generate uri with topic_url and hub_url
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );
        $addStatements = array();
        $addStatements[$subscriptionResourceUri] = array();
        $addStatements[$subscriptionResourceUri][$this->_subscriptionConfig->get('sourceResource')] = array();
        $addStatements[$subscriptionResourceUri][$this->_subscriptionConfig->get('sourceResource')][]
            = array(
                'value' => $resourceUri,
                'type' => 'uri'
            );

        $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_UPDATE_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );

        // Start action
        $this->_versioning->startAction($versioningActionSpec);

        $this->_selectedModelInstance->addMultipleStatements($addStatements);

        $this->_versioning->endAction();
    }
    
    /**
     * Check if there exists a resource for the given URL.
     */
    public function existsResourceInModel($resourceUri) 
    {
        return 0 == count($this->_selectedModelInstance->sparqlQuery(
            'SELECT ?o WHERE { <'. $resourceUri .'> ?p ?o. } LIMIT 1;')
        ) ? false : true;
    }
    
    /**
     * Get a list of filenames related to any resource of the selected model.
     * @return array List of filenames
     */
    public function getFilesForFeedUpdates($cacheDir) 
    {
        $files = scandir($cacheDir);
        
        $result = array ();
        
        // go through all files in the cache folder
        foreach ($files as $cacheFile) {
            // if current file is a pubsub feed update file
            if('pubsub_' === substr($cacheFile, 0, 7)) {
               
                // extract hash from filename
                $subscriptionHash = substr($cacheFile, 7, 32);
                
                $sourceResource = $this->getSourceResource($subscriptionHash);
                
                // there are feed updates for at least one resource in this model
                if (true === $this->existsResourceInModel($sourceResource)) {
                    $result [] = $cacheFile;
                }
            }
        }
        
        return $result;
    }

    /**
     * Get subscription by ID/key
     *
     * @param  string $key
     * @return array
     */
    public function getSubscription($key)
    {
        if (empty($key) || !is_string($key)) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception(
                'Invalid parameter "key"' .
                ' of "' .
                $key .
                '" must be a non-empty string'
            );
        }

        // generate uri with topic_url and hub_url
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );

        $subscriptionResource = new OntoWiki_Model_Resource(
            $this->_selectedModelInstance->getStore(),
            $this->_selectedModelInstance,
            $subscriptionResourceUri
        );

        $subscriptionResourceProperties = $subscriptionResource->getValues();

        if (0 < count($subscriptionResourceProperties)) {
            $subscriptionResourceProperties = $subscriptionResourceProperties
                [$this->_selectedModelInstance->getModelIri()];
            $data = array();
            $data['resourceProperties'] = $subscriptionResourceProperties;
            foreach ($this->_propertyMatching as $dataKey => $propertyKey) {
                $propertyArray = isset($subscriptionResourceProperties
                    [$this->_subscriptionConfig->get($propertyKey)]) ?
                    $subscriptionResourceProperties[$this->_subscriptionConfig->get($propertyKey)] :
                   null;

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
     * @param string $key
     * @return bool
     */
    public function deleteSubscription($key)
    {
        if (empty($key) || !is_string($key)) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception(
                'Invalid parameter "key"' .
                ' of "' .
                $key .
                '" must be a non-empty string'
            );
        }
        if (true == $this->hasSubscription($key)) {
            // generate uri with topic_url and hub_url
            $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
                $this->_subscriptionConfig->get('classUri'),
                $key
            );

            $subscriptionResource = new OntoWiki_Model_Resource(
                $this->_selectedModelInstance->getStore(),
                $this->_selectedModelInstance,
                $subscriptionResourceUri
            );
            $modelUri = $this->_selectedModelInstance->getBaseUri();
            $subscriptionResourceProperties = $subscriptionResource->getValues();
            $subscriptionResourceProperties = $subscriptionResourceProperties[$modelUri];

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

            $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_DELETE_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );
            // Start action
            $this->_versioning->startAction($versioningActionSpec);

            $this->_selectedModelInstance->deleteMultipleStatements(
                $deleteStatements
            );

            $this->_versioning->endAction();

            return true;
        }
        return false;
    }
    
    /**
     * Determine if a subscription matching the key exists
     *
     * @param  string $key
     * @return bool
     */
    public function hasSubscription($key)
    {
        if (empty($key) || !is_string($key)) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception(
                'Invalid parameter "key"' .
                ' of "' .
                $key .
                '" must be a non-empty string'
            );
        }
        // generate uri with topic_url and hub_url
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );

        $subscriptionResource = new OntoWiki_Model_Resource(
            $this->_selectedModelInstance->getStore(),
            $this->_selectedModelInstance,
            $subscriptionResourceUri
        );

        $subscriptionResourceProperties = $subscriptionResource->getValues();

        if (0 < count($subscriptionResourceProperties))
            return true;
        else
            return false;
    }
    
    /**
     * Get the source resource by hash
     * @return string|null
     */
    public function getSourceResource($hash)
    {
        $tmp = $this->_subscriptionsModel->sparqlQuery(
            'SELECT ?sourceResource
            WHERE {
              ?subscriptionUri <' . $this->_subscriptionConfig->get('id') . '> "'. $hash .'" .
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
     * get the topic url for a given resource uri
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
        if (0 < count($results))
            return $results[0]['subscriptionId'];
        else
            return false;
    }
    
    /**
     * get the topic url for a given resource uri
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
        if (0 < count($results))
            return $results[0]['topicUrl'];
        else
            return false;
    }
    
    /**
     * Create a new subscription
     * @param $data Array of values for the new subscription
     * @return bool True if creation went fine, false otherwise.
     */
    public function setSubscription(array $data)
    {
        $returnValue = false;

        if (!isset($data['id'])) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception(
                'ID must be set before attempting a save'
            );
        }

        // generate uri with topic_url and hub_url
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $data['id']
        );

        $subscriptionResource = new OntoWiki_Model_Resource(
            $this->_selectedModelInstance->getStore(),
            $this->_selectedModelInstance,
            $subscriptionResourceUri
        );

        $subscriptionResourceProperties = $subscriptionResource->getValues();

        if (0 < count($subscriptionResourceProperties)) {
            $subscriptionResourceProperties = $subscriptionResourceProperties
                [$this->_selectedModelInstance->getModelIri()];
            $data['created_time'] = $subscriptionResourceProperties
                [$this->_subscriptionConfig->get($this->_propertyMatching['created_time'])][0]['content'];
            $now = new Zend_Date;
            if (isset($data['lease_seconds'])) {
                $data['expiration_time'] = $now->add($data['lease_seconds'], Zend_Date::SECOND)
                ->get('yyyy-MM-dd HH:mms');
            }
            $statements = $this->_generateStatements(
                $subscriptionResourceUri,
                $data,
                $subscriptionResourceProperties
            );

            $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_UPDATE_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );
            // Start action
            $this->_versioning->startAction($versioningActionSpec);

            $this->_selectedModelInstance->addMultipleStatements($statements['addStatements']);
            $this->_selectedModelInstance->deleteMultipleStatements($statements['deleteStatements']);

            $returnValue = false;
        } else {
            $statements = $this->_generateStatements($subscriptionResourceUri, $data);

            $versioningActionSpec = array(
                'type'        => self::VERSIONING_SUBSCRIPTION_ADD_ACTION_TYPE,
                'modeluri'    => $this->_selectedModelInstance->getBaseUri(),
                'resourceuri' => $subscriptionResourceUri
            );
            // Start action
            $this->_versioning->startAction($versioningActionSpec);

            $this->_selectedModelInstance->addMultipleStatements($statements['addStatements']);

            $returnValue = true;
        }

        $this->_versioning->endAction();

        return $returnValue;
    }
}
