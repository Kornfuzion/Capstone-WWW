<?php
    ini_set('display_errors',1);
    error_reporting(E_ALL);
    $upload_path = $_SERVER['DOCUMENT_ROOT'] . "/src/php/uploads/";
     
    $file_path = $upload_path . basename($_FILES['file']['name']);
    $file_size = $_FILES['file']['size'];
    //phpinfo();
    echo "got file " . $file_path . " size " . $file_size . "\n";
    // do upload logic here
   
    if(move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
        echo "success";
    } else{
        echo "fail";
    }

    if (!is_dir($upload_path)) echo "not a dir\n";
    if (!is_writable($upload_path)) echo "not writable\n";
    echo "HMM\n";
?>
