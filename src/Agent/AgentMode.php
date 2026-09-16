<?php

namespace Packstub\Partisan\Agent;

use Laravel\AgentDetector\AgentDetector;

/**
 * Whether partisan is being driven by an AI coding agent rather than a
 * person at a terminal. `PARTISAN_AGENT=1` forces it on, `PARTISAN_AGENT=0`
 * off; otherwise laravel/agent-detector decides from the environment.
 */
final class AgentMode
{
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        $forced = getenv('PARTISAN_AGENT');

        if (is_string($forced) && trim($forced) !== '') {
            return self::$enabled = filter_var($forced, FILTER_VALIDATE_BOOLEAN);
        }

        return self::$enabled = AgentDetector::detect()->isAgent;
    }

    /**
     * @internal for tests
     */
    public static function reset(): void
    {
        self::$enabled = null;
    }
}
