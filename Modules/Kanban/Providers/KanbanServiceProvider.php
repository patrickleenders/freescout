<?php

namespace Modules\Kanban\Providers;

use App\Conversation;
use App\Customer;
use App\Mailbox;
use Modules\Kanban\Entities\KnCard;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

// Module alias.
define('KANBAN_MODULE', 'kanban');

class KanbanServiceProvider extends ServiceProvider
{
    const CARDS_PER_COLUMN = 10;
    const SELECTS_PER_UNION = 25;
    const ALL_MAILBOXES = -1;

    // Params
    const SORT_MANUAL = 'manual';
    const SORT_ACTIVE = 'last_reply_active';
    const SORT_LAST_REPLY_NEW = 'last_reply_new';
    const SORT_LAST_REPLY_OLD = 'last_reply_old';
    const SORT_CREATED_NEW = 'created_new';
    const SORT_CREATED_OLD = 'created_old';
    // For sorting closed conversations.
    const SORT_CLOSED_NEW = 'closed_new';

    const GROUP_BY_COLUMN = 'kn_column_id';
    const GROUP_BY_ASSIGNEE = 'user_id';
    const GROUP_BY_STATUS = 'status';
    const GROUP_BY_TAG = 'tag';

    const FILTER_BY_STATUS = 'status';
    const FILTER_BY_ASSIGNEE = 'user_id';
    const FILTER_BY_STATE = 'state';
    const FILTER_BY_TAG = 'tag';
    const FILTER_BY_COLUMN = 'kn_column_id';
    const FILTER_BY_CF = 'custom_field';

    const AUTOMATE_BY_ASSIGNEE = 'user_id';
    const AUTOMATE_BY_STATUS = 'status';
    const AUTOMATE_BY_TAG = 'tag';

    // ID of the patterns for columns and swimlanes.
    const PATTERN_ID = '-1';

    // Text separating filters values.
    const FILTERS_SEPARATOR = '|';

    // Custom flag for search.
    const SEARCH_CUSTOM = 'kn';

    // Kanban customer channel id.
    const CUSTOMER_CHANNEL = 100;

    public static $use_unions = null;

    public static $default_filters = [
        \Kanban::FILTER_BY_STATUS => [Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING, Conversation::STATUS_AWAITING_CUSTOMER, Conversation::STATUS_AWAITING_SUPPLIER, Conversation::STATUS_AWAITING_TODO],
        \Kanban::FILTER_BY_ASSIGNEE => [],
        \Kanban::FILTER_BY_STATE => [Conversation::STATE_PUBLISHED],
        \Kanban::FILTER_BY_TAG => [],
        \Kanban::FILTER_BY_COLUMN => [],
        \Kanban::FILTER_BY_CF => [],
    ];

    public static $kn_customer = null;

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
        \Eventy::addFilter('stylesheets', function($styles) {
            $styles[] = \Module::getPublicPath(KANBAN_MODULE).'/css/module.css';
            return $styles;
        });
        
        // Add module's JS file to the application layout.
        \Eventy::addFilter('javascripts', function($javascripts) {
            $javascripts[] = \Module::getPublicPath(KANBAN_MODULE).'/js/laroute.js';

            if (!preg_grep("/html5sortable\.js$/", $javascripts)) {
                $javascripts[] = \Module::getPublicPath(KANBAN_MODULE).'/js/html5sortable.js';
            }

             $javascripts[] = \Module::getPublicPath(KANBAN_MODULE).'/js/module.js';
            return $javascripts;
        });

        // Add item to the mailbox menu
        \Eventy::addAction('mailboxes.settings.menu', function($mailbox) {
            ?>
                <li><a href="<?php echo route('kanban.show', ['kn' => ['mailbox_id'=>$mailbox->id]]) ?>"><i class="glyphicon glyphicon-th"></i> <?php echo __('Kanban View') ?></a></li>
            <?php
        }, 11);

        // Determine whether the user can view mailboxes menu.
        \Eventy::addFilter('user.can_view_mailbox_menu', function($value, $user) {
            return $value || true;
        }, 20, 2);

        // Add item to the mailbox menu
        \Eventy::addAction('menu.manage.after_mailboxes', function($mailbox) {
            echo \View::make('kanban::partials/main_menu_item', [])->render();
        }, 15);

        // Select main menu item.
        \Eventy::addFilter('menu.selected', function($menu) {
            $menu['manage']['kanban'] = [
                'kanban.show'
            ];

            return $menu;
        });

        // Add item to the mailbox menu
        \Eventy::addAction('mailboxes.settings.menu', function($mailbox) {
            //echo \View::make('workflows::partials/settings_menu', ['mailbox' => $mailbox])->render();
        }, 25);

