<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agence;
use App\Models\Colis;
use App\Models\Expedition;
use App\Models\User;
use App\Enums\UserType;
use App\Enums\ExpeditionStatus;
use Illuminate\Support\Facades\Log;
use Exception;

class AgenceColisController extends Controller
{
    /**
     * Liste tous les colis rattachés aux expéditions de l'agence.
     */
    public function listColis(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            $agenceId = $user->agence_id;

            // Colis à réceptionner : désignés par le backoffice (agence_destination_id = mon agence, déjà reçus par le backoffice)
            if ($request->boolean('a_receptionner')) {
                $query = Colis::whereHas('expedition', function ($q) use ($agenceId) {
                    $q->where('agence_destination_id', $agenceId);
                })->where('is_received_by_backoffice', true)
                    ->with(['expedition:id,reference,statut_expedition,pays_depart,pays_destination,agence_destination_id', 'expedition.agenceDestination:id,nom_agence,code_agence', 'category:id,nom']);
            } else {
                // Colis des expéditions de l'agence (agence de départ)
                $query = Colis::whereHas('expedition', function ($q) use ($agenceId) {
                    $q->where('agence_id', $agenceId);
                })->with(['expedition:id,reference,statut_expedition,pays_depart,pays_destination', 'category:id,nom']);
            }

            // Filtrage par statut de l'expédition
            if ($status = $request->get('status')) {
                $query->whereHas('expedition', function ($q) use ($status) {
                    $q->where('statut_expedition', $status);
                });
            }

            // Filtrage par date de création
            if ($request->filled('date_debut')) {
                $query->whereDate('created_at', '>=', $request->get('date_debut'));
            }
            if ($request->filled('date_fin')) {
                $query->whereDate('created_at', '<=', $request->get('date_fin'));
            }

            // Recherche par code colis ou référence expédition
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('code_colis', 'like', "%{$search}%")
                        ->orWhereHas('expedition', function ($sq) use ($search) {
                            $sq->where('reference', 'like', "%{$search}%");
                        });
                });
            }

            $colis = $query->latest()->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $colis->items(),
                'meta' => [
                    'current_page' => $colis->currentPage(),
                    'last_page' => $colis->lastPage(),
                    'per_page' => $colis->perPage(),
                    'total' => $colis->total(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erreur liste colis agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Détails d'un colis spécifique.
     */
    public function show(Request $request, string $id)
    {
        try {
            $user = $request->user();

            $colis = Colis::with(['expedition', 'category'])->find($id);

            if (!$colis) {
                return response()->json(['success' => false, 'message' => 'Colis introuvable.'], 404);
            }

            // Vérifier que le colis appartient à l'agence de l'utilisateur
            if ($user->type !== UserType::ADMIN && $colis->expedition->agence_id !== $user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            return response()->json([
                'success' => true,
                'colis' => $colis
            ]);
        } catch (Exception $e) {
            Log::error('Erreur détails colis agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Marquer plusieurs colis comme réceptionnés par l'agence.
     * Une fois réceptionnés, le client peut savoir que son colis est disponible pour retrait.
     */
    public function markMultipleAsReceivedAtDestination(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            $request->validate([
                'codes' => 'required|array',
                'codes.*' => 'string|exists:colis,code_colis',
            ]);

            $codes = $request->input('codes');
            $agenceId = $user->agence_id;

            // Seuls les colis dont l'expédition a été désignée à cette agence (agence_destination_id) peuvent être marqués reçus
            $query = Colis::whereIn('code_colis', $codes)
                ->whereHas('expedition', function ($q) use ($agenceId) {
                    $q->where('agence_destination_id', $agenceId);
                });

            $updated = $query->update([
                'is_received_by_agence_destination' => true,
                'received_at_agence_destination' => now(),
            ]);

            // Mise à jour automatique du statut des expéditions si tous leurs colis sont reçus par l'agence
            $expeditionIds = Colis::whereIn('code_colis', $codes)->pluck('expedition_id')->unique();
            Expedition::whereIn('id', $expeditionIds)->each(fn (Expedition $e) => $e->syncStatutFromColis());

            return response()->json([
                'success' => true,
                'message' => $updated . ' colis marqués comme réceptionnés par l\'agence.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Données invalides.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur réception colis agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirmer la réception de colis à l'agence de départ (colis enlevés chez le client, arrivés en agence).
     * On indique les colis reçus par code ; si tous les colis d'une expédition sont reçus, le statut passe à RECU_AGENCE_DEPART.
     */
    public function markMultipleAsReceivedAtDepart(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            $request->validate([
                'codes' => 'required|array',
                'codes.*' => 'string|exists:colis,code_colis',
            ]);

            $codes = $request->input('codes');
            $agenceId = $user->agence_id;

            // Seuls les colis des expéditions de cette agence (agence de départ) peuvent être marqués reçus à l'agence de départ
            $query = Colis::whereIn('code_colis', $codes)
                ->whereHas('expedition', function ($q) use ($agenceId) {
                    $q->where('agence_id', $agenceId);
                });

            $updated = $query->update([
                'is_received_by_agence_depart' => true,
                'received_at_agence_depart' => now(),
            ]);

            // Mise à jour automatique du statut des expéditions si tous leurs colis sont reçus à l'agence de départ
            $expeditionIds = Colis::whereIn('code_colis', $codes)->pluck('expedition_id')->unique();
            Expedition::whereIn('id', $expeditionIds)->each(fn (Expedition $e) => $e->syncStatutFromColis());

            return response()->json([
                'success' => true,
                'message' => $updated . ' colis marqués comme reçus à l\'agence de départ.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Données invalides.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur confirmation réception agence départ (colis) : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Marquer plusieurs colis comme expédiés vers l'entrepôt.
     * Seuls les colis des expéditions de cette agence (agence de départ) sont concernés.
     * Quand tous les colis d'une expédition sont marqués, le statut passe à EN_TRANSIT_ENTREPOT.
     */
    public function markMultipleAsShippedToWarehouse(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            $request->validate([
                'codes' => 'required|array',
                'codes.*' => 'string|exists:colis,code_colis',
            ]);

            $codes = $request->input('codes');
            $agenceId = $user->agence_id;

            // Seuls les colis des expéditions de cette agence (agence de départ) peuvent être marqués expédiés vers l'entrepôt
            $query = Colis::whereIn('code_colis', $codes)
                ->whereHas('expedition', function ($q) use ($agenceId) {
                    $q->where('agence_id', $agenceId);
                });

            $updated = $query->update([
                'is_expedie_vers_entrepot' => true,
                'expedie_vers_entrepot_at' => now(),
            ]);

            // Mise à jour automatique du statut des expéditions si tous leurs colis sont expédiés vers l'entrepôt
            $expeditionIds = Colis::whereIn('code_colis', $codes)->pluck('expedition_id')->unique();
            Expedition::whereIn('id', $expeditionIds)->each(fn (Expedition $e) => $e->syncStatutFromColis());

            return response()->json([
                'success' => true,
                'message' => $updated . ' colis marqués comme expédiés vers l\'entrepôt.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Données invalides.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur expédition vers entrepôt (colis) : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }
}
