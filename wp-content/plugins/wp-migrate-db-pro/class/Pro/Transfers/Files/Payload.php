<?php

namespace DeliciousBrains\WPMDB\Pro\Transfers\Files;

use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\Http\Http;
use DeliciousBrains\WPMDB\Common\Transfers\Files\Chunker;
use DeliciousBrains\WPMDB\Common\Transfers\Files\Util;
use DeliciousBrains\WPMDB\Pro\Transfers\Sender;

/**
 * Class Payload
 *
 * @package WPMDB\Transfers\Files
 */
class Payload
{

    /**
     * @var Util
     */
    public $util;
    /**
     * @var Chunker
     */
    public $chunker;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var Http
     */
    private $http;

    const PART_SUFFIX = '.part';
    /**
     * @var \DeliciousBrains\WPMDB\Common\Util\Util
     */
    private $common_util;

    public function __construct(
        Util $util,
        Chunker $chunker,
        Filesystem $filesystem,
        Http $http,
        \DeliciousBrains\WPMDB\Common\Util\Util $common_util
    ) {
        $this->util        = $util;
        $this->chunker     = $chunker;
        $this->filesystem  = $filesystem;
        $this->http        = $http;
        $this->common_util = $common_util;
    }

    /**
     *
     * Create a payload string based on an array of file data
     *
     * Write string to $stream resource
     *
     * @param array    $file
     * @param array    $meta_data
     * @param resource $stream
     * @param string   $file_path
     * @param bool     $chunked
     *
     * @return null
     * @throws \Exception
     */
    public function assemble_payload($file, $meta_data, &$stream, $file_path)
    {
        if (!file_exists($file['absolute_path'])) {
            throw new \Exception(sprintf(__('File does not exist - %s', 'wp-migrate-db'), $file['absolute_path']));
        }

        $file_name          = $file['name'];
        $file['type']       = 'file';
        $file['md5']        = md5_file($file['absolute_path']);
        $file['chunk_size'] = isset($file['chunk_path']) ? filesize($file['chunk_path']) : null;
        $file['encoded']    = true;

        if (!isset($file['size'])) {
            $file['size'] = filesize($file['absolute_path']);
        }

        $meta_data['file'] = $file + $meta_data['file'];

        $content = Sender::$start_meta . $file_name . "\n";
        $content .= json_encode($meta_data) . "\n";
        $content .= Sender::$end_meta . $file_name . "\n";
        $content .= Sender::$start_payload . $file_name . "\n";

        // Write first chunk of content to the payload
        fwrite($stream, $content);

        $file_stream = fopen($file_path, 'rb');

        // Skirts memory limits by copying stream to stream - writes directly to stream
        stream_copy_to_stream($file_stream, $stream);

        $content = "\n" . Sender::$end_payload . $file_name;
        $content .= "\n" . Sender::$end_sig . "\n";

        fwrite($stream, $content);

        return null;
    }

    /**
     * @param array  $file_list
     * @param array  $state_data
     * @param string $bottleneck
     *
     * @return resource|array
     */
    public function create_payload($file_list, $state_data, $bottleneck)
    {
        /*
         * Other options to use if large files aren't downloading are:
         * 	$membuffer = 54 * 1024 * 1024; // 54MB
         * 	$handle = apply_filters( 'wpmdb_transfers_payload_handle', fopen( 'php://temp/maxmemory:'. $membuffer ) );
         *
         * OR
         *
         * $handle = apply_filters( 'wpmdb_transfers_payload_handle', fopen( WP_CONTENT_DIR . '/.payload', 'wb+' ) );
         */
        $handle = apply_filters('wpmdb_transfers_payload_handle', tmpfile());

        $count    = 0;
        $sent     = [];
        $chunked  = [];
        $chunking = false;
        $chunks   = 0;

        foreach ($file_list as $key => $file) {
            // Info on fopen() stream
            $fstat = fstat($handle);

            $added_size = $fstat['size'] + $file['size'];

            // If the filesize is less than the bottleneck and adding the file to the payload would push it over the $bottleneck
            // OR the payload already has stuff in it and the next file is a file larger than the bottleneck
            if (($file['size'] < $bottleneck && $added_size >= $bottleneck)
                || (0 !== $fstat['size'] && $file['size'] >= $bottleneck)) {
                break;
            }

            $data = [
                'file'  => $file,
                'stage' => $state_data['stage'],
            ];

            $file_path = $file['absolute_path'];
            $file_size = filesize($file_path);

            //Push and file is too large
            if ($file_size >= $bottleneck && 'push' === $state_data['intent']) {
                $chunks   = ceil($file_size / $bottleneck);
                $chunking = true;
            }

            list($chunked, $file, $file_path, $chunk_data) = $this->maybe_get_chunk_data($state_data, $bottleneck, $chunking, $file_path, $file, $chunks);

            try {
                $this->assemble_payload($file, $data, $handle, $file_path);
            } catch (\Exception $e) {
                $this->util->catch_general_error($e->getMessage());
            }

            $sent[] = $file;
            $count++;
        }

        if ('pull' === $state_data['intent']) {
            $handle = $this->assemble_payload_metadata($count, $sent, $handle);
        }

        fwrite($handle, "\n" . Sender::$end_bucket);

        if ('push' === $state_data['intent']) {
            return array($count, $sent, $handle, $chunked, $file, $chunk_data);
        }

        return $handle;
    }

