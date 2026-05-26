<?php
namespace App\Agent;

use Psr\Log\LoggerInterface;

class SshConnection
{
    public function __construct(
        private readonly mixed  $connection,
        private readonly string $user,
        private readonly string $host,
        private readonly LoggerInterface $logger,
    ) {}

    public function exec(string $command, bool $ignoreError = false): string
    {
        $stream = @ssh2_exec($this->connection, $command);
        if (!$stream) {
            throw new \RuntimeException("Impossible d'exécuter: {$command}");
        }
        stream_set_blocking($stream, true);
        $stderr = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        stream_set_blocking($stderr, true);
        $output = trim(stream_get_contents($stream) ?: '');
        $err    = trim(stream_get_contents($stderr) ?: '');
        fclose($stream);
        if ($err && !$ignoreError) {
            $this->logger->debug("SSH stderr [{$this->host}]: {$err}");
        }
        return $output;
    }

    public function sudo(string $command, bool $ignoreError = false): string
    {
        return $this->exec("sudo -n {$command}", $ignoreError);
    }

    public function upload(string $localPath, string $remotePath): void
    {
        if (!@ssh2_scp_send($this->connection, $localPath, $remotePath, 0644)) {
            throw new \RuntimeException("SCP envoi échoué: {$localPath} → {$remotePath}");
        }
    }

    public function isAlive(): bool
    {
        try {
            return $this->exec('echo alive', true) === 'alive';
        } catch (\Throwable) {
            return false;
        }
    }

    public function getHost(): string { return $this->host; }
    public function getUser(): string { return $this->user; }
}
