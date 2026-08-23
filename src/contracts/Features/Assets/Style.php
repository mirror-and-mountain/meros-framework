<?php

namespace MM\Meros\Contracts\Features\Assets;

class Style extends Asset {
    /**
     * The type of the asset, either 'script' or 'style'.
     *
     * @var string
     */
    final protected string $type = 'style';
}