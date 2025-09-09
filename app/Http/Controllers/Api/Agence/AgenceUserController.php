<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Agence;
use App\Enums\UserType;
use Illuminate\Support\Facades\Log;
use Exception;

class AgenceUserController extends Controller
{
    /**
     * Liste les utilisateurs de l'agence.
     * Réservé à l'admin créateur de l'agence.
     */
    public function listUser(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est de type agence et admin de son agence
            if ($user->type !== UserType::AGENCE || !$user->isAgenceAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur de l\'agence peut gérer les utilisateurs.'
                ], 403);
            }

            $agence = $user->agence;
            if (!$agence) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agence introuvable.'
                ], 404);
            }

            // Récupérer tous les utilisateurs de l'agence (y compris l'admin)
            $users = $agence->users()->select('id', 'nom', 'prenoms', 'telephone', 'email', 'type', 'actif', 'created_at')->get();

            return response()->json([
                'success' => true,
                'users' => $users,
                'agence' => [
                    'id' => $agence->id,
                    'nom_agence' => $agence->nom_agence,
                    'admin_id' => $agence->user_id
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Erreur liste utilisateurs agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur.'
            ], 500);
        }
    }

    /**
     * Crée un nouvel utilisateur rattaché à l'agence.
     * Réservé à l'admin créateur de l'agence.
     */
    public function createUser(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifier que l'utilisateur est de type agence et admin de son agence
            if ($user->type !== UserType::AGENCE || !$user->isAgenceAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur de l\'agence peut créer des utilisateurs.'
                ], 403);
            }

            $agence = $user->agence;
            if (!$agence) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agence introuvable.'
                ], 404);
            }

            // Validation des données
            $request->validate([
                'nom' => ['required', 'string', 'max:255'],
                'prenoms' => ['required', 'string', 'max:255'],
                'telephone' => ['required', 'string', 'unique:users,telephone'],
                'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'type' => ['required', 'in:livreur,agence'], // Interdire la création d'autres admins agence
            ]);

            // Préparer les informations de rôle pour le frontend
            $roleInfo = match ($request->type) {
                UserType::AGENCE => 'is_agence_member',
                UserType::LIVREUR => 'is_livreur',
                default => 'unknown_role',
            };

            // Créer l'utilisateur rattaché à l'agence
            $newUser = User::create([
                'nom' => $request->nom,
                'prenoms' => $request->prenoms,
                'telephone' => $request->telephone,
                'email' => $request->email,
                'type' => UserType::from($request->type),
                'password' => Hash::make($request->password),
                'agence_id' => $agence->id,
                'actif' => true,
                'role' => $roleInfo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès.',
                'user' => $newUser->only(['id', 'nom', 'prenoms', 'telephone', 'email', 'type', 'actif', 'agence_id', 'role'])
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur création utilisateur agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la création de l\'utilisateur.'
            ], 500);
        }
    }

    /**
     * Met à jour un utilisateur de l'agence.
     * Réservé à l'admin créateur de l'agence.
     */
    public function editUser(Request $request, User $user)
    {
        try {
            $adminUser = $request->user();

            // Vérifier que l'utilisateur est de type agence et admin de son agence
            if ($adminUser->type !== UserType::AGENCE || !$adminUser->isAgenceAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur de l\'agence peut modifier les utilisateurs.'
                ], 403);
            }

            $agence = $adminUser->agence;
            if (!$agence) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agence introuvable.'
                ], 404);
            }

            // Vérifier que l'utilisateur à modifier appartient à la même agence
            if ($user->agence_id !== $agence->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur n\'appartient pas à votre agence.'
                ], 403);
            }

            // Empêcher la modification de l'admin créateur par lui-même (sécurité)
            if ($user->id === $agence->user_id && $request->has('type')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier le type de l\'administrateur créateur.'
                ], 422);
            }

            // Validation des données
            $request->validate([
                'nom' => ['sometimes', 'string', 'max:255'],
                'prenoms' => ['sometimes', 'string', 'max:255'],
                'telephone' => ['sometimes', 'string', 'unique:users,telephone,' . $user->id],
                'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
                'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
                'type' => ['sometimes', 'in:livreur,agence,backoffice'],
            ]);

            // Mettre à jour les champs fournis
            $updateData = $request->only(['nom', 'prenoms', 'telephone', 'email']);

            if ($request->has('type')) {
                $updateData['type'] = UserType::from($request->type);
            }

            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès.',
                'user' => $user->only(['id', 'nom', 'prenoms', 'telephone', 'email', 'type', 'actif', 'agence_id'])
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données.',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Erreur modification utilisateur agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la modification de l\'utilisateur.'
            ], 500);
        }
    }

    /**
     * Supprime/désactive un utilisateur de l'agence.
     * Réservé à l'admin créateur de l'agence.
     */
    public function editStatusUser(Request $request, User $user)
    {
        try {
            $adminUser = $request->user();

            // Vérifier que l'utilisateur est de type agence et admin de son agence
            if ($adminUser->type !== UserType::AGENCE || !$adminUser->isAgenceAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé. Seul l\'administrateur de l\'agence peut supprimer les utilisateurs.'
                ], 403);
            }

            $agence = $adminUser->agence;
            if (!$agence) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agence introuvable.'
                ], 404);
            }

            // Vérifier que l'utilisateur à supprimer appartient à la même agence
            if ($user->agence_id !== $agence->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur n\'appartient pas à votre agence.'
                ], 403);
            }

            // Empêcher la suppression de l'admin créateur
            if ($user->id === $agence->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer l\'administrateur créateur de l\'agence.'
                ], 422);
            }

            // Désactiver l'utilisateur au lieu de le supprimer (soft delete logique)
            $user->update(['actif' => false]);

            // Optionnel: révoquer tous ses tokens d'accès
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur désactivé avec succès.'
            ]);
        } catch (Exception $e) {
            Log::error('Erreur suppression utilisateur agence : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue est survenue lors de la suppression de l\'utilisateur.'
            ], 500);
        }
    }
}
