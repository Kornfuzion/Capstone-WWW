<?php
// Routes

require __DIR__ . '/controllers/event.php';

$app->get('/', function ($request, $response, $args) {
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/model/{eventId}', function ($request, $response, $args) {
	$eventId = $request->getAttribute('eventId');
	$modelHost = "http://com.scope.model.s3.amazonaws.com/";
	$modelPath = $modelHost . $eventId . "/"  . "1.ply"; //TODO: multiple frames
    //var_dump($args);
    //var_dump($response);
    //$response->withAttribute('modelPath', $modelPath);
    // Render model viewer
    //return $response->withStatus(200);
    $args['modelPath'] = $modelPath;
    return $this->renderer->render($response, 'model-viewer.phtml', $args);
});

new EventController($app);