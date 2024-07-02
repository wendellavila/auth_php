<?php
require_once "get.php";
require_once "put.php";
require_once "delete.php";
require_once "../request_handler.php";

$handler = new RequestHandler(
    new GetRequest(),
    null,
    new PutRequest(),
    new DeleteRequest()
);
$handler->resolve();