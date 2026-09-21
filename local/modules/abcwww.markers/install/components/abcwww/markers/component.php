<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if (!class_exists('AbcwwwMarkersComponent')) {
    require __DIR__ . '/class.php';
}

$component = new AbcwwwMarkersComponent($this);
$component->executeComponent();
