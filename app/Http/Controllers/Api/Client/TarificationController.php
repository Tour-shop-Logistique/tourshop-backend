<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Services\TarificationService;
use Illuminate\Http\Request;

class TarificationController extends Controller
{
    protected $tarificationService;

    public function __construct(TarificationService $tarificationService)
    {
        $this->tarificationService = $tarificationService;
    }

    public function simuler(Request $request)
    {
        $request->validate([
            'poids' => 'required|numeric|min:0.1|max:50',
            'lat_enlevement' => 'required|numeric',
            'lng_enlevement' => 'required|numeric',
            'lat_livraison' => 'required|numeric',
            'lng_livraison' => 'required|numeric',
            'agence_id' => 'nullable|exists:agences,id',
            'enlevement_domicile' => 'boolean',
            'livraison_express' => 'boolean',
        ]);

        try {
            $tarification = $this->tarificationService->calculerTarif($request->all());

            return response()->json([
                'success' => true,
                'tarification' => $tarification
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du tarif',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function agencesProches(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'rayon' => 'nullable|numeric|min:1|max:50'
        ]);

        $rayon = $request->get('rayon', 10); // 10km par défaut

        $agences = Agence::actives()
                        ->with('user')
                        ->selectRaw(
                            "*, (6371 * acos(cos(radians({$request->latitude})) * cos(radians(latitude)) * cos(radians(longitude) - radians({$request->longitude})) + sin(radians({$request->latitude})) * sin(radians(latitude)))) AS distance"
                        )
                        ->havingRaw("distance <= $rayon")
                        ->orderBy('distance')
                        ->get();

        return response()->json([
            'success' => true,
            'agences' => $agences
        ]);
    }
}