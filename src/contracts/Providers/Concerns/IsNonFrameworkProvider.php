<?php

namespace MM\Meros\Contracts\Providers\Concerns;

trait IsNonFrameworkProvider {

    use ProvidesAssets,
        ProvidesAdminFeatures,
        ProvidesContent,
        ProvidesComponents,
        ProvidesTables;
}