<?php

function write($text) {
    echo $text."<br />";
}

write ("PHP version: " . phpversion());

$fileName = "../tempFiles/r3e_data.json";


//if(!file_exists($fileName)) {
    write("Downloading file...");
    if(!copy("https://raw.githubusercontent.com/sector3studios/r3e-spectator-overlay/master/r3e-data.json", $fileName))
        write("Can't copy file.");
//}

$file = file_get_contents($fileName, "r");
unlink($fileName);
if ($file === false) {
    write("Unable to open file.");
    exit;
}

$file = trim($file);

// Remove invalid ";" at end of file
if(substr($file, -1) == ";")
    $file = substr($file, 0, -1);

$json = json_decode($file, true);

mysqli_report(MYSQLI_REPORT_STRICT);

try {
    require "../auth.php";
    write("Creating database...");
    $conn = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
} catch (Exception $e ) {
    //write ("Service unavailable");
    var_dump($e);
    exit;
}

if ($conn->connect_error)
    die("Connection failed: " . $conn->connect_error);

$conn->query("DROP DATABASE IF EXISTS r3e_data;");

$sql = "CREATE DATABASE r3e_data;";
if ($conn->query($sql) === TRUE) {
    write("Database created successfully");
} else {
    write("Error creating database: " . $conn->error);
}

$conn->query("USE r3e_data;");

// TODO Utiliser les données des Teams et non le TeamName des livrées car on y trouve des erreurs.

$conn->query("CREATE TABLE cars (id INT PRIMARY KEY, name TEXT, classId INT);");
$conn->query("CREATE TABLE classes (id INT PRIMARY KEY, name TEXT);");
$conn->query("CREATE TABLE liveries (id INT PRIMARY KEY, name TEXT, carId INT, teamId INT, number INT);");
$conn->query("CREATE TABLE teams (id INT PRIMARY KEY, name TEXT);");

// TODO trim toutes les données et dire à JF de chercher ' ",' dans le json.

foreach ($json["cars"] as $key => $value) {
    $conn->query("INSERT INTO cars (id, name, classId)
                    VALUES ({$value["Id"]},'{$value["Name"]}',{$value["Class"]});");
}

foreach ($json["classes"] as $key => $value) {
    $conn->query("INSERT INTO classes (id, name) VALUES ({$value["Id"]},'{$value["Name"]}');");
}

foreach ($json["liveries"] as $key => $value) {
    $liveryNumber = 9999;
    preg_match('/^#(\d+)/', $value["Name"], $matches);
    if (count($matches) > 1) {
        $liveryNumber = $matches[1];
    }

    $conn->query("INSERT INTO liveries (id, name, carId, teamId, number) VALUES ({$value["Id"]},'{$value["Name"]}', {$value["Car"]}, '{$value["Team"]}', {$liveryNumber});");
}

foreach ($json["teams"] as $key => $value) {
    $conn->query("INSERT INTO teams (id, name) VALUES ({$value["Id"]},'{$value["Name"]}');");
}

$conn->close();
?>