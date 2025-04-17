<?php
/**
 * 插件管理页面的显示
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 */

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}
?>

<style>
.etwp-steps {
    display: flex;
    justify-content: space-between;
    margin: 30px 0;
    padding: 0;
    list-style: none;
    counter-reset: step;
}

.etwp-steps li {
    flex: 1;
    text-align: center;
    position: relative;
    padding: 0 15px;
}

.etwp-steps li:before {
    content: counter(step);
    counter-increment: step;
    width: 40px;
    height: 40px;
    line-height: 40px;
    border-radius: 50%;
    background: #f0f0f1;
    color: #1d2327;
    font-weight: 600;
    display: block;
    margin: 0 auto 10px;
    transition: all 0.3s ease;
}

.etwp-steps li.active:before {
    background: #2271b1;
    color: #fff;
}

.etwp-steps li.completed:before {
    background: #00a32a;
    color: #fff;
}

.etwp-steps li:not(:last-child):after {
    content: '';
    position: absolute;
    top: 20px;
    right: -50%;
    width: 100%;
    height: 2px;
    background: #f0f0f1;
    z-index: -1;
}

.etwp-steps li.completed:after {
    background: #00a32a;
}

.etwp-steps li span {
    display: block;
    color: #1d2327;
    font-size: 14px;
    font-weight: 500;
    margin-top: 5px;
}

.etwp-steps li.active span {
    color: #2271b1;
}

.etwp-steps li.completed span {
    color: #00a32a;
}

.import-progress-bar-container {
    position: relative;
    height: 24px;
    background-color: #f0f0f1;
    border-radius: 12px;
    margin-bottom: 15px;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.import-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #135e96);
    width: 0%;
    transition: width 0.5s ease;
    position: relative;
}

.progress-pulse {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0) 100%);
    animation: progress-pulse 2s infinite;
}

@keyframes progress-pulse {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

#import-status {
    transition: all 0.3s ease;
    margin: 0;
    font-size: 14px;
    color: #1d2327;
    line-height: 1.5;
}

#import-status.error {
    color: #d63638;
    font-weight: 500;
}

#import-status.warning {
    color: #dba617;
    font-weight: 500;
}

#import-status.success {
    color: #00a32a;
    font-weight: 500;
}

#import-loading {
    text-align: center;
    margin-top: 15px;
    padding: 10px;
    background: #f0f6fc;
    border-radius: 4px;
    color: #1d2327;
    font-size: 14px;
}

#import-loading .spinner {
    float: none;
    margin-right: 8px;
    vertical-align: middle;
}

#import-progress-container {
    margin-top: 20px;
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#import-progress-container h3 {
    margin-top: 0;
    color: #1d2327;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
}

#start-import {
    background: #2271b1;
    border-color: #2271b1;
    color: #fff;
    padding: 6px 12px;
    border-radius: 3px;
    transition: all 0.2s ease;
    font-size: 13px;
    line-height: 1.4;
    height: auto;
    min-height: 30px;
    box-shadow: none;
    text-shadow: none;
    border: 1px solid #2271b1;
}

#start-import:hover {
    background: #135e96;
    border-color: #135e96;
    color: #fff;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

#start-import:disabled {
    background: #f0f0f1;
    border-color: #dcdcde;
    color: #a7aaad;
    cursor: not-allowed;
    box-shadow: none;
}

#cancel-import {
    background: #f0f0f1;
    border-color: #dcdcde;
    color: #1d2327;
    padding: 6px 12px;
    border-radius: 3px;
    transition: all 0.2s ease;
    font-size: 13px;
    line-height: 1.4;
    height: auto;
    min-height: 30px;
    box-shadow: none;
    text-shadow: none;
    margin-left: 10px;
}

#cancel-import:hover {
    background: #dcdcde;
    border-color: #c3c4c7;
    color: #1d2327;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.etwp-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #dcdcde;
}

.etwp-actions .button {
    margin-right: 8px;
}

.etwp-actions .button:last-child {
    margin-right: 0;
}

.etwp-actions .button-primary {
    background: #2271b1;
    border-color: #2271b1;
    color: #fff;
    padding: 6px 12px;
    border-radius: 3px;
    transition: all 0.2s ease;
    font-size: 13px;
    line-height: 1.4;
    height: auto;
    min-height: 30px;
    box-shadow: none;
    text-shadow: none;
}

.etwp-actions .button-primary:hover {
    background: #135e96;
    border-color: #135e96;
    color: #fff;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.etwp-actions .button-secondary {
    background: #f0f0f1;
    border-color: #dcdcde;
    color: #1d2327;
    padding: 6px 12px;
    border-radius: 3px;
    transition: all 0.2s ease;
    font-size: 13px;
    line-height: 1.4;
    height: auto;
    min-height: 30px;
    box-shadow: none;
    text-shadow: none;
}

