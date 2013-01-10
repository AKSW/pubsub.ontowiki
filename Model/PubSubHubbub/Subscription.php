<?php

class PubSubHubbub_Subscription
    extends Zend_Feed_Pubsubhubbub_Model_ModelAbstract
    implements Zend_Feed_Pubsubhubbub_Model_SubscriptionInterface
{
    protected $_subscriptionModelInstance;
    protected $_subscriptionConfig;
    
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
    private function _generateStatements($subscriptionResourceUri, $data, $oldData = array())
    {
        $statements = array();
        $statements[$subscriptionResourceUri] = array();
        $statements[$subscriptionResourceUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] = array();
        $statements[$subscriptionResourceUri]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][]
            = array(
                'value' => $this->_subscriptionConfig->get('classUri'),
                'type' => 'uri'
            );
        $statements[$subscriptionResourceUri][$this->_subscriptionConfig->get('id')] =
            array(array(
                'value' => $data["id"],
                'type' => 'literal'
            ));
        $statements[$subscriptionResourceUri][$this->_subscriptionConfig->get('topicUrl')] =
            array(array(
                'value' => $data["topic_url"],
                'type' => 'literal'
            ));
        $statements[$subscriptionResourceUri][$this->_subscriptionConfig->get('hubUrl')] =
            array(array(
                'value' => $data["hub_url"],
                'type' => 'literal'
            ));
        $statements[$subscriptionResourceUri][$this->_subscriptionConfig->get('createdTime')] =
            array(array(
                'value' => $data["created_time"],
                'type' => 'literal'
            ));
        if (isset($data["lease_seconds"]))
            $statements[$subscriptionResourceUri][$this->_subscriptionConfig->get('leaseSeconds')] =
                array(array(
                    'value' => $data["lease_seconds"],
                    'type' => 'literal'
                ));
        if (isset($data["verify_token"]))
            $statements[$subscriptionResourceUri][$this->_subscriptionConfig->get('verifyToken')] =
                array(array(
                    'value' => $data["verify_token"],
                    'type' => 'literal'
                ));
        if (isset($data["secret"]))
            $statements[$subscriptionResourceUri][$this->_subscriptionConfig->get('secret')] =
                array(array(
                    'value' => $data["secret"],
                    'type' => 'literal'
                ));
        if (isset($data["expiration_time"]))
            $statements[$subscriptionResourceUri][$this->_subscriptionConfig->get('expirationTime')] =
                array(array(
                    'value' => $data["expiration_time"],
                    'type' => 'literal'
                ));
        if (isset($data["subscription_state"]))
            $statements[$subscriptionResourceUri][$this->_subscriptionConfig->get('subscriptionState')] =
                array(array(
                    'value' => $data["subscription_state"],
                    'type' => 'literal'
                ));
        
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

        //TODO: remove this output
        echo "<pre>";
        var_dump("TEST: ", $subscriptionResourceProperties);
        echo "</pre>";
        //exit();

        if (0 < count($subscriptionResourceProperties))
        {
            //Todo: clone data array
            $oldData = $data;
            $data['created_time'] = $subscriptionResourceProperties
                [$subscriptionResourceUri]
                [$this->_subscriptionConfig->get('createdTime')][0]['content'];
            $now = new Zend_Date;
            if (isset($data['lease_seconds'])) {
                $data['expiration_time'] = $now->add($data['lease_seconds'], Zend_Date::ECOND)
                ->get('yyyy-MM-dd HH:mms');
            }
            if ($oldData['created_time'] != $data['created_time'])
                
            //$this->_db->update(
            //    $data,
            //    $this->_db->getAdapter()->quoteInto('id = ?', $data['id'])
            //);
            return false;
        } else {
            $statements = $this->_generateStatements($subscriptionResourceUri, $data);
            $this->_subscriptionModelInstance->addMultipleStatements($statements);
            return true;
        }
        
        if(true == isset($_SESSION ['pubsubhubbub_sub'][$data['id']])) {
            $_SESSION ['pubsubhubbub_sub'][$data['id']] = $data;
            return false;
        } else {
            $_SESSION ['pubsubhubbub_sub'][$data['id']] = $data;
            return true;
        }
        
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
        return false;
        if (empty($key) || !is_string($key)) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception('Invalid parameter "key"'
                .' of "' . $key . '" must be a non-empty string');
        }
        if(true == isset($_SESSION ['pubsubhubbub_sub'][$key])) {
            return $_SESSION ['pubsubhubbub_sub'][$key];
        }
        return false;
        
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
        echo "test";
        return false;
        if (empty($key) || !is_string($key)) {
            require_once 'Zend/Feed/Pubsubhubbub/Exception.php';
            throw new Zend_Feed_Pubsubhubbub_Exception('Invalid parameter "key"'
                .' of "' . $key . '" must be a non-empty string');
        }
        return isset($_SESSION ['pubsubhubbub_sub'][$key]);
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
        
        if(true == isset($_SESSION ['pubsubhubbub_sub'][$key])) {
            unset($_SESSION ['pubsubhubbub_sub'][$key]);
            return true;
        }
        return false;
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
