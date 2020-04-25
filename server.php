<?php

use App\Server;

require 'src/Server.php';

$server = new Server('localhost', 9000);
$server->run();
