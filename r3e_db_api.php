<?php

if(!isset($_GET['getData'])) return;

if(isset($_GET['classId']))
    getCars($_GET['classId']);
else if(isset($_GET['carId'])) {
    if(isset($_GET['username']))
        getLiveries($_GET['carId'], $_GET['username']);
    else
        getLiveries($_GET['carId'], null);
}

function getClasses () {
    require_once "auth.php";

    $db = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
    if ($db->connect_error)
        die("Connection failed: " . $db->connect_error);

    $db->query("USE {$dbName}");

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
    require_once "auth.php";
    
    $db = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
    if ($db->connect_error)
        die("Connection failed: " . $db->connect_error);
    
    $db->query("USE {$dbName}");
    
    $result = $db->query("SELECT * from cars WHERE classId = {$classId} ORDER BY name");
    
    $all = $result->fetch_all(MYSQLI_ASSOC);
    
    if (count($all) > 0) {
        echo "<option class=\"listPrompt\" value=\"-1\">Choisissez une voiture...</option>";

        for ($i=0; $i < count($all); $i++) {
            $row = $all[$i];
            echo "<option value=\"{$row["id"]}\">{$row["name"]}</option>";
        }
    } else echo "";
    
    $db->close();
}

function getLiveries($carId, $username){
    require_once "auth.php";
    $db = new mysqli($dbAddress, $dbUserName, $dbPassword) ;
    if ($db->connect_error)
        die("Connection failed: " . $db->connect_error);
    
    $db->query("USE {$dbName}");
    
    $userResult = $username == null ? false : $db->query("SELECT * FROM users WHERE name='{$username}';");
    if($userResult != null && $userResult->num_rows == 1)
        getUserLiveries($db, $carId, $userResult->fetch_assoc()["id"]);
    else
        getAllLiveries($db, $carId);

    $db->close();
}

function getUserLiveries($db, $carId, $userId) {
    // 'owned' in the request is used only to sort results, I add the default livery for graphical design purpose but there's still a doubt on this purchase.
    $result = $db->query("SELECT liveries.imageUrl, liveries.isFree, liveries.id, liveries.title, userLiveries.liveryId AS userLiveryId
            , IF(userLiveries.liveryId IS NULL AND liveries.isFree=0
                , IF(userCars.carId IS NULL, FALSE
                    , IF(cars.defaultLiveryId=liveries.id, TRUE, FALSE)), TRUE) as owned
            , cars.defaultLiveryId FROM liveries LEFT JOIN userLiveries ON
                (userLiveries.userId={$userId} AND liveries.id = userLiveries.liveryId)
            , cars LEFT JOIN userCars ON (userCars.userId={$userId} AND cars.id = userCars.carId)
            WHERE liveries.carId={$carId} AND cars.id={$carId}
            ORDER BY owned DESC, number, title");
    
    $all = $result->fetch_all(MYSQLI_ASSOC);
    
    for ($i=0; $i < count($all); $i++) {
        $row = $all[$i];
        $isDefault = $row["defaultLiveryId"] == $row["id"];

        $cssClass = $row["owned"] ? "thumbnail" : "thumbnailNotOwned";
        // TODO C'est le bordel toute cette logique
        $owned2 = $row["userLiveryId"] != null || $row["isFree"];
        $notSureIfOwnedHtml = $row["owned"] && !$owned2 && $isDefault ? "<span title=\"Impossible de déterminer si vous possédez cette voiture.\" class=\"notSureIfOwned\">?</span>" : "";

        echo "<div class=\"{$cssClass}\" onclick=\"copyLink('{$row['imageUrl']}')\"><img class=\"image\" src=\"{$row['imageUrl']}\" />{$notSureIfOwnedHtml}<span class=\"thumbnailText\">{$row["title"]}</span></div>";
    }
}

function getAllLiveries($db, $carId) {
    $result = $db->query("SELECT * FROM liveries WHERE carId = {$carId} ORDER BY number, title");
    
    $all = $result->fetch_all(MYSQLI_ASSOC);

    for ($i=0; $i < count($all); $i++) {
        $row = $all[$i];

        echo "<div class=\"thumbnail\" onclick=\"copyLink('{$row['imageUrl']}')\"><img class=\"image\" src=\"{$row['imageUrl']}\" /><span class=\"thumbnailText\">{$row["title"]}</span></div>";
    }
}

function write($text) {
    echo $text."<br />";
}

?>