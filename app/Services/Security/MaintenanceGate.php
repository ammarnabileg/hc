<?php

namespace App\Services\Security;

use App\Models\MaintenanceWindow;
use App\Services\Admin\System\MaintenanceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * مَن يمرّ والموقع مقفول، وماذا يرى مَن لا يمرّ (12.7-و-1).
 *
 * المستثنَون (بهذا الترتيب): **مالك المنصّة** ومَن له `maintenance.manage` —
 * فالأدمن يكمل شغله ويختبر · مسارا **الدخول والخروج** حتى يقدر الأدمن يدخل أصلًا ·
 * **الويب هوك** فالدفع والتكاملات لا تنتظر · و**`system.maintenance.exempt_ips`**.
 */
class MaintenanceGate
{
    public function __construct(
        private readonly MaintenanceService $maintenance,
        private readonly MaintenanceAlert $alert,
    ) {}

    public function allows(Request $request): bool
    {
        if ($this->pathIsExempt($request)) {
            return true;
        }

        if ($this->ipIsExempt($request)) {
            return true;
        }

        $user = $request->user();

        if (! $user) {
            return false;
        }

        // مالك المنصّة يعلو الجميع، ومَن يدير الصيانة لازم يقدر يرفعها من جوّه
        return $user->isPlatformOwner() || $user->allows('maintenance.manage');
    }

    /** صفحة الصيانة نفسها: 503 مع Retry-After حتى لا تُفهرَس ولا تُخزَّن */
    public function wall(Request $request): Response
    {
        $window = $this->maintenance->current();
        $state = $this->maintenance->publicState();

        if ($state['overrun'] && $window instanceof MaintenanceWindow) {
            // ⭐ صمّام أمان ضدّ موقع مقفول ومنسيّ: تنبيه للأدمن بالتمديد أو الرفع
            $this->alert->overrun($window);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'maintenance' => true,
                'message' => $state['overrun'] ? $this->maintenance->overrunMessage() : $state['message'],
            ], 503);
        }

        return response()->view('maintenance.index', [
            'state' => $state,
            'overrunMessage' => $this->maintenance->overrunMessage(),
        ], 503)->header('Retry-After', (string) max(60, $state['seconds_left'] ?: 300));
    }

    /**
     * المسارات المستثناة — قائمة إعدادات لا قيم محروقة، وأنماط `*` مسموحة.
     *
     * @return array<int,string>
     */
    public function exemptPaths(): array
    {
        $configured = setting('system.maintenance.exempt_paths');

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map('strval', $configured)));
        }

        return ['login', 'logout', 'webhooks/*', 'up', 'maintenance/state'];
    }

    private function pathIsExempt(Request $request): bool
    {
        return $request->is(...$this->exemptPaths());
    }

    /**
     * ⭐ `system.maintenance.exempt_ips` — سطر أو فاصلة لكلّ IP.
     * ونقبل كذلك القائمة القديمة `admin_ips` عبر الخدمة فلا يضيع ضبطٌ سابق.
     */
    private function ipIsExempt(Request $request): bool
    {
        $ip = $request->ip();

        $list = array_filter(array_map(
            'trim',
            preg_split('/[\s,]+/', (string) setting('system.maintenance.exempt_ips', '')) ?: [],
        ));

        if ($ip !== null && in_array($ip, $list, true)) {
            return true;
        }

        return $this->maintenance->ipAllowed($ip);
    }
}
