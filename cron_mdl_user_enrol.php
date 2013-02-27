<?php
define('CLI_SCRIPT', true);
require '/var/www/config.php'; // Access Moodle Database login info


// User defined variables
$course_idnumber = '1001';
$csvFileName = 'enrol.txt';


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
$csv_text = array();
foreach ($users as $user) {
    $idnumber = '';

    // figure out if we need to create the user first
    $sql = 'SELECT * FROM mdl_user WHERE ' .
           '`email`="'. $user["email"] . '"' .
           ' AND `firstname`="'. $user["firstName"] . '"' .
           ' AND `lastname`="'. $user["lastName"] . '"' .
           ' AND `city`="'. $user["city"] . '"' .
//           ' AND `country`="'. $user["country"] . '"' .
           ' AND `username`="'. $user["email"] . '"' .
           ' AND `idnumber`="' . $user["email"] . '"';

    if (! $res = $mysqli->query($sql)) {
        die('Error' . $mysqli->error . PHP_EOL);
    }

    // Check for existin user account
    if($res->num_rows > 0) {
        // Account exists
        if ($row = $res->fetch_assoc()) {
            $idnumber = $row["idnumber"];
        } else {
            // TODO: set the user idnumber in the database
//            $idnumber = $user["email"];
        }
        $res->close();
        echo 'User with username: "' . $user['email'] . '" already exists.' . PHP_EOL;
    } else {
        // Account does not exist so create it
        $res->close();

        $date = new DateTime();
        $now = $date->getTimestamp();

        $sql = 'INSERT INTO `mdl_user` SET `confirmed`=?,`mnethostid`=?,`username`=?,`password`=?,`idnumber`=?,`firstname`=?,`lastname`=?,`email`=?,`city`=?,`country`=?,`timecreated`=?,`timemodified`=?';
        if ($qry = $mysqli->prepare($sql)) {
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
                die('Query Error (' . $qry->errno . ') ' . $qry->error . PHP_EOL);
            }

            echo 'Create new user' . PHP_EOL;

            $idnumber = $user['email'];

            $qry->close();
        }
    }

    // build a corresponding line in the csv file for the entry
    $temp = array();
    $temp[] = (($user["chosen"] && !$user["complete"])? 'add':'del');
    $temp[] = 'student';
    $temp[] = $idnumber;
    $temp[] = $course_idnumber;
    $csv_text[] = $temp;
}

$file = $CFG->dataroot . '/' . $csvFileName;
if ($fp = fopen($file, 'w')) {
    echo 'Writing CSV file' . PHP_EOL;
    foreach ($csv_text as $line) {
        fputcsv($fp, $line);
    }
    fclose($fp);
} else {
    die('Could not creat csv file at ' . $file);
}



$mysqli->close();

?>
