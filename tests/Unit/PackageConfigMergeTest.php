<?php

namespace MM\Meros\Tests\Unit;

use MM\Meros\App\Providers\PackageServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class PackageConfigMergeTest extends TestCase {
    public function testAssociativeConfigMergesRecursivelyWhileListsAreOverridden(): void {
        $provider = (new ReflectionClass(ConfigMergeTestServiceProvider::class))->newInstanceWithoutConstructor();
        $merge = new ReflectionMethod(PackageServiceProvider::class, 'mergeConfigArrays');

        $result = $merge->invoke($provider, [
            'meros_crm_salesforce' => [
                'force_enabled' => false,
                'environments' => [
                    'production' => [
                        'client_id' => 'package-client',
                        'queryable_objects' => ['Account', 'Contact'],
                    ],
                    'sandbox' => [
                        'client_id' => 'sandbox-client',
                    ],
                ],
            ],
        ], [
            'meros_crm_salesforce' => [
                'environments' => [
                    'production' => [
                        'client_id' => 'theme-client',
                        'queryable_objects' => ['Lead'],
                    ],
                ],
            ],
            'theme_integration' => ['allow_configuration' => false],
        ]);

        self::assertSame([
            'meros_crm_salesforce' => [
                'force_enabled' => false,
                'environments' => [
                    'production' => [
                        'client_id' => 'theme-client',
                        'queryable_objects' => ['Lead'],
                    ],
                    'sandbox' => [
                        'client_id' => 'sandbox-client',
                    ],
                ],
            ],
            'theme_integration' => ['allow_configuration' => false],
        ], $result);
    }
}

class ConfigMergeTestServiceProvider extends PackageServiceProvider {}
