<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'TestHelper.php';

/**
 *
 */
class PubsubControllerTest extends Zend_Test_PHPUnit_ControllerTestCase
{
    public function setUp()
    {
        $this->bootstrap = new Zend_Application(
            'default',
            ONTOWIKI_ROOT . 'application/config/application.ini'
        );

        parent::setUp();

        $this->request
            ->setPost(
                array(
                    'logintype' => 'locallogin',
                    'username' => 'Admin',
                    'password' => '',
                    'login-save' => 'on'
                )
            );

        // Excute request
        $this->dispatch('/application/login');

    }

    /**
     * @1test
     */
    public function subscription()
    {
        $this->request
            ->setPost(
                array(
                    'hubUrl' => 'http://localhost:8080/subscribe',
                    'topicUrl' => 'http://pshbsubway.local/history/feed/?r=' .
                                  'http%3A%2F%2Fpshbsubway.local%2Fpubsub%2Fresourcewithfeed',
                    'callBackUrl' => 'http://www.pshb.local/pubsub/callback',
                    'subscriptionMode' => 'subscribe',
                    'verifyMode' => 'sync',
                    'sourceResource' => 'http://www.pshbsubway.local/pubsub/resourcewithfeed'
                )
            );

        // Excute request
        $this->dispatch('/pubsub/subscription');

        // Check response
        $this->assertResponseCode(
            200,
            "" == $this->response->getBody() ? null : $this->response->getBody()
        );

    }

    /**
     * @1test
     */
    public function callback()
    {
        $this->request
            ->setParams(
                array(
                    'hub_topic' => 'http://pshbsubway.local/history/feed/?r=http%3A%2F%2Fpshbsubway.local%2Fpubsub%2Fresourcewithfeed',
                    'hub_challenge' => '1111111111111111111111111',
                    'hub_verify_token' => 'a423a4bba78e826628d4cb8ff2dbeb2d',
                    'xhub_subscription' => 'a423a4bba78e826628d4cb8ff2dbeb2d',
                    'hub_mode' => 'subscribe',
                    'hub_lease_seconds' => '5'
                )
            );

        // Excute request
        $this->dispatch('/pubsub/callback');
        echo $this->response->getBody();
        // Check response
        $this->assertResponseCode(
            200,
            "" == $this->response->getBody() ? null : $this->response->getBody()
        );
    }
    
    /**
     * @1test
     * feed update
     */
    public function callbackIfFeedUpdateAvailable()
    {
        $this->request
            ->setPost(
                array(
                    'xhub_subscription' => '353b94d32f25b7cf2477747140425d12'
                )
            );
        
        $this->request->setRawBody('<?xml version="1.0" encoding="utf-8"?><a>Test</a>');
        $this->request->setHeaders(array('Content-Type: application/xml'));
        
        
        // Excute request
        $this->dispatch('/pubsub/callback');
        
        var_dump($this->response->getBody());
        
        // Check response
        $this->assertResponseCode(
            200,
            "" == $this->response->getBody() ? null : $this->response->getBody()
        );
    }

    /**
     * @1test
     */
    public function unSubscription()
    {
        $this->request
            ->setPost(
                array(
                    'hubUrl' => 'http://localhost:8080/subscribe',
                    'topicUrl' => 'http://pshbsubway.local/history/feed/?r=' .
                                  'http%3A%2F%2Fpshbsubway.local%2Fpubsub%2Fresourcewithfeed',
                    'callBackUrl' => 'http://www.pshb.local/pubsub/callback',
                    'subscriptionMode' => 'unsubscribe',
                    'verifyMode' => 'sync',
                )
            );

        // Excute request
        $this->dispatch('/pubsub/subscription');

        // Check response
        $this->assertResponseCode(
            200,
            "" == $this->response->getBody() ? null : $this->response->getBody()
        );

    }
    
    /**
     * @test
     */
    public function publish()
    {
        $this->request
            ->setPost(
                array(
                    'hubUrl' => 'http://localhost:8080/publish',
                    'topicUrl' => 'http://pshbsubway.local/history/feed/?r=' .
                                  'http%3A%2F%2Fpshbsubway.local%2Fpubsub%2Fresourcewithfeed'
                )
            );

        // Excute request
        $this->dispatch('/pubsub/publish');

        // Check response
        $this->assertResponseCode(
            200,
            "" == $this->response->getBody() ? null : $this->response->getBody()
        );

    }
}
