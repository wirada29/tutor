<?php
$host = 'localhost';
$dbname = 'school_system';
$user = 'root';
$pass = ''; // ถ้ามีรหัสผ่านให้ใส่ตรงนี้

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>