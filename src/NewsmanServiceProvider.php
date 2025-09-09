<?php

namespace Newsman\LaravelNewsmanSmtp;

use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\MailManager;
use Newsman\LaravelNewsmanSmtp\Mail\Transport\NewsmanTransport;

class NewsmanServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 2a) log channel
        $logging = $this->app['config']->get('logging.channels');
        if (!isset($logging['newsman'])) {
            $logging['newsman'] = [
                'driver' => 'single',
                'path'   => storage_path('logs/newsman.log'),
                'level'  => 'debug',
            ];
            $this->app['config']->set('logging.channels', $logging);
        }

        // 2b) inject mailer "newsman" in config
        $this->app['config']->set('mail.mailers.newsman', ['transport' => 'newsman']);

        // 2c) optional: set global MAIL_FROM from config/newsman
        $this->app['config']->set('mail.from', [
            'address' => config('newsman.from_address'),
            'name'    => config('newsman.from_name'),
        ]);

        // 2d) register transport
        $this->app->make(MailManager::class)->extend('newsman', function ($config) {
            $cfg = config('newsman');

            $dispatcher = $this->app->bound(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class)
                ? $this->app->make(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class)
                : null;

            $logger = $this->app->bound(\Psr\Log\LoggerInterface::class)
                ? $this->app->make(\Psr\Log\LoggerInterface::class)
                : null;

            return new NewsmanTransport(
                apiKey: $cfg['api_key'],
                accountId: $cfg['account_id'],
                endpoint: $cfg['endpoint'],
                timeout: $cfg['http']['timeout'] ?? 15,
                retryTimes: $cfg['http']['retry']['times'] ?? 0,
                retrySleepMs: $cfg['http']['retry']['sleep'] ?? 0,
                dispatcher: $dispatcher,
                logger: $logger
            );
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Newsman\LaravelNewsmanSmtp\Console\SetupCommand::class,
                \Newsman\LaravelNewsmanSmtp\Console\TestMailCommand::class,
                \Newsman\LaravelNewsmanSmtp\Console\UninstallCommand::class,
            ]);
        }
    }



    
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/newsman.php', 'newsman');

        // OVERRIDE from storage
        $path = storage_path('app/newsman/credentials.php');
        if (is_file($path)) {
            $creds = @include $path; // ['api_key'=>..., 'account_id'=>..., 'from_address'=>..., 'from_name'=>..., 'endpoint'=>...]
            if (is_array($creds)) {
                foreach (['api_key','account_id','endpoint','from_address','from_name'] as $k) {
                    if (!empty($creds[$k])) {
                        config()->set("newsman.$k", $creds[$k]);
                    }
                }
            }
        }
    }


}
