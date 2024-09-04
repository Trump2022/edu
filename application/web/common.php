<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016～2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


// 应用公共文件
if (!function_exists('unThumb')) {
    function unThumb($src)
    {
        return str_replace('/s_', '/', $src);
    }
}

function Trust($URL)
{
    $isSrc = false;
    if (is_array($URL)) {
        $URL = isset($URL[4]) ? $URL[4] : '';
        $isSrc = true;
        $URL = strpos($URL, 'src=') !== false ? substr($URL, 4) : $URL;
    }
    if ($URL == '') return '';
    $URL = str_replace(['"', '\''], '', $URL);
    $strKs3 = 'ks3-cn-beijing.ksyun.com/buttomsup';
    $strKs4 = 'ks3-cn-beijing.ksyun.com/learn';
    if (strpos($URL, $strKs3) !== false) {
        $strCdn = str_replace($strKs3, 'buttomsup.dounixue.net', $URL);
    } else {
        $strCdn = str_replace($strKs4, 'cdn.dounixue.net', $URL);
    }
    $strCdn = str_replace('https', 'http', $strCdn);
    $slhttp = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https:' : 'http:';
    if ($slhttp == 'https:') {
        $strCdn = str_replace('http', 'https', $strCdn);
    } elseif ($slhttp == 'http:') {
        $strCdn = str_replace('https', 'http', $strCdn);
    }
    $pathinfo = pathinfo($URL);
    $t = substr(time(), 0, 10);
    $k = substr(md5('ptteng' . urldecode($pathinfo['filename']) . $t), 0, 16);
    if ($isSrc) {
        return 'scr="' . $strCdn . "?k={$k}&t={$t}\"";
    } else {
        return $strCdn . "?k={$k}&t={$t}";
    }
}

/**
 * 图片处理
 * @param $link
 * @param int $type 1=大图,2=宫图,3=全图,4=小图
 * @return string
 */
function get_oss_process($link, $type = 0)
{
    switch ($type) {
        case 1:
        case 4:
            $link .= '?x-oss-process=image/resize,m_lfit,h_254,w_690';
            break;
        case 2:
            $link .= '?x-oss-process=image/resize,m_lfit,h_200,w_330';
            break;
        case 3:
            $link .= '?x-oss-process=image/resize,m_lfit,h_130,w_219';
            break;
    }
    return $link;
}
