<?php
$_SERVER["REQUEST_METHOD"] = "GET";
$_GET["action"] = "tournaments_list";
define('DEBUG_MODE', true);
require_once "modules/tournaments/api.php";
