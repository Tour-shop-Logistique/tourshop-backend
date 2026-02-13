<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Colis;
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
     * Marquer un colis comme contrôlé.
     */
    public function markAsControlled(Request $request, string $code)
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

            // Vérification du pays pour le backoffice
            if ($user->type === UserType::BACKOFFICE) {
                if (!$user->backoffice || $colis->expedition->agence->pays !== $user->backoffice->pays) {
                    return response()->json(['success' => false, 'message' => 'Accès non autorisé à ce colis.'], 403);
                }
            }

            $colis->is_controlled = true;
            $colis->save();

            return response()->json([
                'success' => true,
                'message' => 'Colis marqué comme contrôlé avec succès.',
                'colis' => $colis
            ]);

        } catch (Exception $e) {
            Log::error('Erreur contrôle colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }
}
