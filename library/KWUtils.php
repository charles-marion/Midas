<?php
/*=========================================================================
 MIDAS Server
 Copyright (c) Kitware SAS. 26 rue Louis Guérin. 69100 Villeurbanne, FRANCE
 All rights reserved.
 More information http://www.kitware.com

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

         http://www.apache.org/licenses/LICENSE-2.0.txt

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
=========================================================================*/

/** Globally useful utility functions. */
class KWUtils
{
    const DEFAULT_MKDIR_MODE = 0775;

    /**
     * @TODO what to do with errors in a way that is consistent with error reporting
     * Will create the directory $dir and set the filemode so that the newly
     * created dir is writable by the current user.
     *
     * @param string $dir
     * @param int $mode
     * @return bool true on success, false otherwise
     */
    public static function mkDir($dir, $mode = self::DEFAULT_MKDIR_MODE)
    {
        if (!file_exists($dir) && !mkdir($dir, $mode, true)) {
            return false;
        }
        // change file mode
        // even though we are swallowing the error messages, we return false
        // if the operation can't be completed
        if (!is_writable($dir) || @!chmod($dir, $mode)) {
            return false;
        }

        return true;
    }

    /**
     * recursively create subdirectories starting at
     * baseDirectory, sequentially creating each of the directories in the
     * subDirectories array, according to the passed in mode.
     *
     * @param string $baseDirectory the first directory to create
     * @param array $subDirectories an array of directories that will be created in a
     * recursive fashion, each one appending to the last as a deeper subdirectory
     * of baseDirectory
     * @param int $mode mode to create the new directories
     * @return string
     * @throws Zend_Exception
     */
    public static function createSubDirectories($baseDirectory, $subDirectories, $mode = self::DEFAULT_MKDIR_MODE)
    {
        if (!file_exists($baseDirectory)) {
            throw new Zend_Exception($baseDirectory.' does not exist');
        }
        $relpath = '';
        foreach ($subDirectories as $directory) {
            $relpath .= $directory."/";
            if (!is_dir($baseDirectory.$relpath) && !KwUtils::mkDir($baseDirectory.$relpath, $mode)
            ) {
                throw new Zend_Exception($baseDirectory.$relpath.' could not be created');
            }
        }

        return $baseDirectory.$relpath;
    }

    /**
     * Return true if the current platform is Windows.
     *
     * @return bool
     */
    public static function isWindows()
    {
        return (strtolower(substr(PHP_OS, 0, 3)) == "win");
    }

    /**
     * Will escape a command respecting the format of the current platform.
     *
     * @TODO, how to test this?
     * @param string $command, the command to be escaped
     * @return string command escaped for the current platform
     */
    public static function escapeCommand($command)
    {
        return $command;
    }

    /**
     * will append the extension to the
     * subject if it is not already a suffix of subject
     *
     * @param string $subject the string to be appended to
     * @param string $ext the extension to check for and append
     * @return string subject, will end with the suffix $ext
     */
    public static function appendStringIfNot($subject, $ext)
    {
        if (!(substr($subject, strlen($subject) - strlen($ext)) === $ext)) {
            $subject .= $ext;
        }

        return $subject;
    }

    /**
     * will execute a command, respecting the format of the current platform.
     *
     * @param string $command command to be executed, with all arguments, and formatted correctly
     * @param null|mixed $output a reference to put the output of the command
     * @param string $chdir the dir to change to for execution, if any
     * @param null|mixed $return_val a reference to put the return value of the command the temporary work dir
     * @throws Zend_Exception
     */
    public static function exec($command, &$output = null, $chdir = '', &$return_val = null)
    {
        $changed = false;
        if (!empty($chdir)) {
            if (is_dir($chdir)) {
                if (!getcwd()) {
                    throw new Zend_Exception('getcwd failed');
                }
                $currCwd = getcwd();

                if (!chdir($chdir)) {
                    throw new Zend_Exception("Failed to change directory: [".$chdir."]");
                }
                $changed = true;
            } else {
                throw new Zend_Exception("passed in chdir is not a directory: [".$chdir."]");
            }
        }

        // on Linux need to add redirection to handle stderr
        $redirect_error = KWUtils::isLinux() ? ' 2>&1' : '';
        $escaped = KWUtils::escapeCommand($command);
        exec($escaped.$redirect_error, $output, $return_val);

        // change back to original directory if necessary
        if ($changed && !chdir($currCwd)) {
            throw new Zend_Exception("Failed to change back to original directory: [".$currCwd."]");
        }
    }

