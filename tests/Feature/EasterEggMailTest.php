<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SignInWithOidcNotice;
use Tests\TestCase;

class EasterEggMailTest extends TestCase
{
    private function footer(): string
    {
        return (string) (new SignInWithOidcNotice)->toMail(new User)->render();
    }

    public function test_the_footer_links_to_the_account_manager_when_connected(): void
    {
        config(['households.account_manager.url' => 'https://konto.example.com/']);

        $html = $this->footer();

        $this->assertStringContainsString('<a href="https://konto.example.com/easter-egg"', $html);
        $this->assertStringContainsString(__('All rights reserved.').'</a>', $html);
    }

    public function test_the_footer_is_plain_text_without_account_manager(): void
    {
        config(['households.account_manager.url' => null]);

        $html = $this->footer();

        $this->assertStringContainsString(__('All rights reserved.'), $html);
        $this->assertStringNotContainsString('easter-egg', $html);
        $this->assertStringNotContainsString(__('All rights reserved.').'</a>', $html);
    }
}
