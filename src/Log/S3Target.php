<?php

namespace MostlySerious\S3Logger\Log;

use DateTime;
use DateInterval;
use yii\log\Target;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;
use Aws\S3\Exception\S3Exception;
use MostlySerious\S3Logger\Plugin;

/**
 * Class S3Target
 *
 * This class extends the Yii framework's Target class for logging. It handles logging to an Amazon S3 bucket.
 * It includes methods to initialize the S3 client, export logs to the S3 bucket, get the formatted log message,
 * get a new key for the S3 object, create a new S3 object with the specified message, and delete the S3 object with the current key.
 *
 * @package Modules\Log
 */
class S3Target extends Target
{
    /**
     * @var S3Client The Amazon S3 client.
     */
    protected S3Client $client;

    /**
     * @var string The current date in 'Y-m-d' format.
     */
    protected string $today;

    /**
     * @var string The name of the S3 bucket.
     */
    protected string $bucket;

    /**
     * @var string The key for the S3 object.
     */
    protected string $key;

    /**
     * @var string The directory for logs.
     */
    protected string $dir;

    /**
     * @var int The maximum number of days logs are retained.
     */
    protected int $retention_by_day;

    /**
     * @var int The maximum size in bytes for log rotation.
     */
    protected int $rotate_logs_at_bytes;

    /**
     * Initialize the S3Target instance.
     *
     * This method initializes the S3 client with the AWS region, access key ID, and secret access key.
     * It also sets the current date, directory for logs, bucket name, and key for the S3 object.
     */
    public function init(): void
    {
        $region = Plugin::$plugin->settings->getRegion();
        $access_key_id = Plugin::$plugin->settings->getAccessKeyId();
        $secret_access_key = Plugin::$plugin->settings->getSecretAccessKey();

        $this->today = gmdate('Y-m-d');
        $this->dir = Plugin::$plugin->settings->getDir();
        $this->bucket = Plugin::$plugin->settings->getBucket();
        $this->retention_by_day = Plugin::$plugin->settings->getRetentionByDay();
        $this->rotate_logs_at_bytes = Plugin::$plugin->settings->getRotateLogsAtBytes();
        $this->key = trim(sprintf('%s/%s.log', $this->dir, $this->today), '/');

        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => new Credentials($access_key_id, $secret_access_key)
        ]);
    }

    /**
     * Export the log messages to the S3 bucket.
     *
     * This method gets the formatted log message and tries to get the S3 object with the current key.
     * If the size of the S3 object is greater than the limit, it copies the object to a new key, deletes the old object, and creates a new object with the message.
     * If the size of the S3 object is not greater than the limit, it deletes the old object and creates a new object with the old object's body and the message.
     * If the S3 object does not exist or an error occurs, it creates a new object with the message.
     */
    public function export()
    {
        $message = $this->getMessage();

        try {
            $object = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
            ]);

            if ((int) $object['ContentLength'] > $this->rotate_logs_at_bytes) {
                $this->client->copyObject([
                    'Bucket' => $this->bucket,
                    'CopySource' => "{$this->bucket}/{$this->key}",
                    'Key' => $this->getNewKey(),
                ]);

                $this->deleteObject();
                $this->putNewObject($message);
            } else {
                $this->deleteObject();
                $this->putNewObject((string) $object['Body'] . $message);
            }

            if ($this->retention_by_day) {
                $date = new DateTime();
                $interval = new DateInterval(sprintf('P%dD', $this->retention_by_day));
                $limit_date = $date->sub($interval)->format('U');

                $objects = $this->client->getPaginator('ListObjects', [
                    'Bucket' => $this->bucket,
                    'Prefix' => $this->dir
                ]);

                foreach ($objects as $list) {
                    foreach ($list['Contents'] as $object) {
                        if (strtotime($object['LastModified']) < $limit_date) {
                            $this->client->deleteObject([
                                'Bucket' => $this->bucket,
                                'Key' => $object['Key']
                            ]);
                        }
                    }
                }
            }
        } catch (S3Exception $e) {
            $this->putNewObject($message);
        }
    }

    /**
     * Get the formatted log message.
     *
     * @return string The formatted log message.
     */
    protected function getMessage(): string
    {
        return implode(PHP_EOL, array_map([ $this, 'formatMessage' ], $this->messages)) . PHP_EOL;
    }

    /**
     * Get a new key for the S3 object.
     *
     * @return string The new key for the S3 object.
     */
    protected function getNewKey(): string
    {
        $indexes = [];
        $objects = $this->client->listObjects([
            'Bucket' => $this->bucket,
            'Prefix' => trim(sprintf('%s/%s', $this->dir, $this->today), '/'),
        ])->get('Contents');

        foreach ($objects as $object) {
            $indexes[] = (int) end(explode('.', trim($object['Key'], '.log')));
        }

        return trim(sprintf('%s/%s.%d.log', $this->dir, $this->today, max($indexes) + 1), '/');
    }

    /**
     * Create a new S3 object with the specified message.
     *
     * @param string $message The message to store in the S3 object.
     */
    protected function putNewObject(string $message = ''): void
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key,
            'Body' => $message
        ]);
    }

    /**
     * Delete the S3 object with the current key.
     */
    protected function deleteObject(): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key
        ]);
    }
}
