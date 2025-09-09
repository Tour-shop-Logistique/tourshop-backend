<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Colis;
use App\Services\TarificationService;
use Illuminate\Http\Request;
use App\Enums\ColisStatus;

class ColisController extends Controller
{
    protected $tarificationService;

    public function __construct(TarificationService $tarificationService)
    {
        $this->tarificationService = $tarificationService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Récupération des colis où l'utilisateur est expéditeur ou destinataire
        $colis = Colis::where(function($query) use ($user) {
            $query->where('expediteur_id', $user->id)
                  ->orWhere('destinataire_id', $user->id);
        })
        ->with(['livreur', 'agence', 'destinataire'])
        ->orderBy('created_at', 'desc')
        ->paginate(10);

        return response()->json([
            'success' => true,
            'colis' => $colis
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'destinataire_id' => 'nullable|exists:users,id',
            'destinataire_nom' => 'nullable|string|max:255',
            'destinataire_telephone' => 'nullable|string',
            'adresse_destinataire' => 'required|string',
            'description' => 'required|string|max:500',
            'poids' => 'required|numeric|min:0.1|max:50',
            'adresse_enlevement' => 'required|string',
            'lat_enlevement' => 'required|numeric',
            'lng_enlevement' => 'required|numeric',
            'lat_livraison' => 'required|numeric',
            'lng_livraison' => 'required|numeric',
            'agence_id' => 'nullable|exists:agences,id',
            'enlevement_domicile' => 'boolean',
            'livraison_express' => 'boolean',
            'paiement_livraison' => 'boolean',
            'photo_colis' => 'nullable|image|max:2048',
        ]);

        // Validation conditionnelle selon le type de destinataire
        if ($request->destinataire_id) {
            // Destinataire utilisateur : les champs nom et téléphone ne sont pas nécessaires
            $request->validate([
                'destinataire_nom' => 'nullable',
                'destinataire_telephone' => 'nullable',
            ]);
        } else {
            // Destinataire externe : les champs nom et téléphone sont obligatoires
            $request->validate([
                'destinataire_nom' => 'required|string|max:255',
                'destinataire_telephone' => 'required|string',
            ]);
        }

        try {
            // Calcul du tarif
            $tarifData = $this->tarificationService->calculerTarif($request->all());

            // Upload de la photo si présente
            $photoPath = null;
            if ($request->hasFile('photo_colis')) {
                $photoPath = $request->file('photo_colis')->store('colis', 'public');
            }

            // Création du colis avec gestion des destinataires
            $colis = Colis::create([
                'expediteur_id' => $request->user()->id,
                'destinataire_id' => $request->destinataire_id,
                'destinataire_nom' => $request->destinataire_nom,
                'destinataire_telephone' => $request->destinataire_telephone,
                'adresse_destinataire' => $request->adresse_destinataire,
                'description' => $request->description,
                'poids' => $request->poids,
                'photo_colis' => $photoPath,
                'adresse_enlevement' => $request->adresse_enlevement,
                'lat_enlevement' => $request->lat_enlevement,
                'lng_enlevement' => $request->lng_enlevement,
                'lat_livraison' => $request->lat_livraison,
                'lng_livraison' => $request->lng_livraison,
                'agence_id' => $request->agence_id,
                'prix_total' => $tarifData['prix_total'],
                'commission_livreur' => $tarifData['commission_livreur'],
                'commission_agence' => $tarifData['commission_agence'],
                'enlevement_domicile' => $request->boolean('enlevement_domicile'),
                'livraison_express' => $request->boolean('livraison_express'),
                'paiement_livraison' => $request->boolean('paiement_livraison'),
                'instructions_enlevement' => $request->instructions_enlevement,
                'instructions_livraison' => $request->instructions_livraison,
                'status' => ColisStatus::EN_ATTENTE,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Colis créé avec succès',
                'colis' => $colis->load(['agence', 'destinataire']),
                'tarification' => $tarifData
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du colis',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        // Récupération du colis avec contrôle d'accès
        $colis = Colis::where(function($query) use ($user) {
            $query->where('expediteur_id', $user->id)
                  ->orWhere('destinataire_id', $user->id);
        })
        ->where('id', $id)
        ->with(['livreur', 'agence', 'destinataire', 'historiques'])
        ->first();

        if (!$colis) {
            return response()->json([
                'success' => false,
                'message' => 'Colis non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'colis' => $colis
        ]);
    }

    public function suivre(Request $request, $codesuivi)
    {
        $colis = Colis::where('code_suivi', $codesuivi)
                     ->with(['livreur', 'agence', 'destinataire', 'historiques'])
                     ->first();

        if (!$colis) {
            return response()->json([
                'success' => false,
                'message' => 'Code de suivi invalide'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'colis' => $colis
        ]);
    }

    public function annuler(Request $request, $id)
    {
        $colis = Colis::where('expediteur_id', $request->user()->id)
                     ->where('id', $id)
                     ->first();

        if (!$colis) {
            return response()->json([
                'success' => false,
                'message' => 'Colis non trouvé'
            ], 404);
        }

        if (!in_array($colis->status, [ColisStatus::EN_ATTENTE, ColisStatus::VALIDE])) {
            return response()->json([
                'success' => false,
                'message' => 'Ce colis ne peut plus être annulé'
            ], 400);
        }

        $colis->update(['status' => ColisStatus::ANNULE]);

        return response()->json([
            'success' => true,
            'message' => 'Colis annulé avec succès'
        ]);
    }

    /**
     * Rechercher des utilisateurs pour les destinataires
     * Permet de trouver des utilisateurs existants lors de la création d'un colis
     */
    public function searchDestinataires(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        // Recherche d'utilisateurs clients par nom, prénom, téléphone ou email
        $users = \App\Models\User::where('type', 'client')
            ->where(function($query) use ($request) {
                $query->where('nom', 'like', '%' . $request->query . '%')
                      ->orWhere('prenoms', 'like', '%' . $request->query . '%')
                      ->orWhere('telephone', 'like', '%' . $request->query . '%')
                      ->orWhere('email', 'like', '%' . $request->query . '%');
            })
            ->limit(10)
            ->get(['id', 'nom', 'prenoms', 'telephone', 'email']);

        return response()->json([
            'success' => true,
            'destinataires' => $users,
        ]);
    }
}