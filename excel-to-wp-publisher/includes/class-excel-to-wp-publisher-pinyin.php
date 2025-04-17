<?php
/**
 * 中文转拼音工具类
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 */

// 如果直接访问此文件，则中止执行
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 中文转拼音工具类
 *
 * 这个类负责将中文字符转换为拼音
 *
 * @since      1.0.0
 * @package    Excel_To_WP_Publisher
 * @author     WordPress Developer
 */
class Excel_To_WP_Publisher_Pinyin {

    /**
     * 中文字符到拼音的映射表
     *
     * @var array
     */
    private $dict = array();

    /**
     * 初始化类
     *
     * @since    1.0.0
     */
    public function __construct() {
        // 初始化常用汉字拼音字典
        $this->init_dict();
    }

    /**
     * 初始化拼音字典
     *
     * @since    1.0.0
     */
    private function init_dict() {
        // 常用汉字拼音映射（简化版）
        $this->dict = array(
            '啊' => 'a', '爱' => 'ai', '安' => 'an', '按' => 'an', '暗' => 'an',
            '八' => 'ba', '把' => 'ba', '爸' => 'ba', '白' => 'bai', '百' => 'bai',
            '班' => 'ban', '半' => 'ban', '办' => 'ban', '帮' => 'bang', '包' => 'bao',
            '保' => 'bao', '报' => 'bao', '杯' => 'bei', '北' => 'bei', '被' => 'bei',
            '本' => 'ben', '比' => 'bi', '笔' => 'bi', '边' => 'bian', '变' => 'bian',
            '标' => 'biao', '表' => 'biao', '别' => 'bie', '病' => 'bing', '波' => 'bo',
            '不' => 'bu', '部' => 'bu', '才' => 'cai', '采' => 'cai', '彩' => 'cai',
            '菜' => 'cai', '参' => 'can', '草' => 'cao', '层' => 'ceng', '茶' => 'cha',
            '差' => 'cha', '长' => 'chang', '常' => 'chang', '场' => 'chang', '唱' => 'chang',
            '超' => 'chao', '车' => 'che', '成' => 'cheng', '城' => 'cheng', '吃' => 'chi',
            '出' => 'chu', '处' => 'chu', '川' => 'chuan', '穿' => 'chuan', '传' => 'chuan',
            '船' => 'chuan', '窗' => 'chuang', '春' => 'chun', '次' => 'ci', '从' => 'cong',
            '村' => 'cun', '存' => 'cun', '错' => 'cuo', '大' => 'da', '打' => 'da',
            '带' => 'dai', '待' => 'dai', '单' => 'dan', '但' => 'dan', '当' => 'dang',
            '到' => 'dao', '道' => 'dao', '的' => 'de', '得' => 'de', '等' => 'deng',
            '低' => 'di', '地' => 'di', '第' => 'di', '点' => 'dian', '电' => 'dian',
            '调' => 'diao', '掉' => 'diao', '东' => 'dong', '动' => 'dong', '都' => 'dou',
            '读' => 'du', '度' => 'du', '短' => 'duan', '对' => 'dui', '多' => 'duo',
            '饿' => 'e', '儿' => 'er', '而' => 'er', '二' => 'er', '发' => 'fa',
            '法' => 'fa', '反' => 'fan', '饭' => 'fan', '方' => 'fang', '房' => 'fang',
            '放' => 'fang', '非' => 'fei', '飞' => 'fei', '分' => 'fen', '风' => 'feng',
            '封' => 'feng', '佛' => 'fo', '否' => 'fou', '夫' => 'fu', '服' => 'fu',
            '父' => 'fu', '付' => 'fu', '负' => 'fu', '妇' => 'fu', '复' => 'fu',
            '该' => 'gai', '改' => 'gai', '概' => 'gai', '干' => 'gan', '刚' => 'gang',
            '高' => 'gao', '告' => 'gao', '哥' => 'ge', '歌' => 'ge', '格' => 'ge',
            '个' => 'ge', '给' => 'gei', '跟' => 'gen', '更' => 'geng', '工' => 'gong',
            '公' => 'gong', '共' => 'gong', '狗' => 'gou', '够' => 'gou', '古' => 'gu',
            '故' => 'gu', '顾' => 'gu', '固' => 'gu', '瓜' => 'gua', '刮' => 'gua',
            '挂' => 'gua', '怪' => 'guai', '关' => 'guan', '观' => 'guan', '管' => 'guan',
            '馆' => 'guan', '光' => 'guang', '广' => 'guang', '规' => 'gui', '鬼' => 'gui',
            '贵' => 'gui', '国' => 'guo', '果' => 'guo', '过' => 'guo', '还' => 'hai',
            '孩' => 'hai', '海' => 'hai', '害' => 'hai', '含' => 'han', '汉' => 'han',
            '好' => 'hao', '号' => 'hao', '喝' => 'he', '和' => 'he', '河' => 'he',
            '黑' => 'hei', '很' => 'hen', '红' => 'hong', '后' => 'hou', '候' => 'hou',
            '忽' => 'hu', '湖' => 'hu', '虎' => 'hu', '互' => 'hu', '护' => 'hu',
            '花' => 'hua', '华' => 'hua', '化' => 'hua', '话' => 'hua', '怀' => 'huai',
            '坏' => 'huai', '欢' => 'huan', '还' => 'huan', '换' => 'huan', '黄' => 'huang',
            '回' => 'hui', '会' => 'hui', '婚' => 'hun', '活' => 'huo', '火' => 'huo',
            '或' => 'huo', '机' => 'ji', '鸡' => 'ji', '积' => 'ji', '极' => 'ji',
            '急' => 'ji', '几' => 'ji', '己' => 'ji', '记' => 'ji', '计' => 'ji',
            '家' => 'jia', '加' => 'jia', '假' => 'jia', '间' => 'jian', '简' => 'jian',
            '见' => 'jian', '建' => 'jian', '将' => 'jiang', '江' => 'jiang', '教' => 'jiao',
            '交' => 'jiao', '角' => 'jiao', '脚' => 'jiao', '叫' => 'jiao', '接' => 'jie',
            '街' => 'jie', '节' => 'jie', '结' => 'jie', '解' => 'jie', '姐' => 'jie',
            '介' => 'jie', '今' => 'jin', '金' => 'jin', '近' => 'jin', '进' => 'jin',
            '经' => 'jing', '京' => 'jing', '精' => 'jing', '景' => 'jing', '静' => 'jing',
            '九' => 'jiu', '久' => 'jiu', '酒' => 'jiu', '旧' => 'jiu', '就' => 'jiu',
            '举' => 'ju', '具' => 'ju', '句' => 'ju', '据' => 'ju', '决' => 'jue',
            '觉' => 'jue', '开' => 'kai', '看' => 'kan', '康' => 'kang', '考' => 'kao',
            '靠' => 'kao', '科' => 'ke', '可' => 'ke', '课' => 'ke', '刻' => 'ke',
            '客' => 'ke', '空' => 'kong', '口' => 'kou', '哭' => 'ku', '苦' => 'ku',
            '块' => 'kuai', '快' => 'kuai', '宽' => 'kuan', '况' => 'kuang', '亏' => 'kui',
            '困' => 'kun', '扩' => 'kuo', '拉' => 'la', '来' => 'lai', '蓝' => 'lan',
            '浪' => 'lang', '老' => 'lao', '乐' => 'le', '类' => 'lei', '冷' => 'leng',
            '离' => 'li', '李' => 'li', '里' => 'li', '理' => 'li', '力' => 'li',
            '立' => 'li', '丽' => 'li', '利' => 'li', '历' => 'li', '连' => 'lian',
            '脸' => 'lian', '练' => 'lian', '恋' => 'lian', '两' => 'liang', '亮' => 'liang',
            '谅' => 'liang', '量' => 'liang', '料' => 'liao', '林' => 'lin', '临' => 'lin',
            '灵' => 'ling', '领' => 'ling', '另' => 'ling', '留' => 'liu', '流' => 'liu',
            '六' => 'liu', '龙' => 'long', '楼' => 'lou', '路' => 'lu', '录' => 'lu',
            '旅' => 'lv', '绿' => 'lv', '乱' => 'luan', '论' => 'lun', '落' => 'luo',
            '妈' => 'ma', '马' => 'ma', '买' => 'mai', '卖' => 'mai', '满' => 'man',
            '慢' => 'man', '忙' => 'mang', '猫' => 'mao', '毛' => 'mao', '么' => 'me',
            '没' => 'mei', '美' => 'mei', '每' => 'mei', '门' => 'men', '们' => 'men',
            '梦' => 'meng', '米' => 'mi', '面' => 'mian', '秒' => 'miao', '民' => 'min',
            '明' => 'ming', '名' => 'ming', '命' => 'ming', '摸' => 'mo', '末' => 'mo',
            '母' => 'mu', '木' => 'mu', '目' => 'mu', '那' => 'na', '哪' => 'na',
            '内' => 'nei', '能' => 'neng', '你' => 'ni', '年' => 'nian', '念' => 'nian',
            '鸟' => 'niao', '您' => 'nin', '牛' => 'niu', '农' => 'nong', '女' => 'nv',
            '暖' => 'nuan', '欧' => 'ou', '怕' => 'pa', '拍' => 'pai', '排' => 'pai',
            '盘' => 'pan', '旁' => 'pang', '胖' => 'pang', '跑' => 'pao', '朋' => 'peng',
            '皮' => 'pi', '片' => 'pian', '票' => 'piao', '漂' => 'piao', '品' => 'pin',
            '平' => 'ping', '破' => 'po', '普' => 'pu', '七' => 'qi', '期' => 'qi',
            '其' => 'qi', '奇' => 'qi', '起' => 'qi', '气' => 'qi', '千' => 'qian',
            '前' => 'qian', '钱' => 'qian', '强' => 'qiang', '墙' => 'qiang', '桥' => 'qiao',
            '切' => 'qie', '且' => 'qie', '亲' => 'qin', '轻' => 'qing', '清' => 'qing',
            '情' => 'qing', '请' => 'qing', '秋' => 'qiu', '球' => 'qiu', '区' => 'qu',
            '取' => 'qu', '去' => 'qu', '趣' => 'qu', '全' => 'quan', '权' => 'quan',
            '群' => 'qun', '然' => 'ran', '让' => 'rang', '热' => 're', '人' => 'ren',
            '认' => 'ren', '日' => 'ri', '容' => 'rong', '如' => 'ru', '入' => 'ru',
            '软' => 'ruan', '三' => 'san', '色' => 'se', '森' => 'sen', '杀' => 'sha',
            '山' => 'shan', '上' => 'shang', '少' => 'shao', '社' => 'she', '身' => 'shen',
            '什' => 'shen', '深' => 'shen', '生' => 'sheng', '声' => 'sheng', '胜' => 'sheng',
            '师' => 'shi', '十' => 'shi', '时' => 'shi', '识' => 'shi', '实' => 'shi',
            '始' => 'shi', '世' => 'shi', '事' => 'shi', '是' => 'shi', '收' => 'shou',
            '手' => 'shou', '受' => 'shou', '书' => 'shu', '树' => 'shu', '数' => 'shu',
            '水' => 'shui', '睡' => 'shui', '说' => 'shuo', '思' => 'si', '死' => 'si',
            '四' => 'si', '送' => 'song', '诉' => 'su', '素' => 'su', '虽' => 'sui',
            '岁' => 'sui', '孙' => 'sun', '所' => 'suo', '他' => 'ta', '她' => 'ta',
            '台' => 'tai', '太' => 'tai', '谈' => 'tan', '汤' => 'tang', '堂' => 'tang',
            '套' => 'tao', '特' => 'te', '提' => 'ti', '题' => 'ti', '体' => 'ti',
            '天' => 'tian', '听' => 'ting', '通' => 'tong', '同' => 'tong', '头' => 'tou',
            '图' => 'tu', '土' => 'tu', '外' => 'wai', '完' => 'wan', '玩' => 'wan',
            '晚' => 'wan', '万' => 'wan', '王' => 'wang', '网' => 'wang', '往' => 'wang',
            '望' => 'wang', '为' => 'wei', '位' => 'wei', '文' => 'wen', '问' => 'wen',
            '我' => 'wo', '无' => 'wu', '五' => 'wu', '午' => 'wu', '物' => 'wu',
            '西' => 'xi', '吸' => 'xi', '希' => 'xi', '息' => 'xi', '系' => 'xi',
            '下' => 'xia', '夏' => 'xia', '先' => 'xian', '现' => 'xian', '线' => 'xian',
            '想' => 'xiang', '相' => 'xiang', '香' => 'xiang', '向' => 'xiang', '像' => 'xiang',
            '小' => 'xiao', '笑' => 'xiao', '校' => 'xiao', '些' => 'xie', '写' => 'xie',
            '谢' => 'xie', '心' => 'xin', '新' => 'xin', '信' => 'xin', '星' => 'xing',
            '行' => 'xing', '形' => 'xing', '醒' => 'xing', '性' => 'xing', '兄' => 'xiong',
            '休' => 'xiu', '修' => 'xiu', '需' => 'xu', '许' => 'xu', '续' => 'xu',
            '选' => 'xuan', '学' => 'xue', '雪' => 'xue', '寻' => 'xun', '亚' => 'ya',
            '严' => 'yan', '言' => 'yan', '眼' => 'yan', '演' => 'yan', '验' => 'yan',
            '阳' => 'yang', '样' => 'yang', '要' => 'yao', '药' => 'yao', '也' => 'ye',
            '业' => 'ye', '夜' => 'ye', '一' => 'yi', '以' => 'yi', '亿' => 'yi',
            '意' => 'yi', '因' => 'yin', '音' => 'yin', '印' => 'yin', '应' => 'ying',
            '英' => 'ying', '影' => 'ying', '用' => 'yong', '优' => 'you', '有' => 'you',
            '又' => 'you', '友' => 'you', '右' => 'you', '于' => 'yu', '与' => 'yu',
            '语' => 'yu', '育' => 'yu', '遇' => 'yu', '元' => 'yuan', '园' => 'yuan',
            '原' => 'yuan', '远' => 'yuan', '院' => 'yuan', '愿' => 'yuan', '月' => 'yue',
            '越' => 'yue', '云' => 'yun', '运' => 'yun', '在' => 'zai', '再' => 'zai',
            '早' => 'zao', '怎' => 'zen', '增' => 'zeng', '展' => 'zhan', '站' => 'zhan',
            '张' => 'zhang', '找' => 'zhao', '照' => 'zhao', '者' => 'zhe', '这' => 'zhe',
            '真' => 'zhen', '整' => 'zheng', '正' => 'zheng', '政' => 'zheng', '之' => 'zhi',
            '知' => 'zhi', '只' => 'zhi', '直' => 'zhi', '值' => 'zhi', '职' => 'zhi',
            '指' => 'zhi', '纸' => 'zhi', '至' => 'zhi', '制' => 'zhi', '中' => 'zhong',
            '种' => 'zhong', '重' => 'zhong', '周' => 'zhou', '州' => 'zhou', '主' => 'zhu',
            '住' => 'zhu', '助' => 'zhu', '注' => 'zhu', '专' => 'zhuan', '转' => 'zhuan',
            '装' => 'zhuang', '准' => 'zhun', '资' => 'zi', '字' => 'zi', '自' => 'zi',
            '总' => 'zong', '走' => 'zou', '组' => 'zu', '最' => 'zui', '作' => 'zuo',
            '做' => 'zuo'
        );
    }

