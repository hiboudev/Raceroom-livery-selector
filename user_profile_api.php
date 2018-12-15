<?php

if (isset($_GET['username'])) {
    synchronizeUserProfile($_GET['username']);
} else if (isset($_GET['checkUsername'])) {
    checkUsername($_GET['checkUsername']);
}

/**
 * Returns:
 * - 0: registered user.
 * - 1: not registered user.
 */
function checkUsername($username)
{
    $connection = getDatabaseConnection();
    $userExists = userExists($connection, $username);
    $connection->close();

    exit($userExists ? "0" : "1");
}

/**
 * Returns:
 * - username if valid.
 * - 1: profile not found on R3E store
 * - 2: error reading downloaded profile file
 * - 3: can't parse json
 */
function synchronizeUserProfile($username)
{
    $connection = getDatabaseConnection();
    $json       = downloadUserProfile($username);
    $userId     = checkUserExists($connection, $username);

    $connection->query("DELETE FROM userLiveries WHERE userId = $userId;");

    foreach ($json["context"]["c"]["purchased_content"] as $contentKey => $contentValue) {
        foreach ($contentValue["items"] as $itemKey => $itemValue) {
            if ($itemValue["type"] == "livery") {
                $connection->query("INSERT into userLiveries VALUES ($userId, {$itemValue["id"]});");
            }
        }
    }

    $connection->close();

    exit($username);
}

function downloadUserProfile($username)
{
    $fileContent = file_get_contents("http://game.raceroom.com/users/$username/purchases?json");
    if ($fileContent === false) {
        exit('2');
    }

    $json = json_decode($fileContent, true);
    if ($json == null) {
        exit('3');
    }

    return $json;
}

function getDatabaseConnection()
{
    require_once "auth.php";
    $connection = new mysqli($dbAddress, $dbUserName, $dbPassword);
    $connection->query("USE $dbName;");

    return $connection;
}

function userExists($connection, $username)
{
    return $connection->query("SELECT id FROM users WHERE name='$username';")->num_rows == 1;
}

function checkUserExists($connection, $username)
{
    $result = $connection->query("SELECT id FROM users WHERE name='$username';");

    if ($result->num_rows == 0) {
        $connection->query("INSERT INTO users (name) VALUES ('$username');");
        $result = $connection->query("SELECT LAST_INSERT_ID();");
    }

    return $result->fetch_array()[0];
}
