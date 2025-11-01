<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Enums\UserType;
use Illuminate\Support\Facades\Log;
use Exception;

class BackofficeUserController extends Controller
{
    /**
     * Liste tous les utilisateurs du système (tous types confondus).
     * Réservé aux utilisateurs backoffice.
     */
    public function listUsers(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est de type backoffice et admin de son backoffice
            if ($user->type !== UserType::BACKOFFICE || !$user->isBackofficeAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur du backoffice peut gérer les utilisateurs.'
                ], 403);
            }


            $backoffice = $user->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Récupérer tous les utilisateurs de l'agence (sauf l'admin et les supprimés)
            $users = $backoffice->users()->where('id', '!=', $backoffice->user_id)->notDeleted()->select('id', 'nom', 'prenoms', 'telephone', 'email', 'type', 'actif', 'role', 'backoffice_id', 'created_at')->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'users' => $users
            ]);
        } catch (Exception $e) {
            Log::error('Erreur liste utilisateurs backoffice : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée un nouvel utilisateur de n'importe quel type.
     * Réservé aux utilisateurs backoffice.
     */
    public function createUser(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est de type backoffice et admin de son backoffice
            if ($user->type !== UserType::BACKOFFICE || !$user->isBackofficeAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur du backoffice peut créer des utilisateurs.'
                ], 403);
            }

            $backoffice = $user->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Validation des données
            $request->validate([
                'nom' => ['required', 'string', 'max:255'],
                'prenoms' => ['required', 'string', 'max:255'],
                'telephone' => ['required', 'string', 'unique:users,telephone'],
                'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'type' => ['required', 'in:backoffice'],
            ]);

            // Préparer les informations de rôle pour le frontend
            $roleInfo = match (UserType::from($request->type)) {
                UserType::BACKOFFICE => 'is_backoffice_member',
                default => 'is_user',
            };

            // Créer l'utilisateur
            $newUser = User::create([
                'nom' => $request->nom,
                'prenoms' => $request->prenoms,
                'telephone' => $request->telephone,
                'email' => $request->email,
                'type' => UserType::from($request->type),
                'password' => Hash::make($request->password),
                'backoffice_id' => $backoffice->id,
                'actif' => true,
                'role' => $roleInfo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès.',
                'user' => $newUser->only(['id', 'nom', 'prenoms', 'telephone', 'email', 'type', 'actif', 'backoffice_id', 'role'])
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur création utilisateur backoffice : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la création de l\'utilisateur.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour un utilisateur de n'importe quel type.
     * Réservé aux utilisateurs backoffice.
     */
    public function editUser(Request $request, User $user)
    {
        try {
            $adminUser = $request->user();

            // Vérifier que l'utilisateur est de type backoffice et admin de son backoffice
            if ($adminUser->type !== UserType::BACKOFFICE || !$adminUser->isBackofficeAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur du backoffice peut modifier les utilisateurs.'
                ], 403);
            }

            $backoffice = $adminUser->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Vérifier que l'utilisateur à modifier appartient à la même backoffice
            if ($user->backoffice_id !== $backoffice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur n\'appartient pas à votre backoffice.'
                ], 403);
            }

            // Validation des données
            $request->validate([
                'nom' => ['sometimes', 'string', 'max:255'],
                'prenoms' => ['sometimes', 'string', 'max:255'],
                'telephone' => ['sometimes', 'string', 'unique:users,telephone,' . $user->id],
                'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
                'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            ]);

            // Mettre à jour les champs fournis
            $updateData = $request->only(['nom', 'prenoms', 'telephone', 'email']);


            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès.',
                'user' => $user->only(['id', 'nom', 'prenoms', 'telephone', 'email', 'type', 'actif', 'backoffice_id', 'role'])
           ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur modification utilisateur backoffice : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la modification de l\'utilisateur.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un utilisateur spécifique
     */
    public function showUser(Request $request, User $user)
    {
        try {
            $adminUser = $request->user();

            // Vérifier que l'utilisateur est de type backoffice et admin de son backoffice
            if ($adminUser->type !== UserType::BACKOFFICE || !$adminUser->isBackofficeAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur du backoffice peut consulter les utilisateurs.'
                ], 403);
            }
            $backoffice = $adminUser->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Vérifier que l'utilisateur à modifier appartient à la même backoffice
            if ($user->backoffice_id !== $backoffice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur n\'appartient pas à votre backoffice.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'user' => $user->only(['id', 'nom', 'prenoms', 'telephone', 'email', 'type', 'backoffice_id', 'role', 'actif', 'created_at', 'updated_at'])
            ]);
        } catch (Exception $e) {
            Log::error('Erreur affichage utilisateur backoffice : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer/désactiver un utilisateur
     */
    public function toggleStatusUser(Request $request, User $user)
    {
        try {
            $adminUser = $request->user();

            // Vérifier que l'utilisateur est de type backoffice et admin de son backoffice
            if ($adminUser->type !== UserType::BACKOFFICE || !$adminUser->isBackofficeAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur du backoffice peut modifier les utilisateurs.'
                ], 403);
            }

            $backoffice = $adminUser->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Vérifier que l'utilisateur à modifier appartient à la même backoffice
            if ($user->backoffice_id !== $backoffice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur n\'appartient pas à votre backoffice.'
                ], 403);
            }

            // Empêcher la modification de l'admin créateur par lui-même (sécurité)
            if ($user->id === $backoffice->user_id && $request->has('type')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier le type de l\'administrateur créateur.'
                ], 422);
            }

            $user->actif = !$user->actif;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => $user->actif ? 'Utilisateur activé.' : 'Utilisateur désactivé.',
                'user' => $user->only(['id', 'nom', 'prenoms', 'telephone', 'email', 'type', 'actif'])
            ]);
        } catch (Exception $e) {
            Log::error('Erreur toggle statut utilisateur backoffice : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer (soft delete) un utilisateur.
     * Réservé aux utilisateurs backoffice.
     */
    public function deleteUser(Request $request, User $user)
    {
        try {
            $adminUser = $request->user();

            // Vérifier que l'utilisateur est de type backoffice et admin de son backoffice
            if ($adminUser->type !== UserType::BACKOFFICE || !$adminUser->isBackofficeAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur du backoffice peut supprimer les utilisateurs.'
                ], 403);
            }

            $backoffice = $adminUser->backoffice;
            if (!$backoffice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backoffice introuvable.'
                ], 404);
            }

            // Vérifier que l'utilisateur à modifier appartient à la même backoffice
            if ($user->backoffice_id !== $backoffice->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur n\'appartient pas à votre backoffice.'
                ], 403);
            }

            // Soft delete : marquer comme supprimé
            $user->is_deleted = true;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès.',
                'user' => $user->only(['id', 'nom', 'prenoms', 'telephone', 'email', 'type', 'role'])
            ]);
        } catch (Exception $e) {
            Log::error('Erreur suppression utilisateur backoffice : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la suppression de l\'utilisateur.',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
