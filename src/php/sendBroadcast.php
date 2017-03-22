<?php

function sendBroadcast($title, $body, $topic) {
$url = "https://fcm.googleapis.com/fcm/send";

/*
$data = "{ \"notification\": {
    \"title\": \"Portugal vs. Denmark\",
    \"body\": \"5 to 1\"
  }
}";
*/
$data = array(
    'data' => array(
                          'title' => $title,
                          'body' => $body      
                      ),
    'to' => '/topics/' . $topic
);

$content = json_encode($data, JSON_UNESCAPED_SLASHES);
echo $content;
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
echo "SENDING NOTIFICATION TO ANDROID\n";
$context  = stream_context_create($options);
echo $context;
$result = file_get_contents($url, false, $context);
if ($result === FALSE) { echo "WE DUN GOOFD SUN"; }

var_dump($result);
}

//sendBroadcast(1, 2, 'test');
?>
