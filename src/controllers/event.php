<?php

class EventController {

    private $container;

    public function __construct($app) {
        $this->container = $app->getContainer(); 
        $app->get('/events', array($this, 'getNearestEvents'));
        $app->get('/event/{id}', array($this, 'getEventByID')); 
        $app->post('/event/', array($this, 'createEvent'));
        $app->put('/event/{id}', array($this, 'editEvent'));
        $app->post('/event/join/{id}', array($this, 'joinEvent'));
        $app->delete('/event/{id}', array($this, 'deleteEvent'));
    }

    public static function parseItem($item){
        $event = array(
            'id' => $item['id']['S'],
            'latitude' => $item['latitude']['N'],
            'longitude' => $item['longitude']['N'],
            'status' => $item['status']['S'], //NEW, CAPTURING, QUEUED, PROCESSING, FINISHED 
            'thumbnail' => $item['thumbnail']['S'], //TODO: S3 image upload with appropriate path or only allow links on the interwebs
            'time_created' => $item['time_created']['S'],
            //"time_started" => "time_started",
            //"time_queued" => "time_queued",
            //"time_processed" => "time_processed",
            "participants" => $item['participants']['SS'], //array('SS' => array($event['uid'])),
            "frames" => $item['frames']['N'] //number of frames? 
        );

        return $event;
    }

    function getNearestEvents($request, $response, $args) {
        $longitude = $request->getQueryParam('longitude');
        $latitude = $request->getQueryParam('latitude');
        $range = $request->getQueryParam('range'); 
        
        $iterator = $this->container->db->getIterator('Scan', array(
            'TableName' => 'events',
            'ScanFilter' => array(
                'latitude' => array(
                    'AttributeValueList' => array(
                        array('N' => ($latitude - $range)),
                        array('N' => ($latitude + $range))
                    ),
                    'ComparisonOperator' => 'BETWEEN'
                ),
                
                'longitude' => array(
                    'AttributeValueList' => array(
                        array('N' => $longitude - $range),
                        array('N' => $longitude + $range)
                    ),
                    'ComparisonOperator' => 'BETWEEN'
                ),
                
                'status' => array(
                    'AttributeValueList' => array(
                        array('S' => "NEW")
                    ),
                    'ComparisonOperator' => 'EQ'
                )
            )
        ));

        $json = array();
        foreach ($iterator as $item) {
            //may need to do some parsing
            array_push($json, self::parseItem($item));
        }

        return $response->withJson($json);
    }

    function getEventByID($request, $response, $args) {
        $id = $request->getAttribute('id');

        $result = $this->container->db->getItem(array(
            'ConsistentRead' => true,
            'TableName' => 'events',
            'Key'       => array(
                'id'   => array('S' => $id)
            )
        ));

        $event = self::parseItem($result['Item']);
        return $response->withJson($event);
    }

    function createEvent($request, $response, $args) {
        $event = json_decode($request->getBody());

        $event['time_created'] = date('Y-m-d H:i:s');
        $event['id'] = $event['uid'] . "_" . round($event['latitude']) . "_" . round($event['longitude']); //let's assume you can only hold one active scope at a time. 
        $result = $this->container->db->putItem(array(
            'TableName' => 'events',
            'Item' => array(        
                'id' => array('S' => $event['id']),
                'latitude' =>  array('N' => $event['latitude']), //-79.3832
                'longitude' => array('N' => $event['longitude']), //79.3832
                'status' => array('S' => "NEW"), //NEW, CAPTURING, QUEUED, PROCESSING, FINISHED 
                'thumbnail' => array('S' => $event['thumbnail']), //TODO: S3 image upload with appropriate path or only allow links on the interwebs
                'time_created' => array('S' => $event['time_created']),
                //"time_started" => "time_started",
                //"time_queued" => "time_started",
                //"time_processed" => "time_started",
                'participants' => array('SS' => array($event['uid'])),
                'frames' => array('N' => $event['frames']) //number of frames? 
            )
        ));

        return $response->withJson($event);
    }

    function editEvent($request, $response, $args) {
        $event = json_decode($request->getBody());
        $event['id'] = $request->getAttribute('id');
        
        $result = $this->container->db->updateItem (array(
            'TableName' => 'events',
            'Key' => array(
                'id' => array('S' => $event['id']) 
            ),
            'ExpressionAttributeValues' =>  array(
                ':latitude' => array('N' => $event['latitude']),
                ':longitude' => array('N' => $event['longitude']),
                ':thumbnail' => array('S' => $event['thumbnail']),
                ':frames' => array('N' => $event['frames']) 
            ),
            'UpdateExpression' => 'set latitude = :latitude, longitude = :longitude, thumbnail = :thumbnail, frames = :frames',
            'ReturnValues' => 'ALL_NEW'
        ));

        $event = self::parseItem($result['Attributes']);
        return $response->withJson($event);
    }

    function joinEvent($request, $response, $args) {
        $id = $request->getAttribute('id');
        
        $r = json_decode($request->getBody());
        
        $result = $this->container->db->updateItem (array(
            'TableName' => 'events',
            'Key' => array(
                'id' => array('S' => $id) 
            ),
            'ExpressionAttributeValues' =>  array(
                ':uid' => array('SS' => array($r['uid']))
            ),
            'UpdateExpression' => 'ADD participants :uid',
            'ReturnValues' => 'ALL_NEW'
        ));

        print_r($result);

        $event = self::parseItem($result['Attributes']);
        return $response->withJson($event);
    }

    function deleteEvent($request, $response, $args) {
        $id = $request->getAttribute('id');

        $result = $this->container->db->deleteItem(array(
            'TableName' => 'events',
            'Key' => array(
                'id' => array('S' => $id) 
            )
        ));

        return $response->withStatus(200);
    }
}