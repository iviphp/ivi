<?php

declare(strict_types=1);

namespace Ivi\Core\Validation;

use Ivi\Core\Validation\Contracts\Rule;
use Ivi\Core\Validation\Rules\{
    Required,
    StringRule,
    IntegerRule,
    NumericRule,
    Email,
    Min,
    Max,
    Between,
    In,
    Regex,
    Sometimes
};

/**
 * Class Validator
 *
 * @package Ivi\Core\Validation
 *
 * @brief Core validation engine for Ivi.php.
 *
 * The `Validator` is responsible for parsing validation rules, executing
 * rule checks on input data, and collecting validation errors in a structured
 * `ErrorBag`. It provides the backbone of Ivi.php’s validation system and
 * supports a declarative, Laravel-style rule syntax.
 *
 * ### Core Responsibilities
 * - Parse and compile validation rules (string-based or object-based)
 * - Execute all rule checks for each field
 * - Aggregate and format human-readable error messages
 * - Throw a `ValidationException` when validation fails
 *
 * ### Rule Syntax
 * Validation rules can be defined as:
 * ```php
 * $validator = new Validator($data, [
 *     'name'  => 'required|string|min:3|max:50',
 *     'email' => ['required', 'email'],
 *     'age'   => [new IntegerRule(), new Min(18)],
 * ]);
 * ```
 * Rules may be written as:
 * - **Pipe syntax:** `"required|string|min:3"`
 * - **Array syntax:** `['required', 'string']`
 * - **Object syntax:** `[new Email(), new Min(10)]`
 *
 * ### Supported Built-in Rules
 * - `required`, `string`, `integer`, `numeric`, `email`
 * - `min`, `max`, `between`, `in`, `regex`, `sometimes`
 *
 * ### Workflow
 * 1. Rules are parsed and compiled into `Rule` objects.
 * 2. Each rule’s `passes()` method is executed in sequence.
 * 3. The first failure per field is recorded in the `ErrorBag`.
 * 4. If errors exist, a `ValidationException` is thrown.
 * 5. On success, a sanitized array of validated data is returned.
 *
 * ### Extending Validation
 * To add a custom rule, implement the `Ivi\Core\Validation\Contracts\Rule` interface:
 * ```php
 * final class AlphaDash implements Rule {
 *     public function passes(mixed $value, array $data, string $field): bool {
 *         return (bool)preg_match('/^[A-Za-z0-9_-]+$/', (string)$value);
 *     }
 *     public function message(string $field): string {
 *         return 'The :attribute may only contain letters, numbers, dashes and underscores.';
 *     }
 * }
 * ```
 * You can then use it in your controller:
 * ```php
 * $validator = new Validator($request->post(), [
 *     'username' => [new AlphaDash(), 'required'],
 * ]);
 * ```
 *
 * ### Error Handling
 * When validation fails, a `ValidationException` is thrown, carrying
 * an `ErrorBag` instance. This can be caught at the controller or middleware
 * level to display structured validation errors.
 *
 * ### Design Notes
 * - Validation stops at the first failing rule per field.
 * - Uses reflection to extract parameters for error message formatting.
 * - Rule definitions are case-insensitive and support dynamic parameter binding.
 *
 * @see \Ivi\Core\Validation\Contracts\Rule
 * @see \Ivi\Core\Validation\ValidationException
 * @see \Ivi\Core\Validation\ErrorBag
 * @see \Ivi\Core\Validation\Messages
 */
final class Validator
{
    /** @var array<string,mixed> Input data to validate. */
    private array $data;

    /** @var array<string,Rule[]> Compiled validation rules per field. */
    private array $compiled = [];

    /** @var ErrorBag Collected validation errors. */
    private ErrorBag $errors;

    /** @var array<string,string> Default error message templates. */
    private array $messages;

    /**
     * @param array<string,mixed> $data   The input data to validate.
     * @param array<string,mixed> $rules  Validation rules (pipe, array, or object syntax).
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->errors = new ErrorBag();
        $this->messages = Messages::defaults();

        $this->compile($rules);
    }

    /** Get the current ErrorBag instance. */
    public function errors(): ErrorBag
    {
        return $this->errors;
    }

