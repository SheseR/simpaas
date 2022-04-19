<?php
$app = require __DIR__ . DIRECTORY_SEPARATOR . '../../../bootstrap/app.php';
$app->boot();

/** \Levtechdev\Simpaas\Cams\Queue\ConsumerManager */
$consumerManager = app()->get(\Levtechdev\Simpaas\Cams\Queue\ConsumerManager::class);

$consumerManager->consume();
