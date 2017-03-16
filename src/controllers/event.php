<?php

class EventController {

    private $container;

    public function __construct($app) {
        $this->container = $app->getContainer(); 
        $app->get('/events', array($this, 'getNearestEvents'));
        $app->get('/event/{id}', array($this, 'getEventByID')); 
        $app->post('/event/{id}', array($this, 'createEvent'));
        $app->put('/event/{id}', array($this, 'editEvent'));
        $app->post('/event/join/{id}', array($this, 'joinEvent'));
        $app->post('/event/upload/{id}', array($this, 'contributeToEvent'));
        $app->delete('/event/{id}', array($this, 'deleteEvent'));
    }

    public static function getTestEvent(){
        $event = array();
        $event['time_created'] = date('Y-m-d H:i:s');
        $event['id'] = 'test'; 
        $event['name'] = 'Lit Drake Concert';
        $event['latitude'] = -79.3832;
        $event['longitude'] = 79.3832;
        $event['thumbnail'] = 'https://ih1.redbubble.net/image.24695464.0125/flat,800x800,070,f.u1.jpg';
        $event['frames'] = 50;
        return $event;
    }

    public static function parseItem($item){
        $event = array(
            'id' => isset($item['id']['S']) ? $item['id']['S'] : null,
            'name' => isset($item['name']['S']) ? $item['name']['S'] : null,
            'latitude' => isset($item['latitude']['N']) ? $item['latitude']['N'] : null,
            'longitude' => isset($item['longitude']['N']) ? $item['longitude']['N'] : null,
            'status' => isset($item['status']['S']) ? $item['status']['S'] : null, //NEW, CAPTURING, QUEUED, PROCESSING, FINISHED 
            'thumbnail' => isset($item['thumbnail']['S']) ? $item['thumbnail']['S'] : null, //TODO: S3 image upload with appropriate path or only allow links on the interwebs
            'time_created' => isset($item['time_created']['S']) ? $item['time_created']['S'] : null,
            'num_participants' => isset($item['num_participants']['N']) ? $item['num_participants']['N'] : null, 
            //"time_started" => "time_started",
            //"time_queued" => "time_queued",
            //"time_processed" => "time_processed",
            "participants" => isset($item['participants']['SS']) ? $item['participants']['SS'] : null, //array('SS' => array($event['uid'])),
            "frames" => isset($item['frames']['N']) ? $item['frames']['N'] : null //number of frames? 
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
        
        $result = $this->container->db->putItem(array(
            'TableName' => 'events',
            'Item' => array(        
                'id' => array('S' => $event['id']),
                'name' => array('S' => $event['name']),
                'latitude' =>  array('N' => $event['latitude']), //-79.3832
                'longitude' => array('N' => $event['longitude']), //79.3832
                'status' => array('S' => "NEW"), //NEW, CAPTURING, QUEUED, PROCESSING, FINISHED 
                'thumbnail' => array('S' => $event['thumbnail']), //TODO: S3 image upload with appropriate path or only allow links on the interwebs
                'time_created' => array('S' => $event['time_created']),
                'num_participants' => array('N' => 0), //start with no participants at the beginning
                //"time_started" => "time_started",
                //"time_queued" => "time_started",
                //"time_processed" => "time_started",
                //'participants' => array('SS' => array($event['uid'])),
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
                ':name' => array('S' => $event['name']),
                ':latitude' => array('N' => $event['latitude']),
                ':longitude' => array('N' => $event['longitude']),
                ':thumbnail' => array('S' => $event['thumbnail']),
                ':frames' => array('N' => $event['frames']) 
            ),
            'UpdateExpression' => 'set name = :name, latitude = :latitude, longitude = :longitude, thumbnail = :thumbnail, frames = :frames',
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
                ':CONST_ONE' => array('N' => 1)
            ),
            'UpdateExpression' => 'ADD num_participants :CONST_ONE',
            'ReturnValues' => 'ALL_NEW'
        ));

        $event = self::parseItem($result['Attributes']); 
        return $response->withJson($event);
    }

    function contributeToEvent($request, $response, $args) {
        $id = $request->getAttribute('id');        
        $uid = $request->getParam('uid');

        //TODO: multiple frames? at once? at a single time?
        $files = $request->getUploadedFiles();
        if (empty($files['image'])) {
            throw new Exception('Expected image');
        }
        
        $image = $files['image'];
        $result = $this->container->s3->putObject(array(
            'Bucket'     => 'com.scope',
            'Key'        => "$id/images/$uid.jpg",
            'Body'       => $image->getStream()->getContents(),
        ));
        
        $result = $this->container->db->updateItem (array(
            'TableName' => 'events',
            'Key' => array(
                'id' => array('S' => $id) 
            ),
            'ExpressionAttributeValues' =>  array(
                ':uid' => array('SS' => array($uid))
            ),
            'UpdateExpression' => 'ADD participants :uid',
            'ReturnValues' => 'ALL_NEW'
        ));

        //TODO: if all the participants are accounted for, we start the image pipeline
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