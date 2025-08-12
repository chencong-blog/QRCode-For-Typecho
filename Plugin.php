<?php
/**
 * Typecho 自动生成当前页面二维码插件
 * 
 * @package QRCode
 * @author 蔥籽
 * @version 1.0.6
 * @link https://chencong.blog
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class QRCode_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件
     * 
     * @return string
     */
    public static function activate()
    {
        // 注册到footer钩子
        Typecho_Plugin::factory('Widget_Archive')->footer = array('QRCode_Plugin', 'displayQRCode');
        return _t('二维码插件已激活，请在设置中配置显示选项');
    }
    
    /**
     * 禁用插件
     * 
     * @return void
     */
    public static function deactivate() {}
    
    /**
     * 插件配置面板
     * 
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 显示位置设置
        $posOptions = array(
            'left' => _t('左侧悬浮'),
            'right' => _t('右侧悬浮'),
            'bottom' => _t('底部居中')
        );
        $position = new Typecho_Widget_Helper_Form_Element_Radio(
            'position',
            $posOptions,
            'right',
            _t('显示位置'),
            _t('选择二维码在页面中的显示位置')
        );
        $form->addInput($position);
        
        // 二维码大小设置 - 增加范围限制
        $size = new Typecho_Widget_Helper_Form_Element_Text(
            'size',
            null,
            '150',
            _t('二维码尺寸'),
            _t('设置二维码的宽度和高度，单位为像素，范围建议50-300')
        );
        $size->addRule('isInteger', _t('请输入有效的数字'));
        $size->addRule(function($size) {
            $value = intval($size);
            return $value >= 50 && $value <= 300;
        }, _t('请输入50-300之间的数字'));
        $form->addInput($size);
        
        // 二维码前景色设置
        $colorDark = new Typecho_Widget_Helper_Form_Element_Text(
            'colorDark',
            null,
            '#000000',
            _t('二维码前景色'),
            _t('设置二维码的深色部分颜色，使用十六进制颜色值，如#000000表示黑色')
        );
        $colorDark->addRule(function($color) {
            return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color);
        }, _t('请输入有效的十六进制颜色值，如#000000'));
        $form->addInput($colorDark);
        
        // 二维码背景色设置
        $colorLight = new Typecho_Widget_Helper_Form_Element_Text(
            'colorLight',
            null,
            '#ffffff',
            _t('二维码背景色'),
            _t('设置二维码的浅色部分颜色，使用十六进制颜色值，如#ffffff表示白色')
        );
        $colorLight->addRule(function($color) {
            return preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color);
        }, _t('请输入有效的十六进制颜色值，如#ffffff'));
        $form->addInput($colorLight);
        
        // 错误纠正码率设置
        $errorLevelOptions = array(
            'L' => _t('低 (7% 容错率) - 二维码较小，容错能力低'),
            'M' => _t('中 (15% 容错率) - 平衡大小和容错能力'),
            'Q' => _t('较高 (25% 容错率) - 二维码稍大，容错能力较高'),
            'H' => _t('高 (30% 容错率) - 二维码较大，容错能力最高')
        );
        $errorLevel = new Typecho_Widget_Helper_Form_Element_Radio(
            'errorLevel',
            $errorLevelOptions,
            'H',
            _t('错误纠正码率'),
            _t('设置二维码的错误纠正能力')
        );
        $form->addInput($errorLevel);
        
        // 显示页面设置
        $showOn = new Typecho_Widget_Helper_Form_Element_Checkbox(
            'showOn',
            array(
                'index' => _t('首页'),
                'post' => _t('文章页'),
                'page' => _t('独立页面'),
                'category' => _t('分类页'),
                'tag' => _t('标签页')
            ),
            array('index', 'post', 'page'),
            _t('显示页面'),
            _t('选择在哪些页面显示二维码')
        );
        $form->addInput($showOn);
        
        // 设备显示设置
        $showDevices = new Typecho_Widget_Helper_Form_Element_Checkbox(
            'showDevices',
            array(
                'desktop' => _t('桌面设备'),
                'tablet' => _t('平板设备'),
                'mobile' => _t('移动设备')
            ),
            array('desktop', 'tablet', 'mobile'),
            _t('显示设备'),
            _t('选择在哪些类型的设备上显示二维码')
        );
        $form->addInput($showDevices);
    }
    
    /**
     * 个人用户配置
     * 
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}
    
    /**
     * 显示二维码（核心优化部分）
     * 
     * @return void
     */
    public static function displayQRCode($footer)
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('QRCode');
        
        try {
            // 尝试获取Archive实例，捕获可能的初始化异常
            $archive = Typecho_Widget::widget('Widget_Archive');
        } catch (Typecho\Widget\Exception $e) {
            // 若Archive初始化失败（如404页面），直接返回不显示二维码
            return $footer;
        }
        
        // 判断当前页面是否需要显示二维码
        $show = false;
        $showOn = $options->showOn ?: array();
        
        // 完善页面类型判断逻辑，覆盖所有场景
        if (
            ($archive->is('index') && in_array('index', $showOn)) ||
            ($archive->is('post') && in_array('post', $showOn)) ||
            ($archive->is('page') && in_array('page', $showOn)) ||
            ($archive->is('category') && in_array('category', $showOn)) ||
            ($archive->is('tag') && in_array('tag', $showOn))
        ) {
            $show = true;
        }
        
        // 特殊场景：404页面强制不显示
        if ($archive->is404()) {
            $show = false;
        }
        
        if (!$show) {
            return $footer;
        }
        
        // 获取当前页面URL（兼容不同页面类型）
        $currentUrl = '';
        if ($archive->is('index')) {
            $currentUrl = Typecho_Widget::widget('Widget_Options')->siteUrl;
        } else {
            $currentUrl = $archive->permalink ?? Typecho_Widget::widget('Widget_Options')->siteUrl;
        }
        
        // 配置参数处理
        $size = intval($options->size);
        $size = max(50, min(300, $size)); // 强制范围限制
        $position = $options->position ?: 'right';
        $errorLevel = $options->errorLevel ?: 'H';
        $showDevices = $options->showDevices ?: array('desktop', 'tablet', 'mobile');
        
        // 颜色格式验证
        $colorDark = $options->colorDark ?: '#000000';
        if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $colorDark)) {
            $colorDark = '#000000';
        }
        
        $colorLight = $options->colorLight ?: '#ffffff';
        if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $colorLight)) {
            $colorLight = '#ffffff';
        }
        
        // 错误纠正码率映射
        $errorLevelMap = array('L' => 'L', 'M' => 'M', 'Q' => 'Q', 'H' => 'H');
        $correctLevel = isset($errorLevelMap[$errorLevel]) ? $errorLevelMap[$errorLevel] : 'H';
        
        // 输出样式
        echo '<style type="text/css">';
        echo '.qrcode-wrapper {position: fixed; z-index: 999; padding: 15px; background: #fff; border-radius: 5px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); transition: all 0.3s ease;}';
        echo '.qrcode-wrapper:hover {box-shadow: 0 4px 16px rgba(0,0,0,0.15);}';
        echo '.qrcode-img {width: ' . $size . 'px; height: ' . $size . 'px;}';
        echo '.qrcode-desc {text-align: center; font-weight: bold; font-size: 16px; color: #666; margin-top: 10px;}';
        
        // 位置样式
        switch ($position) {
            case 'left':
                echo '.qrcode-wrapper {left: 20px; top: 50%; transform: translateY(-50%);}';
                break;
            case 'right':
                echo '.qrcode-wrapper {right: 20px; top: 50%; transform: translateY(-50%);}';
                break;
            case 'bottom':
                echo '.qrcode-wrapper {left: 50%; bottom: 20px; transform: translateX(-50%);}';
                break;
        }
        
        // 设备显示控制
        if (!in_array('mobile', $showDevices)) {
            echo '@media (max-width: 767px) { .qrcode-wrapper { display: none !important; } }';
        }
        if (!in_array('tablet', $showDevices)) {
            echo '@media (min-width: 768px) and (max-width: 1024px) { .qrcode-wrapper { display: none !important; } }';
        }
        if (!in_array('desktop', $showDevices)) {
            echo '@media (min-width: 1025px) { .qrcode-wrapper { display: none !important; } }';
        }
        echo '</style>';
        
        // 输出HTML和脚本
        echo '<div class="qrcode-wrapper">';
        echo '<div id="qrcode" class="qrcode-img"></div>';
        echo '<div class="qrcode-desc">扫码访问当前页面</div>';
        echo '</div>';
        
        echo '<script src="/usr/plugins/QRCode/js/qrcode.min.js"></script>';
        echo '<script type="text/javascript">';
        echo 'window.onload = function() {';
        echo '    new QRCode(document.getElementById("qrcode"), {';
        echo '        text: "' . addslashes($currentUrl) . '",';
        echo '        width: ' . $size . ',';
        echo '        height: ' . $size . ',';
        echo '        colorDark: "' . addslashes($colorDark) . '",';
        echo '        colorLight: "' . addslashes($colorLight) . '",';
        echo '        correctLevel: QRCode.CorrectLevel.' . $correctLevel;
        echo '    });';
        echo '};';
        echo '</script>';
        
        return $footer;
    }
}
?>
