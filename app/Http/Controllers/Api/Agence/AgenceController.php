<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Agence;
use App\Models\Colis;
use App\Models\User;
use App\Enums\UserType;
use App\Enums\ColisStatus;
use Illuminate\Support\Facades\Log;
use Exception; // Importer la classe Exception pour la gestion des erreurs
use Illuminate\Support\Facades\Storage;

class AgenceController extends Controller
{

    /**
     * Enregistre les informations d'agence pour un utilisateur de type 'agence'.
     * L'utilisateur doit être authentifié et de type 'agence'.
     */
    public function setupAgence(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifie si l'utilisateur authentifié est bien de type 'agence'
            if ($user->type !== UserType::AGENCE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seuls les utilisateurs de type agence peuvent configurer une agence.'
                ], 403);
            }

            // Vérifie si l'utilisateur a déjà une agence configurée
            $existingAgence = Agence::where('user_id', $user->id)->first();
            if ($existingAgence) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une agence est déjà configurée pour cet utilisateur.'
                ], 422);
            }

            // Valide les données d'entrée de la requête
            $request->validate([
                'nom_agence' => ['required', 'string', 'max:255'],
                'telephone' => ['required', 'string', 'max:20'],
                'description' => ['nullable', 'string', 'max:1000'],
                'adresse' => ['required', 'string', 'max:255'],
                'ville' => ['required', 'string', 'max:255'],
                'commune' => ['required', 'string', 'max:255'],
                'pays' => ['required', 'string', 'max:255'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'horaires' => ['nullable', 'array'],
                'horaires.*.jour' => ['required_with:horaires', 'string', 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche'],
                'horaires.*.ouverture' => ['required_with:horaires', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
                'horaires.*.fermeture' => ['required_with:horaires', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
            ]);

            // Crée l'agence
            $agence = Agence::create([
                'user_id' => $user->id,
                'nom_agence' => $request->nom_agence,
                'telephone' => $request->telephone,
                'description' => $request->description,
                'adresse' => $request->adresse,
                'ville' => $request->ville,
                'commune' => $request->commune,
                'pays' => $request->pays,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'horaires' => $request->horaires ?? [],
            ]);

            // Rattache l'utilisateur créateur à l'agence créée
            $user->agence_id = $agence->id;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Agence configurée avec succès.',
                'agence' => $agence->toArray()
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur lors de la configuration de l\'agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la configuration de l\'agence.'
            ], 500);
        }
    }


    /**
     * Affiche les informations de l'agence associée à l'utilisateur authentifié.
     * Accessible uniquement par un utilisateur de type 'agence'.
     */
    public function showAgence(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifie si l'utilisateur authentifié est bien de type 'agence'
            if ($user->type !== UserType::AGENCE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seules les agences peuvent consulter leur profil.'
                ], 403); // Statut HTTP 403 Forbidden
            }

            // Récupère l'agence liée via l'agence_id (admin et membres)
            if (!$user->agence_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune agence rattachée à cet utilisateur.'
                ], 404);
            }
            $agence = Agence::find($user->agence_id);

            // Si aucune agence n'est trouvée pour cet utilisateur
            if (!$agence) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil d\'agence introuvable pour cet utilisateur.'
                ], 404); // Statut HTTP 404 Not Found
            }

            // Retourne les informations de l'agence
            return response()->json([
                'success' => true,
                'agence' => $agence->toArray() // Convertit le modèle en tableau pour la réponse JSON
            ]);
        } catch (Exception $e) {
            // Log l'erreur pour le débogage et retourne une réponse générique
            Log::error('Erreur lors de la récupération du profil agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la récupération du profil agence. Veuillez réessayer ultérieurement.',
                // 'error_details' => $e->getMessage() // À décommenter pour le débogage seulement
            ], 500); // Statut HTTP 500 Internal Server Error
        }
    }

    /**
     * Met à jour les informations du profil de l'agence associée à l'utilisateur authentifié.
     * Accessible uniquement par un utilisateur de type 'agence'.
     */
    public function updateAgence(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifie si l'utilisateur authentifié est bien de type 'agence'
            if ($user->type !== UserType::AGENCE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seules les agences peuvent modifier leur profil.'
                ], 403);
            }

            // Récupère l'agence liée à l'ID de l'utilisateur
            $agence = Agence::where('user_id', $user->id)->first();

            // Si aucune agence n'est trouvée pour cet utilisateur
            if (!$agence) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil d\'agence introuvable pour cet utilisateur.'
                ], 404);
            }

            // Valide les données d'entrée de la requête pour la mise à jour
            $request->validate([
                'nom_agence' => ['sometimes', 'string', 'max:255'], // 'sometimes' : le champ n'est validé que s'il est présent
                'telephone' => ['sometimes', 'string', 'max:20'],
                'description' => ['sometimes', 'string', 'max:1000'],
                'adresse' => ['sometimes', 'string', 'max:255'],
                'ville' => ['sometimes', 'string', 'max:255'],
                'commune' => ['sometimes', 'string', 'max:255'],
                'pays' => ['required', 'string', 'max:255'],
                'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
                'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
                'horaires' => ['sometimes', 'array'], // Les horaires peuvent être un tableau d'objets JSON
                'horaires.*.jour' => ['required_with:horaires', 'string'],
                'horaires.*.ouverture' => ['required_with:horaires', 'string'],
                'horaires.*.fermeture' => ['required_with:horaires', 'string'],
            ]);

            // Met à jour l'agence avec les données validées
            $agence->update($request->all());

            // Retourne une réponse de succès avec les informations de l'agence mises à jour
            return response()->json([
                'success' => true,
                'message' => 'Profil de l\'agence mis à jour avec succès.',
                'agence' => $agence->toArray()
            ]);
        } catch (ValidationException $e) {
            // Capture les erreurs de validation et retourne une réponse 422
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données de l\'agence.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            // Log l'erreur et retourne une réponse générique pour les erreurs inattendues
            Log::error('Erreur lors de la mise à jour du profil agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la mise à jour du profil agence. Veuillez réessayer ultérieurement.',
                // 'error_details' => $e->getMessage() // À décommenter pour le débogage seulement
            ], 500);
        }
    }


    /**
     * Tableau de bord avec statistiques de l'agence.
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            $agence = Agence::find($user->agence_id);
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            // Statistiques générales
            $totalColis = Colis::where('agence_id', $agence->id)->count();
            $colisEnAttente = Colis::where('agence_id', $agence->id)->where('status', ColisStatus::EN_ATTENTE)->count();
            $colisEnCours = Colis::where('agence_id', $agence->id)->whereIn('status', [
                ColisStatus::VALIDE,
                ColisStatus::EN_ENLEVEMENT,
                ColisStatus::RECUPERE,
                ColisStatus::EN_TRANSIT,
                ColisStatus::EN_AGENCE,
                ColisStatus::EN_LIVRAISON
            ])->count();
            $colisLivre = Colis::where('agence_id', $agence->id)->where('status', ColisStatus::LIVRE)->count();

            // Statistiques des 30 derniers jours
            $dateDebut = now()->subDays(30);
            $colisMois = Colis::where('agence_id', $agence->id)
                ->where('created_at', '>=', $dateDebut)
                ->count();

            // Revenus du mois (approximatif)
            $revenusMois = Colis::where('agence_id', $agence->id)
                ->where('created_at', '>=', $dateDebut)
                ->where('status', ColisStatus::LIVRE)
                ->sum('commission_agence');

            // Livreurs actifs
            $livreursActifs = User::where('agence_id', $agence->id)
                ->where('type', UserType::LIVREUR)
                ->where('actif', true)
                ->count();

            // Colis récents
            $colisRecents = Colis::where('agence_id', $agence->id)
                ->with(['expediteur:id,nom,prenoms', 'destinataire:id,nom,prenoms', 'livreur:id,nom,prenoms'])
                ->latest()
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'dashboard' => [
                    'statistiques' => [
                        'total_colis' => $totalColis,
                        'colis_en_attente' => $colisEnAttente,
                        'colis_en_cours' => $colisEnCours,
                        'colis_livre' => $colisLivre,
                        'colis_mois' => $colisMois,
                        'revenus_mois' => $revenusMois,
                        'livreurs_actifs' => $livreursActifs
                    ],
                    'colis_recents' => $colisRecents
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erreur dashboard agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Statistiques détaillées avec graphiques.
     */
    public function statistiques(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            $agence = Agence::find($user->agence_id);
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            $periode = $request->get('periode', '30'); // 7, 30, 90 jours
            $dateDebut = now()->subDays($periode);

            // Évolution des colis par jour
            $evolutionColis = Colis::where('agence_id', $agence->id)
                ->where('created_at', '>=', $dateDebut)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Répartition par statut
            $repartitionStatuts = Colis::where('agence_id', $agence->id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get();

            // Top livreurs
            $topLivreurs = User::where('agence_id', $agence->id)
                ->where('type', UserType::LIVREUR)
                ->withCount(['colisLivres as colis_livres' => function ($query) use ($agence) {
                    $query->where('agence_id', $agence->id)->where('status', ColisStatus::LIVRE);
                }])
                ->orderBy('colis_livres', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'statistiques' => [
                    'periode' => $periode,
                    'evolution_colis' => $evolutionColis,
                    'repartition_statuts' => $repartitionStatuts,
                    'top_livreurs' => $topLivreurs
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erreur statistiques agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Liste des livreurs disponibles de l'agence.
     */
    public function livreursDisponibles(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            $agence = Agence::find($user->agence_id);
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            $livreurs = User::where('agence_id', $agence->id)
                ->where('type', UserType::LIVREUR)
                ->where('actif', true)
                ->select('id', 'nom', 'prenoms', 'telephone', 'disponible', 'latitude', 'longitude')
                ->get();

            return response()->json([
                'success' => true,
                'livreurs' => $livreurs
            ]);
        } catch (Exception $e) {
            Log::error('Erreur liste livreurs : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }



    // D'autres méthodes (par exemple, pour la gestion spécifique des tarifs ou des missions) pourraient être ajoutées ici.
}
