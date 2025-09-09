<?php

namespace Newsman\LaravelNewsmanSmtp\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class UninstallCommand extends Command
{
    protected $signature = 'newsman:uninstall {--keep : Keep files from storage/app/newsman instead of deleting}';
    protected $description = 'Remove completely NewsMAN config and use default Laravel (smtp).';

    public function handle(Filesystem $files): int
    {
        $dir = storage_path('app/newsman');
        $mailerFile = $dir.'/mailer.php';
        $credsFile  = $dir.'/credentials.php';

        // 1) Reset default mailer to smtp
        $defaultMailer = 'smtp';
        $content = <<<PHP
<?php
// Generat de `php artisan newsman:uninstall`
return [
    'default' => '{$defaultMailer}',
    'failover_order' => ['smtp','log'],
    'failover_enabled' => false,
];
PHP;

        if (!is_dir($dir)) {
            $files->makeDirectory($dir, 0755, true);
        }
        $files->put($mailerFile, $content);

        $this->info("Mailer has been reset to: {$defaultMailer}");

        // 2) Remove credentials.php (unless using --keep)
        if (!$this->option('keep') && $files->exists($credsFile)) {
            $files->delete($credsFile);
            $this->info("File credentials.php deleted.");
        } elseif ($this->option('keep')) {
            $this->warn("Am păstrat fișierul credentials.php (opțiunea --keep).");
        }

        // Cleaning config cache
        $this->callSilent('config:clear');
        $this->callSilent('cache:clear');

        $this->info("Config has been reset. Now you can run `composer remove newsman/laravel-newsman-smtp` without errors.");

        return self::SUCCESS;
    }
}
