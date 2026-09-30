<?php

namespace Tests\Feature\Admin\Core;

use Tests\TestCase;

/**
 * الهويّة تعمل بالأبيض والأسود (الفكرة #5): سلسلتا «الحركة عبر الوقت» تختلفان
 * بالشكل لا باللون وحده: مسجّلون خطّ متّصل بدوائر، ومبيعات خطّ متقطّع بمربّعات،
 * والمفتاح يعرض عيّنة الخطّ نفسها.
 */
class DashboardChartShapesTest extends TestCase
{
    public function test_the_two_series_differ_by_dash_and_marker_shape(): void
    {
        $points = [
            ['label' => '1 سبتمبر', 'short' => '1', 'signups' => 3, 'sales' => 2],
            ['label' => '2 سبتمبر', 'short' => '2', 'signups' => 5, 'sales' => 1],
        ];

        $html = view('admin.dashboard.components.chart-lines', ['points' => $points])->render();

        $this->assertMatchesRegularExpression('/data-chart-series="sales"\s+stroke-dasharray="6 4"/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-chart-series="signups"\s+stroke-dasharray/', $html);
        $this->assertSame(2, substr_count($html, 'width="5" height="5"'), 'مربّعات المبيعات');
        $this->assertSame(2, substr_count($html, 'r="2.5"'), 'دوائر المسجّلين');
        $this->assertStringContainsString('data-chart-legend="sales"', $html);
        $this->assertMatchesRegularExpression('/data-chart-legend="sales".*?stroke-dasharray="6 4"/s', $html);
    }

    public function test_without_sales_only_the_solid_series_is_drawn(): void
    {
        $html = view('admin.dashboard.components.chart-lines', ['points' => [['label' => 'أ', 'short' => 'أ', 'signups' => 2]]])->render();

        $this->assertStringNotContainsString('data-chart-series="sales"', $html);
        $this->assertStringNotContainsString('stroke-dasharray', $html);
    }
}
