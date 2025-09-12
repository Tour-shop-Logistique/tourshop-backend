<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tarif;
use App\Models\Zone;
use App\Enums\ModeExpedition;
use App\Enums\TypeColis;
use Illuminate\Support\Facades\Log;
use Exception;

class TarificationController extends Controller
{
    /**
     * Simule le prix d'expédition d'un colis
     */
    public function simuler(Request $request)
    {
        try {
            $request->validate([
                'pays_depart' => ['required', 'string'],
                'pays_arrivee' => ['required', 'string'],
                'type_colis' => ['required', 'in:document,colis_standard,colis_fragile,colis_volumineux,produit_alimentaire,electronique,vetement,autre'],
                'mode_expedition' => ['required', 'in:simple,groupage'],
                'poids' => ['required', 'numeric', 'min:0.1'],
                // Dimensions requises pour mode simple
                'longueur' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'largeur' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'hauteur' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                // Option livraison domicile pour groupage
                'livraison_domicile' => ['sometimes', 'boolean']
            ]);

            // Trouver les zones correspondantes
            $zoneDepart = Zone::whereJsonContains('pays', $request->pays_depart)->first();
            $zoneArrivee = Zone::whereJsonContains('pays', $request->pays_arrivee)->first();

            if (!$zoneDepart || !$zoneArrivee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Zone non trouvée pour un ou plusieurs pays.',
                    'zones_disponibles' => Zone::select('nom', 'pays')->get()
                ], 404);
            }

            // Calculer l'indice de référence
            $indiceReference = Tarif::determinerIndiceReference(
                $request->poids,
                $request->longueur ?? 0,
                $request->largeur ?? 0,
                $request->hauteur ?? 0
            );

            $indiceArrondi = Tarif::arrondirIndice($indiceReference);

            // Chercher le tarif correspondant
            $tarif = Tarif::trouverTarifPourColis(
                $zoneDepart->id,
                $zoneArrivee->id,
                $request->type_colis,
                $request->mode_expedition,
                $request->poids,
                $request->longueur ?? 0,
                $request->largeur ?? 0,
                $request->hauteur ?? 0
            );

            if (!$tarif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun tarif trouvé pour ces critères.',
                    'details' => [
                        'zone_depart' => $zoneDepart->nom,
                        'zone_arrivee' => $zoneArrivee->nom,
                        'type_colis' => $request->type_colis,
                        'mode_expedition' => $request->mode_expedition,
                        'indice_calcule' => $indiceArrondi
                    ]
                ], 404);
            }

            // Calculer le prix selon le mode
            $prix = 0;
            $detailsCalcul = [];

            if ($request->mode_expedition === 'simple') {
                $prix = $tarif->calculerPrixSimple($request->poids);
                $detailsCalcul = [
                    'montant_base' => $tarif->montant_base,
                    'pourcentage_prestation' => $tarif->pourcentage_prestation,
                    'supplement_prestation' => ($tarif->montant_base * $tarif->pourcentage_prestation) / 100,
                    'volume_cm3' => ($request->longueur ?? 0) * ($request->largeur ?? 0) * ($request->hauteur ?? 0),
                    'volume_divise' => $tarif->calculerVolumeDivise($request->longueur ?? 0, $request->largeur ?? 0, $request->hauteur ?? 0),
                    'indice_reference' => $indiceReference,
                    'indice_arrondi' => $indiceArrondi
                ];
            } else {
                $livraisonDomicile = $request->get('livraison_domicile', false);
                $prix = $tarif->calculerPrixGroupage($request->poids, $livraisonDomicile);
                $detailsCalcul = [
                    'prix_entrepot' => $tarif->prix_entrepot,
                    'supplement_domicile' => $livraisonDomicile ? $tarif->supplement_domicile_groupage : 0,
                    'livraison_domicile' => $livraisonDomicile,
                    'indice_reference' => $indiceReference,
                    'indice_arrondi' => $indiceArrondi
                ];
            }

            return response()->json([
                'success' => true,
                'simulation' => [
                    'prix_total' => $prix,
                    'devise' => 'FCFA',
                    'zone_depart' => $zoneDepart->nom,
                    'zone_arrivee' => $zoneArrivee->nom,
                    'type_colis' => TypeColis::from($request->type_colis)->label(),
                    'mode_expedition' => ModeExpedition::from($request->mode_expedition)->label(),
                    'poids_kg' => $request->poids,
                    'dimensions_cm' => $request->mode_expedition === 'simple' ? [
                        'longueur' => $request->longueur,
                        'largeur' => $request->largeur,
                        'hauteur' => $request->hauteur
                    ] : null,
                    'delai_livraison_heures' => $tarif->delai_livraison,
                    'details_calcul' => $detailsCalcul
                ],
                'tarif_utilise' => [
                    'id' => $tarif->id,
                    'nom' => $tarif->nom,
                    'agence' => $tarif->agence->nom ?? 'N/A'
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erreur simulation tarification : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la simulation.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste les zones disponibles avec leurs pays
     */
    public function zonesDisponibles()
    {
        try {
            $zones = Zone::where('actif', true)
                ->select('id', 'nom', 'code', 'pays')
                ->get();

            return response()->json([
                'success' => true,
                'zones' => $zones
            ]);
        } catch (Exception $e) {
            Log::error('Erreur récupération zones : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des zones.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
