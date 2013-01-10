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
                    'callBackUrl' => 'http://localhost:8081/callback',
                    'subscriptionMode' => 'subscribe'
                )
            );

        // Excute request  
        $this->dispatch('/pubsub/subscription');
        
        var_dump($this->response->getBody());
        
        // Check response
        $this->assertResponseCode(200);
        
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
                    'callBackUrl' => 'http://localhost:8081/callback',
                    'subscriptionMode' => 'unsubscribe'
                )
            );

        // Excute request  
        $this->dispatch('/pubsub/subscription');
        
        //var_dump($this->response);
        
        // Check response
        $this->assertResponseCode(200);
        
    }
}
