<?php
/**
 * 处理Excel文件的类
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 */

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 处理Excel文件的类
 *
 * 这个类负责读取和解析Excel文件，提取表头和数据
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 * @author     WordPress Developer
 */
class Excel_To_WP_Publisher_Excel_Handler {
    /**
     * WordPress标准字段列表
     *
     * @since    1.0.0
     * @var      array
     */
    private $wp_standard_fields = array(
        'post_title' => array('type' => 'text', 'label' => '标题', 'keywords' => array('标题', 'title', '主题')),
        'post_content' => array('type' => 'editor', 'label' => '内容', 'keywords' => array('内容', 'content', '正文', 'body')),
        'post_excerpt' => array('type' => 'textarea', 'label' => '摘要', 'keywords' => array('摘要', 'excerpt', '简介', 'description')),
        'post_date' => array('type' => 'datetime', 'label' => '发布日期', 'keywords' => array('日期', 'date', '时间', 'time', '发布时间')),
        'post_author' => array('type' => 'user', 'label' => '作者', 'keywords' => array('作者', 'author', '撰写人')),
        'post_status' => array('type' => 'select', 'label' => '状态', 'keywords' => array('状态', 'status', '发布状态')),
        'post_category' => array('type' => 'taxonomy', 'label' => '分类', 'keywords' => array('分类', 'category', '类别')),
        'tags_input' => array('type' => 'tags', 'label' => '标签', 'keywords' => array('标签', 'tags', '关键词', 'keywords'))
    );

    /**
     * 每批处理的数据条数
     *
     * @since    1.0.0
     * @var      int
     */
    private $batch_size = 50;

