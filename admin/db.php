<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
function db(): PDO { return app_db(); }
function get_pdo(): PDO { return app_db(); }
