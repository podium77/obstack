<?php
namespace App\Service;

use App\Entity\Alert;
use App\Entity\RcaAnalysis;
use App\Enum\RcaStatus;
use App\RCA\PyRcaService;
use App\RCA\RcaResult;
use App\Repository\MetricSnapshotRepository;
use App\Repository\RcaAnalysisRepository;

/**
 * CORRECTIF : analyzeAlert() retournait un RcaResult (value object éphémère
 * défini dans App\RCA\RcaResult), alors que RcaController::analyze() passe
 * ce retour directement au template rca/analysis.html.twig, qui lit en
 * réalité les propriétés d'une entité App\Entity\RcaAnalysis
 * (probableCause, details, recommendations, metricsAtTrigger,
 * metricsUnits, id...). Les deux objets n'ont pas la même forme : le
 * template affichait donc des valeurs vides (Twig est permissif sur les
 * propriétés manquantes) et rien n'était jamais persisté, alors que
 * rca/index.html.twig et RcaAnalysisRepository::findByCompany() supposent
 * que ces analyses existent en base.
 *
 * Ce service convertit maintenant explicitement le RcaResult (retour brut
 * de l'appel HTTP à PyRcaService) en une entité RcaAnalysis persistée, et
 * c'est cette entité qui est retournée au contrôleur.
 */
class RcaService
{
    public function __construct(
        private readonly PyRcaService $pyRcaService,
        private readonly MetricSnapshotRepository $snapshotRepo,
        private readonly RcaAnalysisRepository $rcaAnalysisRepo,
    ) {}

    public function analyzeAlert(Alert $alert): RcaAnalysis
    {
        $company = $alert->getApplication()->getEnvironment()->getCompany();
        $snapshots = $this->snapshotRepo->findHistoryForApp($alert->getApplication(), 24);

        $result = $this->pyRcaService->analyzeAlert($alert, $snapshots, $company);

        $analysis = $this->buildAnalysis($alert, $result);

        // RcaAnalysisRepository::save() existait déjà (persist + flush
        // optionnel) mais n'était jusqu'ici jamais appelé pour ce flux.
        $this->rcaAnalysisRepo->save($analysis, flush: true);

        return $analysis;
    }

    private function buildAnalysis(Alert $alert, RcaResult $result): RcaAnalysis
    {
        $analysis = new RcaAnalysis();
        $analysis->setAlert($alert);

        if (!$result->success) {
            $analysis->setStatus(RcaStatus::FAILED);
            $analysis->setProbableCause('Analyse RCA indisponible.');
            $analysis->setDetails(
                $result->disabled
                    ? "L'analyse RCA n'est pas activée pour cette entreprise."
                    : ($result->error ?? 'Erreur inconnue lors de l\'appel au backend RCA.')
            );
            $analysis->setRecommendations([
                'Vérifier la configuration RCA (URL de l\'API, clé API) dans les paramètres de l\'entreprise.',
            ]);

            return $analysis;
        }

        $analysis->setStatus(RcaStatus::COMPLETED);
        $analysis->setProbableCause($this->summarizeProbableCause($alert, $result));
        $analysis->setDetails($result->explanation ?? 'Aucune explication fournie par le backend RCA.');
        $analysis->setRecommendations($this->buildRecommendations($result));

        [$metricsAtTrigger, $metricsUnits] = $this->buildMetricsAtTrigger($alert, $result);
        $analysis->setMetricsAtTrigger($metricsAtTrigger);
        $analysis->setMetricsUnits($metricsUnits);

        if ($alert->getMetric() !== null && $alert->getThreshold() !== null) {
            $analysis->setThresholds([$alert->getMetric() => $alert->getThreshold()]);
        }

        $analysis->setTimeline([
            [
                'timestamp'   => $alert->getCreatedAt(),
                'title'       => 'Alerte déclenchée',
                'description' => sprintf(
                    'Alerte « %s » (sévérité %s) sur %s.',
                    $alert->getTitle(),
                    $alert->getSeverity()->value,
                    $alert->getApplication()->getName(),
                ),
            ],
            [
                'timestamp'   => $result->analyzedAt ?? new \DateTimeImmutable(),
                'title'       => 'Analyse RCA terminée',
                'description' => sprintf(
                    'Backend : %s. Confiance : %s.',
                    $result->model ?? 'inconnu',
                    $result->confidence !== null ? round($result->confidence * 100) . ' %' : 'n/a',
                ),
            ],
        ]);

        return $analysis;
    }

    /**
     * Construit une phrase courte à partir de la cause racine la plus
     * sévère (RcaResult::getTopRootCause(), déjà défini sur le value
     * object mais jamais utilisé jusqu'ici).
     */
    private function summarizeProbableCause(Alert $alert, RcaResult $result): string
    {
        $top = $result->getTopRootCause();

        if (!$top) {
            return $result->explanation ?? 'Cause racine non déterminée par l\'analyse RCA.';
        }

        return sprintf(
            'Anomalie sur « %s » (composant : %s, sévérité : %s)',
            $top['metric'] ?? 'métrique inconnue',
            $top['component'] ?? $alert->getApplication()->getName(),
            $top['severity'] ?? 'warning',
        );
    }

    /**
     * Génère des recommandations à partir des causes racines retournées
     * par le backend RCA. Reste un heuristique simple côté PHP : si le
     * backend RCA externe expose un jour son propre champ
     * "recommendations", le préférer à cette génération générique.
     */
    private function buildRecommendations(RcaResult $result): array
    {
        $recommendations = [];

        foreach ($result->rootCauses as $cause) {
            $metric   = $cause['metric'] ?? 'métrique inconnue';
            $severity = $cause['severity'] ?? 'warning';

            $recommendations[] = $severity === 'critical'
                ? sprintf('Vérifier immédiatement l\'état de « %s » : interruption probable du service.', $metric)
                : sprintf(
                    'Surveiller la métrique « %s » (valeur %s, moyenne habituelle %s).',
                    $metric,
                    $cause['value'] ?? '?',
                    $cause['baseline_mean'] ?? '?',
                );
        }

        return $recommendations ?: ['Aucune recommandation automatique disponible — investigation manuelle conseillée.'];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function buildMetricsAtTrigger(Alert $alert, RcaResult $result): array
    {
        $metricsAtTrigger = [];
        $metricsUnits     = [];

        foreach ($result->rootCauses as $cause) {
            if (empty($cause['metric'])) {
                continue;
            }
            $metricsAtTrigger[$cause['metric']] = $cause['value'] ?? null;
            $metricsUnits[$cause['metric']]     = $this->guessUnit($cause['metric']);
        }

        // Complète avec la métrique propre à l'alerte si le backend RCA
        // ne l'a pas elle-même classée comme anomalie.
        if ($alert->getMetric() && !isset($metricsAtTrigger[$alert->getMetric()])) {
            $metricsAtTrigger[$alert->getMetric()] = $alert->getMetricValue();
            $metricsUnits[$alert->getMetric()]     = $this->guessUnit($alert->getMetric());
        }

        return [$metricsAtTrigger, $metricsUnits];
    }

    private function guessUnit(string $metric): string
    {
        return match (true) {
            str_contains($metric, 'percent')     => '%',
            str_contains($metric, 'latency')     => 'ms',
            str_contains($metric, 'connections') => 'conn.',
            default => '',
        };
    }
}
