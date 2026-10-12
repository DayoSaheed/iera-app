<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\Team;
use App\Models\User;

/**
 * Renders and validates dynamic forms defined in form_templates.
 *
 * Field definition keys: type, name, label, required, options[{label,value}], multiple, dynamic (teams|newmuslims|mentors|volunteers|parents),
 * condition {name, value, not}, hidden, editable, info, placeholder, min, max, step, max (files), error, rows.
 * Types: section, text, email, tel, textarea, number, checkbox, select, city_auto, date, time, datetime, upload, text_copy, typography, text_array
 *
 * A "section" field starts a new step in multi-step mode.
 */
final class FormRenderer
{
    /**
     * @param array $fields template fields
     * @param array $values current values (name => value)
     * @param array $opts requiredOnly, viewOnly, showHidden, steps (bool), existingFiles (name => [docs]), adminBypass
     */
    public static function render(array $fields, array $values = [], array $opts = []): string
    {
        $requiredOnly = !empty($opts['requiredOnly']);
        $viewOnly = !empty($opts['viewOnly']);
        $showHidden = !empty($opts['showHidden']);
        $useSteps = !empty($opts['steps']) && self::hasSections($fields);

        $steps = [];
        $current = ['label' => $opts['firstStepLabel'] ?? 'Details', 'html' => ''];
        foreach ($fields as $f) {
            $type = $f['type'] ?? 'text';
            if ($type === 'section') {
                if ($useSteps) {
                    if ($current['html'] !== '') {
                        $steps[] = $current;
                    }
                    $current = ['label' => $f['label'] ?? 'Step', 'html' => ''];
                } else {
                    $current['html'] .= '<h3 class="form-section">' . e($f['label'] ?? '') . '</h3>';
                }
                continue;
            }
            if (!empty($f['hidden']) && !$showHidden) {
                continue;
            }
            if ($requiredOnly && empty($f['required']) && !in_array($type, ['text_copy', 'typography'], true)) {
                continue;
            }
            $current['html'] .= self::field($f, $values, $viewOnly, $opts);
        }
        $steps[] = $current;

        if (!$useSteps || count($steps) === 1) {
            return implode('', array_column($steps, 'html'));
        }
        $html = '<div class="stepper" data-stepper><ol class="stepper-track">';
        foreach ($steps as $i => $s) {
            $html .= '<li class="stepper-item' . ($i === 0 ? ' is-active' : '') . '" data-step-tab="' . $i . '"><span class="stepper-num">' . ($i + 1) . '</span><span class="stepper-label">' . e($s['label']) . '</span></li>';
        }
        $html .= '</ol>';
        foreach ($steps as $i => $s) {
            $html .= '<div class="form-step" data-step="' . $i . '"' . ($i === 0 ? '' : ' hidden') . '>' . $s['html'] . '</div>';
        }
        $html .= '<div class="stepper-nav"><button type="button" class="btn btn-ghost" data-step-prev hidden>Back</button><span class="grow"></span><button type="button" class="btn btn-primary" data-step-next>Next</button>'
            . '<button type="submit" class="btn btn-primary" data-step-submit hidden>' . e($opts['submitLabel'] ?? 'Submit') . '</button></div></div>';
        return $html;
    }

    public static function hasSections(array $fields): bool
    {
        foreach ($fields as $f) {
            if (($f['type'] ?? '') === 'section') {
                return true;
            }
        }
        return false;
    }

