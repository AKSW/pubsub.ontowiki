<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2006-2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Plugin – pubsub
 *
 * Handle the auto feed update and add the history feed link of each resource to the http header
 *
 * @category   OntoWiki
 * @package    Extensions_Pubsub
 * @author     Konrad Abicht, Lars Eidam
 * @copyright  Copyright (c) 2006-2012, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class PubsubPlugin extends OntoWiki_Plugin
{
    /**
     * Used on linked data redirect to add link field to header
     * @param $event Erfurt_Event
     */
    public function onBeforeLinkedDataRedirect($event)
    {
        if ($event->response === null) {
            return;
        }

        // set history feed url
        $historyFeed = OntoWiki::getInstance()->config->urlBase . 'history/feed/?r=';
        $historyFeed .= urlencode((string)OntoWiki::getInstance()->selectedResource);

        // set header field
        $event->response->setHeader('link', $historyFeed, true);
    }

    /**
     * Trigger on Pub Hub pushed new feed updates for a particular topicUrl.
     * @param $event Erfurt_Event containing xhubSubscription property with subscription id
     */
    public function onFeedUpdate($event)
    {
        // if user want to auto insert incoming feed updates
        if (true == $event->autoInsertFeedUpdates) {

            // read statements from created file and build an array
            $statements = PubSubHubbub_FeedUpdate::getStatementListOutOfFeedUpdateFile(
                $event->feedUpdateFilePath
            );

            // import all statements of the built array into the model
            PubSubHubbub_FeedUpdate::importFeedUpdates(
                $statements,
                $event->sourceResource,
                $event->modelInstance
            );

            // remove file with feed updates
            unlink($event->feedUpdateFilePath);
        }
    }
}
