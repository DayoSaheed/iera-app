<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * Render a template inside a layout ('app', 'auth', 'blank' or null for none).
     * Variables a template defines at top level ($title, $active, $breadcrumbs, $actions, $scripts)
     * are passed on to the layout.
     */
    public static function render(string $template, array $data = [], ?string $layout = 'app'): string
    {
        [$content, $vars] = self::renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }
        $layoutData = $vars + $data;
        $layoutData['content'] = $content;
        [$html] = self::renderFile('layouts/' . $layout, $layoutData);
        return $html;
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::renderFile($template, $data)[0];
    }

    /** @return array{0:string,1:array} rendered html and the template's top-level variables */
    private static function renderFile(string $__template, array $__data): array
    {
        $__file = APP_PATH . '/Views/' . $__template . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException("View not found: $__template");
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $__html = (string) ob_get_clean();
        $__vars = get_defined_vars();
        unset($__vars['__template'], $__vars['__data'], $__vars['__file'], $__vars['__html'], $__vars['content']);
        return [$__html, $__vars];
    }
}
