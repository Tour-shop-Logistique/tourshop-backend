<?php

namespace App\Http\Controllers\Api\Agence;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Agence;
use App\Models\User;
use App\Enums\UserType;
use Illuminate\Support\Facades\Log;
use Exception;

class AgenceNotificationController extends Controller
{
    /**
     * Liste des notifications de l'agence.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $agence = Agence::where('user_id', $user->id)->first();
            if (!$agence) {
                return response()->json(['success' => false, 'message' => 'Agence introuvable.'], 404);
            }

            // Pour l'instant, on simule des notifications
            // Dans une vraie implémentation, vous auriez une table notifications
            $notifications = [
                [
                    'id' => 1,
                    'type' => 'nouveau_colis',
                    'titre' => 'Nouveau colis reçu',
                    'message' => 'Un nouveau colis a été déposé et nécessite votre validation.',
                    'date' => now()->subMinutes(15)->toISOString(),
                    'lu' => false,
                    'priorite' => 'haute'
                ],
                [
                    'id' => 2,
                    'type' => 'livreur_disponible',
                    'titre' => 'Livreur disponible',
                    'message' => 'Jean Dupont est maintenant disponible pour de nouvelles missions.',
                    'date' => now()->subHours(2)->toISOString(),
                    'lu' => true,
                    'priorite' => 'normale'
                ],
                [
                    'id' => 3,
                    'type' => 'colis_livre',
                    'titre' => 'Colis livré avec succès',
                    'message' => 'Le colis TS20250101001 a été livré avec succès.',
                    'date' => now()->subHours(4)->toISOString(),
                    'lu' => true,
                    'priorite' => 'normale'
                ]
            ];

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'non_lues' => collect($notifications)->where('lu', false)->count()
            ]);
        } catch (Exception $e) {
            Log::error('Erreur liste notifications agence : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Marquer une notification comme lue.
     */
    public function marquerLue(Request $request, $notificationId)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            // Dans une vraie implémentation, vous mettriez à jour la base de données
            // Pour l'instant, on simule juste une réponse de succès

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue.'
            ]);
        } catch (Exception $e) {
            Log::error('Erreur marquer notification lue : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Marquer toutes les notifications comme lues.
     */
    public function marquerToutesLues(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            // Dans une vraie implémentation, vous mettriez à jour toutes les notifications

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications ont été marquées comme lues.'
            ]);
        } catch (Exception $e) {
            Log::error('Erreur marquer toutes notifications lues : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer une notification.
     */
    public function supprimer(Request $request, $notificationId)
    {
        try {
            $user = $request->user();
            if ($user->type !== UserType::AGENCE) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            // Dans une vraie implémentation, vous supprimeriez la notification de la base de données

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée.'
            ]);
        } catch (Exception $e) {
            Log::error('Erreur suppression notification : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'errors' => $e->getMessage()], 500);
        }
    }
}
