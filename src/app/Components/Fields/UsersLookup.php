<?php

namespace MM\Meros\App\Components\Fields;

use MM\Meros\App\Models\User;

class UsersLookup extends Lookup {
    protected string $model = User::class;
    protected string $key = 'ID';
    protected string $labelledBy = 'display_name';
}