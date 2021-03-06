<?php


namespace DataSync\Controllers;

use DataSync\Controllers\Logs;

class File
{

    /**
     * Transfer Files Server to Server using PHP Copy
     *
     */
    public static function copy(object $source_data, $file_contents = false)
    {
        $upload_dir = wp_get_upload_dir();
        $ext        = pathinfo($source_data->filename, PATHINFO_EXTENSION);

        /* New file name and path for this file */
        if (('php' === $ext) && ($file_contents)) {
            // TEMPLATE FILE.
            $local_file = (string) str_replace($source_data->source_upload_url, DATA_SYNC_PATH . 'templates/', $source_data->media->guid);
        } else {
            // MEDIA FILE.
            $subfolder = explode('wp-content/uploads', dirname($source_data->media->guid))[1];
            $local_file = $upload_dir['basedir'] . $subfolder . '/' . $source_data->filename;
            $local_url = $upload_dir['baseurl'] . $subfolder . '/' . $source_data->filename;
        }

        /* Copy the file from source url to server */;
        if (!file_exists($upload_dir['basedir'] . $subfolder)) {
            mkdir($upload_dir['basedir'] . $subfolder, 0755, true); // NECESSARY FOR copy().
        }
        $copied = copy($source_data->media->guid, $local_file);

        /* Add notice for success/failure */
        if (! $copied) {
            $logs = new Logs();
            $logs->set('Failed to copy ' . $source_data->media->guid . '.', true);

            return false;
        }

        if (('php' === $ext) && ($file_contents)) {
            file_put_contents($local_file, $file_contents);
        }

        return true;
    }


    /**
     * Recursively delete folders and files.
     *
     * @param $dir
     */
    public static function delete_media($dir)
    {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file !== "." && $file !== "..") {
                    self::delete_media("$dir/$file");
                }
            }
            rmdir($dir);
        } elseif (file_exists($dir)) {
            unlink($dir);
        }
    }

    public static function copy_r($path, $dest)
    {
        if (is_dir($path)) {
            @mkdir($dest);
            $objects = scandir($path);
            if (sizeof($objects) > 0) {
                foreach ($objects as $file) {
                    if ($file == "." || $file == "..") {
                        continue;
                    }
                    // go on.
                    if (is_dir($path . DS . $file)) {
                        copy_r($path . DS . $file, $dest . DS . $file);
                    } else {
                        copy($path . DS . $file, $dest . DS . $file);
                    }
                }
            }

            return true;
        } elseif (is_file($path)) {
            return copy($path, $dest);
        } else {
            return false;
        }
    }
}
