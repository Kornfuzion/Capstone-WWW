<?php

namespace Tests\Functional;

class EventsAPI extends BaseTestCase
{
    public function testCreate()
    {
        $event = array();
        $event['latitude'] = -79.3832;
        $event['longitude'] = 79.3832;
        $event['thumbnail'] = 'https://ih1.redbubble.net/image.24695464.0125/flat,800x800,070,f.u1.jpg';
        $event['uid'] = '1';
        $event['frames'] = 60;
        
        $response = $this->runApp('POST', 'event/', $event);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEdit()
    {
        $id = "1_-79_79";
        
        $event = array();
        $event['latitude'] = -79.3832;
        $event['longitude'] = 79.3832;
        $event['thumbnail'] = 'https://ih1.redbubble.net/image.24695464.0125/flat,800x800,070,f.u1.jpg';
        $event['frames'] = 50;
        
        $response = $this->runApp('PUT', 'event/' . $id, $event);

        $this->assertEquals(200, $response->getStatusCode());
    }
}