<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveCurrentMaster;
use App\Models\Master;
use App\Models\Referral;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralService $referralService
    ) {}

    /**
     * POST /api/referrals/attach
     */
    public function attach(Request $request): JsonResponse
    {
        /** @var Master|null $currentMaster */
        $currentMaster = $request->attributes->get('current_master');

        if (!$currentMaster) {
            return response()->json(['error' => 'Master not found or X-Master-Id header missing'], 401);
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        // Находим реферера по коду заранее для проверки
        $targetReferrer = Master::where('referral_code', $validated['code'])->first();

        if (!$targetReferrer) {
            return response()->json(['message' => 'Referral code not found'], 404);
        }

        if ($targetReferrer->id === $currentMaster->id) {
            return response()->json(['message' => 'You cannot use your own referral code'], 400);
        }

        // Проверяем, привязан ли уже мастер
        $existingReferral = Referral::where('referred_master_id', $currentMaster->id)->first();

        if ($existingReferral) {
            // Повторный вызов — возвращаем 200 OK с имеющейся привязкой без создания новой
            return response()->json([
                'success' => true,
                'message' => 'Already attached',
                'referral' => $existingReferral,
            ], 200);
        }

        // Создаем новую привязку
        $referral = $this->referralService->registerReferral($currentMaster, $validated['code']);

        return response()->json([
            'success'  => true,
            'referral' => $referral,
        ], 201);
    }

    /**
     * GET /api/referrals/my
     */
    public function my(Request $request): JsonResponse
    {
        /** @var Master|null $currentMaster */
        $currentMaster = $request->attributes->get('current_master');

        if (!$currentMaster) {
            return response()->json(['error' => 'Master not found or header missing'], 401);
        }

        $referrals = $this->referralService->getMyReferrals($currentMaster);

        $data = $referrals->map(function ($ref) {
            // Реферал засчитан, если статус rewarded (или есть начисление)
            $isQualified = $ref->status === ($ref::STATUS_REWARDED ?? 'rewarded');

            // Начисленная сумма из записи в referral_earnings
            $earnedAmount = $ref->earning ? $ref->earning->amount : 0;

            return [
                'id'           => $ref->id,
                'name'         => $ref->referredMaster?->name ?? 'Неизвестно',
                'created_at'   => $ref->created_at->toIso8601String(),
                'is_qualified' => $isQualified,
                'earned_amount'=> $earnedAmount,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/referrals/earnings
     */
    public function earnings(Request $request): JsonResponse
    {
        /** @var Master|null $currentMaster */
        $currentMaster = $request->attributes->get('current_master');

        if (!$currentMaster) {
            return response()->json(['error' => 'Master not found or header missing'], 401);
        }

        $summary = $this->referralService->getEarningsSummary($currentMaster);

        return response()->json($summary);
    }
}