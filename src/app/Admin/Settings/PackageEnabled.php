<?php

namespace MM\Meros\App\Admin\Settings;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Facades\Packages;

final class PackageEnabled extends Setting {
    public function whenUpdated(mixed $value, mixed $oldValue, string $itemName, string $optionName): void {
        if ($value === $oldValue) {
            return;
        }

        $packageName = Str::replace('_enabled', '', $itemName);
        $package = Packages::get($packageName);

        if (!($package instanceof \MM\Meros\App\Package)) {
            return;
        }

        if ($value) {
            $package->__whenEnabled();
        } else {
            $package->__whenDisabled();
        }
    }
}