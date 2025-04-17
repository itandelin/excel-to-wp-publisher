<?php
/**
 * 处理WordPress文章的类
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 */

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 处理WordPress文章的类
 *
 * 这个类负责创建和更新WordPress文章
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 * @author     WordPress Developer
 */
class Excel_To_WP_Publisher_Post_Handler {

    /**
     * 初始化类
     *
     * @since    1.0.0
     */
    public function __construct() {
        // 确保WordPress函数可用
    }

    /**
     * 创建WordPress文章
     *
     * @since    1.0.0
     * @param    array    $post_data    文章数据
     * @param    array    $meta_data    自定义字段数据
     * @param    array    $image_data   图片数据，格式为 ['field_name' => 'image_path']
     * @return   int|bool               文章ID或失败时返回false
     */
    public function create_post($post_data, $meta_data = array(), $image_data = array()) {
        try {
            // 检查必要的文章数据
            if (empty($post_data['post_title'])) {
                error_log('Excel to WP Publisher: 缺少文章标题');
                $this->add_import_error('缺少文章标题');
                return false;
            }
    
            // 记录正在创建的文章信息
            error_log('Excel to WP Publisher: 尝试创建文章 - 标题: ' . $post_data['post_title']);
            error_log('Excel to WP Publisher: 文章数据 - ' . print_r($post_data, true));
            
            // 记录图片数据信息
            if (!empty($image_data)) {
                error_log('Excel to WP Publisher: 图片数据 - ' . print_r($image_data, true));
            }
            
            // 记录自定义字段数据
            if (!empty($meta_data)) {
                error_log('Excel to WP Publisher: 自定义字段数据 - ' . print_r($meta_data, true));
            }
    
            // 插入文章
            $post_id = wp_insert_post($post_data, true);
    
            if (is_wp_error($post_id)) {
                $error_msg = '创建文章失败 - ' . $post_id->get_error_message();
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                return false;
            }
            
            error_log('Excel to WP Publisher: 文章创建成功，ID: ' . $post_id);
            
            // 处理图片字段
            if (!empty($image_data) && $post_id) {
                $image_handler = new Excel_To_WP_Publisher_Image_Handler();
                $image_errors = array();
                
                foreach ($image_data as $field_name => $image_path) {
                    if (empty($image_path)) {
                        continue;
                    }
                    
                    // 上传图片
                    error_log('Excel to WP Publisher: 尝试上传图片 - 字段: ' . $field_name . ', 路径: ' . $image_path);
                    // 启用使用文章标题重命名图片功能
                    $attachment_id = $image_handler->upload_image($image_path, $post_id, true);
                    
                    if ($attachment_id) {
                        error_log('Excel to WP Publisher: 图片上传成功，附件ID: ' . $attachment_id);
                        // 如果是特色图片字段
                        if ($field_name === '_thumbnail_id') {
                            $result = set_post_thumbnail($post_id, $attachment_id);
                            if (!$result) {
                                $error_msg = '设置特色图片失败 - 文章ID: ' . $post_id . ', 附件ID: ' . $attachment_id;
                                error_log('Excel to WP Publisher: ' . $error_msg);
                                $image_errors[] = $error_msg;
                            }
                        } else {
                            // 检查是否为ACF字段
                            if (function_exists('acf_get_field') && acf_get_field($field_name)) {
                                $result = update_field($field_name, $attachment_id, $post_id);
                                if (!$result) {
                                    $error_msg = '更新ACF图片字段失败 - 字段: ' . $field_name . ', 文章ID: ' . $post_id;
                                    error_log('Excel to WP Publisher: ' . $error_msg);
                                    $image_errors[] = $error_msg;
                                }
                            } else {
                                // 普通自定义字段
                                $result = update_post_meta($post_id, $field_name, $attachment_id);
                                if (!$result) {
                                    $error_msg = '更新图片自定义字段失败 - 字段: ' . $field_name . ', 文章ID: ' . $post_id;
                                    error_log('Excel to WP Publisher: ' . $error_msg);
                                    $image_errors[] = $error_msg;
                                }
                            }
                        }
                    } else {
                        $error_msg = '图片上传失败 - 字段: ' . $field_name . ', 路径: ' . $image_path;
                        error_log('Excel to WP Publisher: ' . $error_msg);
                        $image_errors[] = $error_msg;
                    }
                }
                
                // 记录图片处理错误
                if (!empty($image_errors)) {
                    foreach ($image_errors as $error) {
                        $this->add_import_error($error);
                    }
                }
            }
    
            // 处理自定义字段
            if (!empty($meta_data) && $post_id) {
                $meta_errors = array();
                
                foreach ($meta_data as $meta_key => $meta_value) {
                    // 检查是否为ACF字段
                    if (function_exists('acf_get_field') && acf_get_field($meta_key)) {
                        $result = update_field($meta_key, $meta_value, $post_id);
                        if (!$result) {
                            $error_msg = '更新ACF字段失败 - 字段: ' . $meta_key . ', 文章ID: ' . $post_id;
                            error_log('Excel to WP Publisher: ' . $error_msg);
                            $meta_errors[] = $error_msg;
                        }
                    } else {
                        // 普通自定义字段
                        $result = update_post_meta($post_id, $meta_key, $meta_value);
                        if (!$result) {
                            $error_msg = '更新自定义字段失败 - 字段: ' . $meta_key . ', 文章ID: ' . $post_id;
                            error_log('Excel to WP Publisher: ' . $error_msg);
                            $meta_errors[] = $error_msg;
                        }
                    }
                }
                
                // 记录自定义字段处理错误
                if (!empty($meta_errors)) {
                    foreach ($meta_errors as $error) {
                        $this->add_import_error($error);
                    }
                }
            }
    
            return $post_id;
        } catch (Exception $e) {
            $error_msg = '创建文章时发生异常: ' . $e->getMessage();
            error_log('Excel to WP Publisher: ' . $error_msg);
            $this->add_import_error($error_msg);
            return false;
        }
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
        );
        
        // 获取自定义字段
        $custom_fields = $this->get_custom_fields();
        
        // 合并所有字段
        return array_merge($core_fields, $custom_fields);
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
     * 获取自定义字段
     *
     * @since    1.0.0
     * @return   array    自定义字段列表
     */
    private function get_custom_fields() {
        $custom_fields = array();
        
        // 获取常用的自定义字段
        global $wpdb;
        $meta_keys = $wpdb->get_col(
            "SELECT DISTINCT meta_key 
            FROM $wpdb->postmeta 
            WHERE meta_key NOT LIKE '\_%' 
            ORDER BY meta_key 
            LIMIT 100"
        );
        
        if ($meta_keys) {
            foreach ($meta_keys as $meta_key) {
                $custom_fields[$meta_key] = $meta_key;
            }
        }
        
        // 获取ACF字段组
        if (function_exists('acf_get_field_groups')) {
            $field_groups = acf_get_field_groups(array('post_type' => 'post'));
            
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group);
                
                if ($fields) {
                    foreach ($fields as $field) {
                        $custom_fields[$field['name']] = $field['label'];
                    }
                }
            }
        }
        
        return $custom_fields;
    }
}