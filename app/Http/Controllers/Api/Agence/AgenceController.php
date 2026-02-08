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

    public function listAgences(Request $request)
    {
        try {
            $user = $request->user();
            // if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN, UserType::AGENCE])) {
            //     return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            // }

            $query = Agence::query();

            if ($user->type === UserType::BACKOFFICE) {
                $query->where('pays', $user->backoffice->pays);
            } else if ($request->filled('pays')) {
                $query->where('pays', $request->pays);
            }

            $agences = $query->orderBy('nom_agence')->get();

            return response()->json(['success' => true, 'agences' => $agences]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la récupération des agences : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enregistre les informations d'agence pour un utilisateur de type 'agence'.
     * L'utilisateur doit être authentifié et de type 'agence'.
     */
    public function setupAgence(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur existe bien en base
            if (!$user || !$user->exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé ou session expirée.'
                ], 401);
            }

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
                'code_agence' => ['required', 'string', 'max:10'],
                'nom_agence' => ['required', 'string', 'max:255'],
                'telephone' => ['required', 'string', 'max:20'],
                'description' => ['nullable', 'string', 'max:1000'],
                'adresse' => ['required', 'string', 'max:255'],
                'ville' => ['required', 'string', 'max:255'],
                'commune' => ['nullable', 'string', 'max:255'],
                'pays' => ['required', 'string', 'max:255'],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'horaires' => ['nullable'],
                'horaires.*.jour' => ['required_with:horaires', 'string', 'in:lundi,mardi,mercredi,jeudi,vendredi,samedi,dimanche'],
                'horaires.*.ouverture' => ['required_with:horaires', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
                'horaires.*.fermeture' => ['required_with:horaires', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
                'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:10240'], // 10MB max
            ]);

            // Normaliser le champ horaires (accepter array ou JSON string)
            $horaires = $request->input('horaires');
            if (is_string($horaires)) {
                $decoded = json_decode($horaires, true);
                $horaires = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($horaires)) {
                $horaires = [];
            }

            // Gestion du logo
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('agences/logos', 'public');
            }

            // Crée l'agence
            $agence = Agence::create([
                'user_id' => $user->id,
                'code_agence' => $request->code_agence,
                'nom_agence' => $request->nom_agence,
                'telephone' => $request->telephone,
                'description' => $request->description,
                'adresse' => $request->adresse,
                'ville' => $request->ville,
                'commune' => $request->commune,
                'pays' => $request->pays,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'horaires' => $horaires,
                'logo' => $logoPath,
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
                'message' => 'Une erreur inattendue est survenue lors de la configuration de l\'agence.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Affiche les informations de l'agence associée à l'utilisateur authentifié.
     * Accessible uniquement par un utilisateur de type 'agence'.
     */
    public function showAgence(Request $request, $id = null)
    {
        try {
            $user = $request->user();
            $agence = null;

            // 1. Déterminer quelle agence afficher
            if ($id) {
                // Si un ID est fourni, on cherche cette agence
                if (!in_array($user->type, [UserType::ADMIN, UserType::BACKOFFICE])) {
                    return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
                }
                $agence = Agence::where('id', $id)->first();
            } else {
                // Si pas d'ID, on affiche l'agence de l'utilisateur connecté (pour type AGENCE)
                if ($user->type !== UserType::AGENCE) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès non autorisé. Seules les agences peuvent consulter leur profil sans ID.'
                    ], 403);
                }

                if (!$user->agence_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Aucune agence rattachée à cet utilisateur.'
                    ], 404);
                }
                $agence = Agence::where('id', $user->agence_id)->first();
            }

            // 2. Vérifier si l'agence existe
            if (!$agence) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agence introuvable.'
                ], 404);
            }

            // 3. Vérification de sécurité pour le BACKOFFICE (même pays)
            if ($user->type === UserType::BACKOFFICE) {
                if (!$user->backoffice || $agence->pays !== $user->backoffice->pays) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès non autorisé : cette agence n\'appartient pas à votre pays.'
                    ], 403);
                }
            }

            // $agenceResponse = $agence->toArray();
            // if ($agence->logo) {
            //     $agenceResponse['logo_url'] = url('storage/' . $agence->logo);
            // }

            return response()->json([
                'success' => true,
                'agence' => $agence
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la récupération du profil agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la récupération du profil agence. Veuillez réessayer ultérieurement.',
                'errors' => $e->getMessage()
            ], 500);
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
                'pays' => ['string', 'max:255'],
                'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
                'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
                'horaires' => ['sometimes'], // peut être un array ou une chaîne JSON
                'horaires.*.jour' => ['required_with:horaires', 'string'],
                'horaires.*.ouverture' => ['required_with:horaires', 'string'],
                'horaires.*.fermeture' => ['required_with:horaires', 'string'],
                'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            ]);

            // Normaliser horaires si fourni dans la requête de mise à jour
            if ($request->has('horaires')) {
                $h = $request->input('horaires');
                if (is_string($h)) {
                    $decoded = json_decode($h, true);
                    $h = is_array($decoded) ? $decoded : [];
                } elseif (!is_array($h)) {
                    $h = [];
                }
                $request->merge(['horaires' => $h]);
            }

            // Gestion du logo pour la mise à jour
            if ($request->hasFile('logo')) {
                // Supprimer l'ancien logo s'il existe
                if ($agence->logo && Storage::disk('public')->exists($agence->logo)) {
                    Storage::disk('public')->delete($agence->logo);
                }
                // Stocker le nouveau logo
                $logoPath = $request->file('logo')->store('agences/logos', 'public');
                $request->merge(['logo' => $logoPath]);
            } elseif ($request->has('logo') && $request->logo === null) {
                // Supprimer le logo si explicitement mis à null
                if ($agence->logo && Storage::disk('public')->exists($agence->logo)) {
                    Storage::disk('public')->delete($agence->logo);
                }
                $request->merge(['logo' => null]);
            }

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
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change le statut actif/inactif d'une agence.
     * Si un ID est fourni, il est utilisé (réservé aux admins/backoffice).
     * Sinon, utilise l'agence de l'utilisateur connecté (si admin agence).
     */
    public function toggleStatus(Request $request, $id = null)
    {
        try {
            $user = $request->user();
            $agence = null;

            if ($id) {
                // Seuls ADMIN ou BACKOFFICE peuvent cibler une agence par ID
                if (!in_array($user->type, [UserType::ADMIN, UserType::BACKOFFICE])) {
                    return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
                }
                $agence = Agence::find($id);
            } else {
                // Un admin d'agence peut toggler sa propre agence
                if ($user->type !== UserType::AGENCE || !$user->isAgenceAdmin()) {
                    return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
                }
                $agence = Agence::where('id', $user->agence_id)->first();
            }

            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            // Vérification du pays pour les utilisateurs BACKOFFICE
            if ($user->type === UserType::BACKOFFICE) {
                if (!$user->backoffice || $agence->pays !== $user->backoffice->pays) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès non autorisé : cette agence n\'appartient pas à votre pays.'
                    ], 403);
                }
            }

            $agence->actif = !$agence->actif;
            $agence->save();

            return response()->json([
                'success' => true,
                'message' => $agence->actif ? 'Agence activée avec succès.' : 'Agence désactivée avec succès.',
                'actif' => $agence->actif,
                'agence' => $agence
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors du changement de statut de l\'agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