        // Pick conversation.
        \Eventy::addAction('conversations_table.preview_prepend', function($conversation, $params) {
            $request = request();
            if (!empty($request->f) && !empty($request->f['custom']) && $request->f['custom'] == self::SEARCH_CUSTOM) {
            ?><button type="button" class="btn btn-default btn-xs kn-btn-pick" onclick="knPickConv(<?php echo $conversation->number ?>, this, event); return false;"><?php echo __('Pick Conversation').' #'. $conversation->number ?></button> <?php
            }
        }, 20, 2);

        // \Eventy::addAction('conversations_table.col_before_conv_number', function() {
        //     $request = request();
        //     if (!empty($request->f) && !empty($request->f['custom']) && $request->f['custom'] == self::SEARCH_CUSTOM) {
        //         $request->request->remove('x_embed');
        //     }
        // });
        
        // Show block in conversation.
        \Eventy::addAction('conversation.after_subject_block', function($conversation, $mailbox) {
            $cards = KnCard::where('conversation_id', $conversation->id)
                // Better filer in PHP.
                //->groupBy('kn_board_id');
                ->get();
            $board_ids = [];
            if (count($cards)) {
                $first = true;
                $user = auth()->user();
                ?>
                    <div class="conv-top-block clearfix">
                        <i class="glyphicon glyphicon-th text-help"></i> 
                        <?php foreach ($cards as $i => $card): ?>
                            <?php
                                if (in_array($card->kn_board_id, $board_ids)) {
                                    continue;
                                }
                                $board_ids[] = $card->kn_board_id;
                                $board = $card->kn_board;
                                // Check permissions.
                                if (!$board || !$board->userCanView($user)) {
                                    continue;
                                }
                            ?>
                            <?php if (!$first): ?> | <?php endif ?><a href="<?php echo $board->url() ?>" title="<?php echo __('Kanban Board') ?>" data-toggle="tooltip"><?php echo $board->name ?></a>
                            <?php
                                $first = false;
                            ?>
                        <?php endforeach ?>
                    </div>
                <?php
            }
        }, 12, 2);

        \Eventy::addAction('conversation.prepend_action_buttons', function($conversation, $mailbox) {
            ?>
                <li><a href="<?php echo route('kanban.ajax_html', ['action' => 'new_card', 'conversation_number' => $conversation->number, 't' => time()]) ?>" data-trigger="modal" data-modal-no-footer="true" data-modal-title="<?php echo __('New Card') ?>" data-modal-size="lg" data-modal-on-show="knInitCardModal"><i class="glyphicon glyphicon-file"></i> <?php echo __("Add to Board") ?></a></li>
            <?php
        }, 40, 2);
    }

    public static function useUnions()
    {
        if (self::$use_unions === null) {
            // https://github.com/freescout-helpdesk/freescout/issues/1658
            if (\Helper::isPgSql()) {
                self::$use_unions = false;
            } else {
                self::$use_unions = true;
            }
        }

        return self::$use_unions;
    }

    public static function parseSelectedCf($item)
    {
        return json_decode($item, true);

        // preg_match("/([^\\".self::FILTERS_VALUES_SEPARATOR."]+)\\"+self::FILTERS_VALUES_SEPARATOR."([^\\".self::FILTERS_VALUES_SEPARATOR."]+)/", $item, $m);

        // if (isset($m[1]) && isset($m[2])) {
        //     return [
        //         'id' => $m[1],
        //         'value' => $m[2],
        //     ];
        // } else {
        //     return [
        //         'id' => '',
        //         'value' => '',
        //     ];
        // }
    }

    public static function getGlobalMailbox()
    {
        $mailbox = new Mailbox();
        $mailbox->id = \Kanban::ALL_MAILBOXES;

        return $mailbox;
    }

    public static function url($params)
    {
        return route('kanban.show', ['kn' => $params]);
    }

    /**
     * Get or create Kanban customer.
     */
    public static function getCustomer()
    {
        if (!empty(self::$kn_customer)) {
            return self::$kn_customer;
        }
        self::$kn_customer = Customer::where('channel', self::CUSTOMER_CHANNEL)->first();

        if (!self::$kn_customer) {
            $customer_data = [
                'channel' => self::CUSTOMER_CHANNEL,
                'first_name' => 'Kanban',
                'last_name' => '',
            ];
            self::$kn_customer = Customer::createWithoutEmail($customer_data);
        }

        return self::$kn_customer;
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
            __DIR__.'/../Config/config.php' => config_path('kanban.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'kanban'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/kanban');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/kanban';
        }, \Config::get('view.paths')), [$sourcePath]), 'kanban');
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
