<?php
require_once "post.php";
require_once "../request_handler.php";

$handler = new RequestHandler(null, new PostRequest());
$handler->resolve();