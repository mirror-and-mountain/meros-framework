<?php 

namespace MM\Meros\Contracts;

abstract class Extension extends Feature
{
    protected abstract function override(): void;
}