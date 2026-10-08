<?php
$servername = "localhost";
$username   = "projectdocument";
$password   = "ProjectDocument26";
$dbname     = "projectdocument";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);

$res = $conn->query("SHOW COLUMNS FROM project_revisions");
echo "<pre>";
while ($row = $res->fetch_assoc()) {
  echo $row['Field'] . "\n";
}
echo "</pre>";

$conn->close();
?>
