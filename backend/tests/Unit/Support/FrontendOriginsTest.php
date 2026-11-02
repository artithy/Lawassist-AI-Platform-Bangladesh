<?php

namespace Tests\Unit\Support;

use App\Support\FrontendOrigins;
use PHPUnit\Framework\TestCase;

class FrontendOriginsTest extends TestCase
{
    public function test_a_single_origin_is_accepted(): void
    {
        $this->assertSame(['http://localhost:5173'], FrontendOrigins::parse('http://localhost:5173'));
        $this->assertSame(['https://app.example.com'], FrontendOrigins::parse('https://app.example.com'));
    }

    public function test_several_origins_are_accepted_trimmed_lowercased_and_deduplicated(): void
    {
        $this->assertSame(
            ['http://localhost:5173', 'https://app.example.com'],
            FrontendOrigins::parse(' http://localhost:5173 , HTTPS://App.Example.com,http://localhost:5173, '),
        );
    }

    public function test_the_default_is_the_local_vite_dev_server(): void
    {
        $this->assertSame('http://localhost:5173', FrontendOrigins::DEFAULT);
        $this->assertSame(['http://localhost:5173'], FrontendOrigins::parse(FrontendOrigins::DEFAULT));
    }

    public function test_whitespace_around_an_entry_is_ignored(): void
    {
        $this->assertSame(['http://localhost:5173'], FrontendOrigins::parse("  http://localhost:5173\n"));
    }

    public function test_nothing_or_blank_allows_nothing(): void
    {
        foreach ([null, '', '   ', ',', ' , , '] as $setting) {
            $this->assertSame([], FrontendOrigins::parse($setting));
        }
    }

    public function test_wildcards_are_never_accepted(): void
    {
        foreach (['*', 'http://*', 'https://*.example.com', 'http://localhost:*', '*.example.com', 'null'] as $setting) {
            $this->assertSame([], FrontendOrigins::parse($setting), $setting);
        }
    }

    public function test_anything_that_is_not_a_bare_origin_is_dropped(): void
    {
        foreach ([
            'localhost:5173', '//localhost:5173', 'ftp://localhost', 'javascript://x', 'http://', 'http://:5173',
            'http://localhost/', 'http://localhost:5173/', 'http://localhost/app', 'http://localhost?x=1', 'http://localhost#x',
            'http://user@localhost', 'http://user:pw@localhost', 'http://local host', "http://local\nhost", 'http://localhost:abc',
            'http://localhost:123456', 'http://-bad.example.com', 'http://bad-.example.com', 'http://a..example.com', 'http://.example.com', 'http://example.com.',
        ] as $setting) {
            $this->assertSame([], FrontendOrigins::parse($setting), $setting);
        }
    }

    public function test_a_bad_entry_does_not_spoil_the_good_ones(): void
    {
        $this->assertSame(
            ['http://localhost:5173'],
            FrontendOrigins::parse('*,http://localhost:5173,https://x.example.com/path'),
        );
    }

    // ---- resolve(): the setting plus the environment ---------------------------------------------------------

    public function test_a_present_setting_always_wins_in_every_environment(): void
    {
        foreach (['local', 'testing', 'production', 'staging', null] as $environment) {
            $this->assertSame(['https://app.example.com'], FrontendOrigins::resolve('https://app.example.com', $environment));
        }
    }

    public function test_an_empty_or_invalid_setting_allows_nothing_even_in_development(): void
    {
        foreach (['local', 'testing'] as $environment) {
            foreach (['', '   ', '*', 'nonsense'] as $setting) {
                $this->assertSame([], FrontendOrigins::resolve($setting, $environment), "{$environment} / [{$setting}]");
            }
        }
    }

    public function test_without_a_setting_only_development_environments_get_the_localhost_default(): void
    {
        $this->assertSame(['http://localhost:5173'], FrontendOrigins::resolve(null, 'local'));
        $this->assertSame(['http://localhost:5173'], FrontendOrigins::resolve(null, 'testing'));
        $this->assertSame(['http://localhost:5173'], FrontendOrigins::resolve(null, ' LOCAL '));
    }

    public function test_without_a_setting_production_and_every_other_environment_allow_nothing(): void
    {
        foreach (['production', 'prod', 'staging', 'development', 'dev', 'preview', 'qa', '', '  ', null, 'localhost', 'local2'] as $environment) {
            $this->assertSame([], FrontendOrigins::resolve(null, $environment), 'environment: '.var_export($environment, true));
        }
    }

    public function test_the_development_list_is_exactly_local_and_testing(): void
    {
        $this->assertSame(['local', 'testing'], FrontendOrigins::DEVELOPMENT_ENVIRONMENTS);
    }
}
