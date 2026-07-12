<?php

namespace FrameworkStandardization\Orchestration;

use FrameworkStandardization\Discovery\DbReadOnlyCharacteristicDiscovery;
use FrameworkStandardization\Registry\CharacteristicRegistryBuilder;

final class DbReadOnlyCharacteristicRegistryOrchestrator
{
    private $discovery;
    private $registryBuilder;

    public function __construct(
        DbReadOnlyCharacteristicDiscovery $discovery,
        CharacteristicRegistryBuilder $registryBuilder
    ) {
        $this->discovery = $discovery;
        $this->registryBuilder = $registryBuilder;
    }

    public function build(array $scope, array $legacyDecisions)
    {
        $discoveredRows = $this->discovery->discover($scope);

        return $this->registryBuilder->build($scope, $discoveredRows, $legacyDecisions);
    }
}
