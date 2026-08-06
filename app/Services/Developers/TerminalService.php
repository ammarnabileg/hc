<?php

namespace App\Services\Developers;

use App\Models\TerminalCommandLog;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * تنفيذ أمر Shell حقيقيّ على الخادم — تاب «الطرفيّة» (12.15-هـ) لمالك المنصّة
 * حصرًا.
 *
 * ⛔ **لا Whitelist ولا Blacklist ولا فلترة على محتوى الأمر** — أمر المالك
 * المباشر المسجَّل في الدستور: «اسمح بكلّ الأوامر». القيد الوحيد المسموح به
 * **زمنيّ لا محتوى**: مهلة تنفيذٍ قابلة للتعديل (`developers.terminal.timeout_seconds`)
 * تمنع أمرًا معلَّقًا من حجز العمليّة للأبد.
 *
 * ⛔ **كلّ أمرٍ مُسجَّلٌ في سجلّ التدقيق** (12.15-هـ) — صفٌّ في
 * `terminal_command_logs` (عرضٌ تشغيليّ في الشاشة نفسها) + قيدٌ في
 * `AuditLog` عبر `AuditTrail::log()` (الأثر الإداريّ الموحَّد، 2.13-و) — لا
 * استثناء صامت.
 */
class TerminalService
{
    public function run(string $command, User $actor, ?string $ip): array
    {
        $timeout = (int) setting('developers.terminal.timeout_seconds', 60);
        $start = microtime(true);

        $process = Process::fromShellCommandline($command, base_path());
        $process->setTimeout($timeout);

        try {
            $process->run();
            $output = $process->getOutput().$process->getErrorOutput();
            $exitCode = $process->getExitCode();
        } catch (ProcessTimedOutException) {
            $output = (string) setting('developers.terminal.timeout_msg', 'الأمر تجاوز المهلة المسموحة وأُوقِف.');
            $exitCode = 124; // نفس كود timeout الشائع في shell
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $maxLines = (int) setting('developers.terminal.max_output_lines', 500);
        $truncated = implode("\n", array_slice(explode("\n", $output), 0, $maxLines));

        $log = TerminalCommandLog::create([
            'user_id' => $actor->id,
            'command' => $command,
            'output' => $truncated,
            'exit_code' => $exitCode,
            'duration_ms' => $durationMs,
            'ip' => $ip,
        ]);

        AuditTrail::log($actor, 'terminal.command', $log, [], ['command' => $command, 'exit_code' => $exitCode]);

        return ['output' => $truncated, 'exit_code' => $exitCode, 'duration_ms' => $durationMs];
    }
}
