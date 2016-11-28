<?php
$longitude = $_GET['longitude'];
$latitude = $_GET['latitude'];

$host = 'localhost';
$user = 'root';
$password = '';

$database = 'events';
$locations_table = 'locations';
$thumbnails_table = 'thumbnails';

// This is about 20km in terms on longitude/latitude
$threshold = 0.2;

// Connecting, selecting database
$mysqli =  new mysqli($host, $user, $password, $database)
    or die('Could not connect: ' . mysql_error());

// Performing SQL query
$query = 'SELECT * FROM ' . $locations_table . ' NATURAL JOIN ' . $thumbnails_table . 
    ' WHERE longitude >= ' . ($longitude - $threshold) .
      ' and longitude <= ' . ($longitude + $threshold) .  
      ' and latitude  >= ' . ($latitude - $threshold) .  
      ' and latitude  <= ' . ($latitude + $threshold);

$result = $mysqli->query($query);

while($row = $result->fetch_row()) {
  $rows[]=$row;
}

echo json_encode($rows);

// Printing results in HTML

$result->free();
$mysqli->close();
?>
