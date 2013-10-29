<?php
/**
 * logp logger for php
 */
class logp
{
    /*
     * LOG_EMEG system is unusable
     */
    const LOG_EMERG     = 0;
    /*
     * LOG_ALERT action must be taken immediately
     */
    const LOG_ALERT     = 1;
    /*
     * LOG_CRIT critical conditions
     */
    const LOG_CRIT      = 2;
    /*
     * LOG_ERR  error conditions
     */
    const LOG_ERR       = 3;
    /*
     * LOG_WARNING  warning conditions
     */
    const LOG_WARNING   = 4;
    /*
     * LOG_NOTICE   normal, but significant, condition
     */
    const LOG_NOTICE    = 5;
    /*
     * LOG_INFO informational message
     */
    const LOG_INFO      = 6;
    /*
     * LOG_DEBUG debug-level message
     */
    const LOG_DEBUG     = 7;

    /*
     * default log path
     */
    private static $log_path = '/tmp';

    /*
     * APP_NAME
     */
    private static $app_name = 'app';

    /*
     * mini level
     */
    private static $mini_level = self::LOG_INFO;

    /*
     * log config set mini level
     */
    public static function config($_log_path, $_app_name = 'app',
        $_mini_level = self::LOG_INFO)
    {/*{{{*/
        if(!empty($_log_path))
        {/*{{{*/
            $_log_path = rtrim($_log_path, DIRECTORY_SEPARATOR);
            if(is_dir($_log_path))
            {
                self::$log_path = $_log_path;
            }
            else if(@mkdir($_log_path, 0755, true))
            {
                self::$log_path = $_log_path;
            }
        }/*}}}*/

        if(!empty($_app_name))
        {
            self::$app_name = $_app_name;
        }

        if(!empty($_mini_level))
        {
            self::$mini_level = $_mini_level;
        }
    }/*}}}*/

    /*
     * log all level messages, default is info
     */
    public static function log($file, $line, $message, $params,
        $level = self::LOG_INFO)
    {/*{{{*/
        if($level > self::$mini_level)
            return ;
        $filename = self::prepare_filename($level);
        $msg = self::prepare_message($file, $line, $message, $params, $level);
        if(!is_dir(self::$log_path))
        {
            if(!@mkdir(self::$log_path, 0755, true))
            {
                trigger_error(self::$log_path . " is not a directory",
                    E_USER_ERROR);
                self::$log_path = '/tmp';
            }
        }
        $logfile = self::$log_path . DIRECTORY_SEPARATOR . $filename;
        if(!file_exists($logfile))
        {
            if(!@touch($logfile))
            {
                $errinfo = error_get_last();
                trigger_error("type:" . $errinfo['type'] . "|message:" .
                    $errinfo['message'], E_USER_ERROR);
            }
        }
        file_put_contents($logfile, $msg, FILE_APPEND | LOCK_EX);
    }/*}}}*/

    /*
     * prepare logfile name
     */
    private static function prepare_filename($level)
    {/*{{{*/
        //filename: '%appname%.%level%.%date%.log';
        return sprintf('%s.%s.%s.log', self::$app_name,
            self::strlevel($level), date('Ymd'));
    }/*}}}*/

    /*
     * prepare message
     */
    private static function prepare_message($file, $line, $message,
        $params, $level)
    {/*{{{*/
        $message_template = "[%addr%] [%datetime%] [%level%] [%session%]" .
            " [%pid%] [%memory%] [%file%:%line%] [%params%] %message%\n";

        $message_placeholders = array(
                    '%addr%'    => '%s',
                    '%datetime%'=> '%s',
                    '%level%'   => '%s',
                    '%session%' => '%s',
                    '%pid%'     => '%s',
                    '%memory%'  => 'mem(real):%0.2fMB',
                    '%file%'    => '%s',
                    '%line%'    => '%s',
                    '%params%'  => '%s',
                    '%message%' => '%s',
                );

        $msg_tmp = str_replace(array_keys($message_placeholders),
            $message_placeholders, $message_template);

        $msg_params = array();
        $msg_params['%addr%']     = self::get_client_ip();
        $msg_params['%datetime%'] = date('Y-m-d H:i:s');
        $msg_params['%level%']    = self::strlevel($level);
        $msg_params['%session%']  = isset($_SESSION) ? session_id() : '-';
        $msg_params['%pid%']      = getmypid();
        $msg_params['%memory%']   = memory_get_usage(true) / 1000000;
        $msg_params['%file%']     = $file;
        $msg_params['%line%']     = $line;
        $msg_params['%params%']   = empty($params) ? '-' : serialize($params);
        $msg_params['%message%']  = str_replace("\n", " ", $message);

        if($level >= self::LOG_DEBUG)
        {/*{{{*/
            $traces = debug_backtrace();

            //shift two logp function call trace
            array_shift($traces);
            array_shift($traces);

            $trace_msg = '';
            foreach($traces as $index => $trace)
            {
                $trace_msg .= ' trace=>#' . $index . $trace['class'] .
                    $trace['type'] . $trace['function'] .
                    " {$trace['file']}:{$trace['line']}:" .
                    serialize($trace['args']);
            }
            $msg_params['%message%'] .= ' debugtrace=[' . trim($trace_msg) . ']';
        }/*}}}*/

        $msg = vsprintf($msg_tmp, $msg_params);

        return $msg;
    }/*}}}*/

    /*
     * log warning messages
     */
    public static function warn($file, $line, $message, $params = array())
    {/*{{{*/
        self::log($file, $line, $message, $params, self::LOG_WARNING);
    }/*}}}*/

    /*
     * log error messages
     */
    public static function error($file, $line, $message, $params = array())
    {/*{{{*/
        self::log($file, $line, $message, $params, self::LOG_ERR);
    }/*}}}*/

    /*
     * log info message
     */
    public static function info($file, $line, $message, $params = array())
    {/*{{{*/
        self::log($file, $line, $message, $params, self::LOG_INFO);
    }/*}}}*/

    /*
     * log debug messages
     */
    public static function debug($file, $line, $message, $params = array())
    {/*{{{*/
        self::log($file, $line, $message, $params, self::LOG_DEBUG);
    }/*}}}*/

    /*
     * level2str convert level to string,
     * set unknown if not exists 
     */
    private static function strlevel($level)
    {/*{{{*/
        switch($level)
        {/*{{{*/
            case self::LOG_EMERG:
                return 'emerg';
            case self::LOG_ALERT:
                return 'alert';
            case self::LOG_CRIT:
                return 'crit';
            case self::LOG_ERR:
                return 'error';
            case self::LOG_WARNING:
                return 'warn';
            case self::LOG_NOTICE:
                return 'notice';
            case self::LOG_INFO:
                return 'info';
            case self::LOG_DEBUG:
                return 'debug';
            default:
                trigger_error('Unknown log level [' . $level . ']',
                    E_USER_WARNING);
                return 'unknown';
        }/*}}}*/
    }/*}}}*/

    /*
     * get client ip address
     */
    private static function get_client_ip()
    {/*{{{*/
        if(!empty($_SERVER['HTTP_CLIENT_IP']))
        {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else 
        {
            return isset($_SERVER['REMOTE_ADDR']) ?
                $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        }
    }/*}}}*/
}
