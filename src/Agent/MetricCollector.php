<?php
namespace App\Agent;

use App\Entity\Application;
use App\Entity\MetricSnapshot;
use App\Enum\AlertSeverity;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MetricCollector
{
    public function __construct(
        private readonly SshClient           $ssh,
        private readonly HttpClientInterface  $httpClient,
        private readonly LoggerInterface      $logger,
    ) {}

    public function collect(Application $app): MetricSnapshot
    {
        $snap = new MetricSnapshot();
        $snap->setApplication($app);

        try {
            $conn = $this->ssh->connect($app);

            // CPU
            $cpu = $conn->exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'");
            $snap->setCpuPercent((float)$cpu);

            // Mémoire
            $mem = $conn->exec("free -m | grep Mem");
            $parts = preg_split('/\s+/', trim($mem));
            if (count($parts) >= 3) {
                $total = (int)$parts[1];
                $used  = (int)$parts[2];
                $snap->setMemoryTotalMb($total);
                $snap->setMemoryUsedMb($used);
                $snap->setMemoryPercent($total > 0 ? round($used / $total * 100, 1) : 0);
            }

            // Disques
            $df = $conn->exec("df -hP | grep -v 'tmpfs\\|udev\\|Filesystem'");
            $diskStats = [];
            foreach (explode("\n", trim($df)) as $line) {
                $p = preg_split('/\s+/', trim($line));
                if (count($p) >= 6) {
                    $diskStats[$p[5]] = [
                        'filesystem'   => $p[0],
                        'size'         => $p[1],
                        'used'         => $p[2],
                        'used_percent' => (int)rtrim($p[4], '%'),
                        'mountpoint'   => $p[5],
                    ];
                }
            }
            $snap->setDiskStats($diskStats);

            // Load average
            $load = $conn->exec("cat /proc/loadavg");
            $lp   = explode(' ', trim($load));
            $snap->setLoadAverage([(float)($lp[0] ?? 0), (float)($lp[1] ?? 0), (float)($lp[2] ?? 0)]);

            // Uptime
            $snap->setUptimeSeconds((int)$conn->exec("awk '{print int(\$1)}' /proc/uptime"));

            // Processus
            $snap->setProcessCount((int)$conn->exec("ps aux | wc -l") - 1);

            // Connexions
            $snap->setActiveConnections((int)$conn->exec("ss -tun | grep ESTAB | wc -l", true));

            // Statut services
            $tomcatSvc = $app->getTomcatConfig()['service_name'] ?? 'tomcat9';
            $snap->setTomcatStatus(
                trim($conn->sudo("systemctl is-active {$tomcatSvc}", true)) === 'active' ? 'running' : 'stopped'
            );

            $dbSvc = $app->getDbConfig()['service_name'] ?? ($app->getDbType()?->getDefaultServiceName() ?? 'postgresql');
            $snap->setDbStatus(
                trim($conn->sudo("systemctl is-active {$dbSvc}", true)) === 'active' ? 'running' : 'stopped'
            );

            // Latences
            $snap->setLatencies($this->measureLatencies($app, $conn));

            // Goulots d'étranglement
            $snap->setBottlenecks($this->detectBottlenecks($snap, $app, $conn));

            // Sévérité globale
            $snap->setSeverity($this->calculateSeverity($snap, $app));
            $snap->setCollectionSuccess(true);

        } catch (\Throwable $e) {
            $this->logger->error("Collecte échouée pour {$app->getName()}: {$e->getMessage()}");
            $snap->setCollectionSuccess(false);
            $snap->setCollectionError($e->getMessage());
            $snap->setSeverity(AlertSeverity::ERROR);
        }

        return $snap;
    }

    private function measureLatencies(Application $app, SshConnection $conn): array
    {
        $latencies = [];

        // Ping LAN
        $p = $conn->exec("ping -c 2 -W 2 {$app->getHostAddress()} 2>/dev/null | tail -1 | awk -F'/' '{print $5}'", true);
        $latencies['internal_ms'] = is_numeric(trim($p)) ? (float)trim($p) : null;

        // Internet
        $pi = $conn->exec("ping -c 2 -W 3 8.8.8.8 2>/dev/null | tail -1 | awk -F'/' '{print $5}'", true);
        $latencies['internet_ms'] = is_numeric(trim($pi)) ? (float)trim($pi) : null;

        // DNS
        $start = microtime(true);
        $conn->exec("dig +short google.com @8.8.8.8 2>/dev/null | head -1", true);
        $latencies['dns_ms'] = round((microtime(true) - $start) * 1000, 1);

        // BDD TCP
        $dbPort = $app->getDbType()?->getDefaultPort() ?? 5432;
        $dbHost = $app->getDbConfig()['db_host'] ?? '127.0.0.1';
        $tcp    = $conn->exec(
            "bash -c 'time echo > /dev/tcp/{$dbHost}/{$dbPort}' 2>&1 | grep real | awk '{print \$2}'",
            true
        );
        if (preg_match('/(\d+)m([\d.]+)s/', $tcp, $m)) {
            $latencies['tomcat_to_db_ms'] = ($m[1] * 60 + (float)$m[2]) * 1000;
        } else {
            $latencies['tomcat_to_db_ms'] = null;
        }

        // HTTP applicatif
        if ($app->getHealthUrl()) {
            try {
                $start    = microtime(true);
                $response = $this->httpClient->request('GET', $app->getHealthUrl(), ['timeout' => 5, 'verify_peer' => false]);
                $response->getStatusCode();
                $latencies['http_ms'] = round((microtime(true) - $start) * 1000, 1);
            } catch (\Throwable) {
                $latencies['http_ms'] = null;
            }
        }

        return $latencies;
    }

    private function detectBottlenecks(MetricSnapshot $snap, Application $app, SshConnection $conn): array
    {
        $bottlenecks = [];
        $t           = $app->getThresholds();

        if ($snap->getCpuPercent() >= ($t['cpu_warning'] ?? 75)) {
            $top = $conn->exec("ps aux --sort=-%cpu | head -4 | awk 'NR>1 {print \$11\" \"\$3\"%\"}'");
            $bottlenecks['cpu_high'] = "CPU à {$snap->getCpuPercent()}% — Top: " . str_replace("\n", ', ', $top);
        }
        if ($snap->getMemoryPercent() >= ($t['memory_warning'] ?? 70)) {
            $swap = $conn->exec("free -m | grep Swap | awk '{print \$3\"/\"\$2\" MB\"}'");
            $bottlenecks['memory_high'] = "Mémoire à {$snap->getMemoryPercent()}% — Swap: {$swap}";
        }
        foreach ($snap->getDiskStats() as $mount => $stat) {
            if ($stat['used_percent'] >= ($t['disk_warning'] ?? 75)) {
                $bottlenecks["disk_{$mount}"] = "Disque {$mount} à {$stat['used_percent']}%";
            }
        }
        if ($snap->getTomcatStatus() !== 'running') {
            $bottlenecks['tomcat_down'] = "Tomcat est arrêté";
        }
        if ($snap->getDbStatus() !== 'running') {
            $bottlenecks['db_down'] = "Base de données arrêtée";
        }
        $dbMs = $snap->getLatencies()['tomcat_to_db_ms'] ?? null;
        if ($dbMs !== null && $dbMs >= ($t['latency_warning'] ?? 100)) {
            $bottlenecks['db_latency'] = "Latence BDD élevée: {$dbMs}ms";
        }
        $cpuCount = (int)$conn->exec("nproc");
        $load1    = $snap->getLoadAverage()[0] ?? 0;
        if ($cpuCount > 0 && $load1 > $cpuCount * 1.5) {
            $bottlenecks['load_high'] = "Charge système: {$load1} pour {$cpuCount} CPU";
        }

        return $bottlenecks;
    }

    private function calculateSeverity(MetricSnapshot $snap, Application $app): AlertSeverity
    {
        $t   = $app->getThresholds();
        $max = 0;

        foreach ([
            [$snap->getCpuPercent(),    $t['cpu_critical'] ?? 90,    $t['cpu_warning'] ?? 75],
            [$snap->getMemoryPercent(), $t['memory_critical'] ?? 85,  $t['memory_warning'] ?? 70],
            [$snap->getMaxDiskPercent(),$t['disk_critical'] ?? 90,    $t['disk_warning'] ?? 75],
        ] as [$val, $crit, $warn]) {
            if ($val === null) continue;
            if ($val >= $crit) $max = max($max, AlertSeverity::CRITICAL->getWeight());
            elseif ($val >= $warn) $max = max($max, AlertSeverity::WARNING->getWeight());
        }

        if ($snap->getTomcatStatus() !== 'running' || $snap->getDbStatus() !== 'running') {
            $max = max($max, AlertSeverity::CRITICAL->getWeight());
        }

        return AlertSeverity::fromWeight($max);
    }
}
