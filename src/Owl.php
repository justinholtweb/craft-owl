<?php

declare(strict_types=1);

namespace justinholtweb\owl;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Elements;
use craft\services\ProjectConfig;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use justinholtweb\owl\elements\Event;
use justinholtweb\owl\models\Settings;
use justinholtweb\owl\services\Calendars;
use justinholtweb\owl\services\Events;
use justinholtweb\owl\services\Ics;
use justinholtweb\owl\services\Occurrences;
use justinholtweb\owl\services\Recurrence;
use justinholtweb\owl\web\twig\CraftVariableBehavior;
use yii\base\Event as YiiEvent;

/**
 * Owl — events & calendar plugin for Craft CMS 5.
 *
 * @method static Owl getInstance()
 * @method Settings getSettings()
 * @property-read Calendars $calendars
 * @property-read Events $events
 * @property-read Ics $ics
 * @property-read Occurrences $occurrences
 * @property-read Recurrence $recurrence
 */
class Owl extends Plugin
{
    public const EDITION_LITE = 'lite';
    public const EDITION_PRO = 'pro';

    public string $schemaVersion = '1.0.1';
    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;

    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
        ];
    }

    public static function config(): array
    {
        return [
            'components' => [
                'calendars' => Calendars::class,
                'events' => Events::class,
                'ics' => Ics::class,
                'occurrences' => Occurrences::class,
                'recurrence' => Recurrence::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();
        $this->registerProjectConfigHandlers();
    }

    /**
     * Whether the active edition includes Commerce ticketing and other Pro features.
     */
    public function isPro(): bool
    {
        return $this->is(self::EDITION_PRO);
    }

    /**
     * Whether Commerce is installed so the Pro ticketing layer can boot.
     */
    public function commerceAvailable(): bool
    {
        return $this->isPro()
            && Craft::$app->getPlugins()->isPluginInstalled('commerce');
    }

    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();
        $nav['label'] = Craft::t('owl', 'Owl');
        $nav['url'] = 'owl/events';
        $nav['subnav'] = [];

        $user = Craft::$app->getUser();

        if ($user->checkPermission('owl-manageEvents')) {
            $nav['subnav']['events'] = [
                'label' => Craft::t('owl', 'Events'),
                'url' => 'owl/events',
            ];
        }

        if ($user->checkPermission('owl-manageCalendars')) {
            $nav['subnav']['calendars'] = [
                'label' => Craft::t('owl', 'Calendars'),
                'url' => 'owl/calendars',
            ];
        }

        if ($user->getIsAdmin()) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('owl', 'Settings'),
                'url' => 'settings/plugins/owl',
            ];
        }

        return $nav;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('owl/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function registerProjectConfigHandlers(): void
    {
        $calendars = $this->calendars;

        Craft::$app->getProjectConfig()
            ->onAdd(Calendars::CONFIG_CALENDARS_KEY . '.{uid}', [$calendars, 'handleChangedCalendar'])
            ->onUpdate(Calendars::CONFIG_CALENDARS_KEY . '.{uid}', [$calendars, 'handleChangedCalendar'])
            ->onRemove(Calendars::CONFIG_CALENDARS_KEY . '.{uid}', [$calendars, 'handleDeletedCalendar']);

        YiiEvent::on(
            ProjectConfig::class,
            ProjectConfig::EVENT_REBUILD,
            function(RebuildConfigEvent $event) use ($calendars) {
                $event->config[Calendars::CONFIG_CALENDARS_KEY] = $calendars->rebuildProjectConfig();
            }
        );
    }

    private function attachEventHandlers(): void
    {
        // Register the Event element type.
        YiiEvent::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Event::class;
            }
        );

        // Control panel routes.
        YiiEvent::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['owl'] = 'owl/events/index';
                $event->rules['owl/events'] = 'owl/events/index';
                $event->rules['owl/events/new'] = 'owl/events/edit';
                $event->rules['owl/events/<eventId:\d+>'] = 'owl/events/edit';
                $event->rules['owl/calendars'] = 'owl/calendars/index';
                $event->rules['owl/calendars/new'] = 'owl/calendars/edit';
                $event->rules['owl/calendars/<calendarId:\d+>'] = 'owl/calendars/edit';
            }
        );

        // Front-end feeds (FullCalendar JSON + ICS).
        YiiEvent::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['owl/events.json'] = 'owl/feed/events';
                $event->rules['owl/calendar/<handle:{handle}>.ics'] = 'owl/feed/calendar';
                $event->rules['owl/event/<eventId:\d+>.ics'] = 'owl/feed/event';
            }
        );

        // Expose craft.owl.* in Twig.
        YiiEvent::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(YiiEvent $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->attachBehavior('owl', CraftVariableBehavior::class);
            }
        );

        // User-group permissions.
        YiiEvent::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Owl',
                    'permissions' => [
                        'owl-manageEvents' => ['label' => Craft::t('owl', 'Manage events')],
                        'owl-manageCalendars' => ['label' => Craft::t('owl', 'Manage calendars')],
                    ],
                ];
            }
        );

        // Commerce ticketing (Pro) boots lazily so the plugin degrades to calendar-only.
        if ($this->commerceAvailable()) {
            // Registered in Phase 6 — Ticket purchasable type.
        }
    }
}
