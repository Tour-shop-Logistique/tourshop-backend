<?php

use App\Http\Controllers\Api\Agence\AgenceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TarifController;
use App\Http\Controllers\Api\Client\ColisController;
use App\Http\Controllers\Api\Client\TarificationController;
use App\Http\Controllers\Api\Livreur\MissionController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Agence\AgenceUserController;
use App\Http\Controllers\Api\Agence\AgenceNotificationController;
use App\Http\Controllers\Api\Agence\AgenceTarifController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * |--------------------------------------------------------------------------
 * | API Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register API routes for your application. These
 * | routes are loaded by the RouteServiceProvider and all of them will
 * | be assigned to the "api" middleware group. Make something great!
 * |
 */

// Route OPTIONS globale pour gérer les requêtes preflight CORS
Route::options('{any}', function () {
    return response('', 200);
})->where('any', '.*');

// Routes publiques (sans authentification)
Route::get('/test-cors', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'CORS fonctionne !',
        'timestamp' => now(),
        'origin' => $request->header('Origin'),
        'method' => $request->method(),
        'headers' => $request->headers->all()
    ]);
});

Route::post('/register', [AuthController::class, 'register']); // Inscription
Route::post('/login', [AuthController::class, 'login']); // Connexion

// Routes protégées par l'authentification (nécessitent un jeton API valide)
Route::middleware('auth:sanctum')->group(function () {
    // Routes d'authentification protégées
    Route::post('/logout', [AuthController::class, 'logout']); // Déconnexion
    Route::get('/profil', [AuthController::class, 'profile']); // Profil de l'utilisateur connecté

    // Routes clients
    /*Route::prefix('client')->group(function () {
        Route::get('/colis', [ColisController::class, 'index']);
        Route::post('/colis', [ColisController::class, 'store']);
        Route::get('/colis/{id}', [ColisController::class, 'show']);
        Route::post('/colis/{id}/annuler', [ColisController::class, 'annuler']);
        Route::get('/suivre/{codesuivi}', [ColisController::class, 'suivre']);
        Route::get('/colis/search-destinataires', [ColisController::class, 'searchDestinataires']);
        Route::post('/tarification/simuler', [TarificationController::class, 'simuler']);
        Route::get('/agences-proches', [TarificationController::class, 'agencesProches']);
    });*/

    // Routes livreurs
    /*Route::prefix('livreur')->group(function () {
        Route::get('/dashboard', [MissionController::class, 'dashboard']);
        Route::get('/missions-disponibles', [MissionController::class, 'missionsDisponibles']);
        Route::post('/missions/{colis}/accepter', [MissionController::class, 'accepterMission']);
        Route::get('/mes-missions', [MissionController::class, 'mesMissions']);
        Route::post('/missions/{colis}/confirmer-enlevement', [MissionController::class, 'confirmerEnlevement']);
        Route::post('/missions/{colis}/confirmer-livraison', [MissionController::class, 'confirmerLivraison']);
        Route::post('/disponibilite', [MissionController::class, 'changerDisponibilite']);
    });*/

    // Routes agences
    Route::prefix('agence')->group(function () {
        Route::post('/setup', [AgenceController::class, 'setupAgence']);
        Route::get('/show', [AgenceController::class, 'showAgence']);
        Route::put('/update', [AgenceController::class, 'updateAgence']);

        // Gestion des utilisateurs de l'agence (réservé à l'admin créateur)
        Route::get('/list-users', [AgenceUserController::class, 'listUser']);
        Route::post('/create-user', [AgenceUserController::class, 'createUser']);
        Route::put('/edit-user/{user}', [AgenceUserController::class, 'editUser']);


        // Tableau de bord et statistiques
        // Route::get('/dashboard', [AgenceController::class, 'dashboard']);  // Tableau de bord avec statistiques
        // Route::get('/statistiques', [AgenceController::class, 'statistiques']);  // Statistiques détaillées
        // Route::get('/livreurs-disponibles', [AgenceController::class, 'livreursDisponibles']);  // Liste des livreurs

        // Application Agence: workflow opérationnel
        // Route::get('/expeditions', [AgenceController::class, 'colis']);
        // Route::get('/expeditions/recherche', [AgenceController::class, 'rechercheColis']);  // Recherche avancée
        // Route::get('/expeditions/{colis}', [AgenceController::class, 'detailsColis']);  // Détails d'un colis
        // Route::post('/expeditions/{colis}/accepter', [AgenceController::class, 'accepter']);
        // Route::post('/expeditions/{colis}/refuser', [AgenceController::class, 'refuser']);
        // Route::post('/expeditions/{colis}/assign-livreur', [AgenceController::class, 'assignerLivreur']);
        // Route::post('/expeditions/{colis}/statut', [AgenceController::class, 'changerStatut']);
        // Route::post('/expeditions/{colis}/preuves', [AgenceController::class, 'ajouterPreuves']);
        // Route::post('/expeditions/{colis}/verifier', [AgenceController::class, 'verifier']);


        // Notifications de l'agence
        // Route::get('/notifications', [AgenceNotificationController::class, 'index']);
        // Route::put('/notifications/{notificationId}/lue', [AgenceNotificationController::class, 'marquerLue']);
        // Route::put('/notifications/toutes-lues', [AgenceNotificationController::class, 'marquerToutesLues']);
        // Route::delete('/notifications/{notificationId}', [AgenceNotificationController::class, 'supprimer']);

        // Gestion des tarifs de l'agence
        // Route::get('/tarifs', [AgenceTarifController::class, 'index']);
        // Route::post('/tarifs', [AgenceTarifController::class, 'store']);
        // Route::get('/tarifs/{tarif}', [AgenceTarifController::class, 'show']);
        // Route::put('/tarifs/{tarif}', [AgenceTarifController::class, 'update']);
        // Route::delete('/tarifs/{tarif}', [AgenceTarifController::class, 'destroy']);
        // Route::put('/tarifs/{tarif}/toggle-status', [AgenceTarifController::class, 'toggleStatus']);
    });

    // Routes tarifs
    /*Route::prefix('tarifs')->group(function () {
        Route::get('/index', [TarifController::class, 'index']);  // Lister tous les tarifs
        Route::post('/store', [TarifController::class, 'store']);  // Créer un nouveau tarif
        Route::get('/show/{tarif}', [TarifController::class, 'show']);  // Afficher un tarif spécifique
        Route::put('/update/{tarif}', [TarifController::class, 'update']);  // Mettre à jour un tarif spécifique
        Route::delete('/destroy/{tarif}', [TarifController::class, 'destroy']);  // Supprimer un tarif spécifique
    });*/

    // Routes admin
    Route::prefix('admin')->group(function () {
        // TODO: Controllers admin
    });
});