    /**
     * @param array  $state_data
     * @param int    $bottleneck
     * @param bool   $chunking
     * @param string $file_path
     * @param array  $file
     * @param int    $chunks
     *
     * @return array
     */
    public function maybe_get_chunk_data($state_data, $bottleneck, $chunking, $file_path, $file, $chunks)
    {
        if (!$chunking) {
            return array(false, $file, $file_path, []);
        }
        // Checks if current migration is a 'push' and if the file is too large to transfer
        list($chunked, $chunk_data) = $this->chunker->chunk_it($state_data, $bottleneck, $file_path, $file, $chunks);

        if ($chunked && false !== $chunked['chunked']) {
            $file      = $chunked['file'];
            $file_path = $chunked['file_path'];
        }

        return array($chunked, $file, $file_path, $chunk_data);
    }

    /**
     *
     * Read payload line by line and parse out contents
     *
     * @param array   $state_data
     * @param resource $stream
     * @param bool     $return
     *
     * @return bool
     * @throws \Exception
     */
    public function process_payload($state_data, $stream, $return = false)
    {
        $is_meta        = false;
        $is_payload     = false;
        $end_payload    = false;
        $is_bucket_meta = false;
        $bucket_meta    = false;
        $meta           = [];

        while (($line = fgets($stream)) !== false) {
            if (false !== strpos($line, Sender::$start_meta)) {
                $is_meta = true;
                continue;
            }

            if ($is_meta) {
                $meta    = json_decode($line, true);
                $is_meta = false;
                continue;
            }

            if (false !== strpos($line, Sender::$start_payload)) {
                $is_payload = true;

                list($dest, $handle) = $this->get_handle_from_metadata($state_data, $meta);

                //For pulls, we're not chunking so use the full filesize, for push check if a chunk size exists, otherwise use the full filesize.
                $filesize = !empty($meta['file']['chunk_size']) ? $meta['file']['chunk_size'] : $meta['file']['size'];

                // maybe we can stream the file without buffering
                if (is_numeric($filesize)) {
                    // set up stream copy here
                    $streamed_bytes = stream_copy_to_stream($stream, $handle, $filesize);
                    if (false === $streamed_bytes || $streamed_bytes !== $filesize) {
                        error_log('Could not copy stream data to file. ' . print_r($dest, true));
                        throw new \Exception(sprintf(__('Could not copy stream data to file. File name: %s', 'wp-migrate-db'), $dest));
                    }
                    // yay! we did it. Next loop gets the end of payload
                    continue;
                }

                //We couldn't determine the filesize so let's bail
                error_log('Could not determine payload filesize: ' . print_r($dest, true));
                throw new \Exception(sprintf(__('Could not determine payload filesize. File name: %s', 'wp-migrate-db'), $dest));
            }

            if ($is_payload) {
                /**
                 * Since we're in a large while loop we need to check if a file's payload
                 * has been read entirely. Files are added to the payload line by line so they
                 * need to read line by line. Sender::$end_payload is the deliminator to say that
                 * a file's contents ends _within_ the payload.
                 */
                if (false !== strpos($line, Sender::$end_payload)) {
                    // Trim trailing newline from end of the created file, thanks fgets()...
                    $stat = fstat($handle);
                    ftruncate($handle, $stat['size'] - 1);

                    fclose($handle);

                    $is_payload  = false;
                    $end_payload = true;
                    continue;
                }

                $this->create_file_by_line($line, $handle, $meta['file']);

            }

            if ($end_payload) {
                if (isset($meta['file']['chunked']) && false !== $meta['file']['chunked']) {
                    if (isset($meta['file']['bytes_offset'], $meta['file']['size']) && ((int)$meta['file']['bytes_offset'] === (int)$meta['file']['size'])) {
                        //Chunking complete
                        $this->rename_part_file($dest, $meta);
                    }
                } else {
                    $this->verify_file_from_payload($dest, $meta);
                }


                $end_payload = false;
                continue;
            }

            /**
             * Bucket meta is information about what's in the payload.
             *
             * Presently this includes a count of how many files it contains and
             * file information from Filesystem::get_file_info() about each file within
             *
             */
            if (false !== strpos($line, Sender::$start_bucket_meta)) {
                $is_bucket_meta = true;
                continue;
            }

            if ($is_bucket_meta) {
                $bucket_meta    = json_decode($line, true);
                $is_bucket_meta = false;
                continue;
            }

            if (false !== strpos($line, Sender::$end_bucket)) {
                if (!$return) {
                    return $this->http->end_ajax($bucket_meta);
                }

                return $bucket_meta;
            }
        }

        return false;
    }

