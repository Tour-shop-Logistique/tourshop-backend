<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Enums\UserType;
use App\Models\Backoffice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;
use App\Services\SupabaseStorageService;

class BackofficeController extends Controller
{
    protected SupabaseStorageService $supabaseStorage;

    public function __construct(SupabaseStorageService $supabaseStorage)
    {
        $this->supabaseStorage = $supabaseStorage;
    }

    /**
     * Configure le backoffice pour un utilisateur de type 'backoffice'.
     * L'utilisateur doit être authentifié et de type 'backoffice'.
     */
    public function setupBackoffice(Request $request)
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

            // Vérifie si l'utilisateur authentifié est bien de type 'backoffice'
            if ($user->type !== UserType::BACKOFFICE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seuls les utilisateurs de type backoffice peuvent configurer le backoffice.'
                ], 403);
            }

            // Vérifie si l'utilisateur a déjà un backoffice configuré
            $existingBackoffice = Backoffice::where('user_id', $user->id)->first();
            if ($existingBackoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un backoffice est déjà configuré pour cet utilisateur.'
                ], 422);
            }

            // Valide les données d'entrée de la requête
            $request->validate([
                'nom_organisation' => ['required', 'string', 'max:255'],
                'telephone' => ['required', 'string', 'max:20'],
                'adresse' => ['required', 'string', 'max:255'],
                'localisation' => ['nullable', 'string', 'max:255'],
                'ville' => ['required', 'string', 'max:255'],
                'commune' => ['nullable', 'string', 'max:255'],
                'pays' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'], // 2MB max
            ]);

            // Gestion de l'upload du logo si fourni
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $this->supabaseStorage->upload($request->file('logo'), 'backoffices/logos');

                if (!$logoPath) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Erreur lors de l\'upload du logo.'
                    ], 500);
                }
            }

            // Créer le backoffice dans la table dédiée
            $backoffice = Backoffice::create([
                'user_id' => $user->id,
                'nom_organisation' => $request->nom_organisation,
                'telephone' => $request->telephone,
                'localisation' => $request->localisation,
                'adresse' => $request->adresse,
                'ville' => $request->ville,
                'commune' => $request->commune,
                'pays' => $request->pays,
                'email' => $request->email,
                'logo' => $logoPath,
            ]);

            // Rattacher l'utilisateur au backoffice
            $user->backoffice_id = $backoffice->id;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Backoffice configuré avec succès.',
                'backoffice' => $backoffice->toArray()
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur lors de la configuration du backoffice : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la configuration du backoffice.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Affiche les informations du backoffice associé à l'utilisateur authentifié.
     * Accessible uniquement par un utilisateur de type 'backoffice'.
     */
    public function showBackoffice(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifie si l'utilisateur authentifié est bien de type 'backoffice'
            if ($user->type !== UserType::BACKOFFICE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seuls les utilisateurs backoffice peuvent consulter leur profil.'
                ], 403);
            }

            // Récupère le backoffice de l'utilisateur
            $backoffice = $user->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable. Veuillez configurer votre backoffice.'
                ], 404);
            }

            // Retourne les informations du backoffice
            return response()->json([
                'success' => true,
                'backoffice' => $backoffice->toArray()
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la récupération du profil backoffice : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la récupération du profil backoffice.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour les informations du backoffice associé à l'utilisateur authentifié.
     * Accessible uniquement par un utilisateur de type 'backoffice'.
     */
    public function updateBackoffice(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifie si l'utilisateur authentifié est bien de type 'backoffice'
            if ($user->type !== UserType::BACKOFFICE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seuls les utilisateurs backoffice peuvent modifier leur profil.'
                ], 403);
            }

            // Récupère le backoffice de l'utilisateur
            $backoffice = $user->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable. Veuillez configurer votre backoffice d\'abord.'
                ], 404);
            }

            // Valide les données d'entrée de la requête pour la mise à jour
            $request->validate([
                'nom_organisation' => ['sometimes', 'string', 'max:255'],
                'telephone' => ['sometimes', 'string', 'max:20'],
                'adresse' => ['sometimes', 'string', 'max:255'],
                'localisation' => ['sometimes', 'string', 'max:255'],
                'ville' => ['sometimes', 'string', 'max:255'],
                'commune' => ['sometimes', 'string', 'max:255'],
                'pays' => ['sometimes', 'string', 'max:255'],
                'email' => ['sometimes', 'email', 'max:255'],
                'logo' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'], // 2MB max
            ]);

            // Gestion de l'upload du logo si fourni
            $updateData = $request->except('logo');
            if ($request->hasFile('logo')) {
                // Supprimer l'ancien logo si existant
                $oldLogo = $backoffice->getRawOriginal('logo');
                if ($oldLogo) {
                    $this->supabaseStorage->delete($oldLogo);
                }

                $logoPath = $this->supabaseStorage->upload($request->file('logo'), 'backoffices/logos');

                if (!$logoPath) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Erreur lors de l\'upload du logo.'
                    ], 500);
                }

                $updateData['logo'] = $logoPath;
            }

            // Met à jour le backoffice
            $backoffice->update($updateData);

            // Retourne une réponse de succès avec les informations mises à jour
            return response()->json([
                'success' => true,
                'message' => 'Profil du backoffice mis à jour avec succès.',
                'backoffice' => $backoffice->toArray()
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données du backoffice.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur lors de la mise à jour du profil backoffice : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la mise à jour du profil backoffice.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
