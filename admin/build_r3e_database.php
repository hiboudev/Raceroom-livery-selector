<?php

function write($text) {
    echo $text."<br />";
}

write ("PHP version: " . phpversion());

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

mysqli_report(MYSQLI_REPORT_STRICT);

try {
    require "../auth.php";
    $db = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
} catch (Exception $e ) {
    var_dump($e);
    exit;
}

if ($db->connect_error)
    die("Connection failed: " . $db->connect_error);

$dbName = "r3e_data";

if(!databaseExists($db, $dbName)) {
    createDatabase($db, $dbName);
} else {
    emptyDatabase($db, $dbName);
}

$start = microtime(true);

write ("Feeding database...");

$classes = array();

$count = 0;

foreach ($json["context"]["c"]["sections"][0]["items"] as $itemKey => $itemValue)
    // actually there's only 'car' type in json
    if($itemValue["type"] == "car") {
        $className = $itemValue["car_class"]["name"];
        if(!array_key_exists($className, $classes)) {
            $db->query("INSERT INTO classes (name) VALUES ('{$className}');");
            $result = $db->query("SELECT LAST_INSERT_ID();");
            $classes[$className] = $result->fetch_array()[0];
        }

        $carId = $itemValue["cid"];
        $carName = $itemValue["name"];
        $carClassId = $classes[$className];
        $defaultLiveryId = $itemValue["livery_id"];

        $db->query("INSERT INTO cars (id, name, classId, defaultLiveryId)
                        VALUES ({$carId},'{$carName}',{$carClassId}, {$defaultLiveryId});");
        
        foreach ($itemValue["content_info"]["livery_images"] as $liveryKey => $liveryValue) {

            $liveryId = $liveryValue["cid"];
            $liveryTitle = $liveryValue["title"];
            $imageUrl = $liveryValue["thumb"];
            // write("exists: ".array_key_exists("free", $liveryValue));
            // write("free: ".($liveryValue["free"]==true));
            // echo "[".true."-".false."]";
            $isFree = $liveryValue["free"] == true ? 1 : 0;

            $count++;

            $liveryNumber = 9999;
            preg_match('/^#(\d+)/', $liveryValue["name"], $matches);
            if (count($matches) > 1) {
                $liveryNumber = $matches[1];
            }

            $db->query("INSERT INTO liveries (id, title, carId, number, imageUrl, isFree) VALUES ({$liveryId},'{$liveryTitle}', {$carId}, {$liveryNumber}, '{$imageUrl}', {$isFree});"); // TODO take only end of url
        }
    }
write( "<br />count:".$count);

$db->close();
write("Done in ". (microtime(true) - $start) ." ms.");

function databaseExists($connection, $dbName) {
    return $connection->query ("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbName}'")->num_rows == 1;
}

function createDatabase ($connection, $dbName) {
    write("Creating database...");

    if ($connection->query("CREATE DATABASE IF NOT EXISTS {$dbName};") === TRUE) {
        write("Database created successfully");
    } else {
        write("Error creating database: " . $connection->error);
    }

    $connection->query("USE {$dbName};");

    $connection->query("CREATE TABLE cars (id INT NOT NULL, name TEXT NOT NULL, classId INT NOT NULL, defaultLiveryId INT NOT NULL, PRIMARY KEY(id));");
    $connection->query("CREATE TABLE classes (id INT NOT NULL AUTO_INCREMENT, name TEXT NOT NULL, PRIMARY KEY(id));");
    $connection->query("CREATE TABLE liveries (id INT NOT NULL, title TEXT NOT NULL, carId INT NOT NULL, number INT NOT NULL,
                            imageUrl TEXT NOT NULL, isFree INT NOT NULL, PRIMARY KEY(id));");

    $connection->query("CREATE TABLE IF NOT EXISTS users ( id INT NOT NULL AUTO_INCREMENT, name TEXT NOT NULL, PRIMARY KEY(id));");
    $connection->query("CREATE TABLE IF NOT EXISTS userLiveries ( userId INT NOT NULL, liveryId INT NOT NULL);");
    // I need this only cause R3E store doesn't list the default liveries. This table helps to know when a default livery is not owned in the case we don't own the car.
    $connection->query("CREATE TABLE IF NOT EXISTS userCars ( userId INT NOT NULL, carId INT NOT NULL);");
}

function emptyDatabase ($connection, $dbName) {
    write("Emptying database...");

    $connection->query("USE {$dbName};");

    $connection->query("TRUNCATE TABLE cars;");
    $connection->query("TRUNCATE TABLE classes;");
    $connection->query("TRUNCATE TABLE liveries;");
}

?>