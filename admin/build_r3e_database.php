<?php

write ("PHP version: " . phpversion());
mysqli_report(MYSQLI_REPORT_STRICT);

$json = downloadFile();
$db = getDatabaseConnection();
fillDatabase($db, $json);
$db->close();

write ("Finished!");

function downloadFile() {
    write("Downloading file...");
    $filePath = "../tempFiles/store_cars_".uniqid().".json";

    if(!copy("http://game.raceroom.com/store/cars/?json", $filePath))
        write("Can't copy file.");
    
    $fileContent = file_get_contents($filePath, "r");
    unlink($filePath);
    
    if ($fileContent === false) {
        write("Unable to open file.");
        exit;
    }
    
    $json = json_decode($fileContent, true);
    if ($json == null) die("Can't parse json.");
    return $json;
}

function fillDatabase($db, $json) {
    write ("Feeding database...");

    $classes = array();
    
    foreach ($json["context"]["c"]["sections"][0]["items"] as $itemKey => $itemValue) {
        
        if($itemValue["type"] == "car") { // actually there's only 'car' type in json
            $className = $itemValue["car_class"]["name"];
            preg_match('/-(\d+)-image-big.[A-Za-z]+$/', $itemValue["car_class"]["image"]["big"], $matches);
            $classId = $matches[1];
    
            if(!array_key_exists($classId, $classes)) {
                $db->query("INSERT INTO classes (id, name) VALUES ({$classId}, '{$className}');");
                $classes[$classId] = $className;
            }
    
            $carId = $itemValue["cid"];
            $carName = $itemValue["name"];
            $defaultLiveryId = $itemValue["livery_id"];
    
            $db->query("INSERT INTO cars (id, name, classId, defaultLiveryId)
                            VALUES ({$carId},'{$carName}',{$classId}, {$defaultLiveryId});");
            
            foreach ($itemValue["content_info"]["livery_images"] as $liveryKey => $liveryValue) {
    
                $liveryId = $liveryValue["cid"];
                $liveryTitle = $liveryValue["title"];
                $imageUrl = $liveryValue["thumb"];
                $isFree = $liveryValue["free"] == true ? 1 : 0;
    
                $liveryNumber = 9999;
                preg_match('/^#(\d+)/', $liveryValue["name"], $matches);
                if (count($matches) > 1)
                    $liveryNumber = $matches[1];
    
                $db->query("INSERT INTO liveries (id, title, carId, number, imageUrl, isFree) VALUES ({$liveryId},'{$liveryTitle}', {$carId}, {$liveryNumber}, '{$imageUrl}', {$isFree});"); // TODO take only end of url
            }
        }
    }
}

function databaseExists($connection, $dbName) {
    return $connection->query ("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbName}'")->num_rows == 1;
}

function getDatabaseConnection() {
    require_once "../auth.php";

    try {
        $db = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
    } catch (Exception $e ) {
        var_dump($e);
        exit;
    }
    
    if ($db->connect_error)
        die("Connection failed: " . $db->connect_error);
        
    if(!databaseExists($db, $dbName)) 
        createDatabase($db, $dbName);
     else 
        emptyDatabase($db, $dbName);

    return $db;
}

function createDatabase ($connection, $dbName) {
    write("Creating database...");

    if ($connection->query("CREATE DATABASE IF NOT EXISTS {$dbName};") === false)
        write("Error creating database: " . $connection->error);

    $connection->query("USE {$dbName};");

    $connection->query("CREATE TABLE cars (id INT NOT NULL, name TEXT NOT NULL, classId INT NOT NULL, defaultLiveryId INT NOT NULL, PRIMARY KEY(id));");
    $connection->query("CREATE TABLE classes (id INT NOT NULL, name TEXT NOT NULL, PRIMARY KEY(id));");
    $connection->query("CREATE TABLE liveries (id INT NOT NULL, title TEXT NOT NULL, carId INT NOT NULL, number INT NOT NULL,
                            imageUrl TEXT NOT NULL, isFree INT NOT NULL, PRIMARY KEY(id));");

    $connection->query("CREATE TABLE IF NOT EXISTS users (id INT NOT NULL AUTO_INCREMENT, name TEXT NOT NULL, PRIMARY KEY(id));");
    $connection->query("CREATE TABLE IF NOT EXISTS userLiveries (userId INT NOT NULL, liveryId INT NOT NULL);");
    // I need this only cause R3E store doesn't list the default liveries. This table helps to know when a default livery is not owned in the case we don't own the car.
    $connection->query("CREATE TABLE IF NOT EXISTS userCars (userId INT NOT NULL, carId INT NOT NULL);");
}

function emptyDatabase ($connection, $dbName) {
    write("Emptying database...");

    $connection->query("USE {$dbName};");

    $connection->query("TRUNCATE TABLE cars;");
    $connection->query("TRUNCATE TABLE classes;");
    $connection->query("TRUNCATE TABLE liveries;");
}

function write($text) {
    echo $text."<br />";
}

?>