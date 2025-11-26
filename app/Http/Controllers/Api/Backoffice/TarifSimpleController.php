<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\TarifSimple;
use App\Models\TarifAgence;
use App\Enums\UserType;
use App\Enums\ModeExpedition;
use App\Enums\TypeColis;
use App\Models\TarifAgenceSimple;
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
                'prix_zones' => ['required', 'array', 'min:1'],
                'prix_zones.*.zone_destination_id' => ['required', 'string', 'exists:zones,id'],
                'prix_zones.*.montant_base' => ['required', 'numeric', 'min:0'],
                'prix_zones.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            $tarif = TarifSimple::create([
                'indice' => $request->indice,
                'prix_zones' => $request->prix_zones,
                'mode_expedition'=> 'simple',
                'pays' => $user->backoffice->pays,
                'backoffice_id' => $user->backoffice->id
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
                'prix_zones' => ['sometimes', 'array', 'min:1'],
                'prix_zones.*.zone_destination_id' => ['required', 'string', 'exists:zones,id'],
                'prix_zones.*.montant_base' => ['required', 'numeric', 'min:0'],
                'prix_zones.*.pourcentage_prestation' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            // Stocker les anciennes valeurs pour détecter les changements de pourcentage_prestation
            $ancienPrixZones = $tarif->prix_zones;

            $tarif->update($request->only(['indice', 'prix_zones']));

            // Vérifier si les prix_zones ont changé et mettre à jour les tarifs d'agence liés
            if ($request->has('prix_zones')) {
                $this->mettreAJourTarifsAgence($tarif, $ancienPrixZones, $request->prix_zones);
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
    private function mettreAJourTarifsAgence(TarifSimple $tarif, array $ancienPrixZones, array $nouveauPrixZones)
    {
        // Créer un mapping des anciens prix par zone
        $anciensPrix = [];
        foreach ($ancienPrixZones as $zone) {
            $anciensPrix[$zone['zone_destination_id']] = [
                'montant_base' => $zone['montant_base'],
                'pourcentage_prestation' => $zone['pourcentage_prestation']
            ];
        }

        // Créer un mapping des nouveaux prix par zone
        $nouveauxPrix = [];
        foreach ($nouveauPrixZones as $zone) {
            $nouveauxPrix[$zone['zone_destination_id']] = [
                'montant_base' => $zone['montant_base'],
                'pourcentage_prestation' => $zone['pourcentage_prestation']
            ];
        }

        // Calculer les différences de pourcentage par zone
        $differencesPourcentages = [];
        $changementsMontant = [];
        foreach ($nouveauxPrix as $zoneId => $nouveauPrix) {
            if (isset($anciensPrix[$zoneId])) {
                $ancienPrix = $anciensPrix[$zoneId];

                // Vérifier les changements de pourcentage
                $differencePourcentage = $nouveauPrix['pourcentage_prestation'] - $ancienPrix['pourcentage_prestation'];
                if ($differencePourcentage != 0) {
                    $differencesPourcentages[$zoneId] = $differencePourcentage;
                }

                // Vérifier les changements de montant_base
                if ($nouveauPrix['montant_base'] != $ancienPrix['montant_base']) {
                    $changementsMontant[$zoneId] = $nouveauPrix['montant_base'];
                }
            }
        }

        // Si aucun changement, sortir
        if (empty($differencesPourcentages) && empty($changementsMontant)) {
            return;
        }

        // Récupérer tous les tarifs d'agence liés à ce tarif simple
        $tarifsAgence = TarifAgenceSimple::where('tarif_simple_id', $tarif->id)->get();

        foreach ($tarifsAgence as $tarifAgence) {
            $prixZonesAgence = $tarifAgence->prix_zones ?? [];
            $modifie = false;

            foreach ($prixZonesAgence as &$zoneAgence) {
                $zoneId = $zoneAgence['zone_destination_id'];

                // Appliquer les changements de pourcentage
                if (isset($differencesPourcentages[$zoneId])) {
                    $zoneAgence['pourcentage_prestation'] += $differencesPourcentages[$zoneId];
                    // S'assurer que le pourcentage reste dans les limites (0-100)
                    $zoneAgence['pourcentage_prestation'] = max(0, min(100, $zoneAgence['pourcentage_prestation']));
                    $modifie = true;
                }

                // Recalculer les montants si le montant_base du tarif de base a changé
                if (isset($changementsMontant[$zoneId])) {
                    $nouveauMontant = $changementsMontant[$zoneId];
                    $zoneAgence['montant_base'] = $nouveauMontant;
                    $zoneAgence['montant_prestation'] = round(($nouveauMontant * $zoneAgence['pourcentage_prestation']) / 100, 2, PHP_ROUND_HALF_UP);
                    $zoneAgence['montant_expedition'] = round($nouveauMontant + $zoneAgence['montant_prestation'], 2, PHP_ROUND_HALF_UP);
                    $modifie = true;
                }
            }

            if ($modifie) {
                $tarifAgence->prix_zones = $prixZonesAgence;
                $tarifAgence->save();
            }
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
