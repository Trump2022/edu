<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="renderer" content="webkit">
    <title>登录{$Auth_site_name}管理系统</title>
    <meta name="generator" content="CRMEB! v2.5" />
    <meta name="author" content="CRMEB! Team and CRMEB UI Team" />
    <meta name="copyright" content="2001-2013 CRMEB Inc." />
    <link href="{__FRAME_PATH}css/bootstrap.min.css?v=3.4.0" rel="stylesheet">
    <link href="{__FRAME_PATH}css/font-awesome.min.css?v=4.3.0" rel="stylesheet">
    <link href="{__FRAME_PATH}css/animate.min.css" rel="stylesheet">
    <link href="{__FRAME_PATH}css/style.min.css?v=3.0.0" rel="stylesheet">
    <link href="{__STATIC_PATH}plug/layer/mobile/need/layer.css" rel="stylesheet">
    <link rel="stylesheet" href="{__PLUG_PATH}captcha/css/verify.css">
    <script src="{__STATIC_PATH}plug/layer/mobile/layer.js"></script>
    <script>
        top != window && (top.location.href = location.href);
    </script>
    <style>
        body {
            background: url("{__ADMIN_PATH}images/login_bg.jpg") center/cover no-repeat;
        }

        .dialog {
            position: fixed;
            top: 50%;
            left: 50%;
            display: flex;
            border-radius: 6px;
            margin-top: -36px;
            overflow: hidden;
            transform: translate(-50%, -50%);
        }

        .dialog .poster {
            width: 286px;
            height: 400px;
        }

        .dialog .form {
            width: 384px;
            height: 400px;
            padding: 0 52px;
            background-color: #FFFFFF;
        }

        .dialog .logo {
            height: 117px;
        }

        .dialog .logo img {
            display: block;
            width: 117px;
            height: 117px;
            margin: 0 auto;
            object-fit: contain;
        }

        .dialog input {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            border: 1px solid #DDDDDD;
            border-radius: 3px;
            outline: none;
        }

        .dialog .input-wrap {
            margin-bottom: 22px;
        }

        .dialog .login-btn {
            width: 100%;
            height: 40px;
            border: none;
            border-radius: 3px;
            background-color: #338BFB;
            font-size: 14px;
            color: #FFFFFF;
            outline: none;
        }

        .dialog input::placeholder {
            color: #999999;
        }

        .login-label {
            position: absolute;
            top: 0;
            right: 0;
            width: 109px;
        }

        .footer a {
            text-decoration: none;
            color: #333333;
        }
    </style>
</head>

