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

class AgenceColisController extends Controller
{

    /**
     * Liste des colis opérés par l'agence (avec filtres simples).
     */
    public function colis(Request $request)
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

            $query = Colis::query()->where('agence_id', $agence->id);
            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->get('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->get('to'));
            }

            $colis = $query->latest()->paginate(20);
            return response()->json(['success' => true, 'data' => $colis]);
        } catch (Exception $e) {
            Log::error('Erreur liste colis agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Recherche avancée de colis avec filtres multiples.
     */
    public function rechercheColis(Request $request)
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

            $query = Colis::where('agence_id', $agence->id)
                ->with(['expediteur:id,nom,prenoms,telephone', 'destinataire:id,nom,prenoms,telephone', 'livreur:id,nom,prenoms']);

            // Filtres
            if ($request->filled('code_suivi')) {
                $query->where('code_suivi', 'like', '%' . $request->code_suivi . '%');
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('expediteur_nom')) {
                $query->whereHas('expediteur', function ($q) use ($request) {
                    $q->where('nom', 'like', '%' . $request->expediteur_nom . '%')
                        ->orWhere('prenoms', 'like', '%' . $request->expediteur_nom . '%');
                });
            }

            if ($request->filled('destinataire_nom')) {
                $query->where('destinataire_nom', 'like', '%' . $request->destinataire_nom . '%');
            }

            if ($request->filled('date_debut')) {
                $query->whereDate('created_at', '>=', $request->date_debut);
            }

            if ($request->filled('date_fin')) {
                $query->whereDate('created_at', '<=', $request->date_fin);
            }

            $colis = $query->latest()->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $colis
            ]);
        } catch (Exception $e) {
            Log::error('Erreur recherche colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Détails d'un colis spécifique avec historique.
     */
    public function detailsColis(Request $request, Colis $colis)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            // Vérifier que le colis appartient à cette agence
            if ($colis->agence_id !== $user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Colis non trouvé.'], 404);
            }

            // Charger les relations et l'historique
            $colis->load([
                'expediteur:id,nom,prenoms,telephone,email',
                'destinataire:id,nom,prenoms,telephone,email',
                'livreur:id,nom,prenoms,telephone',
                'historiqueStatuts' => function ($query) {
                    $query->with('user:id,nom,prenoms')->latest();
                }
            ]);

            return response()->json([
                'success' => true,
                'colis' => $colis
            ]);
        } catch (Exception $e) {
            Log::error('Erreur détails colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Accepter une demande de colis (statut -> VALIDE) et rattacher à l'agence.
     */
    public function accepter(Request $request, Colis $colis)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            if (!$user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Aucune agence rattachée à cet utilisateur.'], 404);
            }

            if ($colis->status !== ColisStatus::EN_ATTENTE && $colis->status !== ColisStatus::VALIDE) {
                return response()->json(['success' => false, 'message' => 'Ce colis ne peut pas être accepté dans son état actuel.'], 422);
            }

            // Rattacher au compte agence de l'utilisateur (admin ou membre)
            $colis->agence_id = $user->agence_id;
            $colis->status = ColisStatus::VALIDE;
            $colis->save();

            return response()->json(['success' => true, 'message' => 'Demande acceptée.', 'colis' => $colis]);
        } catch (Exception $e) {
            Log::error('Erreur acceptation colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Refuser une demande de colis (statut -> ANNULE). Motif pris en entrée (stockage détaillé à ajouter via migration dédiée).
     */
    public function refuser(Request $request, Colis $colis)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            // Le refus ne nécessite pas une agence existante si le colis n'est pas encore rattaché, sinon vérifier l'appartenance
            if ($colis->agence_id && $colis->agence_id !== $user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Colis non trouvé.'], 404);
            }
            $request->validate([
                'motif' => ['required', 'string', 'max:500'],
            ]);

            if ($colis->status !== ColisStatus::EN_ATTENTE) {
                return response()->json(['success' => false, 'message' => 'Seules les demandes en attente peuvent être refusées.'], 422);
            }

            $colis->status = ColisStatus::ANNULE;
            // persister le motif de refus quand le champ/migration sera en place
            $colis->save();

            return response()->json(['success' => true, 'message' => 'Demande refusée.', 'colis' => $colis]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur refus colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Assigner un livreur à un colis.
     */
    public function assignerLivreur(Request $request, Colis $colis)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            // Vérifier que le colis appartient bien à l'agence de l'utilisateur
            if ($colis->agence_id !== $user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Colis non trouvé.'], 404);
            }
            $request->validate([
                'livreur_id' => ['required', 'exists:users,id'],
            ]);
            $livreur = User::find($request->livreur_id);
            if ($livreur->type !== UserType::LIVREUR || $livreur->is_deleted) {
                return response()->json(['success' => false, 'message' => 'L\'utilisateur choisi n\'est pas un livreur valide.'], 422);
            }
            $colis->livreur_id = $livreur->id;
            $colis->save();
            return response()->json(['success' => true, 'message' => 'Livreur assigné.', 'colis' => $colis]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur assignation livreur : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Changer le statut d'un colis selon le workflow.
     */
    public function changerStatut(Request $request, Colis $colis)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            // Vérifier que le colis appartient bien à l'agence de l'utilisateur
            if ($colis->agence_id !== $user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Colis non trouvé.'], 404);
            }
            $request->validate([
                'status' => ['required', 'in:en_enlevement,recupere,en_transit,en_agence,en_livraison,livre']
            ]);
            $colis->status = ColisStatus::from($request->status);
            if ($request->status === ColisStatus::LIVRE->value) {
                $colis->date_livraison = now();
            }
            $colis->save();
            return response()->json(['success' => true, 'message' => 'Statut mis à jour.', 'colis' => $colis]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur changement statut colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Ajouter des preuves (photo de livraison, signature). Fichiers optionnels.
     */
    public function ajouterPreuves(Request $request, Colis $colis)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            // Vérifier que le colis appartient bien à l'agence de l'utilisateur
            if ($colis->agence_id !== $user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Colis non trouvé.'], 404);
            }
            $request->validate([
                'photo_livraison' => ['sometimes', 'file', 'image', 'max:5120'], // 5MB
                'signature_destinataire' => ['sometimes', 'file', 'image', 'max:5120'],
            ]);

            if ($request->hasFile('photo_livraison')) {
                $path = $request->file('photo_livraison')->store('colis/preuves', 'public');
                $colis->photo_livraison = $path;
            }
            if ($request->hasFile('signature_destinataire')) {
                $path = $request->file('signature_destinataire')->store('colis/preuves', 'public');
                $colis->signature_destinataire = $path;
            }
            $colis->save();

            return response()->json(['success' => true, 'message' => 'Preuves ajoutées.', 'colis' => $colis]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur ajout preuves colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    /**
     * Vérifier un colis à l'entrepôt (poids réel, ajustement prix si fourni).
     */
    public function verifier(Request $request, Colis $colis)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }
            // Vérifier que le colis appartient bien à l'agence de l'utilisateur
            if ($colis->agence_id !== $user->agence_id) {
                return response()->json(['success' => false, 'message' => 'Colis non trouvé.'], 404);
            }
            $request->validate([
                'poids' => ['sometimes', 'numeric', 'min:0.01'],
                'prix_total' => ['sometimes', 'numeric', 'min:0'],
            ]);

            if ($request->filled('poids')) {
                $colis->poids = $request->poids;
            }
            if ($request->filled('prix_total')) {
                $colis->prix_total = $request->prix_total;
            }
            // recalcul via TarificationService si besoin

            $colis->save();
            return response()->json(['success' => true, 'message' => 'Colis vérifié.', 'colis' => $colis]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Erreur de validation.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Erreur vérification colis : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }
}