.etwp-actions .button-secondary:hover {
    background: #dcdcde;
    border-color: #c3c4c7;
    color: #1d2327;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.etwp-actions .button:disabled {
    background: #f0f0f1;
    border-color: #dcdcde;
    color: #a7aaad;
    cursor: not-allowed;
    box-shadow: none;
}
</style>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php
    // 显示错误消息
    if (isset($_GET['error'])) {
        $error_code = intval($_GET['error']);
        $error_message = '';
        
        switch ($error_code) {
            case 1:
                $error_message = __('请选择一个Excel文件上传', 'excel-to-wp-publisher');
                break;
            case 2:
                $error_message = __('不支持的文件类型，请上传Excel或CSV文件', 'excel-to-wp-publisher');
                break;
            case 3:
                $error_message = __('无法读取Excel文件中的表头', 'excel-to-wp-publisher');
                break;
            case 4:
                $error_message = __('文件上传失败', 'excel-to-wp-publisher');
                break;
            case 5:
                $error_message = __('Excel文件不存在', 'excel-to-wp-publisher');
                break;
            case 6:
                $error_message = __('Excel文件中没有数据', 'excel-to-wp-publisher');
                break;
            default:
                $error_message = __('发生未知错误', 'excel-to-wp-publisher');
        }
        
        echo '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
    }
    
    // 显示步骤导航
    echo '<ul class="etwp-steps">';
    echo '<li class="' . ($step == 1 ? 'active' : '') . '"><span>' . __('上传Excel文件', 'excel-to-wp-publisher') . '</span></li>';
    echo '<li class="' . ($step == 2 ? 'active' : '') . '"><span>' . __('设置字段映射', 'excel-to-wp-publisher') . '</span></li>';
    echo '<li class="' . ($step == 3 ? 'active' : '') . '"><span>' . __('导入结果', 'excel-to-wp-publisher') . '</span></li>';
    echo '</ul>';
    
    // 步骤1：上传Excel文件
    if ($step == 1) {
        ?>
        <div class="etwp-upload-form">
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="etwp_upload_excel">
                <?php wp_nonce_field('etwp_nonce', 'etwp_nonce'); ?>
                
                <div class="form-field">
                    <label for="excel_file"><?php _e('选择Excel文件', 'excel-to-wp-publisher'); ?></label>
                    <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" required>
                    <p class="description"><?php _e('支持的文件格式：.xlsx, .xls, .csv', 'excel-to-wp-publisher'); ?></p>
                </div>
                
                <?php submit_button(__('上传并继续', 'excel-to-wp-publisher'), 'primary', 'submit', true); ?>
            </form>
        </div>
        <?php
    }
    
    // 步骤2：设置字段映射
    elseif ($step == 2) {
        // 获取Excel表头
        $excel_headers = get_option('etwp_excel_headers', array());
        
        // 获取WordPress字段
        $post_handler = new Excel_To_WP_Publisher_Post_Handler();
        $wp_fields = $post_handler->get_wordpress_fields();
        
        // 获取分类列表
        $categories = $this->get_categories();
        ?>
        <div class="etwp-mapping-form">
            <form method="post" id="etwp-import-form">
                <?php wp_nonce_field('etwp_nonce', 'etwp_nonce'); ?>
                
                <h2><?php _e('字段映射设置', 'excel-to-wp-publisher'); ?></h2>
                <p class="description"><?php _e('拖拽Excel字段到右侧对应的WordPress字段进行映射', 'excel-to-wp-publisher'); ?></p>
                
                <div class="etwp-mapping-container">
                    <!-- Excel字段列表 -->
                    <div class="etwp-excel-fields">
                        <h3><?php _e('Excel字段', 'excel-to-wp-publisher'); ?></h3>
                        <div class="etwp-field-list" id="excel-fields">
                            <?php foreach ($excel_headers as $column => $header) : ?>
                            <div class="etwp-field" data-field="<?php echo esc_attr($header); ?>">
                                <i class="dashicons dashicons-menu"></i>
                                <span class="field-name"><?php echo esc_html($header); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- WordPress字段列表 -->
                    <div class="etwp-wp-fields">
                        <h3><?php _e('WordPress字段', 'excel-to-wp-publisher'); ?></h3>
                        <div class="etwp-field-list" id="wp-fields">
                            <?php foreach ($wp_fields as $field_key => $field_label) : ?>
                            <div class="etwp-field etwp-wp-field" data-field="<?php echo esc_attr($field_key); ?>">
                                <i class="dashicons dashicons-admin-post"></i>
                                <span class="field-name"><?php echo esc_html($field_label); ?></span>
                                <div class="field-drop-zone" data-wp-field="<?php echo esc_attr($field_key); ?>"></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 字段映射预览 -->
                <div class="etwp-mapping-preview">
                    <h3><?php _e('当前映射', 'excel-to-wp-publisher'); ?></h3>
                    <div id="mapping-preview"></div>
                </div>
                
                <h2><?php _e('发布设置', 'excel-to-wp-publisher'); ?></h2>
                
                <div class="etwp-publish-settings">
                    <div class="setting-group">
                        <label><?php _e('分类', 'excel-to-wp-publisher'); ?></label>
                        <select name="category_id" id="category_id">
                            <option value="0"><?php _e('-- 不设置分类 --', 'excel-to-wp-publisher'); ?></option>
                            <?php foreach ($categories as $cat_id => $cat_name) : ?>
                            <option value="<?php echo esc_attr($cat_id); ?>"><?php echo esc_html($cat_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="setting-group">
                        <label><?php _e('文章状态', 'excel-to-wp-publisher'); ?></label>
                        <select name="post_status" id="post_status">
                            <option value="draft"><?php _e('草稿', 'excel-to-wp-publisher'); ?></option>
                            <option value="publish"><?php _e('发布', 'excel-to-wp-publisher'); ?></option>
                            <option value="pending"><?php _e('待审', 'excel-to-wp-publisher'); ?></option>
                        </select>
                    </div>

                    <div class="setting-group">
                        <label><?php _e('图片处理', 'excel-to-wp-publisher'); ?></label>
                        <select name="image_handling" id="image_handling">
                            <option value="keep"><?php _e('保持原文件名', 'excel-to-wp-publisher'); ?></option>
                            <option value="rename"><?php _e('使用文章标题重命名', 'excel-to-wp-publisher'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- 进度监控区域 -->
                <div class="etwp-progress-monitor" style="display: none;">
                    <h3><?php _e('导入进度', 'excel-to-wp-publisher'); ?></h3>
                    
                    <!-- 环形进度图 -->
                    <div class="etwp-progress-circle">
                        <svg viewBox="0 0 36 36" class="circular-chart">
                            <path class="circle-bg" d="M18 2.0845
                                a 15.9155 15.9155 0 0 1 0 31.831
                                a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <path class="circle" stroke-dasharray="0, 100" d="M18 2.0845
                                a 15.9155 15.9155 0 0 1 0 31.831
                                a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            <text x="18" y="20.35" class="percentage">0%</text>
                        </svg>
                    </div>

                    <!-- 进度统计 -->
                    <div class="etwp-progress-stats">
                        <div class="stat-item">
                            <span class="label"><?php _e('已处理', 'excel-to-wp-publisher'); ?></span>
                            <span class="value" id="processed-count">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="label"><?php _e('总数', 'excel-to-wp-publisher'); ?></span>
                            <span class="value" id="total-count">0</span>
                        </div>
                    </div>

                    <!-- 实时日志 -->
                    <div class="etwp-log-container">
                        <div class="log-header">
                            <h4><?php _e('处理日志', 'excel-to-wp-publisher'); ?></h4>
                            <button type="button" class="button-link" id="clear-log"><?php _e('清除', 'excel-to-wp-publisher'); ?></button>
                        </div>
                        <div class="log-content" id="import-log"></div>
                    </div>
                </div>

                <div class="etwp-actions">
                    <div class="action-buttons">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name)); ?>" class="button"><?php _e('返回', 'excel-to-wp-publisher'); ?></a>
                        <button type="button" class="button button-secondary" id="reset-mapping"><?php _e('重置映射', 'excel-to-wp-publisher'); ?></button>
                        <button type="button" class="button button-primary" id="start-import"><?php _e('开始导入', 'excel-to-wp-publisher'); ?></button>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    // 步骤3：导入结果
    elseif ($step == 3) {
        $imported = isset($_GET['imported']) ? intval($_GET['imported']) : 0;
        $failed = isset($_GET['failed']) ? intval($_GET['failed']) : 0;
        $total = isset($_GET['total']) ? intval($_GET['total']) : 0;
        ?>
        <div class="etwp-result">
            <h2><?php _e('导入完成', 'excel-to-wp-publisher'); ?></h2>
            
            <div class="etwp-result-summary">
                <p>
                    <?php 
                    printf(
                        __('总共处理了 %1$d 条记录，成功导入 %2$d 条，失败 %3$d 条。', 'excel-to-wp-publisher'),
                        $total,
                        $imported,
                        $failed
                    ); 
                    ?>
                </p>
            </div>
            
            <div class="etwp-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $this->plugin_name)); ?>" class="button button-primary"><?php _e('返回上传页面', 'excel-to-wp-publisher'); ?></a>
                <a href="<?php echo esc_url(admin_url('edit.php')); ?>" class="button"><?php _e('查看所有文章', 'excel-to-wp-publisher'); ?></a>
            </div>
        </div>
        <?php
    }
    ?>
</div>