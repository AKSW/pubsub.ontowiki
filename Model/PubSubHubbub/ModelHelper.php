<?php

class PubSubHubbub_ModelHelper
{
    protected $_pubSubModelUri;
    protected $_store;
        
    /*
     * __construct()
     * @param $config Array
     */
    
    function __construct($pubSubModelUri, $store) {
        $this->_pubSubModelUri = $pubSubModelUri;
        $this->_store = $store;
    }
    
    /**
     * Action add the required publisher and subscriber models
     */
    public function addModel()
    {
        if (false == $this->_store->isModelAvailable($this->_pubSubModelUri))
        {
            // create model
            $model = $this->_store->getNewModel(
                $this->_pubSubModelUri,
                $this->_pubSubModelUri,
                Erfurt_Store::MODEL_TYPE_OWL
            );
            
            // connect it with system model
            $this->_store->addStatement(
                "http://localhost/OntoWiki/Config/",
                $this->_pubSubModelUri,
                "http://ns.ontowiki.net/SysOnt/hiddenImports",
                array( "value" => "http://ns.ontowiki.net/SysBase/", "type" => "uri")
            );
        }
    }
}