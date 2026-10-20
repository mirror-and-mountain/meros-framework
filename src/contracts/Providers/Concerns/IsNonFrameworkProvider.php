<?php

namespace MM\Meros\Contracts\Providers\Concerns;

trait IsNonFrameworkProvider {

    use ProvidesAssets,
        ProvidesAdminFeatures,
        ProvidesAutomation,
        ProvidesContent,
        ProvidesComponents,
        ProvidesTables,
        ProvidesIntegrations;
}