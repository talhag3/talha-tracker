<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Could not connect to the database: " . $e->getMessage());
}
?>
