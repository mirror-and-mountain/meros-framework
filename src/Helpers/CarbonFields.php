<?php 

namespace MM\Meros\Helpers;

use Carbon_Fields\Carbon_Fields;

class CarbonFields
{
    /**
     * Checks whether Carbon Fields has been booted and boots
     * if it hasn't.
     *
     * @return void
     */
    public static function boot(): void
    {
        $theme       = app()->make('meros.theme_manager');
        $initialised = $theme->carbonFieldsInitialised;

        if ( $initialised ) {
            return;
        }

        // Boot Carbon Fields
        Carbon_Fields::boot();

        $theme->carbonFieldsInitialised = true;
        dd($theme);
    }
}