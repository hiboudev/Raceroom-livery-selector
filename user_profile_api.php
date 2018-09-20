<?php

if(isset($_GET['username']))
    synchronizeUserProfile($_GET['username']);
else if (isset($_GET['checkUsername']))
    checkUsername($_GET['checkUsername']);


/**
 * Print (all strings):
 * - 0: registered user.
 * - 1: not registered user.
 */
function checkUsername ($username) {
    $connection = getDatabaseConnection();
    $userExists = userExists($connection, $username);
    $connection->close();
    
    exit($userExists ? "0" : "1");
}

/**
 * Print (all strings):
 * - username if valid.
 * - 1: profile not found on R3E store
 * - 2: error reading downloaded profile file
 * - 3: can't parse json
 */
function synchronizeUserProfile ($username) {
    $connection = getDatabaseConnection();
    $json = downloadUserProfile ($username);
    $userId = checkUserExists($connection, $username);

    $connection->query("DELETE FROM userLiveries WHERE userId = $userId;");
    
    // TODO Moyen d'optimiser en une seule boucle ?
    foreach ($json["context"]["c"]["purchased_content"] as $contentKey => $contentValue)
        foreach ($contentValue["items"] as $itemKey => $itemValue)
            if($itemValue["type"] == "livery")
                $connection->query("INSERT into userLiveries VALUES ({$userId}, {$itemValue["id"]});");
    
    foreach ($json["context"]["c"]["purchased_content"] as $contentKey => $contentValue)
        foreach ($contentValue["items"] as $itemKey => $itemValue)
            if ($itemValue["type"] == "car") {
                $carId = $itemValue["id"];
                $result = $connection->query("SELECT defaultLiveryId FROM cars WHERE id={$carId}");
                $defaultLiveryId = $result->fetch_array()[0]; // TODO La RUF n'a pas de livrée par défaut, voir pas de livrée du tout

                $result = $connection->query("SELECT userLiveries.liveryId, liveries.carId FROM userLiveries, liveries WHERE userLiveries.userId={$userId} AND liveries.carId={$carId} AND userLiveries.liveryId=liveries.id");
                
                if($result->num_rows == 0)
                    $connection->query("INSERT into userLiveries VALUES ({$userId}, {$defaultLiveryId});");
            }

    $connection->close();

    exit($username); // Réponse pour JS
}

function downloadUserProfile ($username) {
    $fileName = 'tempFiles/' . $username . uniqid(); //'tempFiles/hiboudev';
    
    @copy("http://game.raceroom.com/users/{$username}/purchases?json", $fileName) or exit('1');

    $fileContent = file_get_contents($fileName, "r");
    if ($fileContent === false)
        exit('2');

    unlink ($fileName);
    
    $json = json_decode($fileContent, true);
    if($json == null)
        exit ('3');

    return $json;
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