<?php

/**
 * author: Stefano Volponi
 * In tutto lo script si considera come utente remoto localhost:8001 e come utente locale (quello che deve caricarsi la copia dei feed) localhost:8002.
 * Ad ogni modo le funzioni sono parametrizzate per poter prendere in ingresso qualsiasi indirizzo.
 */

	ini_set('track_errors', 1);


    /**
     * @param string $user nome dell'iser di cui si vuole trovare l'ID
     * @param string $website url del sito
     * @param string $format formato del file di risposta
     * @param boolean $supportAnon specifica se distinguere l'utente anonimo
     * @param string $apiAccessPoint access point risorsa REST
     * @return array
     * @throws Exception
     */
    function findID ($user, $website='http://localhost:8002', $format='json', $supportAnon=true, $apiAccessPoint='/rest/export/json/users'){

        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $website.$apiAccessPoint.'?_format='.$format,
            CURLOPT_USERAGENT => 'Codular Sample cURL Request'
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        if(curl_errno($curl)){
            throw new Exception(curl_error($curl));
        }
        curl_close($curl);
        // Close request to clear up some resources


        $jsonData_array=json_decode($resp,true);
        $user =str_replace(' ', '',$user );

        if ($supportAnon){
            if(strtolower($user)=="anonymous"){
                return  [0,""];
            }
        }


        foreach ($jsonData_array as $element)
        {

            if(strtolower($element['name'][0]['value'])==strtolower($user)){
                return [$element['uid'][0]['value'],$element['uuid'][0]['value']];
            }
        };
        return [-1,""];
    }

    /**
     * @param array $userID vettore contenente ID e UUID dell'autore del post
     * @param string $message testo del messaggio
     * @param string $date data di pubblicazione
     * @param string $photoUrl eventuale url della foto pubblicata nel post (può essere NULL)
     * TODO: non è necessario specificare il website se l'utente ha già in locale l'immagine
     * @param string $otherWebsite website dell'utente remoto (può essere NULL)
     * @param string $target_website website dell'utente locale (può essere NULL)
     * @return array ritorna l'array popolato per la chiamata REST
     * @throws Exception
     */
    function createPost($userID,$message,$date, $photoUrl, $otherWebsite, $target_website){

        $d=strtotime($date);
        $type = "post";

        $data = array(
            "type" => [array(
                "target_id" => $type
            )
            ],
            "user_id" => [array(
                "target_id"=> $userID[0],
                "target_type"=> "user",
                "target_uuid"=> $userID[1],
                "url" => "/en/user/".$userID[0]."/stream"
            )
            ],
            "field_post" => [array(
                "value" => $message,
                "format"=> "basic_html"
            )],
            "created" => [array(
                "value" => date("Y-m-d\\TH:i:sP", $d),
                "format"=> "Y-m-d\\TH:i:sP"
            )],
            "field_visibility" => [array(
                "value"=> "1"
            )],

        );

        if ($photoUrl!=null) {
            $type="photo";

            $photoID = uploadImage($otherWebsite.$photoUrl,getToken($target_website),$target_website);
            $data = array(
                "type" => [array(
                    "target_id" => $type
                )
                ],
                "user_id" => [array(
                    "target_id"=> $userID[0],
                    "target_type"=> "user",
                    "target_uuid"=> $userID[1],
                    "url" => "/en/user/".$userID[0]."/stream"
                )
                ],
                "field_post" => [array(
                    "value" => $message,
                    "format"=> "basic_html"
                )],
                "created" => [array(
                    "value" => date("Y-m-d\\TH:i:sP", $d),
                    "format"=> "Y-m-d\\TH:i:sP"
                )],
                "field_visibility" => [array(
                    "value"=> "1"
                )],

                "field_post_image" => [array(
                    "target_id"=>$photoID
                )]

            );
        }



	    return $data;
    }

    /**
     * CSRF token per autenticazione delle operazioni di POST.
     * @param string $website il sito internet a cui fare la richiesta
     * @return mixed il token
     * @throws Exception
     */
    function getToken($website){
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $website.'/rest/session/token/',
            CURLOPT_USERAGENT => 'Codular Sample cURL Request'
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        if(curl_errno($curl)){
            throw new Exception(curl_error($curl));
        }
        // Close request to clear up some resources
        curl_close($curl);

        return $resp;
    }

    /**
     * @param string $data
     * @param string $token
     * @param string $website sito a cui fare la richiesta
     * @param string $format formato di $data
     * @param string $apiAccessPoint access point risorsa REST
     * @param string $applicationFormat formato del file di risposta (json/hal+json...)
     * @return mixed errors or json output
     * @throws Exception
     */
    function postRequest($data, $token,$website='http://localhost:8002',$apiAccessPoint='/entity/post', $format='json', $applicationFormat='json'){


        $url=$website.$apiAccessPoint.'?_format='.$format;

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'X-CSRF-Token: '.$token,
                'Content-Type: application/'.$applicationFormat
            ),
            CURLOPT_POSTFIELDS => $data
        ));

        // Send the request
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);

        // Check for errors
        if($response === FALSE){
            die(curl_error($ch));
        }
        return  $response;
    }

    /**
     * @param array $postTarget post ID e UUID a cui associare il commento
     * @param array $userID vettore contenente ID e UUID dell'autore del post
     * @param string $message testo del commentp
     * @param string $date data del commento
     * @return array ritorna l'array popolato per la chiamata REST
     */
    function createPostComment($postTarget,$userID,$message,$date){

        $d=strtotime($date);
        $provided_user_uuid=true;
        if ($userID [1]=="") $provided_user_uuid=false;

	    $data=array(
            "comment_type"=>[arraY(
                "target_id" => "post_comment",
                "target_type"=> "comment_type",
                //"target_uuid" => "1a59df9f-40a8-462a-ad34-89e7bce8732b"
            )],
            "status" => [array(
                "value" => true
            )],
            "pid"=>[],
            "entity_id"=>[array(
                "target_id" => $postTarget[0],
                "target_type" => "post",
                "target_uuid"=> $postTarget[1],
                "url"=> "/post/".$postTarget[0]
            )],
            "subject" => [],
            "uid" =>  [array(
                "target_id" => $userID[0],
                "target_type"=> "user",
                "url" => "/it/user/".$userID[0]."/stream",
                ($provided_user_uuid ? "target_uuid" : "") => ($provided_user_uuid ? $userID [1] : ""),
            )],
            "name"=>[],
            "mail"=>[],
            "homepage"=>[],
            "entity_type"=>[array(
                "value" => "post"
            )],
            "field_name"=>[array(
                "value" => "field_post_comments"
            )],
            "default_langcode"=>[array(
                "value" => true
            )],
            "created" => [array(
                "value" => date("Y-m-d\\TH:i:sP", $d),
                "format"=> "Y-m-d\\TH:i:sP"
            )],
            "field_comment_body"=>[array(
                "value" => $message,
                "format" => "basic_html",

            )]
        );

	    return $data;

    }

    /**
     * @param boolean $latest  per scegliere solo il primo post
     * @param string $website url del sito
     * @param string $format formato del file di risposta
     * @param string $apiAccessPoint access point risorsa REST
     * @return mixed elenco dei post dell'utente o solamente il primo
     * @throws Exception
     */
    function getCurrentUserPosts($latest, $website='http://localhost:8002',$apiAccessPoint='/api/rest3/', $format='json'){
        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $website.$apiAccessPoint.'?_format='.$format,
            CURLOPT_USERAGENT => 'Codular Sample cURL Request'
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        if(curl_errno($curl)){
            throw new Exception(curl_error($curl));
        }
        curl_close($curl);
        // Close request to clear up some resources
        $jsonData = json_decode($resp, true);
        if ($latest) return $jsonData[0];
        return $jsonData;
    }

    /**
     * @param array $posts vettore dei posts
     * @param array $author autore  del post di cui si vuole l'ID
     * @param string $date data del post
     * @return array|null ritorna ID e UUID
     */
    function findPostID($posts,$author, $date){
        foreach ($posts as $post){

            try {
                $post_author = findID($post["post_author"]);
            } catch (Exception $e) {
                echo $e, PHP_EOL;
                return null;
            }
            $post_date = $post["created_1"];
            if ($post_author[0]==$author[0] && $post_date==$date){
                return [$post["post_id"],$post["post_uuid"]];
            }
        }
        return null;
    }


    /**
     * @param array $posts vettore dei posts
     * @param array $author autore del commento di cui si vuole l'ID
     * @param string $date data del commento
     * @return array|null ritorna ID e UUID
     */
    function findCommentID($posts,$author, $date){
        foreach ($posts as $post){

            try {
                $comment_author = findID($post["comment_author"]);
            } catch (Exception $e) {
                echo $e, PHP_EOL;
                return null;
            }
            $post_date = $post["created"];
            if ($comment_author[0]==$author[0] && $post_date==$date){
                return [$post["comment_id"],$post["comment_uuid"]];
            }
        }
        return null;
    }

    /**
     * @param array $posts vettore dei posts
     * @param array $ID vettore con ID e UUID
     * @return null|$post
     */
    function getUserPost($posts,$ID){
        foreach ($posts as $post){

            $post_uuid= $ID[1];

            if ($post_uuid==$post["post_uuid"]){
                return $post;
            }
        }
        return null;

    }


    /**
     * Generate a random string, using a cryptographically secure
     * pseudorandom number generator (random_int)
     *
     * For PHP 7, random_int is a PHP core function
     * For PHP 5.x, depends on https://github.com/paragonie/random_compat
     *
     * @param int $length How many characters do we want?
     * @param string $keyspace A string of all possible characters
     *                         to select from
     * @return string
     * @throws Exception
     */
    function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $pieces []= $keyspace[random_int(0, $max)];
        }
        return implode('', $pieces);
    }


    /**
     * @param string $imagePath il path all'immagine di destinazione: può essere un uri
     * @param string $token token per accedere con REST al sito dell'utente locale
     * @param string $website url al sito dell'utente remoto
     * @param string $format formato del file di risposta
     * @param string $applicationFormat formato del file di risposta (json/hal+json...)
     * @param string $apiAccessPoint access point risorsa REST
     * @return mixed  ID dell'immagine appena caricata
     * @throws Exception
     */
    function uploadImage($imagePath, $token, $website,$apiAccessPoint='/entity/file', $format='hal_json', $applicationFormat='hal+json'){

        $imageType = pathinfo($imagePath, PATHINFO_EXTENSION);
        $imageName = random_str(5);
        $image=file_get_contents($imagePath);
        $picture = base64_encode($image);

        $data= array(
            "_links"=> array(
                "type" => array(
                    "href"=> $website."/rest/type/file/image"
                )
            ) ,
          "filemime" =>[array(

              "value"=> "image/".$imageType
          )],
            "type" => [array(
                "target_id" => "image"
            )],
            "data" =>[array(
                "value" => $picture
            )],
            "uri"=>[array(
                "value"=>"public://".$imageName.".".$imageType)],
            "filename"=>[array(
                "value"=>$imageName.".".$imageType)]

        );


        $ch = curl_init($website.$apiAccessPoint.'?_format='.$format);
        curl_setopt_array($ch, array(
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => array(
                'X-CSRF-Token: '.$token,
                'Content-Type: application/'.$applicationFormat
            ),
            CURLOPT_POSTFIELDS => json_encode($data, JSON_PRETTY_PRINT)
        ));

        //echo json_encode($data, JSON_PRETTY_PRINT), PHP_EOL;
        //echo $imageName,PHP_EOL;
        //echo $token,PHP_EOL;

        // Send the request
        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }


        // Check for errors
        if($response === FALSE){
            die(curl_error($ch));
        }

        $rData = json_decode($response, true);

        return $rData['fid'][0]['value'];

    }


    /**
     * DEMO
     * ciclo di aggiornamento dei posts
     * @param string $remote_user_website url utente remoto
     * @param string $local_user_website url utente locale
     * @param string $jsonPath path al file json contenente le informazioni dell'utente remoto
     * @throws Exception
     */
    function updatePostAndComments($remote_user_website,$local_user_website, $jsonPath){


        $latest_post = getCurrentUserPosts(true);
        $latest_post_date= new DateTime( $latest_post["created_1"]);



        $string = file_get_contents($jsonPath);
        $json_a = json_decode($string, true);

        $i=0;
        while  ($i < count($json_a)){
            //se trovo un post pubblicato dopo l'ultimo mio post e l'autore è conosciuto dall'utente
            $post=$json_a[$i];
            if(new DateTime($post["created_1"])>$latest_post_date && findID($post["post_author"])[0] >-1){
                echo $post["created_1"],PHP_EOL;
                $post_author= findID($post["post_author"]);
                $post_date = $post["created_1"];
                $post_message= $post["post_text"];
                $post_photo= $post["field_post_image"];
                $post_data=createPost($post_author,$post_message,$post_date, $post_photo,$remote_user_website,$local_user_website); //genero la struttura per la post REST
                $jsonData = json_encode($post_data, JSON_PRETTY_PRINT);
                echo postRequest($jsonData,getToken($local_user_website),$local_user_website),PHP_EOL; // post
                $post_comments=$post["post_comments"];
                for ($j=$i; $j<$i+$post_comments; $j+=1){ //ciclo sui commenti del post
                    $npost=$json_a[$j];
                    $comment_text=$npost["comment_text"];
                    $comment_author=findID($npost["comment_author"]);
                    $comment_date = $npost["created"];
                    if($comment_author[0]>-1){
                        $usersPost=getCurrentUserPosts(false);
                        $post_comment_data=createPostComment(findPostID($usersPost,$post_author,$post_date),$comment_author,$comment_text,$comment_date);
                        $jsonData = json_encode($post_comment_data, JSON_PRETTY_PRINT);
                        echo postRequest($jsonData,getToken($local_user_website),$local_user_website,"/comment"),PHP_EOL;
                    }
                }
                if ($post_comments!=0){
                    $i+=($post_comments-1);
                }
            }
            else{
                $post_author= findID($post["post_author"]);
                $post_date = $post["created_1"];
                $usersPost=getCurrentUserPosts(false); //refresh
                $post_info=findPostID($usersPost,$post_author,$post_date); //recupero l'ID del post (dell'utente corrente) basandomi sulle info fornite da remoto
                $post_comments=$post["post_comments"]; //recupero il numero dei commenti
                if ($post_info!=null && $post_author[0]>-1){ //se c'è un post effettivamente creato che va aggiornato
                    $upost=getUserPost($usersPost,$post_info); //recupero le informazioni del post
                    $new_comments=$post_comments-$upost["post_comments"]; //guardo quanti commenti vanno aggiornati
                    if ($new_comments>0){
                        for ($j=$i; $j<$i+$post_comments; $j+=1){ //ciclo sui commenti del post
                            $npost=$json_a[$j];
                            $comment_text=$npost["comment_text"];
                            $comment_author=findID($npost["comment_author"]);
                            $comment_date = $npost["created"];
                            if(findCommentID($usersPost,$comment_author,$npost["created"])==null){
                                $usersPost=getCurrentUserPosts(false);
                                $post_comment_data=createPostComment(findPostID($usersPost,$post_author,$post_date),$comment_author,$comment_text,$comment_date);
                                $jsonData = json_encode($post_comment_data, JSON_PRETTY_PRINT);
                                echo postRequest($jsonData,getToken($local_user_website),$local_user_website,"/comment"),PHP_EOL;
                            }
                        }

                    }
                }
                if ($post_comments!=0){
                    $i+=($post_comments-1);
                }
            }
            $i+=1;
        }
    }


    function updateLikes(){
        //TODO ...
    }



    $update_request=1;
    $remote_user_website="http://localhost:8001";
    $local_user_website ="http://localhost:8002";
    $dir="json_results/results3.json";
    switch ($update_request) {
        case 1:
            try {
                updatePostAndComments($remote_user_website,$local_user_website,$dir);
            } catch (Exception $e) {
                echo $e,PHP_EOL;
            }
            break;
        case 2:
            updateLikes();
            break;
        case 3:
             break;

        default:
            break;
    }













?>
