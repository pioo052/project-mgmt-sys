<?php
declare(strict_types=1);

/**
 * Dependency-free, theme aware charts built from plain HTML/CSS.
 * Every chart includes a data table fallback for assistive technology.
 */

function chart_palette(): array
{
    return ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)', 'var(--chart-6)'];
}

function chart_bars(array $points, string $label, string $valuePrefix = ''): string
{
    if (!$points) {
        return chart_empty();
    }
    $max = max(array_map(static fn ($v): float => (float) $v, array_values($points)));
    $max = $max > 0 ? $max : 1;
    $html = '<div class="chart chart--bars" role="img" aria-label="' . e($label) . '">';
    $html .= '<div class="chart__plot">';
    foreach ($points as $pointLabel => $value) {
        $pct = round(((float) $value / $max) * 100, 1);
        $html .= '<div class="chart-bar" title="' . e((string) $pointLabel . ': ' . $valuePrefix . number_format((float) $value)) . '">'
            . '<span class="chart-bar__value">' . e($valuePrefix . number_format((float) $value)) . '</span>'
            . '<span class="chart-bar__track"><span class="chart-bar__fill" style="height:' . $pct . '%"></span></span>'
            . '<span class="chart-bar__label">' . e((string) $pointLabel) . '</span>'
            . '</div>';
    }
    $html .= '</div></div>';
    return $html . chart_data_table($points, $label, $valuePrefix);
}

function chart_hbars(array $points, string $label, string $valuePrefix = ''): string
{
    if (!$points) {
        return chart_empty();
    }
    $max = max(array_map(static fn ($v): float => (float) $v, array_values($points)));
    $max = $max > 0 ? $max : 1;
    $html = '<div class="chart chart--hbars" role="img" aria-label="' . e($label) . '">';
    foreach ($points as $pointLabel => $value) {
        $pct = round(((float) $value / $max) * 100, 1);
        $html .= '<div class="hbar">'
            . '<span class="hbar__label">' . e((string) $pointLabel) . '</span>'
            . '<span class="hbar__track"><span class="hbar__fill" style="width:' . $pct . '%"></span></span>'
            . '<span class="hbar__value">' . e($valuePrefix . number_format((float) $value)) . '</span>'
            . '</div>';
    }
    $html .= '</div>';
    return $html . chart_data_table($points, $label, $valuePrefix);
}

function chart_donut(array $points, string $label, string $centerLabel = 'Total', string $valuePrefix = ''): string
{
    if (!$points) {
        return chart_empty();
    }
    $total = array_sum(array_map(static fn ($v): float => (float) $v, array_values($points)));
    if ($total <= 0) {
        return chart_empty();
    }
    $palette = chart_palette();
    $stops = [];
    $cursor = 0.0;
    $i = 0;
    foreach ($points as $value) {
        $share = ((float) $value / $total) * 100;
        $stops[] = $palette[$i % count($palette)] . ' ' . round($cursor, 2) . '% ' . round($cursor + $share, 2) . '%';
        $cursor += $share;
        $i++;
    }
    $gradient = 'conic-gradient(' . implode(', ', $stops) . ')';

    $html = '<div class="chart chart--donut" role="img" aria-label="' . e($label) . '">';
    $html .= '<div class="donut" style="background:' . e($gradient) . '">'
        . '<div class="donut__hole"><span class="donut__value">' . e($valuePrefix . number_format((float) $total)) . '</span>'
        . '<span class="donut__label">' . e($centerLabel) . '</span></div></div>';
    $html .= '<ul class="legend">';
    $i = 0;
    foreach ($points as $pointLabel => $value) {
        $html .= '<li class="legend__item"><span class="legend__swatch" style="background:' . $palette[$i % count($palette)] . '" aria-hidden="true"></span>'
            . '<span class="legend__label">' . e(humanize((string) $pointLabel)) . '</span>'
            . '<span class="legend__value">' . e($valuePrefix . number_format((float) $value)) . '</span></li>';
        $i++;
    }
    $html .= '</ul></div>';
    return $html . chart_data_table($points, $label, $valuePrefix);
}

function chart_data_table(array $points, string $label, string $valuePrefix = ''): string
{
    $html = '<details class="chart-data"><summary>View ' . e(strtolower($label)) . ' data</summary><table class="table table--compact"><caption class="sr-only">' . e($label) . '</caption><thead><tr><th scope="col">Category</th><th scope="col" class="text-right">Value</th></tr></thead><tbody>';
    foreach ($points as $pointLabel => $value) {
        $html .= '<tr><td>' . e(humanize((string) $pointLabel)) . '</td><td class="text-right">' . e($valuePrefix . number_format((float) $value)) . '</td></tr>';
    }
    $html .= '</tbody></table></details>';
    return $html;
}

function chart_empty(string $message = 'No data recorded yet'): string
{
    return '<div class="chart-empty">' . icon('analytics', 22) . '<p>' . e($message) . '</p></div>';
}
