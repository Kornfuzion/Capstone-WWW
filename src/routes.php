<?php
// Routes

require __DIR__ . '/controllers/event.php';

$app->get('/', function ($request, $response, $args) {
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

$app->get('/model', function ($request, $response, $args) {
    // Render model viewer
    return $this->renderer->render($response, 'model-viewer.phtml', $args);
});

new EventController($app);