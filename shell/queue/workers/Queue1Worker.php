<?php
$app = require __DIR__ . DIRECTORY_SEPARATOR . '../../../bootstrap/app.php';
$app->boot();

/** \App\Queue\EntityConsumer $consumerManager */
$consumerManager = app()->get(\App\Queue\EntityConsumer::class);

$consumerManager->consume();
