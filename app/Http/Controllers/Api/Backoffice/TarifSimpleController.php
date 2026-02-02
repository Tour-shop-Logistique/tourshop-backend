<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\TarifSimple;
use App\Enums\UserType;
use App\Models\TarifAgenceSimple;
use App\Enums\TypeExpedition;
use Exception;

class TarifSimpleController extends Controller
{
    /**
     * Lister les tarifs de base
     */
    public function list(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN, UserType::AGENCE])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $query = TarifSimple::query();

            // if ($request->filled('indice')) {
            //     $query->where('indice', $request->indice);
            // }
            // if ($request->filled('mode_expedition')) {
            //     $query->where('mode_expedition', $request->mode_expedition);
            // }
            // if ($request->filled('pays')) {
            //     $query->where('pays', $request->pays);
            // }

            if ($user->type === UserType::BACKOFFICE) {
                $query->where('backoffice_id', $user->backoffice_id);
            }

            if ($user->type === UserType::AGENCE) {
                $query->where('pays', $user->agence->pays);
            }


            $tarifs = $query->orderBy('indice')->get();

            return response()->json(['success' => true, 'tarifs' => $tarifs]);
        } catch (Exception $e) {
            Log::error('Erreur listing tarifs base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Créer un tarif de base
     */
    public function add(Request $request)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $request->validate([
                'indice' => ['required', 'numeric', 'min:0'],
                'zone_destination_id' => ['required', 'string', 'exists:zones,id'],
                'montant_base' => ['required', 'numeric', 'min:0'],
                'pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $backofficeId = $user->backoffice_id;
            $pays = $user->backoffice->pays;

            // Vérifier si un tarif existe déjà pour cet indice et cette zone destination
            $exists = TarifSimple::where('backoffice_id', $backofficeId)
                ->where('indice', $request->indice)
                ->where('zone_destination_id', $request->zone_destination_id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => "Un tarif existe déjà pour cet indice et cette zone de destination."
                ], 422);
            }

            $tarif = TarifSimple::create([
                'indice' => $request->indice,
                'zone_destination_id' => $request->zone_destination_id,
                'montant_base' => $request->montant_base,
                'pourcentage_prestation' => $request->pourcentage_prestation,
                'type_expedition' => TypeExpedition::LD->value,
                'pays' => $pays,
                'backoffice_id' => $backofficeId
            ]);

            return response()->json(['success' => true, 'message' => 'Tarif simple créé avec succès.', 'tarif' => $tarif], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur création tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour un tarif de base
     */
    public function edit(Request $request, TarifSimple $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if ($user->type === UserType::BACKOFFICE && $tarif->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif n\'appartient pas à votre backoffice.'], 403);
            }

            $request->validate([
                'indice' => ['sometimes', 'numeric', 'min:0'],
                'zone_destination_id' => ['sometimes', 'string', 'exists:zones,id'],
                'montant_base' => ['sometimes', 'numeric', 'min:0'],
                'pourcentage_prestation' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            ]);

            $ancienMontantBase = $tarif->montant_base;
            $ancienPourcentage = $tarif->pourcentage_prestation;

            $tarif->update($request->only(['indice', 'zone_destination_id', 'montant_base', 'pourcentage_prestation']));

            // Vérifier si les montants ont changé et mettre à jour les tarifs d'agence liés
            if ($tarif->montant_base != $ancienMontantBase || $tarif->pourcentage_prestation != $ancienPourcentage) {
                $this->mettreAJourTarifsAgence($tarif, $ancienMontantBase, $ancienPourcentage);
            }

            return response()->json(['success' => true, 'message' => 'Tarif de base mis à jour.', 'tarif' => $tarif]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation des données.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Afficher un tarif de base
     */
    public function show(Request $request, TarifSimple $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if ($user->type === UserType::BACKOFFICE && $tarif->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif n\'appartient pas à votre backoffice.'], 403);
            }

            return response()->json(['success' => true, 'tarif' => $tarif]);
        } catch (Exception $e) {
            Log::error('Erreur affichage tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }


    /**
     * Supprimer un tarif de base
     */
    public function delete(Request $request, TarifSimple $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if ($user->type === UserType::BACKOFFICE && $tarif->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif n\'appartient pas à votre backoffice.'], 403);
            }

            $tarif->delete();

            return response()->json(['success' => true, 'message' => 'Tarif simple supprimé.']);
        } catch (Exception $e) {
            Log::error('Erreur suppression tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour les tarifs d'agence liés en cas de changement du tarif de base
     */
    private function mettreAJourTarifsAgence(TarifSimple $tarif, $ancienMontantBase, $ancienPourcentage)
    {
        // Différence de pourcentage à appliquer aux agences
        $deltaPourcentage = $tarif->pourcentage_prestation - $ancienPourcentage;

        // Récupérer tous les tarifs d'agence liés à ce tarif simple précis
        $tarifsAgence = TarifAgenceSimple::where('tarif_simple_id', $tarif->id)->get();

        foreach ($tarifsAgence as $tarifAgence) {
            // Si le montant de base a changé, on le répercute
            if ($tarif->montant_base != $ancienMontantBase) {
                $tarifAgence->montant_base = $tarif->montant_base;
            }

            // Si le pourcentage du backoffice a changé, on applique le delta au pourcentage de l'agence
            if ($deltaPourcentage != 0) {
                $tarifAgence->pourcentage_prestation = max(0, min(100, $tarifAgence->pourcentage_prestation + $deltaPourcentage));
            }

            $tarifAgence->save(); // Le modèle recalculera automatique les montants dans son boot() saving()
        }
    }

    /**
     * Activer/Désactiver un tarif de base
     */
    public function toggleStatus(Request $request, TarifSimple $tarif)
    {
        try {
            $user = $request->user();
            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if ($user->type === UserType::BACKOFFICE && $tarif->backoffice_id !== $user->backoffice_id) {
                return response()->json(['success' => false, 'message' => 'Ce tarif n\'appartient pas à votre backoffice.'], 403);
            }

            $tarif->actif = !$tarif->actif;
            $tarif->save();

            return response()->json(['success' => true, 'message' => $tarif->actif ? 'Tarif activé.' : 'Tarif désactivé.', 'tarif' => $tarif]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut tarif base : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
