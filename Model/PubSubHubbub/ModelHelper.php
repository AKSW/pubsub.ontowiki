<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

class PubSubHubbub_ModelHelper
{
    protected $_pubSubModelUri;
    protected $_store;

    /*
     * __construct()
     * @param $config Array
     */

    function __construct($pubSubModelUri, $store)
    {
        $this->_pubSubModelUri = $pubSubModelUri;
        $this->_store = $store;
    }

    /**
     * Action add the required publisher and subscriber models
     * @return new model
     */
    public function addModel()
    {
        $model = "";
        if (false == $this->_store->isModelAvailable($this->_pubSubModelUri)) {
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
        } else {
            $model = $this->_store->getModel($this->_pubSubModelUri);
        }

        return $model;
    }
}