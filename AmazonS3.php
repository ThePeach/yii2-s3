<?php
/**
 * @author: Jovani F. Alferez <vojalf@gmail.com>
 */

namespace ThePeach\yii2s3;

/**
 * A Yii2-compatible component wrapper for Aws\S3\S3Client.
 * Just add this component to your configuration providing this class,
 * key, secret and bucket.
 *
 * ~~~
 * 'components' => [
 *     'storage' => [
 *          'class' => '\jovanialferez\yii2s3\AmazonS3',
 *          'key' => 'AWS_ACCESS_KEY_ID',
 *          'secret' => 'AWS_SECRET_ACCESS_KEY',
 *          'bucket' => 'YOUR_BUCKET',
 *          'region' => 'eu-west-1' // this is optional, but required if your bucket is not in US
 *     ],
 * ],
 * ~~~
 *
 * You can then start using this component as:
 *
 * ```php
 * $storage = \Yii::$app->storage;
 * $url = $storage->uploadFile('/path/to/file', 'unique_file_name');
 * ```
 */
class AmazonS3 extends \yii\base\Component
{
    public $bucket;
    public $key;
    public $secret;
    public $region = null;

    private $_client;

    public function init()
    {
        parent::init();

        $options = [
            'key' => $this->key,
            'secret' => $this->secret
        ];

        if ($this->region !== null) {
            $options['region'] = $this->region;
        }

        $this->_client = \Aws\S3\S3Client::factory($options);
    }

    /**
     * Uploads the file into S3 in that bucket.
     *
     * @param string $filePath Full path of the file. Can be from tmp file path.
     * @param string $fileName Filename to save this file into S3. May include directories.
     * @param bool $bucket Override configured bucket.
     * @return bool|string The S3 generated url that is publicly-accessible.
     */
    public function uploadFile($filePath, $fileName, $bucket = false)
    {
        if (!$bucket) {
            $bucket = $this->bucket;
        }

        try {
            $result = $this->_client->putObject([
                    'ACL' => 'public-read',
                    'Bucket' => $bucket,
                    'Key' => $fileName,
                    'SourceFile' => $filePath,
                    'ContentType' => \yii\helpers\FileHelper::getMimeType($filePath),
                ]);

        } catch (\Exception $e) {
            return false;
        }
        
        return $result->get('ObjectURL');
    }

    /**
     * Deletes a file from the S3 bucket.
     *
     * @param string $fileName Filename to delete from the bucket. May include directories.
     * @param bool $bucket Override configured bucket.
     * @return bool if the delete operation completed successfully.
     */
    public function deleteFile($fileName, $bucket = false)
    {
        if (!$bucket) {
            $bucket = $this->bucket;
        }

        try {
            $result = $this->_client->deleteObject([
                    'Bucket' => $bucket,
                    'Key' => $fileName
                ]);

        } catch (\Exception $e) {
            return false;
        }

        // delete is an idempotent operation
        // @see https://forums.aws.amazon.com/thread.jspa?messageID=455154
        return $this->doesFileExist($fileName, $bucket);
    }

    /**
     * Checks if a file esists in the S3 bucket.
     *
     * @param string $fileName Fielname to check in the bucket. May include directories.
     * @param bool $bucket Override configured bucket.
     * @return bool if the file exists or not
     */
    public function doesFileExist($fileName, $bucket = false)
    {
        if (!$bucket) {
            $bucket = $this->bucket;
        }

        try {
            $result = $this->_client->doesObjectExist($bucket, $fileName);
        } catch (\Exception $e){
            return false;
        }

        return $result;
    }

    /**
     * Copies an existing file to another file
     *
     * @param string $fromfileName the path of the source file name
     * @param string $toFileName the path name of the new file name
     * @param string $bucket Overrides configured bucket
     * @return bool if the operation completed successfully
     */
    public function copyFile($fromFileName, $toFileName, $bucket = false)
    {
        if (!$bucket) {
            $bucket = $this->bucket;
        }

        try {
            $result = $this->_client->copyObject([
                'Bucket' => $bucket,
                'CopySource' => $bucket . DIRECTORY_SEPARATOR . $fileName,
                'Key' => $toFileName
            ]);
        } catch (\Exception $e) {
            return false;
        }

        return $this->doesFileExist($toFileName, $bucket);
    }
}
