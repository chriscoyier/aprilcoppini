<?php

namespace DeliciousBrains\WPMDB\Pro\MST;

use DeliciousBrains\WPMDB\Common\Filesystem\Filesystem;
use DeliciousBrains\WPMDB\Common\Util\Util;

class MediaFilesCompat
{

    /**
     * @var Util
     */
    private $util;
    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(
        Util $util,
        Filesystem $filesystem

    ) {
        $this->util       = $util;
        $this->filesystem = $filesystem;
    }

    /**
     * Given the $state_data array, check if 'mst_selected_subsite' is 1
     *
     * @param $state_data
     *
     * @return int
     */
    public function get_subsite_from_state_data($state_data, $target = 'source')
    {
        $local_multisite   = 'true' === $state_data['site_details']['local']['is_multisite'];
        $remote_multisite  = 'true' === $state_data['site_details']['remote']['is_multisite'];
        if ($local_multisite && $remote_multisite) {
            if ('destination' === $target) {
                return (int)isset($state_data['mst_destination_subsite']) ? $state_data['mst_destination_subsite'] : 0;
            }
            return (int)isset($state_data['mst_selected_subsite']) ? $state_data['mst_selected_subsite'] : 0;
        }
        if ($local_multisite){
            if ('push' === $state_data['intent']) {
                return 'source' === $target ? $state_data['mst_selected_subsite'] : 0;
            }
            return 'source' === $target ? 0 : $state_data['mst_selected_subsite'];
        }
        //if remote is multisite
        if ('push' === $state_data['intent']) {
            return 'source' === $target ? 0 : $state_data['mst_selected_subsite'];
        }
        return isset($state_data['mst_selected_subsite']) && 'source' === $target ? $state_data['mst_selected_subsite']: 0;
    }

    /**
     *
     * Called from the 'wpmdb_mf_destination_uploads' hook
     *
     * @param $uploads_dir
     * @param $state_data
     *
     * @return mixed
     */
    public function filter_media_uploads($uploads_dir, $state_data)
    {
        if (!is_multisite() || !$this->is_subsite_migration($state_data)) {
            return $uploads_dir;
        }

        $site_id = $this->get_subsite_from_state_data($state_data, 'destination');

        if ($site_id === 0) {
            return $uploads_dir;
        }

        $uploads_info = $this->util->uploads_info($site_id);

        if (isset($uploads_info['basedir'])) {
            return $uploads_info['basedir'];
        }

        return $uploads_dir;
    }

    /**
     *
     * Called from the 'wpmdb_mf_destination_file' hook
     *
     * @param string $file
     * @param array  $state_data
     *
     * @return string
     */
    public function filter_media_destination($file, $state_data)
    {
        $site_id = $this->get_subsite_from_state_data($state_data, 'source');
        if ($site_id === 0 || !$this->is_subsite_migration($state_data)) {
            return $file;
        }

        $slashed_file = $this->filesystem->slash_one_direction($file);

        $pattern = '/^\\' . DIRECTORY_SEPARATOR . 'sites\\' . DIRECTORY_SEPARATOR . $site_id . '/';

        if (false !== strpos($slashed_file, 'blogs.dir')) {
            $pattern = '/^blogs.dir\\' . DIRECTORY_SEPARATOR . $site_id.'\\' . DIRECTORY_SEPARATOR . 'files/';
        }

        $file = preg_replace($pattern, '', $slashed_file);

        return $file;
    }

    /**
     * Filter relative paths for media exports
     *
     * Hooked to filter_media_export_destination
     *
     * @param string $relative_path
     * @param string $state_data
     * @return string
     **/
    public function filter_media_export_destination($relative_path, $state_data)
    {

        if ( ! isset($state_data['mst_select_subsite']) || $state_data['mst_select_subsite'] !== '1' || $state_data['mst_selected_subsite'] < 2) {
            return $relative_path;
        }

        return str_replace('sites/' . $state_data['mst_selected_subsite'], '', $relative_path);

    }

    /**
     *
     * Called from the 'wpmdb_mf_local_uploads_folder' hook
     *
     * @param $path
     * @param $state_data
     *
     * @return array|mixed
     */
    public function filter_uploads_path_local($path, $state_data)
    {
        return $this->filter_uploads_path($path, $state_data, 'local');
    }

    /**
     *
     * Called from the 'wpmdb_mf_remote_uploads_folder' hook
     *
     * @param $path
     * @param $state_data
     *
     * @return array|mixed
     */
    public function filter_uploads_path_remote($path, $state_data)
    {
        return $this->filter_uploads_path($path, $state_data, 'remote');
    }

    /**
     *
     * Given $state_data and an uploads file path, determine new uploads path
     *
     * @param        $path
     * @param        $state_data
     * @param string $location
     *
     * @return array|mixed
     */
    public function filter_uploads_path($path, $state_data, $location = 'local')
    {
        $target = 'local' === $location ? 'source' : 'destination';
        if ('pull' === $state_data['intent']) {
            $target = 'local' === $location ? 'destination' : 'source';
        }
        $blog_id = $this->get_subsite_from_state_data($state_data, $target);

        if ($blog_id <= 1  || !$this->is_subsite_migration($state_data)) {
            return $path;
        }

        $uploads = 'remote' === $location ? $this->get_remote_uploads_dir($blog_id, $state_data) : $this->util->uploads_info($blog_id);

        if (isset($uploads['basedir']) && 'remote' !== $location) {
            $path = $uploads['basedir'];
        } else {
            $path = $uploads;
        }

        $path = $location === 'remote' ? (array)$path : $path;

        return $path;
    }

    /**
     *
     * Get path of remote uploads directory
     *
     * @param int $blog_id
     * @param array $state_data
     * @return string
     **/
    public function get_remote_uploads_dir($blog_id, $state_data) {
        $path = $state_data['site_details']['remote']['uploads']['basedir'];
        if ($blog_id > 1) {
            $path .= '/sites/' . $blog_id;
        }
        return $path;
    }

    /**
     *
     * Filter excludes if subsite ID is 1, we don't want to migrate all the other subsites as well
     *
     * Call from the 'wpmdb_mf_excludes' hook
     *
     * @param $excludes
     * @param $state_data
     *
     * @return array
     */
    public function filter_media_excludes($excludes, $state_data)
    {
        if (!$this->is_subsite_migration($state_data)) {
            return $excludes;
        }
        $blog_id = $this->get_subsite_from_state_data($state_data, 'source');

        if ($blog_id !== 1) {
            return $excludes;
        }

        $excludes[] = '**/sites/*';

        return $excludes;
    }

    /**
     *
     * @param $state_data
     *
     * @return bool
     */
    private function is_subsite_migration($state_data) {
        return isset($state_data['mst_select_subsite']) && '1' === $state_data['mst_select_subsite'];
    }
}
