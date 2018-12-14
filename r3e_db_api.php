<?php

if (!isset($_GET['dataType'])) {
    return;
}

$dataType = $_GET['dataType'];
$username = isset($_GET['username']) ? $_GET['username'] : null;

switch ($dataType) {
    case "cars":
        if (!isset($_GET['classId'])) {
            die("Bad request.");
        }
        getCars($_GET['classId']);
        break;

    case "carLiveries":
        if (!isset($_GET['carId'])) {
            die("Bad request.");
        }
        getLiveries($_GET['carId'], $username);
        break;

    case "classLiveries":
        if (!isset($_GET['classId'])) {
            die("Bad request.");
        }
        getLiveries($_GET['classId'], $username, true);
        break;

    default:
        die("Bad request.");
}

function getClasses()
{
    $db = getDatabase();

    $result = $db->query("SELECT * from classes ORDER BY name;");

    $all = $result->fetch_all(MYSQLI_ASSOC);

    echo "<option class=\"listPrompt\" value=\"-1\">Choisissez une classe...</option>";

    for ($i = 0; $i < count($all); $i++) {
        $row = $all[$i];
        echo "<option value=\"{$row["id"]}\">{$row["name"]}</option>";
    }

    $db->close();
}

function getCars($classId)
{
    $db = getDatabase();

    $result = $db->query("SELECT * from cars WHERE classId = $classId ORDER BY name;");

    $all = $result->fetch_all(MYSQLI_ASSOC);

    if (count($all) > 0) {
        echo "<option class=\"listPrompt\" value=\"-1\">Choisissez une voiture...</option>";

        for ($i = 0; $i < count($all); $i++) {
            $row = $all[$i];
            echo "<option value=\"{$row["id"]}\">{$row["name"]}</option>";
        }
    } else {
        echo "";
    }

    $db->close();
}

function getLiveries($id, $username, $isClassId = false)
{
    $db = getDatabase();

    $userId = getUserId($db, $username);

    if ($userId != -1) {
        getUserLiveries($db, $id, $isClassId, $userId);
    } else {
        getAllLiveries($db, $id, $isClassId);
    }

    $db->close();
}

function getUserLiveries($db, $id, $isClassId, $userId)
{
    $idColumnName = $isClassId ? "classId" : "carId";

    $result = $db->query(
        "SELECT liveries.imageUrl, liveries.title, liveries.drivers, cars.name as carName
            , IF(liveries.isFree = 1 OR userLiveries.liveryId IS NOT NULL, TRUE, FALSE) as owned
        FROM cars, liveries
        LEFT JOIN userLiveries
            ON (userLiveries.userId = $userId AND userLiveries.liveryId = liveries.id)
        WHERE liveries.$idColumnName = $id AND cars.id = liveries.carId
        ORDER BY carName, owned DESC, number, title");

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    displayLiveries($rows);
}

function getAllLiveries($db, $id, $isClassId)
{
    $idColumnName = $isClassId ? "classId" : "carId";

    $result = $db->query(
        "SELECT liveries.imageUrl, liveries.title, liveries.drivers, cars.name as carName
        FROM liveries, cars
        WHERE liveries.$idColumnName = $id AND cars.id = liveries.carId
        ORDER BY carName, number, title");

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    displayLiveries($rows);
}

function displayLiveries($rows)
{
    $previousCarName = null;

    for ($i = 0; $i < count($rows); $i++) {
        $row     = $rows[$i];
        $carName = $row['carName'];

        if ($carName != $previousCarName) {
            echo "<h3 class=\"carName\">$carName</h3>";
        }

        $ownedCssClass = !array_key_exists("owned", $row) || $row["owned"] ? "owned" : "notOwned";

        echo "<div class=\"thumbnail $ownedCssClass\" onclick=\"copyLink('{$row['imageUrl']}')\"><img class=\"image lazy\" src=\"images/imagePlaceholder.png\" data-src=\"{$row['imageUrl']}\" /><div class=\"thumbnailText\"><span class=\"liveryTitle\">{$row["title"]}</span><span class=\"liveryDrivers\">{$row["drivers"]}</span></div></div>";

        $previousCarName = $carName;
    }
}

function getUserId($db, $username)
{
    if ($username == null) {
        return -1;
    }

    $userResult = $db->query("SELECT * FROM users WHERE name='$username';");
    if ($userResult != null && $userResult->num_rows == 1) {
        return $userResult->fetch_assoc()["id"];
    } else {
        return -1;
    }

}

function getDatabase()
{
    require_once "auth.php";

    $db = new mysqli($dbAddress, $dbUserName, $dbPassword);
    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error);
    }

    $db->query("USE $dbName");

    return $db;
}

function write($text)
{
    echo $text . "<br />";
}
