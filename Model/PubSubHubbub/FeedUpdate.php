<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Handles feed updates for a certain resource.
 */
class PubSubHubbub_FeedUpdate
{
    /**
     * Add versioning related action type
     */
    const VERSIONING_FEED_SYNC_ACTION_TYPE = 3010;

    /**
     * Read pubsub file and generates statements (add and delete)
     * @param $filepath File to read
     * @return array
     */
    public static function getStatementListOutOfFeedUpdateFile($filepath)
    {
        $statements = array ();
        $xml = new SimpleXMLElement(file_get_contents($filepath));

        // go through all entry-elements in the current file
        foreach ($xml->entry as $entry) {
            $namespaces = $entry->getNamespaces(true);
            $xhtml = $entry->content->children($namespaces['xhtml']);
            $divContent = json_decode((string) $xhtml->div);
            $statements[$divContent->id] = $divContent;
        }

        return $statements;
    }

    /**
     * Import given statements (feed updates) into the model
     * @param $statements List of add and delete statements
     * @param $r Resource URI which are related to the given $statements
     * @model $model Model instance to add and delete statements from
     * @return void
     */
    public static function importFeedUpdates($statements, $r, &$model)
    {
        // there are feed updates for $subscriptionId
        if (0 < count($statements)) {
            $erfurt = Erfurt_App::getInstance();
            $versioning = $erfurt->getVersioning();
            $actionSpec = array(
                'type'        => self::VERSIONING_FEED_SYNC_ACTION_TYPE,
                'modeluri'    => $model->getBaseUri(),
                'resourceuri' => $r
            );

            sort($statements);

            // Start action
            $versioning->startAction($actionSpec);

            // go through all statements
            foreach ($statements as $statement) {

                // there are ADD statements
                if (0 < count($statement->added)) {
                    $type = true == Erfurt_Uri::check($statement->added[0][2])
                    ? 'uri'
                    : 'literal';

                    // add
                    $model->addStatement(
                        $statement->added[0][0],
                        $statement->added[0][1],
                        array('value' => $statement->added[0][2], 'type' => $type)
                    );

                // there are delete statements
                } elseif (0 < count($statement->deleted)) {
                    $type = true == Erfurt_Uri::check($statement->deleted[0][2])
                    ? 'uri'
                    : 'literal';

                    $deleteStatement = array(
                        $statement->deleted[0][0] => array(
                            $statement->deleted[0][1] => array(
                                array('value' => $statement->deleted[0][2], 'type' => $type)
                            )
                        )
                    );

                    // delete
                    $model->deleteMultipleStatements($deleteStatement);
                }
            }

            $versioning->endAction();
        }
    }

    /**
     * Removes all files which are related to a given $subscriptionId
     * @param $cacheFiles List of filenames
     * @param $subscriptionId ID to a certain subscription
     * @param $cacheFolder Path to OntoWiki cache folder
     */
    public static function removeFeedUpdateFiles($cacheFiles, $subscriptionId, $cacheFolder)
    {
        // delete files
        foreach ($cacheFiles as $filename) {
            if (false !== strpos($filename, 'pubsub_'.$subscriptionId .'_')) {
                unlink($cacheFolder .'/'. $filename);
            }
        }
    }

    /**
     *
     */
    public static function getSubscriptionIdOutOfFilename($filename)
    {
        return substr($filename, 7, 32);
    }
}
