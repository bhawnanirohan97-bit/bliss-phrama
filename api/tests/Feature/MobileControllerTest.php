<?php

namespace Tests\Feature;

use App\Models\AiBotSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_mobile_dashboard(): void
    {
        $response = $this->get('/mobile');
        $response->assertStatus(200);
        $response->assertSee('Bliss Pharmex AI');
        $response->assertSee('Mobile Lead Catcher');
    }

    public function test_it_toggles_bot_from_mobile(): void
    {
        $setting = AiBotSetting::current();
        $setting->update(['enabled' => false]);

        $response = $this->post('/mobile/toggle', ['enabled' => true]);
        $response->assertRedirect('/mobile');

        $this->assertTrue(AiBotSetting::current()->fresh()->enabled);

        // Test ajax toggle
        $response = $this->postJson('/mobile/toggle', ['enabled' => false]);
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'enabled' => false,
            'status_text' => 'OFF',
        ]);
        $this->assertFalse(AiBotSetting::current()->fresh()->enabled);
    }

    public function test_it_updates_criteria_from_mobile(): void
    {
        $response = $this->post('/mobile/criteria', [
            'reject_domestic' => '1',
            'country_filters' => ['US', 'GB', 'AU', 'PH'],
        ]);

        $response->assertRedirect('/mobile');
        $setting = AiBotSetting::current()->fresh();
        $this->assertTrue($setting->reject_domestic);
        $this->assertEquals(['US', 'GB', 'AU', 'PH'], $setting->country_filters);
    }
}
