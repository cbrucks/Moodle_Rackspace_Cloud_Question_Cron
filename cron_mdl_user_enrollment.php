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
    $existing_user = $mysqli->query($con, 'SELECT * FROM mdl_user WHERE ' .
             'email="' . $user["email"] . '" AND ' .
             'password="' . $user["password"] . '" AND ' .
             'firstname="' . $user["firstName"]  . '" AND '.
             'lastname="' . $user["lastName"] . '" AND '.
             'city="' . $user["city"] . '" AND '.
             'country="' . $user["country"] . '" AND '.
             'username="' . $user["email"] . '"');

    $results = $mysqli->query('SELECT * FROM mdl_user');
    echo var_dump($results);

    // build a corresponding line in the csv file for the entry
    $csv_text .= (($user["chosen"])? 'add':'del') . ',';
    $csv_text .= 'student,';
    $csv_text .= $user["email"] . ',';
    $csv_text .= $course_idnumber . PHP_EOL;
}

echo $csv_text;

$mysqli->close();

?>
