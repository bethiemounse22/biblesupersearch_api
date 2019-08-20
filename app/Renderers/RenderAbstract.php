<?php

namespace App\Renderers;

use App\Models\Bible;

abstract class RenderAbstract {
    static public $name;
    static public $description;

    protected $Bible;

    public function __construct($module) {

    }

    abstract public function render();

    abstract public function output();

}

