<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Pubsub PubSubHubbub ModelHelper
 *
 * Handles all model processes, i.e. check for model and add model
 *
 * @category   OntoWiki
 * @package    Extensions_Pubsub_PubSubHubbub
 * @author     Konrad Abicht, Lars Eidam
 * @copyright  Copyright (c) 2006-2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class PubSubHubbub_ModelHelper
{
    protected $_pubSubModelUri;
    protected $_store;

    /*
     * Constructor
     *
     * @param $pubSubModelUri string URI of the subscription model
     * @prama $store Erfurt_Store
     */

    function __construct($pubSubModelUri, $store)
    {
        $this->_pubSubModelUri = $pubSubModelUri;
        $this->_store = $store;
    }

    /**
     * Action add the required publisher and subscriber models
     *
     * @return exiting or new model
     */
    public function addModel()
    {
        $model = "";
        // if model not exists
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
            // if model exists
        } else {
            $model = $this->_store->getModel($this->_pubSubModelUri);
        }

        return $model;
    }
}
