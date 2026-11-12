<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_api_call_is_logged_without_its_password(): void
    {
        $this->postJson('/api/v1/driver/login', ['email' => 'nobody@og.local', 'password' => 'secret123'])->assertStatus(422);

        $log = ApiLog::query()->sole();
        $this->assertSame('POST', $log->method);
        $this->assertSame('api/v1/driver/login', $log->path);
        $this->assertSame(422, $log->status);
        $this->assertSame('nobody@og.local', $log->request['email']);
        $this->assertSame('***', $log->request['password']);
        $this->assertStringContainsString('Invalid driver credentials', $log->response);
        $this->assertStringNotContainsString('secret123', json_encode($log->getAttributes()));
    }

    public function test_the_month_end_clean_up_keeps_only_this_month(): void
    {
        $this->travelTo('2026-10-31 23:30:00');
        $row = ['method' => 'GET', 'path' => 'api/x', 'status' => 200, 'duration_ms' => 1];
        ApiLog::query()->create($row + ['created_at' => '2026-09-30 23:59:59']);
        ApiLog::query()->create($row + ['created_at' => '2026-10-01 00:00:00']);
        ApiLog::query()->create($row + ['created_at' => '2026-10-31 10:00:00']);

        $this->artisan('og:prune-api-logs')->assertSuccessful();

        $this->assertSame(['2026-10-01 00:00:00', '2026-10-31 10:00:00'], ApiLog::query()->orderBy('id')->pluck('created_at')->map->toDateTimeString()->all());
    }
}
