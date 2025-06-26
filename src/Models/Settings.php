<?php

namespace MostlySerious\S3Logger\Models;

use craft\base\Model;
use craft\helpers\App;
use yii\validators\NumberValidator;
use craft\validators\StringValidator;
use craft\behaviors\EnvAttributeParserBehavior;

/**
 * Class Settings
 *
 * This class represents the settings for the S3 Logger. It includes the directory for logs,
 * the maximum size for log rotation, AWS region, bucket name, access key ID, and secret access key.
 *
 * @package MostlySerious\S3Logger\Models
 */
class Settings extends Model
{
    /**
     * @var string The directory for logs.
     */
    public bool $enabled = true;

    /**
     * @var string The directory for logs.
     */
    public string $dir = '_logs';

    /**
     * @var int The maximum number of days logs are retained.
     */
    public int $retentionByDay = 90;

    /**
     * @var int The maximum size in bytes for log rotation.
     */
    public int $rotateLogsAtBytes = 10000000;

    /**
     * @var string The AWS region for the S3 bucket.
     */
    public string $region = '';

    /**
     * @var string The name of the S3 bucket.
     */
    public string $bucket = '';

    /**
     * @var string The AWS access key ID.
     */
    public string $accessKeyId = '';

    /**
     * @var string The AWS secret access key.
     */
    public string $secretAccessKey = '';

    /**
     * Check if all the settings are valid.
     *
     * @return bool True if all settings are valid, false otherwise.
     */
    public function getIsValid(): string
    {
        return $this->getEnabled()
            && $this->getRotateLogsAtBytes()
            && $this->getRegion()
            && $this->getBucket()
            && $this->getAccessKeyId()
            && $this->getSecretAccessKey();
    }

    /**
     * Get the directory for logs.
     *
     * @return string The directory for logs.
     */
    public function getEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    /**
     * Get the directory for logs.
     *
     * @return string The directory for logs.
     */
    public function getDir(): ?string
    {
        return App::parseEnv($this->dir);
    }

    /**
     * Get the maximum size in bytes for log rotation.
     *
     * @return int The maximum size in bytes for log rotation.
     */
    public function getRotateLogsAtBytes(): int
    {
        return (int) App::parseEnv($this->rotateLogsAtBytes);
    }

    /**
     * Get the maximum number of days logs are retained.
     *
     * @return int The maximum number of days logs are retained.
     */
    public function getRetentionByDay(): int
    {
        return (int) App::parseEnv($this->retentionByDay);
    }

    /**
     * Get the AWS region.
     *
     * @return string The AWS region for the S3 bucket.
     */
    public function getRegion(): ?string
    {
        return App::parseEnv($this->region);
    }

    /**
     * Get the S3 bucket name.
     *
     * @return string The name of the S3 bucket.
     */
    public function getBucket(): ?string
    {
        return App::parseEnv($this->bucket);
    }

    /**
     * Get the AWS access key ID.
     *
     * @return string The AWS access key ID.
     */
    public function getAccessKeyId(): ?string
    {
        return App::parseEnv($this->accessKeyId);
    }

    /**
     * Get the AWS secret access key.
     *
     * @return string The AWS secret access key.
     */
    public function getSecretAccessKey(): ?string
    {
        return App::parseEnv($this->secretAccessKey);
    }

    /**
     * Define behaviors for the model.
     *
     * @return array The behaviors for the model.
     */
    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [ 'dir', 'rotateLogsAtBytes', 'retentionByDay', 'region', 'bucket', 'accessKeyId', 'secretAccessKey' ],
            ],
        ];
    }

    /**
     * Define rules for the model attributes.
     *
     * @return array The rules for the model attributes.
     */
    protected function defineRules(): array
    {
        return [
            [ [ 'dir', 'rotateLogsAtBytes', 'retentionByDay', 'region', 'bucket', 'accessKeyId', 'secretAccessKey' ], 'required' ],
            [ [ 'dir', 'region', 'bucket', 'accessKeyId', 'secretAccessKey'], StringValidator::class ],
            [ [ 'rotateLogsAtBytes' ], NumberValidator::class, 'integerOnly' => true, 'min' => 1e+6 ],
            [ [ 'retentionByDay' ], NumberValidator::class, 'integerOnly' => true, 'min' => 0 ]
        ];
    }
}
