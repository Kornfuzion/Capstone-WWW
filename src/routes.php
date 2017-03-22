<?php
// Routes

require __DIR__ . '/controllers/event.php';
require __DIR__ . '/controllers/model.php';

$app->get('/', function ($request, $response, $args) {
    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

function sendBroadcast($title, $body, $topic) {
    $url = "https://fcm.googleapis.com/fcm/send";

    $data = array(
        'data' => array(
            'title' => $title,
            'body' => $body      
        ),
        'to' => "/topics/$topic" 
    );

    $content = json_encode($data, JSON_UNESCAPED_SLASHES);
    $requestHeaders = array(
        'Content-type: application/json',
        'Authorization: key=AAAAkH7zsZQ:APA91bERB53BhlshsRLZ1p82VSih4d_GDs9eE9jdCaRiCFXhNls1_EZAmVBhNMyYF_iDANy6m9ReJ_PIfdoQlRsy5lM1wGI5V0EhuajBrmZeXDjPXlAwt2Olns3VnyTBny5UWjk3Uhdi',
        sprintf('Content-Length: %d', strlen($content))
    );

    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => implode("\r\n", $requestHeaders), 
            'method'  => 'POST',
            'content' => $content
        )
    );
    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

$app->get('/broadcast', function ($request, $response, $args) {
    $topic = $request->getQueryParam('topic');
    $result = sendBroadcast(0,0, $topic);
    return $response->withJson($result);
});

new EventController($app);

new ModelController($app);
