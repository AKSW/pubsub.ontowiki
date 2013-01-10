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
    private function _generateStatements($subscriptionResourceUri, $data)
    {
        $statements = array();
        $statements[$subscriptionResourceUri] = array();
        $statements[$subscriptionResourceUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] = array();
        $statements[$subscriptionResourceUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][]
            = array(
                'value' => $this->_subscriptionConfig->get('classUri'),
                'type' => 'uri'
            );
        foreach ($data as $dataKey => $dataValue)
        {
            if (isset($dataValue))
                $statements[$subscriptionResourceUri]
                [$this->_subscriptionConfig->get($this->_propertyMatching[$dataKey])] = array(
                        array(
                        'value' => $dataValue,
                        'type' => 'literal'
                        )
                    );
        }
        
        return $statements;
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
            $oldData = $data;
            $data['created_time'] = $subscriptionResourceProperties
                [$this->_subscriptionModelInstance->getModelIri()]
                [$this->_subscriptionConfig->get('createdTime')][0]['content'];
            $now = new Zend_Date;
            if (isset($data['lease_seconds'])) {
                $data['expiration_time'] = $now->add($data['lease_seconds'], Zend_Date::ECOND)
                ->get('yyyy-MM-dd HH:mms');
            }
            if ($oldData['created_time'] != $data['created_time'])
            {
                $this->_subscriptionModelInstance->deleteMatchingStatements(
                    $subscriptionResourceUri,
                    $this->_subscriptionConfig->get($this->_propertyMatching['created_time']),
                    array('value' => $oldData['created_time'], 'type' => 'literal')
                );
                $this->_subscriptionModelInstance->addStatement(
                    $subscriptionResourceUri,
                    $this->_subscriptionConfig->get($this->_propertyMatching['created_time']),
                    array('value' => $data['created_time'], 'type' => 'literal')
                );
            }
            if ($oldData['expiration_time'] != $data['expiration_time'])
            {
                $this->_subscriptionModelInstance->deleteMatchingStatements(
                    $subscriptionResourceUri,
                    $this->_subscriptionConfig->get($this->_propertyMatching['expiration_time']),
                    array('value' => $oldData['expirationTime'], 'type' => 'literal')
                );
                $this->_subscriptionModelInstance->addStatement(
                    $subscriptionResourceUri,
                    $this->_subscriptionConfig->get($this->_propertyMatching['expiration_time']),
                    array('value' => $data['expirationTime'], 'type' => 'literal')
                );
            }
            return false;
        } else {
            $statements = $this->_generateStatements($subscriptionResourceUri, $data);
            $this->_subscriptionModelInstance->addMultipleStatements($statements);
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
        $subscriptionResourceProperties = $subscriptionResourceProperties[$this->_subscriptionModelInstance->getModelIri()];

        if (0 < count($subscriptionResourceProperties))
        {
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

}
