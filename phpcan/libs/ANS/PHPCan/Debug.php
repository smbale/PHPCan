<?php
/**
* phpCan - http://idc.anavallasuiza.com/
*
* phpCan is released under the GNU Affero GPL version 3
*
* More information at license.txt
*/

namespace ANS\PHPCan;

defined('ANS') or die();

class Debug
{
    public $settings = array();

    private $errors = array();
    private $time;
    private $time_pause = 0;
    private $time_store = array();

    /**
     * public function __construct ([bool $play_timing])
     *
     * return boolean
     */
    public function __construct ($play_timing = true)
    {
        if ($play_timing) {
            $this->playTiming();
        }
    }

    /**
     * public function setSettings ([array/string $settings], [string $autoglobal])
     *
     * return boolean
     */
    public function setSettings ($settings = null, $autoglobal = '')
    {
        global $Config;

        if ($autoglobal) {
            $Config->config['autoglobal'][] = $autoglobal;
        }

        if (!$this->settings || is_null($settings)) {
            $this->settings = array(
                'save' => false,
                'save_path' => (BASE_PATH.$Config->phpcan_paths['logs']),
                'print' => true,
                'print_styles' => (LIBS_PATH.'ANS/PHPCan/Utils/debug_styles.php'),
                'fatal_error_template' => (LIBS_PATH.'ANS/PHPCan/Utils/error_template.php'),
                'redirect' => true
            );
        }

        if (is_string($settings) && $Config->config[$settings]) {
            $settings = $Config->config[$settings];
        } elseif (is_array($settings)) {
            $settings = $settings;
        } else {
            $settings = array();
        }

        $this->settings = array_merge($this->settings, $settings);

        //Check if save_path is writable
        if ($this->settings['save'] && !is_writable($this->settings['save_path'])) {
            $this->fatalError(__('The log folder "%s" is not writtable!', $this->settings['save_path']));
        }

        if ($this->settings['ip']) {
            $this->settings['ip'] = is_array($this->settings['ip']) ? $this->settings['ip'] : array($this->settings['ip']);
            $this->settings['print'] = in_array(ip(), $this->settings['ip']);
        } else {
            $this->settings['ip'] = array();
        }

        return true;
    }

    /**
     * private function debug_backtrace (array $debug)
     *
     * return array
     */
    private function debug_backtrace ($debug)
    {
        unset($debug['object'], $debug['args']);

        foreach ($debug as $key => $value) {
            if (is_array($value)) {
                $debug[$key] = $this->debug_backtrace($value);
            }
        }

        return $debug;
    }

    public function canPrint ()
    {
        return $this->settings['print'] ? true : false;
    }

    /**
     * private function error (string $where, string $messsage)
     *
     * save a error message
     */
    public function error ($where, $message)
    {
        $this->errors[] = array(
            'where' => $where,
            'info' => $this->debug_backtrace(debug_backtrace()),
            'message' => $message
        );

        if ($this->settings['print']) {
            $this->e($message, $where);
        }

        $this->store($message, $where);

        return true;
    }

    /**
     * private function fatalError ($error)
     *
     * generate a fatal error and die
     */
    public function fatalError ($error)
    {
        include ($this->settings['fatal_error_template']);

        $this->store($error, 'fatal_errors');

        header(getenv('SERVER_PROTOCOL').' 500 Internal Server Error');

        exit;
    }

    /**
     * public function showErrors ()
     *
     * print all saved errors
     */
    public function showErrors ()
    {
        global $Errors;

        $public_errors = $Errors->get();

        if (!$this->settings['print'] || (!$this->errors && !$public_errors)) {
            return false;
        }

        if ($public_errors) {
            $this->e($public_errors, __('Public errors'));
        }

        include_once ($this->settings['print_styles']);

        foreach ($this->errors as $error) {
            $error['info'] = array_reverse($error['info']);

            echo '<div class="phpcan_debug_show">';

            if ($error['message']) {
                echo '<p class="msg">';

                if (is_array($error['message'])) {
                    print_r($error['message']);
                } else {
                    echo $error['message'];
                }

                echo '</p>';
            }

            echo '<ol>';

            foreach ($error['info'] as $v) {
                switch ($v['function']) {
                    case 'include':
                    case 'include_once':
                    case 'require':
                    case 'require_once':
                        continue 2;
                        break;

                    default:
                        echo '<li><dl>';
                        echo '<dt>File: </dt>';
                        echo '<dd>'.$v['file'].' <strong>(line '.$v['line'].')</strong></dd>';
                        echo '<dt>Function: </dt>';
                        echo '<dd><strong>'.$v['function'].'</strong> (';
                        echo '<code>';

                        if (is_array($v['args'][0])) {
                            print_r($v['args'][0]);
                        } else {
                            echo '"'.$v['args'][0].'"';
                        }

                        echo '</code>';
                        echo ')</dd>';
                        echo '</dl></li>';
                }
            }

            echo '</ol>';
            echo '</div>';
        }
    }

