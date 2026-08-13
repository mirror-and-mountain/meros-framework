<?php 

namespace MM\Meros\Contracts\Registers;

interface Discoverer extends Maker {
    /**
     * Discovers features at the given path.
     *
     * @param string $path
     *
     * @return void
     */
    public function discover(string $path = ''): void;
    
    /**
     * Retrieves the appropriate register for the given feature class.
     *
     * @param string $featureClass
     *
     * @return AllFeatureRegistrationMethods
     */
    public static function resolveDiscovererRegister(string $featureClass): Discoverer;
}