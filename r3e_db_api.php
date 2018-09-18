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
    
    $result = $db->query("SELECT l.id, l.name, t.name AS teamName FROM liveries l, teams t WHERE l.carId = {$carId} AND t.id = l.teamId ORDER BY l.number, l.name");
    
    $all = $result->fetch_all(MYSQLI_ASSOC);

    for ($i=0; $i < count($all); $i++) {
        $row = $all[$i];
        
        $imageUrl = getImageUrl($row["id"], $row["teamName"], $row["name"]);

        echo "<div class=\"thumbnail\" onclick=\"copyLink('{$imageUrl}')\"><img class=\"image\" src=\"{$imageUrl}\" /><span class=\"thumbnailText\">{$row["teamName"]} <b>{$row["name"]}</b></span></div>";
        // echo $row["name"]." : ".$imageUrl."<br />";
    }
    
    $db->close();
}

function getImageUrl($liveryId, $teamName, $liveryName) { // TODO BUG : Des WTCR ne s'affichent pas. Erreur dans les id de l'url du magasin et dans un nom de team.
    // TODO nettoyer/optimiser les substitutions
    $teamName = trim($teamName);
    $teamName = preg_replace('/ [^A-Za-z0-9] /', '-', $teamName);
    $teamName = str_replace(' ', '-', $teamName);
    $unwanted_array = array(    'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ő' => 'O', 'Ø'=>'O', 'Ù'=>'U',
                            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                            'ö'=>'o', 'ő' => 'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü' =>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
    $teamName = strtolower(strtr( $teamName, $unwanted_array ));
    $teamName = preg_replace('/[^A-Za-z0-9\-]/', '', $teamName);
    $liveryName = strtolower(str_replace('#', '', str_replace(' ', '-', preg_replace('/(\s+-\s+)|(-\s+)|(\s+-)/', '-', $liveryName))));

    return "http://game.raceroom.com/r3e/assets/content/carlivery/{$teamName}-{$liveryName}-{$liveryId}-image-small.png";
}

function write($text) {
    echo $text."<br />";
}

?>