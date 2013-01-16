<?php

class PubSubHubbub_Subscription
    extends Zend_Feed_Pubsubhubbub_Model_ModelAbstract
    implements Zend_Feed_Pubsubhubbub_Model_SubscriptionInterface
{
    protected $_subscriptionModelInstance;
    protected $_subscriptionConfig;
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
    
    public function __construct ($subscriptionModelInstance, $subscriptionConfig) 
    {
        // avoid Zend table init
        $this->_subscriptionConfig = $subscriptionConfig;
        $this->_subscriptionModelInstance = $subscriptionModelInstance;
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
     * function generate the statements array
     * @param $subscriptionResourceUri
     * @param $data data array
     * @return array Statements Array
     */
    private function _generateStatements($subscriptionResourceUri, $data, $subscriptionResourceProperties = null)
    {
        $addStatements = array();
        $deleteStatements = array();
        $addStatements[$subscriptionResourceUri] = array();
        if (!isset($subscriptionResourceProperties))
        {
            $addStatements[$subscriptionResourceUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] = array();
            $addStatements[$subscriptionResourceUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][]
                = array(
                    'value' => $this->_subscriptionConfig->get('classUri'),
                    'type' => 'uri'
                );
        }
        foreach ($data as $dataKey => $dataValue)
        {
            if (isset($dataValue) &&
                (!isset($subscriptionResourceProperties) ||
                    (
                        isset($subscriptionResourceProperties[$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])][0]['content']) &&
                        $dataValue != $subscriptionResourceProperties[$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])][0]['content']
                    )
                )
            )
            {
                $addStatements[$subscriptionResourceUri]
                    [$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])] = array(
                        array(
                        'value' => $dataValue,
                        'type' => 'literal'
                        )
                    );
                if (isset($subscriptionResourceProperties) &&
                    (
                        isset($subscriptionResourceProperties[$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])][0]['content']) &&
                        $dataValue != $subscriptionResourceProperties[$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])][0]['content']
                    )
                )
                {
                    if (0 == count($deleteStatements))
                        $deleteStatements[$subscriptionResourceUri] = array();
                    $deleteStatements[$subscriptionResourceUri]
                    [$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])] = array(
                        array(
                        'value' => $subscriptionResourceProperties[$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])][0]['content'],
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
     */
    public function setSubscription(array $data)
    {
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
        
        $subscriptionResource = new OntoWiki_Model_Resource (
            $this->_subscriptionModelInstance->getStore(),
            $this->_subscriptionModelInstance,
            $subscriptionResourceUri
         );
        
        $subscriptionResourceProperties = $subscriptionResource->getValues();
        
        if (0 < count($subscriptionResourceProperties))
        {
            $subscriptionResourceProperties = $subscriptionResourceProperties[$this->_subscriptionModelInstance->getModelIri()];
            $data['created_time'] = $subscriptionResourceProperties
                [$this->_subscriptionConfig->get($this->_propertyMatching['created_time'])][0]['content'];
            $now = new Zend_Date;
            if (isset($data['lease_seconds'])) {
                $data['expiration_time'] = $now->add($data['lease_seconds'], Zend_Date::SECOND)
                ->get('yyyy-MM-dd HH:mms');
            }
            $statements = $this->_generateStatements($subscriptionResourceUri, $data, $subscriptionResourceProperties);
            $this->_subscriptionModelInstance->addMultipleStatements($statements['addStatements']);
            $this->_subscriptionModelInstance->deleteMultipleStatements($statements['deleteStatements']);

            return false;
        } else {
            $statements = $this->_generateStatements($subscriptionResourceUri, $data);
            $this->_subscriptionModelInstance->addMultipleStatements($statements['addStatements']);

            return true;
        }
        
        // old implementation
        /*
        $result = $this->_db->find($data['id']);
        if (count($result)) {
            $data['created_time'] = $result->current()->created_time;
            $now = new Zend_Date;
            if (isset($data['lease_seconds'])) {
                $data['expiration_time'] = $now->add($data['lease_seconds'], Zend_Date:ECOND)
                ->get('yyyy-MM-dd HH:mms');
            }
            $this->_db->update(
                $data,
                $this->_db->getAdapter()->quoteInto('id = ?', $data['id'])
            );
            return false;
        }

        $this->_db->insert($data);
        return true;*/
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
            throw new Zend_Feed_Pubsubhubbub_Exception('Invalid parameter "key"'
                .' of "' . $key . '" must be a non-empty string');
        }
        
        // generate uri with topic_url and hub_url
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );
        
        $subscriptionResource = new OntoWiki_Model_Resource (
            $this->_subscriptionModelInstance->getStore(),
            $this->_subscriptionModelInstance,
            $subscriptionResourceUri
         );
        
        $subscriptionResourceProperties = $subscriptionResource->getValues();

        if (0 < count($subscriptionResourceProperties))
        {
            $subscriptionResourceProperties = $subscriptionResourceProperties[$this->_subscriptionModelInstance->getModelIri()];
            $data = array();
            foreach ($this->_propertyMatching as $dataKey => $propertyKey)
            {
                $propertyArray = isset($subscriptionResourceProperties
                    [$this->_subscriptionConfig->get($propertyKey)]) ?
                    $subscriptionResourceProperties[$this->_subscriptionConfig->get($propertyKey)] :
                   null;
                
                if (isset($propertyArray) && "" != $propertyArray[0]['content'])
                {
                    $data[$dataKey] = $propertyArray[0]['content'];
                }
                else
                {
                    $data[$dataKey] = null;
                }
            }
            return $data;
        }
        return false;
    
        // old implementation
        /*
        $result = $this->_db->find($key);
        if (count($result)) {
            return $result->current()->toArray();
        }
        return false;*/
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
            throw new Zend_Feed_Pubsubhubbub_Exception('Invalid parameter "key"'
                .' of "' . $key . '" must be a non-empty string');
        }
        // generate uri with topic_url and hub_url
        $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
            $this->_subscriptionConfig->get('classUri'),
            $key
        );
        
        $subscriptionResource = new OntoWiki_Model_Resource (
            $this->_subscriptionModelInstance->getStore(),
            $this->_subscriptionModelInstance,
            $subscriptionResourceUri
         );
        
        $subscriptionResourceProperties = $subscriptionResource->getValues();

        if (0 < count($subscriptionResourceProperties))
            return true;
        else
            return false;
        
        // old implementation
        /*
        $result = $this->_db->find($key);
        if (count($result)) {
            return true;
        }
        return false;*/
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
            throw new Zend_Feed_Pubsubhubbub_Exception('Invalid parameter "key"'
                .' of "' . $key . '" must be a non-empty string');
        }
        if(true == $this->hasSubscription($key)) {
            // generate uri with topic_url and hub_url
            $subscriptionResourceUri = $this->_generateSubscriptionResourceUri(
                $this->_subscriptionConfig->get('classUri'),
                $key
            );
            
            $this->_subscriptionModelInstance->deleteMatchingStatements(
                    $subscriptionResourceUri,
                    null,
                    null
                );
            return true;
        }
        return false;
    
        // old implementation
        /*
        $result = $this->_db->find($key);
        if (count($result)) {
            $this->_db->delete(
                $this->_db->getAdapter()->quoteInto('id = ?', $key)
            );
            return true;
        }
        return false;*/
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
        $this->_subscriptionModelInstance->addMultipleStatements($addStatements);
    }
    
    /**
     * get the topic url for a given resource uri
     */
    public function getTopicByResourceUri($resourceUri)
    {
        $results = $this->_subscriptionModelInstance->sparqlQuery (
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
}