    private static function field(array $f, array $values, bool $viewOnly, array $opts): string
    {
        $type = $f['type'] ?? 'text';
        $name = (string) ($f['name'] ?? '');
        $label = (string) ($f['label'] ?? $name);
        $value = $values[$name] ?? null;
        $required = !empty($f['required']);
        $disabled = $viewOnly || (isset($f['editable']) && $f['editable'] === false && $value !== null && $value !== '');
        $attrs = ' data-field="' . e($name) . '"';
        if (!empty($f['condition'])) {
            $attrs .= ' data-condition=\'' . e(json_encode($f['condition'])) . '\'';
        }
        $wrap = '<div class="field' . ($required ? ' is-required' : '') . '"' . $attrs . '>';
        $info = !empty($f['info']) ? '<p class="field-info">' . e($f['info']) . '</p>' : '';
        $id = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $common = ' id="' . $id . '" name="' . e($name) . '"' . ($required ? ' data-required="1"' : '') . ($disabled ? ' disabled' : '');
        $ph = !empty($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '';
        $lbl = '<label for="' . $id . '">' . e($label) . ($required ? ' <span class="req">*</span>' : '') . '</label>';

        switch ($type) {
            case 'typography':
                return '<div class="field-static">' . nl2br(e($f['text'] ?? $label)) . '</div>';
            case 'text_copy':
                $links = '';
                foreach ($f['options'] ?? [] as $o) {
                    $links .= '<li><a href="' . e($o['value'] ?? '#') . '" target="_blank" rel="noopener">' . e($o['label'] ?? $o['value'] ?? '') . '</a></li>';
                }
                return '<div class="field field-copy"><strong>' . e($label) . '</strong>' . $info . ($links ? '<ul>' . $links . '</ul>' : '') . '</div>';
            case 'textarea':
                return $wrap . $lbl . $info . '<textarea' . $common . $ph . ' rows="' . (int) ($f['rows'] ?? 4) . '">' . e($value) . '</textarea></div>';
            case 'number':
                $extra = '';
                foreach (['min', 'max', 'step'] as $k) {
                    if (isset($f[$k])) {
                        $extra .= ' ' . $k . '="' . e($f[$k]) . '"';
                    }
                }
                return $wrap . $lbl . $info . '<input type="number"' . $common . $ph . $extra . ' value="' . e($value) . '"></div>';
            case 'checkbox':
                $checked = in_array($value, [true, 1, '1', 'on', 'yes', 'agree'], true) ? ' checked' : '';
                return $wrap . '<label class="check"><input type="checkbox"' . $common . ' value="1"' . $checked . '> <span>' . e($label) . ($required ? ' <span class="req">*</span>' : '') . '</span></label>' . $info . '</div>';
            case 'select':
                $options = self::options($f, $opts);
                $multiple = !empty($f['multiple']);
                $selected = is_array($value) ? array_map('strval', $value) : ($value === null || $value === '' ? [] : [(string) $value]);
                $html = $wrap . $lbl . $info;
                if ($multiple) {
                    $html .= '<div class="choices" data-multiselect>';
                    foreach ($options as $o) {
                        $v = (string) $o['value'];
                        $html .= '<label class="choice"><input type="checkbox" name="' . e($name) . '[]" value="' . e($v) . '"' . (in_array($v, $selected, true) ? ' checked' : '') . ($disabled ? ' disabled' : '') . ($required ? ' data-required-group="' . e($name) . '"' : '') . '> <span>' . e($o['label']) . '</span></label>';
                    }
                    return $html . '</div></div>';
                }
                $html .= '<select' . $common . '><option value="">Select…</option>';
                foreach ($options as $o) {
                    $v = (string) $o['value'];
                    $html .= '<option value="' . e($v) . '"' . (in_array($v, $selected, true) ? ' selected' : '') . '>' . e($o['label']) . '</option>';
                }
                return $html . '</select></div>';
            case 'city_auto':
                return $wrap . $lbl . $info . '<input type="text"' . $common . ' value="' . e($value) . '" placeholder="Start typing a city…" autocomplete="off" data-city-autocomplete list="' . $id . '_list"><datalist id="' . $id . '_list"></datalist></div>';
            case 'date':
                return $wrap . $lbl . $info . '<input type="date"' . $common . ' value="' . e(self::fmt($value, 'Y-m-d')) . '"></div>';
            case 'time':
                return $wrap . $lbl . $info . '<input type="time"' . $common . ' value="' . e($value) . '"></div>';
            case 'datetime':
                return $wrap . $lbl . $info . '<input type="datetime-local"' . $common . ' value="' . e(self::fmt($value, 'Y-m-d\TH:i')) . '"></div>';
            case 'upload':
                $max = (int) ($f['max'] ?? 1);
                $html = $wrap . $lbl . $info;
                $existing = $opts['existingFiles'][$name] ?? [];
                if ($existing) {
                    $html .= '<ul class="file-list">';
                    foreach ($existing as $d) {
                        $html .= '<li><a href="' . e(url('/documents/' . $d['id'])) . '" target="_blank" rel="noopener">' . e($d['filename']) . '</a> <small>' . e(fmt_date($d['created_at'])) . '</small></li>';
                    }
                    $html .= '</ul>';
                }
                if (!$viewOnly) {
                    $html .= '<input type="file" name="' . e($name) . '[]" id="' . $id . '" accept="image/*,.pdf"' . ($max > 1 ? ' multiple' : '') . ' data-max-files="' . $max . '"' . ($required && !$existing ? ' data-required="1"' : '') . '>';
                    $html .= '<p class="field-hint">Up to ' . $max . ' file' . ($max > 1 ? 's' : '') . ', images or PDF, max 10 MB each.</p>';
                }
                return $html . '</div>';
            case 'text_array':
                $items = is_array($value) ? $value : ($value ? [$value] : []);
                $html = $wrap . $lbl . $info . '<div data-text-array>';
                foreach (array_merge($items, ['']) as $item) {
                    $html .= '<input type="text" name="' . e($name) . '[]" value="' . e($item) . '"' . ($disabled ? ' disabled' : '') . '>';
                }
                return $html . '</div></div>';
            case 'email':
                return $wrap . $lbl . $info . '<input type="email"' . $common . $ph . ' value="' . e($value) . '"></div>';
            case 'tel':
                return $wrap . $lbl . $info . '<input type="tel"' . $common . $ph . ' value="' . e($value) . '" data-phone></div>';
            default:
                $extra = isset($f['maxlength']) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '';
                $extra .= in_array($name, ['phone', 'mobile'], true) ? ' data-phone' : '';
                return $wrap . $lbl . $info . '<input type="text"' . $common . $ph . $extra . ' value="' . e($value) . '"></div>';
        }
    }

    private static function fmt(mixed $value, string $format): string
    {
        if (!$value || !is_string($value)) {
            return '';
        }
        $t = strtotime($value);
        return $t ? date($format, $t) : $value;
    }

    /** Resolve static or dynamic options. */
    public static function options(array $f, array $opts = []): array
    {
        $out = [];
        foreach ($f['options'] ?? [] as $o) {
            if (!is_array($o)) {
                $o = ['label' => $o, 'value' => $o];
            }
            $out[] = ['label' => (string) ($o['label'] ?? $o['value'] ?? ''), 'value' => (string) ($o['value'] ?? $o['label'] ?? '')];
        }
        $dynamic = $f['dynamic'] ?? null;
        if ($dynamic) {
            $key = is_array($dynamic) ? ($dynamic['source'] ?? '') : (string) $dynamic;
            $out = array_merge($out, self::dynamicOptions($key, $opts));
        }
        return $out;
    }

    public static function dynamicOptions(string $source, array $opts = []): array
    {
        switch ($source) {
            case 'teams':
                return array_map(fn($t) => ['label' => $t['name'], 'value' => $t['id']], Team::all(null, 'approved'));
            case 'parents':
                return array_map(fn($p) => ['label' => start_case($p), 'value' => $p], Team::parents());
            case 'newmuslims':
                return array_map(fn($u) => ['label' => User::fullName($u) . ' (' . $u['email'] . ')', 'value' => $u['id']], User::list(['type' => 'newmuslim', 'not_status' => ['removed']], false));
            case 'mentors':
                return array_map(fn($u) => ['label' => User::fullName($u) . ' (' . $u['email'] . ')', 'value' => $u['id']], User::list(['type' => 'volunteer', 'sub_group' => 'mentor', 'status' => ['approved']], false));
            case 'volunteers':
                return array_map(fn($u) => ['label' => User::fullName($u) . ' (' . $u['email'] . ')', 'value' => $u['id']], User::list(['type' => 'volunteer', 'status' => ['approved']], false));
        }
        return [];
    }

    /**
     * Read submitted values for the template fields and validate them.
     * Returns [values, errors, files]. Files: name => list of $_FILES entries.
     */
    public static function collect(array $fields, Request $req, array $opts = []): array
    {
        $requiredOnly = !empty($opts['requiredOnly']);
        $bypassRequired = !empty($opts['adminBypass']);
        $values = [];
        $errors = [];
        $files = [];
        // First pass: raw values (needed for conditions)
        foreach ($fields as $f) {
            $name = (string) ($f['name'] ?? '');
            $type = $f['type'] ?? 'text';
            if ($name === '' || in_array($type, ['section', 'text_copy', 'typography'], true)) {
                continue;
            }
            if (!empty($f['hidden']) && empty($opts['showHidden'])) {
                continue;
            }
            if ($requiredOnly && empty($f['required'])) {
                continue;
            }
            if ($type === 'upload') {
                $files[$name] = $req->files($name);
                continue;
            }
            $raw = $req->input($name);
            $values[$name] = self::coerce($f, $raw);
        }
        // Second pass: validation with conditions
        foreach ($fields as $f) {
            $name = (string) ($f['name'] ?? '');
            $type = $f['type'] ?? 'text';
            if ($name === '' || in_array($type, ['section', 'text_copy', 'typography'], true)) {
                continue;
            }
            if (!array_key_exists($name, $values) && !array_key_exists($name, $files)) {
                continue;
            }
            if (!self::conditionMet($f, $values)) {
                // hidden by condition: drop value so stale data is not stored
                if ($type === 'upload') {
                    $files[$name] = [];
                } else {
                    $values[$name] = is_array($values[$name] ?? null) ? [] : null;
                }
                continue;
            }
            $label = (string) ($f['label'] ?? $name);
            if ($type === 'upload') {
                $hasExisting = !empty($opts['existingFiles'][$name]);
                if (!empty($f['required']) && !$bypassRequired && !$files[$name] && !$hasExisting) {
                    $errors[$name] = $f['error'] ?? "$label is required.";
                }
                if (count($files[$name]) > (int) ($f['max'] ?? 1)) {
                    $errors[$name] = "$label: at most " . (int) ($f['max'] ?? 1) . ' file(s).';
                }
                continue;
            }
            $v = $values[$name];
            $empty = $v === null || $v === '' || $v === [] || $v === false;
            if (!empty($f['required']) && !$bypassRequired && $empty) {
                $errors[$name] = "$label is required.";
                continue;
            }
            if ($empty) {
                continue;
            }
            if ($type === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = "$label must be a valid email address.";
            } elseif ($type === 'number') {
                if (!is_numeric($v)) {
                    $errors[$name] = "$label must be a number.";
                } elseif (isset($f['min']) && (float) $v < (float) $f['min']) {
                    $errors[$name] = "$label must be at least {$f['min']}.";
                } elseif (isset($f['max']) && (float) $v > (float) $f['max']) {
                    $errors[$name] = "$label must be at most {$f['max']}.";
                }
            } elseif (in_array($type, ['date', 'datetime'], true) && strtotime((string) $v) === false) {
                $errors[$name] = "$label must be a valid date.";
            } elseif ($type === 'select' && !empty($f['options']) && empty($f['dynamic'])) {
                $allowed = array_column(self::options($f), 'value');
                foreach ((array) $v as $item) {
                    if (!in_array((string) $item, $allowed, true)) {
                        $errors[$name] = "$label has an invalid value.";
                        break;
                    }
                }
            } elseif ($type === 'tel' || in_array($name, ['phone', 'mobile'], true)) {
                $digits = preg_replace('/\D+/', '', (string) $v) ?? '';
                if (strlen($digits) < 10 || strlen($digits) > 11) {
                    $errors[$name] = "$label must be a valid phone number.";
                } else {
                    $values[$name] = \App\Core\Str::formatPhone((string) $v);
                }
            } elseif ($name === 'postal_code') {
                if (!preg_match('/^[A-Za-z]\d[A-Za-z]\s?\d[A-Za-z]\d$/', (string) $v)) {
                    $errors[$name] = "$label must be a valid Canadian postal code.";
                } else {
                    $values[$name] = Geo::normalizePostal((string) $v);
                }
            } elseif (is_string($v) && mb_strlen($v) > 5000) {
                $errors[$name] = "$label is too long.";
            }
        }
        return [$values, $errors, $files];
    }

    private static function coerce(array $f, mixed $raw): mixed
    {
        $type = $f['type'] ?? 'text';
        switch ($type) {
            case 'checkbox':
                return $raw ? 1 : 0;
            case 'select':
                if (!empty($f['multiple'])) {
                    return array_values(array_filter(array_map('trim', (array) $raw), fn($x) => $x !== ''));
                }
                return is_array($raw) ? (string) reset($raw) : trim((string) $raw);
            case 'text_array':
                return array_values(array_filter(array_map('trim', (array) $raw), fn($x) => $x !== ''));
            case 'number':
                $s = trim((string) (is_array($raw) ? '' : $raw));
                return $s === '' ? null : (str_contains($s, '.') ? (float) $s : (int) $s);
            case 'datetime':
                $s = trim((string) (is_array($raw) ? '' : $raw));
                return $s === '' ? null : date('Y-m-d H:i:s', strtotime($s) ?: time());
            default:
                return is_array($raw) ? implode(', ', $raw) : trim((string) $raw);
        }
    }

    public static function conditionMet(array $f, array $values): bool
    {
        $c = $f['condition'] ?? null;
        if (!$c || empty($c['name'])) {
            return true;
        }
        $actual = $values[$c['name']] ?? null;
        $expected = $c['value'] ?? '';
        $matches = is_array($actual) ? in_array((string) $expected, array_map('strval', $actual), true) : ((string) $actual === (string) $expected);
        if (!empty($c['not'])) {
            return !$matches;
        }
        return $matches;
    }

    /** Human readable label for a value (used in read-only views). */
    public static function displayValue(array $f, mixed $v): string
    {
        if ($v === null || $v === '' || $v === []) {
            return '';
        }
        if (($f['type'] ?? '') === 'checkbox') {
            return $v ? 'Yes' : 'No';
        }
        if (!empty($f['options'])) {
            $map = [];
            foreach (self::options($f) as $o) {
                $map[$o['value']] = $o['label'];
            }
            return implode(', ', array_map(fn($x) => $map[(string) $x] ?? (string) $x, (array) $v));
        }
        if (in_array($f['type'] ?? '', ['datetime'], true)) {
            return fmt_datetime((string) $v);
        }
        if (($f['type'] ?? '') === 'date') {
            return fmt_date((string) $v);
        }
        return is_array($v) ? implode(', ', $v) : (string) $v;
    }
}
