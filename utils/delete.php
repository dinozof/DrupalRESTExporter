<?php
/**
 Semplice script per pulire i post caricati in remoto.
**/
$start=0;
$stop=250;
for ($i=$start; $i<$stop; $i++){
$ch = curl_init("http://localhost:8002/post/".$i."?_format=json");
curl_setopt_array($ch, array(
CURLOPT_RETURNTRANSFER => TRUE,
CURLOPT_HTTPHEADER => array(
'X-CSRF-Token: nHqgVaoVnDHZMsglGEQHSfjBBVOyYjIgB3ET44wUiFs',
'Content-Type: application/json'
),
));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");


// Send the request
$response = curl_exec($ch);
echo $response,PHP_EOL;
}

?>