<body>
    <div class="dialog">
        <img class="login-label" src="{__ADMIN_PATH}images/login_label.png" alt="">
        <div>
            <img id="poster" class="poster" src="">
        </div>
        <div class="form">
            <div class="logo">
                <img id="logo" src="" alt="">
            </div>
            <div class="input-wrap">
                <input type="text" id="account" name="account" placeholder="账号">
            </div>
            <div class="input-wrap">
                <input type="password" id="pwd" name="pwd" placeholder="密码">
            </div>
            <div>
                <button class="login-btn">登录</button>
            </div>
        </div>
    </div>
    <div class="footer" style="position: fixed;bottom: 0;width: 100%;left: 0;margin: 0;opacity: 0.8;">
        <div class="pull-right" id="copyright"></div>
    </div>
    <div id="captcha"></div>
    <div id="verify" hidden></div>
    <script src="{__PLUG_PATH}jquery-1.10.2.min.js"></script>
    <script src="{__FRAME_PATH}js/bootstrap.min.js?v=3.4.0"></script>
    <script src="{__MODULE_PATH}login/ios-parallax.js"></script>
    <script src="{__PLUG_PATH}md5.min.js"></script>
    <script src="{__PLUG_PATH}captcha/js/crypto-js.js"></script>
    <script src="{__PLUG_PATH}captcha/js/ase.js"></script>
    <script src="{__PLUG_PATH}captcha/js/verify.js"></script>
    <script>
      var config = {
        DEFAULT_COPYRIGHT: 'Copyright © 2014-2024 CRMEB知识付费',
        VERSION: 'CRMEB-ZSFF-v2.1.8',
        REFRESH_INTERVAL: 60000, // 1分钟
        UPDATE_INTERVAL: 600000, // 6分钟
        notifyType: {
          special_num: { name: '专题审核' },
          store_num: { name: '商品审核' },
          downloa_num: { name: '资料审核' },
          even_num: { name: '活动审核' },
          certificate_num: { name: '证书审核' },
          test_num_2: { name: '考试审核' },
          test_num_1: { name: '练习审核' },
          live_num: { name: '直播审核' }
        }
      };
      // 更新版权信息
      function updateCopyright(copyrightText) {
        $('#copyright').html(copyrightText);
      }

      // 获取并更新版权信息
      function updateCopyrightFromServer() {
        $.ajax({
            url: "{:url('login/get_copyright')}",
            type: 'GET',
            dataType: 'json',
            success: function (res) {
                if (res.code === 200) {
                  if (res.data.nncnL_crmeb_copyright) {
                    updateCopyright(res.data.nncnL_crmeb_copyright);
                  } else {
                    updateCopyright(config.DEFAULT_COPYRIGHT + ' 版本号:<a href="http://www.crmeb.com/" target="_blank">' + config.VERSION + '</a>');
                  }
                } else if (res.code === 400) {
                  // 如果服务器返回错误，可以考虑显示默认版权信息或给出用户提示
                  updateCopyright(config.DEFAULT_COPYRIGHT + ' 版本号:<a href="http://www.crmeb.com/" target="_blank">' + config.VERSION + '</a>');
                }
            },
            error: function () {
              console.error('获取版权信息失败');
            }
        });
      }
      $(function () {
        var VERIFY_NUM_KEY = 'verify_num';
        var SUCCESS_CODE = 1;
        var NEED_CAPTCHA_CODE = 3;
        var REQUEST_ERROR_CODE = 4;

        var loginLeftImagePath = "{$login_left_image}" || '{__ADMIN_PATH}images/login_poster.png';
        var loginLogoPath = "{$login_logo}" || '{__ADMIN_PATH}images/login_logo.gif';

        var num = Number(localStorage.getItem(VERIFY_NUM_KEY)) || 0;
        var data = {};

        $('#poster').attr('src', loginLeftImagePath);
        $('#logo').attr('src', loginLogoPath);
        updateCopyrightFromServer(); // 更新版权信息

        function showMessage(message, isSuccess = false) {
          layer.open({
            content: message,
            skin: isSuccess ? 'msg' : 'msg error',
            time: 1
          });
        }

        function handleLogin(data) {
          $.ajax({
            url: "{:url('verify')}",
            type: 'post',
            data: data,
            dataType: 'json',
            success: function (res) {
              switch (res.code) {
                case SUCCESS_CODE:
                  localStorage.removeItem(VERIFY_NUM_KEY);
                  window.location.replace("{:url('index/index')}");
                  break;
                case NEED_CAPTCHA_CODE:
                  if (data.captchaType === undefined) {
                    num = Math.min(localStorage.getItem(VERIFY_NUM_KEY) || 0 + 1, 2);
                    localStorage.setItem(VERIFY_NUM_KEY, num);
                    handleSubmit();
                  } else {
                    showMessage('滑块验证错误');
                  }
                  break;
                case REQUEST_ERROR_CODE:
                  showMessage('请求方式错误');
                  break;
                default:
                  num = res.num || 0;
                  localStorage.setItem(VERIFY_NUM_KEY, num);
                  showMessage(res.msg, false);
                  break;
              }
            },
            error: function (err) {
              showMessage('登录失败！', false);
            }
          });
        }

        function handleSubmit() {
          var account = $.trim($('#account').val());
          var pwd = $.trim($('#pwd').val());
          if (!account) {
            showMessage('请输入账号');
            return;
          }
          if (!pwd) {
            showMessage('请输入密码');
            return;
          }
          data.account = account;
          data.pwd = md5(pwd);
          var num = Number(localStorage.getItem(VERIFY_NUM_KEY)) || 0;
          if (num >= 2) {
            $('#verify').trigger('click');
          } else {
            handleLogin(data);
          }
        }

        $('.login-btn').on('click', handleSubmit);
        $(document).on('keydown', function (event) {
          if (event.keyCode === 13) {
            handleSubmit();
          }
        });

        $('#captcha').slideVerify({
          baseUrl: '/admin/login',
          mode: 'pop',
          containerId: 'verify',
          beforeCheck: function () {
            var flag = true;
            return flag;
          },
          ready: function () { },
          success: function (params) {
            data.captchaVerification = params.captchaVerification;
            data.captchaType = 'blockPuzzle';
            handleLogin(data);
          },
          error: function () { },
          beforeShow: function () {
            var flag = true;
            return flag;
          }
        });
      });
    </script>
</body>

</html>