    /**
     * @param $dest
     * @param $meta
     *
     * @throws \Exception
     */
    public function verify_file_from_payload($dest, $meta)
    {
        // Verify size of file matches what it's supposed to be
        if (!$this->util->verify_file($dest, (int)$meta['file']['size'])) {
            $msg = sprintf(__('File size of source and destination do not match: <br>%s<br>Destination size: %s, Local size: %s', 'wp-migrate-db'), $dest, filesize($dest), $meta['file']['size']);
            throw new \Exception($msg);
        }

        $md5 = md5_file($dest);

        if ($meta['file']['md5'] !== $md5) {
            $msg = sprintf(__("File MD5's do not match for file: %s \nLocal MD5: %s Remote MD5: %s", 'wp-migrate-db'), \dirname($dest), $md5, $meta['file']['md5']);

            throw new \Exception($msg);
        }
    }

    /**
     *
     * Give a line of data from fgets() write to a previously created resource(stream).
     *
     * @param $line
     * @param $handle
     *
     * @return bool
     * @throws \Exception
     */
    public function create_file_by_line($line, $handle, $file_data)
    {
        if (!$handle || !$bytes = fwrite($handle, $line)) {
            error_log('Could not write line to file. ' . print_r($file_data, true));
            throw new \Exception(sprintf(__('Could not write line to file. File name: %s', 'wp-migrate-db'), $file_data['name']));
        }

        return false;
    }

    /**
     * @param array $state_data
     * @param $meta
     *
     * @return string
     */
    public function assemble_filepath_from_payload($state_data, $meta)
    {
        $stage = $state_data['stage'];

        $file = $this->filesystem->slash_one_direction($meta['file']['relative_path']);

        if (isset($meta['file']['chunked']) && $meta['file']['chunked'] === true) {
            $file .= self::PART_SUFFIX;
        }

        $dest = Util::get_temp_dir($stage) . $file;
        if ($stage === 'media_files') {
            // Filtered by MST
            $uploads = apply_filters('wpmdb_mf_destination_uploads', Util::get_wp_uploads_dir(), $state_data);
            $file    = apply_filters('wpmdb_mf_destination_file', $file, $state_data);
            $dest    = $uploads . $file;
        }

        return $dest;
    }


    /**
     * @param $stage
     * @param $meta
     *
     * @return array
     * @throws \Exception
     */
    public function get_handle_from_metadata($state_data, $meta)
    {
        $dest = $this->assemble_filepath_from_payload($state_data, $meta);

        $dirname = \dirname($dest);

        if (!is_dir($dirname)) {
            if (!$this->filesystem->mkdir($dirname)) {
                $msg = sprintf(__('Could not create directory: %s', 'wp-migrate-db'), dirname($dest));
                throw new \Exception($msg);
            }
        }

        $mode = isset($meta['file']['chunked']) ? 'ab' : 'wb';

        // Files need to be deleted before hand when running media stage because they are copied in place
        // 'w' fopen() mode truncates the file before opening to write
        if (
            isset($meta['file']['chunked']) && $meta['file']['chunk_number'] === 1 ||
            !isset($meta['file']['chunked'])
        ) {
            $mode = 'w';
        }

        if (file_exists($dest) && !is_writable($dest)) {
            $msg = sprintf(__('The `%s` file is not writable, please check the file permissions of the parent folder and ensure the web server can read from and write to this file.', 'wp-migrate-db'), $dest);
            throw new \Exception($msg);
        }

        $handle = fopen($dest, $mode);

        return array($dest, $handle);
    }

    /**
     * @param $count
     * @param $sent
     * @param $handle
     *
     * @return resource
     */
    public function assemble_payload_metadata($count, $sent, $handle)
    {
        // Information about what's in the payload, number of files and an array of file data about the files included
        $bucket_meta = json_encode(compact('count', 'sent'));

        $bucket_meta_content = Sender::$start_bucket_meta . "\n";
        $bucket_meta_content .= $bucket_meta . "\n";
        $bucket_meta_content .= Sender::$end_bucket_meta;

        fwrite($handle, $bucket_meta_content);

        return $handle;
    }

    /**
     * @param $dest
     * @param $meta
     */
    public function rename_part_file($dest, $meta)
    {
        $original_filename = str_replace(self::PART_SUFFIX, '', $dest);

        if ($this->filesystem->file_exists($original_filename)) {
            $this->filesystem->unlink($original_filename);
        }

        $renamed = rename($dest, $original_filename);

        if (!$renamed) {
            return $this->http->end_ajax(new \WP_Error('wpmdb_chunk_file_rename_failed', sprintf(__('Unable to rename part file %s', 'wp-migrate-db'), $dest)));
        }

        return $this->verify_file_from_payload($original_filename, $meta);
    }
}
