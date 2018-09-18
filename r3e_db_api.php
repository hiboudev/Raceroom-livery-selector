<?php

if(isset($_GET['classId']))
    getCars($_GET['classId']);
else if(isset($_GET['carId']))
    getLiveries($_GET['carId']);

function getClasses () {
    require "auth.php";

    $db = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
    if ($db->connect_error)
        die("Connection failed: " . $db->connect_error);

    $db->query("USE r3e_data");

    $result = $db->query("SELECT * from classes ORDER BY name");

    $all = $result->fetch_all(MYSQLI_ASSOC);

    echo "<option class=\"listPrompt\" value=\"-1\">Choisissez une classe...</option>";

    for ($i=0; $i < count($all); $i++) {
        $row = $all[$i];
        echo "<option value=\"{$row["id"]}\">{$row["name"]}</option>";
    }

    $db->close();
}

function getCars($classId){
    require "auth.php";
    
    $db = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
    if ($db->connect_error)
        die("Connection failed: " . $db->connect_error);
    
    $db->query("USE r3e_data");
    
    $result = $db->query("SELECT * from cars WHERE classId = {$classId} ORDER BY name");
    
    $all = $result->fetch_all(MYSQLI_ASSOC);
    
    echo "<option class=\"listPrompt\" value=\"-1\">Choisissez une voiture...</option>";

    for ($i=0; $i < count($all); $i++) {
        $row = $all[$i];
        echo "<option value=\"{$row["id"]}\">{$row["name"]}</option>";
    }
    
    $db->close();
}

function getLiveries($carId){
    require "auth.php";
    
    $db = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
    if ($db->connect_error)
        die("Connection failed: " . $db->connect_error);
    
    $db->query("USE r3e_data");
    
    $result = $db->query("SELECT * FROM liveries WHERE carId = {$carId} ORDER BY number, title");
    
    $all = $result->fetch_all(MYSQLI_ASSOC);

    for ($i=0; $i < count($all); $i++) {
        $row = $all[$i];

        echo "<div class=\"thumbnail\" onclick=\"copyLink('{$row['imageUrl']}')\"><img class=\"image\" src=\"{$row['imageUrl']}\" /><span class=\"thumbnailText\">{$row["title"]}</span></div>";
    }
    
    $db->close();
}

function write($text) {
    echo $text."<br />";
}

?>