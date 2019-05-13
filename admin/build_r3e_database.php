<?php

write("PHP version: " . phpversion());
mysqli_report(MYSQLI_REPORT_STRICT);

try {
    $storeData     = getStoreData();
    $secondaryData = getSecondaryData();
} catch (Exception $e) {
    exit($e->getMessage());
}

$db = getDatabaseConnection();
fillDatabase($db, $storeData, $secondaryData);
createCsvFile($db);
$db->close();

write("Finished!");

/**
 * Return value can be null.
 */
function getSecondaryJson()
{
    write("Downloading secondary json...");

    $url = "https://raw.githubusercontent.com/sector3studios/r3e-spectator-overlay/master/r3e-data.json";
    //$url         = "https://raw.githubusercontent.com/hiboudev/r3e-spectator-overlay/master/r3e-data.json";
    $fileContent = file_get_contents($url);
    $json        = null;

    if ($fileContent !== false) {
        // Removing invalid ';' at end of json.
        $fileContent = preg_replace("/;\s*$/", '', $fileContent);
        $json        = json_decode($fileContent, true);

        if ($json == null) {
            write("Error parsing JSON from URL: $url");
        }
    } else {
        write("Error getting file from URL: $url");
    }

    return $json;
}

function getSecondaryData()
{
    $secondaryData = new R3eData();
    $json          = getSecondaryJson();
    write("Parsing secondary data...");

    if ($json != null) {

        foreach ($json['classes'] as $classItem) {
            $classId = intval($classItem['Id']);

            $secondaryData->classes[$classId] = new CarClass(
                $classId,
                trim($classItem['Name'])
            );
        }

        foreach ($json['cars'] as $carItem) {
            $carId = intval($carItem['Id']);

            $secondaryData->cars[$carId] = new Car(
                $carId,
                trim($carItem['Name']),
                intval($carItem['Class']),
                trim($carItem['BrandName'])
            );
        }

        foreach ($json['liveries'] as $liveryItem) {

            $drivers = "";
            foreach ($liveryItem['drivers'] as $driver) {
                if ($drivers != "") {
                    $drivers .= ", ";
                }
                $drivers .= trim($driver['Forename']) . " " . trim($driver['Surname']);
            }

            $liveryId   = intval($liveryItem['Id']);
            $teamName   = trim($liveryItem['TeamName']);
            $liveryName = trim($liveryItem['Name']);
            $imageUrl   = getImageUrl($liveryId);

            $secondaryData->liveries[$liveryId] = new Livery(
                $liveryId,
                "$teamName - $liveryName",
                intval($liveryItem['Car']),
                intval($liveryItem['Class']),
                getLiveryNumber($liveryItem['Name']),
                $imageUrl,
                0,
                $drivers
            );
        }
    }

    return $secondaryData;
}

function getStoreJson()
{
    write("Downloading store json...");

    $url         = "http://game.raceroom.com/store/cars/?json";
    $fileContent = file_get_contents($url);

    if ($fileContent === false) {
        exit("Unable to open URL: $url");
    }

    $json = json_decode($fileContent, true);
    if ($json == null) {
        exit("Can't parse json from URL: $url");
    }

    return $json;
}

