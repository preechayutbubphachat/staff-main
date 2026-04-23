<?php

$host = "localhost:3306";
$dbname = "obgate_staff";     
$username = "obgate_staff";    
$password = "fk26u2DRotlYz~d^";        

try {

    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e){
    die("DB ERROR: " . $e->getMessage());
}
