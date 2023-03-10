<?php

namespace Code\Module\Admin;

use App;
use PHPGit\Exception\GitException;
use Code\Storage\GitRepo;
use Michelf\MarkdownExtra;
use Code\Lib\Addon;
use Code\Render\Theme;


class Addons
{

    /**
     * @brief
     *
     */
    public function post()
    {

        if (argc() > 2 && is_file("addon/" . argv(2) . "/" . argv(2) . ".php")) {
            @include_once("addon/" . argv(2) . "/" . argv(2) . ".php");
            if (function_exists(argv(2) . '_plugin_admin_post')) {
                $func = argv(2) . '_plugin_admin_post';
                $a = [];
                $func($a);
            }

            goaway(z_root() . '/admin/addons/' . argv(2));
        }
        elseif (argc() > 2) {
            switch (argv(2)) {
                case 'updaterepo':
                    if (array_key_exists('repoName', $_REQUEST)) {
                        $repoName = $_REQUEST['repoName'];
                    }
                    else {
                        json_return_and_die(array('message' => 'No repo name provided.', 'success' => false));
                    }
                    $extendDir = 'cache/git/sys/extend';
                    $addonDir = $extendDir . '/addon';
                    if (!file_exists($extendDir)) {
                        if (!mkdir($extendDir, 0770, true)) {
                            logger('Error creating extend folder: ' . $extendDir);
                            json_return_and_die(array('message' => 'Error creating extend folder: ' . $extendDir, 'success' => false));
                        } else {
                            if (!symlink(realpath('extend/addon'), $addonDir)) {
                                logger('Error creating symlink to addon folder: ' . $addonDir);
                                json_return_and_die(array('message' => 'Error creating symlink to addon folder: ' . $addonDir, 'success' => false));
                            }
                        }
                    }
                    $repoDir = 'cache/git/sys/extend/addon/' . $repoName;
                    if (!is_dir($repoDir)) {
                        logger('Repo directory does not exist: ' . $repoDir);
                        json_return_and_die(array('message' => 'Invalid addon repo.', 'success' => false));
                    }
                    if (!is_writable($repoDir)) {
                        logger('Repo directory not writable to web server: ' . $repoDir);
                        json_return_and_die(array('message' => 'Repo directory not writable to web server.', 'success' => false));
                    }
                    $git = new GitRepo('sys', null, false, $repoName, $repoDir);
                    try {
                        if ($git->pull()) {
                            $files = array_diff(scandir($repoDir), array('.', '..'));
                            foreach ($files as $file) {
                                if (is_dir($repoDir . '/' . $file) && $file !== '.git') {
                                    $source = '../extend/addon/' . $repoName . '/' . $file;
                                    $target = realpath('addon/') . '/' . $file;
                                    unlink($target);
                                    if (!symlink($source, $target)) {
                                        logger('Error linking addons to /addon');
                                        json_return_and_die(array('message' => 'Error linking addons to /addon', 'success' => false));
                                    }
                                }
                            }
                            json_return_and_die(array('message' => 'Repo updated.', 'success' => true));
                        } else {
                            json_return_and_die(array('message' => 'Error updating addon repo.', 'success' => false));
                        }
                    } catch (GitException $e) {
                        json_return_and_die(array('message' => 'Error updating addon repo.', 'success' => false));
                    }
                case 'removerepo':
                    if (array_key_exists('repoName', $_REQUEST)) {
                        $repoName = $_REQUEST['repoName'];
                    } else {
                        json_return_and_die(array('message' => 'No repo name provided.', 'success' => false));
                    }
                    $extendDir = 'cache/git/sys/extend';
                    $addonDir = $extendDir . '/addon';
                    if (!file_exists($extendDir)) {
                        if (!mkdir($extendDir, 0770, true)) {
                            logger('Error creating extend folder: ' . $extendDir);
                            json_return_and_die(array('message' => 'Error creating extend folder: ' . $extendDir, 'success' => false));
                        } else {
                            if (!symlink(realpath('extend/addon'), $addonDir)) {
                                logger('Error creating symlink to addon folder: ' . $addonDir);
                                json_return_and_die(array('message' => 'Error creating symlink to addon folder: ' . $addonDir, 'success' => false));
                            }
                        }
                    }
                    $repoDir = 'cache/git/sys/extend/addon/' . $repoName;
                    if (!is_dir($repoDir)) {
                        logger('Repo directory does not exist: ' . $repoDir);
                        json_return_and_die(array('message' => 'Invalid addon repo.', 'success' => false));
                    }
                    if (!is_writable($repoDir)) {
                        logger('Repo directory not writable to web server: ' . $repoDir);
                        json_return_and_die(array('message' => 'Repo directory not writable to web server.', 'success' => false));
                    }
                    /// @TODO remove directory and unlink /addon/files
                    if (rrmdir($repoDir)) {
                        json_return_and_die(array('message' => 'Repo deleted.', 'success' => true));
                    } else {
                        json_return_and_die(array('message' => 'Error deleting addon repo.', 'success' => false));
                    }
                case 'installrepo':
                    if (array_key_exists('repoURL', $_REQUEST)) {
                        $repoURL = $_REQUEST['repoURL'];
                        $extendDir = 'cache/git/sys/extend';
                        $addonDir = $extendDir . '/addon';
                        if (!file_exists($extendDir)) {
                            if (!mkdir($extendDir, 0770, true)) {
                                logger('Error creating extend folder: ' . $extendDir);
                                json_return_and_die(array('message' => 'Error creating extend folder: ' . $extendDir, 'success' => false));
                            } else {
                                if (!symlink(realpath('extend/addon'), $addonDir)) {
                                    logger('Error creating symlink to addon folder: ' . $addonDir);
                                    json_return_and_die(array('message' => 'Error creating symlink to addon folder: ' . $addonDir, 'success' => false));
                                }
                            }
                        }
                        if (!is_writable($extendDir)) {
                            logger('Directory not writable to web server: ' . $extendDir);
                            json_return_and_die(array('message' => 'Directory not writable to web server.', 'success' => false));
                        }
                        $repoName = null;
                        if (array_key_exists('repoName', $_REQUEST) && $_REQUEST['repoName'] !== '') {
                            $repoName = $_REQUEST['repoName'];
                        } else {
                            $repoName = GitRepo::getRepoNameFromURL($repoURL);
                        }
                        if (!$repoName) {
                            logger('Invalid git repo');
                            json_return_and_die(array('message' => 'Invalid git repo', 'success' => false));
                        }
                        $repoDir = $addonDir . '/' . $repoName;
                        $tempRepoBaseDir = 'cache/git/sys/temp/';
                        $tempAddonDir = $tempRepoBaseDir . $repoName;

                        if (!is_writable($addonDir) || !is_writable($tempAddonDir)) {
                            logger('Temp repo directory or /extend/addon not writable to web server: ' . $tempAddonDir);
                            json_return_and_die(array('message' => 'Temp repo directory not writable to web server.', 'success' => false));
                        }
                        rename($tempAddonDir, $repoDir);

                        if (!is_writable(realpath('addon/'))) {
                            logger('/addon directory not writable to web server: ' . $tempAddonDir);
                            json_return_and_die(array('message' => '/addon directory not writable to web server.', 'success' => false));
                        }
                        $files = array_diff(scandir($repoDir), array('.', '..'));
                        foreach ($files as $file) {
                            if (is_dir($repoDir . '/' . $file) && $file !== '.git') {
                                $source = '../extend/addon/' . $repoName . '/' . $file;
                                $target = realpath('addon/') . '/' . $file;
                                unlink($target);
                                if (!symlink($source, $target)) {
                                    logger('Error linking addons to /addon');
                                    json_return_and_die(array('message' => 'Error linking addons to /addon', 'success' => false));
                                }
                            }
                        }
                        $git = new GitRepo('sys', $repoURL, false, $repoName, $repoDir);
                        $repo = $git->probeRepo();
                        json_return_and_die(array('repo' => $repo, 'message' => '', 'success' => true));
                    }
                case 'addrepo':
                    if (array_key_exists('repoURL', $_REQUEST)) {
                        $repoURL = $_REQUEST['repoURL'];
                        $extendDir = 'cache/git/sys/extend';
                        $addonDir = $extendDir . '/addon';
                        $tempAddonDir = realpath('cache') . '/git/sys/temp';
                        if (!file_exists($extendDir)) {
                            if (!mkdir($extendDir, 0770, true)) {
                                logger('Error creating extend folder: ' . $extendDir);
                                json_return_and_die(array('message' => 'Error creating extend folder: ' . $extendDir, 'success' => false));
                            } else {
                                if (!symlink(realpath('extend/addon'), $addonDir)) {
                                    logger('Error creating symlink to addon folder: ' . $addonDir);
                                    json_return_and_die(array('message' => 'Error creating symlink to addon folder: ' . $addonDir, 'success' => false));
                                }
                            }
                        }
                        if (!is_dir($tempAddonDir)) {
                            if (!mkdir($tempAddonDir, 0770, true)) {
                                logger('Error creating temp plugin repo folder: ' . $tempAddonDir);
                                json_return_and_die(array('message' => 'Error creating temp plugin repo folder: ' . $tempAddonDir, 'success' => false));
                            }
                        }
                        $repoName = null;
                        if (array_key_exists('repoName', $_REQUEST) && $_REQUEST['repoName'] !== '') {
                            $repoName = $_REQUEST['repoName'];
                        } else {
                            $repoName = GitRepo::getRepoNameFromURL($repoURL);
                        }
                        if (!$repoName) {
                            logger('Invalid git repo');
                            json_return_and_die(array('message' => 'Invalid git repo: ' . $repoName, 'success' => false));
                        }
                        $repoDir = $tempAddonDir . '/' . $repoName;
                        if (!is_writable($tempAddonDir)) {
                            logger('Temporary directory for new addon repo is not writable to web server: ' . $tempAddonDir);
                            json_return_and_die(array('message' => 'Temporary directory for new addon repo is not writable to web server.', 'success' => false));
                        }
                        // clone the repo if new automatically
                        $git = new GitRepo('sys', $repoURL, true, $repoName, $repoDir);

                        $remotes = $git->git->remote();
                        $fetchURL = $remotes['origin']['fetch'];
                        if ($fetchURL !== $git->url) {
                            if (rrmdir($repoDir)) {
                                $git = new GitRepo('sys', $repoURL, true, $repoName, $repoDir);
                            } else {
                                json_return_and_die(array('message' => 'Error deleting existing addon repo.', 'success' => false));
                            }
                        }
                        $repo = $git->probeRepo();
                        $repo['readme'] = $repo['manifest'] = null;
                        foreach ($git->git->tree('master') as $object) {
                            if ($object['type'] == 'blob' && (strtolower($object['file']) === 'readme.md' || strtolower($object['file']) === 'readme')) {
                                $repo['readme'] = MarkdownExtra::defaultTransform($git->git->cat->blob($object['hash']));
                            } elseif ($object['type'] == 'blob' && strtolower($object['file']) === 'manifest.json') {
                                $repo['manifest'] = $git->git->cat->blob($object['hash']);
                            }
                        }
                        json_return_and_die(array('repo' => $repo, 'message' => '', 'success' => true));
                    } else {
                        json_return_and_die(array('message' => 'No repo URL provided', 'success' => false));
                    }
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * @brief Addons admin page.
     *
     * @return string with parsed HTML
     */
    public function get()
    {

        /*
         * Single plugin
         */

        if (App::$argc == 3) {
            $plugin = App::$argv[2];
            if (!is_file("addon/$plugin/$plugin.php")) {
                notice(t("Item not found."));
                return '';
            }

            $enabled = in_array($plugin, Addon::list_installed());
            $info = Addon::get_info($plugin);
            $x = Addon::check_versions($info);

            // disable plugins which are installed but incompatible versions

            if ($enabled && !$x) {
                $enabled = false;
                Addon::uninstall($plugin);
            }
            $info['disabled'] = 1 - intval($x);

            if (x($_GET, "a") && $_GET['a'] == "t") {
                check_form_security_token_redirectOnErr('/admin/addons', 'admin_addons', 't');
                $pinstalled = false;
                // Toggle plugin status
                $idx = array_search($plugin, Addon::list_installed());
                if ($idx !== false) {
                    Addon::uninstall($plugin);
                    $pinstalled = false;
                    info(sprintf(t("Plugin %s disabled."), $plugin));
                } else {
                    Addon::install($plugin);
                    $pinstalled = true;
                    info(sprintf(t("Plugin %s enabled."), $plugin));
                }

                if ($pinstalled) {
                    @require_once("addon/$plugin/$plugin.php");
                    if (function_exists($plugin . '_plugin_admin')) {
                        goaway(z_root() . '/admin/addons/' . $plugin);
                    }
                }
                goaway(z_root() . '/admin/addons');
            }

            // display plugin details

            if (in_array($plugin, Addon::list_installed())) {
                $status = 'on';
                $action = t('Disable');
            } else {
                $status = 'off';
                $action = t('Enable');
            }

            $readme = null;
            if (is_file("addon/$plugin/README.md")) {
                $readme = file_get_contents("addon/$plugin/README.md");
                $readme = MarkdownExtra::defaultTransform($readme);
            } elseif (is_file("addon/$plugin/README")) {
                $readme = "<pre>" . file_get_contents("addon/$plugin/README") . "</pre>";
            }

            $admin_form = '';

            $r = q(
                "select * from addon where plugin_admin = 1 and aname = '%s' limit 1",
                dbesc($plugin)
            );

            if ($r) {
                @require_once("addon/$plugin/$plugin.php");
                if (function_exists($plugin . '_plugin_admin')) {
                    $func = $plugin . '_plugin_admin';
                    $func($admin_form);
                }
            }


            $t = Theme::get_template('admin_plugins_details.tpl');
            return replace_macros($t, array(
                '$title' => t('Administration'),
                '$page' => t('Addons'),
                '$toggle' => t('Toggle'),
                '$settings' => t('Settings'),
                '$baseurl' => z_root(),

                '$plugin' => $plugin,
                '$status' => $status,
                '$action' => $action,
                '$info' => $info,
                '$str_author' => t('Author: '),
                '$str_maintainer' => t('Maintainer: '),
                '$str_minversion' => t('Minimum project version: '),
                '$str_maxversion' => t('Maximum project version: '),
                '$str_minphpversion' => t('Minimum PHP version: '),
                '$str_serverroles' => t('Compatible Server Roles: '),
                '$str_requires' => t('Requires: '),
                '$disabled' => t('Disabled - version incompatibility'),

                '$admin_form' => $admin_form,
                '$function' => 'addons',
                '$screenshot' => '',
                '$readme' => $readme,

                '$form_security_token' => get_form_security_token('admin_addons'),
            ));
        }


        /*
         * List plugins
         */
        $plugins = [];
        $files = glob('addon/*/');
        if ($files) {
            foreach ($files as $file) {
                if ($file === 'addon/vendor/') {
                    continue;
                }
                if (is_dir($file)) {
                    list($tmp, $id) = array_map('trim', explode('/', $file));
                    $info = Addon::get_info($id);
                    $enabled = in_array($id, Addon::list_installed());
                    $x = Addon::check_versions($info);

                    // disable plugins which are installed but incompatible versions

                    if ($enabled && !$x) {
                        $enabled = false;
                        $idz = array_search($id, Addon::list_installed());
                        if ($idz !== false) {
                            Addon::uninstall($id);
                        }
                    }
                    $info['disabled'] = 1 - intval($x);

                    $plugins[] = array($id, (($enabled) ? "on" : "off"), $info);
                }
            }
        }

        usort($plugins, 'self::plugin_sort');

        $allowManageRepos = false;
        if (is_writable('extend/addon') && is_writable('cache')) {
            $allowManageRepos = true;
        }

        $admin_plugins_add_repo_form = replace_macros(
            Theme::get_template('admin_plugins_addrepo.tpl'),
            array(
                '$post' => 'admin/addons/addrepo',
                '$desc' => t('Enter the public git repository URL of the addon repo.'),
                '$repoURL' => array('repoURL', t('Addon repo git URL'), '', ''),
                '$repoName' => array('repoName', t('Custom repo name'), '', '', t('(optional)')),
                '$submit' => t('Download Addon Repo')
            )
        );
        $newRepoModalID = random_string(3);
        $newRepoModal = replace_macros(
            Theme::get_template('generic_modal.tpl'),
            array(
                '$id' => $newRepoModalID,
                '$title' => t('Install new repo'),
                '$ok' => t('Install'),
                '$cancel' => t('Cancel')
            )
        );

        $reponames = $this->listAddonRepos();
        $addonrepos = [];
        foreach ($reponames as $repo) {
            $addonrepos[] = array('name' => $repo, 'description' => '');
            /// @TODO Parse repo info to provide more information about repos
        }

        $t = Theme::get_template('admin_plugins.tpl');
        return replace_macros($t, array(
            '$title' => t('Administration'),
            '$page' => t('Addons'),
            '$submit' => t('Submit'),
            '$baseurl' => z_root(),
            '$function' => 'addons',
            '$plugins' => $plugins,
            '$disabled' => t('Disabled - version incompatibility'),
            '$form_security_token' => get_form_security_token('admin_addons'),
            '$allowManageRepos' => $allowManageRepos,
            '$managerepos' => t('Manage Repos'),
            '$installedtitle' => t('Installed Addon Repositories'),
            '$addnewrepotitle' => t('Install a New Addon Repository'),
            '$expandform' => false,
            '$form' => $admin_plugins_add_repo_form,
            '$newRepoModal' => $newRepoModal,
            '$newRepoModalID' => $newRepoModalID,
            '$addonrepos' => $addonrepos,
            '$repoUpdateButton' => t('Update'),
            '$repoBranchButton' => t('Switch branch'),
            '$repoRemoveButton' => t('Remove')
        ));
    }

    public function listAddonRepos()
    {
        $addonrepos = [];
        $addonDir = 'extend/addon/';
        if (is_dir($addonDir)) {
            if ($handle = opendir($addonDir)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {
                        $addonrepos[] = $entry;
                    }
                }
                closedir($handle);
            }
        }
        return $addonrepos;
    }

    public static function plugin_sort($a, $b)
    {
        return (strcmp(strtolower($a[2]['name']), strtolower($b[2]['name'])));
    }
}
