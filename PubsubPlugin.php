<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

class PubsubPlugin extends OntoWiki_Plugin
{
    /**
     * Trigger on Pub Hub pushed new feed updates for a particular topicUrl.
     * @param $event Erfurt_Event containing xhubSubscription property with subscription id
     */
    public function onFeedUpdate($event){}
}
