<?php

namespace App\Services\Referral;

use App\Models\Master;
use App\Models\Referral;
use App\Models\ReferralEarning;
use Illuminate\Database\Eloquent\Collection;

class ReferralService
{
    /**
     * Закрепление реферала за мастером
     */
    public function registerReferral(Master $referred, string $code): ?Referral
    {
        // 1. Ищем владельца кода
        $referrer = Master::where('referral_code', $code)->first();

        // 2. Если код не существует или мастер пытается вписать СВОЙ код — отказ
        if (!$referrer || $referrer->id === $referred->id) {
            return null;
        }

        // 3. Проверяем, не привязан ли ТЕКУЩИЙ мастер уже к кому-либо
        $existingReferral = Referral::where('referred_master_id', $referred->id)->first();

        if ($existingReferral) {
            // Мастер уже за кем-то закреплен!
            // Возвращаем существующую запись (или null / выбрасываем исключение, зависит от желаемого ответа API)
            return $existingReferral;
        }

        // 4. Создаем чистую новую привязку к владельцу кода
        return Referral::create([
            'referrer_master_id' => $referrer->id,
            'referred_master_id' => $referred->id,
            'program'            => Referral::PROGRAM_MASTER_INVITE ?? 'master_invite',
            'status'             => Referral::STATUS_PENDING ?? 'pending',
        ]);
    }

    public function rewardAmount(int $paymentAmount): int
    {
        $percent = (int) config('referral.percent');

        return (int) round(($paymentAmount / 100) * $percent);
    }

    /**
     * Список приведённых мастеров текущего реферера
     */
    public function getMyReferrals(Master $master): Collection
    {
        return Referral::query()
            ->where('referrer_master_id', $master->id)
            ->with(['referredMaster', 'referralEarning']) // Загружаем связанные модели
            ->get();
    }

    /**
     * Финансовая сводка по вознаграждениям
     */
    public function getEarningsSummary(Master $master): array
    {
        $earnings = ReferralEarning::query()
            ->where('referrer_master_id', $master->id)
            ->get();

        $qualifyingCount = Referral::query()
            ->where('referrer_master_id', $master->id)
            ->where('status', Referral::STATUS_REWARDED ?? 'rewarded')
            ->count();

        $total = $earnings->sum('amount');
        $pending = $earnings->where('status', ReferralEarning::STATUS_PENDING)->sum('amount');
        $paid = $earnings->where('status', ReferralEarning::STATUS_PAID)->sum('amount');

        return [
            'total_earned'     => (int) $total,
            'pending'          => (int) $pending,
            'paid'             => (int) $paid,
            'qualifying_count' => $qualifyingCount,
        ];
    }
}
