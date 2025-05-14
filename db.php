<?php
$conn = new mysqli("localhost", "root", "", "blog_system1");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