    /**
     * Return true if the current platform is Linux.
     *
     * @return bool
     */
    public static function isLinux()
    {
        return (strtolower(substr(PHP_OS, 0, 5)) == "linux");
    }

    /**
     * will prepare an executable application and params for command line
     * execution, including escaping and quoting arguments.
     *
     * @param string $app_name the application to be executed
     * @param array $params an array of arguments to the application
     * @return string the full command line command, escaped and quoted
     * @throws Zend_Exception if the app is not in the path and not executable
     */
    public static function prepareExecCommand($app_name, $params = array())
    {
        // Check if application is executable, if not, see if you can find it
        // in the path
        if (!KWUtils::isExecutable($app_name, false)) {
            $app_name = KWUtils::findApp($app_name, true);
        }

        // escape parameters
        $escapedParams = array();
        foreach ($params as $param) {
            $escapedParams[] = escapeshellarg($param);
        }

        // glue together app_name and params using spaces
        return escapeshellarg($app_name).' '.implode(' ', $escapedParams);
    }

    /**
     * will return true if the app can be found and is
     * executable, can optionally look in the path.
     *
     * @param string $app_name the app to check
     * @param bool $check_in_path , if true, will search in path for app
     * @return bool true if app_name is found and executable, False otherwise
     */
    public static function isExecutable($app_name, $check_in_path = false)
    {
        if (!is_executable($app_name)) {
            if ($check_in_path) {
                try {
                    if (KWUtils::findApp($app_name, true)) {
                        return true;
                    }
                } catch (Zend_Exception $ze) {
                    return false;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * will return the absolute path of an application
     *
     * @param string $app_name the name of the application
     * @param bool $check_execution_flag whether to include in the check that the
     * application is executable
     * @return string the path to the application, throws a Zend_Exception if the app
     *             can't be found, or if $check_execution_flag  is set and the app is not
     *             executable.
     * @throws Zend_Exception
     */
    public static function findApp($app_name, $check_execution_flag)
    {
        $PHP_PATH_SEPARATOR = ":";
        // split path
        $path_list = explode($PHP_PATH_SEPARATOR, getenv("PATH"));

        // loop through paths
        foreach ($path_list as $path) {
            $status = false;
            $path_to_app = KWUtils::appendStringIfNot($path, DIRECTORY_SEPARATOR).$app_name;
            if ($check_execution_flag) {
                if (is_executable($path_to_app)) {
                    $status = true;
                    break;
                }
            } else {
                if (file_exists($path_to_app)) {
                    $status = true;
                    break;
                }
            }
        }
        if (!$status) {
            throw new Zend_Exception(
                "Failed to locate the application: [".$app_name."] [check_execution_flag:".$check_execution_flag."]"
            );
        }

        return $path_to_app;
    }

    /**
     * Format the application name according to the platform.
     *
     * @param string $app_name
     * @return string
     */
    public static function formatAppName($app_name)
    {
        if (substr(PHP_OS, 0, 3) == "WIN") {
            $app_name = KWUtils::appendStringIfNot($app_name, ".exe");
        }

        return $app_name;
    }

    /**
     * Helper function to recursively delete a directory
     *
     * @param string $directorypath Directory to be deleted
     * @return bool Success or not
     */
    public static function recursiveRemoveDirectory($directorypath)
    {
        if (empty($directorypath) || !is_dir($directorypath)) {
            return false;
        }

        // if the path has a slash at the end, remove it here
        $directorypath = rtrim($directorypath, '/');
        // open the directory
        $handle = opendir($directorypath);

        if (!is_readable($directorypath)) {
            return false;
        }
        // and scan through the items inside
        while (false !== ($item = readdir($handle))) {
            // if the filepointer is not the current directory or the parent directory
            if ($item != '.' && $item != '..') {
                // build the new path to delete
                $path = $directorypath.'/'.$item;
                // if the new path is a directory
                if (is_dir($path)) {
                    // call itself with the new path
                    KWUtils::recursiveRemoveDirectory($path);
                    // if the new path is a file
                } else {
                    // remove the file
                    unlink($path);
                }
            }
        }
        closedir($handle);
        // try to delete the now empty directory
        if (!rmdir($directorypath)) {
            return false;
        }

        return true;
    }
}
