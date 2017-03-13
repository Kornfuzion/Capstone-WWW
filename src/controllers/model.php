<?php

class ModelController {

    private $container;

    public static $modelHost = "http://com.scope.model.s3.amazonaws.com/";

    public function __construct($app) {
        $this->container = $app->getContainer(); 
        $app->get('/model/point/{eventId}', array($this, 'pointCloudViewer'));
        $app->get('/model/mesh/{eventId}', array($this, 'meshViewer'));

    }

    function pointCloudViewer ($request, $response, $args) {
        $eventId = $request->getAttribute('eventId');
        $modelPath = self::$modelHost . "/point/" . $eventId . "/"  . "1.ply"; //TODO: multiple frames
        $args['modelPath'] = $modelPath; 
        return $this->container->renderer->render($response, 'model-viewer.phtml', $args);
    }

    function meshViewer ($request, $response, $args) {
        //TODO;
        $eventId = $request->getAttribute('eventId');
        $modelPath = self::$modelHost . "/mesh/" . $eventId . "/"  . "1.ply"; //TODO: multiple frames
        $args['modelPath'] = $modelPath; 
        return $this->container->renderer->render($response, 'model-viewer.phtml', $args);
    }
}