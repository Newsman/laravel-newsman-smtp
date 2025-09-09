<?php

namespace Newsman\LaravelNewsmanSmtp\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class SetupCommand extends Command
{
    protected $signature = 'newsman:setup {--endpoint=}';
    protected $description = 'Publică config și setează account_id/api_key pentru NewsMAN (fără .env)';

    public function handle(Filesystem $files): int
    {
            // src/Console/SetupCommand.php (only handling)

        $accountId = $this->ask('NEWSMAN Account ID');
        $apiKey    = $this->secret('NEWSMAN API key (input ascuns)');
        $endpoint  = $this->option('endpoint') ?: config('newsman.endpoint');
        $fromAddr  = $this->ask('From email address', config('newsman.from_address'));
        $fromName  = $this->ask('From name', config('newsman.from_name'));

        $dir = storage_path('app/newsman');
        $path = $dir.'/credentials.php';
        if (! $files->exists($dir)) {
            $files->makeDirectory($dir, 0755, true);
        }

        $content = <<<PHP
        <?php
        // Generat de `php artisan newsman:setup`
        return [
            'account_id'   => '{$this->escape($accountId)}',
            'api_key'      => '{$this->escape($apiKey)}',
            'endpoint'     => '{$this->escape($endpoint)}',
            'from_address' => '{$this->escape($fromAddr)}',
            'from_name'    => '{$this->escape($fromName)}',
        ];
        PHP;

        $files->put($path, $content);
        $this->info("Salvat credențiale + from în: storage/app/newsman/credentials.php");
        $this->call('config:clear');

        return self::SUCCESS;

    }

    private function escape(string $v): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $v);
    }
}
