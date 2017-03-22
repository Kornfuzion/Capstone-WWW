<?php
// Routes

require __DIR__ . '/controllers/event.php';
require __DIR__ . '/controllers/model.php';
require __DIR__ . '/php/sendBroadcast.php';

$app->get('/', function ($request, $response, $args) {
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/broadcast', function ($request, $response, $args) {
    // Render index view
    $topic = $request->getQueryParam('topic');
    sendBroadcast(0, 0, $topic);	
    return $response->withStatus(200);
    //return $this->renderer->render($response, 'sendBroadcast.php', $args);
});

new EventController($app);

new ModelController($app);
