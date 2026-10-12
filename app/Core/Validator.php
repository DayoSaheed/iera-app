<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Small rule-based validator.
 *   $v = Validator::make($data, ['email' => 'required|email', 'age' => 'nullable|integer|min:1']);
 *   if ($v->fails()) { $v->errors() }
 */
final class Validator
{
    private array $errors = [];
    private array $data;

    private function __construct(array $data, private array $rules, private array $labels = [])
    {
        $this->data = $data;
        $this->validate();
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?string
    {
        foreach ($this->errors as $msgs) {
            return $msgs[0];
        }
        return null;
    }

    /** Only the validated keys */
    public function validated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $key) {
            if (array_key_exists($key, $this->data)) {
                $out[$key] = is_string($this->data[$key]) ? trim($this->data[$key]) : $this->data[$key];
            }
        }
        return $out;
    }

    private function label(string $key): string
    {
        return $this->labels[$key] ?? Str::startCase($key);
    }

    private function add(string $key, string $message): void
    {
        $this->errors[$key][] = $message;
    }

    private function validate(): void
    {
        foreach ($this->rules as $key => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$key] ?? null;
            $isEmpty = $value === null || $value === '' || $value === [];
            $nullable = in_array('nullable', $rules, true);

            if (in_array('required', $rules, true) && $isEmpty) {
                $this->add($key, $this->label($key) . ' is required.');
                continue;
            }
            if ($isEmpty && ($nullable || !in_array('required', $rules, true))) {
                continue;
            }
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $this->apply($key, $name, $arg, $value);
            }
        }
    }

    private function apply(string $key, string $name, ?string $arg, mixed $value): void
    {
        $label = $this->label($key);
        switch ($name) {
            case 'email':
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->add($key, "$label must be a valid email address.");
                }
                break;
            case 'max':
                if (is_string($value) && mb_strlen($value) > (int) $arg) {
                    $this->add($key, "$label may not be longer than $arg characters.");
                } elseif (is_numeric($value) && !is_string($value) && $value > (int) $arg) {
                    $this->add($key, "$label may not be greater than $arg.");
                } elseif (is_array($value) && count($value) > (int) $arg) {
                    $this->add($key, "$label may not have more than $arg items.");
                }
                break;
            case 'min':
                if (is_string($value) && !is_numeric($value) && mb_strlen($value) < (int) $arg) {
                    $this->add($key, "$label must be at least $arg characters.");
                } elseif (is_numeric($value) && (float) $value < (float) $arg) {
                    $this->add($key, "$label must be at least $arg.");
                }
                break;
            case 'in':
                $allowed = explode(',', (string) $arg);
                $values = is_array($value) ? $value : [$value];
                foreach ($values as $v) {
                    if (!in_array((string) $v, $allowed, true)) {
                        $this->add($key, "$label has an invalid value.");
                        break;
                    }
                }
                break;
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->add($key, "$label must be a number.");
                }
                break;
            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->add($key, "$label must be a whole number.");
                }
                break;
            case 'date':
                if (!is_string($value) || strtotime($value) === false) {
                    $this->add($key, "$label must be a valid date.");
                }
                break;
            case 'boolean':
                if (!in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off', 'yes', 'no'], true)) {
                    $this->add($key, "$label must be yes or no.");
                }
                break;
            case 'array':
                if (!is_array($value)) {
                    $this->add($key, "$label must be a list.");
                }
                break;
            case 'string':
                if (!is_string($value)) {
                    $this->add($key, "$label must be text.");
                }
                break;
            case 'regex':
                if (!is_string($value) || !preg_match((string) $arg, $value)) {
                    $this->add($key, "$label format is invalid.");
                }
                break;
            case 'same':
                if ($value !== ($this->data[$arg] ?? null)) {
                    $this->add($key, "$label does not match " . $this->label((string) $arg) . '.');
                }
                break;
            case 'phone':
                $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
                if (strlen($digits) < 10 || strlen($digits) > 11) {
                    $this->add($key, "$label must be a valid phone number.");
                }
                break;
            case 'postal_ca':
                if (!preg_match('/^[A-Za-z]\d[A-Za-z]\s?\d[A-Za-z]\d$/', (string) $value)) {
                    $this->add($key, "$label must be a valid Canadian postal code.");
                }
                break;
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->add($key, "$label must be a valid URL.");
                }
                break;
            case 'uuid':
                if (!preg_match('/^[0-9a-f-]{36}$/i', (string) $value)) {
                    $this->add($key, "$label is invalid.");
                }
                break;
            case 'slug':
                if (!preg_match('/^[a-z0-9_]+$/', (string) $value)) {
                    $this->add($key, "$label may only contain lowercase letters, numbers and underscores.");
                }
                break;
        }
    }
}
