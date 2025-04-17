<?php
/**
 * 处理图片上传的类
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 */

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 处理图片上传的类
 *
 * 这个类负责将本地图片上传到WordPress媒体库
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 * @author     WordPress Developer
 */
class Excel_To_WP_Publisher_Image_Handler {

    /**
     * 初始化类
     *
     * @since    1.0.0
     */
    public function __construct() {
        // 确保WordPress媒体函数可用
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        if (!function_exists('wp_insert_attachment')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }
    }

    /**
     * 从本地路径上传图片到WordPress媒体库
     *
     * @since    1.0.0
     * @param    string    $image_path    本地图片路径
     * @param    int       $post_id       关联的文章ID，默认为0
     * @param    bool      $rename_by_title 是否使用文章标题重命名图片，默认为false
     * @return   int|bool                 附件ID或失败时返回false
     */
    public function upload_image($image_path, $post_id = 0, $rename_by_title = false) {
        // 记录原始路径以便调试
        error_log('Excel to WP Publisher: 尝试上传图片，原始路径 - ' . $image_path);
        
        // 检查路径是否为空
        if (empty($image_path)) {
            $this->add_import_error('图片路径为空');
            return false;
        }
        
        // 记录更多详细信息用于调试
        error_log('Excel to WP Publisher: 图片处理开始，文章ID - ' . $post_id);
        
        // 记录服务器环境信息，帮助调试
        error_log('Excel to WP Publisher: 服务器操作系统 - ' . PHP_OS);
        error_log('Excel to WP Publisher: PHP版本 - ' . PHP_VERSION);
        error_log('Excel to WP Publisher: 当前工作目录 - ' . getcwd());
        error_log('Excel to WP Publisher: 文件是否存在检查 - ' . (file_exists($image_path) ? '存在' : '不存在'));
        
        // 检查文件权限
        if (file_exists($image_path)) {
            error_log('Excel to WP Publisher: 文件权限 - ' . substr(sprintf('%o', fileperms($image_path)), -4));
            error_log('Excel to WP Publisher: 文件大小 - ' . filesize($image_path) . ' 字节');
            
            // 检查文件是否为空
            if (filesize($image_path) === 0) {
                $this->add_import_error('图片文件为空 - ' . $image_path);
                return false;
            }
        }
        
        // 检查是否为网络URL
        if (filter_var($image_path, FILTER_VALIDATE_URL)) {
            error_log('Excel to WP Publisher: 检测到网络URL - ' . $image_path);
            // 下载远程图片到临时文件
            $temp_file = download_url($image_path);
            
            if (is_wp_error($temp_file)) {
                error_log('Excel to WP Publisher: 下载远程图片失败 - ' . $temp_file->get_error_message());
                return false;
            }
            
            error_log('Excel to WP Publisher: 成功下载远程图片到 - ' . $temp_file);
            $image_path = $temp_file;
        } else {
            // 处理本地路径格式
            $image_path = $this->normalize_path($image_path);
            error_log('Excel to WP Publisher: 规范化后的路径 - ' . $image_path);
            
            // 检查规范化后的路径是否为空或文件是否存在
            if (empty($image_path) || !file_exists($image_path)) {
                $this->add_import_error('图片文件不存在或路径无效 - ' . $image_path);
                return false;
            }
        }

        // 获取文件信息
        $file_info = pathinfo($image_path);
        if (!isset($file_info['basename']) || empty($file_info['basename'])) {
            $this->add_import_error('无法获取文件信息 - ' . $image_path);
            return false;
        }
        
        $file_name = $file_info['basename'];
        $file_type = wp_check_filetype($file_name);

        // 检查是否为有效的图片类型
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (empty($file_type['type']) || !in_array($file_type['type'], $allowed_types)) {
            $this->add_import_error('不支持的图片类型 - ' . $file_name . '，类型: ' . (empty($file_type['type']) ? '未知' : $file_type['type']));
            return false;
        }

        // 获取上传目录信息
        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['error']) && !empty($upload_dir['error'])) {
            error_log('Excel to WP Publisher: 获取上传目录失败 - ' . $upload_dir['error']);
            return false;
        }
        
        // 检查上传目录是否存在且可写
        if (!is_dir($upload_dir['path']) || !is_writable($upload_dir['path'])) {
            error_log('Excel to WP Publisher: 上传目录不存在或不可写 - ' . $upload_dir['path']);
            // 尝试创建目录
            if (!wp_mkdir_p($upload_dir['path'])) {
                error_log('Excel to WP Publisher: 无法创建上传目录 - ' . $upload_dir['path']);
                return false;
            }
        }
        
        // 如果需要使用文章标题重命名图片
        if ($rename_by_title && $post_id > 0) {
            // 获取文章标题
            $post_title = get_the_title($post_id);
            
            if (!empty($post_title)) {
                error_log('Excel to WP Publisher: 尝试使用文章标题重命名图片 - ' . $post_title);
                
                // 加载中文转拼音类
                require_once(ETWP_PLUGIN_DIR . 'includes/class-excel-to-wp-publisher-pinyin.php');
                $pinyin = new Excel_To_WP_Publisher_Pinyin();
                
                // 将文章标题转换为适合文件名的拼音
                $filename_base = $pinyin->convert_to_filename($post_title);
                
                // 获取原始文件扩展名
                $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                
                // 创建新的文件名
                $new_filename = $filename_base . '.' . $ext;
                
                error_log('Excel to WP Publisher: 生成的文件名 - ' . $new_filename);
                
                // 确保文件名唯一
                $unique_filename = wp_unique_filename($upload_dir['path'], $new_filename);
            } else {
                // 如果无法获取文章标题，使用原始文件名
                $unique_filename = wp_unique_filename($upload_dir['path'], $file_name);
            }
        } else {
            // 使用原始文件名
            $unique_filename = wp_unique_filename($upload_dir['path'], $file_name);
        }
        
        $temp_file = $upload_dir['path'] . '/' . $unique_filename;

        // 复制文件到上传目录
        error_log('Excel to WP Publisher: 尝试复制图片到 - ' . $temp_file);
        
        // 检查源文件是否可读
        if (!is_readable($image_path)) {
            error_log('Excel to WP Publisher: 源图片文件不可读 - ' . $image_path);
            // 尝试修改权限
            @chmod($image_path, 0644);
            if (!is_readable($image_path)) {
                error_log('Excel to WP Publisher: 修改权限后仍然不可读 - ' . $image_path);
                // 如果是网络URL下载的临时文件，可能权限问题
                if (filter_var($image_path, FILTER_VALIDATE_URL) === false && file_exists($image_path)) {
                    error_log('Excel to WP Publisher: 尝试读取文件内容作为最后手段');
                    // 尝试直接读取文件内容，绕过权限检查
                    $file_content = @file_get_contents($image_path);
                    if ($file_content !== false) {
                        // 创建新的临时文件
                        $new_temp = wp_tempnam();
                        if (@file_put_contents($new_temp, $file_content)) {
                            error_log('Excel to WP Publisher: 成功创建新的临时文件 - ' . $new_temp);
                            $image_path = $new_temp;
                        } else {
                            error_log('Excel to WP Publisher: 无法创建新的临时文件');
                            return false;
                        }
                    } else {
                        error_log('Excel to WP Publisher: 无法读取文件内容');
                        return false;
                    }
                } else {
                    return false;
                }
            }
        }
        
        // 检查目标目录是否可写
        $target_dir = dirname($temp_file);
        if (!is_writable($target_dir)) {
            error_log('Excel to WP Publisher: 目标目录不可写 - ' . $target_dir);
            // 尝试修改权限
            @chmod($target_dir, 0755);
            if (!is_writable($target_dir)) {
                error_log('Excel to WP Publisher: 修改权限后目标目录仍然不可写 - ' . $target_dir);
                return false;
            }
        }
        
        // 尝试使用copy函数
        if (function_exists('copy')) {
            // 清除之前的错误
            error_clear_last();
            $copy_result = @copy($image_path, $temp_file);
            if ($copy_result) {
                error_log('Excel to WP Publisher: 使用copy函数成功复制图片文件');
            } else {
                $error = error_get_last();
                error_log('Excel to WP Publisher: copy函数失败 - ' . $image_path . ' -> ' . $temp_file . '，错误信息: ' . ($error ? $error['message'] : '未知错误'));
            }
        } else {
            error_log('Excel to WP Publisher: copy函数不可用');
            $copy_result = false;
        }
        
        // 如果copy失败或不可用，使用file_get_contents和file_put_contents
        if (!$copy_result) {
            error_log('Excel to WP Publisher: 尝试使用file_get_contents和file_put_contents');
            
            // 清除之前的错误
            error_clear_last();
            $file_content = @file_get_contents($image_path);
            if ($file_content === false) {
                $error = error_get_last();
                error_log('Excel to WP Publisher: 读取图片文件失败 - ' . $image_path . '，错误信息: ' . ($error ? $error['message'] : '未知错误'));
                
                // 尝试使用curl作为最后的手段（对于网络URL）
                if (filter_var($image_path, FILTER_VALIDATE_URL) && function_exists('curl_init')) {
                    error_log('Excel to WP Publisher: 尝试使用curl下载图片');
                    $ch = curl_init($image_path);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    $file_content = curl_exec($ch);
                    $curl_error = curl_error($ch);
                    curl_close($ch);
                    
                    if (empty($file_content)) {
                        error_log('Excel to WP Publisher: curl下载失败 - ' . $curl_error);
                        return false;
                    }
                    error_log('Excel to WP Publisher: 使用curl成功下载图片');
                } else {
                    return false;
                }
            }
            
            // 清除之前的错误
            error_clear_last();
            $write_result = @file_put_contents($temp_file, $file_content);
            if ($write_result === false) {
                $error = error_get_last();
                error_log('Excel to WP Publisher: 写入图片文件失败 - ' . $temp_file . '，错误信息: ' . ($error ? $error['message'] : '未知错误'));
                return false;
            }
            
            error_log('Excel to WP Publisher: 成功复制图片文件，大小: ' . $write_result . ' 字节');
        }

        // 检查文件是否成功复制
        if (!file_exists($temp_file)) {
            error_log('Excel to WP Publisher: 复制后的文件不存在 - ' . $temp_file);
            return false;
        }

        // 准备附件数据
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/' . $unique_filename,
            'post_mime_type' => $file_type['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', $unique_filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $post_id
        );

        // 插入附件
        error_log('Excel to WP Publisher: 尝试插入附件 - ' . $temp_file);
        $attachment_id = wp_insert_attachment($attachment, $temp_file, $post_id);

        if (!is_wp_error($attachment_id) && $attachment_id > 0) {
            error_log('Excel to WP Publisher: 附件插入成功，ID: ' . $attachment_id);
            // 生成附件的元数据
            try {
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $temp_file);
                if (empty($attachment_data)) {
                    error_log('Excel to WP Publisher: 生成附件元数据失败 - 返回空数据');
                    // 即使元数据生成失败，我们仍然可以继续，因为附件已经创建
                } else {
                    wp_update_attachment_metadata($attachment_id, $attachment_data);
                }
                
                // 如果有关联文章，设置特色图片
                if ($post_id > 0) {
                    set_post_thumbnail($post_id, $attachment_id);
                    error_log('Excel to WP Publisher: 已设置文章特色图片，文章ID: ' . $post_id . '，图片ID: ' . $attachment_id);
                }
                
                return $attachment_id;
            } catch (Exception $e) {
                error_log('Excel to WP Publisher: 处理附件元数据时发生异常 - ' . $e->getMessage());
                // 尝试返回附件ID，即使元数据处理失败
                return $attachment_id;
            }
        } else {
            if (is_wp_error($attachment_id)) {
                error_log('Excel to WP Publisher: 创建附件失败 - ' . $attachment_id->get_error_message());
            } else {
                error_log('Excel to WP Publisher: 创建附件失败 - 未知错误');
            }
            // 清理临时文件
            @unlink($temp_file);
            return false;
        }
    }

    /**
     * 获取图片URL
     *
     * @since    1.0.0
     * @param    int       $attachment_id    附件ID
     * @param    string    $size             图片尺寸
     * @return   string                      图片URL
     */
    public function get_image_url($attachment_id, $size = 'full') {
        $image = wp_get_attachment_image_src($attachment_id, $size);
        return $image ? $image[0] : '';
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
     * 规范化文件路径，处理各种路径格式
     *
     * @since    1.0.0
     * @param    string    $path    原始路径
     * @return   string             规范化后的路径
     */
    private function normalize_path($path) {
        // 记录原始路径以便调试
        error_log('Excel to WP Publisher: 规范化路径前 - ' . $path);
        
        // 如果路径为空，直接返回
        if (empty($path)) {
            error_log('Excel to WP Publisher: 路径为空');
            return '';
        }
        
        // 检测操作系统类型
        $is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        $is_mac = (strtoupper(substr(PHP_OS, 0, 6)) === 'DARWIN' || strtoupper(substr(PHP_OS, 0, 3)) === 'MAC');
        
        error_log('Excel to WP Publisher: 检测到操作系统 - ' . PHP_OS . ' (Windows: ' . ($is_windows ? '是' : '否') . ', Mac: ' . ($is_mac ? '是' : '否') . ')');
        
        // 移除可能存在的文件协议前缀
        $path = preg_replace('/^file:\/\//', '', $path);
        error_log('Excel to WP Publisher: 移除文件协议后 - ' . $path);
        
        // 处理空格编码和其他URL编码字符
        $path = str_replace('%20', ' ', $path);
        $path = urldecode($path); // 处理所有URL编码字符
        error_log('Excel to WP Publisher: URL解码后 - ' . $path);
        
        // 处理Windows系统路径格式
        if ($is_windows || preg_match('/^[a-zA-Z]:\\/', $path) || preg_match('/^[a-zA-Z]:/', $path)) {
            error_log('Excel to WP Publisher: 检测到Windows系统路径格式 - ' . $path);
            // 确保Windows路径使用正确的分隔符
            $path = str_replace('/', '\\', $path);
            // 检查文件是否存在
            if (file_exists($path)) {
                error_log('Excel to WP Publisher: Windows系统路径文件存在 - ' . $path);
                return $path;
            } else {
                error_log('Excel to WP Publisher: Windows系统路径文件不存在 - ' . $path . '，尝试其他方法');
                // 不要立即返回，继续尝试其他方法
            }
        }
        
        // 处理Mac系统路径格式
        if ($is_mac || strpos($path, '/Users/') === 0 || strpos($path, '/Volumes/') === 0) {
            error_log('Excel to WP Publisher: 检测到Mac系统路径格式 - ' . $path);
            // 检查文件是否存在
            if (file_exists($path)) {
                error_log('Excel to WP Publisher: Mac系统路径文件存在 - ' . $path);
                return $path;
            } else {
                error_log('Excel to WP Publisher: Mac系统路径文件不存在 - ' . $path . '，尝试其他方法');
                // 不要立即返回，继续尝试其他方法
            }
        }
        
        // 确保路径使用正确的目录分隔符（统一使用/）
        $path = str_replace('\\', '/', $path);
        
        // 记录更多详细信息用于调试
        error_log('Excel to WP Publisher: 处理后的路径 - ' . $path);
        error_log('Excel to WP Publisher: 文件是否存在 - ' . (file_exists($path) ? '是' : '否'));
        
        // 检查路径是否为网络URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            error_log('Excel to WP Publisher: 检测到网络URL - ' . $path);
            // 如果是网络URL，尝试下载图片到临时目录
            $temp_file = download_url($path);
            if (!is_wp_error($temp_file)) {
                error_log('Excel to WP Publisher: 成功下载远程图片到 - ' . $temp_file);
                return $temp_file;
            } else {
                error_log('Excel to WP Publisher: 下载远程图片失败 - ' . $path . '，错误: ' . $temp_file->get_error_message());
                // 返回空字符串，表示处理失败
                return '';
            }
        }
        
        // 处理Windows系统路径格式
        if ($is_windows || preg_match('/^[a-zA-Z]:\\/', $path) || preg_match('/^[a-zA-Z]:/', $path)) {
            error_log('Excel to WP Publisher: 检测到Windows系统路径格式 - ' . $path);
            // 确保Windows路径使用正确的分隔符
            $path = str_replace('/', '\\', $path);
            // 检查文件是否存在
            if (file_exists($path)) {
                error_log('Excel to WP Publisher: Windows系统路径文件存在 - ' . $path);
                return $path;
            } else {
                error_log('Excel to WP Publisher: Windows系统路径文件不存在 - ' . $path . '，尝试其他方法');
                // 不要立即返回，继续尝试其他方法
            }
        }
        
        // 处理Mac系统路径格式
        if ($is_mac || strpos($path, '/Users/') === 0 || strpos($path, '/Volumes/') === 0) {
            error_log('Excel to WP Publisher: 检测到Mac系统路径格式 - ' . $path);
            // 检查文件是否存在
            if (file_exists($path)) {
                error_log('Excel to WP Publisher: Mac系统路径文件存在 - ' . $path);
                return $path;
            } else {
                error_log('Excel to WP Publisher: Mac系统路径文件不存在 - ' . $path . '，尝试其他方法');
                // 不要立即返回，继续尝试其他方法
            }
        }
        
        // 检测操作系统类型
        $is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        $is_mac = (strtoupper(substr(PHP_OS, 0, 6)) === 'DARWIN' || strtoupper(substr(PHP_OS, 0, 3)) === 'MAC');
        
        error_log('Excel to WP Publisher: 检测到操作系统 - ' . PHP_OS . ' (Windows: ' . ($is_windows ? '是' : '否') . ', Mac: ' . ($is_mac ? '是' : '否') . ')');
        
        // 处理Windows系统路径格式
        if ($is_windows || preg_match('/^[a-zA-Z]:\\/', $path) || preg_match('/^[a-zA-Z]://', $path)) {
            error_log('Excel to WP Publisher: 检测到Windows系统路径格式 - ' . $path);
            // 检查文件是否存在
            if (file_exists($path)) {
                error_log('Excel to WP Publisher: Windows系统路径文件存在 - ' . $path);
                return $path;
            } else {
                error_log('Excel to WP Publisher: Windows系统路径文件不存在 - ' . $path . '，尝试其他方法');
                // 不要立即返回，继续尝试其他方法
            }
        }
        
        // 处理Mac系统路径格式
        if ($is_mac || strpos($path, '/Users/') === 0 || strpos($path, '/Volumes/') === 0) {
            error_log('Excel to WP Publisher: 检测到Mac系统路径格式 - ' . $path);
            // 检查文件是否存在
            if (file_exists($path)) {
                error_log('Excel to WP Publisher: Mac系统路径文件存在 - ' . $path);
                return $path;
            } else {
                error_log('Excel to WP Publisher: Mac系统路径文件不存在 - ' . $path . '，尝试其他方法');
                // 不要立即返回，继续尝试其他方法
            }
        }
        
        // 处理相对路径
        if (strpos($path, './') === 0 || strpos($path, '../') === 0 || !preg_match('/^(\/|[a-zA-Z]:\\|https?:\/\/)/', $path)) {
            // 这是一个相对路径
            error_log('Excel to WP Publisher: 检测到相对路径 - ' . $path);
            
            // 尝试多种可能的基础路径
            $possible_base_paths = array();
            
            // Excel文件所在目录（如果可用，优先级最高）
            $excel_file = get_option('etwp_excel_file');
            if (!empty($excel_file) && file_exists($excel_file)) {
                $possible_base_paths[] = dirname($excel_file);
                error_log('Excel to WP Publisher: 添加Excel文件所在目录 - ' . dirname($excel_file));
            }
            
            // 当前上传目录
            $upload_dir = wp_upload_dir();
            $possible_base_paths[] = $upload_dir['basedir'];
            $possible_base_paths[] = $upload_dir['path']; // 当前月份的上传目录
            
            // WordPress内容目录
            $possible_base_paths[] = WP_CONTENT_DIR;
            
            // WordPress根目录
            $possible_base_paths[] = ABSPATH;
            
            // 插件目录
            $possible_base_paths[] = ETWP_PLUGIN_DIR;
            
            // 当前工作目录
            $possible_base_paths[] = getcwd();
            error_log('Excel to WP Publisher: 添加当前工作目录 - ' . getcwd());
            
            // 移除开头的./ 或 ../
            $relative_path = preg_replace('/^\.\.|\.\//','', $path);
            // 也尝试原始路径（可能是相对于当前目录的路径）
            $paths_to_try = array($relative_path, $path);
            
            foreach ($possible_base_paths as $base_path) {
                foreach ($paths_to_try as $try_path) {
                    $test_path = rtrim($base_path, '/') . '/' . $try_path;
                    error_log('Excel to WP Publisher: 尝试路径 - ' . $test_path);
                    if (file_exists($test_path)) {
                        error_log('Excel to WP Publisher: 找到匹配的文件 - ' . $test_path);
                        return $test_path;
                    }
                }
            }
            
            // 如果所有尝试都失败，尝试直接使用路径（可能是服务器上的绝对路径）
            if (file_exists($path)) {
                error_log('Excel to WP Publisher: 直接使用原始路径 - ' . $path);
                return $path;
            }
            
            // 所有尝试都失败
            error_log('Excel to WP Publisher: 无法解析相对路径 - ' . $path);
            return '';
        }
        
        // 检查绝对路径是否存在
        if (!file_exists($path)) {
            error_log('Excel to WP Publisher: 文件不存在 - ' . $path);
            return '';
        }
        
        error_log('Excel to WP Publisher: 规范化路径后 - ' . $path);
        return $path;
    }
    
    /**
     * 检测字符串是否为图片路径
     *
     * @since    1.0.0
     * @param    string    $value    要检查的字符串
     * @return   bool                是否为图片路径
     */
    public function is_image_path($value) {
        // 如果值为空，直接返回false
        if (empty($value)) {
            error_log('Excel to WP Publisher: is_image_path - 值为空');
            return false;
        }
        
        // 记录正在检查的值
        error_log('Excel to WP Publisher: is_image_path - 检查值: ' . $value);
        
        // 支持的图片扩展名
        $supported_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'svg', 'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'BMP', 'TIFF', 'TIF', 'SVG'];
        
        // 如果值看起来像是Base64编码的图片数据
        if (strpos($value, 'data:image/') === 0 && strpos($value, ';base64,') !== false) {
            error_log('Excel to WP Publisher: is_image_path - 检测到Base64编码的图片数据');
            return true;
        }
        
        // 检查是否为URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $parsed_url = parse_url($value);
            if (!isset($parsed_url['path'])) {
                error_log('Excel to WP Publisher: is_image_path - URL没有路径部分: ' . $value);
                return false;
            }
            
            $ext = pathinfo($parsed_url['path'], PATHINFO_EXTENSION);
            $is_image = in_array(strtolower($ext), $supported_extensions);
            
            // 如果URL中包含图片相关关键词，也认为是图片
            if (!$is_image && (strpos($value, 'image') !== false || strpos($value, 'photo') !== false || strpos($value, 'picture') !== false)) {
                error_log('Excel to WP Publisher: is_image_path - URL包含图片关键词，尝试作为图片处理: ' . $value);
                return true;
            }
            
            error_log('Excel to WP Publisher: is_image_path - URL检查结果: ' . ($is_image ? '是图片' : '不是图片') . ', 扩展名: ' . $ext);
            return $is_image;
        }
        
        // 检查是否为本地路径
        if (strpos($value, '/') !== false || strpos($value, '\\') !== false) {
            // 先检查原始路径的扩展名
            $original_ext = pathinfo($value, PATHINFO_EXTENSION);
            if (!empty($original_ext) && in_array(strtolower($original_ext), $supported_extensions)) {
                error_log('Excel to WP Publisher: is_image_path - 原始路径是图片: ' . $value);
                return true;
            }
            
            // 尝试规范化路径
            $normalized_path = $this->normalize_path($value);
            
            // 如果normalize_path返回空，则不是有效路径
            if (empty($normalized_path)) {
                error_log('Excel to WP Publisher: is_image_path - 规范化路径为空');
                return false;
            }
            
            $path_info = pathinfo($normalized_path);
            if (!isset($path_info['extension'])) {
                error_log('Excel to WP Publisher: is_image_path - 路径没有扩展名: ' . $normalized_path);
                return false;
            }
            
            $ext = $path_info['extension'];
            $is_image = in_array(strtolower($ext), $supported_extensions);
            
            error_log('Excel to WP Publisher: is_image_path - 本地路径检查结果: ' . ($is_image ? '是图片' : '不是图片') . ', 扩展名: ' . $ext);
            return $is_image;
        }
        
        // 检查是否为简单文件名（可能是相对路径）
        $ext = pathinfo($value, PATHINFO_EXTENSION);
        if (!empty($ext)) {
            $is_image = in_array(strtolower($ext), $supported_extensions);
            error_log('Excel to WP Publisher: is_image_path - 简单文件名检查结果: ' . ($is_image ? '是图片' : '不是图片') . ', 扩展名: ' . $ext);
            return $is_image;
        }
        
        error_log('Excel to WP Publisher: is_image_path - 不是图片路径: ' . $value);
        return false;
    }
}