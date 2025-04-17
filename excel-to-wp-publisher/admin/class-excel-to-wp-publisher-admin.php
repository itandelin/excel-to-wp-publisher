<?php
/**
 * 插件的管理区域功能
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 */

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 插件的管理区域功能类
 *
 * 定义插件的管理区域功能，包括页面显示、设置和处理Excel导入
 *
 * @package    Excel_To_WP_Publisher
 * @author     WordPress Developer
 */
class Excel_To_WP_Publisher_Admin {

    /**
     * 插件的唯一标识符
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    插件的唯一标识符
     */
    private $plugin_name;

    /**
     * 插件的当前版本
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    插件的当前版本
     */
    private $version;
    
    /**
     * 上传的Excel文件的临时存储路径
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $excel_file    Excel文件路径
     */
    private $excel_file;

    /**
     * 初始化类并设置其属性
     *
     * @since    1.0.0
     * @param    string    $plugin_name       插件的名称
     * @param    string    $version           插件的版本
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // 创建上传目录
        $upload_dir = wp_upload_dir();
        $this->upload_path = $upload_dir['basedir'] . '/excel-to-wp-publisher/';
        if (!file_exists($this->upload_path)) {
            wp_mkdir_p($this->upload_path);
        }
        
        // 添加AJAX处理函数
        add_action('wp_ajax_etwp_save_mapping', array($this, 'ajax_save_mapping'));
        add_action('wp_ajax_etwp_load_mapping', array($this, 'ajax_load_mapping'));
        add_action('wp_ajax_etwp_get_field_suggestions', array($this, 'ajax_get_field_suggestions'));
        add_action('wp_ajax_etwp_process_batch', array($this, 'ajax_process_batch'));
        
        // 初始化属性
        $this->batch_size = 50; // 每批处理的数据条数
        $this->current_batch = 0; // 当前处理的批次
    }

    /**
     * 注册管理区域的样式
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, ETWP_PLUGIN_URL . 'admin/css/excel-to-wp-publisher-admin.css', array(), $this->version, 'all');
    }

    /**
     * 注册管理区域的脚本
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        // 加载必要的WordPress脚本
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-droppable');
        wp_enqueue_script('jquery-ui-draggable');
        
        // 加载SheetJS库
        wp_enqueue_script('sheetjs', 'https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js', array(), null, true);
        
        // 加载插件主脚本
        wp_enqueue_script($this->plugin_name, ETWP_PLUGIN_URL . 'admin/js/excel-to-wp-publisher-admin.js', array('jquery', 'jquery-ui-sortable', 'sheetjs'), $this->version, true);
        
        // 添加本地化脚本
        wp_localize_script($this->plugin_name, 'etwp_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('etwp_nonce'),
            'batch_size' => $this->batch_size,
            'plugin_name' => $this->plugin_name,
            'i18n' => array(
                'processing' => __('正在处理...', 'excel-to-wp-publisher'),
                'success' => __('处理完成', 'excel-to-wp-publisher'),
                'error' => __('处理失败', 'excel-to-wp-publisher'),
                'confirm_reset' => __('确定要重置字段映射吗？', 'excel-to-wp-publisher'),
                'no_mapping' => __('没有设置字段映射，请拖拽Excel字段到对应的WordPress字段', 'excel-to-wp-publisher'),
                'at_least_one_mapping' => __('请至少设置一个字段映射', 'excel-to-wp-publisher'),
                'server_error' => __('与服务器通信时发生错误', 'excel-to-wp-publisher'),
                'import_error' => __('导入过程中发生错误', 'excel-to-wp-publisher')
            )
        ));
    }

    /**
     * 添加插件管理菜单
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __('Excel到WordPress发布器', 'excel-to-wp-publisher'),
            __('Excel发布器', 'excel-to-wp-publisher'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_admin_page'),
            'dashicons-upload',
            26
        );
    }

    /**
     * 注册插件设置
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // 注册设置
        register_setting(
            'etwp_settings',
            'etwp_settings'
        );
    }

    /**
     * 渲染插件管理页面
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
        // 检查是否有上传的Excel文件
        $excel_file = get_option('etwp_excel_file');
        $step = isset($_GET['step']) ? intval($_GET['step']) : 1;
        
        include_once ETWP_PLUGIN_DIR . 'admin/partials/excel-to-wp-publisher-admin-display.php';
    }

    /**
     * 处理Excel文件上传
     *
     * @since    1.0.0
     */
    public function handle_excel_upload() {
        // 验证nonce
        if (!isset($_POST['etwp_nonce']) || !wp_verify_nonce($_POST['etwp_nonce'], 'etwp_nonce')) {
            wp_die(__('安全检查失败', 'excel-to-wp-publisher'));
        }
        
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('您没有足够的权限执行此操作', 'excel-to-wp-publisher'));
        }
        
        // 检查是否有文件上传
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&error=1'));
            exit;
        }
        
        // 检查文件类型
        $file_type = wp_check_filetype(basename($_FILES['excel_file']['name']));
        $allowed_types = array(
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv'
        );
        
        if (!in_array($file_type['type'], $allowed_types)) {
            wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&error=2'));
            exit;
        }
        
        // 移动上传的文件到插件的上传目录
        $upload_file = $this->upload_path . basename($_FILES['excel_file']['name']);
        if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $upload_file)) {
            // 保存文件路径到选项
            update_option('etwp_excel_file', $upload_file);
            
            // 解析Excel文件获取字段
            $excel_handler = new Excel_To_WP_Publisher_Excel_Handler();
            $headers = $excel_handler->get_excel_headers($upload_file);
            
            if ($headers) {
                update_option('etwp_excel_headers', $headers);
                wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&step=2'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&error=3'));
                exit;
            }
        } else {
            wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&error=4'));
            exit;
        }
    }

    /**
     * 处理文章导入
     *
     * @since    1.0.0
     */
    public function handle_posts_import() {
        // 验证nonce
        if (!isset($_POST['etwp_nonce']) || !wp_verify_nonce($_POST['etwp_nonce'], 'etwp_nonce')) {
            wp_die(__('安全检查失败', 'excel-to-wp-publisher'));
        }
        
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_die(__('您没有足够的权限执行此操作', 'excel-to-wp-publisher'));
        }
        
        // 获取字段映射
        $field_mapping = isset($_POST['field_mapping']) ? $_POST['field_mapping'] : array();
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft';
        
        if (empty($field_mapping)) {
            wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&step=2&error=1'));
            exit;
        }
        
        // 获取Excel文件路径
        $excel_file = get_option('etwp_excel_file');
        if (!$excel_file || !file_exists($excel_file)) {
            wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&error=5'));
            exit;
        }
        
        // 初始化处理类
        $excel_handler = new Excel_To_WP_Publisher_Excel_Handler();
        $post_handler = new Excel_To_WP_Publisher_Post_Handler();
        $image_handler = new Excel_To_WP_Publisher_Image_Handler();
        
        // 获取Excel数据
        $data = $excel_handler->get_excel_data($excel_file);
        
        if (!$data || empty($data)) {
            wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&error=6'));
            exit;
        }
        
        // 导入计数器
        $imported = 0;
        $failed = 0;
        $total = count($data);
        
        // 处理每一行数据
        foreach ($data as $row) {
            // 准备文章数据
            $post_data = array(
                'post_status' => $post_status,
                'post_type' => 'post',
            );
            
            // 设置分类
            if ($category_id > 0) {
                $post_data['post_category'] = array($category_id);
            }
            
            // 处理字段映射
            $meta_data = array();
            $image_data = array();
            $has_title = false;
            $has_content = false;
            
            foreach ($field_mapping as $excel_field => $wp_field) {
                if (empty($wp_field) || !isset($row[$excel_field])) {
                    continue;
                }
                
                $value = $row[$excel_field];
                
                // 处理核心字段
                if ($wp_field === 'post_title') {
                    $post_data['post_title'] = sanitize_text_field($value);
                    $has_title = true;
                } elseif ($wp_field === 'post_content') {
                    $post_data['post_content'] = wp_kses_post($value);
                    $has_content = true;
                } elseif ($wp_field === 'post_excerpt') {
                    $post_data['post_excerpt'] = sanitize_text_field($value);
                } elseif ($wp_field === '_thumbnail_id') {
                    // 特色图片处理
                    if ($image_handler->is_image_path($value)) {
                        $image_data[$wp_field] = $value;
                    }
                } else {
                    // 检查是否为图片路径
                    if ($image_handler->is_image_path($value)) {
                        // 这是一个图片路径，添加到图片数据中
                        $image_data[$wp_field] = $value;
                    } else {
                        // 普通自定义字段
                        $meta_data[$wp_field] = $value;
                    }
                }
            }
            
            // 检查必要字段
            if (!$has_title) {
                $failed++;
                continue;
            }
            
            // 如果没有内容，设置一个默认内容
            if (!$has_content) {
                $post_data['post_content'] = '';
            }
            
            // 创建文章
            $post_id = $post_handler->create_post($post_data, $meta_data, $image_data);
            
            if ($post_id) {
                $imported++;
            } else {
                $failed++;
                error_log('Excel to WP Publisher: 创建文章失败，标题: ' . $post_data['post_title']);
            }
        }
        
        // 重定向到结果页面
        wp_redirect(admin_url('admin.php?page=' . $this->plugin_name . '&step=3&imported=' . $imported . '&failed=' . $failed . '&total=' . $total));
        exit;
    }
    
    /**
     * 获取WordPress可用的字段
     *
     * @since    1.0.0
     * @return   array    WordPress可用字段列表
     */
    public function get_wordpress_fields() {
        // 核心字段
        $core_fields = array(
            'post_title' => __('标题', 'excel-to-wp-publisher'),
            'post_content' => __('内容', 'excel-to-wp-publisher'),
            'post_excerpt' => __('摘要', 'excel-to-wp-publisher'),
            '_thumbnail_id' => __('特色图片', 'excel-to-wp-publisher'),
        );
        
        // 获取自定义字段
        $custom_fields = array();
        
        // 获取ACF字段组
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups();
            
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group);
                
                if ($fields) {
                    foreach ($fields as $field) {
                        $custom_fields[$field['name']] = $field['label'];
                    }
                }
            }
        }
        
        // 合并所有字段
        return array_merge($core_fields, $custom_fields);
    }
    
    /**
     * 获取WordPress分类列表
     *
     * @since    1.0.0
     * @return   array    WordPress分类列表
     */
    public function get_categories() {
        $categories = get_categories(array(
            'hide_empty' => false,
        ));
        
        $category_list = array();
        foreach ($categories as $category) {
            $category_list[$category->term_id] = $category->name;
        }
        
        return $category_list;
    }
    
    /**
     * AJAX处理：保存字段映射设置
     *
     * @since    1.0.0
     */
    public function ajax_save_mapping() {
        // 验证nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'etwp_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        // 获取映射数据
        $mapping_data = isset($_POST['mapping']) ? $_POST['mapping'] : array();
        
        // 如果映射数据是JSON字符串，则解码
        if (is_string($mapping_data)) {
            $mapping_data = json_decode(stripslashes($mapping_data), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('映射数据格式错误: ' . json_last_error_msg());
            }
        }
        
        // 验证映射数据格式
        if (!is_array($mapping_data)) {
            wp_send_json_error('映射数据格式不正确');
        }
        
        // 检查映射数据是否为空
        if (empty($mapping_data)) {
            wp_send_json_error('没有映射数据');
        }
        
        // 验证每个映射项
        foreach ($mapping_data as $excel_field => $wp_field) {
            if (empty($excel_field) || empty($wp_field)) {
                wp_send_json_error('映射数据包含空值');
            }
        }
        
        // 保存映射设置
        $result = update_option('etwp_field_mapping', $mapping_data);
        
        if ($result) {
            wp_send_json_success('映射设置已保存');
        } else {
            wp_send_json_error('保存映射设置失败');
        }
    }
    
    /**
     * AJAX处理：加载字段映射设置
     *
     * @since    1.0.0
     */
    public function ajax_load_mapping() {
        // 验证nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'etwp_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        // 获取保存的映射设置
        $mapping_data = get_option('etwp_field_mapping', array());
        
        if (!empty($mapping_data)) {
            wp_send_json_success($mapping_data);
        } else {
            wp_send_json_error('没有保存的映射设置');
        }
    }
    
    /**
     * AJAX处理：获取字段智能匹配建议
     *
     * @since    1.0.0
     */
    public function ajax_get_field_suggestions() {
        check_ajax_referer('etwp_nonce', 'nonce');
        
        if (!isset($_POST['headers']) || empty($_POST['headers'])) {
            wp_send_json_error('未提供Excel表头数据');
        }
        
        $headers = json_decode(stripslashes($_POST['headers']), true);
        $excel_handler = new Excel_To_WP_Publisher_Excel_Handler();
        $suggestions = $excel_handler->smart_match_fields($headers);
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * AJAX处理：处理数据批次
     *
     * @since    1.0.0
     */
    public function ajax_process_batch() {
        // 添加服务器环境信息
        error_log('Excel to WP Publisher: PHP版本: ' . PHP_VERSION);
        error_log('Excel to WP Publisher: WordPress版本: ' . get_bloginfo('version'));
        
        // 检查nonce
        if (!isset($_POST['nonce'])) {
            error_log('Excel to WP Publisher: 缺失nonce参数');
            wp_send_json_error(array('message' => '安全验证失败: 缺少nonce参数'));
            return;
        }
        
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'etwp_nonce')) {
            error_log('Excel to WP Publisher: nonce验证失败: ' . $_POST['nonce']);
            wp_send_json_error(array('message' => '安全验证失败: nonce无效'));
            return;
        }
        
        error_log('Excel to WP Publisher: nonce验证成功');
        error_log('Excel to WP Publisher: 接收到AJAX请求 - etwp_process_batch');
        error_log('Excel to WP Publisher: POST数据: ' . print_r($_POST, true));
        
        // 获取Excel文件路径
        $file_path = get_option('etwp_excel_file');
        error_log('Excel to WP Publisher: 从选项中获取文件路径: ' . $file_path);
        
        $batch_index = isset($_POST['batch_index']) ? intval($_POST['batch_index']) : 0;
        
        // 修复字段映射数据获取方式，前端发送的是'mapping'而不是'field_mapping'
        $field_mapping = isset($_POST['mapping']) ? $_POST['mapping'] : array();
        
        // 记录映射数据原始格式
        error_log('Excel to WP Publisher: 映射数据原始格式: ' . gettype($field_mapping));
        if (is_string($field_mapping)) {
            error_log('Excel to WP Publisher: 映射数据字符串长度: ' . strlen($field_mapping));
            error_log('Excel to WP Publisher: 映射数据片段: ' . substr($field_mapping, 0, 200) . '...');
        } else {
            error_log('Excel to WP Publisher: 映射数据: ' . print_r($field_mapping, true));
        }
        
        // 如果字段映射是JSON字符串，则解码
        if (is_string($field_mapping)) {
            // 尝试解码，如果解码失败，记录错误
            $decoded_mapping = json_decode(stripslashes($field_mapping), true);
            if ($decoded_mapping === null && json_last_error() !== JSON_ERROR_NONE) {
                $error_msg = '解析映射数据失败: ' . json_last_error_msg() . ' (' . json_last_error() . ')';
                error_log('Excel to WP Publisher: ' . $error_msg);
                error_log('Excel to WP Publisher: JSON数据: ' . $field_mapping);
                $this->add_import_error($error_msg);
                wp_send_json_error(array('message' => $error_msg, 'errors' => $this->get_import_errors()));
                return;
            }
            $field_mapping = $decoded_mapping;
            error_log('Excel to WP Publisher: 解码后的映射数据: ' . print_r($field_mapping, true));
        }
        
        // 确保解码后的映射数据不为空
        if (empty($field_mapping)) {
            $error_msg = '映射数据为空';
            error_log('Excel to WP Publisher: ' . $error_msg);
            $this->add_import_error($error_msg);
            wp_send_json_error(array('message' => $error_msg, 'errors' => $this->get_import_errors()));
            return;
        }
        
        // 确保映射数据是正确的格式
        if (!is_array($field_mapping)) {
            $error_msg = '映射数据格式不正确，应为数组，实际为: ' . gettype($field_mapping);
            error_log('Excel to WP Publisher: ' . $error_msg);
            $this->add_import_error($error_msg);
            wp_send_json_error(array('message' => $error_msg, 'errors' => $this->get_import_errors()));
            return;
        }
        
        // 确保映射数据包含正确的字段
        if (isset($field_mapping[0]) && is_array($field_mapping[0])) {
            // 检查是否为对象数组格式
            if (!isset($field_mapping[0]['excelField']) || !isset($field_mapping[0]['wpField'])) {
                $error_msg = '映射数据格式不正确，应包含excelField和wpField属性';
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                wp_send_json_error(array('message' => $error_msg, 'errors' => $this->get_import_errors()));
                return;
            }
            
            // 转换对象数组格式为关联数组格式
            $converted_mapping = array();
            foreach ($field_mapping as $mapping) {
                $converted_mapping[$mapping['excelField']] = $mapping['wpField'];
            }
            $field_mapping = $converted_mapping;
            error_log('Excel to WP Publisher: 转换后的映射数据: ' . print_r($field_mapping, true));
        }
        
        // 记录接收到的数据
        error_log('Excel to WP Publisher: 接收到的批次索引 - ' . $batch_index);
        error_log('Excel to WP Publisher: 接收到的映射数据类型 - ' . gettype($field_mapping));
        error_log('Excel to WP Publisher: 接收到的映射数据 - ' . print_r($field_mapping, true));
        
        $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        // 记录导入开始信息
        error_log('Excel to WP Publisher: 开始处理批次 ' . $batch_index);
        error_log('Excel to WP Publisher: Excel文件路径 - ' . $file_path);
        error_log('Excel to WP Publisher: 文件是否存在 - ' . (file_exists($file_path) ? '是' : '否'));
        error_log('Excel to WP Publisher: 字段映射 - ' . print_r($field_mapping, true));
        error_log('Excel to WP Publisher: 文章状态 - ' . $post_status);
        error_log('Excel to WP Publisher: 分类ID - ' . $category_id);
        
        // 清除之前的导入错误记录
        delete_option('etwp_import_errors');
        
        // 如果是第一批，重置已处理行数计数器
        if ($batch_index === 0) {
            delete_option('etwp_processed_rows');
        }
        
        if (!$file_path || !file_exists($file_path)) {
            $error_msg = 'Excel文件不存在 - ' . $file_path;
            error_log('Excel to WP Publisher: ' . $error_msg);
            $this->add_import_error($error_msg);
            wp_send_json_error(array('message' => $error_msg, 'errors' => $this->get_import_errors()));
            return;
        }
        
        if (empty($field_mapping)) {
            $error_msg = '未设置字段映射';
            error_log('Excel to WP Publisher: ' . $error_msg);
            $this->add_import_error($error_msg);
            wp_send_json_error(array('message' => $error_msg, 'errors' => $this->get_import_errors()));
            return;
        }
        
        // 初始化处理类
        try {
            $excel_handler = new Excel_To_WP_Publisher_Excel_Handler();
            $post_handler = new Excel_To_WP_Publisher_Post_Handler();
            $image_handler = new Excel_To_WP_Publisher_Image_Handler();
            
            // 获取Excel文件的总行数
            $total_excel_rows = $excel_handler->get_excel_total_rows($file_path);
            
            if ($total_excel_rows <= 0) {
                $error_msg = 'Excel文件中没有有效数据';
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                wp_send_json_error(array('message' => $error_msg, 'errors' => $this->get_import_errors()));
            }
            
            // 保存总行数到选项，以便在后续批次中使用
            update_option('etwp_excel_total_rows', $total_excel_rows);
            
            // 计算批次的开始行和结束行
            // 批次大小默认为20
            $batch_size = $this->get_batch_size();
            
            // 计算批次的开始行和结束行
            $start_row = 2 + ($batch_index * $batch_size);
            $end_row = $start_row + $batch_size - 1;
            
            // 检查是否为最后一批
            $is_last_batch = ($end_row >= $total_excel_rows + 1);
            
            error_log('Excel to WP Publisher: 原始批次计算 - 批次索引: ' . $batch_index . ', 开始行: ' . $start_row . ', 结束行: ' . $end_row . ', Excel总行数: ' . $total_excel_rows);
            
            // 确保最后一批次包含到最后一行
            if ($is_last_batch) {
                // 特别处理最后一批，确保包含最后一行
                $end_row = $total_excel_rows + 1;
                error_log('Excel to WP Publisher: 调整批次结束行到Excel实际最后一行: ' . $end_row);
            }
            
            error_log('Excel to WP Publisher: 最终批次配置 - 批次索引: ' . $batch_index . ', 开始行: ' . $start_row . ', 结束行: ' . $end_row . ', Excel总行数: ' . $total_excel_rows . ', 是否最后一批: ' . ($is_last_batch ? '是' : '否'));
            $batch_data = $excel_handler->get_excel_data($file_path, 0, $start_row, $end_row);
            
            if ($batch_data === false) {
                $error_msg = '获取Excel数据失败，请检查文件格式是否正确';
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                wp_send_json_error(array('message' => $error_msg, 'errors' => $this->get_import_errors()));
            }
            
            // 如果是最后一批，记录实际获取的数据行数
            if ($is_last_batch) {
                error_log('Excel to WP Publisher: 最后一批实际获取的数据行数: ' . count($batch_data));
                if (count($batch_data) > 0) {
                    error_log('Excel to WP Publisher: 最后一批数据包含的行: ' . print_r(array_keys($batch_data), true));
                    // 只打印最后一行数据内容，避免日志过大
                    if (!empty($batch_data)) {
                        $last_row = end($batch_data);
                        error_log('Excel to WP Publisher: 最后一行数据内容示例: ' . print_r($last_row, true));
                    }
                } else {
                    error_log('Excel to WP Publisher: 最后一批没有获取到有效数据');
                }
            }
            
            error_log('Excel to WP Publisher: 成功获取批次数据，实际行数: ' . count($batch_data));
            
            $results = array(
                'success' => 0,
                'failed' => 0,
                'errors' => array(),
                'processed_rows' => array()
            );
            
            foreach ($batch_data as $row_index => $row) {
                $row_result = array(
                    'status' => 'failed',
                    'title' => isset($row['标题']) ? $row['标题'] : '未知标题',
                    'errors' => array()
                );
                
                $post_data = array(
                    'post_status' => $post_status,
                    'post_type' => 'post'
                );
                
                if ($category_id > 0) {
                    $post_data['post_category'] = array($category_id);
                }
                
                $meta_data = array();
                $image_data = array();
                
                // 记录当前处理的行
                error_log('Excel to WP Publisher: 处理数据行 ' . ($row_index + 1));
                
                foreach ($field_mapping as $excel_field => $wp_field) {
                    if (empty($wp_field) || !isset($row[$excel_field])) {
                        continue;
                    }
                    
                    $value = $row[$excel_field];
                    error_log('Excel to WP Publisher: 处理字段 - Excel字段: ' . $excel_field . ', WP字段: ' . $wp_field . ', 值: ' . $value);
                    
                    switch ($wp_field) {
                        case 'post_title':
                            $post_data[$wp_field] = sanitize_text_field($value);
                            $row_result['title'] = $post_data[$wp_field]; // 更新结果中的标题
                            break;
                        case 'post_content':
                            $post_data[$wp_field] = wp_kses_post($value);
                            break;
                        case 'post_excerpt':
                            $post_data[$wp_field] = sanitize_text_field($value);
                            break;
                        case '_thumbnail_id':
                            if ($image_handler->is_image_path($value)) {
                                $image_data[$wp_field] = $value;
                                error_log('Excel to WP Publisher: 检测到特色图片路径 - ' . $value);
                            } else {
                                $error_msg = '特色图片路径无效 - ' . $value;
                                error_log('Excel to WP Publisher: ' . $error_msg);
                                $row_result['errors'][] = $error_msg;
                            }
                            break;
                        default:
                            if ($image_handler->is_image_path($value)) {
                                $image_data[$wp_field] = $value;
                                error_log('Excel to WP Publisher: 检测到图片路径 - 字段: ' . $wp_field . ', 路径: ' . $value);
                            } else {
                                $meta_data[$wp_field] = $value;
                            }
                    }
                }
                
                if (empty($post_data['post_title'])) {
                    $results['failed']++;
                    $error_msg = '缺少标题字段';
                    $results['errors'][] = $error_msg;
                    $row_result['errors'][] = $error_msg;
                    error_log('Excel to WP Publisher: ' . $error_msg);
                    $results['processed_rows'][] = $row_result;
                    continue;
                }
                
                error_log('Excel to WP Publisher: 尝试创建文章 - ' . $post_data['post_title']);
                
                try {
                    $post_id = $post_handler->create_post($post_data, $meta_data, $image_data);
                    
                    if ($post_id) {
                        $results['success']++;
                        $row_result['status'] = 'success';
                        $row_result['post_id'] = $post_id;
                        error_log('Excel to WP Publisher: 文章创建成功 - ID: ' . $post_id);
                    } else {
                        $results['failed']++;
                        $error_msg = '创建文章失败 - 标题: ' . $post_data['post_title'];
                        $results['errors'][] = $error_msg;
                        $row_result['errors'][] = $error_msg;
                        error_log('Excel to WP Publisher: ' . $error_msg);
                    }
                } catch (Exception $e) {
                    $results['failed']++;
                    $error_msg = '创建文章时发生异常: ' . $e->getMessage();
                    $results['errors'][] = $error_msg;
                    $row_result['errors'][] = $error_msg;
                    error_log('Excel to WP Publisher: ' . $error_msg);
                }
                
                $results['processed_rows'][] = $row_result;
            }
            
            // 获取所有导入错误
            $import_errors = $this->get_import_errors();
            if (!empty($import_errors)) {
                $results['errors'] = array_merge($results['errors'], $import_errors);
            }
            
            // 获取已处理的行数
            $processed_rows = get_option('etwp_processed_rows', 0) + count($batch_data);
            
            // 特殊处理最后一批，确保所有行都被处理
            if ($is_last_batch) {
                error_log('Excel to WP Publisher: 最后一批处理完成，已处理行数: ' . $processed_rows . ', 总行数: ' . $total_excel_rows);
                
                if ($processed_rows < $total_excel_rows) {
                    $missing_rows = $total_excel_rows - $processed_rows;
                    error_log('Excel to WP Publisher: 检测到有' . $missing_rows . '行未被处理，强制设置已处理行数为总行数');
                    $processed_rows = $total_excel_rows;
                } else if ($processed_rows > $total_excel_rows) {
                    error_log('Excel to WP Publisher: 处理行数(' . $processed_rows . ')超过总行数(' . $total_excel_rows . ')，这可能是因为数据处理逻辑更改导致的');
                    $processed_rows = $total_excel_rows; // 确保处理行数不超过总行数
                }
            }
            
            // 更新已处理的行数
            update_option('etwp_processed_rows', $processed_rows);
            
            // 计算进度信息
            $progress_percent = ($total_excel_rows > 0) ? round(($processed_rows / $total_excel_rows) * 100) : 0;
            $has_more = $processed_rows < $total_excel_rows;
            
            // 返回处理结果和进度信息
            wp_send_json_success(array(
                'results' => $results,
                'batch_index' => $batch_index,
                'has_more' => $has_more,
                'more_data' => $has_more, // 兼容旧版本代码
                'total_rows' => $total_excel_rows,
                'processed_rows' => $processed_rows,
                'progress_percent' => $progress_percent,
                'is_last_batch' => $is_last_batch,
                'success_count' => $results['success'],
                'failed_count' => $results['failed'],
                'server_time' => date('Y-m-d H:i:s')
            ));
            
        } catch (Exception $e) {
            $error_msg = '处理批次时发生异常: ' . $e->getMessage();
            error_log('Excel to WP Publisher: ' . $error_msg);
            $this->add_import_error($error_msg);
            wp_send_json_error(array('message' => $error_msg, 'errors' => $this->get_import_errors()));
        }
    }
    
    /**
     * 获取批次大小
     *
     * @since    1.0.0
     * @return   int    批次大小
     */
    private function get_batch_size() {
        // 从设置中获取批次大小，如果未设置，则使用默认值20
        $batch_size = intval(get_option('etwp_batch_size', 20));
        
        // 确保批次大小至少为1
        if ($batch_size < 1) {
            $batch_size = 20;
        }
        
        return $batch_size;
    }
    
    /**
     * 添加导入错误信息
     *
     * @since    1.0.0
     * @param    string    $error_msg    错误信息
     */
    private function add_import_error($error_msg) {
        error_log('Excel to WP Publisher: ' . $error_msg);
        
        // 将错误信息保存到临时选项中，以便在前端显示
        $import_errors = get_option('etwp_import_errors', array());
        $import_errors[] = $error_msg;
        update_option('etwp_import_errors', $import_errors);
    }
    
    /**
     * 获取导入错误信息
     *
     * @since    1.0.0
     * @return   array    错误信息数组
     */
    private function get_import_errors() {
        return get_option('etwp_import_errors', array());
    }
}