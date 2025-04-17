<?php
/**
 * 插件的主类文件
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 */

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 插件的主类
 *
 * 这个类负责定义国际化、管理页面钩子和公共面向站点的钩子。
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 * @author     WordPress Developer
 */
class Excel_To_WP_Publisher {

    /**
     * 插件加载器，负责维护和注册所有钩子
     *
     * @since    1.0.0
     * @access   protected
     * @var      Excel_To_WP_Publisher_Loader    $loader    维护所有钩子的对象
     */
    protected $loader;

    /**
     * 插件的唯一标识符
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    插件的唯一标识符
     */
    protected $plugin_name;

    /**
     * 插件的当前版本
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    插件的当前版本
     */
    protected $version;

    /**
     * 定义插件的核心功能
     *
     * 设置插件名称和版本，加载依赖项、定义国际化功能
     * 以及设置管理和公共钩子。
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->plugin_name = 'excel-to-wp-publisher';
        $this->version = ETWP_VERSION;

        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    /**
     * 加载插件所需的依赖项
     *
     * 包括用于编排插件功能的类和用于定义国际化功能的类。
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // 加载插件加载器
        require_once ETWP_PLUGIN_DIR . 'includes/class-excel-to-wp-publisher-loader.php';
        
        // 加载管理区域功能
        require_once ETWP_PLUGIN_DIR . 'admin/class-excel-to-wp-publisher-admin.php';
        
        // 加载Excel处理类
        require_once ETWP_PLUGIN_DIR . 'includes/class-excel-to-wp-publisher-excel-handler.php';
        
        // 加载WordPress文章处理类
        require_once ETWP_PLUGIN_DIR . 'includes/class-excel-to-wp-publisher-post-handler.php';
        
        // 加载图片处理类
        require_once ETWP_PLUGIN_DIR . 'includes/class-excel-to-wp-publisher-image-handler.php';

        $this->loader = new Excel_To_WP_Publisher_Loader();
    }

    /**
     * 注册与管理区域功能相关的所有钩子
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new Excel_To_WP_Publisher_Admin($this->get_plugin_name(), $this->get_version());
        
        // 注册样式和脚本
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // 注册管理菜单
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        
        // 注册设置
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        
        // 注册Excel上传处理
        $this->loader->add_action('admin_post_etwp_upload_excel', $plugin_admin, 'handle_excel_upload');
        
        // 注册文章导入处理
        $this->loader->add_action('admin_post_etwp_import_posts', $plugin_admin, 'handle_posts_import');
    }

    /**
     * 运行加载器以执行所有已注册的钩子
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * 插件的名称，用于国际化和显示
     *
     * @since     1.0.0
     * @return    string    插件名称
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * 引用加载器对象，用于添加功能到WordPress
     *
     * @since     1.0.0
     * @return    Excel_To_WP_Publisher_Loader    负责协调插件钩子的对象
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * 检索插件的版本号
     *
     * @since     1.0.0
     * @return    string    插件版本号
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * 插件激活时执行的功能
     *
     * @since    1.0.0
     */
    public static function activate() {
        // 激活时的操作
    }

    /**
     * 插件停用时执行的功能
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // 停用时的操作
    }
}