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
$db->close();

write("Finished!");

/**
 * Return value can be null.
 */
function getSecondaryJson()
{
    write("Downloading secondary json...");

    $url         = "https://raw.githubusercontent.com/sector3studios/r3e-spectator-overlay/master/r3e-data.json";
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
                $classItem['Name']
            );
        }

        foreach ($json['cars'] as $carItem) {
            $carId = intval($carItem['Id']);

            $secondaryData->cars[$carId] = new Car(
                $carId,
                $carItem['Name'],
                intval($carItem['Class'])
            );
        }

        foreach ($json['liveries'] as $liveryItem) {

            $drivers = "";
            foreach ($liveryItem['drivers'] as $driver) {
                if ($drivers != "") {
                    $drivers .= ", ";
                }
                $drivers .= "{$driver['Forename']} {$driver['Surname']}";
            }

            $liveryId   = intval($liveryItem['Id']);
            $teamName   = $liveryItem['TeamName'];
            $liveryName = $liveryItem['Name'];
            $imageUrl   = getImageUrl($liveryId, $teamName, $liveryName);

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

            $carId   = intval($itemValue["cid"]);
            $carName = $itemValue["name"];

            $storeData->cars[$carId] = new Car(
                $carId,
                $itemValue["name"],
                $classId
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
                    $liveryValue["thumb"],
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
                    VALUES ($class->id, '$class->name', 1);");
    }

    foreach ($storeData->cars as $car) {
        query($db, "INSERT INTO cars (id, name, classId, fromShop)
                    VALUES ($car->id, '$car->name', $car->classId, 1);");
    }

    foreach ($storeData->liveries as $livery) {
        $drivers = array_key_exists($livery->id, $secondaryData->liveries) ? $secondaryData->liveries[$livery->id]->drivers : "";

        query($db, "INSERT INTO liveries (id, title, carId, classId, number, imageUrl, isFree, drivers, fromShop)
                    VALUES (    $livery->id, \"$livery->title\", $livery->carId, $livery->classId,
                                $livery->number, \"$livery->imageUrl\", $livery->isFree, \"$drivers\", 1);");
    }

    foreach ($secondaryData->classes as $class) {
        query($db, "INSERT IGNORE INTO classes (id, name, fromShop)
                    VALUES ($class->id, '$class->name', 0);");
    }

    foreach ($secondaryData->cars as $car) {
        query($db, "INSERT IGNORE INTO cars (id, name, classId, fromShop)
                    VALUES ($car->id, '$car->name', $car->classId, 0);");
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

    query($db, "CREATE DATABASE IF NOT EXISTS $dbName character set UTF8 collate utf8_bin");
    query($db, "USE $dbName;");

    query($db, "CREATE TABLE classes        (id INT NOT NULL, name TEXT NOT NULL, fromShop BOOLEAN NOT NULL, PRIMARY KEY(id));");
    query($db, "CREATE TABLE cars           (id INT NOT NULL, name TEXT NOT NULL, classId INT NOT NULL, fromShop BOOLEAN NOT NULL, PRIMARY KEY(id));");
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

function getImageUrl($liveryId, $teamName, $liveryName)
{
    // TODO BUG : Des WTCR ne s'affichent pas. Erreur dans les id de l'url du magasin et dans un nom de team.
    // TODO nettoyer/optimiser les substitutions
    $teamName       = trim($teamName);
    $teamName       = preg_replace('/ [^A-Za-z0-9] /', '-', $teamName);
    $teamName       = str_replace(' ', '-', $teamName);
    $unwanted_array = array('Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â'  => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
        'Ê'                         => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ'  => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O', 'Ø' => 'O', 'Ù' => 'U',
        'Ú'                         => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
        'è'                         => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î'  => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ö'                         => 'o', 'ő' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü'  => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y');
    $teamName   = strtolower(strtr($teamName, $unwanted_array));
    $teamName   = preg_replace('/[^A-Za-z0-9\-]/', '', $teamName);
    $liveryName = strtolower(str_replace('#', '', str_replace(' ', '-', preg_replace('/(\s+-\s+)|(-\s+)|(\s+-)/', '-', $liveryName))));

    return "http://game.raceroom.com/r3e/assets/content/carlivery/{$teamName}-{$liveryName}-{$liveryId}-image-small.png";
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

    public function __construct($id, $name, $classId)
    {
        $this->id      = $id;
        $this->name    = $name;
        $this->classId = $classId;
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
