<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_expiring_cli_installation_creates_manager_and_consumes_config(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'install-');
        chmod($path, 0600);
        file_put_contents($path, json_encode(['email' => 'initial@example.test', 'name' => 'Synthetic manager', 'organization' => 'Synthetic organization', 'password' => 'SyntheticTestingPassword123!', 'expires_at' => now()->addHour()->toIso8601String()]));
        $this->artisan('app:initialize', ['--config' => $path])->assertExitCode(0);
        $this->assertFileDoesNotExist($path);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame('manager', DB::table('organization_members')->value('role'));
    }

    public function test_expired_bootstrap_file_cannot_create_account(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'install-');
        chmod($path, 0600);
        file_put_contents($path, json_encode(['email' => 'expired@example.test', 'name' => 'Test', 'organization' => 'Test', 'password' => 'SyntheticTestingPassword123!', 'expires_at' => now()->subHour()->toIso8601String()]));
        try {
            $this->artisan('app:initialize', ['--config' => $path])->assertExitCode(1);
            $this->assertSame(0, DB::table('users')->count());
        } finally {
            unlink($path);
        }
    }
}
