<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Colis;
use App\Models\Expedition;
use App\Enums\UserType;
use Illuminate\Support\Facades\Log;
use Exception;

class BackofficeColisController extends Controller
{
    /**
     * Liste des colis que le backoffice doit contrôler.
     * Basé sur les pays couverts par les zones rattachées au backoffice.
     */
    public function listColis(Request $request)
    {
        try {
            $user = $request->user();

            // Vérification du rôle
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $query = Colis::query();

            // Si c'est un utilisateur backoffice (non admin global)
            if ($user->type === UserType::BACKOFFICE) {
                if (!$user->backoffice_id || !$user->backoffice) {
                    return response()->json(['success' => false, 'message' => 'Aucun backoffice rattaché à votre compte.'], 403);
                }

                $backofficeCountry = $user->backoffice->pays;

                if (!$backofficeCountry) {
                    return response()->json(['success' => false, 'message' => 'Pays non défini pour votre backoffice.'], 403);
                }

                $query->whereHas('expedition.agence', function ($q) use ($backofficeCountry) {
                    $q->where('pays', $backofficeCountry);
                });
            }

            // --- FILTRES ---

            // Recherche par code colis, référence expédition ou info expéditeur/destinataire
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('code_colis', 'like', "%{$search}%")
                        ->orWhereHas('expedition', function ($sq) use ($search) {
                            $sq->where('reference', 'like', "%{$search}%")
                                ->orWhere('expediteur->nom_prenom', 'like', "%{$search}%")
                                ->orWhere('destinataire->nom_prenom', 'like', "%{$search}%");
                        });
                });
            }

            // Filtre par statut d'expédition
            if ($status = $request->get('statut_expedition')) {
                $query->whereHas('expedition', function ($q) use ($status) {
                    $q->where('statut_expedition', $status);
                });
            }

            // Filtre par agence
            if ($agenceId = $request->get('agence_id')) {
                $query->whereHas('expedition', function ($q) use ($agenceId) {
                    $q->where('agence_id', $agenceId);
                });
            }

            // Filtre par statut de contrôle
            if ($request->has('is_controlled')) {
                $query->where('is_controlled', $request->boolean('is_controlled'));
            }

            // Filtre par statut de réception (reçu par backoffice)
            if ($request->has('is_received_by_backoffice')) {
                $query->where('is_received_by_backoffice', $request->boolean('is_received_by_backoffice'));
            }

            // Filtre par date de création
            if ($request->filled('date_debut')) {
                $query->whereDate('created_at', '>=', $request->get('date_debut'));
            }
            if ($request->filled('date_fin')) {
                $query->whereDate('created_at', '<=', $request->get('date_fin'));
            }

            // --- CHARGEMENT ---

            $colis = $query->with([
                'expedition:id,reference,statut_expedition,pays_depart,pays_destination,expediteur,destinataire,agence_id',
                'expedition.agence:id,nom_agence,code_agence,telephone',
                'category:id,nom'
            ])->get();
            // ->latest()->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $colis,
                // 'data' => $colis->items(),
                // 'meta' => [
                //     'current_page' => $colis->currentPage(),
                //     'last_page' => $colis->lastPage(),
                //     'per_page' => $colis->perPage(),
                //     'total' => $colis->total(),
                // ]
            ]);

        } catch (Exception $e) {
            Log::error('Erreur liste colis backoffice : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Détails d'un colis pour le backoffice.
     */
    public function showColis(Request $request, string $code)
    {
        try {
            $user = $request->user();

            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $colis = Colis::where('code_colis', $code)->first();

            if (!$colis) {
                return response()->json(['success' => false, 'message' => 'Colis introuvable.'], 404);
            }

            // Si c'est un backoffice, vérifier que le colis lui appartient via le pays de l'agence
            if ($user->type === UserType::BACKOFFICE) {
                if (!$user->backoffice || $colis->expedition->agence->pays !== $user->backoffice->pays) {
                    return response()->json(['success' => false, 'message' => 'Accès non autorisé à ce colis.'], 403);
                }
            }

            $colis->load([
                'expedition:id,reference,statut_expedition,pays_depart,pays_destination,expediteur,destinataire,agence_id',
                'expedition.agence:id,nom_agence,code_agence,telephone',
                'category:id,nom'
            ]);

            return response()->json([
                'success' => true,
                'colis' => $colis
            ]);

        } catch (Exception $e) {
            Log::error('Erreur détails colis backoffice : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Marquer plusieurs colis comme contrôlés.
     */
    public function markMultipleAsControlled(Request $request)
    {
        try {
            $user = $request->user();

            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $request->validate([
                'codes' => 'required|array',
                'codes.*' => 'string|exists:colis,code_colis',
            ]);

            $codes = $request->input('codes');

            $query = Colis::whereIn('code_colis', $codes);

            // Vérification du pays pour le backoffice
            if ($user->type === UserType::BACKOFFICE) {
                if (!$user->backoffice) {
                    return response()->json(['success' => false, 'message' => 'Aucun backoffice rattaché.'], 403);
                }

                $backofficeCountry = $user->backoffice->pays;

                // Vérifier si des colis ne sont pas autorisés
                $nonAuthorized = (clone $query)->whereHas('expedition.agence', function ($q) use ($backofficeCountry) {
                    $q->where('pays', '!=', $backofficeCountry);
                })->exists();

                if ($nonAuthorized) {
                    return response()->json(['success' => false, 'message' => 'Certains colis ne font pas partie de votre territoire.'], 403);
                }
            }

            $query->update([
                'is_controlled' => true,
                'controlled_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => count($codes) . ' colis contrôlés avec succès.',
            ]);

        } catch (Exception $e) {
            Log::error('Erreur contrôle multiple colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Marquer plusieurs colis comme réceptionnés (reçus d'ailleurs par le backoffice).
     */
    public function markMultipleAsReceived(Request $request)
    {
        try {
            $user = $request->user();

            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $request->validate([
                'codes' => 'required|array',
                'codes.*' => 'string|exists:colis,code_colis',
                'agence_id' => 'required|uuid|exists:agences,id',
            ]);

            $codes = $request->input('codes');
            $agenceDestinationId = $request->input('agence_id');

            $query = Colis::whereIn('code_colis', $codes);

            // Tous les colis d'une même expédition doivent être attribués à la même agence
            $expeditionIds = Colis::whereIn('code_colis', $codes)->pluck('expedition_id')->unique();
            $expeditionsAvecAutreAgence = Expedition::whereIn('id', $expeditionIds)
                ->whereNotNull('agence_destination_id')
                ->where('agence_destination_id', '!=', $agenceDestinationId)
                ->exists();
            if ($expeditionsAvecAutreAgence) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tous les colis d\'une même expédition doivent être envoyés à la même agence. Une expédition concernée a déjà été attribuée à une autre agence.',
                ], 422);
            }

            // Vérification du pays pour le backoffice
            if ($user->type === UserType::BACKOFFICE) {
                if (!$user->backoffice) {
                    return response()->json(['success' => false, 'message' => 'Aucun backoffice rattaché.'], 403);
                }

                $backofficeCountry = $user->backoffice->pays;

                $nonAuthorized = (clone $query)->whereHas('expedition.agence', function ($q) use ($backofficeCountry) {
                    $q->where('pays', '!=', $backofficeCountry);
                })->exists();

                if ($nonAuthorized) {
                    return response()->json(['success' => false, 'message' => 'Certains colis ne font pas partie de votre territoire.'], 403);
                }
            }

            $query->update([
                'is_received_by_backoffice' => true,
                'received_at_backoffice' => now(),
            ]);

            // Désigner l'agence qui devra réceptionner les colis (uniquement si pas déjà définie pour cette expédition)
            Expedition::whereIn('id', $expeditionIds)
                ->whereNull('agence_destination_id')
                ->update(['agence_destination_id' => $agenceDestinationId]);

            // Mise à jour automatique du statut des expéditions si tous leurs colis sont reçus par le backoffice
            Expedition::whereIn('id', $expeditionIds)->each(fn (Expedition $e) => $e->syncStatutFromColis());

            return response()->json([
                'success' => true,
                'message' => count($codes) . ' colis marqués comme réceptionnés avec succès.',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Données invalides.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur réception multiple colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }
}
