<?php

namespace App\Modules\HardwareBuilder\Services;

use App\Modules\HardwareBuilder\Exceptions\HardwareIncompatibilityException;

class BuilderValidationEngine
{
    /**
     * Executes cross-component compatibility checks for a given PC Build payload.
     * 
     * @param array $components Array of component data blocks containing 'category' and 'attributes'
     * @throws HardwareIncompatibilityException
     */
    public function validate(array $components): void
    {
        $conflicts = [];
        
        $cpu = null;
        $motherboard = null;
        $rams = [];
        $psu = null;
        $totalWattage = 0;

        foreach ($components as $comp) {
            $cat = strtolower($comp['category']);
            if ($cat === 'cpu') $cpu = $comp;
            if ($cat === 'motherboard') $motherboard = $comp;
            if ($cat === 'ram') $rams[] = $comp;
            if ($cat === 'psu' || $cat === 'power supply') $psu = $comp;

            // Accumulate wattage
            if (isset($comp['attributes']['tdp'])) {
                $totalWattage += (int) $comp['attributes']['tdp'];
            }
        }

        // 1. Socket Validation
        if ($cpu && $motherboard) {
            $cpuSocket = $cpu['attributes']['socket'] ?? null;
            $moboSockets = $motherboard['attributes']['socket_support'] ?? [];
            
            if ($cpuSocket && !in_array($cpuSocket, $moboSockets)) {
                $conflicts[] = [
                    'type' => 'socket_mismatch',
                    'message' => "CPU Socket ({$cpuSocket}) is incompatible with Motherboard supported sockets (" . implode(', ', $moboSockets) . ")."
                ];
            }
        }

        // 2. RAM Generation Validation
        if ($motherboard && count($rams) > 0) {
            $moboRamType = $motherboard['attributes']['supported_ram'] ?? null;
            foreach ($rams as $ram) {
                $ramType = $ram['attributes']['ram_type'] ?? null;
                if ($moboRamType && $ramType && $ramType !== $moboRamType) {
                    $conflicts[] = [
                        'type' => 'ram_mismatch',
                        'message' => "RAM Type ({$ramType}) is incompatible with Motherboard supported RAM ({$moboRamType})."
                    ];
                }
            }
        }

        // 3. Wattage Safety Validation (20% overhead)
        if ($psu && $totalWattage > 0) {
            $psuWattage = (int) ($psu['attributes']['wattage'] ?? 0);
            $requiredWithMargin = $totalWattage * 1.2; // 20% safety overhead

            if ($psuWattage < $requiredWithMargin) {
                $conflicts[] = [
                    'type' => 'power_deficit',
                    'message' => "Total system wattage with safety margin ({$requiredWithMargin}W) exceeds PSU capacity ({$psuWattage}W)."
                ];
            }
        }

        if (count($conflicts) > 0) {
            throw new HardwareIncompatibilityException($conflicts);
        }
    }
}