function getStoreData()
{
    $json      = getStoreJson();
    $storeData = new R3eData();
    write("Parsing store data...");

    foreach ($json["context"]["c"]["sections"][0]["items"] as $itemKey => $itemValue) {

        // actually there's only 'car' type in json
        if ($itemValue["type"] == "car") {
            $className = $itemValue["car_class"]["name"];
            preg_match('/-(\d+)-image-big.[A-Za-z]+$/', $itemValue["car_class"]["image"]["big"], $matches);
            $classId = intval($matches[1]);

            if (!array_key_exists($classId, $storeData->classes)) {
                $storeData->classes[$classId] = new CarClass($classId, $className);
            }

            $carId = intval($itemValue["cid"]);

            $storeData->cars[$carId] = new Car(
                $carId,
                $itemValue["name"],
                $classId,
                $itemValue["manufacturer"]["name"]
            );

            foreach ($itemValue["content_info"]["livery_images"] as $liveryValue) {

                $liveryId = intval($liveryValue["cid"]);
                $isFree   = $liveryValue["free"] == true ? 1 : 0;

                $storeData->liveries[$liveryId] = new Livery(
                    $liveryId,
                    $liveryValue["title"],
                    $carId,
                    $classId,
                    getLiveryNumber($liveryValue["name"]),
                    getImageUrl($liveryId),
                    $isFree,
                    null// No information about drivers at this point.
                );
            }
        }
    }

    return $storeData;
}

