# Excel to WordPress Publisher

这是一个WordPress插件，用于批量从Excel导入文章内容到WordPress，支持图片上传和字段映射。

## 功能特点

- 支持Excel (.xlsx, .xls) 和 CSV 文件导入
- 灵活的字段映射，可将Excel列映射到WordPress字段
- 支持自定义字段和ACF字段
- 支持从本地路径上传图片到WordPress媒体库
- 自动适配MacOS和Windows系统图片路径，智能识别操作系统
- 可选择文章状态（草稿、发布、待审）和分类
- 可保存字段映射设置，方便下次使用
- 批量导入功能，提高内容发布效率
- 支持中文拼音转换，优化文章别名和URL
- 导入过程可视化，实时显示进度和结果
- 错误处理机制，提供详细的导入日志
- 支持导入后自动设置特色图片

## 安装说明

### 前置要求

- WordPress 5.0 或更高版本
- PHP 7.2 或更高版本
- PHP扩展：zip, xml, gd

### 安装方法

1. 下载完整的插件包
2. 将整个插件目录上传到WordPress的`/wp-content/plugins/`目录
3. 在WordPress管理后台激活插件

> **注意**：本插件包已包含完整的vendor目录，包括所有必要的依赖库（如PhpSpreadsheet）。您可以直接上传使用，无需手动运行Composer。

## 使用方法

1. 在WordPress管理后台，点击左侧菜单中的「Excel发布器」
2. 上传Excel文件（支持.xlsx, .xls, .csv格式）
3. 设置字段映射，将Excel列映射到WordPress字段
4. 选择文章分类和状态
5. 点击「开始导入」按钮开始导入过程
6. 导入完成后，查看导入结果

## 图片导入说明

要导入图片，Excel中的单元格需要包含图片的完整本地路径。插件会自动将这些图片上传到WordPress媒体库，并关联到相应的文章。插件支持自动识别操作系统（MacOS或Windows）并正确处理不同格式的图片路径，无需用户手动调整路径格式。

## 字段映射

- 可以使用「自动映射相似字段」功能快速匹配相似名称的字段
- 可以保存映射设置，方便下次使用
- 支持搜索功能，方便在大量字段中查找

## 注意事项

- 导入大量数据时，可能需要增加PHP的执行时间和内存限制
- 确保Excel文件中的图片路径正确且可访问
- 建议先导入少量数据测试，确认映射正确后再导入全部数据
- 建议在本地搭建环境运行导入，远程网络环境可能会导致上传超时等问题
- 笔者使用的是免费的[后羿采集器](https://www.houyicaiji.com/)采集的数据，当然也可以使用其他的采集工具导出采集数据为 Excel文件。
- 本插件是由 AI 编写，笔者没有 Windows 环境，所以只在 MacOS上做了测试，若 Windows 上运行有问题，请自行下载源码后让 AI 去修改：）
- 使用说明可以[查看博客](https://www.74110.net/notes/excel-to-wp-publisher/)
