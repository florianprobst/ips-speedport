<?php
require_once('config.php');
print_r($sp->getData('status'));
$sp->logout();
