<?php
/**
 * Plugin Name: Excel to WordPress Publisher
 * Plugin URI: https://www.74110.net
 * Description: 批量从Excel导入文章内容到WordPress，支持图片上传和字段映射。
 * Version: 1.0.0
 * Author: Mr. T
 * Author URI: https://www.74110.net
 * Text Domain: excel-to-wp-publisher
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('ETWP_VERSION', '1.0.0');
define('ETWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ETWP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETWP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 包含必要的文件
require_once ETWP_PLUGIN_DIR . 'includes/class-excel-to-wp-publisher.php';
require_once ETWP_PLUGIN_DIR . 'includes/class-excel-to-wp-publisher-pinyin.php';

// 激活插件时的钩子
register_activation_hook(__FILE__, array('Excel_To_WP_Publisher', 'activate'));

// 停用插件时的钩子
register_deactivation_hook(__FILE__, array('Excel_To_WP_Publisher', 'deactivate'));

/**
 * 启动插件
 *
 * @since 1.0.0
 */
function run_excel_to_wp_publisher() {
    $plugin = new Excel_To_WP_Publisher();
    $plugin->run();
}

// 运行插件
run_excel_to_wp_publisher();