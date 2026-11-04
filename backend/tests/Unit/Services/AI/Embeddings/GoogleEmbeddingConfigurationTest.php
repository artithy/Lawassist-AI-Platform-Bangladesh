<?php

namespace Tests\Unit\Services\AI\Embeddings;

use Symfony\Component\Finder\Finder;
use Tests\Support\OverridesEnvironment;
use Tests\TestCase;

/**
 * config/google_ai.php, .env.example, and the rules that keep the API key out of the code, the repository and the logs.
 *
 * Nothing here opens .env (the real secrets file). The scans below look only at files that are committed or that the
 * application writes, and when they find something they report the file name, never the line.
 */
class GoogleEmbeddingConfigurationTest extends TestCase
{
    use OverridesEnvironment;

    private const NO_GOOGLE_ENV = [
        'GOOGLE_AI_API_KEY' => null,
        'GOOGLE_AI_BASE_URL' => null,
        'GOOGLE_AI_EMBEDDING_MODEL' => null,
        'GOOGLE_AI_EMBEDDING_DIMENSIONS' => null,
        'GOOGLE_AI_CONNECT_TIMEOUT' => null,
        'GOOGLE_AI_TIMEOUT' => null,
        'GOOGLE_AI_EMBEDDING_BATCH_SIZE' => null,
    ];

    /** A Google API key looks like "AIza" and 35 more characters. Written in two pieces so this file is not a match itself. */
    private const KEY_SHAPE = '/AI'.'za[0-9A-Za-z_\-]{35}/';

    // ---- the config file -------------------------------------------------------------------------------------

    public function test_the_defaults_are_the_approved_choices(): void
    {
        $config = $this->configFileUnder('google_ai', self::NO_GOOGLE_ENV);

        $this->assertNull($config['api_key']); // no default: a missing key must stop the service, not be guessed
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta', $config['base_url']);
        $this->assertSame('gemini-embedding-001', $config['embedding_model']);
        $this->assertSame(1536, $config['embedding_dimensions']);
        $this->assertSame(5, $config['connect_timeout']);
        $this->assertSame(30, $config['timeout']);
        $this->assertSame(50, $config['batch_size']);
    }

    public function test_the_environment_overrides_every_value(): void
    {
        $config = $this->configFileUnder('google_ai', [
            'GOOGLE_AI_API_KEY' => 'test-key-not-real-0123456789',
            'GOOGLE_AI_BASE_URL' => 'https://google.test/v1',
            'GOOGLE_AI_EMBEDDING_MODEL' => 'other-model',
            'GOOGLE_AI_EMBEDDING_DIMENSIONS' => '768',
            'GOOGLE_AI_CONNECT_TIMEOUT' => '2',
            'GOOGLE_AI_TIMEOUT' => '60',
            'GOOGLE_AI_EMBEDDING_BATCH_SIZE' => '10',
        ]);

        $this->assertSame('test-key-not-real-0123456789', $config['api_key']);
        $this->assertSame('https://google.test/v1', $config['base_url']);
        $this->assertSame('other-model', $config['embedding_model']);
        $this->assertSame('768', $config['embedding_dimensions']);
        $this->assertSame('2', $config['connect_timeout']);
        $this->assertSame('60', $config['timeout']);
        $this->assertSame('10', $config['batch_size']);
    }

    public function test_the_config_file_has_exactly_the_seven_documented_keys(): void
    {
        $keys = array_keys($this->configFileUnder('google_ai', self::NO_GOOGLE_ENV));
        sort($keys);

        $this->assertSame(['api_key', 'base_url', 'batch_size', 'connect_timeout', 'embedding_dimensions', 'embedding_model', 'timeout'], $keys);
    }

    public function test_api_key_is_the_only_secret_setting(): void
    {
        $secretLooking = array_filter(
            array_keys(require base_path('config/google_ai.php')),
            fn (string $key) => preg_match('/key|token|secret|password/i', $key) === 1,
        );

        $this->assertSame(['api_key'], array_values($secretLooking));
    }

    public function test_the_key_is_read_from_the_environment_with_no_fallback_value(): void
    {
        $source = (string) file_get_contents(base_path('config/google_ai.php'));

        $this->assertMatchesRegularExpression("/'api_key'\\s*=>\\s*env\\(\\s*'GOOGLE_AI_API_KEY'\\s*\\)\\s*,/", $source);
    }

    public function test_the_application_loads_the_google_ai_config(): void
    {
        $this->assertIsArray(config('google_ai'));
        $this->assertArrayHasKey('embedding_dimensions', config('google_ai'));
    }

    // ---- .env.example and phpunit.xml ------------------------------------------------------------------------