    /**
     * 初始化类
     *
     * @since    1.0.0
     */
    public function __construct() {
        // 注册AJAX处理函数
        add_action('wp_ajax_etwp_get_mappable_fields', array($this, 'get_mappable_fields'));
        // 移除与Admin类冲突的AJAX处理函数注册
        // add_action('wp_ajax_etwp_process_batch', array($this, 'process_batch'));
        
        // 初始化自定义字段缓存
        $this->init_custom_fields_cache();
        // 检查是否已安装PhpSpreadsheet
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            // 如果没有安装，则尝试包含内置的库
            $autoload_path = ETWP_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($autoload_path)) {
                require_once $autoload_path;
                if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                    error_log('Excel to WP Publisher: 加载autoload.php后PhpSpreadsheet库仍不可用');
                    add_action('admin_notices', array($this, 'display_dependency_notice'));
                }
            } else {
                // 如果autoload.php不存在，添加一个管理员通知
                error_log('Excel to WP Publisher: vendor/autoload.php不存在，请运行composer install');
                add_action('admin_notices', array($this, 'display_dependency_notice'));
            }
        }
        
        // 确保上传目录存在
        $upload_dir = wp_upload_dir();
        $this->upload_path = $upload_dir['basedir'] . '/excel-to-wp-publisher/';
        if (!file_exists($this->upload_path)) {
            $result = wp_mkdir_p($this->upload_path);
            if (!$result) {
                error_log('Excel to WP Publisher: 无法创建上传目录 - ' . $this->upload_path);
            }
        }
        
        // 检查上传目录是否可写
        if (!is_writable($this->upload_path)) {
            error_log('Excel to WP Publisher: 上传目录不可写 - ' . $this->upload_path);
        }
    }
    
    /**
     * 显示依赖缺失的通知
     *
     * @since    1.0.0
     */
    public function display_dependency_notice() {
        $class = 'notice notice-error';
        $message = __('Excel到WordPress发布器需要PhpSpreadsheet库才能正常工作。请在插件目录中运行<code>composer install</code>命令来安装所需依赖。', 'excel-to-wp-publisher');
        $install_instructions = __('如果您没有在服务器上安装Composer，请按照以下步骤操作：<br>1. 在本地计算机上下载插件<br>2. 在插件目录中运行<code>composer install</code>命令<br>3. 将整个插件目录（包括vendor文件夹）上传到您的WordPress服务器', 'excel-to-wp-publisher');
        
        echo '<div class="' . esc_attr($class) . '"><p>' . wp_kses_post($message) . '</p><p>' . wp_kses_post($install_instructions) . '</p></div>';
    }

    /**
     * 获取Excel文件的表头
     *
     * @since    1.0.0
     * @param    string    $file_path    Excel文件路径
     * @return   array|bool              表头数组或失败时返回false
     */
    public function get_excel_headers($file_path) {
        try {
            // 检查文件是否存在
            if (!file_exists($file_path)) {
                error_log('Excel to WP Publisher: 文件不存在 - ' . $file_path);
                return false;
            }
            
            // 检查文件是否可读
            if (!is_readable($file_path)) {
                error_log('Excel to WP Publisher: 文件不可读 - ' . $file_path);
                return false;
            }
            
            // 检查PhpSpreadsheet库是否可用
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                // 显示错误通知
                add_action('admin_notices', array($this, 'display_dependency_notice'));
                error_log('Excel to WP Publisher: PhpSpreadsheet库未加载');
                return false;
            }
            
            // 获取文件扩展名
            $file_info = pathinfo($file_path);
            if (!isset($file_info['extension'])) {
                error_log('Excel to WP Publisher: 无法确定文件类型 - ' . $file_path);
                return false;
            }
            
            $extension = strtolower($file_info['extension']);
            
            // 根据文件类型处理
            if ($extension === 'csv') {
                $headers = $this->get_csv_headers($file_path);
            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                $headers = $this->get_excel_sheet_headers($file_path);
            } else {
                error_log('Excel to WP Publisher: 不支持的文件类型 - ' . $extension);
                return false;
            }
            
            if (empty($headers)) {
                error_log('Excel to WP Publisher: 未能获取到表头数据');
                return false;
            }
            
            return $headers;
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            error_log('Excel to WP Publisher: 读取Excel文件失败 - ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('Excel to WP Publisher: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取CSV文件的表头
     *
     * @since    1.0.0
     * @param    string    $file_path    CSV文件路径
     * @return   array                   表头数组
     */
    private function get_csv_headers($file_path) {
        $headers = array();
        
        if (($handle = fopen($file_path, 'r')) !== false) {
            // 读取第一行作为表头
            if (($data = fgetcsv($handle, 1000, ',')) !== false) {
                foreach ($data as $index => $header) {
                    if (!empty($header)) {
                        $headers[$index] = $header;
                    }
                }
            }
            fclose($handle);
        }
        
        return $headers;
    }

    /**
     * 获取Excel文件的表头
     *
     * @since    1.0.0
     * @param    string    $file_path    Excel文件路径
     * @return   array                   表头数组
     */
    private function get_excel_sheet_headers($file_path) {
        $headers = array();
        
        // 检查PhpSpreadsheet库是否可用
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            // 显示错误通知
            add_action('admin_notices', array($this, 'display_dependency_notice'));
            error_log('Excel to WP Publisher: PhpSpreadsheet库未加载');
            return array();
        }
        
        try {
            // 检查文件是否可读
            if (!is_readable($file_path)) {
                error_log('Excel to WP Publisher: 文件不可读 - ' . $file_path);
                return array();
            }
            
            // 加载Excel文件
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // 获取表头行
            $headerRow = $worksheet->getRowIterator(1, 1)->current();
            $cellIterator = $headerRow->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            
            // 遍历表头单元格
            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $value = $cell->getValue();
                
                if (!empty($value)) {
                    $headers[$column] = $value;
                }
            }
            
            if (empty($headers)) {
                error_log('Excel to WP Publisher: 未找到有效的表头数据');
            }
            
            return $headers;
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            error_log('Excel to WP Publisher: 读取Excel文件失败 - ' . $e->getMessage());
            return array();
        } catch (\Exception $e) {
            error_log('Excel to WP Publisher: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * 初始化自定义字段缓存
     *
     * @since    1.0.0
     */
    private function init_custom_fields_cache() {
        // 获取所有已注册的自定义字段
        global $wpdb;
        $meta_keys = $wpdb->get_col(
            "SELECT DISTINCT meta_key 
            FROM $wpdb->postmeta 
            WHERE meta_key NOT LIKE '\_\_%' 
            LIMIT 100"
        );
        
        // 缓存自定义字段信息
        set_transient('etwp_custom_fields', $meta_keys, 30 * MINUTE_IN_SECONDS);
    }

    /**
     * 获取所有可映射的字段
     *
     * @since    1.0.0
     */
    public function get_mappable_fields() {
        check_ajax_referer('etwp_nonce', 'nonce');
        
        $fields = array(
            'standard' => $this->wp_standard_fields,
            'custom' => array()
        );
        
        // 获取缓存的自定义字段
        $custom_fields = get_transient('etwp_custom_fields');
        if ($custom_fields) {
            foreach ($custom_fields as $field) {
                $fields['custom'][$field] = array(
                    'type' => 'text',
                    'label' => $field,
                    'keywords' => array($field)
                );
            }
        }
        
        wp_send_json_success($fields);
    }

    /**
     * 智能匹配Excel列头到WordPress字段
     *
     * @since    1.0.0
     * @param    array    $headers    Excel表头数组
     * @return   array               匹配结果数组
     */
    public function smart_match_fields($headers) {
        $matches = array();
        $all_fields = array_merge(
            $this->wp_standard_fields,
            get_transient('etwp_custom_fields') ?: array()
        );
        
        foreach ($headers as $header) {
            $best_match = null;
            $max_score = 0;
            
            foreach ($all_fields as $field_key => $field_info) {
                // 计算相似度分数
                $score = 0;
                
                // 完全匹配
                if (strtolower($header) === strtolower($field_info['label'])) {
                    $score = 100;
                }
                // 关键词匹配
                elseif (isset($field_info['keywords'])) {
                    foreach ($field_info['keywords'] as $keyword) {
                        if (stripos($header, $keyword) !== false) {
                            $score = 80;
                            break;
                        }
                    }
                }
                // 部分匹配
                if ($score === 0) {
                    similar_text(strtolower($header), strtolower($field_info['label']), $similarity);
                    $score = $similarity;
                }
                
                if ($score > $max_score) {
                    $max_score = $score;
                    $best_match = $field_key;
                }
            }
            
            if ($max_score >= 60) { // 设置匹配阈值
                $matches[$header] = $best_match;
            }
        }
        
        return $matches;
    }

    /**
     * 处理单个数据批次
     *
     * @since    1.0.0
     */
    public function process_batch() {
        check_ajax_referer('etwp_nonce', 'nonce');
        
        $file_path = sanitize_text_field($_POST['file_path']);
        $batch_index = intval($_POST['batch_index']);
        $field_mapping = json_decode(stripslashes($_POST['field_mapping']), true);
        
        // 如果是第一批，重置已处理行数计数器
        if ($batch_index === 0) {
            delete_option('etwp_processed_rows');
        }
        
        // 获取Excel文件的总行数
        $total_rows = $this->get_excel_total_rows($file_path);
        error_log('Excel to WP Publisher: 总行数: ' . $total_rows);
        
        // 修正批次处理逻辑，确保批次之间不会重叠且正确处理所有行
        // 从第2行开始（跳过表头）
        $start_row = 2 + ($batch_index * $this->batch_size);
        $end_row = $start_row + $this->batch_size - 1;
        
        // 检查是否为最后一批
        $is_last_batch = ($end_row >= ($total_rows + 1));
        
        // 如果是最后一批，确保结束行包含文件的最后一行
        if ($is_last_batch) {
            $end_row = $total_rows + 1;
            error_log('Excel to WP Publisher: 处理最后一批数据 - 开始行: ' . $start_row . ', 结束行: ' . $end_row);
        }
        
        error_log('Excel to WP Publisher: 批次处理 - 批次索引: ' . $batch_index . ', 开始行: ' . $start_row . ', 结束行: ' . $end_row . ', 是否最后一批: ' . ($is_last_batch ? '是' : '否'));
        
        // 获取批次数据
        $batch_data = $this->get_excel_sheet_data($file_path, 0, $start_row, $end_row);
        
        if ($batch_data === false) {
            wp_send_json_error(array('message' => '处理数据时发生错误'));
            return;
        }
        
        // 特殊处理最后一批，确保不遗漏任何行
        if ($is_last_batch) {
            error_log('Excel to WP Publisher: 最后一批实际获取的数据行数: ' . count($batch_data));
        }
        
        // 获取已处理的行数
        $processed_rows = get_option('etwp_processed_rows', 0) + count($batch_data);
        
        // 特殊处理最后一批，确保处理行数不超过总行数
        if ($is_last_batch && $processed_rows > $total_rows) {
            error_log('Excel to WP Publisher: 处理行数(' . $processed_rows . ')超过总行数(' . $total_rows . ')，调整为总行数');
            $processed_rows = $total_rows;
        }
        
        // 更新已处理的行数
        update_option('etwp_processed_rows', $processed_rows);
        
        // 返回处理结果
        wp_send_json_success(array(
            'data' => $batch_data,
            'total_rows' => $total_rows,
            'processed_rows' => $processed_rows,
            'batch_index' => $batch_index,
            'has_more' => $processed_rows < $total_rows,
            'is_last_batch' => $is_last_batch
        ));
    }

    /**
     * 获取Excel文件的数据
     *
     * @since    1.0.0
     * @param    string    $file_path    Excel文件路径
     * @param    int       $sheet_index  工作表索引
     * @param    int       $start_row    开始行
     * @param    int       $end_row      结束行
     * @return   array|bool              数据数组或失败时返回false
     */
    public function get_excel_data($file_path, $sheet_index = 0, $start_row = 0, $end_row = null) {
        error_log('Excel to WP Publisher: 获取Excel数据 - 文件路径: ' . $file_path . ', 开始行: ' . $start_row . ', 结束行: ' . ($end_row ? $end_row : '全部'));
        
        try {
            // 检查开始行和结束行的有效性
            if ($end_row !== null && $start_row >= $end_row) {
                error_log('Excel to WP Publisher: 开始行大于或等于结束行，没有要处理的数据');
                return array(); // 返回空数组而不是false，避免前端显示错误
            }
            
            // 记录详细的调试信息
            error_log('Excel to WP Publisher: 开始读取Excel文件 - ' . $file_path);
            error_log('Excel to WP Publisher: 工作表索引 - ' . $sheet_index);
            error_log('Excel to WP Publisher: 起始行 - ' . $start_row);
            error_log('Excel to WP Publisher: 结束行 - ' . ($end_row ? $end_row : '全部'));
            
            // 检查文件是否存在
            if (!file_exists($file_path)) {
                $error_msg = '文件不存在 - ' . $file_path;
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                return false;
            }
            
            // 检查文件是否可读
            if (!is_readable($file_path)) {
                $error_msg = '文件不可读 - ' . $file_path;
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                return false;
            }
            
            // 检查文件大小
            $file_size = filesize($file_path);
            error_log('Excel to WP Publisher: 文件大小 - ' . $file_size . ' 字节');
            if ($file_size === 0) {
                $error_msg = '文件为空 - ' . $file_path;
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                return false;
            }
            
            // 检查PhpSpreadsheet库是否可用
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                // 显示错误通知
                add_action('admin_notices', array($this, 'display_dependency_notice'));
                $error_msg = 'PhpSpreadsheet库未加载，请确保已正确安装依赖';
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                return false;
            }
            
            // 获取文件扩展名
            $file_info = pathinfo($file_path);
            if (!isset($file_info['extension'])) {
                $error_msg = '无法确定文件类型 - ' . $file_path;
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                return false;
            }
            
            $extension = strtolower($file_info['extension']);
            error_log('Excel to WP Publisher: 文件类型 - ' . $extension);
            
            // 根据文件类型处理
            if ($extension === 'csv') {
                error_log('Excel to WP Publisher: 开始处理CSV文件');
                $data = $this->get_csv_data($file_path, $start_row, $end_row);
            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                error_log('Excel to WP Publisher: 开始处理Excel文件');
                $data = $this->get_excel_sheet_data($file_path, $sheet_index, $start_row, $end_row);
            } else {
                $error_msg = '不支持的文件类型 - ' . $extension;
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                return false;
            }
            
            if (empty($data)) {
                // 检查是否为最后一批数据的边界情况
                if ($end_row !== null && $end_row > $start_row) {
                    error_log('Excel to WP Publisher: 获取数据为空，但这可能是最后一批的正常情况');
                    return array(); // 返回空数组，而不是返回false
                }
                
                $error_msg = '未能获取到数据，Excel文件可能为空或格式不正确';
                error_log('Excel to WP Publisher: ' . $error_msg);
                $this->add_import_error($error_msg);
                return false;
            }
            
            error_log('Excel to WP Publisher: 成功读取数据，共 ' . count($data) . ' 行');
            return $data;
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            $error_msg = '读取Excel文件失败 - ' . $e->getMessage();
            error_log('Excel to WP Publisher: ' . $error_msg);
            $this->add_import_error($error_msg);
            return false;
        } catch (\Exception $e) {
            $error_msg = '处理Excel文件时发生异常 - ' . $e->getMessage();
            error_log('Excel to WP Publisher: ' . $error_msg);
            $this->add_import_error($error_msg);
            return false;
        }
    }

    /**
     * 获取CSV文件的所有数据
     *
     * @since    1.0.0
     * @param    string    $file_path    CSV文件路径
     * @return   array                   数据数组
     */
    private function get_csv_data($file_path) {
        $data = array();
        $headers = array();
        $row_index = 0;
        
        if (($handle = fopen($file_path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if ($row_index === 0) {
                    // 第一行是表头
                    foreach ($row as $index => $header) {
                        if (!empty($header)) {
                            $headers[$index] = $header;
                        }
                    }
                } else {
                    // 数据行
                    $row_data = array();
                    foreach ($headers as $index => $header) {
                        if (isset($row[$index])) {
                            $row_data[$header] = $row[$index];
                        } else {
                            $row_data[$header] = '';
                        }
                    }
                    $data[] = $row_data;
                }
                $row_index++;
            }
            fclose($handle);
        }
        
        return $data;
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
     * 获取Excel文件的总行数
     *
     * @since    1.0.0
     * @param    string    $file_path    Excel文件路径
     * @return   int                    总行数
     */
    public function get_excel_total_rows($file_path) {
        try {
            // 检查文件是否存在
            if (!file_exists($file_path)) {
                error_log('Excel to WP Publisher: 文件不存在 - ' . $file_path);
                return 0;
            }
            
            // 检查文件是否可读
            if (!is_readable($file_path)) {
                error_log('Excel to WP Publisher: 文件不可读 - ' . $file_path);
                return 0;
            }
            
            // 获取文件扩展名
            $file_info = pathinfo($file_path);
            if (!isset($file_info['extension'])) {
                error_log('Excel to WP Publisher: 无法确定文件类型 - ' . $file_path);
                return 0;
            }
            
            $extension = strtolower($file_info['extension']);
            
            // 根据文件类型处理
            if ($extension === 'csv') {
                // 对于CSV文件，计算行数
                $row_count = 0;
                if (($handle = fopen($file_path, 'r')) !== false) {
                    while (fgetcsv($handle, 1000, ',') !== false) {
                        $row_count++;
                    }
                    fclose($handle);
                }
                // 减去表头行
                return max(0, $row_count - 1);
            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                // 对于Excel文件，使用PhpSpreadsheet获取行数
                if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                    error_log('Excel to WP Publisher: PhpSpreadsheet库未加载');
                    return 0;
                }
                
                // 使用PhpSpreadsheet加载文件并获取实际有效数据行数
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // 获取表头
                $headers = array();
                $headerRow = $worksheet->getRowIterator(1, 1)->current();
                $cellIterator = $headerRow->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);
                
                foreach ($cellIterator as $cell) {
                    $column = $cell->getColumn();
                    $value = $cell->getValue();
                    
                    if (!empty($value)) {
                        $headers[$column] = $value;
                    }
                }
                
                // 如果没有找到表头，返回0
                if (empty($headers)) {
                    error_log('Excel to WP Publisher: 未找到有效的表头数据');
                    return 0;
                }
                
                // 新的计算方法：直接获取最后一个非空行的索引，然后减去表头行
                $highestRow = $worksheet->getHighestDataRow();
                
                // 完全重写计算逻辑，强制手动遍历所有行来计算有效行数
                $rowCount = 0;
                // 从第2行开始（跳过表头）
                for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
                    $hasValue = false;
                    // 检查每一列
                    foreach ($headers as $column => $header) {
                        $value = $worksheet->getCell($column . $rowIndex)->getValue();
                        if ($value !== null && $value !== '') {
                            $hasValue = true;
                            break;
                        }
                    }
                    
                    if ($hasValue) {
                        $rowCount++;
                    }
                }
                
                // 记录详细信息
                error_log('Excel to WP Publisher: 新计算方法 - 有效数据行数: ' . $rowCount);
                error_log('Excel to WP Publisher: 原始最高行号: ' . $highestRow);
                
                // 确保不会漏掉任何行
                $finalRowCount = $rowCount;
                
                error_log('Excel to WP Publisher: 最终统计的总行数: ' . $finalRowCount);
                
                return $finalRowCount;
            } else {
                error_log('Excel to WP Publisher: 不支持的文件类型 - ' . $extension);
                return 0;
            }
        } catch (\Exception $e) {
            error_log('Excel to WP Publisher: 获取总行数时发生错误 - ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 获取Excel文件的所有数据
     *
     * @since    1.0.0
     * @param    string    $file_path    Excel文件路径
     * @param    int       $sheet_index  工作表索引，默认为0（第一个工作表）
     * @param    int       $start_row    起始行，默认为2（跳过表头）
     * @param    int       $end_row      结束行，默认为null（所有行）
     * @return   array                   数据数组
     */
    private function get_excel_sheet_data($file_path, $sheet_index = 0, $start_row = 2, $end_row = null) {
        $data = array();
        
        // 检查PhpSpreadsheet库是否可用
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            // 显示错误通知
            add_action('admin_notices', array($this, 'display_dependency_notice'));
            error_log('Excel to WP Publisher: PhpSpreadsheet库未加载');
            return array();
        }
        
        try {
            // 检查文件是否可读
            if (!is_readable($file_path)) {
                error_log('Excel to WP Publisher: 文件不可读 - ' . $file_path);
                return array();
            }
            
            // 加载Excel文件
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            
            // 获取指定的工作表
            $worksheet = ($sheet_index > 0 && $sheet_index < $spreadsheet->getSheetCount()) 
                ? $spreadsheet->getSheet($sheet_index) 
                : $spreadsheet->getActiveSheet();
            
            // 获取表头
            $headers = array();
            $headerRow = $worksheet->getRowIterator(1, 1)->current();
            $cellIterator = $headerRow->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            
            foreach ($cellIterator as $cell) {
                $column = $cell->getColumn();
                $value = $cell->getValue();
                
                if (!empty($value)) {
                    $headers[$column] = $value;
                }
            }
            
            // 如果没有找到表头，返回空数组
            if (empty($headers)) {
                error_log('Excel to WP Publisher: 未找到有效的表头数据');
                return array();
            }
            
            // 获取数据行范围
            $highestRow = $worksheet->getHighestDataRow();
            
            // 确保结束行不超过文件的最高行数
            if ($end_row && $end_row <= $highestRow) {
                $highestRow = $end_row;
            }
            
            // 检查开始行是否超出有效范围
            if ($start_row > $highestRow) {
                error_log('Excel to WP Publisher: 开始行超出Excel文件的最大行数，没有要处理的数据 - 开始行: ' . $start_row . ', 最大行数: ' . $highestRow);
                return array();
            }
            
            error_log('Excel to WP Publisher: 处理数据行范围 - 开始行: ' . $start_row . ', 结束行: ' . $highestRow);
            
            $skippedRows = 0; // 记录跳过的空行数
            $processedRows = 0; // 记录处理的行数
            
            for ($rowIndex = $start_row; $rowIndex <= $highestRow; $rowIndex++) {
                $rowData = array();
                $hasData = false;
                $isEmptyCount = 0;
                $totalCells = 0;
                
                // 记录是否为最后一行
                $isLastRow = ($rowIndex == $highestRow);
                
                // 记录详细的行处理信息
                error_log('Excel to WP Publisher: 处理行 ' . $rowIndex . ' (highestRow=' . $highestRow . ', start_row=' . $start_row . ', end_row=' . ($end_row ? $end_row : 'null') . ')');
                
                foreach ($headers as $column => $header) {
                    $cell = $worksheet->getCell($column . $rowIndex);
                    $value = $cell->getValue();
                    $totalCells++;
                    
                    // 处理日期格式
                    if ($cell->getDataType() == \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC && 
                        \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d H:i:s');
                    }
                    
                    $rowData[$header] = $value;
                    
                    // 检查是否有数据
                    if (!empty($value) && $value !== null && $value !== '') {
                        $hasData = true;
                    } else {
                        $isEmptyCount++;
                    }
                }
                
                // 如果是最后一行，记录更详细的日志
                if ($isLastRow) {
                    error_log('Excel to WP Publisher: 处理最后一行 - 行号: ' . $rowIndex . ', 空单元格数: ' . $isEmptyCount . ', 总单元格数: ' . $totalCells);
                    error_log('Excel to WP Publisher: 最后一行数据: ' . print_r($rowData, true));
                    
                    // 我们应该检查最后一行是否真的有有效数据，而不是盲目添加
                    if ($hasData || ($isEmptyCount < $totalCells)) {
                        $data[] = $rowData;
                        $processedRows++;
                        error_log('Excel to WP Publisher: 添加最后一行有效数据到结果中');
                    } else {
                        error_log('Excel to WP Publisher: 最后一行没有有效数据，不添加到结果中');
                        $skippedRows++;
                    }
                    continue; // 处理完最后一行后，继续下一次循环
                }
                
                // 只添加有数据的行
                if ($hasData || ($isEmptyCount < $totalCells)) {
                    $data[] = $rowData;
                    $processedRows++;
                } else {
                    $skippedRows++;
                    error_log('Excel to WP Publisher: 获取数据时跳过空行 - 行号: ' . $rowIndex);
                }
            }
            
            error_log('Excel to WP Publisher: 获取数据统计 - 有效行数: ' . $processedRows . ', 跳过空行数: ' . $skippedRows . ', 总处理行数: ' . ($processedRows + $skippedRows));
            
            if (empty($data)) {
                error_log('Excel to WP Publisher: 未找到有效的数据行');
            }
            
            return $data;
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            error_log('Excel to WP Publisher: 读取Excel文件失败 - ' . $e->getMessage());
            return array();
        } catch (\Exception $e) {
            error_log('Excel to WP Publisher: ' . $e->getMessage());
            return array();
        }
    }
}