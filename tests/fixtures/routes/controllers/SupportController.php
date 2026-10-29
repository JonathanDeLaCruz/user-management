<?php
namespace routefixtures\controllers;

/** A filename ending in Controller.php does not make a class a Yii controller. */
final class SupportController
{
    public function __construct() { throw new \RuntimeException('Non-controller was instantiated.'); }
}
