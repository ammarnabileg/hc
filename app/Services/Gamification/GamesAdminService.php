<?php

namespace App\Services\Gamification;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\Setting;
use App\Models\User;
use App\Services\Admin\Volunteer\AuditTrail;
use Illuminate\Support\Facades\Cache;

/**
 * تاب «الألعاب» في لوحة التلعيب (24.2 · 7.5).
 *
 * كتالوج إعدادات مستقلّ للمجال — لأنّ زرّ **Reset للافتراضيّ** يحتاج مرجعًا
 * واحدًا لكلّ مفتاح، ولا يصحّ أن تتفرّق الافتراضيّات بين السيدر والكود (2.13).
 *
 * الصيغة: key => [group, label_ar, type, default]
 */
class GamesAdminService
{
    public const STATUSES = ['active' => 'مفعّلة', 'soon' => 'قريبًا', 'paused' => 'موقوفة'];

    /** @return array<string, array{0:string,1:string,2:string,3:string}> */
    public static function catalog(): array
    {
        return [
            'games.enabled' => ['gamification_games', 'تفعيل قسم الألعاب كاملًا', 'bool', '1'],
            // التذكرة تُخصَم بمجرّد الدخول — ولحظة الخصم قابلة للضبط (24.2)
            'games.charge_moment' => ['gamification_games', 'لحظة خصم التذكرة', 'string', 'on_enter'],
            'games.ticket_cost' => ['gamification_games', 'تكلفة الدخول الافتراضيّة (تذاكر)', 'number', '1'],
            'games.daily_sessions_cap' => ['gamification_games', 'حدّ يوميّ عامّ للجلسات', 'number', '10'],
            'games.daily_xp_cap' => ['gamification_games', 'سقف XP اليوميّ من الألعاب', 'number', '300'],
            'games.celebration_enabled' => ['gamification_games', 'الاحتفال والصوت عند النتيجة', 'bool', '1'],
            'games.result_text' => ['gamification_games', 'نصّ شاشة النتيجة', 'string', 'خلّصت اللعبة — نتيجتك :score ومكسبك :xp نقطة.'],
            'games.soon_text' => ['gamification_games', 'نصّ «قريبًا» الافتراضيّ', 'string', 'اللعبة دي في الطريق — استنّانا قريب.'],
        ];
    }

    /** صفوف التاب بقيمها وحالة «معدَّل» */
    public static function rows(): array
    {
        $stored = Setting::query()->whereIn('key', array_keys(self::catalog()))->pluck('value', 'key');
        $rows = [];

        foreach (self::catalog() as $key => [$group, $label, $type, $default]) {
            $rows[$key] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'default' => $default,
                'value' => $stored[$key] ?? $default,
                'modified' => isset($stored[$key]) && (string) $stored[$key] !== (string) $default,
            ];
        }

        return $rows;
    }

    public static function saveSettings(array $values, ?User $actor = null): int
    {
        $catalog = self::catalog();
        $count = 0;

        foreach ($values as $key => $value) {
            if (! isset($catalog[$key])) {
                continue; // لا نكتب مفتاحًا خارج الكتالوج منعًا لتلويث الجدول
            }

            [$group, $label, $type, $default] = $catalog[$key];

            $setting = Setting::firstOrCreate(['key' => $key], [
                'group' => $group, 'label_ar' => $label, 'type' => $type,
                'default_value' => $default, 'value' => $default,
            ]);

            $setting->forceFill(['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value])->save();
            $count++;
        }

        Cache::forget('settings');
        AuditTrail::log($actor, 'games.settings.update', null, [], ['keys' => array_keys($values)]);

        return $count;
    }

    public static function resetSettings(?User $actor = null): int
    {
        $defaults = [];

        foreach (self::catalog() as $key => [, , , $default]) {
            $defaults[$key] = $default;
        }

        return self::saveSettings($defaults, $actor);
    }

    public static function saveGame(?Game $game, array $data, ?User $actor = null): Game
    {
        $game ??= new Game;
        $old = $game->exists ? $game->only(['name_ar', 'ticket_cost', 'status']) : [];

        $game->fill([
            'key' => $data['key'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'] ?? null,
            'description' => $data['description'] ?? null,
            // أيقونة SVG مرسومة — ممنوع أيّ مكتبة أيقونات (2.16-ج)
            'icon_svg' => $data['icon_svg'] ?? null,
            'ticket_cost' => (float) ($data['ticket_cost'] ?? setting('games.ticket_cost', 1)),
            'xp_mode' => in_array($data['xp_mode'] ?? '', ['fixed', 'by_score'], true) ? $data['xp_mode'] : 'fixed',
            'xp_reward' => (int) ($data['xp_reward'] ?? 0),
            'daily_limit' => ($data['daily_limit'] ?? null) !== '' ? $data['daily_limit'] ?? null : null,
            'status' => array_key_exists($data['status'] ?? '', self::STATUSES) ? $data['status'] : 'active',
            'soon_text' => $data['soon_text'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ])->save();

        AuditTrail::log($actor, 'games.save', $game, $old, $game->only(['name_ar', 'ticket_cost', 'status']));

        return $game;
    }

    /** عكس جلسة بسبب — تدقيقٌ لا حذف (24.2) */
    public static function reverseSession(GameSession $session, string $reason, ?User $actor = null): void
    {
        $session->forceFill(['status' => 'reversed', 'reversed_reason' => $reason])->save();

        AuditTrail::log($actor, 'games.session.reverse', $session, [], ['reason' => $reason]);
    }
}
