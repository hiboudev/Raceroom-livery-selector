<?php

write("PHP version: " . phpversion());
mysqli_report(MYSQLI_REPORT_STRICT);

$db = getDatabaseConnection();
fillDatabase($db, getStoreCarList(), getLiveryDriverList());
$db->close();

write("Finished!");

function getLiveryDriverList()
{
    write("Downloading driver list...");

    $url               = "https://raw.githubusercontent.com/sector3studios/r3e-spectator-overlay/master/r3e-data.json";
    $fileContent       = file_get_contents($url);
    $driversByLiveryId = [];

    if ($fileContent !== false) {
        // Removing invalid ';' at end of json.
        $fileContent = preg_replace("/;\s*$/", '', $fileContent);
        $json        = json_decode($fileContent, true);

        if ($json != null) {
            foreach ($json['liveries'] as $livery) {
                $liveryId = intval($livery['Id']);
                $drivers  = "";

                foreach ($livery['drivers'] as $driver) {
                    if ($drivers != "") {
                        $drivers .= ", ";
                    }
                    $drivers .= "{$driver['Forename']} {$driver['Surname']}";
                }
                $driversByLiveryId[$liveryId] = $drivers;
            }
        } else {
            write("Error parsing JSON from URL: $url");
        }
    } else {
        write("Error getting file from URL: $url");
    }

    return $driversByLiveryId;
}

function getStoreCarList()
{
    write("Downloading store car list...");
    $filePath = "../tempFiles/store_cars_" . uniqid() . ".json";

    // TODO utiliser directement file_get_content()
    if (!copy("http://game.raceroom.com/store/cars/?json", $filePath)) {
        die("Can't copy file.");
    }

    $fileContent = file_get_contents($filePath, "r");
    unlink($filePath);

    if ($fileContent === false) {
        die("Unable to open file.");
    }

    $json = json_decode($fileContent, true);
    if ($json == null) {
        die("Can't parse json.");
    }

    return $json;
}

function fillDatabase($db, $storeCarList, $liveryDriverList)
{
    write("Feeding database...");

    $classes = array();

    foreach ($storeCarList["context"]["c"]["sections"][0]["items"] as $itemKey => $itemValue) {

        if ($itemValue["type"] == "car") {
            // actually there's only 'car' type in json
            $className = $itemValue["car_class"]["name"];
            preg_match('/-(\d+)-image-big.[A-Za-z]+$/', $itemValue["car_class"]["image"]["big"], $matches);
            $classId = $matches[1];

            if (!array_key_exists($classId, $classes)) {
                query($db, "INSERT INTO classes (id, name)
                            VALUES ($classId, '$className');");

                $classes[$classId] = $className;
            }

            $carId   = $itemValue["cid"];
            $carName = $itemValue["name"];

            query($db, "INSERT INTO cars (id, name, classId)
                        VALUES ($carId,'$carName',$classId);");

            foreach ($itemValue["content_info"]["livery_images"] as $liveryKey => $liveryValue) {

                $liveryId    = intval($liveryValue["cid"]);
                $liveryTitle = $liveryValue["title"];
                $imageUrl    = $liveryValue["thumb"];
                $isFree      = $liveryValue["free"] == true ? 1 : 0;

                $liveryNumber = 9999;
                preg_match('/^#(\d+)/', $liveryValue["name"], $matches);
                if (count($matches) > 1) {
                    $liveryNumber = $matches[1];
                }

                $drivers = array_key_exists($liveryId, $liveryDriverList) ? $liveryDriverList[$liveryId] : "";

                query($db, "INSERT INTO liveries (id, title, carId, classId, number, imageUrl, isFree, drivers)
                            VALUES ($liveryId, \"$liveryTitle\", $carId, $classId, $liveryNumber, \"$imageUrl\", $isFree, \"$drivers\");");
            }
        }
    }
}

function getDatabaseConnection()
{
    require_once "../auth.php";

    try {
        $db = new mysqli($dbAddress, $dbUserName, $dbPassword);
    } catch (Exception $e) {
        var_dump($e);
        exit;
    }

    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error);
    }

    if (!databaseExists($db, $dbName)) {
        createDatabase($db, $dbName);
    } else {
        emptyDatabase($db, $dbName);
    }

    return $db;
}

function databaseExists($connection, $dbName)
{
    return query($connection, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'")->num_rows == 1;
}

function createDatabase($connection, $dbName)
{
    write("Creating database...");

    query($connection,
        "CREATE DATABASE IF NOT EXISTS {$dbName};
        USE {$dbName};

        CREATE TABLE cars (id INT NOT NULL, name TEXT NOT NULL, classId INT NOT NULL, PRIMARY KEY(id));
        CREATE TABLE classes (id INT NOT NULL, name TEXT NOT NULL, PRIMARY KEY(id));
        CREATE TABLE liveries (id INT NOT NULL, title TEXT NOT NULL, carId INT NOT NULL, classId INT NOT NULL, number INT NOT NULL,
                                imageUrl TEXT NOT NULL, isFree INT NOT NULL, drivers TEXT NOT NULL, PRIMARY KEY(id));
        CREATE TABLE users (id INT NOT NULL AUTO_INCREMENT, name TEXT NOT NULL, PRIMARY KEY(id));
        CREATE TABLE userLiveries (userId INT NOT NULL, liveryId INT NOT NULL);
    ");
}

function emptyDatabase($connection, $dbName)
{
    write("Emptying database...");
    // Can't send truncate query with another one (shows an error but works... o.O).
    query($connection, "USE $dbName; ");
    query($connection, "TRUNCATE TABLE cars;");
    query($connection, "TRUNCATE TABLE classes;");
    query($connection, "TRUNCATE TABLE liveries;");
}

function query($db, $sql)
{
    $result = $db->query($sql);

    if (!$result) {
        die("ERROR: " . $db->error);
    }

    return $result;
}

function write($text)
{
    echo $text . "<br />";
}
