<?php
mysqli_report(MYSQLI_REPORT_OFF);
$servername = "localhost";
$username="root";
$password = ""; 
$conn = new mysqli($servername,$username,$password);
if ($conn->connect_error) {
    echo "<h2>error connecting to db</h2>";
    die();
} else {
    echo "";
}

?>