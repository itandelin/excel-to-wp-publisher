<?php
/**
 * 注册所有插件的动作和过滤器
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 */

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 注册所有插件的动作和过滤器。
 *
 * 维护插件使用的所有钩子的列表，
 * 并在适当的时候运行它们。
 *
 * @package    Excel_To_WP_Publisher
 * @author     WordPress Developer
 */
class Excel_To_WP_Publisher_Loader {

    /**
     * 要注册到WordPress的动作数组
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $actions    要注册到WordPress的动作
     */
    protected $actions;

    /**
     * 要注册到WordPress的过滤器数组
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $filters    要注册到WordPress的过滤器
     */
    protected $filters;

    /**
     * 初始化集合，用于存储动作和过滤器
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * 将新动作添加到要注册到WordPress的动作集合中
     *
     * @since    1.0.0
     * @param    string               $hook             钩子的名称
     * @param    object               $component        引用对象的实例
     * @param    string               $callback         定义在$component中的回调函数的名称
     * @param    int                  $priority         可选。钩子执行的优先级。默认为10
     * @param    int                  $accepted_args    可选。回调接受的参数数量。默认为1
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * 将新过滤器添加到要注册到WordPress的过滤器集合中
     *
     * @since    1.0.0
     * @param    string               $hook             钩子的名称
     * @param    object               $component        引用对象的实例
     * @param    string               $callback         定义在$component中的回调函数的名称
     * @param    int                  $priority         可选。钩子执行的优先级。默认为10
     * @param    int                  $accepted_args    可选。回调接受的参数数量。默认为1
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * 用于将动作和过滤器注册到单个集合的实用函数
     *
     * @since    1.0.0
     * @access   private
     * @param    array                $hooks            要注册的钩子的集合
     * @param    string               $hook             要注册的钩子的名称
     * @param    object               $component        引用对象的实例
     * @param    string               $callback         定义在$component中的回调函数的名称
     * @param    int                  $priority         钩子执行的优先级
     * @param    int                  $accepted_args    回调接受的参数数量
     * @return   array                                  钩子集合，可能已修改
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * 注册插件中定义的钩子
     *
     * @since    1.0.0
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
}