    public function test_env_example_documents_the_settings_with_an_empty_key_and_safe_values(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        foreach ([
            'GOOGLE_AI_API_KEY=',
            'GOOGLE_AI_EMBEDDING_MODEL=gemini-embedding-001',
            'GOOGLE_AI_EMBEDDING_DIMENSIONS=1536',
            'GOOGLE_AI_BASE_URL=https://generativelanguage.googleapis.com/v1beta',
            'GOOGLE_AI_CONNECT_TIMEOUT=5',
            'GOOGLE_AI_TIMEOUT=30',
            'GOOGLE_AI_EMBEDDING_BATCH_SIZE=50',
        ] as $line) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($line, '/').'$/m', $example, $line);
        }

        // The opt-in flag is only ever a commented-out hint here, never switched on.
        $this->assertDoesNotMatchRegularExpression('/^RUN_GOOGLE_EMBEDDING_INTEGRATION_TESTS=/m', $example);
    }

    public function test_phpunit_xml_neither_holds_the_key_nor_switches_the_real_test_on(): void
    {
        $xml = (string) file_get_contents(base_path('phpunit.xml'));

        $this->assertStringNotContainsString('GOOGLE_AI', $xml);
        $this->assertStringNotContainsString('RUN_GOOGLE_EMBEDDING_INTEGRATION_TESTS', $xml);
    }

    // ---- the key stays out of the code and the repository ----------------------------------------------------

    public function test_only_the_config_file_knows_the_environment_variable_names(): void
    {
        $offenders = [];

        foreach ((new Finder)->files()->in(base_path('app'))->name('*.php') as $file) {
            if (str_contains($file->getContents(), 'GOOGLE_AI_')) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Read settings through config(), not by variable name: '.implode(', ', $offenders));
    }

    public function test_no_google_api_key_is_written_into_any_project_file(): void
    {
        $offenders = [];
        $finder = (new Finder)->files()->ignoreDotFiles(false)
            ->in(array_filter([
                base_path('app'), base_path('config'), base_path('database'), base_path('routes'), base_path('tests'),
                base_path('docs'), base_path('resources'), base_path('bootstrap'),
            ], 'is_dir'))
            ->exclude(['cache', 'vendor', 'node_modules'])
            ->notName(['.env', '.env.*']);

        foreach ($finder as $file) {
            if (preg_match(self::KEY_SHAPE, $file->getContents()) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        foreach (['.env.example', 'phpunit.xml', 'composer.json', 'README.md', 'docker-compose.yml', '../docker-compose.yml'] as $name) {
            $path = base_path($name);

            if (is_file($path) && preg_match(self::KEY_SHAPE, (string) file_get_contents($path)) === 1) {
                $offenders[] = $name;
            }
        }

        $this->assertSame([], $offenders, 'A Google API key is written into: '.implode(', ', $offenders));
    }

    public function test_no_file_assigns_a_value_to_the_key_variable_except_the_private_env_file(): void
    {
        $offenders = [];
        $finder = (new Finder)->files()->ignoreDotFiles(false)
            ->in(array_filter([base_path('app'), base_path('config'), base_path('database'), base_path('routes'), base_path('docs'), base_path('resources')], 'is_dir'))
            ->exclude(['cache', 'vendor', 'node_modules']);

        foreach ($finder as $file) {
            if (preg_match('/GOOGLE_AI_API_KEY[ \t]*=[ \t]*[^\s#]/', $file->getContents()) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        foreach (['.env.example', '../docker-compose.yml'] as $name) {
            $path = base_path($name);

            if (is_file($path) && preg_match('/GOOGLE_AI_API_KEY[ \t]*[=:][ \t]*[^\s#]/', (string) file_get_contents($path)) === 1) {
                $offenders[] = $name;
            }
        }

        $this->assertSame([], $offenders, 'The key variable is given a value in: '.implode(', ', $offenders));
    }

    public function test_the_application_logs_hold_no_google_api_key(): void
    {
        $offenders = [];
        $logs = base_path('storage/logs');

        if (is_dir($logs)) {
            foreach ((new Finder)->files()->in($logs)->name('*.log') as $file) {
                if (preg_match(self::KEY_SHAPE, $file->getContents()) === 1) {
                    $offenders[] = $file->getFilename();
                }
            }
        }

        $this->assertSame([], $offenders, 'A Google API key is in the log file(s): '.implode(', ', $offenders));
    }

    public function test_the_assignment_scan_tells_an_empty_placeholder_from_a_value(): void
    {
        $pattern = '/GOOGLE_AI_API_KEY[ \t]*=[ \t]*[^\s#]/';

        $this->assertSame(0, preg_match($pattern, "GOOGLE_AI_API_KEY=\nGOOGLE_AI_EMBEDDING_MODEL=x"));
        $this->assertSame(0, preg_match($pattern, 'GOOGLE_AI_API_KEY= # set it in .env'));
        $this->assertSame(1, preg_match($pattern, 'GOOGLE_AI_API_KEY=something'));
        $this->assertSame(1, preg_match($pattern, 'GOOGLE_AI_API_KEY = something'));
    }

    public function test_the_key_scan_can_find_a_key(): void
    {
        // Proof that the pattern works, so the "nothing found" results above mean something.
        $this->assertSame(1, preg_match(self::KEY_SHAPE, 'x AI'.'zaSyA-1234567890abcdefghijklmnopqrstuv_ y'));
        $this->assertSame(0, preg_match(self::KEY_SHAPE, 'test-key-not-real-0123456789'));
    }
}