    /**
     * Validate all fields and return sanitized data.
     *
     * Executes each compiled rule and collects the first failure per field.
     * Throws a `ValidationException` if any rule fails.
     *
     * @throws ValidationException
     * @return array<string,mixed> Validated data subset.
     */
    public function validate(): array
    {
        foreach ($this->compiled as $field => $rules) {
            $exists = array_key_exists($field, $this->data);
            $value  = $this->data[$field] ?? null;

            $isSometimes = $this->hasRule($rules, Sometimes::class);
            if ($isSometimes && !$exists) {
                continue; // skip fields not present
            }

            foreach ($rules as $rule) {
                if (!$rule->passes($value, $this->data, $field)) {
                    $this->errors->add($field, $this->formatMessage($rule, $field));
                    break; // stop after first failure per field
                }
            }
        }

        if (!$this->errors->isEmpty()) {
            throw new ValidationException($this->errors);
        }

        // Return only validated keys
        $clean = [];
        foreach (array_keys($this->compiled) as $f) {
            if (array_key_exists($f, $this->data)) {
                $clean[$f] = $this->data[$f];
            }
        }
        return $clean;
    }

    /** Check if a given rule type exists for the current field. */
    private function hasRule(array $rules, string $class): bool
    {
        foreach ($rules as $r) {
            if ($r instanceof $class) return true;
        }
        return false;
    }

    /**
     * Compile all rule definitions into instantiated Rule objects.
     *
     * @param array<string, string|Rule|Rule[]|string[]> $rules
     * @return void
     */
    private function compile(array $rules): void
    {
        foreach ($rules as $field => $pipeOrArray) {
            $this->compiled[$field] = [];

            $parts = is_string($pipeOrArray)
                ? explode('|', $pipeOrArray)
                : (is_array($pipeOrArray) ? $pipeOrArray : [$pipeOrArray]);

            foreach ($parts as $part) {
                if ($part instanceof Rule) {
                    $this->compiled[$field][] = $part;
                    continue;
                }
                if (is_array($part)) {
                    foreach ($part as $p) {
                        if ($p instanceof Rule) $this->compiled[$field][] = $p;
                    }
                    continue;
                }

                [$name, $params] = $this->parseRule($part);
                $this->compiled[$field][] = $this->makeRule($name, $params);
            }
        }
    }

    /** Parse a rule string like "min:3" into its name and parameters. */
    private function parseRule(string $s): array
    {
        $s = trim($s);
        $name = $s;
        $params = [];
        if (str_contains($s, ':')) {
            [$name, $raw] = explode(':', $s, 2);
            $params = array_map('trim', explode(',', $raw));
        }
        return [strtolower($name), $params];
    }

    /** Instantiate a Rule object from its name and parameters. */
    private function makeRule(string $name, array $params): Rule
    {
        return match ($name) {
            'required'  => new Required(),
            'string'    => new StringRule(),
            'integer'   => new IntegerRule(),
            'numeric'   => new NumericRule(),
            'email'     => new Email(),
            'min'       => new Min((float)($params[0] ?? 0)),
            'max'       => new Max((float)($params[0] ?? 0)),
            'between'   => new Between((float)($params[0] ?? 0), (float)($params[1] ?? 0)),
            'in'        => new In(...$params),
            'regex'     => new Regex($params[0] ?? '/.*/'),
            'sometimes' => new Sometimes(),
            default     => throw new \InvalidArgumentException("Unknown rule: {$name}"),
        };
    }

    /** Format the final error message for a failed rule. */
    private function formatMessage(Rule $rule, string $field): string
    {
        $ruleKey = $this->ruleKeyFromClass($rule);
        $template = $this->messages[$ruleKey] ?? $rule->message($field) ?? 'The :attribute is invalid.';
        $params = $this->paramsFromRule($rule);

        $msg = str_replace(':attribute', $field, $template);
        if ($params !== '') {
            $msg = str_replace(':params', $params, $msg);
        }
        return $msg;
    }

    /** Extract the short rule name for message lookup. */
    private function ruleKeyFromClass(Rule $rule): string
    {
        $short = strtolower((new \ReflectionClass($rule))->getShortName());
        return match ($short) {
            'stringrule' => 'string',
            default      => $short,
        };
    }

    /** Retrieve rule parameters for message substitution. */
    private function paramsFromRule(Rule $rule): string
    {
        return match (true) {
            $rule instanceof Min      => (string)(new \ReflectionProperty($rule, 'min'))->getValue($rule),
            $rule instanceof Max      => (string)(new \ReflectionProperty($rule, 'max'))->getValue($rule),
            $rule instanceof Between  => implode(',', [
                (new \ReflectionProperty($rule, 'min'))->getValue($rule),
                (new \ReflectionProperty($rule, 'max'))->getValue($rule),
            ]),
            $rule instanceof In       => implode(',', (new \ReflectionProperty($rule, 'set'))->getValue($rule)),
            $rule instanceof Regex    => (string)(new \ReflectionProperty($rule, 'pattern'))->getValue($rule),
            default                   => '',
        };
    }
}
