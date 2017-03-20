<?php

class ModelController {

    private $container;

    public static $modelHost = "http://com.scope.s3.amazonaws.com/";

    public function __construct($app) {
        $this->container = $app->getContainer(); 
        $app->get('/model/point/{eventId}', array($this, 'pointCloudViewer'));
        $app->get('/model/mesh/{eventId}', array($this, 'meshViewer'));

    }

    function pointCloudViewer ($request, $response, $args) {
        $eventId = $request->getAttribute('eventId');
        $modelPath = self::$modelHost . "$eventId/model/point/0.ply"; //TODO: multiple frames
        $args['modelPath'] = $modelPath; 
        return $this->container->renderer->render($response, 'model-viewer.phtml', $args);
    }

    function meshViewer ($request, $response, $args) {
        //TODO;
        $eventId = $request->getAttribute('eventId');
        $modelPath = self::$modelHost . "$eventId/model/mesh/0.ply"; //TODO: multiple frames
        $args['modelPath'] = $modelPath; 
        return $this->container->renderer->render($response, 'model-viewer.phtml', $args);
    }
}