<?php
// A simple debugging script to see what's in the session
session_start();
echo "<h1>Session Debug</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Cookie Information</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<p><a href='index.php'>Back to Home</a></p>";
echo "<p><a href='index.php?show_landing=1'>Show Landing Page</a></p>";
echo "<p><a href='logout.php'>Logout</a></p>";
?>