function fillDatabase($db, $storeData, $secondaryData)
{
    write("Feeding database...");

    foreach ($storeData->classes as $class) {
        query($db, "INSERT INTO classes (id, name, fromShop)
                    VALUES ($class->id, \"$class->name\", 1);");
    }

    foreach ($storeData->cars as $car) {
        query($db, "INSERT INTO cars (id, name, classId, brandName, fromShop)
                    VALUES ($car->id, \"$car->name\", $car->classId, \"$car->brandName\", 1);");
    }

    foreach ($storeData->liveries as $livery) {
        $drivers = array_key_exists($livery->id, $secondaryData->liveries) ? $secondaryData->liveries[$livery->id]->drivers : "";

        query($db, "INSERT INTO liveries (id, title, carId, classId, number, imageUrl, isFree, drivers, fromShop)
                    VALUES (    $livery->id, \"$livery->title\", $livery->carId, $livery->classId,
                                $livery->number, \"$livery->imageUrl\", $livery->isFree, \"$drivers\", 1);");
    }

    foreach ($secondaryData->classes as $class) {
        query($db, "INSERT IGNORE INTO classes (id, name, fromShop)
                    VALUES ($class->id, \"$class->name\", 0);");
    }

    foreach ($secondaryData->cars as $car) {
        query($db, "INSERT IGNORE INTO cars (id, name, classId, brandName, fromShop)
                    VALUES ($car->id, \"$car->name\", $car->classId, \"$car->brandName\", 0);");
    }

    foreach ($secondaryData->liveries as $livery) {
        query($db, "INSERT IGNORE INTO liveries (id, title, carId, classId, number, imageUrl, isFree, drivers, fromShop)
                    VALUES (    $livery->id, \"$livery->title\", $livery->carId, $livery->classId,
                                $livery->number, \"$livery->imageUrl\", $livery->isFree, \"$livery->drivers\", 0);");
    }
}

function getLiveryNumber($liveryName)
{
    $liveryNumber = 9999;
    preg_match('/^#(\d+)/', $liveryName, $matches);
    if (count($matches) > 1) {
        $liveryNumber = intval($matches[1]);
    }
    return $liveryNumber;
}

function getDatabaseConnection()
{
    require_once "../auth.php";

    try {
        $db = new mysqli($dbAddress, $dbUserName, $dbPassword);
    } catch (Exception $e) {
        var_dump($e);
        exit("Can't connect to database.");
    }

    if ($db->connect_error) {
        exit("Connection failed: " . $db->connect_error);
    }

    if (!databaseExists($db, $dbName)) {
        createDatabase($db, $dbName);
    } else {
        emptyDatabase($db, $dbName);
    }

    return $db;
}

function databaseExists($db, $dbName)
{
    return query($db, "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'")->num_rows == 1;
}

function createDatabase($db, $dbName)
{
    write("Creating database...");

    query($db, "CREATE DATABASE IF NOT EXISTS $dbName character set utf8mb4 COLLATE utf8mb4_unicode_ci;");
    query($db, "USE $dbName;");

    query($db, "CREATE TABLE classes        (id INT NOT NULL, name TEXT NOT NULL, fromShop BOOLEAN NOT NULL, PRIMARY KEY(id));");
    query($db, "CREATE TABLE cars           (id INT NOT NULL, name TEXT NOT NULL, classId INT NOT NULL, brandName TEXT NOT NULL, fromShop BOOLEAN NOT NULL, PRIMARY KEY(id));");
    query($db, "CREATE TABLE liveries       (id INT NOT NULL, title TEXT NOT NULL, carId INT NOT NULL, classId INT NOT NULL, number INT NOT NULL,
                                                imageUrl TEXT NOT NULL, isFree INT NOT NULL, drivers TEXT NOT NULL, fromShop BOOLEAN NOT NULL, PRIMARY KEY(id));");
    query($db, "CREATE TABLE users          (id INT NOT NULL AUTO_INCREMENT, name TEXT NOT NULL, PRIMARY KEY(id));");
    query($db, "CREATE TABLE userLiveries   (userId INT NOT NULL, liveryId INT NOT NULL, PRIMARY KEY(userId, liveryId));");
}

function emptyDatabase($db, $dbName)
{
    write("Emptying database...");

    query($db, "USE $dbName; ");
    query($db, "TRUNCATE TABLE cars;");
    query($db, "TRUNCATE TABLE classes;");
    query($db, "TRUNCATE TABLE liveries;");
}

function query($db, $sql)
{
    $result = $db->query($sql);

    if (!$result) {
        exit("ERROR: " . $db->error);
    }

    return $result;
}

function write($text)
{
    echo $text . "<br />";
}

function getImageUrl($liveryId)
{
    return "http://game.raceroom.com/store/image_redirect?id={$liveryId}&size=small";
}

function createCsvFile($db)
{
    write("Creating Csv file...");

    $result = query($db,
        "SELECT     cars.brandName,
                    cars.name as carName,
                    classes.name as className,
                    liveries.title as liveryName,
                    liveries.imageUrl,
                    liveries.fromShop,
                    liveries.id as liveryId

        FROM        liveries, cars, classes
        WHERE       cars.id = liveries.carId AND classes.id = liveries.classId
        ORDER BY    className, brandName, carName, liveryName
    ;");

    $liveries = $result->fetch_all(MYSQLI_ASSOC);

    // UTF8 header
    $csvContent = "\xEF\xBB\xBF";

    $csvContent .= "id,class,brand,car,livery,buyable,imageUrl\r\n";
    foreach ($liveries as $livery) {
        $csvContent .= "{$livery['liveryId']},{$livery['className']},{$livery['brandName']},{$livery['carName']},{$livery['liveryName']},{$livery['fromShop']},{$livery['imageUrl']}\r\n";
    }

    if (file_put_contents("../liveries.csv", $csvContent) === false) {
        write("ERROR writing csv file!");
    }
}

class R3eData
{
    public $classes  = [];
    public $cars     = [];
    public $liveries = [];
};

class CarClass
{
    public $id;
    public $name;

    public function __construct($id, $name)
    {
        $this->id   = $id;
        $this->name = $name;
    }
}

class Car
{
    public $id;
    public $name;
    public $classId;
    public $brandName;

    public function __construct($id, $name, $classId, $brandName)
    {
        $this->id        = $id;
        $this->name      = $name;
        $this->classId   = $classId;
        $this->brandName = $brandName;
    }
}

class Livery
{

    public $id;
    public $title;
    public $carId;
    public $classId;
    public $number;
    public $imageUrl;
    public $isFree;
    public $drivers;

    public function __construct($id, $title, $carId, $classId, $number, $imageUrl, $isFree, $drivers)
    {
        $this->id       = $id;
        $this->title    = $title;
        $this->carId    = $carId;
        $this->classId  = $classId;
        $this->number   = $number;
        $this->imageUrl = $imageUrl;
        $this->isFree   = $isFree;
        $this->drivers  = $drivers;
    }
}
