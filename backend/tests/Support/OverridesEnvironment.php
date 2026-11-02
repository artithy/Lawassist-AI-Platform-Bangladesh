<?php

namespace Tests\Support;

use Closure;

/**
 * Runs a callback with some environment variables set (or removed), then puts everything back.
 * Used to evaluate a config file under a different APP_ENV / FRONTEND_ORIGIN, the way a real deployment would.
 * A null value means "not set at all".
 */
trait OverridesEnvironment
{
    /**
     * @param  array<string, string|null>  $variables
     */
    protected function withEnvironment(array $variables, Closure $callback): mixed
    {
        $saved = [];

        foreach ($variables as $name => $value) {
            $saved[$name] = [
                'env' => array_key_exists($name, $_ENV) ? $_ENV[$name] : null,
                'hasEnv' => array_key_exists($name, $_ENV),
                'server' => array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null,
                'hasServer' => array_key_exists($name, $_SERVER),
                'putenv' => getenv($name),
            ];

            $this->setEnvironmentVariable($name, $value);
        }

        try {
            return $callback();
        } finally {
            foreach ($saved as $name => $before) {
                if ($before['hasEnv']) {
                    $_ENV[$name] = $before['env'];
                } else {
                    unset($_ENV[$name]);
                }

                if ($before['hasServer']) {
                    $_SERVER[$name] = $before['server'];
                } else {
                    unset($_SERVER[$name]);
                }

                $before['putenv'] === false ? putenv($name) : putenv("{$name}={$before['putenv']}");
            }
        }
    }

    private function setEnvironmentVariable(string $name, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            return;
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }

    /** Evaluates a config file afresh, with the given environment. @return array<string, mixed> */
    protected function configFileUnder(string $file, array $variables): array
    {
        return $this->withEnvironment($variables, fn () => require base_path("config/{$file}.php"));
    }
}
