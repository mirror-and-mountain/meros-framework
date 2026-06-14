<?php 

namespace MM\Meros\App\Fields;

class Password extends Input {
    public string $handle = 'password';
    public static string $category = 'specialised';
    public static string $icon = 'lock';

    protected array $attributes = [
        'type' => 'password',
    ];

    protected array $supports = [
        'required',
        'disabled',
        'placeholder',
        'icon'
    ];

    protected array $compatibleDataTypes = [
        'string'
    ];

}