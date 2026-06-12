<?php

namespace Tests\Feature\Modular;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use App\Modules\Shared\Events\TransactionCompleted;
use App\Modules\Kernel\ModuleManifest;
use App\Modules\Kernel\ModuleDependencyException;

class EventBusCouplingTest extends TestCase
{
    public function test_zero_coupling_test()
    {
        // Register the provider dynamically to mock the module being active
        $provider = app()->register(\App\Modules\Pharmacy\Providers\PharmacyServiceProvider::class);
        
        $payload = [
            ['product_id' => 1, 'quantity' => 2]
        ];
        
        event(new TransactionCompleted(1, 999, $payload));

        // Assert that the pharmacy module intercepted and processed it securely without hard dependencies
        $this->assertTrue(Cache::has("pharmacy_transaction_999"));
    }

    public function test_dependency_resolution_test()
    {
        $manifest = new ModuleManifest();
        
        // Assume 'core-pos' is active, but 'serial-number' is not
        $activeModules = ['core-pos']; 

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessage('Cannot activate pharmacy. Missing core dependency: serial-number');

        $manifest->validateDependencies('pharmacy', $activeModules);
    }
}
