<?php
include 'db.php';

$sql = file_get_contents('schema.sql');

try {
    $db->exec($sql);
    echo "Database tables created successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
