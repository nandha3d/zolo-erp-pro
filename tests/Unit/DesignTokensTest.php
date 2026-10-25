<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Brand colours live in public/css/zolo-tokens.css. The old indigo/violet accents
 * (each page had its own) must not come back in app CSS or in blade styles.
 */
class DesignTokensTest extends TestCase
{
    private const OLD_ACCENTS = '/#(4f46e5|6366f1|7c3aed|7c5cc4|8b5cf6|4338ca|6d28d9|eef2ff|f5f3ff|c7d2fe)\b|rgba?\(\s*(99,\s*102,\s*241|79,\s*70,\s*229|124,\s*58,\s*237)\b/i';

    // The token file itself, third-party, login (own art direction) and compiled vendor-style sheets.
    private const SKIP_CSS = ['zolo-tokens.css', 'app.css', 'auth.css', 'dropzone.css', 'custom-rtl.css', 'style.css', 'grasp_mobile_progress_circle-1.0.0.min.css'];

    // Emails and PDF/print templates cannot use CSS variables.
    private const SKIP_VIEWS = '#/(mail|emails?)/|invoice|print|receipt|reciept|pdf|label|barcode|/auth/#i';

    public function test_app_stylesheets_use_brand_tokens_not_old_accents(): void
    {
        $offenders = [];
        foreach (glob(dirname(__DIR__, 2) . '/public/css/*.css') as $file) {
            if (in_array(basename($file), self::SKIP_CSS, true)) {
                continue;
            }
            foreach (file($file) as $i => $line) {
                if (preg_match(self::OLD_ACCENTS, $line) && ! str_contains($line, '--ind')) {
                    $offenders[] = basename($file) . ':' . ($i + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Use var(--z-primary*) from zolo-tokens.css instead of hard-coded accents.');
    }

    public function test_blade_styles_use_brand_tokens_not_old_accents(): void
    {
        $root = dirname(__DIR__, 2) . '/resources/views';
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (! str_ends_with($path, '.blade.php') || preg_match(self::SKIP_VIEWS, $path)) {
                continue;
            }
            $html = file_get_contents($path);
            preg_match_all('#<style\b[^>]*>(.*?)</style>|\sstyle="([^"]*)"#is', $html, $styles);
            foreach (array_merge($styles[1], $styles[2]) as $css) {
                if (preg_match(self::OLD_ACCENTS, $css)) {
                    $offenders[] = substr($path, strlen($root) + 1);
                    break;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)), 'Use var(--z-primary*, fallback) in blade styles.');
    }

    public function test_blades_style_with_classes_not_inline_styles(): void
    {
        $offenders = [];
        foreach ($this->appViews() as $rel => $html) {
            // Allowed inline: display:none (JS show/hide state) and values computed by Blade (data, e.g. a bar width).
            preg_match_all('#\sstyle="([^"]*)"#i', $html, $m);
            foreach ($m[1] as $style) {
                if (preg_match('/^\s*display\s*:\s*none\s*;?\s*$/i', $style) || str_contains($style, '{{')) {
                    continue;
                }
                $offenders[] = "$rel: style=\"$style\"";
            }
            // Page CSS lives in public/css (pages/*.css), not in <style> blocks. Exceptions: colours from the database.
            if (preg_match('#<style\b#i', $html) && ! in_array($rel, self::DYNAMIC_STYLE_VIEWS, true)) {
                $offenders[] = "$rel: <style> block";
            }
        }

        $this->assertSame([], $offenders, 'Use component classes (zolo-components.css) or utilities (zolo-utilities.css); page CSS goes in public/css/pages.');
    }

    public function test_layout_views_load_page_css_in_the_head(): void
    {
        $offenders = [];
        foreach ($this->appViews() as $rel => $html) {
            if (! preg_match("/@extends\(\s*'backend\.layout\.(main|top-head)'/", $html)) {
                continue;
            }
            $body = preg_replace('#@push\(.*?@endpush|<script\b.*?</script>#s', '', $html);
            if (preg_match('#<link\b[^>]*stylesheet#i', $body)) {
                $offenders[] = $rel;
            }
        }

        $this->assertSame([], $offenders, "Put page stylesheets in @push('css') so they load in <head>.");
    }

    // Public QR menu takes its theme colours from the company's settings.
    private const DYNAMIC_STYLE_VIEWS = ['backend/qr/public_menu.blade.php'];

    /** @return array<string, string> relative path => contents, app views only */
    private function appViews(): array
    {
        $root = dirname(__DIR__, 2) . '/resources/views';
        $views = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (! str_ends_with($path, '.blade.php') || preg_match(self::SKIP_VIEWS, $path) || str_contains($path, '/vendor/')) {
                continue;
            }
            $views[substr($path, strlen($root) + 1)] = file_get_contents($path);
        }

        return $views;
    }
}
