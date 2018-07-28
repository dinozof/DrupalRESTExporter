<?php  


	ini_set('track_errors', 1);


    /**
     * @param string $apiAccessPoint l'url dell'access point
     * @param string $saveFileName   nome file per il salvataggio
     * @param string $format tipo di dato che si vuole scaricare
     * @param string $remoteServer  url server remoto
     * @throws Exception Se la richiesta fallisce
     */

	function downloadFeeds($apiAccessPoint,$saveFileName,$format='json', $remoteServer='http://localhost:8001/'){


        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $remoteServer.$apiAccessPoint.'/?_format='.$format,
            CURLOPT_USERAGENT => 'Codular Sample cURL Request'
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        // Close request to clear up some resources

        if(curl_errno($curl)){
            throw new Exception(curl_error($curl));
        }
        curl_close($curl);


        //print_r($result);
        $fp = fopen($saveFileName, 'w');
        if ($fp)
        {
            echo 'file is open',PHP_EOL;
        }
        else
        {

            echo 'file is not opened, or not found: ',$php_errormsg,PHP_EOL;
        }
        $jsonData = json_encode(json_decode($resp), JSON_PRETTY_PRINT);
        fwrite($fp, $jsonData);   //here it will print the array pretty
        fclose($fp);



	}


	$dir="json_results";
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
	for ($i=1; $i <6 ; $i++) {

        try {
            downloadFeeds('api/rest' . $i, $dir."/".'results' . $i . '.json', 'json');
        } catch (Exception $e) {
            echo $e, PHP_EOL;
        }

    }



?>
     