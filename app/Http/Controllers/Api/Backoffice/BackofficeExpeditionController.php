<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Expedition;
use Illuminate\Http\Request;
use App\Enums\ExpeditionStatus;
use Illuminate\Support\Facades\Log;
use Exception;

class BackofficeExpeditionController extends Controller
{
    /**
     * Liste les expéditions selon le mode :
     * - mode=depart  : expéditions dont le pays_depart correspond au pays du backoffice (à contrôler avant transit)
     * - mode=arrivee : expéditions dont le pays_destination correspond au pays du backoffice (à réceptionner venant de l'étranger)
     */
    public function listExpeditions(Request $request)
    {
        try {
            $user = $request->user();

            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $mode = $request->get('mode', 'depart'); // 'depart' ou 'arrivee'

            $query = Expedition::query();

            // Filtrage directionnel par pays pour le backoffice
            if ($user->type === UserType::BACKOFFICE) {
                if (!$user->backoffice) {
                    return response()->json(['success' => false, 'message' => 'Aucun backoffice rattaché.'], 403);
                }

                $country = $user->backoffice->pays;

                if ($mode === 'arrivee') {
                    // Expéditions en transit international à destination du pays du backoffice
                    $query->where('pays_destination', $country);
                } else {
                    // Par défaut : mode "depart" — expéditions partant du pays du backoffice
                    $query->where('pays_depart', $country);
                }
            }

            if ($request->filled('status')) {
                $query->where('statut_expedition', $request->get('status'));
            }

            if ($request->filled('date_debut')) {
                $query->whereDate('created_at', '>=', $request->get('date_debut'));
            }
            if ($request->filled('date_fin')) {
                $query->whereDate('created_at', '<=', $request->get('date_fin'));
            }

            // Sélectionner uniquement les colonnes nécessaires pour le backoffice
            $expeditions = $query->select([
                'id',
                'agence_id',
                'reference',
                'type_expedition',
                'statut_expedition',
                'statut_paiement',
                'pays_depart',
                'expediteur',
                'pays_destination',
                'destinataire',
                'montant_base',
                'montant_prestation',
                'montant_expedition',
                'frais_emballage',
                'frais_annexes',
                'code_suivi_expedition',
                'date_deplacement_entrepot',
                'date_expedition_depart',
                'date_expedition_arrivee',
            ])
                ->with([
                    'agence:id,nom_agence,code_agence,telephone,adresse,ville,commune,pays',
                    'colis:id,expedition_id,code_colis,designation,poids,articles,montant_colis_total,is_controlled,is_received_by_backoffice,received_at_backoffice'
                ])
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $expeditions
            ]);

        } catch (Exception $e) {
            Log::error('Erreur liste expéditions backoffice : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mise en transit d'une expédition : renseigne les frais annexes et le code de suivi.
     */
    public function transitExpedition(Request $request, string $id)
    {
        try {
            $user = $request->user();

            if (!in_array($user->type, [UserType::BACKOFFICE, UserType::ADMIN])) {
                return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 403);
            }

            $expedition = Expedition::with('agence')->find($id);

            if (!$expedition) {
                return response()->json(['success' => false, 'message' => 'Expédition introuvable.'], 404);
            }

            // Vérification du pays pour le backoffice (basé sur pays_depart)
            if ($user->type === UserType::BACKOFFICE) {
                if (!$user->backoffice || $expedition->pays_depart !== $user->backoffice->pays) {
                    return response()->json(['success' => false, 'message' => 'Accès non autorisé à cette expédition.'], 403);
                }
            }

            $request->validate([
                'frais_annexes' => ['required', 'numeric', 'min:0'],
                'code_suivi_expedition' => ['nullable', 'string', 'max:255'],
            ]);

            $expedition->update([
                'frais_annexes' => $request->frais_annexes,
                'code_suivi_expedition' => $request->code_suivi_expedition,
                'statut_expedition' => ExpeditionStatus::DEPART_EXPEDITION_SUCCES,
                'date_expedition_depart' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Informations de transit mises à jour avec succès.',
                'expedition' => $expedition
            ]);

        } catch (Exception $e) {
            Log::error('Erreur mise en transit backoffice : ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur serveur.', 'error' => $e->getMessage()], 500);
        }
    }
}
