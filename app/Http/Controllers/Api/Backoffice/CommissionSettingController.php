<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use App\Services\CommissionService;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Exception;

class CommissionSettingController extends Controller
{
    protected CommissionService $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * Lister les paramètres de commission
     */
    public function list(Request $request): JsonResponse
    {
        try {
            // $user = $request->user();
            // if (!in_array($user->type, [UserType::ADMIN])) {
            //     return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            // }

            $settings = CommissionSetting::orderBy('key')->get();

            return response()->json([
                'success' => true,
                'settings' => $settings
            ]);
        } catch (Exception $e) {
            Log::error('Erreur listing commissions : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Ajouter un paramètre de commission
     */
    public function add(Request $request): JsonResponse
    {
        try {
            // $user = $request->user();
            // if (!in_array($user->type, [UserType::ADMIN])) {
            //     return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            // }

            $request->validate([
                'key' => ['required', 'string', 'unique:commission_settings,key'],
                'value' => ['required', 'numeric'],
                'type' => ['required', 'in:pourcentage,fixe'],
                'description' => ['nullable', 'string'],
            ]);

            $setting = CommissionSetting::create([
                'key' => $request->key,
                'value' => $request->value,
                'type' => $request->type,
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paramètre de commission créé.',
                'setting' => $setting
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur création commission : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Modifier un paramètre de commission
     */
    public function edit(Request $request, string $id): JsonResponse
    {
        try {
            // $user = $request->user();
            // if (!in_array($user->type, [UserType::ADMIN])) {
            //     return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            // }

            $setting = CommissionSetting::find($id);
            if (!$setting) {
                return response()->json(['success' => false, 'message' => 'Paramètre non trouvé.'], 404);
            }

            $request->validate([
                'value' => ['sometimes', 'numeric'],
                'type' => ['sometimes', 'in:pourcentage,fixe'],
                'description' => ['nullable', 'string'],
            ]);

            $setting->update($request->only(['value', 'type', 'description']));

            // Vider le cache pour cette clé via le service
            $this->commissionService->clearCache($setting->key);

            return response()->json([
                'success' => true,
                'message' => 'Paramètre de commission mis à jour.',
                'setting' => $setting
            ]);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour commission : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Modifier un paramètre de commission
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        try {
            // $user = $request->user();
            // if (!in_array($user->type, [UserType::ADMIN])) {
            //     return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            // }

            $setting = CommissionSetting::find($id);
            if (!$setting) {
                return response()->json(['success' => false, 'message' => 'Paramètre non trouvé.'], 404);
            }

            $setting->is_active = !$setting->is_active;
            $setting->save();

            // Vider le cache pour cette clé via le service
            $this->commissionService->clearCache($setting->key);

            return response()->json(['success' => true, 'message' => $setting->is_active ? 'Commission activée.' : 'Commission désactivée.', 'setting' => $setting]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour commission : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
