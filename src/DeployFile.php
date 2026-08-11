<?php

namespace Sydgren\ShipIt;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Local validation of a repository's `.shipit/deploy.yml`.
 *
 * ShipIt validates the file again when it deploys and remains the authority —
 * this exists so a mistake is caught in the editor or in CI rather than by a
 * failed deployment. The rules mirror ShipIt's; if they ever disagree, ShipIt
 * wins and the deployment log says why.
 */
class DeployFile
{
    public const PATH = '.shipit/deploy.yml';

    public const MAX_SCRIPT_LENGTH = 8000;

    private const ALLOWED_KEYS = ['name', 'script', 'enabled', 'critical'];

    /**
     * Every problem found in the file, as messages ready to print.
     *
     * An empty list means the file would be accepted.
     *
     * @return list<string>
     */
    public function problems(string $contents): array
    {
        if (trim($contents) === '') {
            return ['The file is empty.'];
        }

        try {
            $parsed = Yaml::parse($contents);
        } catch (ParseException $e) {
            return ['Not valid YAML: '.$e->getMessage()];
        }

        if (! is_array($parsed) || ! array_key_exists('steps', $parsed)) {
            return ['Missing a top-level `steps:` list.'];
        }

        $steps = $parsed['steps'];

        if (! is_array($steps) || $steps === [] || ! array_is_list($steps)) {
            return ['`steps:` must be a non-empty list.'];
        }

        $problems = [];

        foreach ($steps as $index => $step) {
            foreach ($this->stepProblems($step, $index + 1) as $problem) {
                $problems[] = $problem;
            }
        }

        return $problems;
    }

    /**
     * The step names in the file, for a summary after a successful check.
     *
     * @return list<string>
     */
    public function stepNames(string $contents): array
    {
        $parsed = Yaml::parse($contents);

        return array_map(
            fn (array $step): string => (string) $step['name'],
            $parsed['steps'] ?? [],
        );
    }

    /**
     * @return list<string>
     */
    private function stepProblems(mixed $step, int $position): array
    {
        if (! is_array($step)) {
            return ["Step {$position}: must be a mapping with `name` and `script`."];
        }

        $problems = [];

        if (($unknown = array_diff(array_keys($step), self::ALLOWED_KEYS)) !== []) {
            $problems[] = "Step {$position}: unknown key(s) ".implode(', ', $unknown)
                .'. Allowed: '.implode(', ', self::ALLOWED_KEYS).'.';
        }

        foreach (['name', 'script'] as $required) {
            if (! isset($step[$required]) || ! is_string($step[$required]) || trim($step[$required]) === '') {
                $problems[] = "Step {$position}: `{$required}` is required and must not be blank.";
            }
        }

        if (isset($step['script']) && is_string($step['script'])
            && strlen($step['script']) > self::MAX_SCRIPT_LENGTH) {
            $problems[] = "Step {$position}: `script` is longer than ".self::MAX_SCRIPT_LENGTH.' characters.';
        }

        foreach (['enabled', 'critical'] as $flag) {
            if (array_key_exists($flag, $step) && ! is_bool($step[$flag])) {
                $problems[] = "Step {$position}: `{$flag}` must be true or false.";
            }
        }

        return $problems;
    }
}