    /**
     * public function showData ([string $filter])
     *
     * Return none
     */
    public function showData ($filter = '')
    {
        global $Vars;

        if (!$this->settings['print'] || !$Vars->data) {
            return false;
        }

        $variables = array();
        $filter = func_get_args();

        if ($filter) {
            foreach ($filter as $v) {
                if (in_array($v, $Vars->data)) {
                    $variables[] = $v;
                }
            }
        } else {
            $variables = $Vars->data;
        }

        include_once($this->settings['print_styles']);

        echo '<hr />';
        echo '<div class="phpcan_debug_show">';

        echo '<ul class="phpcan_debug_menu">';

        foreach ($variables as $key) {
            echo '<li><a href="#phpcan_debug_data_'.$key.'">'.$key.'</a></li>';
        }

        echo '</ul>';

        foreach ($variables as $key) {
            global $$key;

            $value = $$key;

            echo '<h1 id="phpcan_debug_data_'.$key.'">$'.$key.'</h1>';
            echo '<code>';

            print_r($value);

            echo '</code>';
        }

        echo '</div>';
    }

    /**
     * public function e ()
     *
     * Return none
     */
    public function e ($var, $title = '')
    {
        if (!$this->settings['print']) {
            return false;
        }

        include_once($this->settings['print_styles']);

        $where = $this->debug_backtrace(debug_backtrace());

        echo '<hr />';
        echo '<pre class="phpcan_debug_e phpcan_debug_'.$style.'">';

        if ($title) {
            echo '<h1>'.$title.' ('.gettype($var).')</h1>'."\n";
        } else {
            echo '<h1>'.gettype($var).'</h1>'."\n";
        }

        foreach ($where as $line => $row) {
            echo '<span class="subtitle">'.$line.' / '.$row['file'].' <strong>(line '.$row['line'].')</strong></span>'."\n";
        }

        echo '<br />';

        if (is_string($var)) {
            echo htmlspecialchars($var);
        } elseif (is_bool($var)) {
            echo $var ? 'true' : 'false';
        } else {
            ob_start();

            print_r($var);

            $var = htmlspecialchars(ob_get_contents());

            ob_end_clean();

            echo $var;
        }

        echo '</pre>';
    }

    /**
     * private function store (string $message, [string $file])
     *
     * store into files the phpCan messages
     */
    private function store ($message, $file = '')
    {
        if (!$this->settings['save']) {
            return true;
        }

        $file = $this->settings['save_path'].($file ? $file : 'debugs').'.php';

        //Check if the file is writable
        if (is_file($file) && !is_writable($file)) {
            $this->fatalError(__('The file "%s" is not writtable!', $file));
        }

        $where = $this->debug_backtrace(debug_backtrace());

        $text = "\n\n".'-- '.date('Y/m/d H:i:s').' --------------------------'."\n\n";

        foreach ($where as $row) {
            $text .= 'File: '.$row['file'].' | Line: '.$row['line']."\n";
        }

        if (is_array($message)) {
            ob_start();

            print_r($message);

            $message = ob_get_flush();
        }

        $text .= "\n".$message;

        file_put_contents($file, $text, FILE_APPEND);
    }

    /**
     * public function resetTiming (void)
     */
    public function resetTiming ()
    {
        $this->time_store = array();
        $this->time = microtime(true);
    }

    /**
     * public function pauseTiming (void)
     */
    public function pauseTiming ()
    {
        $this->time_pause = microtime(true);
    }

    /**
     * public function playTiming (void)
     */
    public function playTiming ()
    {
        if ($this->time_pause > 0) {
            $this->time += (microtime(true) - $this->time_pause);
            $this->time_pause = 0;
        } elseif (!$this->time_store) {
            $this->time = microtime(true);
            $where = array();

            foreach ($this->debug_backtrace(debug_backtrace()) as $row) {
                $where[] = $row['file'].' (line '.$row['line'].')';
            }

            $this->time_store[] = array(
                'text' => 'Timing start',
                'total_time' => 0,
                'time_from_previous' => 0,
                'where' => $where
            );
        }
    }

    /**
     * public function markTiming ([string $text])
     */
    public function markTiming ($text = '')
    {
        $text = $text ? $text : 'Timing info';
        $total_time = microtime(true) - $this->time;

        $where = array();

        foreach ($this->debug_backtrace(debug_backtrace()) as $row) {
            $where[] = $row['file'].' (line '.$row['line'].')';
        }

        $time_from_previous = end($this->time_store);
        $time_from_previous = $total_time - $time_from_previous['total_time'];

        $this->playTiming();

        return $this->time_store[] = array(
            'text' => $text,
            'total_time' => $total_time,
            'time_from_previous' => $time_from_previous,
            'where' => $where
        );
    }

    /**
     * public function showTiming ([string $text])
     */
    public function showTiming ($text = 'Show timing')
    {
        $this->markTiming($text);

        $this->e($this->time_store, 'Show timing all');
    }

    /**
     * public function getTiming ()
     */
    public function getTiming ($text = 'Get timing')
    {
        $this->markTiming($text);

        return $this->time_store;
    }

    /**
    * public function getMemoryUsage ()
    */
    public function getMemoryUsage ()
    {
        $size = memory_get_peak_usage(true);
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');

        return sprintf('%.2f %s', $size / pow(1024, ($i = floor(log($size, 1024)))), $unit[$i]);
    }
}
