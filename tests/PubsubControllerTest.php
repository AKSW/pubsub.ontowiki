<?php
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
     * @test
     */
    public function subscription()
    {

        
        $this->request
            ->setPost(
                array(
                    'hubUrl' => 'http://localhost:8080/subscribe',
                    'topicUrl' => 'http://localhost:8081/atom/stream/eugene',
                    'callBackUrl' => 'http://www.pshb.local/pubsub/callback',
                    'subscriptionMode' => 'subscribe',
                    'verifyMode' => 'sync'
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
                    'hub_topic' => 'http://localhost:8081/atom/stream/eugene',
                    'hub_challenge' => '1111111111111111111111111',
                    'hub_verify_token' => 'ec01547f7d5daa0f060d5f7210690bb6',
                    'xhub_subscription' => 'ec01547f7d5daa0f060d5f7210690bb6',
                    'hub_mode' => 'subscribe',
                    'hub_lease_seconds' => '5'
                )
            );

        // Excute request  
        $this->dispatch('/pubsub/callback');
        
        // Check response
        $this->assertResponseCode(
            200,
            "" == $this->response->getBody() ? null : $this->response->getBody()
        );
    }
    
    /**
     * @test
     */
    public function unSubscription()
    {
        $this->request
            ->setPost(
                array(
                    'hubUrl' => 'http://localhost:8080/subscribe',
                    'topicUrl' => 'http://localhost:8081/atom/stream/eugene',
                    'callBackUrl' => 'http://www.pshb.local/pubsub/callback',
                    'subscriptionMode' => 'unsubscribe',
                    'verifyMode' => 'sync'
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
}
