<?php

namespace App\Http\Controllers\Volunteer;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Services\Volunteer\Org\CapacityReport;
use App\Services\Volunteer\Org\DepartmentScope;
use App\Services\Volunteer\Org\MemberDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * السعة والأحمال (13.4-ف · 24.4-7) — **مؤشّرات لا موانع**.
 *
 * الشاشة **تقرأ فقط**: لا فورم ضبط ولا زرّ يمنع تسكينًا أو ترقيةً أو نقلًا.
 * والأرقام مصدرها `positions` و`entities` — وضبطها في الإدارة المركزيّة (13.4-ك).
 */
class CapacityController extends Controller
{
    public function __construct(
        private readonly DepartmentScope $scope,
        private readonly CapacityReport $report,
        private readonly MemberDirectory $directory,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $root = $this->scope->rootFor($user, (int) $request->query('entity') ?: null);
        $tab = in_array($request->query('tab'), ['occupancy', 'gaps', 'loads'], true)
            ? (string) $request->query('tab')
            : 'span';

        if (! $root) {
            return view('volunteer.org.capacity', [
                'root' => null, 'roots' => collect(), 'tab' => $tab,
                'banner' => $this->report->banner(), 'difference' => $this->report->differenceLine(),
                'spans' => collect(), 'occupancy' => collect(), 'unhealthy' => collect(),
                'vacancies' => collect(), 'loads' => collect(),
            ]);
        }

        $memberships = $this->scope->memberships($this->scope->entityIds($root));

        return view('volunteer.org.capacity', [
            'root' => $root,
            'roots' => $this->scope->rootsFor($user),
            'tab' => $tab,
            'banner' => $this->report->banner(),
            'difference' => $this->report->differenceLine(),
            // كلّ تاب يُبنى عند فتحه فقط (2.7 · 2.15)
            'spans' => $tab === 'span' ? $this->report->spanTable($memberships) : collect(),
            'occupancy' => $tab === 'occupancy' ? $this->report->occupancy($root, $memberships) : collect(),
            'unhealthy' => $tab === 'gaps' ? $this->report->unhealthy($memberships) : collect(),
            'vacancies' => $tab === 'gaps' ? $this->directory->vacancies($root, $memberships) : collect(),
            'loads' => $tab === 'loads' ? $this->report->loads($memberships) : collect(),
        ]);
    }

    /** بوب-أب الكيان: أعضاؤه ونسبة إشغاله وتجاوزاته — **تنبيهًا فقط بلا منع** */
    public function entity(Request $request, Entity $entity): JsonResponse
    {
        $user = $request->user();
        $root = $this->scope->rootFor($user);

        abort_unless($root && in_array($entity->id, $this->scope->entityIds($root), true), 403);

        $memberships = $this->scope->memberships($this->scope->entityIds($root));

        return response()->json($this->report->entityDetail($entity, $memberships));
    }
}
