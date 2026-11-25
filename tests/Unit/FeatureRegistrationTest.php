<?php

namespace MM\Meros\Tests\Unit;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;
use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Testing\FeatureProvider;
use MM\Meros\Testing\TestCase;

/**
 * Tests feature creation, provider ownership, lookup and register contract validation.
 */
final class FeatureRegistrationTest extends TestCase
{
    public function test_registration_and_making_preserve_provider_ownership(): void
    {
        $provider = new FeatureProvider($this->directory);
        $register = new ProbeRegister();
        $register->checkout($provider)->register(RegisteredFeature::class, 'registered');
        $feature = $register->checkout($provider)->makeFrom('registered');
        $this->assertSame($provider, $feature->getProvider());
        $this->assertSame($feature, $this->assertRegistered($register, 'registered', $provider));
        $this->assertFalse($register->isCheckedOut());
    }

    public function test_made_features_are_retrievable_and_duplicates_fail(): void
    {
        $provider = new FeatureProvider($this->directory);
        $register = new ProbeRegister();
        $feature = $register->checkout($provider)->make(['name' => 'made']);
        $this->assertSame($feature, $this->assertRegistered($register, 'made', $provider));
        $this->expectException(\LogicException::class);
        $register->checkout($provider)->make(['name' => 'made']);
    }

    public function test_register_requires_checkout(): void
    {
        $register = new ProbeRegister();
        $this->expectException(\LogicException::class);
        $register->register(RegisteredFeature::class);
    }

    public function test_register_rejects_a_class_outside_its_contract(): void
    {
        $provider = new FeatureProvider($this->directory);
        $register = new ProbeRegister();
        $this->expectException(\InvalidArgumentException::class);
        $register->checkout($provider)->register(InvalidFeature::class);
    }
}

class ProbeFeature extends Feature implements Makeable, Registrable
{
    use IsMakeable, IsRegistrable;
    protected string $name = '';
    protected function init(): void { $this->identifier('name', 'snake'); }
}

class RegisteredFeature extends ProbeFeature
{
    protected function configure(): void { $this->name = 'registered'; }
}

class InvalidFeature extends \ArrayObject {}

class ProbeRegister extends Register implements RegistrarMaker
{
    use IsRegistrarMaker;
    protected function configure(): void { $this->contract(ProbeFeature::class); $this->unique(); }
}
