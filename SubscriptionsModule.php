<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki module – subscriptions
 *
 * Add instance properties to the list view
 *
 * @category   OntoWiki
 * @package    Extensions_pubsub
 */
class SubscriptionsModule extends OntoWiki_Module
{
    /**
     * Constructor
     */
    public function init()
    {
    }

    public function getTitle()
    {
        return "Updates and Subscriptions";
    }

    public function shouldShow()
    {
        return true;
    }

    public function getContents()
    {
        return $this->render('pubsub/subscriptions');
    }
}


