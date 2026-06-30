<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    public function test_user_can_switch_to_bulgarian_locale(): void
    {
        $this->from('/dashboard')
            ->get('/locale/bg')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('locale', 'bg');

        $this->withSession(['locale' => 'bg'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('lang="bg"', false)
            ->assertSee('Управленско табло');
    }

    public function test_user_can_switch_to_hindi_locale(): void
    {
        $this->from('/dashboard')
            ->get('/locale/hi')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('locale', 'hi');

        $this->withSession(['locale' => 'hi'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('lang="hi"', false)
            ->assertSee('कार्यकारी डैशबोर्ड');
    }

    public function test_unsupported_locale_is_not_found(): void
    {
        $this->get('/locale/fr')->assertNotFound();
    }

    public function test_duralux_pages_render_the_language_switcher(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('nxl-header-language')
            ->assertSee(route('locale.switch', 'en'))
            ->assertSee(route('locale.switch', 'bg'))
            ->assertSee(route('locale.switch', 'hi'));
    }
}