    /**
     * 将中文字符串转换为拼音
     *
     * @since    1.0.0
     * @param    string    $text       要转换的中文字符串
     * @param    string    $separator  拼音之间的分隔符
     * @return   string                转换后的拼音字符串
     */
    public function convert($text, $separator = '') {
        if (empty($text)) {
            return '';
        }
        
        $result = '';
        $len = mb_strlen($text, 'UTF-8');
        
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            
            // 如果是ASCII字符（英文、数字等），直接保留
            if (ord($char) < 128) {
                $result .= $char;
            } 
            // 如果是中文字符，查找拼音
            else {
                if (isset($this->dict[$char])) {
                    $result .= $separator . $this->dict[$char];
                } else {
                    // 如果字典中没有，保留原字符
                    $result .= $separator . 'x';
                }
            }
        }
        
        return $result;
    }

    /**
     * 将中文字符串转换为用于文件名的拼音
     * 
     * 只保留字母、数字和连字符
     *
     * @since    1.0.0
     * @param    string    $text       要转换的中文字符串
     * @return   string                转换后的拼音文件名
     */
    public function convert_to_filename($text) {
        // 先转换为拼音
        $pinyin = $this->convert($text, '-');
        
        // 转为小写
        $pinyin = strtolower($pinyin);
        
        // 只保留字母、数字和连字符，其他字符替换为连字符
        $pinyin = preg_replace('/[^a-z0-9\-]/', '-', $pinyin);
        
        // 替换多个连续的连字符为单个连字符
        $pinyin = preg_replace('/-+/', '-', $pinyin);
        
        // 移除开头和结尾的连字符
        $pinyin = trim($pinyin, '-');
        
        // 如果为空，返回默认名称
        if (empty($pinyin)) {
            return 'image';
        }
        
        return $pinyin;
    }
}