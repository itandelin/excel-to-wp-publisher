/**
 * 插件管理页面的JavaScript
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('初始化Excel导入模块...');
        
        // 初始化各种功能
            addQuickMappingButtons();
        autoMapFields();
            addFieldSearchFunction();
            setupSaveMappingFunction();
        
        if ($('#drag-drop-mapping').length) {
            initDragAndDrop();
        }
            
            addSelectMappingOption();
            
        // 初始化进度UI
        if ($('#import-progress-container').length === 0) {
            $('<div id="import-progress-container" class="etwp-progress-container" style="display:none;">' +
              '<div class="etwp-progress">' +
              '<div id="import-progress-bar" class="etwp-progress-bar"></div>' +
              '</div>' +
              '<div id="import-status" class="etwp-status">准备导入...</div>' +
              '</div>').insertBefore($('#import-log-container'));
        }
        
        // 添加开始导入和取消导入按钮的点击事件
        $('#start-import').on('click', function(e) {
            e.preventDefault();
            console.log('开始导入按钮被点击');
                startImport();
            });
        
        $('#cancel-import').on('click', function(e) {
            e.preventDefault();
            cancelImport();
        });
        
        // 添加上传文件验证
            $('#excel_file').on('change', function() {
                validateFileSelection(this);
            });
        
        // 添加日志样式
        addLogStyles();
        
        console.log('Excel导入模块初始化完成');
    });
    
    /**
     * 添加快速映射按钮
     */
    function addQuickMappingButtons() {
        // 添加自动映射按钮
        const $mappingHeader = $('.etwp-mapping-table').prev('h2');
        $mappingHeader.after(
            '<div class="etwp-quick-actions">' +
            '<button type="button" class="button" id="etwp-auto-map">' + 
            '自动映射相似字段</button>' +
            '<button type="button" class="button" id="etwp-clear-map">' + 
            '清除所有映射</button>' +
            '</div>'
        );
        
        // 自动映射功能
        $('#etwp-auto-map').on('click', function() {
            autoMapFields();
        });
        
        // 清除映射功能
        $('#etwp-clear-map').on('click', function() {
            $('.etwp-mapping-table select').val('');
        });
    }
    
    /**
     * 自动映射相似字段
     */
    function autoMapFields() {
        $('.etwp-mapping-table tbody tr').each(function() {
            const excelField = $(this).find('td:first').text().toLowerCase();
            const $select = $(this).find('select');
            
            // 已经有选择的跳过
            if ($select.val()) {
                return;
            }
            
            // 尝试查找匹配的WordPress字段
            $select.find('option').each(function() {
                const wpField = $(this).text().toLowerCase();
                const wpValue = $(this).val();
                
                if (wpValue && (excelField === wpField || excelField.includes(wpField) || wpField.includes(excelField))) {
                    $select.val(wpValue);
                    return false; // 找到匹配项，跳出循环
                }
            });
            
            // 特殊字段映射规则
            if (!$select.val()) {
                // 标题字段
                if (excelField.includes('标题') || excelField.includes('title')) {
                    $select.val('post_title');
                }
                // 内容字段
                else if (excelField.includes('内容') || excelField.includes('正文') || excelField.includes('content')) {
                    $select.val('post_content');
                }
                // 摘要字段
                else if (excelField.includes('摘要') || excelField.includes('excerpt')) {
                    $select.val('post_excerpt');
                }
                // 图片字段
                else if (excelField.includes('图片') || excelField.includes('image') || excelField.includes('photo')) {
                    $select.val('_thumbnail_id');
                }
            }
        });
    }
    
    /**
     * 添加字段搜索功能
     */
    function addFieldSearchFunction() {
        const $mappingTable = $('.etwp-mapping-table');
        $mappingTable.before(
            '<div class="etwp-search-field">' +
            '<input type="text" id="etwp-search-field" placeholder="搜索字段..." />' +
            '</div>'
        );
        
        $('#etwp-search-field').on('keyup', function() {
            const searchText = $(this).val().toLowerCase();
            
            $('.etwp-mapping-table tbody tr').each(function() {
                const excelField = $(this).find('td:first').text().toLowerCase();
                const wpFields = $(this).find('select option').map(function() {
                    return $(this).text().toLowerCase();
                }).get().join(' ');
                
                if (excelField.includes(searchText) || wpFields.includes(searchText)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
    }
    
    /**
     * 设置保存映射功能
     */
    function setupSaveMappingFunction() {
        // 添加保存映射设置按钮
        const $actionsDiv = $('.etwp-actions');
        $actionsDiv.prepend(
            '<button type="button" class="button" id="etwp-save-mapping">' + 
            '保存映射设置</button>'
        );
        
        // 添加加载映射设置按钮
        $actionsDiv.prepend(
            '<button type="button" class="button" id="etwp-load-mapping">' + 
            '加载上次映射</button>'
        );
        
        // 保存映射设置
        $('#etwp-save-mapping').on('click', function() {
            const mappingData = {};
            
            // 检查当前使用哪种映射方式
            if ($('.etwp-select-mapping').is(':visible')) {
                // 从选择框获取映射数据
                $('.excel-to-wp-mapping').each(function() {
                    const excelField = $(this).attr('data-excel-field');
                    const wpField = $(this).val();
                    
                    if (wpField) {
                        mappingData[excelField] = wpField;
                    }
                });
            } else {
                // 从标准映射表格中获取数据
            $('.etwp-mapping-table tbody tr').each(function() {
                const excelField = $(this).find('td:first').text();
                const wpField = $(this).find('select').val();
                
                if (wpField) {
                    mappingData[excelField] = wpField;
                }
            });
            }
            
            // 检查是否有映射数据
            if (Object.keys(mappingData).length === 0) {
                alert('请至少设置一个字段映射');
                return;
            }
            
            // 使用AJAX保存映射设置
            $.ajax({
                url: etwp_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'etwp_save_mapping',
                    nonce: etwp_vars.nonce,
                    mapping: JSON.stringify(mappingData)
                },
                success: function(response) {
                    if (response.success) {
                        alert('映射设置已保存');
                    } else {
                        alert('保存映射设置失败: ' + (response.data || '未知错误'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('保存映射设置时发生错误: ' + error);
                }
            });
        });
        
        // 加载映射设置
        $('#etwp-load-mapping').on('click', function() {
            // 使用AJAX加载映射设置
            $.ajax({
                url: etwp_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'etwp_load_mapping',
                    nonce: etwp_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        applyMappingSettings(response.data);
                        alert('已加载上次的映射设置');
                    } else {
                        alert('没有找到保存的映射设置');
                    }
                },
                error: function() {
                    alert('加载映射设置时发生错误');
                }
            });
        });
    }
    
    /**
     * 应用映射设置
     */
    function applyMappingSettings(mappingData) {
        $('.etwp-mapping-table tbody tr').each(function() {
            const excelField = $(this).find('td:first').text();
            const $select = $(this).find('select');
            
            if (mappingData[excelField]) {
                $select.val(mappingData[excelField]);
            }
        });
    }
    
    /**
     * 验证文件选择
     */
    function validateFileSelection(fileInput) {
        const fileName = fileInput.value;
        const fileExt = fileName.split('.').pop().toLowerCase();
        
        if (!fileName) {
            return;
        }
        
        if (['xlsx', 'xls', 'csv'].indexOf(fileExt) === -1) {
            alert('请选择有效的Excel文件（.xlsx, .xls, .csv）');
            fileInput.value = '';
        }
    }
    
    /**
     * 添加CSS样式以支持日志级别显示
     */
    function addLogStyles() {
        if ($('#etwp-log-styles').length === 0) {
            $('head').append(
                '<style id="etwp-log-styles">' +
                '.import-log { max-height: 300px; overflow-y: auto; background-color: #f5f5f5; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; font-family: monospace; white-space: pre-wrap; min-height: 100px; }' +
                '.log-entry { margin-bottom: 3px; line-height: 1.4; padding: 3px; }' +
                '.log-entry.info { color: #333; }' +
                '.log-entry.success { color: #46b450; background-color: rgba(70, 180, 80, 0.05); }' +
                '.log-entry.warning { color: #ffb900; background-color: rgba(255, 185, 0, 0.05); }' +
                '.log-entry.error { color: #dc3232; background-color: rgba(220, 50, 50, 0.05); }' +
                '.log-entry.error-detail { color: #dc3232; margin-left: 20px; font-size: 0.9em; background-color: rgba(220, 50, 50, 0.1); padding: 5px 10px; border-left: 2px solid #dc3232; font-family: monospace; white-space: pre-wrap; word-break: break-all; }' +
                '.timestamp { color: #666; font-size: 0.9em; margin-right: 5px; }' +
                '#import-log-container { margin-top: 20px; }' +
                '#import-log-container h3 { margin-top: 0; }' +
                '.log-actions { margin-top: 10px; }' +
                '</style>'
            );
        }
    }
    
    /**
     * 初始化拖拽功能
     */
    function initDragAndDrop() {
        // 确保jQuery UI已正确加载
        if (typeof $.fn.draggable !== 'function' || typeof $.fn.droppable !== 'function') {
            console.error('jQuery UI 拖拽功能未正确加载');
            return;
        }
        
        console.log('开始初始化拖拽功能...');
        
        // 确保所有Excel字段都有正确的data-field属性
        $('#excel-fields .etwp-field').each(function() {
            if (!$(this).attr('data-field')) {
                const fieldName = $(this).find('.field-name').text();
                $(this).attr('data-field', fieldName);
                console.log('修复缺失的data-field属性:', fieldName);
            }
        });
        
        // 先解除所有现有的draggable绑定
        try {
            $('#excel-fields .etwp-field').draggable('destroy');
        } catch (e) {
            console.log('没有现有的draggable绑定或无法销毁:', e.message);
        }
        
        // 使Excel字段可拖拽
        $('#excel-fields .etwp-field').draggable({
            helper: 'clone',
            revert: 'invalid',
            cursor: 'move',
            opacity: 0.7,
            zIndex: 100,
            containment: '.etwp-mapping-form'
        });
        
        // 先移除所有现有的droppable绑定，防止重复绑定
        try {
            $('.field-drop-zone').droppable('destroy');
        } catch (e) {
            console.log('没有现有的droppable绑定或无法销毁:', e.message);
        }
        
        // 使WordPress字段的放置区域可接收拖拽
        $('.field-drop-zone').droppable({
            accept: '#excel-fields .etwp-field',
            hoverClass: 'ui-droppable-hover',
            activeClass: 'ui-droppable-active',
            drop: function(event, ui) {
                // 统一使用data-field属性获取Excel字段名称
                let excelField = ui.draggable.attr('data-field');
                
                // 如果data-field属性不存在，尝试从field-name元素获取
                if (!excelField) {
                    excelField = ui.draggable.find('.field-name').text();
                    // 确保获取到字段名后，设置回data-field属性，保证一致性
                    ui.draggable.attr('data-field', excelField);
                    console.log('从field-name获取字段名并设置data-field属性:', excelField);
                }
                
                const wpField = $(this).attr('data-wp-field');
                
                console.log('拖拽字段:', excelField, '到目标字段:', wpField);
                
                if (!excelField) {
                    console.error('无法获取Excel字段名称');
                    return;
                }
                
                // 清除之前的映射（如果存在）
                $(this).empty();
                
                // 添加映射
                $(this).html(
                    '<div class="dropped-field" data-excel-field="' + excelField + '">' +
                    '<span class="field-name">' + excelField + '</span>' +
                    '<span class="remove-mapping dashicons dashicons-no-alt"></span>' +
                    '</div>'
                );
                
                // 确保映射预览区域存在
                if ($('#mapping-preview').length === 0) {
                    console.error('映射预览区域不存在');
                    return;
                }
                
                // 立即更新映射预览
                updateMappingPreview();
                
                // 添加移除映射的事件
                $('.remove-mapping').off('click').on('click', function(e) {
                    e.stopPropagation(); // 阻止事件冒泡
                    $(this).parent().parent().empty();
                    updateMappingPreview();
                });
            }
        });
        
        // 初始化时立即更新一次映射预览
        updateMappingPreview();
        
        // 添加调试信息
        console.log('拖拽功能已初始化，可拖拽元素数量:', $('#excel-fields .etwp-field').length);
        console.log('放置区域数量:', $('.field-drop-zone').length);
    }
    
    /**
     * 添加选择框映射选项
     */
    function addSelectMappingOption() {
        // 创建选择框映射区域
        const $selectMappingArea = $('<div class="etwp-select-mapping"></div>');
        $('.etwp-mapping-container').after($selectMappingArea);
        
        // 获取Excel字段和WordPress字段
        const excelFields = [];
        $('#excel-fields .etwp-field').each(function() {
            const fieldName = $(this).attr('data-field') || $(this).find('.field-name').text();
            excelFields.push(fieldName);
        });
        
        const wpFields = [];
        $('#wp-fields .etwp-field').each(function() {
            const fieldKey = $(this).attr('data-field');
            const fieldName = $(this).find('.field-name').text();
            wpFields.push({key: fieldKey, name: fieldName});
        });
        
        // 创建选择框映射表格
        let tableHtml = '<table class="etwp-select-mapping-table">' +
                        '<thead><tr><th>Excel字段</th><th>WordPress字段</th></tr></thead>' +
                        '<tbody>';
        
        excelFields.forEach(function(excelField) {
            tableHtml += '<tr>' +
                         '<td>' + excelField + '</td>' +
                         '<td><select class="excel-to-wp-mapping" data-excel-field="' + excelField + '">' +
                         '<option value="">-- 选择字段 --</option>';
            
            wpFields.forEach(function(wpField) {
                tableHtml += '<option value="' + wpField.key + '">' + wpField.name + '</option>';
            });
            
            tableHtml += '</select></td>' +
                         '</tr>';
        });
        
        tableHtml += '</tbody></table>';
        $selectMappingArea.html(tableHtml);
        
        // 添加选择框变更事件
        $('.excel-to-wp-mapping').on('change', function() {
            updateSelectMappingPreview();
        });
        
        // 隐藏拖拽映射容器，显示选择框映射
        $('.etwp-mapping-container').hide();
        $('.etwp-select-mapping').show();
    }
    
    /**
     * 更新选择框映射预览
     */
    function updateSelectMappingPreview() {
        // 获取映射预览区域
        const $preview = $('#mapping-preview');
        if ($preview.length === 0) {
            console.error('找不到映射预览区域 #mapping-preview');
            return;
        }
        
        // 清空预览区域
        $preview.empty();
        
        let mappingCount = 0;
        
        // 收集所有选择框映射
        $('.excel-to-wp-mapping').each(function() {
            const excelField = $(this).attr('data-excel-field');
            const wpField = $(this).val();
            
            if (wpField) {
                const wpFieldName = $(this).find('option:selected').text();
                
                // 添加到映射预览
                $preview.append(
                    '<div class="mapping-item">' +
                    '<div class="excel-field">' + excelField + '</div>' +
                    '<div class="wp-field">' + wpFieldName + '</div>' +
                    '</div>'
                );
                
                mappingCount++;
            }
        });
        
        // 如果没有映射，显示提示
        if (mappingCount === 0) {
            if (typeof etwp_vars !== 'undefined' && etwp_vars.i18n && etwp_vars.i18n.no_mapping) {
                $preview.html('<p>' + etwp_vars.i18n.no_mapping + '</p>');
            } else {
                $preview.html('<p>暂无字段映射</p>');
            }
        }
    }
    
    /**
     * 更新映射预览
     */
    function updateMappingPreview() {
        console.log('开始执行updateMappingPreview函数...');
        
        // 获取映射预览区域
        const $preview = $('#mapping-preview');
        if ($preview.length === 0) {
            console.error('找不到映射预览区域 #mapping-preview');
            return;
        }
        
        // 清空预览区域
        $preview.empty();
        console.log('已清空映射预览区域');
        
        let mappingCount = 0;
        
        // 添加调试信息
        console.log('开始更新映射预览，查找所有放置区域...');
        console.log('放置区域数量:', $('.field-drop-zone').length);
        
        // 收集所有映射
        $('.field-drop-zone').each(function() {
            // 获取当前WordPress字段信息
            const wpField = $(this).attr('data-wp-field');
            const $wpFieldContainer = $(this).closest('.etwp-field');
            const wpFieldName = $wpFieldContainer.find('.field-name').text();
            
            console.log('检查WordPress字段:', wpField, wpFieldName);
            
            // 查找是否有已放置的Excel字段
            const $droppedField = $(this).find('.dropped-field');
            console.log('检查放置区域:', wpField, '是否有已放置字段:', $droppedField.length);
            
            if ($droppedField.length) {
                // 统一使用data-excel-field属性获取Excel字段名称
                let excelField = $droppedField.attr('data-excel-field');
                if (!excelField) {
                    // 如果data-excel-field属性不存在，尝试从field-name元素获取
                    excelField = $droppedField.find('.field-name').text();
                    // 确保获取到字段名后，设置回data-excel-field属性，保证一致性
                    $droppedField.attr('data-excel-field', excelField);
                    console.log('从field-name获取字段名并设置data-excel-field属性:', excelField);
                    
                    // 如果仍然没有找到，尝试直接获取内容
                    if (!excelField) {
                        excelField = $droppedField.text().replace('×', '').trim();
                        // 设置data-excel-field属性
                        $droppedField.attr('data-excel-field', excelField);
                        console.log('从文本内容获取字段名并设置data-excel-field属性:', excelField);
                    }
                }
                
                console.log('映射预览:', excelField, '->', wpFieldName, '(wpField:', wpField + ')');
                
                // 确保字段名称不为空
                if (excelField && wpField) {
                    // 添加到映射预览
                    $preview.append(
                        '<div class="mapping-item">' +
                        '<div class="excel-field">' + excelField + '</div>' +
                        '<div class="wp-field">' + wpFieldName + '</div>' +
                        '</div>'
                    );
                    
                    mappingCount++;
                    console.log('已添加映射项:', excelField, '->', wpFieldName);
                } else {
                    console.error('映射字段名称为空:', 'excelField=', excelField, 'wpField=', wpField);
                }
            }
        });
        
        // 如果没有映射，显示提示
        if (mappingCount === 0) {
            if (typeof etwp_vars !== 'undefined' && etwp_vars.i18n && etwp_vars.i18n.no_mapping) {
                $preview.html('<p>' + etwp_vars.i18n.no_mapping + '</p>');
            } else {
                $preview.html('<p>暂无字段映射</p>');
            }
            console.log('没有找到映射，显示提示信息');
        } else {
            console.log('总共找到 ' + mappingCount + ' 个映射');
        }
    }
    
    /**
     * 重置映射
     */
    function resetMapping() {
        // 清空所有放置区域
        $('.field-drop-zone').empty();
        
        // 清空所有选择框
        $('.excel-to-wp-mapping').val('');
        
        // 确保映射预览也被更新
        const mappingMode = $('input[name="mapping_mode"]:checked').val() || 'drag';
        if (mappingMode === 'drag') {
            updateMappingPreview();
        } else {
            updateSelectMappingPreview();
        }
        
        console.log('所有映射已重置');
    }
    
    /**
     * 记录日志消息
     * @param {string} message - 日志消息
     * @param {string} type - 消息类型（info, success, warning, error）
     */
    function logMessage(message, type = 'info') {
        try {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = $('<div>')
                .addClass('log-entry')
                .addClass(type)
                .html(`<span class="timestamp">[${timestamp}]</span> ${message}`);
            
            // 确保日志容器存在
            if ($('#import-log').length === 0) {
                console.warn('日志容器不存在，创建新容器');
                if ($('#import-progress-container').length) {
                    $('#import-progress-container').after('<div id="import-log" class="import-log"></div>');
                } else {
                    $('body').append('<div id="import-log" class="import-log"></div>');
                }
            }
            
            // 确保日志容器可见
            $('#import-log').show();
            
            // 添加日志条目
            $('#import-log').append(logEntry);
            
            // 滚动到底部
            const $logContainer = $('#import-log');
            if ($logContainer.length) {
                $logContainer.scrollTop($logContainer[0].scrollHeight);
            }
            
            // 强制DOM更新
            $logContainer[0] && $logContainer[0].offsetHeight;
            
            // 同时在控制台输出
            const consoleMethod = type === 'error' ? 'error' : type === 'warning' ? 'warn' : 'log';
            console[consoleMethod](`[${type.toUpperCase()}] ${message}`);
            
            // 如果是错误，检查是否需要显示错误提示
            if (type === 'error') {
                // 添加红色边框提醒
                $('#import-log').addClass('has-error');
                
                // 如果没有明确的错误提示，添加一个
                if ($('.import-error-notice').length === 0) {
                    $('#import-log').before(
                        '<div class="notice notice-error import-error-notice is-dismissible">' +
                        '<p><strong>导入过程中发生错误，请查看下方日志了解详情</strong></p>' +
                        '<button type="button" class="notice-dismiss"><span class="screen-reader-text">忽略此通知。</span></button>' +
                        '</div>'
                    );
                    
                    // 添加忽略通知的事件处理
                    $('.notice-dismiss').on('click', function() {
                        $(this).closest('.notice').remove();
                    });
                }
            }
        } catch (e) {
            // 如果日志记录失败，至少尝试在控制台记录
            console.error('记录日志失败:', e);
            console.log(message);
        }
    }
    
    /**
     * 初始化导入界面
     */
    function initImportUI() {
        // 确保进度容器存在
        if ($('#import-progress-container').length === 0) {
            const progressHTML = `
                <div id="import-progress-container" style="display:none; margin-top: 20px; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3 style="margin-top: 0; color: #1d2327; font-size: 18px; font-weight: 600; margin-bottom: 20px;">导入进度</h3>
                    <div class="import-progress-bar-container" style="height: 24px; background-color: #f0f0f1; border-radius: 12px; margin-bottom: 15px; overflow: hidden; position: relative; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                        <div id="import-progress-bar" class="import-progress-bar" style="height: 100%; background: linear-gradient(90deg, #2271b1, #135e96); width: 0%; transition: width 0.5s ease; position: relative;">
                            <div class="progress-pulse" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0) 100%); animation: progress-pulse 2s infinite;"></div>
                        </div>
                    </div>
                    <p id="import-status" style="margin: 0; font-size: 14px; color: #1d2327; line-height: 1.5;">准备导入...</p>
                    <button type="button" id="cancel-import" class="button button-secondary" style="display:none; margin-top: 15px; padding: 8px 16px; border-radius: 4px; transition: all 0.3s ease;">取消导入</button>
                </div>
            `;
            
            // 在开始导入按钮后添加进度容器
            $('#start-import').after(progressHTML);
            
            // 添加步骤指示器样式
            const style = document.createElement('style');
            style.textContent = `
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
            `;
            
            document.head.appendChild(style);
        }
    }
    
    /**
     * 检查导入状态
     */
    function checkImportStatus() {
        if (!window.importParams) {
            logMessage('导入参数未初始化', 'warning');
            return;
        }
        
        const now = new Date().getTime();
        const runningTime = (now - window.importParams.startTime) / 1000;
        const lastActivity = (now - window.importParams.lastActivityTime) / 1000;
        
        logMessage('===== 导入状态检查 =====', 'info');
        logMessage('- 导入运行中: ' + (window.importParams.importRunning ? '是' : '否'), 'info');
        logMessage('- 当前批次: ' + window.importParams.batchIndex, 'info');
        logMessage('- 已处理行数: ' + window.importParams.processedRows, 'info');
        logMessage('- 总行数: ' + window.importParams.totalRows, 'info');
        logMessage('- 运行时间: ' + runningTime.toFixed(2) + ' 秒', 'info');
        logMessage('- 最后活动: ' + lastActivity.toFixed(2) + ' 秒前', 'info');
        
        // 检查DOM更新状态
        const domUpdateWorking = testDOMUpdates();
        logMessage('- DOM更新状态: ' + (domUpdateWorking ? '正常' : '可能已阻塞'), 'info');
        
        // 尝试恢复UI状态
        if (window.importParams.importRunning && lastActivity > 30) {
            logMessage('检测到导入过程可能已停止响应，尝试恢复...', 'warning');
            
            // 尝试更新UI状态
            forceUIUpdate();
            
            // 如果DOM更新不正常，建议重置
            if (!domUpdateWorking) {
                logMessage('DOM更新可能已阻塞，建议重置导入过程', 'warning');
                
                // 显示重置按钮
                if ($('#reset-import').length === 0) {
                    $('#import-status').after('<button id="reset-import" class="button button-primary" style="margin-left:10px;">重置导入</button>');
                    $('#reset-import').on('click', function() {
                        resetImport();
                    });
                }
            }
        }
    }
    
    /**
     * 测试DOM更新是否正常工作
     */
    function testDOMUpdates() {
        const $testElement = $('#dom-update-check');
        if (!$testElement.length) return true;
        
        // 修改测试元素并检查是否应用
        const testValue = 'test-' + new Date().getTime();
        $testElement.attr('data-test', testValue);
        
        // 强制重绘
        $testElement[0].offsetHeight;
        
        // 检查是否应用
        return $testElement.attr('data-test') === testValue;
    }
    
    /**
     * 重置导入过程
     */
    function resetImport() {
        if (window.importParams) {
            // 清理心跳
            if (window.importParams.heartbeatId) {
                clearInterval(window.importParams.heartbeatId);
            }
            
            // 重置导入状态
            window.importParams.importRunning = false;
        }
        
        // 重置UI状态
        $('#import-status').removeClass('warning warning-blink error');
        $('#import-progress-bar').css('width', '0%');
        $('#start-import').prop('disabled', false).removeClass('disabled').text('开始导入');
        $('#cancel-import').hide();
        $('body').removeAttr('data-import-running');
        $('#reset-import').remove();
        
        // 记录重置
        logMessage('导入过程已重置', 'warning');
        updateImportStatus('导入已重置，可重新开始');
        
        // 移除导航警告
        $(window).off('beforeunload.import');
    }
    
    /**
     * 更新导入状态文本
     */
    function updateImportStatus(message, type = 'info') {
        // 更新状态文本
        $('#import-status').text(message);
        
        // 移除所有状态类
        $('#import-status').removeClass('error warning warning-blink');
        
        // 添加状态类
        if (type === 'error') {
            $('#import-status').addClass('error');
        } else if (type === 'warning') {
            $('#import-status').addClass('warning');
        }
        
        // 更新最后活动时间
        if (window.importParams) {
            window.importParams.lastActivityTime = new Date().getTime();
        }
        
        // 记录状态变化
        if (window.etwp_import && window.etwp_import.debug_mode) {
            console.log('状态更新:', type, message);
        }
    }
    
    /**
     * 完成导入过程
     */
    function completeImport(responseData) {
        try {
            // 计算总耗时
            const duration = (new Date().getTime() - window.importStartTime) / 1000;
            
            // 计算进度
            const progress = window.importParams.totalRows > 0 
                ? Math.min(100, Math.round((window.importParams.processedRows / window.importParams.totalRows) * 100))
                : 100;
            
            // 更新UI和日志
            logMessage('导入完成！总共处理了 ' + window.importParams.processedRows + ' 行数据，耗时 ' + duration.toFixed(2) + ' 秒', 'success');
            updateImportStatus('导入完成！', 'success');
            $('#import-progress-bar').css('width', progress + '%');
            
            // 重置导入状态
            window.importParams.importRunning = false;
            $('#cancel-import').hide();
            $('#start-import').prop('disabled', false).removeClass('disabled').text('重新导入');
            
            // 移除导入运行标记
            $('body').removeAttr('data-import-running');
            
            // 移除导航警告
            $(window).off('beforeunload.import');
            
            // 清理心跳检测
            if (window.importParams.heartbeatId) {
                clearInterval(window.importParams.heartbeatId);
                window.importParams.heartbeatId = null;
            }
            
            // 显示完成消息
            showCompletionMessage(window.importParams.processedRows, duration);
            
            // 记录成功信息
            if (responseData.results) {
                logMessage(`批次 ${window.importParams.batchIndex + 1} 处理完成：成功导入 ${responseData.results.success} 条数据，失败 ${responseData.results.failed} 条，总进度 ${progress}%`, 'success');
                
                // 如果有错误详情，记录它们
                if (responseData.results.errors && responseData.results.errors.length > 0) {
                    responseData.results.errors.forEach(function(error, index) {
                        if (index < 5) { // 只显示前5个错误，避免日志过长
                            logMessage(`错误 ${index + 1}: ${error}`, 'error-detail');
                        } else if (index === 5) {
                            logMessage(`... 还有 ${responseData.results.errors.length - 5} 个错误未显示`, 'error-detail');
                        }
                    });
                }
                } else {
                logMessage(`批次 ${window.importParams.batchIndex + 1} 处理完成，总进度 ${progress}%`, 'success');
            }
            
            // 调用完成函数
            if (typeof finishImport === 'function') {
                finishImport(responseData);
            }
        } catch (error) {
            console.error('完成导入处理出错:', error);
            logMessage('完成导入处理出错: ' + error.message, 'error');
        }
    }
    
    /**
     * 取消导入
     */
    function cancelImport() {
        if (window.importParams && window.importParams.importRunning) {
            // 设置导入状态为停止
            window.importParams.importRunning = false;
            
            // 清理心跳检测
            if (window.importParams.heartbeatId) {
                clearInterval(window.importParams.heartbeatId);
                window.importParams.heartbeatId = null;
            }
            
            // 更新UI状态
            $('#import-status').text('导入已取消');
            $('#import-status').removeClass('warning warning-blink').addClass('warning');
            logMessage('导入已被用户取消', 'warning');
            $('#cancel-import').hide();
            $('#start-import').prop('disabled', false).removeClass('disabled').text('重新导入');
            
            // 移除导入运行标记
            $('body').removeAttr('data-import-running');
            
            // 移除导航警告
            $(window).off('beforeunload.import');
            
            // 记录取消时间
            const duration = (new Date().getTime() - window.importStartTime) / 1000;
            logMessage('导入已取消，持续时间: ' + duration.toFixed(2) + ' 秒', 'warning');
            
            // 添加重启选项
            if ($('#restart-import').length === 0) {
                $('#import-status').after('<button id="restart-import" class="button button-primary" style="margin-left:10px;">重新开始导入</button>');
                $('#restart-import').on('click', function() {
                    $(this).remove();
                    startImport();
                });
            }
        }
    }
    
    /**
     * 显示导入完成消息
     */
    function showCompletionMessage(importedCount, duration) {
        // 清理进度条显示
        $('#import-progress-container').removeClass('in-progress');
        $('#import-status').text('导入完成').removeClass('warning').addClass('success');
        
        // 创建完成消息
        const durationText = formatDuration(duration);
        const importRate = Math.round(importedCount / (duration / 60));
        
        // 构建消息HTML
        const completionHTML = `
            <div class="completion-message">
                <div class="completion-icon">✓</div>
                <div class="completion-details">
                    <h3>导入已完成</h3>
                    <ul>
                        <li>导入记录数: <strong>${importedCount}</strong></li>
                        <li>总耗时: <strong>${durationText}</strong></li>
                        <li>平均导入速度: <strong>${importRate}</strong> 条/分钟</li>
                    </ul>
                    <div class="completion-actions">
                        <button id="view-posts" class="button button-primary">查看导入的文章</button>
                        <button id="start-new-import" class="button">开始新导入</button>
                    </div>
                </div>
            </div>
        `;
        
        // 添加完成消息到页面
        if ($('.completion-message').length === 0) {
            $('#import-progress-container').prepend(completionHTML);
            
            // 绑定按钮事件
            $('#view-posts').on('click', function() {
                // 获取导入的文章类型和状态
                const postType = window.importParams ? window.importParams.postType : 'post';
                const postStatus = window.importParams ? window.importParams.postStatus : 'draft';
                
                // 构建正确的跳转URL
                const adminUrl = etwp_vars.admin_url || window.location.origin + '/wp-admin/';
                const listUrl = `${adminUrl}edit.php?post_type=${postType}&post_status=${postStatus}`;
                
                // 跳转到文章列表页面
                window.location.href = listUrl;
            });
            
            $('#start-new-import').on('click', function() {
                // 重新加载页面，开始新的导入
                window.location.reload();
            });
        }
        
        // 记录日志
        logMessage(`导入完成！共导入 ${importedCount} 条记录，耗时 ${durationText}`, 'success');
    }

    /**
     * 格式化持续时间
     */
    function formatDuration(durationInSeconds) {
        if (durationInSeconds < 60) {
            return `${Math.round(durationInSeconds)} 秒`;
        } else if (durationInSeconds < 3600) {
            const minutes = Math.floor(durationInSeconds / 60);
            const seconds = Math.round(durationInSeconds % 60);
            return `${minutes} 分 ${seconds} 秒`;
        } else {
            const hours = Math.floor(durationInSeconds / 3600);
            const minutes = Math.floor((durationInSeconds % 3600) / 60);
            const seconds = Math.round(durationInSeconds % 60);
            return `${hours} 小时 ${minutes} 分 ${seconds} 秒`;
        }
    }

    /**
     * 开始导入
     */
    function startImport() {
        console.log('开始导入函数被调用...');
        
        // 初始化导入UI
        initImportUI();
        
        // 清空日志并显示
        $('#import-log').empty();
        $('#import-progress-container').show();
        $('#import-log-container').css('display', 'block');
        $('#import-log').css('display', 'block');
        
        // 记录开始日志
        logMessage('开始导入过程', 'info');
        
        // 获取字段映射数据
        let fieldMapping = [];
        
        // 检查当前使用哪种映射方式
        if ($('.etwp-select-mapping').is(':visible')) {
            // 从选择框获取映射数据
            $('.excel-to-wp-mapping').each(function() {
                const excelField = $(this).attr('data-excel-field');
                const wpField = $(this).val();
                
                if (wpField) {
                    fieldMapping.push({
                        excelField: excelField,
                        wpField: wpField
                    });
                }
            });
        } else {
            // 从标准映射表格中获取数据
            $('.etwp-mapping-table tbody tr').each(function() {
                const excelField = $(this).find('td:first').text();
                const wpField = $(this).find('select').val();
                
                if (wpField) {
                    fieldMapping.push({
                        excelField: excelField,
                        wpField: wpField
                    });
                }
            });
        }
        
        // 验证字段映射
        if (!fieldMapping || Object.keys(fieldMapping).length === 0) {
            // 显示错误提示
            $('#mapping-error').remove();
            $('#start-import').after('<div id="mapping-error" class="notice notice-error" style="margin-top:10px;"><p><strong>错误：</strong>请至少设置一个字段映射才能开始导入。</p><p>请在上方的"字段映射"部分选择至少一个Excel列对应的WordPress字段。</p></div>');
            
            // 平滑滚动到字段映射区域
            $('html, body').animate({
                scrollTop: $('#field-mapping-container').offset().top - 50
            }, 500);
            
            // 添加高亮效果
            $('#field-mapping-container').addClass('field-mapping-highlight');
            setTimeout(function() {
                $('#field-mapping-container').removeClass('field-mapping-highlight');
            }, 2000);
            
            return false;
        }
        
        logMessage('字段映射有效，共 ' + fieldMapping.length + ' 个映射', 'info');
        
        // 重置导入UI
        resetImport();
        
        // 设置导入全局变量
        window.importParams = {
            importRunning: true,
            batchIndex: 0,
            processedRows: 0,
            totalRows: 0,
            startTime: new Date().getTime(),
            lastActivityTime: new Date().getTime(),
            postType: $('#post_type').val() || 'post',
            postStatus: $('#post_status').val() || 'draft',
            categoryId: $('#category_id').val() || 0,
            fieldMapping: fieldMapping
        };
        
        window.importStartTime = new Date().getTime();
        
        // 标记导入正在运行
        $('body').attr('data-import-running', 'true');
        
        // 添加页面离开警告
        $(window).on('beforeunload.import', function() {
            if (window.importParams && window.importParams.importRunning) {
                return '导入过程正在进行中，确定要离开页面吗？';
            }
        });
        
        // 显示取消按钮
        $('#cancel-import').show();
        
        // 禁用开始按钮
        $('#start-import').prop('disabled', true).addClass('disabled').text('导入中...');
        
        try {
            // 开始处理第一批数据
            processBatchSimple();
        } catch (error) {
            console.error('启动导入过程出错:', error);
            logMessage('启动导入过程出错: ' + error.message, 'error');
            
            // 重置导入状态
            window.importParams.importRunning = false;
            $('#cancel-import').hide();
            $('#start-import').prop('disabled', false).removeClass('disabled').text('重试导入');
        }
    }
    
    /**
     * 简化版的批处理函数 - 更稳定的实现
     */
    function processBatchSimple() {
        if (!window.importParams || !window.importParams.importRunning) {
            console.error('导入参数不存在或导入已取消');
            return;
        }
        
        // 更新最后活动时间
        window.importParams.lastActivityTime = new Date().getTime();
        
        console.log('处理批次 ' + window.importParams.batchIndex);
        
        // 更新状态
        const currentBatch = window.importParams.batchIndex + 1;
        const totalBatches = Math.ceil(window.importParams.totalRows / 10); // 假设每批10条数据
        $('#import-status').text(`正在处理批次 ${currentBatch}/${totalBatches}...`);
        
        // 显示加载指示器
        if ($('#import-loading').length === 0) {
            $('#import-progress-container').append('<div id="import-loading" style="text-align:center;margin-top:10px;"><span class="spinner is-active" style="float:none;margin-right:5px;"></span>正在处理...</div>');
        } else {
            $('#import-loading').show();
        }
        
        // 准备请求数据
        const requestData = {
            action: 'etwp_process_batch',
            nonce: etwp_vars.nonce,
            batch_index: window.importParams.batchIndex,
            post_status: window.importParams.postStatus,
            category_id: window.importParams.categoryId,
            mapping: JSON.stringify(window.importParams.fieldMapping)
        };
        
        // 发送AJAX请求
        $.ajax({
            url: etwp_vars.ajax_url,
            type: 'POST',
            data: requestData,
            dataType: 'json',
            timeout: 60000,
            success: function(response) {
                // 更新最后活动时间
                window.importParams.lastActivityTime = new Date().getTime();
                
                // 隐藏加载指示器
                $('#import-loading').hide();
                
                console.log('服务器响应:', response);
                
                if (response.success) {
                    // 更新进度信息
                    const data = response.data;
                    
                    // 解析服务器返回的进度数据
                    window.importParams.processedRows = parseInt(data.processed_rows || 0);
                    window.importParams.totalRows = parseInt(data.total_rows || 0);
                    
                    // 计算和更新进度
                    let progress = 0;
                    if (window.importParams.totalRows > 0) {
                        progress = Math.min(100, Math.round((window.importParams.processedRows / window.importParams.totalRows) * 100));
                    }
                    
                    // 更新进度条
                    $('#import-progress-bar').css('width', progress + '%');
                    $('#import-status').text(`已处理: ${window.importParams.processedRows} / ${window.importParams.totalRows} (${progress}%)`);
                    
                    // 检查是否继续处理下一批
                    const hasMore = (data.has_more === true || data.more_data === true);
                    
                    if (hasMore && window.importParams.importRunning) {
                        // 继续处理下一批
                        window.importParams.batchIndex++;
                        
                        // 延迟1秒处理下一批，减轻服务器压力
                        setTimeout(processBatchSimple, 1000);
                    } else {
                        // 导入完成
                        completeImport(data);
                    }
                } else {
                    // 处理错误
                    let errorMessage = '未知错误';
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    
                    $('#import-status').text(`导入失败: ${errorMessage}`);
                    
                    // 重置导入状态
                    window.importParams.importRunning = false;
                    $('#cancel-import').hide();
                    $('#start-import').prop('disabled', false).removeClass('disabled').text('重试导入');
                    
                    // 移除导入运行标记
                    $('body').removeAttr('data-import-running');
                }
            },
            error: function(xhr, status, error) {
                // 更新最后活动时间
                if (window.importParams) {
                    window.importParams.lastActivityTime = new Date().getTime();
                }
                
                // 隐藏加载指示器
                $('#import-loading').hide();
                
                console.log('AJAX请求失败:', status, error);
                console.log('响应内容:', xhr.responseText || '无响应内容');
                
                let errorMsg = error || '未知错误';
                
                // 根据不同错误类型提供更明确的错误信息
                if (xhr.status === 0) {
                    $('#import-status').text(`第 ${currentBatch} 批请求错误：连接中断或服务器未响应`);
                } else if (errorThrown === 'timeout') {
                    $('#import-status').text(`第 ${currentBatch} 批请求超时，服务器处理时间过长`);
                } else if (xhr.status === 400 || xhr.status === 403 || xhr.status === 404) {
                    $('#import-status').text(`第 ${currentBatch} 批请求错误 ${xhr.status}：${xhr.responseText || errorThrown}`);
                } else if (xhr.status === 500) {
                    $('#import-status').text(`第 ${currentBatch} 批服务器内部错误：可能PHP执行超时或内存不足`);
                } else {
                    $('#import-status').text(`第 ${currentBatch} 批请求失败：${errorThrown || '未知错误'}`);
                }
                
                // 重置导入状态
                if (window.importParams) {
                    window.importParams.importRunning = false;
                }
                $('#cancel-import').hide();
                $('#start-import').prop('disabled', false).removeClass('disabled').text('重试导入');
                
                // 移除导入运行标记
                $('body').removeAttr('data-import-running');
            }
        });
    }

    /**
     * 强制更新UI状态
     */
    function forceUIUpdate() {
        try {
            // 尝试强制重绘UI元素
            $('#import-progress-bar')[0] && $('#import-progress-bar')[0].offsetHeight;
            $('#import-status')[0] && $('#import-status')[0].offsetHeight;
            
            // 添加临时动画类并移除
            $('#import-progress-container').addClass('heartbeat-pulse');
        setTimeout(function() {
                $('#import-progress-container').removeClass('heartbeat-pulse');
            }, 300);
            
            // 设置最后活动时间
            if (window.importParams) {
                window.importParams.lastActivityTime = new Date().getTime();
            }
            
            // 记录UI刷新
            console.log('UI强制刷新完成，时间:', new Date().toLocaleTimeString());
        } catch (e) {
            console.error('UI强制刷新出错:', e);
        }
    }
    
    /**
     * 清除日志
     */
    function clearLog() {
        $('#import-log').empty();
        logMessage('日志已清除', 'info');
    }

    // 绑定清除日志按钮事件
    $(document).on('click', '#clear-log', function() {
        clearLog();
    });

})(jQuery);/* DEBUG MODE ENABLED */
