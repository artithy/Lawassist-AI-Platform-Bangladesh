<?php

namespace App\Support;

/**
 * Turns the FRONTEND_ORIGIN setting into the list of origins the browser frontend may call this API from (CORS).
 *
 * The setting is one origin or a comma-separated list, each exactly "http(s)://host[:port]": no path, query, fragment,
 * credentials or wildcard. Anything else is dropped, so a typo ends up allowing nothing rather than something too broad.
 */
final class FrontendOrigins
{
    /** The local Vite dev server. Used ONLY when FRONTEND_ORIGIN is not set AND the app runs in a development environment. */
    public const DEFAULT = 'http://localhost:5173';

    /** Environments in which the default above applies. Every other environment (production, staging, unknown...) gets none. */
    public const DEVELOPMENT_ENVIRONMENTS = ['local', 'testing'];

    /** scheme://host[:port], nothing else; host labels are letters, digits and inner hyphens. The character classes has no "*", so wildcards can never match. */
    private const ORIGIN = '/\Ahttps?:\/\/[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*(?::[0-9]{1,5})?\z/';

    /**
     * The origins to allow, from the FRONTEND_ORIGIN setting and the environment name.
     *
     *  - Setting present (even empty): it alone decides; an empty or invalid setting allows nothing.
     *  - Setting absent: the local dev server in a development environment, NOTHING anywhere else. A production deployment
     *    that forgot FRONTEND_ORIGIN must not quietly trust localhost.
     *
     * @return list<string>
     */
    public static function resolve(?string $setting, ?string $environment): array
    {
        if ($setting !== null) {
            return self::parse($setting);
        }

        return in_array(strtolower(trim((string) $environment)), self::DEVELOPMENT_ENVIRONMENTS, true)
            ? self::parse(self::DEFAULT)
            : [];
    }

    /** @return list<string> */
    public static function parse(?string $setting): array
    {
        $origins = [];

        foreach (explode(',', (string) $setting) as $candidate) {
            $candidate = strtolower(trim($candidate));

            if (preg_match(self::ORIGIN, $candidate) === 1 && ! in_array($candidate, $origins, true)) {
                $origins[] = $candidate;
            }
        }

        return $origins;
    }
}
