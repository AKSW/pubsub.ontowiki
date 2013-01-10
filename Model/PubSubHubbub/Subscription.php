<?php

class PubSubHubbub_Subscription
    extends Zend_Feed_Pubsubhubbub_Model_ModelAbstract
    implements Zend_Feed_Pubsubhubbub_Model_SubscriptionInterface
{

    public function __construct () 
    {
        // avoid Zend table init
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
