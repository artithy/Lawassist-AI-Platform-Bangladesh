<?php

namespace Tests\Unit\Services\AI;

use Symfony\Component\Finder\Finder;
use Tests\Support\OverridesEnvironment;
use Tests\TestCase;

/** config/ollama.php, .env.example, and the rule that service code reads configuration only through config(). */
class OllamaConfigurationTest extends TestCase
{
    use OverridesEnvironment;

    private const NO_OLLAMA_ENV = ['OLLAMA_BASE_URL' => null, 'OLLAMA_MODEL' => null, 'OLLAMA_CONNECT_TIMEOUT' => null, 'OLLAMA_TIMEOUT' => null];

    public function test_the_defaults_suit_local_development_in_docker(): void
    {
        $config = $this->configFileUnder('ollama', self::NO_OLLAMA_ENV);

        $this->assertSame('http://host.docker.internal:11434', $config['base_url']);
        $this->assertSame('gemma4:31b-cloud', $config['model']);
        $this->assertSame(5, $config['connect_timeout']);
        $this->assertSame(120, $config['timeout']);
    }

    public function test_the_environment_overrides_every_value(): void
    {
        $config = $this->configFileUnder('ollama', [
            'OLLAMA_BASE_URL' => 'http://localhost:11434',
            'OLLAMA_MODEL' => 'llama3.2:3b',
            'OLLAMA_CONNECT_TIMEOUT' => '2',
            'OLLAMA_TIMEOUT' => '300',
        ]);

        $this->assertSame('http://localhost:11434', $config['base_url']);
        $this->assertSame('llama3.2:3b', $config['model']);
        $this->assertSame('2', $config['connect_timeout']);
        $this->assertSame('300', $config['timeout']);
    }

    public function test_the_config_file_has_exactly_the_four_documented_keys(): void
    {
        $config = $this->configFileUnder('ollama', self::NO_OLLAMA_ENV);

        $keys = array_keys($config);
        sort($keys);

        $this->assertSame(['base_url', 'connect_timeout', 'model', 'timeout'], $keys);
    }

    public function test_the_application_loads_the_ollama_config(): void
    {
        $this->assertIsArray(config('ollama'));
        $this->assertArrayHasKey('base_url', config('ollama'));
    }

    public function test_env_example_documents_the_settings_with_safe_placeholders(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        foreach ([
            'OLLAMA_BASE_URL=http://host.docker.internal:11434',
            'OLLAMA_MODEL=gemma4:31b-cloud',
            'OLLAMA_CONNECT_TIMEOUT=5',
            'OLLAMA_TIMEOUT=120',
        ] as $line) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($line, '/').'$/m', $example, $line);
        }

        // The opt-in flag is only ever a commented-out hint here, never switched on.
        $this->assertDoesNotMatchRegularExpression('/^RUN_OLLAMA_INTEGRATION_TESTS=/m', $example);
    }

    public function test_service_classes_never_read_the_environment_directly(): void
    {
        $offenders = [];

        foreach ((new Finder)->files()->in(base_path('app'))->name('*.php') as $file) {
            // Strip comments so documentation that mentions env() does not count.
            $code = preg_replace('#/\*.*?\*/|//[^\n]*|\#[^\n]*#s', '', $file->getContents()) ?? '';

            if (preg_match('/\b(?:env|getenv)\s*\(|\$_ENV\b|\$_SERVER\s*\[\s*[\'"]OLLAMA/', $code) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Read settings through config(), not env(): '.implode(', ', $offenders));
    }

    public function test_the_ollama_settings_do_not_hold_secrets_or_api_keys(): void
    {
        // No credentials are needed or supported: the config must not grow a key/token entry by accident.
        foreach (array_keys(require base_path('config/ollama.php')) as $key) {
            $this->assertDoesNotMatchRegularExpression('/key|token|secret|password/i', $key);
        }
    }
}
