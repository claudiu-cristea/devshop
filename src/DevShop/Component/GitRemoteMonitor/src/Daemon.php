<?php

namespace DevShop\Component\GitRemoteMonitor;

use Robo\Common\ConfigAwareTrait;

class Daemon extends \Core_Daemon
{
    use ConfigAwareTrait;

    protected $install_instructions = [
    'Run as the user that will be cloning code.',
    'Create a writable folder at /var/log/git-remote-monitor.',
    ];

  /**
   * How many seconds to wait.
   * @var int
   */
    protected $loop_interval = 3;

    /**
     * @var string
     */
    private static $logdir = '/var/log/git-remote-monitor';

  /**
   * The list of git remotes and their ls-remote output.
   * @var array
   */
    protected $remotes = array();

  /**
   * @param array $remotes
   */
    public function saveRemotes(Array $remotes)
    {
        $this->remotes = $remotes;
    }

  /**
   * The only plugin we're using is a simple file-based lock to prevent 2 instances from running
   */
    protected function setup_plugins()
    {
        $this->plugin('Lock_File');
        $this->Lock_File->daemon_name = '.git-remote-monitor';
    }

  /**
   * This is where you implement any once-per-execution setup code.
   * @return void
   * @throws \Exception
   */
    protected function setup()
    {
        $this->log("================================");
        $this->log("Git Remote Monitor Daemon: setup");
        $this->log("--------------------------------");
        $this->log("Logging to: " . $this->log_file());
    }

  /**
   * This is where you implement the tasks you want your daemon to perform.
   * This method is called at the frequency defined by loop_interval.
   *
   * @return void
   */
    protected function execute()
    {
        static $remotes_list_last = '';
        static $called = 1;

      // Call git-remote-monitor remotes via shell, so that all of the Robo config is loaded and we don't have to integrate the remote daemon classes with robo classes.
        $remotes = [];
        $grm_root = dirname(dirname(__FILE__));
        $command = "$grm_root/git-remote-monitor remotes";

        // @TODO: Log only if debug is enabled.
        // $this->log("> $command", 'command');
        exec($command, $remotes, $exit);
        if ($exit != 0) {
            $this->fatal_error('git-remote-monitor remotes command failed: ' . implode(PHP_EOL, $remotes));
        }
        $count = count($remotes);
        $remotes_list = implode(PHP_EOL, $remotes);

        // @TODO: Log only if debug is enabled.
        // $this->log("Results of remotes call: $remotes_list", 'debug');

        if ($remotes_list != $remotes_list_last) {
            $this->log("================================");
            $this->log("Remotes list updated: Now watching $count git remotes.");
            $this->log("--------------------------------");

            // Log an entry for every remote being monitored.
            // @TODO: Integrate config so we don't have this hard coded.
            $remotes_path = $_SERVER['HOME'] . '/.grm/remotes/';
            foreach ($remotes as $url) {
                $file = $remotes_path . GitRemote::getSlug($url) . '.yml';
                $this->log("$url | $file");
            }
        } elseif (empty($count)) {
            $this->error("No remotes output from 'git-remote-monitor remotes' command.");
        }

        // Queue tasks for every remote.
        foreach ($remotes as $url) {
            $this->task(new Task($url));
        }

        // Log every 10th call.
        if ($called % 10 == 0) {
            $this->log("Loaded list $called times.");
        }

        $remotes_list_last = $remotes_list;
        $called++;
    }

    /**
     * @param string $dir the directory to store logfiles in.
     * @return void
     */
    public static function setLogdir($dir)
    {
        if (!empty($dir)) {
            self::$logdir = $dir;
        }
    }

  /**
   * Dynamically build the file name for the log file. This simple algorithm
   * will rotate the logs once per day and try to keep them in a central /var/log location.
   * @return string
   */
    protected function log_file()
    {
        $dir = self::$logdir;
        if (@file_exists($dir) == false) {
            @mkdir($dir, 0777, true);
        }

        return $dir . '/log_' . date('Ymd');
    }
}