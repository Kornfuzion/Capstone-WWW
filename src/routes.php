<?php
// Routes

require __DIR__ . '/controllers/event.php';
require __DIR__ . '/controllers/model.php';

$app->get('/', function ($request, $response, $args) {
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

new EventController($app);

new ModelController($app);