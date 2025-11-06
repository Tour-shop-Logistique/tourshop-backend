<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\TarifPersonnalise;
use App\Models\TarifSimple;
use App\Enums\UserType;
use Exception;

class TarifPersonnaliseController extends Controller
{
    /**
     * Lister les tarifs personnalisés
     */
    public function listTarifCustom(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $query = TarifPersonnalise::query();

            // Filtrer par backoffice si nécessaire
            if ($user->type === UserType::BACKOFFICE) {
                $query->whereHas('tarifSimple', function ($q) use ($user) {
                    $q->where('backoffice_id', $user->backoffice_id);
                });
            }

            $tarifs = $query->orderBy('created_at', 'desc')->get();

            return response()->json(['success' => true, 'tarifs' => $tarifs]);
        } catch (Exception $e) {
            Log::error('Erreur listing tarifs personnalisés : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Créer un tarif personnalisé
     */
    public function addTarifCustom(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }


            $request->validate([
                'tarif_simple_id' => ['required', 'uuid', 'exists:tarifs_simple,id'],
                'prix_zones' => ['required', 'array', 'min:1'],
                'prix_zones.*.zone_destination_id' => ['required', 'string', 'exists:zones,id'],
                'prix_zones.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            // Vérifier que le tarif simple existe et appartient au backoffice
            $tarifSimple = TarifSimple::find($request->tarif_simple_id);
            if (!$tarifSimple) {
                return response()->json(['success' => false, 'message' => 'Tarif de base introuvable ou inactif.'], 404);
            }

            if ($user->type === UserType::BACKOFFICE && $tarifSimple->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif de base ne vous appartient pas.'], 403);
            }

            // Récupérer les montants de base depuis le tarif simple et calculer les montants personnalisés
            $prixZones = [];
            $tarifSimplePrixZones = collect($tarifSimple->prix_zones);

            foreach ($request->prix_zones as $zoneRequest) {
                $zoneId = $zoneRequest['zone_destination_id'];
                $pourcentage = $zoneRequest['pourcentage_prestation'];

                // Trouver le montant de base dans le tarif simple
                $zoneSimple = $tarifSimplePrixZones->firstWhere('zone_destination_id', $zoneId);
                if (!$zoneSimple) {
                    return response()->json([
                        'success' => false,
                        'message' => "Zone $zoneId introuvable dans le tarif de base."
                    ], 422);
                }

                $montantBase = $zoneSimple['montant_base'];

                $prixZones[] = [
                    'zone_destination_id' => $zoneId,
                    'montant_base' => $montantBase,
                    'pourcentage_prestation' => $pourcentage,
                    'montant_prestation' => round(($montantBase * $pourcentage) / 100, 2, PHP_ROUND_HALF_UP),
                    'montant_expedition' => round($montantBase + ($montantBase * $pourcentage) / 100, 2, PHP_ROUND_HALF_UP),
                ];
            }

            $tarif = TarifPersonnalise::create([
                'tarif_simple_id' => $request->tarif_simple_id,
                'prix_zones' => $prixZones,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tarif personnalisé créé avec succès.',
                'tarif' => $tarif
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur création tarif personnalisé : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Afficher un tarif personnalisé
     */
    public function showTarifCustom(Request $request, TarifPersonnalise $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            // Vérifier l'appartenance au backoffice
            if ($user->type === UserType::BACKOFFICE && $tarif->tarifSimple->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif ne vous appartient pas.'], 403);
            }

            return response()->json(['success' => true, 'tarif' => $tarif]);
        } catch (Exception $e) {
            Log::error('Erreur affichage tarif personnalisé : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer un tarif personnalisé
     */
    public function deleteTarifCustom(Request $request, TarifPersonnalise $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            // Vérifier l'appartenance au backoffice
            if ($user->type === UserType::BACKOFFICE && $tarif->tarifSimple->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif ne vous appartient pas.'], 403);
            }

            // Vérifier si des agences utilisent ce tarif
            $agencesUtilisant = \App\Models\Agence::where('tarif_code', $tarif->code)->count();
            if ($agencesUtilisant > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de supprimer ce tarif car $agencesUtilisant agence(s) l'utilisent."
                ], 422);
            }

            $tarif->delete();

            return response()->json(['success' => true, 'message' => 'Tarif personnalisé supprimé avec succès.']);
        } catch (Exception $e) {
            Log::error('Erreur suppression tarif personnalisé : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Modifier un tarif personnalisé
     */
    public function editTarifCustom(Request $request, TarifPersonnalise $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            // Vérifier l'appartenance au backoffice
            if ($user->type === UserType::BACKOFFICE && $tarif->tarifSimple->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif ne vous appartient pas.'], 403);
            }

            $request->validate([
                'prix_zones' => ['sometimes', 'array', 'min:1'],
                'prix_zones.*.zone_destination_id' => ['required_with:prix_zones', 'string', 'exists:zones,id'],
                'prix_zones.*.pourcentage_prestation' => ['required_with:prix_zones', 'numeric', 'min:0', 'max:100'],
            ]);

            if ($request->has('prix_zones')) {
                // Récupérer les montants de base depuis le tarif simple et recalculer
                $prixZones = [];
                $tarifSimplePrixZones = collect($tarif->tarifSimple->prix_zones);

                foreach ($request->prix_zones as $zoneRequest) {
                    $zoneId = $zoneRequest['zone_destination_id'];
                    $pourcentage = $zoneRequest['pourcentage_prestation'];

                    // Trouver le montant de base dans le tarif simple
                    $zoneSimple = $tarifSimplePrixZones->firstWhere('zone_destination_id', $zoneId);
                    if (!$zoneSimple) {
                        return response()->json([
                            'success' => false,
                            'message' => "Zone $zoneId introuvable dans le tarif de base."
                        ], 422);
                    }

                    $montantBase = $zoneSimple['montant_base'];

                    $prixZones[] = [
                        'zone_destination_id' => $zoneId,
                        'montant_base' => $montantBase,
                        'pourcentage_prestation' => $pourcentage,
                        'montant_prestation' => round(($montantBase * $pourcentage) / 100, 2, PHP_ROUND_HALF_UP),
                        'montant_expedition' => round($montantBase + ($montantBase * $pourcentage) / 100, 2, PHP_ROUND_HALF_UP),
                    ];
                }

                $tarif->prix_zones = $prixZones;
            }

            $tarif->save();

            return response()->json([
                'success' => true,
                'message' => 'Tarif personnalisé modifié avec succès.',
                'tarif' => $tarif
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur modification tarif personnalisé : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Activer/Désactiver un tarif personnalisé
     */
    public function toggleStatusTarifCustom(Request $request, TarifPersonnalise $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            // Vérifier l'appartenance au backoffice
            if ($user->type === UserType::BACKOFFICE && $tarif->tarifSimple->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif ne vous appartient pas.'], 403);
            }

            $tarif->actif = !$tarif->actif;
            $tarif->save();

            return response()->json([
                'success' => true,
                'message' => $tarif->actif ? 'Tarif activé.' : 'Tarif désactivé.',
                'tarif' => $tarif
            ]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut tarif personnalisé : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
