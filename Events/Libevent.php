<?php
/**
 * Event库
 *
 * @author    chain01
 * 
 */
namespace PHPiot\Events;

use PHPiot\Worker;

/**
 * 事件循环的库
 */
class Libevent implements EventInterface
{
    /**
     * 库
     *
     * @var resource
     */
    protected $_eventBase = null;

    /**
     * 所有侦听/写入事件的侦听器
     *
     * @var array
     */
    protected $_allEvents = array();

    /**
     * select中断侦听器
     *
     * @var array
     */
    protected $_eventSignal = array();

    /**
     * 所有定时器事件侦听器
     * [func, args, event, flag, time_interval]
     *
     * @var array
     */
    protected $_eventTimer = array();

    /**
     * 构建
     */
    public function __construct()
    {
        $this->_eventBase = event_base_new();
    }

    /**
     * {@inheritdoc}
     */
    public function add($fd, $flag, $func, $args = array())
    {
        switch ($flag) {
            case self::EV_SIGNAL:
                $fd_key                      = (int)$fd;
                $real_flag                   = EV_SIGNAL | EV_PERSIST;
                $this->_eventSignal[$fd_key] = event_new();
                if (!event_set($this->_eventSignal[$fd_key], $fd, $real_flag, $func, null)) {
                    return false;
                }
                if (!event_base_set($this->_eventSignal[$fd_key], $this->_eventBase)) {
                    return false;
                }
                if (!event_add($this->_eventSignal[$fd_key])) {
                    return false;
                }
                return true;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                $event    = event_new();
                $timer_id = (int)$event;
                if (!event_set($event, 0, EV_TIMEOUT, array($this, 'timerCallback'), $timer_id)) {
                    return false;
                }

                if (!event_base_set($event, $this->_eventBase)) {
                    return false;
                }

                $time_interval = $fd * 1000000;
                if (!event_add($event, $time_interval)) {
                    return false;
                }
                $this->_eventTimer[$timer_id] = array($func, (array)$args, $event, $flag, $time_interval);
                return $timer_id;

            default :
                $fd_key    = (int)$fd;
                $real_flag = $flag === self::EV_READ ? EV_READ | EV_PERSIST : EV_WRITE | EV_PERSIST;

                $event = event_new();

                if (!event_set($event, $fd, $real_flag, $func, null)) {
                    return false;
                }

                if (!event_base_set($event, $this->_eventBase)) {
                    return false;
                }

                if (!event_add($event)) {
                    return false;
                }

                $this->_allEvents[$fd_key][$flag] = $event;

                return true;
        }

    }

    /**
     * {@inheritdoc}
     */
    public function del($fd, $flag)
    {
        switch ($flag) {
            case self::EV_READ:
            case self::EV_WRITE:
                $fd_key = (int)$fd;
                if (isset($this->_allEvents[$fd_key][$flag])) {
                    event_del($this->_allEvents[$fd_key][$flag]);
                    unset($this->_allEvents[$fd_key][$flag]);
                }
                if (empty($this->_allEvents[$fd_key])) {
                    unset($this->_allEvents[$fd_key]);
                }
                break;
            case  self::EV_SIGNAL:
                $fd_key = (int)$fd;
                if (isset($this->_eventSignal[$fd_key])) {
                    event_del($this->_eventSignal[$fd_key]);
                    unset($this->_eventSignal[$fd_key]);
                }
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                // 这里 fd 为timerid 
                if (isset($this->_eventTimer[$fd])) {
                    event_del($this->_eventTimer[$fd][2]);
                    unset($this->_eventTimer[$fd]);
                }
                break;
        }
        return true;
    }

    /**
     * 定时器回调
     *
     * @param mixed $_null1
     * @param int   $_null2
     * @param mixed $timer_id
     */
    protected function timerCallback($_null1, $_null2, $timer_id)
    {
        if ($this->_eventTimer[$timer_id][3] === self::EV_TIMER) {
            event_add($this->_eventTimer[$timer_id][2], $this->_eventTimer[$timer_id][4]);
        }
        try {
            call_user_func_array($this->_eventTimer[$timer_id][0], $this->_eventTimer[$timer_id][1]);
        } catch (\Exception $e) {
            Worker::log($e);
            exit(250);
        } catch (\Error $e) {
            Worker::log($e);
            exit(250);
        }
        if (isset($this->_eventTimer[$timer_id]) && $this->_eventTimer[$timer_id][3] === self::EV_TIMER_ONCE) {
            $this->del($timer_id, self::EV_TIMER_ONCE);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearAllTimer()
    {
        foreach ($this->_eventTimer as $task_data) {
            event_del($task_data[2]);
        }
        $this->_eventTimer = array();
    }

    /**
     * {@inheritdoc}
     */
    public function loop()
    {
        event_base_loop($this->_eventBase);
    }

    /**
     * 销毁循环
     *
     * @return void
     */
    public function destroy()
    {
        foreach ($this->_eventSignal as $event) {
            event_del($event);
        }
    }

    /**
     * 获取计时器计数
     *
     * @return integer
     */
    public function getTimerCount()
    {
        return count($this->_eventTimer);
    }
}

