<?php
error_log("Router: REQUEST_URI = " . $_SERVER["REQUEST_URI"]);

if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|ico|svg)$/', $_SERVER["REQUEST_URI"])) {
    error_log("Serving static file");
    return false; 
} else {
    error_log("Including index.php");
    include __DIR__ . '/index.php';
}
?>