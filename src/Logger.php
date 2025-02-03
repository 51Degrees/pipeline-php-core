<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2025 51 Degrees Mobile Experts Limited, Davidson House,
 * Forbury Square, Reading, Berkshire, United Kingdom RG1 3EU.
 *
 * This Original Work is licensed under the European Union Public Licence
 * (EUPL) v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 *
 * If using the Work as, or as part of, a network application, by
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading,
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */

declare(strict_types=1);

namespace fiftyone\pipeline\core;

/**
 * Logging for a Pipeline.
 */
class Logger
{
    /**
     * @var array<string, mixed>
     */
    public array $settings;

    private int $minLevel;

    /**
     * @var array<string>
     */
    private array $levels = ['trace', 'debug', 'info', 'warning', 'error', 'critical'];

    /**
     * Create a logger.
     *
     * @param null|string $level Values: 'trace', 'debug', 'info', 'warning', 'error', 'critical'
     * @param array<string, mixed> $settings Custom settings for a logger
     */
    public function __construct(?string $level, array $settings = [])
    {
        $level = strtolower((string) $level);

        if (!in_array($level, $this->levels)) {
            $level = 'error';
        }

        $this->settings = $settings;
        $this->minLevel = array_search($level, $this->levels);
    }

    /**
     * Log a message.
     */
    public function log(string $level, string $message): void
    {
        $levelIndex = array_search(strtolower($level), $this->levels);

        if ($levelIndex >= $this->minLevel) {
            $log = [
                'time' => date('Y-m-d H:i:s'),
                'level' => $level,
                'message' => $message
            ];

            $this->logInternal($log);
        }
    }

    /**
     * Internal logging function overridden by specific loggers.
     *
     * @param array<string, string> $log
     */
    public function logInternal(array $log): void
    {
        return;
    }
}
