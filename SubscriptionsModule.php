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
        $headerFeedTags = $this->_privateConfig->get('subscriptions')->get('headerFeedTags');
        if (is_object($headerFeedTags))
            $headerFeedTags = $headerFeedTags->toArray();
        else
            $headerFeedTags = array($headerFeedTags);
        $this->view->headerFeedTags = $headerFeedTags;

        return $this->render('pubsub/subscriptions');
    }
}


