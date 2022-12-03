<?php

namespace Modules\ImapMove\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

class ImapMoveServiceProvider extends ServiceProvider
{
    const ACTION_READ = 1;
    const ACTION_REMOVE = 2;
    const ACTION_MOVE = 3;

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        \Eventy::addAction('mailbox.connection_incoming.after_default_settings', function($mailbox) {
            echo \View::make('imapmove::partials/mailbox_settings', ['mailbox' => $mailbox])->render();
        }, 20, 1);

        \Eventy::addAction('mailbox.incoming_settings_before_save', function($mailbox, $request) {
            $meta = $mailbox->getMeta('imapmove') ?? [];    
            if (!empty($request->imapmove_action)) {
                $meta['action'] = (int)$request->imapmove_action;
            }
            $meta['folder'] = $request->imapmove_folder ?? '';
            $mailbox->setMeta('imapmove', $meta);
        }, 20, 2);

        \Eventy::addAction('fetch_emails.after_set_seen', function($message, $mailbox, $command) {
            $meta = $mailbox->getMeta('imapmove') ?? [];
            if (!empty($meta['action'])) {
                try {
                    if ($meta['action'] == \ImapMove::ACTION_MOVE) {
                        if ($meta['folder']) {
                            $move_function = 'moveToFolder';
                            if (!method_exists($message, $move_function)) {
                                $move_function = 'move';
                            }
                            // second parameter "expunge" does not remove the message.
                            if (!$message->$move_function(trim($meta['folder']))) {
                                $error_msg = 'Could not move the email to the following IMAP folder: '.$meta['folder'];
                                $command->error('['.date('Y-m-d H:i:s').'] '.$error_msg);
                                \Log::error('[Move or Remove IMAP Message Module] Mailbox: '.$mailbox->name.'; Message: '.$message->getSubject().'; '.$error_msg);
                            } else {
                                $message->delete();
                            }
                        } else {
                            $error_msg = 'Can not move the email to IMAP folder as IMAP folder name is not set.';
                            $command->error('['.date('Y-m-d H:i:s').'] '.$error_msg);
                            \Log::error('[Move or Remove IMAP Message Module] Mailbox: '.$mailbox->name.'; Message: '.$message->getSubject().'; '.$error_msg);
                        }
                    }
                    if ($meta['action'] == \ImapMove::ACTION_REMOVE) {
                        if (!$message->delete()) {
                            $error_msg = 'Could not delete the email.';
                            $command->error('['.date('Y-m-d H:i:s').'] '.$error_msg);
                            \Log::error('[Move or Remove IMAP Message Module] Mailbox: '.$mailbox->name.'; Message: '.$message->getSubject().'; '.$error_msg);
                        }
                    }
                } catch (\Exception $e) {
                    $error_msg = $e->getMessage();
                    $command->error('['.date('Y-m-d H:i:s').'] '.$error_msg);
                    \Log::error('[Move or Remove IMAP Message Module] Mailbox: '.$mailbox->name.'; Message: '.$message->getSubject().'; '.$error_msg);
                }
            }
        }, 20, 3);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('imapmove.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'imapmove'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/imapmove');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/imapmove';
        }, \Config::get('view.paths')), [$sourcePath]), 'imapmove');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     * @source https://github.com/sebastiaanluca/laravel-resource-flow/blob/develop/src/Modules/ModuleServiceProvider.php#L66
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
