<?php

namespace Tests\Feature\Admin\Ops;

use Illuminate\Support\Facades\DB;

class DebugPipelineTest extends OpsTestCase
{
    public function test_debug(): void
    {
        $admin = $this->admin(['updates.view', 'updates.manage', 'backups.create', 'version_history.view', 'version_history.list']);
        $this->set('updates.migrations_path', 'tests/Feature/Admin/Ops/fixtures/migrations');

        $this->actingAs($admin)->post(route('admin.ops.updates.dry-run'));
        $r = $this->actingAs($admin)->post(route('admin.ops.updates.migrate'), ['confirm' => 'تنفيذ', 'understood' => '1']);

        fwrite(STDERR, "\nSTATUS: ".json_encode(session('status'), JSON_UNESCAPED_UNICODE)."\n");
        fwrite(STDERR, "RUNS: ".json_encode(DB::table('update_runs')->get()->all(), JSON_UNESCAPED_UNICODE)."\n");
        $this->assertTrue(true);
    }
}
