<?php
define('CLI_SCRIPT', true);
require '/var/www/config.php'; // Access Moodle Database login info

$log = '';

$course_idnumber = 'autoenroll';

// Connect to the Database
$mysqli = new mysqli($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);

/*
 * This is the "official" OO way to do it,
 * BUT $connect_error was broken until PHP 5.2.9 and 5.3.0.
 */
if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') '
            . $mysqli->connect_error);
}

/*
 * Use this instead of $connect_error if you need to ensure
 * compatibility with PHP versions prior to 5.2.9 and 5.3.0.
 */
if (mysqli_connect_error()) {
    die('Connect Error (' . mysqli_connect_errno() . ') '
            . mysqli_connect_error());
}

// Perform the cURL request
$url = 'http://166.78.21.13:3000/signups.json';
$curl_ch = curl_init($url);
curl_setopt($curl_ch, CURLOPT_RETURNTRANSFER, 1);  // Make silent
$curl_result = curl_exec($curl_ch);
curl_close($curl_ch);

// Convert the string into an array
$users = json_decode($curl_result, true);

// go through each user
$csv_text = '';
foreach ($users as $user) {
    // figure out if we need to create the user first
    $results = $mysqli->query($con, 'SELECT * FROM mdl_user WHERE ' .
             'email="' . $user["email"] . '" ' .
             'AND firstname="' . $user["firstName"]  . '" ' .
             'AND lastname="' . $user["lastName"] . '" ' .
             'AND city="' . $user["city"] . '" ' .
             'AND country="' . $user["country"] . '" ' .
             'AND username="' . $user["email"] . '" ' .
             'AND idnumber="' . $user["email"] . '" '
             );

    // GOING THROUGH THE DATA
    var_dump($results);
    if($results->num_rows > 0) {
        while($row = $results->fetch_assoc()) {
            echo $row['username'] . PHP_EOL;
        }
    } else {
        $date = new DateTime();
        $now = $date->getTimestamp();

        $sql = 'INSERT INTO `mdl_user` SET `confirmed`=?,`mnethostid`=?,`username`=?,`password`=?,`idnumber`=?,`firstname`=?,`lastname`=?,`email`=?,`city`=?,`country`=?,`timecreated`=?,`timemodified`=?';
        if ($qry = $mysqli->prepare($sql)) {
            echo 'Create new user' . PHP_EOL;

            $conf = 1;
            $mneth = 1;
            $username = $user["email"];
            //TODO: need to encrypt the password
            if (isset($CFG->passwordsaltmain)) {
                $pass = md5($user["password"].$CFG->passwordsaltmain);
            } else {
                $pass = md5($user["password"]);
            }
            $id = $user["email"];
            $first = $user["firstName"];
            $last = $user["lastName"];
            $email = $user["email"];
            $city = $user["city"];
            $coun = $user["country"];
            $create = $now;
            $mod = $now;

            $qry->bind_param("iissssssssii", $conf,$mneth,$username,$pass,$id,$first,$last,$email,$city,$coun,$create,$mod);

            $qry->execute();
            if ($qry->errno) {
                echo "ERROR: " . $qry->error . PHP_EOL;
                return;
            }

            $qry->close();
        }
    }
    $mysqli->commit();

    $results->close();


    // build a corresponding line in the csv file for the entry
    $csv_text .= (($user["chosen"])? 'add':'del') . ',';
    $csv_text .= 'student,';
    $csv_text .= $user["email"] . ',';
    $csv_text .= $course_idnumber . PHP_EOL;
}

echo $csv_text;

$mysqli->close();

?>
