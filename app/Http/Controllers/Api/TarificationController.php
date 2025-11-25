<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Zone;
use App\Services\TarificationService;
use App\Enums\ModeExpedition;
use App\Enums\TypeColis;
use Illuminate\Support\Facades\Log;
use Exception;

class TarificationController extends Controller
{
    protected $tarificationService;

    public function __construct(TarificationService $tarificationService)
    {
        $this->tarificationService = $tarificationService;
    }

    /**
     * Simule le prix d'expédition d'un colis
     */
    public function simuler(Request $request)
    {
        try {
            $request->validate([
                'pays_depart' => ['required', 'string'],
                'pays_arrivee' => ['required', 'string'],
                'type_colis' => ['required_if:mode_expedition,groupage', 'in:document,colis_standard,colis_fragile,colis_volumineux,produit_alimentaire,electronique,vetement,autre'],
                'mode_expedition' => ['required', 'in:simple,groupage'],
                'poids' => ['required', 'numeric', 'min:0.1'],
                // Dimensions requises pour mode simple
                'longueur' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'largeur' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                'hauteur' => ['required_if:mode_expedition,simple', 'numeric', 'min:0'],
                // Option livraison domicile pour groupage
                'livraison_domicile' => ['sometimes', 'boolean'],
                // Catégorie groupage pour fallback/priorité
                'category_id' => ['sometimes', 'uuid', 'exists:category_products,id'],
            ]);

            // Utiliser le service de tarification pour la simulation
            $resultat = $this->tarificationService->simulerTarification([
                'pays_depart' => $request->pays_depart,
                'pays_arrivee' => $request->pays_arrivee,
                'type_colis' => TypeColis::from($request->type_colis)->value,
                'mode_expedition' => ModeExpedition::from($request->mode_expedition)->value,
                'poids' => $request->poids,
                'longueur' => $request->longueur ?? 0,
                'largeur' => $request->largeur ?? 0,
                'hauteur' => $request->hauteur ?? 0,
                'livraison_domicile' => $request->get('livraison_domicile', false),
                'category_id' => $request->get('category_id'),
            ]);

            if (!$resultat['success']) {
                return response()->json($resultat, 404);
            }

            return response()->json($resultat);
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
