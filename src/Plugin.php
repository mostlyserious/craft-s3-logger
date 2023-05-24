<?php

namespace MostlySerious\S3Logger;

use Craft;
use yii\base\Event;
use craft\base\Model;
use craft\services\Plugins;
use craft\helpers\UrlHelper;
use craft\events\PluginEvent;
use craft\base\Plugin as BasePlugin;
use MostlySerious\S3Logger\Log\S3Target;
use MostlySerious\S3Logger\Models\Settings;

class Plugin extends BasePlugin
{
    public static $plugin;

    public bool $hasCpSettings = true;

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        if (self::$plugin->settings->getIsValid()) {
            Craft::$app->log->targets['s3'] = Craft::createObject([
                'class' => S3Target::class,
                'levels' => [ 'error', 'warning' ]
            ]);
        }

        Event::on(Plugins::class, Plugins::EVENT_AFTER_INSTALL_PLUGIN, [ $this, 'afterInstallPlugin' ]);
    }

    public function afterInstallPlugin(PluginEvent $event)
    {
        $isCpRequest = Craft::$app->getRequest()->isCpRequest;

        if ($event->plugin === $this && $isCpRequest) {
            Craft::$app->controller->redirect(UrlHelper::cpUrl('settings/plugins/s3-logger'))->send();
        }
    }

    public function getSettings(): ?Model
    {
        $settings = parent::getSettings();
        $config = Craft::$app->config->getConfigFromFile('s3-logger');

        foreach ($settings as $settingName => $settingValue) {
            $settingValueOverride = null;

            foreach ($config as $configName => $configValue) {
                if ($configName === $settingName) {
                    $settingValueOverride = $configValue;
                }
            }

            $settings->$settingName = $settingValueOverride ?? $settingValue;
        }

        return $settings;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('s3-logger/_settings', [
            'settings' => $this->getSettings()
        ]);
    }
}
