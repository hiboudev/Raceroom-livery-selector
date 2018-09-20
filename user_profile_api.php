<?php

if(isset($_GET['username']))
    synchronizeUserProfile($_GET['username']);
else if (isset($_GET['checkUsername']))
    checkUsername($_GET['checkUsername']);


function checkUsername ($username) {
    $connection = getDatabaseConnection();
    if(!userExists($connection, $username)) {
        $connection->close(); // TODO revoir les ouvertures/fermetures de connexion
        synchronizeUserProfile($username);
    }
    echo true;
}

function synchronizeUserProfile ($username) {
    $connection = getDatabaseConnection();
    $json = downloadUserProfile ($username);
    $userId = checkUserExists($connection, $username);

    // write("Deleting current user entries...");

    $connection->query("DELETE FROM userLiveries WHERE userId = $userId;");
    
    // write("Feeding user profile...");

    $count = 0;
    // $count2 = 0;
    $liveries = array();
    foreach ($json["context"]["c"]["purchased_content"] as $contentKey => $contentValue)
        foreach ($contentValue["items"] as $itemKey => $itemValue)
            if($itemValue["type"] == "livery") {

                $connection->query("INSERT into userLiveries VALUES ({$userId}, {$itemValue["id"]});");

                // foreach ($itemValue["content_info"]["livery_images"] as $liveryKey => $liveryValue) {
                    //write($itemValue["owned"]);
                    // $count2++;
                    // write($itemValue["id"]);

                    // if(!array_key_exists($liveryValue["cid"], $liveries)) { // So many dupplicates in this json, 11000 liveries instead of 1000... :'(
                    //     $connection->query("INSERT into userLiveries VALUES ({$userId}, {$liveryValue["cid"]});");
                    //     $liveries[$liveryValue["cid"]] = true;
                    // }
                // }
            } 
    
    foreach ($json["context"]["c"]["purchased_content"] as $contentKey => $contentValue)
        foreach ($contentValue["items"] as $itemKey => $itemValue)
            if ($itemValue["type"] == "car") {
                $carId = $itemValue["id"];
                $result = $connection->query("SELECT defaultLiveryId FROM cars WHERE id={$carId}");
                $defaultLiveryId = $result->fetch_array()[0]; // TODO La RUF n'a pas de livrée par défaut, voir pas de livrée du tout

                $result = $connection->query("SELECT userLiveries.liveryId, liveries.carId FROM userLiveries, liveries WHERE userLiveries.userId={$userId} AND liveries.carId={$carId} AND userLiveries.liveryId=liveries.id");
                
                if($result->num_rows == 0) {
                    $connection->query("INSERT into userLiveries VALUES ({$userId}, {$defaultLiveryId});");
                }

                // foreach ($itemValue["content_info"]["livery_images"] as $carLiveryKey => $carLiveryValue) {
                //     // write($carLiveryValue["cid"]);
                //     // write(var_dump($carLiveryValue["owned"] == 'true'));
                //     // $connection->query("INSERT into userLiveries VALUES ({$userId}, {$carLiveryValue["cid"]});");
                // }
            }

    $connection->close();

    echo $username; // Réponse pour JS
}

function downloadUserProfile ($username) {
    $fileName = 'tempFiles/' . $username . uniqid(); //'tempFiles/hiboudev';

    if(!copy("http://game.raceroom.com/users/{$username}/purchases?json", $fileName))
        die("Can't copy file.");

    // write ("File copied.");

    $fileContent = file_get_contents($fileName, "r");
    if ($fileContent === false)
        die("Unable to open file.");
    
    // $fileContent = str_replace("true", "\"true\"", $fileContent);
    // $fileContent = str_replace("false", "\"false\"", $fileContent);

    unlink ($fileName);

    return json_decode($fileContent, true);
}

function getDatabaseConnection () {
    require "auth.php";
    $connection = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
    $connection->query("USE r3e_data;");

    return $connection;
}

function userExists($connection, $username) {
    return $connection->query("SELECT id FROM users WHERE name='{$username}';")->num_rows == 1;
}

function checkUserExists($connection, $username) {
    $result = $connection->query("SELECT id FROM users WHERE name='{$username}';");
    
    if ($result->num_rows == 0) {
        $connection->query("INSERT INTO users (name) VALUES ('{$username}');");
        $result = $connection->query("SELECT LAST_INSERT_ID();");
    }
    
    return $result->fetch_array()[0];
}

function write($text) {
    echo $text."<br />";
}

?>